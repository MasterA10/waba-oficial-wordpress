<?php

namespace WAS\Meta;

use WAS\Core\TableNameResolver;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger de Requisições Meta API
 */
class MetaApiRequestLogger {

    /**
     * Registra uma chamada para a Meta Graph API.
     */
    public static function log(string $operation, string $method, string $path, int $status_code, bool $success, $response_response, int $duration_ms, array $error = []) {
        global $wpdb;

        $table_name = TableNameResolver::getMetaApiLogsTable();
        $tenant_id = TenantContext::getTenantId();

        // Sanitização de path (remover tokens se existirem na URL)
        $path = preg_replace('/access_token=[^&]+/', 'access_token=TOKEN_MASKED', $path);

        $wpdb->insert($table_name, [
            'tenant_id'     => $tenant_id,
            'operation'     => $operation,
            'method'        => $method,
            'path'          => $path,
            'status_code'   => $status_code,
            'success'       => $success ? 1 : 0,
            'error_code'    => $error['code'] ?? null,
            'error_subcode' => $error['subcode'] ?? null,
            'error_message' => $error['message'] ?? null,
            'duration_ms'   => $duration_ms,
            'created_at'    => current_time('mysql', true),
        ]);
    }
}
