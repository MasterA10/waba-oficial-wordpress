<?php
namespace WAS\Inbox;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\PhoneNumberService;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class OutboundMessageService {
    private $message_repo;
    private $conversation_repo;
    private $phone_service;
    private $token_service;
    private $api_client;

    public function __construct() {
        $this->message_repo = new MessageRepository();
        $this->conversation_repo = new ConversationRepository();
        $this->phone_service = new PhoneNumberService();
        $this->token_service = new TokenService();
        $this->api_client = new MetaApiClient();
    }

    /**
     * Envia uma mensagem de texto livre.
     */
    public function send_text($conversation_id, $text) {
        $tenant_id = TenantContext::get_tenant_id();
        
        $conversation = $this->conversation_repo->get_by_id($conversation_id);
        if (!$conversation) return ['success' => false, 'error' => 'Conversa não encontrada'];

        $contact_repo = new ContactRepository();
        $contact = $contact_repo->get_by_id($conversation->contact_id);
        if (!$contact) return ['success' => false, 'error' => 'Contato não encontrado'];

        $phone_number_id = $this->phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) {
            \WAS\Compliance\AuditLogger::log('send_text_error', 'conversation', $conversation_id, [
                'error' => 'Missing config',
                'has_phone' => !!$phone_number_id,
                'has_token' => !!$token
            ]);
            return ['success' => false, 'error' => 'Configuração de envio (número/token) incompleta'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $contact->wa_id,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text,
            ],
        ];

        $response = $this->api_client->postJson(
            'messages.send',
            ['phone_number_id' => $phone_number_id],
            $payload,
            $token
        );

        if ($response['success']) {
            $wa_message_id = $response['messages'][0]['id'] ?? null;
            
            \WAS\Compliance\AuditLogger::log('send_text_success', 'conversation', $conversation_id, [
                'wa_message_id' => $wa_message_id
            ]);

            // Salvar no repositório local
            $this->message_repo->create_outbound([
                'conversation_id' => $conversation_id,
                'wa_message_id'   => $wa_message_id,
                'message_type'    => 'text',
                'text_body'       => $text,
                'status'          => 'sent'
            ]);

            $this->conversation_repo->update_last_message_at($conversation_id);
            
            return ['success' => true, 'wa_message_id' => $wa_message_id];
        }

        return $response;
    }
}
