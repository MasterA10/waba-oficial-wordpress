<?php
namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;

if (!defined('ABSPATH')) {
    exit;
}

class PhoneNumberService {
    /**
     * Registra ou atualiza um número de telefone para um tenant.
     */
    public function register_phone_number($phone_number_id, $tenant_id, $display_phone = '') {
        global $wpdb;
        $table = TableNameResolver::get_table_name('whatsapp_phone_numbers');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE phone_number_id = %s",
            $phone_number_id
        ));

        if ($existing) {
            return $wpdb->update($table, [
                'tenant_id' => $tenant_id,
                'display_phone_number' => $display_phone,
            ], ['id' => $existing]);
        }

        return $wpdb->insert($table, [
            'tenant_id' => $tenant_id,
            'whatsapp_account_id' => 1, // Assumindo conta padrão no MVP
            'phone_number_id' => $phone_number_id,
            'display_phone_number' => $display_phone,
            'status' => 'active',
            'created_at' => current_time('mysql', 1)
        ]);
    }

    /**
     * Obtém o Phone Number ID principal de um tenant.
     */
    public function get_primary_id($tenant_id) {
        global $wpdb;
        $table = TableNameResolver::get_table_name('whatsapp_phone_numbers');
        return $wpdb->get_var($wpdb->prepare(
            "SELECT phone_number_id FROM $table WHERE tenant_id = %d ORDER BY created_at ASC LIMIT 1",
            $tenant_id
        ));
    }
}
