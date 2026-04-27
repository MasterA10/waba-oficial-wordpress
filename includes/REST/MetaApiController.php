<?php

namespace WAS\REST;

use WAS\Meta\MetaAppRepository;
use WAS\Meta\TokenVault;
use WAS\Compliance\AuditLogger;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para configuração do Meta App
 */
class MetaApiController {

    private $repository;

    public function __construct() {
        $this->repository = new MetaAppRepository();
    }

    /**
     * Obtém a configuração atual do Meta App.
     */
    public function get_config(WP_REST_Request $request) {
        $app = $this->repository->get_active_app(false); // Não descriptografar aqui

        if (!$app) {
            return new WP_REST_Response(null, 200);
        }

        $phone_service = new \WAS\WhatsApp\PhoneNumberService();
        $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();

        return new WP_REST_Response([
            'app_id'        => $app->app_id,
            'app_secret'    => TokenVault::mask(TokenVault::decrypt($app->app_secret)),
            'graph_version' => $app->graph_version,
            'verify_token'  => $app->verify_token,
            'webhook_url'   => home_url('/was-meta-check-99'),
            'primary_phone_number_id' => $phone_service->get_primary_id($tenant_id)
        ], 200);
    }

    /**
     * Salva a configuração do Meta App.
     */
    public function save_config(WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (empty($params['app_id'])) {
            return new WP_REST_Response(['message' => 'App ID é obrigatório'], 400);
        }

        // Se o secret vier mascarado, não atualizamos o secret
        if (isset($params['app_secret']) && strpos($params['app_secret'], '...') !== false) {
            unset($params['app_secret']);
        }

        $result = $this->repository->save_app($params);

        if ($result === false) {
            return new WP_REST_Response(['message' => 'Erro ao salvar configuração'], 500);
        }

        // Salvar Phone Number ID se fornecido
        if (!empty($params['primary_phone_number_id'])) {
            $phone_service = new \WAS\WhatsApp\PhoneNumberService();
            $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();
            $phone_service->register_phone_number($params['primary_phone_number_id'], $tenant_id);
        }

        AuditLogger::log('save_meta_config', 'meta_app', $result, [
            'app_id' => $params['app_id']
        ]);

        return new WP_REST_Response(['message' => 'Configuração salva com sucesso', 'id' => $result], 200);
    }

    /**
     * Verifica permissão para gerenciar Meta App.
     */
    public function permissions_check() {
        return Routes::check_auth();
    }
}
