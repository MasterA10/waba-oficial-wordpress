<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSyncService {
    private $repository;
    private $meta_service;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->meta_service = new TemplateMetaService();
    }

    /**
     * Sincroniza templates da Meta para o banco local.
     */
    public function syncWaba(int $tenantId, ?string $wabaId = null): array {
        $accountRepo = new \WAS\WhatsApp\WhatsAppAccountRepository();
        $account = $accountRepo->findForTenant($tenantId, $wabaId);

        if (!$account || empty($account->waba_id)) {
            return ['success' => false, 'error' => 'WABA ID não configurado para este tenant.'];
        }

        $tokenService = new \WAS\Meta\TokenService();
        $token = $tokenService->get_active_token($tenantId);
        if (!$token) {
            return ['success' => false, 'error' => 'Token não configurado para este tenant.'];
        }

        $response = $this->meta_service->list($account->waba_id, $token);

        if (!isset($response['data'])) {
            \WAS\Core\SystemLogger::logError('Falha ao listar templates da Meta para sincronização.', [
                'tenant_id' => $tenantId,
                'error'     => $response['error'] ?? 'Unknown error'
            ]);
            return ['success' => false, 'error' => $response['error'] ?? 'Unknown error'];
        }

        $summary = [
            'created_local' => 0,
            'updated_local' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        foreach (($response['data'] ?? []) as $metaTemplate) {
            try {
                $result = $this->upsertFromMeta($tenantId, $account->id, $account->waba_id, $metaTemplate);
                $summary[$result]++;
            } catch (\Throwable $e) {
                $summary['errors']++;
                \WAS\Core\SystemLogger::logException($e, [
                    'tenant_id' => $tenantId,
                    'waba_id' => $account->waba_id,
                    'template' => $metaTemplate['name'] ?? null
                ]);
            }
        }

        \WAS\Compliance\AuditLogger::log('template_sync_completed', 'template', 0, [
            'tenant_id'       => $tenantId,
            'waba_id'         => $account->waba_id,
            'summary'         => $summary
        ]);

        return ['success' => true, 'summary' => $summary];
    }

    private function upsertFromMeta(int $tenantId, int $accountId, string $wabaId, array $metaTemplate): string {
        $name = $metaTemplate['name'] ?? '';
        $language = $metaTemplate['language'] ?? '';

        if (!$name || !$language) {
            return 'unchanged';
        }

        $existing = $this->repository->findByWabaNameLanguage($tenantId, $wabaId, $name, $language);

        $data = [
            'tenant_id' => $tenantId,
            'whatsapp_account_id' => $accountId,
            'waba_id' => $wabaId,
            'meta_template_id' => $metaTemplate['id'] ?? null,
            'name' => $name,
            'language' => $language,
            'category' => $metaTemplate['category'] ?? 'UNKNOWN',
            'status' => $metaTemplate['status'] ?? 'UNKNOWN',
            'components_json' => wp_json_encode($metaTemplate['components'] ?? []),
            'rejection_reason' => $metaTemplate['rejected_reason'] ?? null,
            'meta_payload' => wp_json_encode($metaTemplate),
            'synced_at' => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1),
            'body_text' => '' // Fallback required
        ];

        if (!empty($metaTemplate['components']) && is_array($metaTemplate['components'])) {
            foreach ($metaTemplate['components'] as $comp) {
                if ($comp['type'] === 'BODY') {
                    $data['body_text'] = $comp['text'] ?? '';
                } elseif ($comp['type'] === 'HEADER') {
                    $data['header_type'] = $comp['format'] ?? null;
                    // 'header_text' doesn't exist in schema, friendly_payload is used. We use meta_payload instead.
                } elseif ($comp['type'] === 'FOOTER') {
                    $data['footer_text'] = $comp['text'] ?? '';
                } elseif ($comp['type'] === 'BUTTONS') {
                    $data['buttons_json'] = json_encode($comp['buttons']);
                }
            }
        }

        if (!$existing) {
            $data['created_at'] = current_time('mysql', 1);
            $this->repository->create($data);
            return 'created_local';
        }

        $this->repository->update($existing->id, $data);
        return 'updated_local';
    }
}
