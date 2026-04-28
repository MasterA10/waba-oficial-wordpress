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

        try {
            $build_result = $this->builder->build($params);
            $meta_payload = $build_result['meta_payload'];
            $variable_map = $build_result['variable_map'];
        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }

        $tenant_id = TenantContext::get_tenant_id();
        $account_repo = new \WAS\WhatsApp\WhatsAppAccountRepository();
        $account = $account_repo->getByTenant($tenant_id)[0] ?? null;

        if (!$account || empty($account->waba_id)) {
            return new WP_REST_Response(['message' => 'WABA ID não configurado para o tenant.'], 400);
        }

        $token_service = new \WAS\Meta\TokenService();
        $token = $token_service->get_active_token($tenant_id);

        if (!$token) {
            return new WP_REST_Response(['message' => 'Token não configurado para o tenant.'], 400);
        }

        $local_id = $this->repository->create([
            'waba_id'             => $account->waba_id,
            'whatsapp_account_id' => $account->id,
            'name'                => $params['name'],
            'category'            => $params['category'],
            'language'            => $params['language'],
            'body_text'           => $params['body']['text'],
            'status'              => 'submitting',
            'friendly_payload'    => json_encode($params),
            'variable_map'        => json_encode($variable_map)
        ]);

        try {
            $meta_service = new TemplateMetaService();
            $response = $meta_service->create($account->waba_id, $meta_payload, $token);

            if ($response['success'] ?? false) {
                $this->repository->update($local_id, [
                    'meta_template_id' => $response['id'] ?? null,
                    'status'           => 'PENDING',
                    'meta_payload'     => json_encode($meta_payload)
                ]);

                \WAS\Compliance\AuditLogger::log('template_create_success', 'template', $local_id, [
                    'name'      => $meta_payload['name'],
                    'waba_id'   => $account->waba_id,
                    'meta_id'   => $response['id'] ?? null
                ]);

                return new WP_REST_Response(['success' => true, 'id' => $local_id, 'meta_id' => $response['id'] ?? null], 201);
            }

            $this->repository->update($local_id, [
                'status' => 'FAILED',
                'last_meta_error' => $response['error'] ?? 'Erro desconhecido'
            ]);

            return new WP_REST_Response(['message' => 'Erro na Meta: ' . ($response['error'] ?? 'Desconhecido')], 400);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::create_item', 'local_id' => $local_id]);
            return new WP_REST_Response(['message' => 'Erro interno ao criar template.'], 500);
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
            $template = $this->repository->get_by_id($id);
            if (!$template) {
                return new WP_REST_Error('not_found', 'Template não encontrado', ['status' => 404]);
            }

            $policyService = new \WAS\Templates\TemplatePolicyService();
            if ($policyService->shouldBlockEdit($template)) {
                return new WP_REST_Response(['message' => 'Este template não pode ser editado no estado atual. Considere duplicá-lo.'], 403);
            }

            $build_result = $this->builder->build($params);
            $meta_payload = $build_result['meta_payload'];
            $variable_map = $build_result['variable_map'];

            $tenant_id = TenantContext::get_tenant_id();
            $token_service = new \WAS\Meta\TokenService();
            $token = $token_service->get_active_token($tenant_id);

            if ($template->meta_template_id && $token) {
                $meta_service = new TemplateMetaService();
                $response = $meta_service->update($template->meta_template_id, $meta_payload, $token);
                if (!($response['success'] ?? false)) {
                    return new WP_REST_Response(['message' => 'Erro na Meta ao atualizar: ' . ($response['error'] ?? 'Desconhecido')], 400);
                }
            }

            $update_data = [
                'name' => $params['name'],
                'category' => $params['category'],
                'language' => $params['language'],
                'body_text' => $params['body']['text'],
                'friendly_payload' => json_encode($params),
                'variable_map' => json_encode($variable_map),
                'meta_payload' => json_encode($meta_payload)
            ];

            $this->repository->update($id, $update_data);
            
            \WAS\Compliance\AuditLogger::log('template_update_success', 'template', $id, [
                'name' => $meta_payload['name'],
                'meta_id' => $template->meta_template_id
            ]);

            return new WP_REST_Response(['success' => true], 200);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::update_item', 'local_id' => $id]);
            return new WP_REST_Response(['message' => 'Erro interno ao atualizar template.'], 500);
        }
    }

    public function delete_item(WP_REST_Request $request) {
        $id = (int) $request['id'];
        
        try {
            $deletionService = new \WAS\Templates\TemplateDeletionService();
            $result = $deletionService->deleteTemplate($id);

            if ($result['success'] ?? false) {
                return new WP_REST_Response(['success' => true], 200);
            }
            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao excluir.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::delete_item', 'local_id' => $id]);
            return new WP_REST_Response(['message' => 'Erro interno ao excluir template.'], 500);
        }
    }

    public function duplicate_item(WP_REST_Request $request) {
        $id = (int) $request['id'];
        $params = $request->get_json_params();
        $newName = $params['new_name'] ?? '';

        if (!$newName || !preg_match('/^[a-z0-9_]+$/', $newName)) {
            return new WP_REST_Response(['message' => 'Nome inválido. Use apenas minúsculas e underscores.'], 400);
        }

        try {
            $duplicateService = new \WAS\Templates\TemplateDuplicationService();
            $result = $duplicateService->duplicate($id, $newName);

            if ($result['success'] ?? false) {
                return new WP_REST_Response($result, 201);
            }
            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao duplicar.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::duplicate_item', 'local_id' => $id]);
            return new WP_REST_Response(['message' => 'Erro interno ao duplicar template.'], 500);
        }
    }

    public function sync_templates(WP_REST_Request $request) {
        $tenant_id = TenantContext::get_tenant_id();
        $waba_id = $request->get_param('waba_id');
        try {
            $sync_service = new \WAS\Templates\TemplateSyncService();
            $result = $sync_service->syncWaba($tenant_id, $waba_id);

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
        $id = $request['id']; // ID do template
        $conversation_id = $request->get_param('conversation_id');
        $to_phone = $request->get_param('to_phone');
        $variables = $request->get_param('variables') ?? [];
        $button_variables = $request->get_param('button_variables') ?? [];

        try {
            $send_service = new \WAS\Templates\TemplateSendService();
            // Assinatura correta: send($conversation_id, $template_id, $variables, $button_variables, $to_phone)
            $result = $send_service->send($conversation_id, $id, $variables, $button_variables, $to_phone);

            if ($result['success'] ?? false) {
                \WAS\Compliance\AuditLogger::log('template_send_success', 'template', $id, [
                    'conversation_id' => $conversation_id,
                    'to_phone' => $to_phone,
                    'wa_message_id' => $result['wa_message_id'] ?? null
                ]);
                return new WP_REST_Response(['success' => true, 'wa_message_id' => $result['wa_message_id'] ?? null], 200);
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
