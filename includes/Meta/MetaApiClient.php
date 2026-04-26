<?php

namespace WAS\Meta;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cliente HTTP para Meta Graph API
 * 
 * Única porta de saída oficial para chamadas à Meta.
 */
class MetaApiClient {

    /**
     * Executa uma requisição para a Meta Graph API.
     * 
     * @param string $operation Operação interna (ex: WA_SEND_MESSAGE).
     * @param array $placeholders Variáveis para o path.
     * @param array $body Corpo da requisição.
     * @param string $token Access Token a ser usado (opcional).
     * @return MetaApiResponse
     */
    public static function request(string $operation, array $placeholders = [], array $body = [], string $token = ''): MetaApiResponse {
        $start_time = microtime(true);
        
        // 1. Resolve endpoint
        $resolved = MetaEndpointRegistry::resolve($operation, $placeholders);
        $method = $resolved['method'];
        $path = $resolved['path'];

        // 2. Resolve versão da Graph API (Pode vir de config futuramente)
        $version = WAS_META_GRAPH_DEFAULT_VERSION;
        
        // 3. Monta URL
        $url = WAS_META_GRAPH_BASE_URL . '/' . $version . '/' . ltrim($path, '/');

        // 4. Prepara headers
        $args = [
            'method'      => $method,
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => [
                'Content-Type' => 'application/json',
            ],
            'body'        => !empty($body) ? json_encode($body) : null,
        ];

        if (!empty($token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        // Se for GET, o body vai como query params se necessário (depende da operação)
        if ($method === 'GET' && !empty($body)) {
            $url = add_query_arg($body, $url);
            unset($args['body']);
        }

        // 5. Executa
        $response = wp_remote_request($url, $args);
        $duration_ms = (int) ((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            $apiResponse = new MetaApiResponse($operation, 500, ['error' => ['message' => $response->get_error_message()]]);
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body_content = wp_remote_retrieve_body($response);
            $apiResponse = new MetaApiResponse($operation, $status_code, $body_content);
            
            $request_id = wp_remote_retrieve_header($response, 'x-fb-request-id');
            if ($request_id) {
                $apiResponse->set_request_id($request_id);
            }
        }

        // 6. Loga requisição
        MetaApiRequestLogger::log(
            $operation,
            $method,
            $path,
            $apiResponse->status_code,
            $apiResponse->success,
            $apiResponse->data,
            $duration_ms,
            $apiResponse->error
        );

        return $apiResponse;
    }
}
