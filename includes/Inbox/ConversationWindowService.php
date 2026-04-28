<?php
namespace WAS\Inbox;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço responsável por gerenciar a Janela de Atendimento de 24 horas (Customer Service Window).
 * Conforme regras da Meta, mensagens livres só podem ser enviadas dentro dessa janela.
 */
class ConversationWindowService {
    private $repository;

    public function __construct() {
        $this->repository = new ConversationRepository();
    }

    /**
     * Atualiza/Renova a janela de atendimento a partir de uma mensagem inbound do cliente.
     */
    public function refreshFromInboundMessage(int $tenantId, int $conversationId, string $waMessageId, int $timestamp): void {
        // Se o timestamp não for fornecido ou for inválido, usa o atual
        $messageTime = $timestamp > 0 ? $timestamp : time();
        
        $lastCustomerMessageAt = gmdate('Y-m-d H:i:s', $messageTime);
        $expiresAt = gmdate('Y-m-d H:i:s', $messageTime + (24 * 60 * 60));

        $this->repository->update($conversationId, [
            'last_customer_message_at'           => $lastCustomerMessageAt,
            'customer_service_window_expires_at' => $expiresAt,
            'customer_service_window_status'     => 'open',
            'last_inbound_wa_message_id'         => $waMessageId,
            'updated_at'                         => current_time('mysql', true)
        ]);

        \WAS\Core\SystemLogger::logInfo('WindowService: Janela de atendimento renovada.', [
            'tenant_id'       => $tenantId,
            'conversation_id' => $conversationId,
            'message_time'    => gmdate('Y-m-d H:i:s', $messageTime),
            'expires_at'      => $expiresAt,
            'wa_message_id'   => $waMessageId
        ]);
    }

    /**
     * Retorna o estado detalhado da janela de uma conversa.
     */
    public function getWindowState(object $conversation, bool $debugLog = false): array {
        if (empty($conversation->customer_service_window_expires_at)) {
            if ($debugLog) {
                \WAS\Core\SystemLogger::logInfo('WindowService_Debug: Conversa sem data de expiração.', [
                    'conversation_id' => $conversation->id ?? 'unknown'
                ]);
            }
            return [
                'status'            => 'closed',
                'is_open'           => false,
                'expires_at'        => null,
                'seconds_remaining' => 0,
                'human_remaining'   => 'expirada',
                'can_send_freeform' => false,
                'must_use_template' => true,
            ];
        }

        $now = time();
        $expiresAt = strtotime($conversation->customer_service_window_expires_at . ' UTC');
        $remaining = max(0, $expiresAt - $now);

        if ($remaining <= 0) {
            return [
                'status'            => 'closed',
                'is_open'           => false,
                'expires_at'        => $conversation->customer_service_window_expires_at,
                'seconds_remaining' => 0,
                'human_remaining'   => 'expirada',
                'can_send_freeform' => false,
                'must_use_template' => true,
            ];
        }

        // Se faltar menos de 1 hora, status muda para closing_soon
        $status = $remaining <= (60 * 60) ? 'closing_soon' : 'open';

        $state = [
            'status'            => $status,
            'is_open'           => true,
            'expires_at'        => $conversation->customer_service_window_expires_at,
            'seconds_remaining' => $remaining,
            'human_remaining'   => $this->formatHumanRemaining($remaining),
            'can_send_freeform' => true,
            'must_use_template' => false,
        ];

        if ($debugLog) {
            \WAS\Core\SystemLogger::logInfo('WindowService_Debug: Cálculo de janela realizado.', [
                'conversation_id' => $conversation->id ?? 'unknown',
                'raw_expires_at'  => $conversation->customer_service_window_expires_at,
                'time_now_utc'    => gmdate('Y-m-d H:i:s', $now),
                'now_epoch'       => $now,
                'expires_epoch'   => $expiresAt,
                'remaining'       => $remaining,
                'final_status'    => $status
            ]);
        }

        return $state;
    }

    /**
     * Verifica se uma mensagem livre (texto/mídia) pode ser enviada.
     * @throws \RuntimeException
     */
    public function assertCanSendFreeform(int $tenantId, int $conversationId): void {
        $conversation = $this->repository->findForTenant($conversationId, $tenantId);
        if (!$conversation) {
            \WAS\Core\SystemLogger::logError('WindowService: Falha na validação - Conversa não encontrada.', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId
            ]);
            throw new \RuntimeException('Conversa não encontrada.');
        }

        $window = $this->getWindowState($conversation, true);
        
        if (!$window['can_send_freeform']) {
            \WAS\Core\SystemLogger::logWarning('WindowService: Bloqueio de envio - Janela fechada.', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'window_state' => $window
            ]);
            throw new \RuntimeException('A janela de atendimento de 24 horas está fechada. Use um template para retomar o contato.');
        }

        \WAS\Core\SystemLogger::logInfo('WindowService: Validação de envio permitida.', [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'expires_at' => $window['expires_at'],
            'seconds_remaining' => $window['seconds_remaining']
        ]);
    }

    /**
     * Formata o tempo restante de forma amigável.
     */
    private function formatHumanRemaining(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }
        return "{$minutes}min";
    }
}
