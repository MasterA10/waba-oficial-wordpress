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
	 * Register rewrite rules for the SaaS App and Webhook.
	 */
	private function register_rewrite_rules() {
		add_action( 'init', function() {
			add_rewrite_rule( '^app/login/?$', 'index.php?was_app_page=login', 'top' );
			add_rewrite_rule( '^app/dashboard/?$', 'index.php?was_app_page=dashboard', 'top' );
			add_rewrite_rule( '^app/(.+)/?$', 'index.php?was_app_page=$matches[1]', 'top' );
            
            // Raw Webhook Rule
            add_rewrite_rule( '^was-meta-webhook/?$', 'index.php?was_meta_webhook=1', 'top' );
            
            // Flush rules automatically if version changes
            $installed_version = get_option( 'was_version' );
            if ( $installed_version !== WAS_VERSION ) {
                flush_rewrite_rules( false );
                update_option( 'was_version', WAS_VERSION );
            }
		}, 99 );

        // Prevent WordPress from redirecting webhooks (canonical)
        add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
            if ( get_query_var( 'was_meta_webhook' ) ) {
                return false;
            }
            return $redirect_url;
        }, 10, 2 );

		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'was_app_page';
            $vars[] = 'was_meta_webhook';
			return $vars;
		} );

		add_action( 'template_redirect', [ $this, 'handle_app_routing' ] );
        add_action( 'template_redirect', [ $this, 'handle_raw_webhook' ] );
	}

    /**
     * Handle the raw webhook endpoint for Meta verification.
     */
    public function handle_raw_webhook() {
        if ( (int) get_query_var( 'was_meta_webhook' ) !== 1 ) {
            return;
        }

        $controller = new \WAS\REST\WebhookController();

        if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
            $controller->verify_webhook( new \WP_REST_Request( 'GET', '/meta/webhook' ) );
            exit;
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $controller->receive_event( new \WP_REST_Request( 'POST', '/meta/webhook' ) );
            exit;
        }

        status_header( 405 );
        echo 'Method Not Allowed';
        exit;
    }

	/**
	 * Register basic hooks.
	 */
	private function register_hooks() {
		add_action( 'rest_api_init', [ \WAS\REST\Routes::class, 'register' ] );

        // Legal Pages Hooks (Template Redirect)
        \WAS\Compliance\LegalPagesGenerator::boot();
        
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
	 * Check if the plugin should run in Demo Mode.
	 * Returns true if no Meta App configuration is found.
	 */
	public static function is_demo_mode() {
		// Manual override via constant
		if ( defined( 'WAS_DEMO_MODE' ) ) {
			return (bool) WAS_DEMO_MODE;
		}

		$repository = new \WAS\Meta\MetaAppRepository();
		$app = $repository->get_active_app();

		// True if no app or empty fields
		if ( ! $app || empty( $app->app_id ) || empty( $app->app_secret ) ) {
			return true;
		}

		// True if fields contain common mock/placeholder strings
		$placeholders = ['mock', '123456', 'demo', 'test', 'insira'];
		foreach ($placeholders as $p) {
			if (strpos(strtolower($app->app_id), $p) !== false || strpos(strtolower($app->app_secret), $p) !== false) {
				return true;
			}
		}

		return false;
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
