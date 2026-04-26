<?php

namespace WAS\REST;

use WAS\WhatsApp\WhatsAppAccountRepository;
use WAS\WhatsApp\PhoneNumberRepository;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para WhatsApp (Accounts, Numbers)
 */
class WhatsAppApiController {

    private $accountRepository;
    private $numberRepository;

    public function __construct() {
        $this->accountRepository = new WhatsAppAccountRepository();
        $this->numberRepository = new PhoneNumberRepository();
    }

    /**
     * Lista contas WABA do tenant atual.
     */
    public function get_accounts(WP_REST_Request $request) {
        $accounts = $this->accountRepository->getByTenant();
        
        // Para cada conta, podemos opcionalmente anexar os números (P1)
        
        return new WP_REST_Response($accounts, 200);
    }

    /**
     * Lista números de telefone do tenant atual.
     */
    public function get_phone_numbers(WP_REST_Request $request) {
        // Implementar se necessário separadamente
        return new WP_REST_Response([], 200);
    }

    /**
     * Verifica permissão.
     */
    public function permissions_check() {
        return Routes::check_auth();
    }
}
