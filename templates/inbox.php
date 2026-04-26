<?php
/**
 * Template for the Inbox SaaS Experience
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="was-app-container was-inbox-container">
    <!-- Sidebar: Conversas -->
    <aside class="was-inbox-sidebar">
        <div class="was-inbox-header">
            <h2>Conversas</h2>
            <div class="was-inbox-actions">
                <button id="was-refresh-conversations" class="was-btn-icon" title="Atualizar">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
        </div>
        
        <div class="was-inbox-search">
            <input type="text" id="was-conversation-search" placeholder="Buscar contato...">
        </div>

        <div id="was-conversations-list" class="was-conversations-list">
            <!-- Renderizado via JS -->
            <div class="was-loading-state">Carregando conversas...</div>
        </div>
    </aside>

    <!-- Main: Chat -->
    <main class="was-inbox-main" id="was-inbox-main">
        <div id="was-no-conversation-selected" class="was-empty-state">
            <div class="was-empty-icon">💬</div>
            <h3>Selecione uma conversa</h3>
            <p>Escolha um contato ao lado para visualizar o histórico de mensagens.</p>
        </div>

        <div id="was-active-chat" class="was-active-chat" style="display: none;">
            <header class="was-chat-header">
                <div class="was-contact-info">
                    <div class="was-contact-avatar" id="was-chat-avatar"></div>
                    <div>
                        <h3 id="was-chat-contact-name">Nome do Contato</h3>
                        <span id="was-chat-contact-phone">5511999999999</span>
                    </div>
                </div>
                <div class="was-chat-actions">
                    <!-- Atribuição, Tags, etc (P2) -->
                </div>
            </header>

            <div id="was-messages-history" class="was-messages-history">
                <!-- Mensagens renderizadas aqui -->
            </div>

            <footer class="was-chat-footer">
                <div id="was-chat-input-container">
                    <textarea id="was-message-input" placeholder="Digite uma mensagem..." rows="1"></textarea>
                    <button id="was-send-message" class="was-btn-primary" disabled>
                        <span class="dashicons dashicons-send"></span>
                    </button>
                </div>
            </footer>
        </div>
    </main>
</div>
