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

        // If conversation_id is provided, find the contact's wa_id
        if (empty($to) && !empty($params['conversation_id'])) {
            global $wpdb;
            $table_conv = \WAS\Core\TableNameResolver::get_table_name('conversations');
            $table_cont = \WAS\Core\TableNameResolver::get_table_name('contacts');
            
            $wa_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ct.wa_id FROM $table_conv cv 
                 JOIN $table_cont ct ON cv.contact_id = ct.id 
                 WHERE cv.id = %d",
                $params['conversation_id']
            ));
            
            if ($wa_id) {
                $to = $wa_id;
            }
        }

        if (empty($to)) {
            return new WP_REST_Response(['message' => 'Destinatário ausente'], 400);
        }

        try {
            $this->service->sendTemplateMessage($to, $id, $params['components'] ?? []);
            return new WP_REST_Response(['message' => 'Template enviado para processamento', 'success' => true], 200);
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
