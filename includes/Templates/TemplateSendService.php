<?php
namespace WAS\Templates;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\PhoneNumberService;
use WAS\Inbox\MessageRepository;
use WAS\Inbox\ContactRepository;
use WAS\Inbox\ConversationRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSendService {
    private $api_client;
    private $token_service;
    private $message_repo;
    private $contact_repo;
    private $conversation_repo;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo = new MessageRepository();
        $this->contact_repo = new ContactRepository();
        $this->conversation_repo = new ConversationRepository();
    }

    /**
     * Envia um template aprovado.
     */
    public function send($conversation_id, $template_id, $variables = [], $button_variables = [], $to_phone = null) {
        $tenant_id = TenantContext::get_tenant_id();
        
        $repository = new TemplateRepository();
        $template = $repository->get_by_id($template_id);
        if (!$template) return ['success' => false, 'error' => 'Template não encontrado'];

        $to = $to_phone;
        
        // Resolve conversa se necessário
        if (!$to && $conversation_id) {
            $conversation = $this->conversation_repo->get_by_id($conversation_id);
            if ($conversation) {
                $contact = $this->contact_repo->get_by_id($conversation->contact_id);
                if ($contact) $to = $contact->wa_id;
            }
        }

        if (!$to) return ['success' => false, 'error' => 'Destinatário não informado'];
        $to = preg_replace('/\D/', '', $to);

        if (!$conversation_id && $to) {
            $contact = $this->contact_repo->find_or_create_by_wa_id($to, 'Novo Contato', $to);
            if (!$contact) {
                \WAS\Core\SystemLogger::logError('TemplateSendService: Falha ao encontrar/criar contato.', [
                    'to' => $to,
                    'tenant_id' => $tenant_id,
                ]);
                return ['success' => false, 'error' => 'Não foi possível encontrar ou criar o contato.'];
            }
            $contact_id = $contact->id;
            $conversation = $this->conversation_repo->find_open_by_contact($contact_id);
            if (!$conversation) {
                $conversation_id = $this->conversation_repo->create(['contact_id' => $contact_id, 'status' => 'open']);
            } else {
                $conversation_id = $conversation->id;
            }
        }

        $phone_service = new PhoneNumberService();
        $phone_number_id = $phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) return ['success' => false, 'error' => 'Configuração de envio incompleta.'];

        // Mapa de variáveis
        $variable_map = [];
        try { if ($template->variable_map) $variable_map = json_decode($template->variable_map, true); } catch (\Exception $e) {}

        // Montar componentes e renderizar prévia
        $components = [];
        $rendered_body = $template->body_text;
        $rendered_header = $template->header_type === 'TEXT' ? $template->header_text : '';
        $rendered_footer = $template->footer_text;

        // Processa variáveis do BODY
        if (!empty($variable_map)) {
            $body_params = [];
            ksort($variable_map);
            foreach ($variable_map as $pos => $friendlyName) {
                $val = $variables[$friendlyName] ?? $variables[$pos] ?? ''; 
                $body_params[] = ['type' => 'text', 'text' => (string)$val];
                $rendered_body = str_replace("{{{$friendlyName}}}", (string)$val, $rendered_body);
            }
            if (!empty($body_params)) {
                $components[] = ['type' => 'body', 'parameters' => $body_params];
            }
        }

        // Processa variáveis do HEADER (Se houver)
        // Atualmente o builder salva header_text fixo, mas se no futuro tiver {{var}} no header:
        // foreach($variables as $k => $v) { $rendered_header = str_replace("{{$k}}", $v, $rendered_header); }

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

        $response = $this->api_client->postJson('messages.send', ['phone_number_id' => $phone_number_id], $payload, $token);

        if ($response['success']) {
            // Salva um snapshot renderizado para o Inbox
            $history_snapshot = [
                'name' => $template->name,
                'header' => $rendered_header,
                'body' => $rendered_body,
                'footer' => $rendered_footer,
                'buttons' => json_decode($template->buttons_json, true) ?: []
            ];

            $this->message_repo->create_outbound([
                'conversation_id' => $conversation_id,
                'wa_message_id'   => $response['messages'][0]['id'] ?? null,
                'message_type'    => 'template',
                'text_body'       => $rendered_body, 
                'status'          => 'sent',
                'raw_payload'     => json_encode($history_snapshot), // Salva o snapshot amigável
                'tenant_id'       => $tenant_id
            ]);

            $this->conversation_repo->update_last_message_at($conversation_id);

            return [
                'success' => true, 
                'wa_message_id' => $response['messages'][0]['id'],
                'conversation_id' => $conversation_id,
                'rendered_body' => $rendered_body,
                'rendered_header' => $rendered_header,
                'rendered_footer' => $rendered_footer,
                'buttons' => $history_snapshot['buttons']
            ];
        }

        return $response;
    }
}
