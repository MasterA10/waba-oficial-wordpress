<?php
namespace WAS\Templates;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\PhoneNumberService;
use WAS\Inbox\MessageRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSendService {
    private $api_client;
    private $token_service;
    private $message_repo;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo = new MessageRepository();
    }

    /**
     * Envia um template aprovado.
     */
    public function send($conversation_id, $template_id, $variables = [], $button_variables = []) {
        $tenant_id = TenantContext::get_tenant_id();
        
        $repository = new TemplateRepository();
        $template = $repository->get_by_id($template_id);
        if (!$template) return ['success' => false, 'error' => 'Template não encontrado'];

        // Buscar dados do contato
        global $wpdb;
        $prefix = \WAS\Core\TableNameResolver::get_table_name("");
        $contact_id = $wpdb->get_var($wpdb->prepare("SELECT contact_id FROM {$prefix}conversations WHERE id = %d", $conversation_id));
        $to = $wpdb->get_var($wpdb->prepare("SELECT wa_id FROM {$prefix}contacts WHERE id = %d", $contact_id));

        $phone_service = new PhoneNumberService();
        $phone_number_id = $phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) return ['success' => false, 'error' => 'Envio não configurado'];

        // Montar componentes de envio
        $components = [];

        // Body Parameters
        if (!empty($variables)) {
            $params = [];
            foreach ($variables as $val) {
                $params[] = ['type' => 'text', 'text' => (string)$val];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $params
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template->name,
                'language' => ['code' => $template->language],
                'components' => $components
            ]
        ];

        $response = $this->api_client->postJson(
            'messages.send',
            ['phone_number_id' => $phone_number_id],
            $payload,
            $token
        );

        if ($response['success']) {
            $this->message_repo->create_outbound([
                'conversation_id' => $conversation_id,
                'wa_message_id'   => $response['messages'][0]['id'] ?? null,
                'message_type'    => 'template',
                'text_body'       => $template->body_text, // Representação simplificada
                'status'          => 'sent',
                'raw_payload'     => json_encode($payload)
            ]);
            return ['success' => true, 'wa_message_id' => $response['messages'][0]['id']];
        }

        return $response;
    }
}
