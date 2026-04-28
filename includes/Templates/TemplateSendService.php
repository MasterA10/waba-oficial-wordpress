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
     * 
     * @param int|null $conversation_id ID da conversa local (opcional se to_phone for informado)
     * @param int $template_id ID do template local
     * @param array $variables Variáveis amigáveis [ 'nome' => 'Ana' ]
     * @param array $button_variables Variáveis de botão (se houver)
     * @param string|null $to_phone Número de destino
     */
    public function send($conversation_id, $template_id, $variables = [], $button_variables = [], $to_phone = null) {
        $tenant_id = TenantContext::get_tenant_id();
        
        $repository = new TemplateRepository();
        $template = $repository->get_by_id($template_id);
        if (!$template) return ['success' => false, 'error' => 'Template não encontrado'];

        $to = $to_phone;
        if (!$to && $conversation_id) {
            global $wpdb;
            $prefix = \WAS\Core\TableNameResolver::get_table_name("");
            $contact_id = $wpdb->get_var($wpdb->prepare("SELECT contact_id FROM {$prefix}conversations WHERE id = %d", $conversation_id));
            $to = $wpdb->get_var($wpdb->prepare("SELECT wa_id FROM {$prefix}contacts WHERE id = %d", $contact_id));
        }

        if (!$to) return ['success' => false, 'error' => 'Destinatário não informado'];
        $to = preg_replace('/\D/', '', $to);

        $phone_service = new PhoneNumberService();
        $phone_number_id = $phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) return ['success' => false, 'error' => 'Configuração de envio incompleta.'];

        // Mapa de variáveis: [ "1" => "nome", "2" => "pedido" ]
        $variable_map = [];
        try {
            if ($template->variable_map) {
                $variable_map = json_decode($template->variable_map, true);
            }
        } catch (\Exception $e) {}

        // Montar componentes de envio conforme o mapa
        $components = [];

        // 1. BODY Parameters
        if (!empty($variable_map)) {
            $body_params = [];
            // Itera pelas posições 1, 2, 3... conforme o mapa salvo
            ksort($variable_map);
            foreach ($variable_map as $pos => $friendlyName) {
                $val = $variables[$friendlyName] ?? $variables[$pos] ?? ''; 
                $body_params[] = ['type' => 'text', 'text' => (string)$val];
            }
            if (!empty($body_params)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $body_params
                ];
            }
        }

        // 2. BUTTON Parameters (Futuro/Avançado)
        // Se houver lógica de botão dinâmico no friendly_payload, ela deve ser tratada aqui.
        // Por enquanto, o MVP foca no body.

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
            // Registrar mensagem outbound
            $this->message_repo->create_outbound([
                'conversation_id' => $conversation_id,
                'wa_message_id'   => $response['messages'][0]['id'] ?? null,
                'message_type'    => 'template',
                'text_body'       => $template->body_text, 
                'status'          => 'sent',
                'raw_payload'     => json_encode($payload),
                'tenant_id'       => $tenant_id
            ]);
            return ['success' => true, 'wa_message_id' => $response['messages'][0]['id']];
        }

        return $response;
    }
}
