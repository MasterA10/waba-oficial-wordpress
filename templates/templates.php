<div class="wrap">
    <h1>Templates Oficiais</h1>
    
    <div id="was-templates-app">
        <div class="was-actions-bar" style="margin: 20px 0; display: flex; gap: 10px;">
            <button id="sync-templates" class="button">Sincronizar da Meta</button>
            <button id="was-open-create-modal" class="button button-primary">Novo Template</button>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Idioma</th>
                    <th>Categoria</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="template-list-body">
                <!-- Preenchido via JS -->
            </tbody>
        </table>
    </div>

    <!-- Modal de Criação -->
    <div id="was-create-template-modal" class="was-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:10% auto; padding:20px; width:50%; border-radius:8px;">
            <h2>Novo Template Official</h2>
            <form id="was-create-template-form">
                <p>
                    <label>Nome (apenas letras minúsculas e _)</label><br>
                    <input type="text" id="tpl-name" class="regular-text" required placeholder="ex: promocao_verao">
                </p>
                <p>
                    <label>Idioma</label><br>
                    <select id="tpl-lang" class="regular-text">
                        <option value="pt_BR">Português (BR)</option>
                        <option value="en_US">Inglês (US)</option>
                        <option value="es_ES">Espanhol</option>
                    </select>
                </p>
                <p>
                    <label>Categoria</label><br>
                    <select id="tpl-cat" class="regular-text">
                        <option value="UTILITY">Utilidade</option>
                        <option value="MARKETING">Marketing</option>
                        <option value="AUTHENTICATION">Autenticação</option>
                    </select>
                </p>
                <p>
                    <label>Corpo da Mensagem</label><br>
                    <textarea id="tpl-body" style="width:100%;" rows="5" required placeholder="Olá {{1}}, seu código é {{2}}"></textarea>
                </p>
                <div style="text-align:right;">
                    <button type="button" id="was-close-create-modal" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Criar na Meta</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Envio -->
    <div id="was-send-template-modal" class="was-modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div class="was-modal-content" style="background:white; margin:10% auto; padding:20px; width:40%; border-radius:8px;">
            <h2>Enviar Template</h2>
            <form id="was-send-template-form">
                <input type="hidden" id="send-tpl-id">
                <p>
                    <strong>Template:</strong> <span id="send-tpl-name-display"></span>
                </p>
                <p>
                    <label>Destinatário (WhatsApp ID / Telefone com DDI)</label><br>
                    <input type="text" id="send-tpl-to" class="regular-text" required placeholder="5511999999999">
                </p>
                <p class="description">Nota: No MVP, variáveis {{1}}, {{2}} etc. devem ser enviadas como texto simples no corpo (P2 implementará campos dinâmicos).</p>
                <div style="text-align:right;">
                    <button type="button" id="was-close-send-modal" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Enviar Mensagem</button>
                </div>
            </form>
        </div>
    </div>
</div>
