<?php
namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\Inbox\MessageRepository;
use WAS\Core\SystemLogger;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço responsável por exibir o indicador de "digitando..." e marcar mensagens como lidas na Meta Cloud API.
 */
final class TypingIndicatorService {
    private $api_client;
    private $token_service;
    private $message_repo;
    private $phone_service;

    public function __construct() {
        $this->api_client    = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo  = new MessageRepository();
        $this->phone_service = new PhoneNumberService();
    }

    /**
     * Exibe "digitando..." para uma mensagem específica (inbound).
     *
     * @param int $conversationId
     * @param int $messageId
     * @return array
     */
    public function show_typing(int $conversationId, int $messageId): array {
        $tenant_id = TenantContext::get_tenant_id();

        // 1. Busca a mensagem original
        $message = $this->message_repo->find_by_id($messageId);

        if (!$message) {
            SystemLogger::logWarning('TypingIndicator: Mensagem não encontrada.', ['message_id' => $messageId]);
            return ['success' => false, 'error' => 'Mensagem não encontrada'];
        }

        if ($message->direction !== 'inbound') {
            return ['success' => false, 'error' => 'Indicador de digitação só pode ser acionado para mensagens recebidas.'];
        }

        if (empty($message->wa_message_id)) {
            return ['success' => false, 'error' => 'Mensagem não possui ID oficial do WhatsApp.'];
        }

        // 2. Busca configurações de envio
        $phone_number_id = $this->phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) {
            return ['success' => false, 'error' => 'Configurações de WhatsApp incompletas para este tenant.'];
        }

        // 3. Monta o payload conforme documentação oficial da Meta
        $payload = [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $message->wa_message_id,
            'typing_indicator'  => [
                'type' => 'text'
            ]
        ];

        SystemLogger::logInfo('TypingIndicator: Enviando indicador de digitação...', [
            'conversation_id' => $conversationId,
            'wa_message_id'   => $message->wa_message_id,
        ]);

        $response = $this->api_client->postJson('messages.send', ['phone_number_id' => $phone_number_id], $payload, $token);

        if ($response['success'] ?? false) {
            SystemLogger::logInfo('TypingIndicator: Sucesso ao exibir "digitando...".', [
                'conversation_id' => $conversationId,
                'wa_message_id'   => $message->wa_message_id,
            ]);
            return ['success' => true];
        }

        SystemLogger::logError('TypingIndicator: Falha na Meta API.', [
            'response' => $response,
            'payload'  => $payload
        ]);

        return $response;
    }
}
