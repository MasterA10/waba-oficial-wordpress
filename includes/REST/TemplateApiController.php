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
        try {
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

            \WAS\Core\SystemLogger::logError('A Meta recusou a criação do template.', [
                'local_id'     => $local_id,
                'name'         => $params['name'],
                'meta_error'   => $response['error'] ?? 'Unknown Meta error',
                'meta_payload' => $meta_payload
            ]);

            return new WP_REST_Response(['message' => 'Erro na Meta: ' . ($response['error'] ?? 'Desconhecido')], 400);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::create_item', 'local_id' => $local_id]);
            return new WP_REST_Response(['message' => 'Erro interno ao processar criação de template na Meta.'], 500);
        }
    }

    public function get_item(WP_REST_Request $request) {
        $id = $request['id'];
        $template = $this->repository->get_by_id($id);
        if (!$template) {
            return new WP_REST_Error('not_found', 'Template não encontrado', ['status' => 404]);
        }
        return new WP_REST_Response($template, 200);
    }

    public function update_item(WP_REST_Request $request) {
        $id = $request['id'];
        $params = $request->get_json_params();
        
        try {
            $updated = $this->repository->update($id, $params);
            if ($updated) {
                return new WP_REST_Response(['success' => true], 200);
            }
            return new WP_REST_Response(['message' => 'Nenhuma alteração realizada.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::update_item', 'local_id' => $id]);
            return new WP_REST_Response(['message' => 'Erro interno ao atualizar template.'], 500);
        }
    }

    public function sync_templates(WP_REST_Request $request) {
        $tenant_id = TenantContext::get_tenant_id();
        try {
            $sync_service = new \WAS\Templates\TemplateSyncService();
            $result = $sync_service->sync($tenant_id);

            if ($result['success'] ?? false) {
                return new WP_REST_Response($result, 200);
            }

            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao sincronizar da Meta.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::sync_templates']);
            return new WP_REST_Response(['message' => 'Erro interno do sistema ao sincronizar templates.'], 500);
        }
    }

    public function send_template(WP_REST_Request $request) {
        $id = $request['id'];
        $conversation_id = $request->get_param('conversation_id');
        $tenant_id = TenantContext::get_tenant_id();

        try {
            $send_service = new \WAS\Templates\TemplateSendService();
            $result = $send_service->send_template($id, $conversation_id, $tenant_id);

            if ($result['success'] ?? false) {
                return new WP_REST_Response(['success' => true], 200);
            }

            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao enviar template.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::send_template', 'local_id' => $id, 'conversation_id' => $conversation_id]);
            return new WP_REST_Response(['message' => 'Erro interno do sistema ao enviar template.'], 500);
        }
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }
}
