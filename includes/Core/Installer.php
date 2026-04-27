<?php
/**
 * Installer class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer.
 */
class Installer {

	/**
	 * Run installation logic.
	 */
	public static function install() {
		self::create_tables();
		self::validate_installation();
		self::create_capabilities();
		\WAS\Compliance\LegalPagesGenerator::generateAll();
		self::seed_data();
        flush_rewrite_rules(false);
	}

	/**
	 * Validate that all tables were created.
	 */
	private static function validate_installation() {
		global $wpdb;
		$tables = TableNameResolver::get_all_tables();
		$missing = [];

		foreach ( $tables as $table ) {
			$full_name = TableNameResolver::get_table_name( $table );
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $full_name ) ) !== $full_name ) {
				$missing[] = $full_name;
			}
		}

		if ( ! empty( $missing ) ) {
			error_log( 'WAS Error: Missing tables after installation: ' . implode( ', ', $missing ) );
			add_action( 'admin_notices', function() use ( $missing ) {
				echo '<div class="error"><p>WhatsApp SaaS Core: Missing tables: ' . esc_html( implode( ', ', $missing ) ) . '</p></div>';
			} );
		}
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// WAS-011: Tenants table.
		$table_tenants = TableNameResolver::get_table_name( 'tenants' );
		$sql_tenants = "CREATE TABLE $table_tenants (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(100) NOT NULL,
			status varchar(50) DEFAULT 'active',
			plan varchar(50) DEFAULT 'free',
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";
		dbDelta( $sql_tenants );

		// WAS-012: Tenant Users table.
		$table_tenant_users = TableNameResolver::get_table_name( 'tenant_users' );
		$sql_tenant_users = "CREATE TABLE $table_tenant_users (
			tenant_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			role varchar(50) NOT NULL,
			status varchar(50) DEFAULT 'active',
			PRIMARY KEY  (tenant_id, user_id),
			KEY tenant_id (tenant_id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql_tenant_users );

		// WAS-013: Meta Apps table.
		$table_meta_apps = TableNameResolver::get_table_name( 'meta_apps' );
		$sql_meta_apps = "CREATE TABLE $table_meta_apps (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			app_id varchar(100) NOT NULL,
			app_secret text NOT NULL,
			config_id varchar(100) NULL,
			graph_version varchar(20) DEFAULT 'v25.0',
			webhook_verify_token varchar(255) NULL,
			verify_token varchar(255) NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_meta_apps );

		// WAS-014: WhatsApp Accounts table.
		$table_wa_accounts = TableNameResolver::get_table_name( 'whatsapp_accounts' );
		$sql_wa_accounts = "CREATE TABLE $table_wa_accounts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			meta_business_id varchar(100) NULL,
			waba_id varchar(100) NOT NULL,
			name varchar(255) NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_waba (tenant_id, waba_id),
			KEY waba_id (waba_id),
			KEY tenant_id (tenant_id)
		) $charset_collate;";
		dbDelta( $sql_wa_accounts );

		// WAS-015: WhatsApp Phone Numbers table.
		$table_wa_phones = TableNameResolver::get_table_name( 'whatsapp_phone_numbers' );
		$sql_wa_phones = "CREATE TABLE $table_wa_phones (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			whatsapp_account_id bigint(20) UNSIGNED NOT NULL,
			phone_number_id varchar(100) NOT NULL,
			display_phone_number varchar(50) NULL,
			verified_name varchar(255) NULL,
			status varchar(50) DEFAULT 'active',
			is_default tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_number_id (phone_number_id),
			KEY tenant_id (tenant_id),
			KEY wa_account_id (whatsapp_account_id)
		) $charset_collate;";
		dbDelta( $sql_wa_phones );

		// WAS-016: Meta Tokens table.
		$table_meta_tokens = TableNameResolver::get_table_name( 'meta_tokens' );
		$sql_meta_tokens = "CREATE TABLE $table_meta_tokens (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			whatsapp_account_id bigint(20) UNSIGNED NULL,
			access_token_encrypted text NOT NULL,
			scopes text NULL,
			expires_at datetime NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY wa_account_id (whatsapp_account_id)
		) $charset_collate;";
		dbDelta( $sql_meta_tokens );

		// WAS-017: Contacts table.
		$table_contacts = TableNameResolver::get_table_name( 'contacts' );
		$sql_contacts = "CREATE TABLE $table_contacts (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			wa_id varchar(100) NOT NULL,
			phone varchar(50) NOT NULL,
			profile_name varchar(255) NULL,
			tags text NULL,
			opt_in_status varchar(50) DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_wa_id (tenant_id, wa_id),
			KEY phone (phone),
			KEY tenant_id (tenant_id)
		) $charset_collate;";
		dbDelta( $sql_contacts );

		// WAS-018: Opt-ins table.
		$table_optins = TableNameResolver::get_table_name( 'contact_optins' );
		$sql_optins = "CREATE TABLE $table_optins (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			contact_id bigint(20) UNSIGNED NOT NULL,
			source varchar(100) NULL,
			consent_text text NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY tenant_id (tenant_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_optins );

		// WAS-019: Conversations table.
		$table_conversations = TableNameResolver::get_table_name( 'conversations' );
		$sql_conversations = "CREATE TABLE $table_conversations (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			contact_id bigint(20) UNSIGNED NOT NULL,
			phone_number_id varchar(100) NOT NULL,
			assigned_user_id bigint(20) UNSIGNED NULL,
			status varchar(50) DEFAULT 'open',
			last_message_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY contact_id (contact_id),
			KEY status (status),
			KEY last_message_at (last_message_at)
		) $charset_collate;";
		dbDelta( $sql_conversations );

		// WAS-020: Messages table.
		$table_messages = TableNameResolver::get_table_name( 'messages' );
		$sql_messages = "CREATE TABLE $table_messages (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			conversation_id bigint(20) UNSIGNED NOT NULL,
			direction varchar(20) NOT NULL,
			message_type varchar(50) NOT NULL,
			wa_message_id varchar(255) NULL,
			text_body text NULL,
			status varchar(50) DEFAULT 'sent',
			raw_payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY wa_message_id (wa_message_id),
			KEY conversation_id (conversation_id),
			KEY tenant_id (tenant_id),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_messages );

		// WAS-021: Message Statuses table.
		$table_msg_statuses = TableNameResolver::get_table_name( 'message_statuses' );
		$sql_msg_statuses = "CREATE TABLE $table_msg_statuses (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wa_message_id varchar(255) NOT NULL,
			status varchar(50) NOT NULL,
			raw_payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY wa_message_id (wa_message_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_msg_statuses );

		// WAS-022: Message Templates table.
		$table_templates = TableNameResolver::get_table_name( 'message_templates' );
		$sql_templates = "CREATE TABLE $table_templates (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			whatsapp_account_id bigint(20) UNSIGNED NOT NULL,
			meta_template_id varchar(190) NULL,
			waba_id varchar(100) NOT NULL,
			name varchar(190) NOT NULL,
			category varchar(80) NOT NULL,
			language varchar(20) NOT NULL DEFAULT 'pt_BR',
			status varchar(50) DEFAULT 'draft',
			friendly_payload longtext NULL,
			meta_payload longtext NULL,
			variable_map longtext NULL,
			header_type varchar(50) NULL,
			body_text longtext NOT NULL,
			footer_text text NULL,
			components_json longtext NULL,
			buttons_json longtext NULL,
			rejection_reason text NULL,
			last_meta_error text NULL,
			submitted_at datetime NULL,
			approved_at datetime NULL,
			rejected_at datetime NULL,
			paused_at datetime NULL,
			deleted_at datetime NULL,
			synced_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_waba_template_lang (tenant_id, waba_id, name, language),
			KEY tenant_id (tenant_id),
			KEY waba_id (waba_id),
			KEY status (status),
			KEY category (category)
		) $charset_collate;";
		dbDelta( $sql_templates );

		// WAS-023: Media table.
		$table_media = TableNameResolver::get_table_name( 'media' );
		$sql_media = "CREATE TABLE $table_media (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			meta_media_id varchar(255) NULL,
			wp_attachment_id bigint(20) UNSIGNED NULL,
			mime_type varchar(100) NULL,
			filename varchar(255) NULL,
			direction varchar(20) NULL,
			status varchar(50) DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY meta_media_id (meta_media_id),
			KEY tenant_id (tenant_id)
		) $charset_collate;";
		dbDelta( $sql_media );

		// WAS-024: Webhook Events table.
		$table_webhooks = TableNameResolver::get_table_name( 'webhook_events' );
		$sql_webhooks = "CREATE TABLE $table_webhooks (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			payload longtext NOT NULL,
			processing_status varchar(50) DEFAULT 'pending',
			signature_valid tinyint(1) DEFAULT 0,
			received_at datetime NOT NULL,
			processed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY processing_status (processing_status),
			KEY received_at (received_at)
		) $charset_collate;";
		dbDelta( $sql_webhooks );

		// WAS-025: Audit Logs table.
		$table_audit = TableNameResolver::get_table_name( 'audit_logs' );
		$sql_audit = "CREATE TABLE $table_audit (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NULL,
			user_id bigint(20) UNSIGNED NULL,
			action varchar(100) NOT NULL,
			entity_type varchar(100) NULL,
			entity_id varchar(100) NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY created_at (created_at),
			KEY tenant_id (tenant_id)
		) $charset_collate;";
		dbDelta( $sql_audit );

		// WAS-026: Settings table.
		$table_settings = TableNameResolver::get_table_name( 'settings' );
		$sql_settings = "CREATE TABLE $table_settings (
			tenant_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			setting_key varchar(100) NOT NULL,
			setting_value longtext NULL,
			autoload varchar(20) DEFAULT 'yes',
			PRIMARY KEY  (tenant_id, setting_key),
			KEY setting_key (setting_key)
		) $charset_collate;";
		dbDelta( $sql_settings );

		// WAS-027: Onboarding Sessions table.
		$table_onboarding = TableNameResolver::get_table_name( 'onboarding_sessions' );
		$sql_onboarding = "CREATE TABLE $table_onboarding (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			session_uuid varchar(100) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'started',
			meta_code text DEFAULT NULL,
			waba_id varchar(100) DEFAULT NULL,
			phone_number_id varchar(100) DEFAULT NULL,
			business_id varchar(100) DEFAULT NULL,
			error_message text DEFAULT NULL,
			raw_session_payload longtext DEFAULT NULL,
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			failed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_uuid (session_uuid),
			KEY tenant_id (tenant_id),
			KEY user_id (user_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql_onboarding );

		// Meta API Logs table.
		$table_meta_logs = TableNameResolver::get_table_name( 'meta_api_logs' );
		$sql_meta_logs = "CREATE TABLE $table_meta_logs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tenant_id bigint(20) UNSIGNED NULL,
			operation varchar(100) NOT NULL,
			method varchar(10) NOT NULL,
			path text NOT NULL,
			request_payload longtext NULL,
			response_body longtext NULL,
			status_code int(5) NOT NULL,
			success tinyint(1) NOT NULL,
			error_code varchar(50) NULL,
			error_subcode varchar(50) NULL,
			error_message text NULL,
			duration_ms int(10) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY tenant_id (tenant_id),
			KEY operation (operation),
			KEY created_at (created_at)
		) $charset_collate;";
		dbDelta( $sql_meta_logs );
	}

	/**
	 * Create custom capabilities and roles.
	 */
	private static function create_capabilities() {
		Capabilities::register();
	}

	/**
	 * Seed initial data.
	 */
	private static function seed_data() {
		global $wpdb;

		$table_tenants = TableNameResolver::get_table_name( 'tenants' );
		$table_users   = TableNameResolver::get_table_name( 'tenant_users' );

		// Check if we already have tenants.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_tenants" );
		if ( $count > 0 ) {
			return;
		}

		// Create default tenant for the first administrator found.
		$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
		if ( empty( $admins ) ) {
			return;
		}

		$admin = $admins[0];

		$wpdb->insert( $table_tenants, [
			'name'       => 'Minha Empresa Demo',
			'slug'       => 'demo',
			'status'     => 'active',
			'plan'       => 'free',
			'created_at' => current_time( 'mysql', true ),
		] );

		$tenant_id = $wpdb->insert_id;

		$wpdb->insert( $table_users, [
			'tenant_id' => $tenant_id,
			'user_id'   => $admin->ID,
			'role'      => 'platform_owner',
			'status'    => 'active',
		] );

		// Set as current tenant for this user.
		update_user_meta( $admin->ID, '_was_current_tenant_id', $tenant_id );
	}
}
