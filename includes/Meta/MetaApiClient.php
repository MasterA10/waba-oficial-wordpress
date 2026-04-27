<?php
namespace WAS\Meta;

use WAS\Meta\MetaAppRepository;

if (!defined('ABSPATH')) {
    exit;
}

class MetaApiClient {
    private $version;
    private $baseUrl = 'https://graph.facebook.com';

    public function __construct() {
        $repository = new MetaAppRepository();
        $app = $repository->get_active_app();
        $this->version = $app->graph_version ?? 'v25.0';
    }

    public function postJson(string $operation, array $pathParams, array $body, string $token) {
        $url = $this->buildUrl($operation, $pathParams);
        $start_time = microtime(true);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        $result = $this->parse($response);
        $duration = (int)((microtime(true) - $start_time) * 1000);

        MetaApiRequestLogger::log(
            $operation,
            'POST',
            $url,
            $result['code'] ?? wp_remote_retrieve_response_code($response),
            $result['success'],
            $result,
            $duration,
            !$result['success'] ? ['message' => $result['error'] ?? ''] : []
        );

        return $result;
    }

    public function get(string $operation, array $pathParams, array $query, string $token) {
        $url = add_query_arg($query, $this->buildUrl($operation, $pathParams));
        $start_time = microtime(true);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
        ]);

        $result = $this->parse($response);
        $duration = (int)((microtime(true) - $start_time) * 1000);

        MetaApiRequestLogger::log(
            $operation,
            'GET',
            $url,
            $result['code'] ?? wp_remote_retrieve_response_code($response),
            $result['success'],
            $result,
            $duration,
            !$result['success'] ? ['message' => $result['error'] ?? ''] : []
        );

        return $result;
    }

    public function delete(string $operation, array $pathParams, array $query, string $token) {
        $url = add_query_arg($query, $this->buildUrl($operation, $pathParams));
        $start_time = microtime(true);

        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
        ]);

        $result = $this->parse($response);
        $duration = (int)((microtime(true) - $start_time) * 1000);

        MetaApiRequestLogger::log(
            $operation,
            'DELETE',
            $url,
            $result['code'] ?? wp_remote_retrieve_response_code($response),
            $result['success'],
            $result,
            $duration,
            !$result['success'] ? ['message' => $result['error'] ?? ''] : []
        );

        return $result;
    }

    private function buildUrl(string $operation, array $pathParams): string {
        $path = MetaEndpointRegistry::resolve($operation, $pathParams);
        return sprintf('%s/%s%s', $this->baseUrl, $this->version, $path);
    }

    private function parse($response) {
        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400 || isset($body['error'])) {
            return [
                'success' => false,
                'error' => $body['error']['message'] ?? 'Erro na Meta API',
                'code' => $body['error']['code'] ?? $code
            ];
        }

        return array_merge(['success' => true], $body ?: []);
    }
}
