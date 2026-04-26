<?php
/**
 * MetaEndpointRegistry class.
 *
 * @package WAS\Meta
 */

namespace WAS\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for Meta API endpoints.
 */
class MetaEndpointRegistry {

	/**
	 * Resolve an operation to an endpoint configuration.
	 *
	 * @param string $operation The operation name.
	 * @param array  $params    Parameters for placeholders.
	 * @return array
	 * @throws \Exception If operation is unknown.
	 */
	public static function resolve( $operation, $params = [] ) {
		$endpoints = [
			'WA_SEND_MESSAGE' => [
				'method' => 'POST',
				'path'   => '/{phone_number_id}/messages',
			],
			'WA_UPLOAD_MEDIA' => [
				'method' => 'POST',
				'path'   => '/{phone_number_id}/media',
			],
			'WA_LIST_TEMPLATES' => [
				'method' => 'GET',
				'path'   => '/{waba_id}/message_templates',
			],
			'WA_CREATE_TEMPLATE' => [
				'method' => 'POST',
				'path'   => '/{waba_id}/message_templates',
			],
			'WA_SUBSCRIBE_WABA_WEBHOOKS' => [
				'method' => 'POST',
				'path'   => '/{waba_id}/subscribed_apps',
			],
			'OAUTH_EXCHANGE_CODE' => [
				'method' => 'GET',
				'path'   => '/oauth/access_token',
			],
		];

		if ( ! isset( $endpoints[ $operation ] ) ) {
			throw new \Exception( "Unknown Meta API operation: {$operation}" );
		}

		$config = $endpoints[ $operation ];
		$path = $config['path'];

		foreach ( $params as $key => $value ) {
			$path = str_replace( '{' . $key . '}', $value, $path );
		}

		$config['path'] = $path;
		return $config;
	}
}
