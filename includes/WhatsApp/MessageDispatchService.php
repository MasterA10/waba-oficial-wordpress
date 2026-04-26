<?php

namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenVault;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço Central de Despacho de Mensagens
 * 
 * Único lugar responsável por enviar mensagens para a Meta.
 */
class MessageDispatchService {

    /**
     * Envia uma mensagem de texto simples.
     * 
     * @param string $phone_number_id ID do número remetente.
     * @param string $to Número de destino (com DDI).
     * @param string $text Conteúdo da mensagem.
     * @param string $token Access Token.
     * @return \WAS\Meta\MetaApiResponse
     */
    public function send_text(string $phone_number_id, string $to, string $text, string $token) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text
            ]
        ];

        return MetaApiClient::request('WA_SEND_MESSAGE', ['PHONE_NUMBER_ID' => $phone_number_id], $payload, $token);
    }

    /**
     * Envia um template.
     */
    public function send_template(string $phone_number_id, string $to, string $template_name, string $language_code, array $components, string $token) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $template_name,
                'language' => ['code' => $language_code],
                'components' => $components
            ]
        ];

        return MetaApiClient::request('WA_SEND_MESSAGE', ['PHONE_NUMBER_ID' => $phone_number_id], $payload, $token);
    }
}
