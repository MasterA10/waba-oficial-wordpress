<?php
namespace WAS\Inbox;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class ContactRepository {
    private $table_name;

    public function __construct() {
        $this->table_name = TableNameResolver::get_table_name('contacts');
    }

    /**
     * Busca um contato pelo WhatsApp ID ou cria se não existir.
     * 
     * @param string $wa_id O ID do WhatsApp (geralmente o número de telefone).
     * @param string $profile_name O nome de perfil do contato.
     * @return object|false O objeto do contato ou false em caso de erro.
     */
    public function find_or_create_by_wa_id($wa_id, $profile_name = '') {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        if (!$tenant_id) {
            return false;
        }

        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE wa_id = %s AND tenant_id = %d",
            $wa_id,
            $tenant_id
        ));

        if ($contact) {
            // Atualiza o nome se mudou ou se estava vazio
            if (!empty($profile_name) && $contact->profile_name !== $profile_name) {
                $wpdb->update(
                    $this->table_name,
                    ['profile_name' => $profile_name, 'updated_at' => current_time('mysql', 1)],
                    ['id' => $contact->id],
                    ['%s', '%s'],
                    ['%d']
                );
                $contact->profile_name = $profile_name;
            }
            return $contact;
        }

        $result = $wpdb->insert(
            $this->table_name,
            [
                'tenant_id'    => $tenant_id,
                'wa_id'        => $wa_id,
                'profile_name' => $profile_name,
                'created_at'   => current_time('mysql', 1),
                'updated_at'   => current_time('mysql', 1),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            return $this->get_by_id($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Adiciona uma tag ao contato.
     */
    public function add_tag($contact_id, $tag) {
        $contact = $this->get_by_id($contact_id);
        if (!$contact) return false;

        $tags = json_decode($contact->tags ?: '[]', true);
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update_tags($contact_id, $tags);
        }
        return true;
    }

    /**
     * Remove uma tag do contato.
     */
    public function remove_tag($contact_id, $tag) {
        $contact = $this->get_by_id($contact_id);
        if (!$contact) return false;

        $tags = json_decode($contact->tags ?: '[]', true);
        $tags = array_values(array_diff($tags, [$tag]));
        return $this->update_tags($contact_id, $tags);
    }

    private function update_tags($contact_id, $tags) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->update(
            $this->table_name,
            ['tags' => json_encode($tags), 'updated_at' => current_time('mysql', 1)],
            ['id' => $contact_id, 'tenant_id' => $tenant_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }

    /**
     * Busca um contato pelo ID interno.
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
     * Lista contatos do tenant atual.
     */
    public function list_contacts($limit = 20, $offset = 0) {
        global $wpdb;
        $tenant_id = TenantContext::get_tenant_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tenant_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
            $tenant_id,
            $limit,
            $offset
        ));
    }
}
