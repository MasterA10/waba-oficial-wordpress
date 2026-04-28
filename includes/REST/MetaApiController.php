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
$token_service = new \WAS\Meta\TokenService();
$tenant_id = \WAS\Auth\TenantContext::get_tenant_id();
$raw_token = $token_service->get_active_token($tenant_id);

// Buscar WABA ID atual
global $wpdb;
$acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
$waba_id = $wpdb->get_var($wpdb->prepare("SELECT waba_id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));

// Buscar URL do Cadastro Incorporado
$settings_table = \WAS\Core\TableNameResolver::get_table_name('settings');
$embedded_signup_url = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $settings_table WHERE tenant_id = %d AND setting_key = 'embedded_signup_url'", $tenant_id));

        // Tenta descriptografar o segredo se existir
        $app_secret_masked = '';
        if (!empty($app->app_secret)) {
            try {
                $app_secret_masked = TokenVault::mask(TokenVault::decrypt($app->app_secret));
            } catch (\Exception $e) {
                $app_secret_masked = '********'; // Fallback se falhar
            }
        }

        return new WP_REST_Response([
            'app_id'        => $app->app_id,
            'app_secret'    => $app_secret_masked,
            'config_id'     => $app->config_id ?? '',
            'graph_version' => $app->graph_version,
            'verify_token'  => $app->verify_token,
            'webhook_url'   => home_url('/was-meta-check-99'),
            'primary_phone_number_id' => $phone_service->get_primary_id($tenant_id),
            'meta_access_token' => $raw_token ? TokenVault::mask($raw_token, 8) : '',
            'waba_id'       => $waba_id ?: '',
            'embedded_signup_url' => $embedded_signup_url ?: ''
        ], 200);
    }

    /**
     * Salva a configuração do Meta App.
     */
    public function save_config(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();

        if (empty($params['app_id'])) {
            return new WP_REST_Response(['message' => 'App ID é obrigatório'], 400);
        }

        try {
            // Se o secret vier mascarado, não atualizamos o secret
            if (isset($params['app_secret']) && strpos($params['app_secret'], '...') !== false) {
                unset($params['app_secret']);
            }

            $result = $this->repository->save_app($params);

            if ($result === false) {
                global $wpdb;
                $table_name = \WAS\Core\TableNameResolver::get_table_name('meta_apps');
                $db_error = $wpdb->last_error ? ' | DB Error: ' . $wpdb->last_error : ' | No DB Error logged';
                
                // Debug columns
                $cols = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
                $col_names = array_map(function($c) { return $c->Field; }, $cols);
                $cols_debug = ' | Cols in DB: ' . implode(',', $col_names);

                return new WP_REST_Response(['message' => 'Erro ao salvar configuração do App no banco (' . $table_name . ')' . $db_error . $cols_debug], 500);
            }

            // Salvar URL do Cadastro Incorporado (nas configurações do Tenant)
            if (isset($params['embedded_signup_url'])) {
                global $wpdb;
                $settings_table = \WAS\Core\TableNameResolver::get_table_name('settings');
                $url = sanitize_text_field($params['embedded_signup_url']);
                
                $existing_setting = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $settings_table WHERE tenant_id = %d AND setting_key = 'embedded_signup_url'",
                    $tenant_id
                ));
                
                if ($existing_setting) {
                    $wpdb->update($settings_table, ['setting_value' => $url], ['tenant_id' => $tenant_id, 'setting_key' => 'embedded_signup_url']);
                } else {
                    $wpdb->insert($settings_table, [
                        'tenant_id' => $tenant_id,
                        'setting_key' => 'embedded_signup_url',
                        'setting_value' => $url
                    ]);
                }
            }

            // Salvar WABA ID se fornecido
            if (!empty($params['waba_id'])) {
                global $wpdb;
                $acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
                $existing_acc = $wpdb->get_var($wpdb->prepare("SELECT id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));

                if ($existing_acc) {
                    $wpdb->update($acc_table, ['waba_id' => sanitize_text_field($params['waba_id'])], ['id' => $existing_acc]);
                } else {
                    $wpdb->insert($acc_table, [
                        'tenant_id' => $tenant_id,
                        'waba_id'   => sanitize_text_field($params['waba_id']),
                        'name'      => 'Conta WhatsApp',
                        'status'    => 'active',
                        'created_at' => current_time('mysql', 1)
                    ]);
                }
            }

            // Salvar Access Token se fornecido e não mascarado
            if (!empty($params['meta_access_token']) && strpos($params['meta_access_token'], '...') === false) {
                global $wpdb;
                $token_table = \WAS\Core\TableNameResolver::get_table_name('meta_tokens');
                $encrypted_token = TokenVault::encrypt($params['meta_access_token']);

                // Limpa antigos e insere o novo (Simplificado para o MVP)
                $wpdb->delete($token_table, ['tenant_id' => $tenant_id]);
                $wpdb->insert($token_table, [
                    'tenant_id' => $tenant_id,
                    'whatsapp_account_id' => 1,
                    'access_token_encrypted' => $encrypted_token,
                    'status' => 'active',
                    'created_at' => current_time('mysql', 1)
                ]);
            }

            // Salvar Phone Number ID se fornecido
            if (!empty($params['primary_phone_number_id'])) {
                $phone_service = new \WAS\WhatsApp\PhoneNumberService();
                $phone_service->register_phone_number(sanitize_text_field($params['primary_phone_number_id']), $tenant_id);
            }

            AuditLogger::log('save_meta_config', 'meta_app', (string)$result, [
                'app_id' => $params['app_id']
            ]);

            return new WP_REST_Response(['message' => 'Configurações salvas com sucesso', 'id' => $result], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response(['message' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifica permissão para gerenciar Meta App.
     */
    public function permissions_check() {
        return Routes::check_auth();
    }
}
