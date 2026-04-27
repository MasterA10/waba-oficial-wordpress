<?php
namespace WAS\Templates;

use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateDeletionService {

    private $repository;
    private $metaService;
    private $tokenService;
    private $accountRepo;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->metaService = new TemplateMetaService();
        $this->tokenService = new \WAS\Meta\TokenService();
        $this->accountRepo = new \WAS\WhatsApp\WhatsAppAccountRepository();
    }

    public function deleteTemplate(int $templateId): array {
        $tenantId = TenantContext::get_tenant_id();
        $template = $this->repository->findForTenant($templateId, $tenantId);

        if (!$template) {
            return ['success' => false, 'error' => 'Template não encontrado.'];
        }

        $token = $this->tokenService->get_active_token($tenantId);
        if (!$token) {
            return ['success' => false, 'error' => 'Token não configurado para este tenant.'];
        }

        // Tenta deletar na Meta se já foi submetido
        if ($template->meta_template_id || strtolower($template->status) !== 'draft') {
            $metaResponse = null;
            if ($template->meta_template_id) {
                // Se temos o ID real, deleta por ID (mais seguro)
                $metaResponse = $this->metaService->deleteById($template->meta_template_id, $token);
            } else {
                // Tenta por nome no waba_id
                $metaResponse = $this->metaService->deleteByName($template->waba_id, $template->name, $token);
            }

            if (!($metaResponse['success'] ?? false) && !str_contains(strtolower($metaResponse['error'] ?? ''), 'does not exist')) {
                return ['success' => false, 'error' => 'Erro na Meta ao excluir: ' . ($metaResponse['error'] ?? 'Desconhecido')];
            }
        }

        // Executa o soft delete localmente
        $this->repository->softDelete($templateId);

        \WAS\Compliance\AuditLogger::log('template_delete_success', 'template', $templateId, [
            'name' => $template->name,
            'waba_id' => $template->waba_id
        ]);

        return ['success' => true];
    }
}
