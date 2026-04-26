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
                    <button id="was-open-templates-inbox" class="was-btn-secondary" title="Enviar Modelo">
                        <span class="dashicons dashicons-layout"></span>
                    </button>
                    <textarea id="was-message-input" placeholder="Digite uma mensagem..."></textarea>
                    <button id="was-send-message" class="was-btn-primary" disabled>
                        <span class="dashicons dashicons-send"></span>
                    </button>
                </div>
                </footer>
                </div>
                </main>
                </div>

                <!-- Modal de Seleção de Template na Inbox -->
                <div id="was-inbox-tpl-modal" class="was-modal" style="display:none; position:fixed; z-index:12000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
                <div class="was-modal-content" style="background:white; margin:10% auto; padding:20px; width:450px; border-radius:8px;">
                <h3>Escolha um Modelo</h3>
                <div id="was-inbox-tpl-list" style="max-height:300px; overflow-y:auto; margin-bottom:20px;">
                <!-- Carregado via JS -->
                </div>
                <div style="text-align:right;">
                <button type="button" id="was-close-inbox-tpl-modal" class="button">Cancelar</button>
                </div>
                </div>
                </div>

