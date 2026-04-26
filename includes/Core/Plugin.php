<?php
/**
 * Main Plugin class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class.
 */
class Plugin {

	/**
	 * Instance of this class.
	 *
	 * @var Plugin
	 */
	protected static $instance = null;

	/**
	 * Get the instance of this class.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 */
	public function boot() {
		$this->register_hooks();
		$this->register_rewrite_rules();
	}

	/**
	 * Register rewrite rules for the SaaS App.
	 */
	private function register_rewrite_rules() {
		add_action( 'init', function() {
			add_rewrite_rule( '^app/login/?$', 'index.php?was_app_page=login', 'top' );
			add_rewrite_rule( '^app/dashboard/?$', 'index.php?was_app_page=dashboard', 'top' );
			add_rewrite_rule( '^app/(.+)/?$', 'index.php?was_app_page=$matches[1]', 'top' );
		} );

		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'was_app_page';
			return $vars;
		} );

		add_action( 'template_redirect', [ $this, 'handle_app_routing' ] );
	}

	/**
	 * Register basic hooks.
	 */
	private function register_hooks() {
		add_action( 'rest_api_init', [ \WAS\REST\Routes::class, 'register' ] );
        
        // Inbox routes might be separate if register_routes is needed instance-wise
        add_action( 'rest_api_init', function() {
            (new \WAS\REST\InboxApiController())->register_routes();
        });

		if ( is_admin() ) {
			\WAS\Admin\Menu::getInstance()->init();
		}
	}

	/**
	 * Handle routing for the SaaS App.
	 */
	public function handle_app_routing() {
		$page = get_query_var( 'was_app_page' );
		if ( ! $page ) {
			return;
		}

		// Normalize page slug (convert slashes to hyphens for template lookup)
		$page = str_replace( '/', '-', trim( $page, '/' ) );

		$auth_controller = new \WAS\Auth\LoginController();
		
		if ( 'login' === $page ) {
			$auth_controller->render_login_page();
			exit;
		}

		// Protect other app pages.
		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url( '/app/login' ) );
			exit;
		}

		// Check tenant access.
		if ( ! \WAS\Auth\TenantGuard::check_access() ) {
			$this->render_no_tenant_error();
			exit;
		}

		$this->render_app_shell( $page );
		exit;
	}

	/**
	 * Render the app shell with the specific page.
	 *
	 * @param string $page The page identifier.
	 */
	private function render_app_shell( $page ) {
		$template = WAS_PLUGIN_DIR . "templates/app-shell.php";
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			wp_die( 'App Shell template missing.' );
		}
	}

	/**
	 * Render no tenant error.
	 */
	private function render_no_tenant_error() {
		wp_die( 'Acesso pendente. Você não possui uma empresa vinculada à sua conta.' );
	}
}
