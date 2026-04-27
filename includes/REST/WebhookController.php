<?php
namespace WAS\REST;

use WAS\Meta\MetaAppRepository;
use WAS\WhatsApp\WebhookSignatureValidator;
use WAS\WhatsApp\WebhookProcessor;
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
     * Verificação do Webhook (GET) - Meta Challenge
     */
    public function verify_webhook(WP_REST_Request $request) {
        $mode = $request->get_param('hub_mode') ?: $request->get_param('hub.mode');
        $token = $request->get_param('hub_verify_token') ?: $request->get_param('hub.verify_token');
        $challenge = $request->get_param('hub_challenge') ?: $request->get_param('hub.challenge');

        if ($mode === 'subscribe' && $token) {
            $repository = new MetaAppRepository();
            $app = $repository->get_active_app();

            // Comparação segura de token
            if ($app && hash_equals($app->verify_token, (string)$token)) {
                // IMPORTANTE: Deve retornar APENAS o challenge como texto puro
                header('Content-Type: text/plain');
                echo (string)$challenge;
                exit;
            }
        }

        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    /**
     * Recebimento de Eventos (POST)
     */
    public function receive_event(WP_REST_Request $request) {
        $repository = new MetaAppRepository();
        $app = $repository->get_active_app();
        
        if (!$app) {
            return new WP_REST_Response(['message' => 'App not configured'], 500);
        }

        $raw_body = $request->get_body();
        $signature = $request->get_header('x-hub-signature-256');

        // 1. Validar assinatura (Segurança)
        if (!WebhookSignatureValidator::is_valid($raw_body, $signature, $app->app_secret)) {
            return new WP_REST_Response(['message' => 'Invalid signature'], 403);
        }

        $payload = json_decode($raw_body, true);
        if (empty($payload)) {
            return new WP_REST_Response(['message' => 'Invalid payload'], 400);
        }

        // 2. Processar evento (Roteamento e Persistência)
        $processor = new WebhookProcessor();
        $processor->process($payload);

        // 3. Responder rápido para a Meta
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Rota pública
     */
    public function permissions_check() {
        return true;
    }
}
