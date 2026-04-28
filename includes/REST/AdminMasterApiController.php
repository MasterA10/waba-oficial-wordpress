<?php
/**
 * AdminMasterApiController class.
 *
 * @package WAS\REST
 */

namespace WAS\REST;

use WP_REST_Request;
use WP_REST_Response;
use WAS\Core\TableNameResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for Master Admin REST endpoints.
 */
class AdminMasterApiController {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( 'was/v1', '/admin/overview', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_overview' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/tenants', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_tenants' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/wabas', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_wabas' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/phone-numbers', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_phones' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/onboardings', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_onboardings' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/templates', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_templates' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/webhooks', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_webhooks' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/tokens', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_tokens' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/app-review/checklist', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_review_checklist' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/audit-logs', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_audit_logs' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'was/v1', '/admin/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		] );

        register_rest_route( 'was/v1', '/admin/meta-apps', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_meta_apps' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_meta_app' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        ] );
	}

	/**
	 * Get overview stats for the master dashboard.
	 */
	public function get_overview( $request ) {
		global $wpdb;

		$table_tenants     = TableNameResolver::get_table_name( 'tenants' );
		$table_wabas       = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$table_phones      = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$table_templates   = TableNameResolver::get_table_name( 'message_templates' );
		$table_webhooks    = TableNameResolver::get_table_name( 'webhook_events' );
		$table_onboarding  = TableNameResolver::get_table_name( 'onboarding_sessions' );

		$tenants_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_tenants WHERE status = 'active'" );
		$wabas_count   = $wpdb->get_var( "SELECT COUNT(*) FROM $table_wabas WHERE status = 'connected'" );
		$phones_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_phones WHERE status = 'active'" );
		$templates_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_templates WHERE status = 'APPROVED'" );
		$webhooks_today = $wpdb->get_var( "SELECT COUNT(*) FROM $table_webhooks WHERE DATE(received_at) = CURDATE()" );
		$onboarding_fails = $wpdb->get_var( "SELECT COUNT(*) FROM $table_onboarding WHERE status = 'failed' OR status = 'cancelled'" );

		return new WP_REST_Response( [
			'tenants'             => (int) $tenants_count,
			'wabas'               => (int) $wabas_count,
			'phones'              => (int) $phones_count,
			'templates'           => (int) $templates_count,
			'webhooks_today'      => (int) $webhooks_today,
			'onboarding_failures' => (int) $onboarding_fails,
		], 200 );
	}

    /**
     * Get list of tenants.
     */
    public function get_tenants( $request ) {
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'tenants' );
        $tenants = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
        return new WP_REST_Response( $tenants, 200 );
    }

    /**
     * Get list of WABAs.
     */
    public function get_wabas( $request ) {
        global $wpdb;
        $table_wabas   = TableNameResolver::get_table_name( 'whatsapp_accounts' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );

        $wabas = $wpdb->get_results( "
            SELECT w.*, t.name as tenant_name 
            FROM $table_wabas w
            LEFT JOIN $table_tenants t ON w.tenant_id = t.id
            ORDER BY w.created_at DESC
        " );

        return new WP_REST_Response( $wabas, 200 );
    }

    /**
     * Get list of Phone Numbers.
     */
    public function get_phones( $request ) {
        global $wpdb;
        $table_phones  = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );

        $phones = $wpdb->get_results( "
            SELECT p.*, t.name as tenant_name 
            FROM $table_phones p
            LEFT JOIN $table_tenants t ON p.tenant_id = t.id
            ORDER BY p.created_at DESC
        " );

        return new WP_REST_Response( $phones, 200 );
    }

    /**
     * Get list of Onboarding Sessions.
     */
    public function get_onboardings( $request ) {
        global $wpdb;
        $table_onboarding = TableNameResolver::get_table_name( 'onboarding_sessions' );
        $table_tenants    = TableNameResolver::get_table_name( 'tenants' );
        $table_users      = $wpdb->users;

        $sessions = $wpdb->get_results( "
            SELECT s.*, t.name as tenant_name, u.user_login 
            FROM $table_onboarding s
            LEFT JOIN $table_tenants t ON s.tenant_id = t.id
            LEFT JOIN $table_users u ON s.user_id = u.ID
            ORDER BY s.created_at DESC
        " );

        return new WP_REST_Response( $sessions, 200 );
    }

    /**
     * Get list of Templates.
     */
    public function get_templates( $request ) {
        global $wpdb;
        $table_templates = TableNameResolver::get_table_name( 'message_templates' );
        $table_tenants   = TableNameResolver::get_table_name( 'tenants' );

        $templates = $wpdb->get_results( "
            SELECT m.*, t.name as tenant_name 
            FROM $table_templates m
            LEFT JOIN $table_tenants t ON m.tenant_id = t.id
            ORDER BY m.created_at DESC
        " );

        return new WP_REST_Response( $templates, 200 );
    }

    /**
     * Get list of Webhooks.
     */
    public function get_webhooks( $request ) {
        global $wpdb;
        $table_webhooks = TableNameResolver::get_table_name( 'webhook_events' );
        $table_tenants  = TableNameResolver::get_table_name( 'tenants' );

        $events = $wpdb->get_results( "
            SELECT w.*, t.name as tenant_name 
            FROM $table_webhooks w
            LEFT JOIN $table_tenants t ON w.tenant_id = t.id
            ORDER BY w.received_at DESC LIMIT 100
        " );

        return new WP_REST_Response( $events, 200 );
    }

    /**
     * Get list of Tokens.
     */
    public function get_tokens( $request ) {
        global $wpdb;
        $table_tokens  = TableNameResolver::get_table_name( 'meta_tokens' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );

        $tokens = $wpdb->get_results( "
            SELECT k.*, t.name as tenant_name 
            FROM $table_tokens k
            LEFT JOIN $table_tenants t ON k.tenant_id = t.id
            ORDER BY k.created_at DESC
        " );

        // Sanitize for security
        foreach($tokens as $token) {
            unset($token->access_token_encrypted);
            $token->prefix = $token->token_prefix ?: 'EAAG...';
            $token->length = $token->token_length ?: 0;
        }

        return new WP_REST_Response( $tokens, 200 );
    }

    /**
     * Get App Review Checklist.
     */
    public function get_review_checklist( $request ) {
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'app_review_checklist' );
        $items = $wpdb->get_results( "SELECT * FROM $table" );

        if ( empty($items) ) {
            // Default items based on the spec
            return new WP_REST_Response([
                ['item_key' => 'business_portfolio_created', 'label' => 'Portfolio de Negócios Criado', 'status' => 'pending'],
                ['item_key' => 'meta_app_created', 'label' => 'Meta App Criado', 'status' => 'pending'],
                ['item_key' => 'embedded_signup_configured', 'label' => 'Embedded Signup Configurado', 'status' => 'pending'],
                ['item_key' => 'privacy_policy_url_added', 'label' => 'URL de Privacidade Adicionada', 'status' => 'pending'],
                ['item_key' => 'template_creation_video_recorded', 'label' => 'Vídeo: Criação de Template', 'status' => 'pending'],
                ['item_key' => 'message_sending_video_recorded', 'label' => 'Vídeo: Envio de Mensagem', 'status' => 'pending'],
            ], 200);
        }

        return new WP_REST_Response( $items, 200 );
    }

    /**
     * Get list of Meta Apps.
     */
    public function get_meta_apps( $request ) {
        global $wpdb;
        $table = TableNameResolver::get_table_name( 'meta_apps' );
        $apps = $wpdb->get_results( "SELECT * FROM $table" );
        
        // Sanitize for security
        foreach($apps as $app) {
            unset($app->app_secret);
            if (!empty($app->app_secret_encrypted)) {
                $app->app_secret_masked = 'EAAG...'; // Mocked mask for now
            }
        }

        return new WP_REST_Response( $apps, 200 );
    }

    /**
     * Get Master Audit Logs.
     */
    public function get_audit_logs( $request ) {
        global $wpdb;
        $table_audit   = TableNameResolver::get_table_name( 'audit_logs' );
        $table_tenants = TableNameResolver::get_table_name( 'tenants' );
        $table_users   = $wpdb->users;

        $logs = $wpdb->get_results( "
            SELECT l.*, u.user_login, t.name as tenant_name 
            FROM $table_audit l 
            LEFT JOIN $table_users u ON l.user_id = u.ID
            LEFT JOIN $table_tenants t ON l.tenant_id = t.id
            ORDER BY l.created_at DESC LIMIT 200
        " );

        return new WP_REST_Response( $logs, 200 );
    }

    /**
     * Get Global Settings.
     */
    public function get_settings( $request ) {
        // Return global settings from wp_options or was_settings where tenant_id = 0
        return new WP_REST_Response([
            'master_graph_version' => get_option('was_master_graph_version', 'v25.0'),
            'master_msg_rate_limit' => get_option('was_master_msg_rate_limit', 60),
            'master_log_retention' => get_option('was_master_log_retention', 90),
        ], 200);
    }

    /**
     * Save Global Settings.
     */
    public function save_settings( $request ) {
        $params = $request->get_json_params();
        
        if (isset($params['master_graph_version'])) {
            update_option('was_master_graph_version', sanitize_text_field($params['master_graph_version']));
        }
        if (isset($params['master_msg_rate_limit'])) {
            update_option('was_master_msg_rate_limit', intval($params['master_msg_rate_limit']));
        }
        if (isset($params['master_log_retention'])) {
            update_option('was_master_log_retention', intval($params['master_log_retention']));
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Save/Create Meta App.
     */
    public function save_meta_app( $request ) {
        // Logic for saving Meta App from master panel
        return new WP_REST_Response(['success' => true], 200);
    }

	/**
	 * Check permissions for master admin.
	 */
	public function permissions_check() {
		return current_user_can( 'was_view_master_dashboard' );
	}
}
