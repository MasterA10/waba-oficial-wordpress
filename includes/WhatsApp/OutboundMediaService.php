<?php
namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Inbox\MessageRepository;
use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MediaRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class OutboundMediaService {
    private $api_client;
    private $token_service;
    private $message_repo;
    private $media_repo;
    private $conversation_repo;
    private $validation_service;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo = new MessageRepository();
        $this->media_repo = new MediaRepository();
        $this->conversation_repo = new ConversationRepository();
        $this->validation_service = new MediaValidationService();
    }

    public function send_media($conversation_id, $filePath, $mimeType, $mediaType, $caption = '', $filename = '') {
        $tenant_id = TenantContext::get_tenant_id();
        
        $conversation = $this->conversation_repo->get_by_id($conversation_id);
        if (!$conversation) {
            \WAS\Core\SystemLogger::logError('Tentativa de envio de mídia em conversa inexistente.', ['id' => $conversation_id]);
            throw new \Exception('Conversa não encontrada.');
        }

        try {
            $this->validation_service->validate($filePath, $mimeType, $mediaType);
        } catch (\Exception $e) {
            \WAS\Core\SystemLogger::logError('Falha na validação de mídia.', [
                'conversation_id' => $conversation_id,
                'media_type'      => $mediaType,
                'mime'            => $mimeType,
                'error'           => $e->getMessage()
            ]);
            throw $e;
        }

        $phone_service = new PhoneNumberService();
        $phone_number_id = $phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) {
            \WAS\Core\SystemLogger::logError('Configuração de envio de mídia incompleta.', ['tenant_id' => $tenant_id]);
            throw new \Exception('Configuração de envio incompleta.');
        }

        // 1. Registrar mídia localmente
        $media_id = $this->media_repo->create([
            'conversation_id' => $conversation_id,
            'media_type'      => $mediaType,
            'mime_type'       => $mimeType,
            'filename'        => basename($filePath),
            'file_size'       => filesize($filePath),
            'direction'       => 'outbound',
            'status'          => 'validated'
        ]);

        // 2. Upload para Meta
        $uploadResponse = $this->api_client->uploadMedia($phone_number_id, $filePath, $mimeType, $token);
        if (!$uploadResponse['success']) {
            $this->media_repo->update($media_id, ['status' => 'failed', 'error_message' => $uploadResponse['error']]);
            \WAS\Core\SystemLogger::logError('Falha no upload de mídia para a Meta.', [
                'media_id' => $media_id,
                'error'    => $uploadResponse['error']
            ]);
            return $uploadResponse;
        }

        $meta_media_id = $uploadResponse['id'];
        $this->media_repo->mark_uploaded($media_id, $meta_media_id);

        // 3. Resolver destinatário
        $contact_repo = new \WAS\Inbox\ContactRepository();
        $contact = $contact_repo->get_by_id($conversation->contact_id);
        if (!$contact) throw new \Exception('Contato não encontrado.');

        // 4. Montar Payload de Mensagem
        $mediaObject = ['id' => $meta_media_id];
        if ($caption && $mediaType !== 'audio') $mediaObject['caption'] = $caption;
        if ($filename && $mediaType === 'document') $mediaObject['filename'] = $filename;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $contact->wa_id,
            'type'              => $mediaType,
            $mediaType          => $mediaObject
        ];

        // 5. Enviar Mensagem
        $sendResponse = $this->api_client->postJson('messages.send', ['phone_number_id' => $phone_number_id], $payload, $token);
        if ($sendResponse['success']) {
            $wa_message_id = $sendResponse['messages'][0]['id'] ?? null;
            
            // 6. Registrar mensagem e vincular mídia
            $message_id = $this->message_repo->create_outbound([
                'conversation_id' => $conversation_id,
                'wa_message_id'   => $wa_message_id,
                'message_type'    => $mediaType,
                'text_body'       => $caption ?: basename($filePath),
                'status'          => 'sent',
                'raw_payload'     => json_encode($payload)
            ]);

            $this->media_repo->attach_message($media_id, $message_id);
            $this->media_repo->update($media_id, ['status' => 'sent']);
            $this->conversation_repo->update_last_message_at($conversation_id);

            \WAS\Compliance\AuditLogger::log('media_sent', 'media', $media_id, [
                'conversation_id' => $conversation_id,
                'media_type'      => $mediaType,
                'wa_message_id'   => $wa_message_id
            ]);

            return ['success' => true, 'wa_message_id' => $wa_message_id, 'media_id' => $media_id];
        }

        \WAS\Core\SystemLogger::logError('Falha ao enviar mensagem de mídia.', [
            'media_id' => $media_id,
            'payload'  => $payload,
            'response' => $sendResponse
        ]);

        return $sendResponse;
    }
}
