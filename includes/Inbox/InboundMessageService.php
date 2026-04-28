<?php
namespace WAS\Inbox;

use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class InboundMessageService {
    private $contact_repo;
    private $conversation_repo;
    private $message_repo;

    public function __construct(
        ContactRepository $contact_repo,
        ConversationRepository $conversation_repo,
        MessageRepository $message_repo
    ) {
        $this->contact_repo = $contact_repo;
        $this->conversation_repo = $conversation_repo;
        $this->message_repo = $message_repo;
    }

    /**
     * Processa uma mensagem inbound normalizada.
     * 
     * @param array $dto DTO normalizado vindo do WebhookProcessor.
     * @return bool
     */
    public function handle($dto) {
        // 1. Garantir que o contexto do tenant está correto (resolvido pelo WebhookProcessor)
        if (isset($dto['tenant_id'])) {
            TenantContext::set_tenant_id($dto['tenant_id']);
        }

        // 2. Verificar duplicidade
        if ($this->message_repo->find_by_wa_message_id($dto['wa_message_id'])) {
            return true; // Já processada
        }

        // 3. Encontrar ou criar contato
        $contact = $this->contact_repo->find_or_create_by_wa_id(
            $dto['from'], 
            $dto['profile_name'] ?? '',
            $dto['from'] // Usa o wa_id como telefone por padrão
        );

        if (!$contact) {
            return false;
        }

        // 4. Encontrar ou criar conversa aberta
        $conversation = $this->conversation_repo->find_or_create_open_conversation(
            $contact->id,
            $dto['phone_number_id'] ?? ''
        );

        if (!$conversation) {
            return false;
        }

        // 5. Salvar a mensagem
        $type = $dto['message_type'] ?? $dto['type'] ?? 'text';
        $body = $dto['text_body'] ?? '';

        // Fallback para tipos não suportados
        if (!in_array($type, ['text', 'image', 'video', 'audio', 'document', 'sticker'])) {
            $type = 'unknown';
            $body = $body ?: '[Tipo de mensagem não suportado]';
        }

        // Resolver reply_to_message_id local
        $reply_to_message_id = null;
        $reply_to_wa_message_id = null;
        $context_from = null;
        $context_payload = null;

        if (!empty($dto['reply_context']['has_context'])) {
            $reply_to_wa_message_id = $dto['reply_context']['reply_to_wa_message_id'];
            $context_from = $dto['reply_context']['context_from'];
            $context_payload = $dto['reply_context']['context_payload'];

            $original_msg = $this->message_repo->find_by_wa_message_id($reply_to_wa_message_id);
            if ($original_msg) {
                $reply_to_message_id = $original_msg->id;
            }
        }

        $message_id = $this->message_repo->create_inbound([
            'conversation_id'        => $conversation->id,
            'wa_message_id'          => $dto['wa_message_id'],
            'message_type'           => $type,
            'text_body'              => $body,
            'status'                 => 'received',
            'reply_to_message_id'    => $reply_to_message_id,
            'reply_to_wa_message_id' => $reply_to_wa_message_id,
            'context_from'           => $context_from,
            'context_payload'        => $context_payload,
            'raw_payload'            => isset($dto['raw_message']) ? wp_json_encode($dto['raw_message']) : null,
        ]);

        if ($message_id) {
            // 6. Se for mídia, baixar arquivo
            if (in_array($type, ['image', 'video', 'audio', 'document']) && !empty($dto['media_data']['id'])) {
                try {
                    $media_service = new \WAS\WhatsApp\InboundMediaService();
                    $media_service->handle_inbound_media(
                        $dto['tenant_id'],
                        $conversation->id,
                        $message_id,
                        $dto['media_data']['id'],
                        $type,
                        $dto['media_data']['mime_type'] ?? ''
                    );
                } catch (\Throwable $e) {
                    \WAS\Core\SystemLogger::logException($e, ['context' => 'InboundMessageService::handle_media', 'message_id' => $message_id]);
                }
            }

            // 7. Atualizar timestamp da conversa
            $this->conversation_repo->update_last_message_at($conversation->id);
            return true;
        }

        return false;
    }
}
