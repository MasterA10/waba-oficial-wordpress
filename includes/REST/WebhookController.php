<?php

namespace WAS\REST;

use WAS\Meta\MetaAppRepository;
use WAS\Core\TableNameResolver;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para Webhooks da Meta
 */
class WebhookController {

    /**
     * Verificação do Webhook (GET)
     */
    public function verify_webhook(WP_REST_Request $request) {
        $mode = $request->get_param('hub_mode');
        $token = $request->get_param('hub_verify_token');
        $challenge = $request->get_param('hub_challenge');

        if ($mode && $token) {
            $repository = new MetaAppRepository();
            $app = $repository->get_active_app();

            if ($app && $token === $app->verify_token) {
                return new WP_REST_Response((int)$challenge, 200);
            }
        }

        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    /**
     * Recebimento de Eventos (POST)
     */
    public function receive_event(WP_REST_Request $request) {
        global $wpdb;

        $payload = $request->get_json_params();
        if (empty($payload)) {
            return new WP_REST_Response(['message' => 'Invalid payload'], 400);
        }

        $table_name = TableNameResolver::getWebhookEventsTable();
        
        $wpdb->insert($table_name, [
            'payload'           => json_encode($payload),
            'processing_status' => 'pending',
            'received_at'       => current_time('mysql', true),
        ]);

        // Responde rápido com 200 OK
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Rota pública
     */
    public function permissions_check() {
        return true;
    }
}
