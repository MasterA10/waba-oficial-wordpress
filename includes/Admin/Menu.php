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

        wp_enqueue_style('was-app-css', WAS_PLUGIN_URL . 'assets/css/app.css', [], WAS_VERSION);
        wp_enqueue_script('was-app-js', WAS_PLUGIN_URL . 'assets/js/app.js', [], WAS_VERSION, true);

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

        wp_localize_script('was-app-js', 'wasApp', [
            'restUrl' => esc_url_raw(rest_url('was/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'page'    => $current_page
        ]);
    }

    public function register_menus() {
        $capability = 'manage_options'; 

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
            'manage_options',
            'was-inbox',
            [$this, 'render_inbox']
        );

        add_submenu_page(
            'was-dashboard',
            'Templates',
            'Templates',
            'manage_options',
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
            'manage_options',
            'was-settings-whatsapp',
            [$this, 'render_settings_whatsapp']
        );

        add_submenu_page(
            'was-dashboard',
            'Logs',
            'Logs',
            'manage_options',
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
