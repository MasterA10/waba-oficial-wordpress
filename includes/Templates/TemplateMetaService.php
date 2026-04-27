<?php
namespace WAS\Templates;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\AccountService;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateMetaService {
    private $api_client;
    private $token_service;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
    }

    /**
     * Cria o template na Meta Graph API.
     */
    public function create($tenant_id, array $meta_payload) {
        global $wpdb;
        $acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
        $waba_id = $wpdb->get_var($wpdb->prepare("SELECT waba_id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));
        
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$waba_id || !$token) {
            return ['success' => false, 'error' => 'WABA ID ou Token não configurado'];
        }

        return $this->api_client->postJson(
            'templates.create',
            ['waba_id' => $waba_id],
            $meta_payload,
            $token
        );
    }

    /**
     * Lista templates da Meta.
     */
    public function list_from_meta($tenant_id) {
        global $wpdb;
        $acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
        $waba_id = $wpdb->get_var($wpdb->prepare("SELECT waba_id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));
        
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$waba_id || !$token) {
            return ['success' => false, 'error' => 'WABA ID ou Token não configurado'];
        }

        return $this->api_client->get(
            'templates.list',
            ['waba_id' => $waba_id],
            ['fields' => 'id,name,status,category,language,components,rejected_reason'],
            $token
        );
    }

    /**
     * Exclui um template da Meta.
     */
    public function delete($tenant_id, $template_name) {
        global $wpdb;
        $acc_table = \WAS\Core\TableNameResolver::get_table_name('whatsapp_accounts');
        $waba_id = $wpdb->get_var($wpdb->prepare("SELECT waba_id FROM $acc_table WHERE tenant_id = %d LIMIT 1", $tenant_id));
        
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$waba_id || !$token) {
            return ['success' => false, 'error' => 'WABA ID ou Token não configurado'];
        }

        return $this->api_client->delete(
            'templates.delete',
            ['waba_id' => $waba_id],
            ['name' => $template_name],
            $token
        );
    }

    /**
     * Atualiza os componentes de um template na Meta.
     */
    public function update($tenant_id, $meta_template_id, array $components) {
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$token) {
            return ['success' => false, 'error' => 'Token não configurado'];
        }

        return $this->api_client->postJson(
            'templates.update',
            ['template_id' => $meta_template_id],
            ['components' => $components],
            $token
        );
    }
}
