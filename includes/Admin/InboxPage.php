<?php
namespace WAS\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class InboxPage {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'was-inbox') === false) {
            return;
        }

        wp_enqueue_style('was-app-css', WAS_PLUGIN_URL . 'assets/css/app.css', [], WAS_VERSION);
        wp_enqueue_script('was-app-js', WAS_PLUGIN_URL . 'assets/js/app.js', [], WAS_VERSION, true);

        wp_localize_script('was-app-js', 'wasData', [
            'restUrl' => get_rest_url(null, WAS_REST_NAMESPACE),
            'nonce'   => wp_create_nonce('wp_rest'),
            'tenantId' => \WAS\Auth\TenantContext::get_tenant_id()
        ]);
    }

    public function register_menu() {
        add_submenu_page(
            'was-dashboard', // Parent slug (assumindo que Dev 01 criou)
            'Inbox',
            'Inbox',
            'was_view_inbox',
            'was-inbox',
            [$this, 'render']
        );
    }

    public function render() {
        $template_path = WAS_PLUGIN_DIR . 'templates/inbox.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template de Inbox não encontrado.</p></div>';
        }
    }
}
