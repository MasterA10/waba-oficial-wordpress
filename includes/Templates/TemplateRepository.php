<?php

namespace WAS\Templates;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repositório de Templates
 */
class TemplateRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::getTemplatesTable();
    }

    /**
     * Cria ou atualiza um template local
     */
    public function createOrUpdate(array $data) {
        global $wpdb;

        $tenant_id = TenantContext::getTenantId();
        $data['tenant_id'] = $tenant_id;

        // Se tiver ID da Meta, tenta encontrar para atualizar
        $existing_id = null;
        if (!empty($data['meta_template_id'])) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE meta_template_id = %s AND tenant_id = %d",
                $data['meta_template_id'],
                $tenant_id
            ));
        }

        // Se não tiver ID da Meta, tenta pelo nome e idioma (Unique Key)
        if (!$existing_id) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE name = %s AND language = %s AND tenant_id = %d",
                $data['name'],
                $data['language'],
                $tenant_id
            ));
        }

        if ($existing_id) {
            $data['updated_at'] = current_time('mysql', 1);
            $wpdb->update($this->table_name, $data, ['id' => $existing_id]);
            return $existing_id;
        } else {
            $data['created_at'] = current_time('mysql', 1);
            $data['updated_at'] = current_time('mysql', 1);
            $wpdb->insert($this->table_name, $data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Lista templates do tenant
     */
    public function listByTenant($status = null) {
        global $wpdb;
        $tenant_id = TenantContext::getTenantId();

        $query = "SELECT * FROM {$this->table_name} WHERE tenant_id = %d";
        $params = [$tenant_id];

        if ($status) {
            $query .= " AND status = %s";
            $params[] = $status;
        }

        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }

    /**
     * Busca um template por ID
     */
    public function find($id) {
        global $wpdb;
        $tenant_id = TenantContext::getTenantId();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id,
            $tenant_id
        ));
    }
}
