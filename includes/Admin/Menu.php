<?php

namespace WAS\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerenciador de Menus Admin
 */
class Menu {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'was-') === false) {
            return;
        }

        // Map menu slugs to our page identifiers
        $page_mapping = [
            'toplevel_page_was-dashboard' => 'dashboard',
            'whatsapp-saas_page_was-inbox' => 'inbox',
            'whatsapp-saas_page_was-templates' => 'templates',
            'whatsapp-saas_page_was-settings-meta' => 'settings-meta',
            'whatsapp-saas_page_was-settings-whatsapp' => 'settings-whatsapp',
            'whatsapp-saas_page_was-logs' => 'logs',
        ];

        $current_page = $page_mapping[$hook] ?? 'dashboard';

        \WAS\Core\AssetService::enqueue_assets($current_page);
    }

    public function register_menus() {
        $capability = 'was_access_app'; 

        add_menu_page(
            'WhatsApp SaaS',
            'WhatsApp SaaS',
            $capability,
            'was-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-whatsapp',
            30
        );

        add_submenu_page(
            'was-dashboard',
            'Dashboard',
            'Dashboard',
            $capability,
            'was-dashboard',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'was-dashboard',
            'Inbox',
            'Inbox',
            'was_view_inbox',
            'was-inbox',
            [$this, 'render_inbox']
        );

        add_submenu_page(
            'was-dashboard',
            'Templates',
            'Templates',
            'was_manage_templates',
            'was-templates',
            [$this, 'render_templates_page']
        );

        add_submenu_page(
            'was-dashboard',
            'Configurações Meta',
            'Configurações Meta',
            'manage_options',
            'was-settings-meta',
            [$this, 'render_settings_meta']
        );

        add_submenu_page(
            'was-dashboard',
            'WhatsApp Setup',
            'WhatsApp Setup',
            'was_manage_whatsapp',
            'was-settings-whatsapp',
            [$this, 'render_settings_whatsapp']
        );

        add_submenu_page(
            'was-dashboard',
            'Logs',
            'Logs',
            'was_view_logs',
            'was-logs',
            [$this, 'render_logs_page']
        );
    }

    public function render_dashboard() {
        include WAS_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public function render_inbox() {
        include WAS_PLUGIN_DIR . 'templates/inbox.php';
    }

    public function render_templates_page() {
        include WAS_PLUGIN_DIR . 'templates/templates.php';
    }

    public function render_logs_page() {
        include WAS_PLUGIN_DIR . 'templates/logs.php';
    }

    public function render_settings_meta() {
        include WAS_PLUGIN_DIR . 'templates/settings-meta.php';
    }

    public function render_settings_whatsapp() {
        include WAS_PLUGIN_DIR . 'templates/settings-whatsapp.php';
    }
}
