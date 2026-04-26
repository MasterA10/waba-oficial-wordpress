<?php
/**
 * TableNameResolver class.
 *
 * @package WAS\Core
 */

namespace WAS\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolver for table names.
 */
class TableNameResolver {

	/**
	 * Get the full table name with prefix.
	 *
	 * @param string $table The table identifier.
	 * @return string
	 */
	public static function get_table_name( $table ) {
		global $wpdb;
		return $wpdb->prefix . 'was_' . $table;
	}

	public static function getAuditLogsTable() {
		return self::get_table_name( 'audit_logs' );
	}

    public static function getMetaApiLogsTable() {
        return self::get_table_name( 'meta_api_logs' );
    }

    public static function getWebhookEventsTable() {
        return self::get_table_name( 'webhook_events' );
    }

    public static function getTemplatesTable() {
        return self::get_table_name( 'message_templates' );
    }

	/**
	 * List of all plugin tables.
	 *
	 * @return array
	 */
	public static function get_all_tables() {
		return [
			'tenants',
			'tenant_users',
			'meta_apps',
			'whatsapp_accounts',
			'whatsapp_phone_numbers',
			'meta_tokens',
			'contacts',
			'contact_optins',
			'conversations',
			'messages',
			'message_statuses',
			'message_templates',
			'media',
			'webhook_events',
			'audit_logs',
			'settings',
		];
	}
}
