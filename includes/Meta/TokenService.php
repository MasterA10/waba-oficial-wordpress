<?php
namespace WAS\Meta;

if (!defined('ABSPATH')) {
    exit;
}

class TokenService {
    /**
     * Obtém o token ativo para o tenant/waba.
     */
    public function get_active_token($tenant_id, $waba_internal_id = null) {
        $vault = new TokenVault();
        return $vault->get_valid_token($tenant_id, $waba_internal_id);
    }
}
