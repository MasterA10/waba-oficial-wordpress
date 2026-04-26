<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class MessageRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('messages');
    }

    /**
     * Cria uma mensagem de entrada (inbound).
     */
    public function create_inbound($data) {
        return $this->create(array_merge($data, ['direction' => 'inbound']));
    }

    /**
     * Cria uma mensagem de saída (outbound).
     */
    public function create_outbound($data) {
        return $this->create(array_merge($data, ['direction' => 'outbound']));
    }

    /**
     * Método base para criação de mensagens.
     */
    private function create($data) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $defaults = [
            'tenant_id'       => $tenant_id,
            'status'          => 'received',
            'created_at'      => current_time('mysql', 1),
        ];

        $payload = array_merge($defaults, $data);

        $result = $wpdb->insert(
            $this->table_name,
            $payload
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Busca mensagem pelo ID oficial do WhatsApp (evita duplicidade).
     */
    public function find_by_wa_message_id($wa_message_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE wa_message_id = %s AND tenant_id = %d",
            $wa_message_id,
            $tenant_id
        ));
    }

    /**
     * Lista mensagens de uma conversa.
     */
    public function list_by_conversation($conversation_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE conversation_id = %d AND tenant_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d",
            $conversation_id,
            $tenant_id,
            $limit,
            $offset
        ));
    }

    /**
     * Atualiza o status de uma mensagem (sent, delivered, read, failed).
     */
    public function update_status($wa_message_id, $status) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            ['status' => $status],
            ['wa_message_id' => $wa_message_id, 'tenant_id' => $tenant_id],
            ['%s'],
            ['%s', '%d']
        );
    }
}
