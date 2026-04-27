<?php
namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;
use WAS\Inbox\InboundMessageService;
use WAS\Inbox\ContactRepository;
use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MessageRepository;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookProcessor {
    /**
     * Processa o payload bruto do webhook.
     */
    public function process($payload) {
        global $wpdb;

        // 1. Identificar IDs básicos
        $phone_number_id = $this->extract_phone_number_id($payload);
        $waba_id = $payload['entry'][0]['id'] ?? null;

        // 2. Resolver Tenant pelo phone_number_id
        $tenant_id = $this->find_tenant_by_phone_number_id($phone_number_id);

        // 3. Salvar evento bruto
        $event_table = TableNameResolver::get_table_name('webhook_events');
        $wpdb->insert($event_table, [
            'tenant_id'       => $tenant_id,
            'waba_id'         => $waba_id,
            'phone_number_id' => $phone_number_id,
            'payload'         => json_encode($payload),
            'processing_status' => 'pending',
            'received_at'     => current_time('mysql', 1)
        ]);
        $event_id = $wpdb->insert_id;

        // 4. Se for mensagem, rotear para InboundMessageService
        if ($this->is_message_event($payload)) {
            try {
                $success = $this->handle_message_event($payload, $tenant_id, $phone_number_id, $event_id);
                if (!$success) {
                    \WAS\Core\SystemLogger::logError('InboundMessageService retornou false ao processar mensagem.', [
                        'payload_summary' => ['waba_id' => $waba_id, 'phone' => $phone_number_id]
                    ]);
                }
            } catch (\Throwable $e) {
                \WAS\Core\SystemLogger::logException($e, [
                    'context'         => 'WebhookProcessor::process',
                    'tenant_id'       => $tenant_id,
                    'phone_number_id' => $phone_number_id,
                    'event_id'        => $event_id
                ]);
            }
        }

        // Marcar como processado
        $wpdb->update($event_table, 
            ['processing_status' => 'processed', 'processed_at' => current_time('mysql', 1)],
            ['id' => $event_id]
        );

        return true;
    }

    private function extract_phone_number_id($payload) {
        return $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;
    }

    private function find_tenant_by_phone_number_id($phone_number_id) {
        if (!$phone_number_id) return null;
        global $wpdb;
        $table = TableNameResolver::get_table_name('whatsapp_phone_numbers');
        return $wpdb->get_var($wpdb->prepare("SELECT tenant_id FROM $table WHERE phone_number_id = %s", $phone_number_id));
    }

    private function is_message_event($payload) {
        return isset($payload['entry'][0]['changes'][0]['value']['messages']);
    }

    private function handle_message_event($payload, $tenant_id, $phone_number_id, $event_id) {
        $value = $payload['entry'][0]['changes'][0]['value'];
        $message = $value['messages'][0];
        $contact = $value['contacts'][0] ?? [];

        // Normalizar DTO para o InboundMessageService
        $dto = [
            'tenant_id'       => $tenant_id,
            'phone_number_id' => $phone_number_id,
            'wa_message_id'   => $message['id'],
            'from'            => $message['from'],
            'profile_name'    => $contact['profile']['name'] ?? '',
            'message_type'    => $message['type'],
            'text_body'       => $message['text']['body'] ?? '',
            'timestamp'       => $message['timestamp'],
            'raw_event_id'    => $event_id,
        ];

        // Injetar dependências manualmente para o serviço
        $service = new InboundMessageService(
            new ContactRepository(),
            new ConversationRepository(),
            new MessageRepository()
        );

        return $service->handle($dto);
    }
}
