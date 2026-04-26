<?php

namespace WAS\REST;

use WAS\Templates\TemplateService;
use WAS\Templates\TemplateSyncService;
use WAS\Templates\TemplateRepository;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para Templates
 */
class TemplateApiController {
    private $service;
    private $syncService;

    public function __construct() {
        $repository = new TemplateRepository();
        $this->service = new TemplateService($repository);
        $this->syncService = new TemplateSyncService($repository);
    }
    
    /**
     * Busca um template específico
     */
    public function get_item(WP_REST_Request $request) {
        $id = $request->get_param('id');
        $template = $this->service->repository->find($id);

        if (!$template) {
            return new WP_REST_Response(['message' => 'Template não encontrado'], 404);
        }

        // Decodifica payloads para facilitar pro JS
        if (!empty($template->friendly_payload)) {
            $template->friendly_payload = json_decode($template->friendly_payload);
        }
        if (!empty($template->buttons_json)) {
            $template->buttons_json = json_decode($template->buttons_json);
        }

        return new WP_REST_Response($template, 200);
    }

    /**
     * Atualiza um template
     */
    public function update_item(WP_REST_Request $request) {
        $id = $request->get_param('id');
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['language']) || empty($params['category']) || empty($params['body']['text'])) {
            return new WP_REST_Response(['message' => 'Campos obrigatórios ausentes'], 400);
        }

        try {
            // Em produção Meta, edição de templates aprovados é restrita. 
            // Aqui permitimos atualização local/demo.
            $data = [
                'id'               => $id,
                'name'             => strtolower(preg_replace('/[^a-z0-9_]/', '', $params['name'])),
                'language'         => $params['language'],
                'category'         => $params['category'],
                'friendly_payload' => json_encode($params),
                'body_text'        => $params['body']['text'],
                'header_type'      => $params['header']['type'] ?? 'NONE',
                'footer_text'      => $params['footer']['text'] ?? '',
                'buttons_json'     => json_encode($params['buttons'] ?? []),
                'status'           => \WAS\Core\Plugin::is_demo_mode() ? 'APPROVED' : 'PENDING'
            ];

            $this->service->repository->createOrUpdate($data);
            
            \WAS\Compliance\AuditLogger::log('update_template', 'template', $id, ['name' => $data['name']]);

            return new WP_REST_Response(['message' => 'Template atualizado com sucesso'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza templates
     */
    public function sync_templates(WP_REST_Request $request) {
        $count = $this->syncService->sync();
        return new WP_REST_Response(['message' => "$count templates sincronizados."], 200);
    }

    /**
     * Lista templates
     */
    public function get_items(WP_REST_Request $request) {
        $status = $request->get_param('status');
        $templates = $this->service->listTemplates($status);

        return new WP_REST_Response($templates, 200);
    }

    /**
     * Cria um template
     */
    public function create_item(WP_REST_Request $request) {
        $params = $request->get_json_params();
        
        // Backward compatibility fallback for simple requests
        if (!isset($params['body']['text']) && !empty($params['body_text'])) {
            $params['body'] = ['text' => $params['body_text']];
        }

        if (empty($params['name']) || empty($params['language']) || empty($params['category']) || empty($params['body']['text'])) {
            return new WP_REST_Response(['message' => 'Campos obrigatórios ausentes'], 400);
        }

        try {
            $id = $this->service->createTemplate($params);
            return new WP_REST_Response(['id' => $id, 'message' => 'Template criado com sucesso'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Envia um template
     */
    public function send_template(WP_REST_Request $request) {
        $id = $request->get_param('id');
        $params = $request->get_json_params();

        $to = $params['to'] ?? null;
        $conversation_id = $params['conversation_id'] ?? null;

        $contact_repo = new \WAS\Inbox\ContactRepository();
        $conversation_repo = new \WAS\Inbox\ConversationRepository();
        $msg_repo = new \WAS\Inbox\MessageRepository();

        // If we only have the phone number, find or create the contact and conversation
        if (empty($conversation_id) && !empty($to)) {
            $contact = $contact_repo->find_or_create_by_wa_id($to, $to);
            if ($contact) {
                $conversation = $conversation_repo->find_or_create_open_conversation($contact->id);
                if ($conversation) {
                    $conversation_id = $conversation->id;
                }
            }
        }

        // If we have conversation_id but not the 'to' (wa_id), find the wa_id
        if (empty($to) && !empty($conversation_id)) {
            global $wpdb;
            $table_conv = \WAS\Core\TableNameResolver::get_table_name('conversations');
            $table_cont = \WAS\Core\TableNameResolver::get_table_name('contacts');
            
            $wa_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ct.wa_id FROM $table_conv cv 
                 JOIN $table_cont ct ON cv.contact_id = ct.id 
                 WHERE cv.id = %d",
                $conversation_id
            ));
            
            if ($wa_id) {
                $to = $wa_id;
            }
        }

        if (empty($to)) {
            return new WP_REST_Response(['message' => 'Destinatário não identificado'], 400);
        }

        try {
            $this->service->sendTemplateMessage($to, $id, $params['components'] ?? []);

            // Persistir no histórico do chat
            if (!empty($conversation_id)) {
                $template = $this->service->repository->find($id);
                $friendly = json_decode($template->friendly_payload);

                $body_text = $template->body_text;
                $variables = $params['variables'] ?? [];

                // Replace variables in body text for local display
                foreach ($variables as $key => $val) {
                    $body_text = str_replace('{{' . $key . '}}', $val, $body_text);
                }

                $msg_repo = new \WAS\Inbox\MessageRepository();
                $msg_repo->create_outbound([
                    'conversation_id' => $conversation_id,
                    'message_type'    => 'template',
                    'text_body'       => $body_text,
                    'raw_payload'     => json_encode([
                        'header'  => ($template->header_type === 'TEXT') ? ($friendly->header->text ?? '') : null,
                        'body'    => $body_text,
                        'footer'  => $template->footer_text,
                        'buttons' => json_decode($template->buttons_json)
                    ]),
                    'status'          => 'sent',
                    'wa_message_id'   => 'tpl_' . time()
                ]);
                $conversation_repo->update_last_message_at($conversation_id);
            }



            return new WP_REST_Response(['message' => 'Template enviado com sucesso', 'success' => true], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Callback de permissão simples (será refinado com CapabilityService)
     */
    public function permissions_check() {
        return current_user_can('manage_options'); // Placeholder para was_manage_templates
    }
}
