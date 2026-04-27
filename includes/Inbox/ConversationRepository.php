<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class ConversationRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('conversations');
    }

    /**
     * Busca uma conversa aberta para um contato ou cria se não existir.
     * 
     * @param int $contact_id ID do contato.
     * @param string $phone_number_id O ID do número de telefone (Meta).
     * @return object|false
     */
    public function find_or_create_open_conversation($contact_id, $phone_number_id = '') {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE contact_id = %d AND tenant_id = %d AND status = 'open'",
            $contact_id,
            $tenant_id
        ));

        if ($conversation) {
            return $conversation;
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'tenant_id'       => $tenant_id,
                'contact_id'      => $contact_id,
                'phone_number_id' => $phone_number_id, // Campo obrigatório
                'status'          => 'open',
                'last_message_at' => current_time('mysql', 1),
                'created_at'      => current_time('mysql', 1),
                'updated_at'      => current_time('mysql', 1),
            ]
        );

        if ($result) {
            return $this->get_by_id($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Atualiza o timestamp da última mensagem na conversa.
     */
    public function update_last_message_at($conversation_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'last_message_at' => current_time('mysql', 1),
                'updated_at'      => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Atribui uma conversa a um usuário (atendente).
     */
    public function assign($conversation_id, $user_id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            [
                'assigned_user_id' => $user_id,
                'updated_at'       => current_time('mysql', 1)
            ],
            ['id' => $conversation_id, 'tenant_id' => $tenant_id],
            ['%d', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Busca uma conversa pelo ID.
     */
    public function get_by_id($id) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND tenant_id = %d",
            $id,
            $tenant_id
        ));
    }

    /**
     * Lista conversas do tenant ordenadas pela última mensagem.
     */
    public function list_conversations($limit = 20, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        $contact_table = TableNameResolver::get_table_name('contacts');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, ct.profile_name, ct.wa_id 
             FROM {$this->table_name} c
             JOIN {$contact_table} ct ON c.contact_id = ct.id
             WHERE c.tenant_id = %d 
             ORDER BY c.last_message_at DESC 
             LIMIT %d OFFSET %d",
            $tenant_id,
            $limit,
            $offset
        ));
    }
}
