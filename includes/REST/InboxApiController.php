<?php
namespace WAS\REST;

use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MessageRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class InboxApiController {
    private $conversation_repo;
    private $message_repo;

    public function __construct() {
        $this->conversation_repo = new ConversationRepository();
        $this->message_repo = new MessageRepository();
    }

    public function register_routes() {
        register_rest_route(WAS_REST_NAMESPACE, '/conversations', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_conversations'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_conversation_detail'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/messages', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_message'],
                'permission_callback' => [$this, 'check_send_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/assign', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'assign_conversation'],
                'permission_callback' => [$this, 'check_assignment_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/messages/text', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_text_message'],
                'permission_callback' => [$this, 'check_send_permission'],
            ],
        ]);
    }

    public function check_permission() {
        return current_user_can('was_view_inbox');
    }

    /**
     * Envia uma mensagem de texto em uma conversa.
     */
    public function send_text_message($request) {
        $id   = $request['id'];
        $text = $request->get_param('text');

        if (empty($text)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'O texto da mensagem é obrigatório'], 400);
        }

        try {
            $service = new \WAS\Inbox\OutboundMessageService();
            $result = $service->send_text($id, $text);

            if ($result['success']) {
                return new \WP_REST_Response($result, 200);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao enviar mensagem'
            ], 500);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'InboxApiController::send_text_message', 'conversation_id' => $id]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno do sistema ao enviar mensagem.'
            ], 500);
        }
    }

    public function check_send_permission() {
        $auth = Routes::check_auth();
        if ( is_wp_error( $auth ) ) {
            return $auth;
        }
        return current_user_can('was_send_messages');
    }

    public function check_assignment_permission() {
        $auth = Routes::check_auth();
        if ( is_wp_error( $auth ) ) {
            return $auth;
        }
        return current_user_can('was_assign_conversations');
    }

    /**
     * Atribui uma conversa a um atendente.
     */
    public function assign_conversation($request) {
        $id      = $request['id'];
        $user_id = $request->get_param('user_id');

        try {
            $result = $this->conversation_repo->assign($id, $user_id);

            if ($result !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => 'Conversa atribuída com sucesso'
                ], 200);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao atribuir conversa'
            ], 500);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'InboxApiController::assign_conversation', 'conversation_id' => $id]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno do sistema ao atribuir conversa.'
            ], 500);
        }
    }

    /**
     * Envia uma mensagem em uma conversa.
     */
    public function send_message($request) {
        $id      = $request['id'];
        $type    = $request->get_param('type') ?: 'text';
        $body    = $request->get_param('body');
        
        try {
            $conversation = $this->conversation_repo->get_by_id($id);
            if (!$conversation) {
                return new \WP_REST_Error('not_found', 'Conversa não encontrada', ['status' => 404]);
            }

            // Buscar dados do contato para saber o "to"
            $contact_repo = new \WAS\Inbox\ContactRepository();
            $contact = $contact_repo->get_by_id($conversation->contact_id);

            if (!$contact) {
                return new \WP_REST_Error('contact_not_found', 'Contato não encontrado', ['status' => 404]);
            }

            $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();

            // Chamar serviço de envio (Dev 02)
            $dispatch_service = new \WAS\WhatsApp\MessageDispatchService();
            $result = $dispatch_service->send_message($contact->wa_id, $type, $body, $tenant_id);

            if ($result['success']) {
                // Salvar no repositório local
                $this->message_repo->create_outbound([
                    'conversation_id' => $conversation->id,
                    'wa_message_id'   => $result['wa_message_id'],
                    'message_type'    => $type,
                    'text_body'       => $body,
                    'status'          => 'sent'
                ]);

                $this->conversation_repo->update_last_message_at($conversation->id);

                return new \WP_REST_Response([
                    'success' => true,
                    'wa_message_id' => $result['wa_message_id']
                ], 200);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao enviar mensagem'
            ], 500);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'InboxApiController::send_message', 'conversation_id' => $id]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno do sistema ao enviar mensagem de template/mídia.'
            ], 500);
        }
    }

    /**
     * Lista conversas do tenant atual.
     */
    public function get_conversations($request) {
        $limit  = $request->get_param('limit') ?: 20;
        $offset = $request->get_param('offset') ?: 0;

        $conversations = $this->conversation_repo->list_conversations($limit, $offset);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $conversations
        ], 200);
    }

    /**
     * Busca detalhes e mensagens de uma conversa (WAS-075).
     */
    public function get_conversation_detail($request) {
        $id = $request['id'];
        
        $conversation = $this->conversation_repo->get_by_id($id);
        
        if (!$conversation) {
            return new \WP_REST_Error('not_found', 'Conversa não encontrada', ['status' => 404]);
        }

        $messages = $this->message_repo->list_by_conversation($id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'conversation' => $conversation,
                'messages'     => $messages
            ]
        ], 200);
    }
}
