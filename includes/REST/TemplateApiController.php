<?php
namespace WAS\REST;

use WAS\Templates\TemplateRepository;
use WAS\Templates\TemplatePayloadBuilder;
use WAS\Templates\TemplateMetaService;
use WAS\Auth\TenantContext;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateApiController {
    private $repository;
    private $builder;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->builder = new TemplatePayloadBuilder();
    }

    /**
     * Lista templates do tenant.
     */
    public function get_items(WP_REST_Request $request) {
        $templates = $this->repository->list_templates();
        return new WP_REST_Response($templates, 200);
    }

    /**
     * Cria um novo template na Meta e salva localmente.
     */
    public function create_item(WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (empty($params['name']) || empty($params['body']['text'])) {
            return new WP_REST_Response(['message' => 'Nome e conteúdo do corpo são obrigatórios'], 400);
        }

        // 1. Construir payload oficial
        try {
            $meta_payload = $this->builder->build($params);
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }

        // 2. Salvar rascunho local primeiro
        $local_id = $this->repository->create([
            'name' => $params['name'],
            'category' => $params['category'],
            'language' => $params['language'],
            'body_text' => $params['body']['text'],
            'status' => 'submitting',
            'friendly_payload' => json_encode($params),
            'variable_map' => json_encode($meta_payload['variable_map'])
        ]);

        // 3. Enviar para a Meta
        $meta_service = new TemplateMetaService();
        $tenant_id = TenantContext::get_tenant_id();
        $response = $meta_service->create($tenant_id, $meta_payload);

        if ($response['success']) {
            $this->repository->update($local_id, [
                'meta_template_id' => $response['id'] ?? null,
                'status'           => 'PENDING',
                'meta_payload'     => json_encode($meta_payload)
            ]);

            \WAS\Compliance\AuditLogger::log('template_create_success', 'template', $local_id, [
                'name' => $params['name'],
                'meta_id' => $response['id'] ?? null
            ]);

            return new WP_REST_Response([
                'success' => true, 
                'id' => $local_id, 
                'meta_id' => $response['id'] ?? null
            ], 201);
        }

        // Se falhou na Meta, atualiza status local
        $this->repository->update($local_id, [
            'status' => 'failed',
            'rejection_reason' => $response['error'] ?? 'Erro desconhecido'
        ]);

        \WAS\Compliance\AuditLogger::log('template_create_error', 'template', $local_id, [
            'name' => $params['name'],
            'error' => $response['error'] ?? 'Unknown Meta error'
        ]);
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }
}
