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
            $dto['profile_name'] ?? ''
        );

        if (!$contact) {
            return false;
        }

        // 4. Encontrar ou criar conversa aberta
        $conversation = $this->conversation_repo->find_or_create_open_conversation($contact->id);

        if (!$conversation) {
            return false;
        }

        // 5. Salvar a mensagem
        $type = $dto['type'] ?? 'text';
        $body = $dto['text_body'] ?? '';

        // Fallback para tipos não suportados
        if (!in_array($type, ['text', 'image', 'video', 'audio', 'document', 'sticker'])) {
            $type = 'unknown';
            $body = $body ?: '[Tipo de mensagem não suportado]';
        }

        $message_id = $this->message_repo->create_inbound([
            'conversation_id' => $conversation->id,
            'wa_message_id'   => $dto['wa_message_id'],
            'message_type'    => $type,
            'text_body'       => $body,
            'status'          => 'received'
        ]);

        if ($message_id) {
            // 6. Atualizar timestamp da conversa
            $this->conversation_repo->update_last_message_at($conversation->id);
            return true;
        }

        return false;
    }
}
