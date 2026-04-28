/**
 * WAS App Core - Main JavaScript
 * 
 * Standardized for Meta SaaS Experience.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.wasApp === 'undefined') return;

    const { restUrl, nonce } = window.wasApp;
    let currentConversationId = null;
    let wizardStep = 1;
    let wizardButtons = [];
    let editingTemplateId = null;
    let wizardVariables = {}; // Ex: { 'nome': 'Fulano' }
    let activeSendTemplate = null;

    // Safety: Ensure window helpers are defined early
    window.updateVariableExample = (key, val) => {
        wizardVariables[key] = val;
        updatePreview();
        renderChecklist();
    };

    window.updateWizardButton = (i, field, val) => { 
        if (!wizardButtons[i]) return;
        wizardButtons[i][field] = val; 
        if (field === 'url') renderWizardButtons(); // Re-render to show/hide dynamic URL example field
        updatePreview(); 
        renderChecklist();
    };

    window.removeWizardButton = (i) => { 
        wizardButtons.splice(i, 1); 
        renderWizardButtons(); 
        updatePreview(); 
        renderChecklist();
    };

    /**
     * Helper to make authenticated API requests to WP REST API
     */
    async function wasApiFetch(path, method = 'GET', body = null) {
        const url = `${restUrl}${path}`;
        const options = {
            method,
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        if (body) options.body = JSON.stringify(body);

        try {
            const response = await fetch(url, options);
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || `HTTP error! status: ${response.status}`);
            return data;
        } catch (error) {
            console.error(`WAS API Error [${method} ${path}]:`, error);
            throw error;
        }
    }

    /**
     * Dashboard
     */
    if (document.getElementById('stat-wa-accounts')) {
        initDashboard();
    }

    /**
     * Master Admin Dashboard
     */
    if (document.getElementById('master-stat-tenants')) {
        initMasterDashboard();
    }

    if (document.getElementById('master-tenants-list')) {
        initMasterTenants();
    }

    if (document.getElementById('master-apps-list')) {
        initMasterApps();
    }

    if (document.getElementById('master-wabas-list')) {
        initMasterWabas();
    }

    if (document.getElementById('master-phones-list')) {
        initMasterPhones();
    }

    if (document.getElementById('master-onboardings-list')) {
        initMasterOnboardings();
    }

    if (document.getElementById('master-templates-list')) {
        initMasterTemplates();
    }

    if (document.getElementById('master-webhooks-list')) {
        initMasterWebhooks();
    }

    if (document.getElementById('master-tokens-list')) {
        initMasterTokens();
    }

    if (document.getElementById('master-review-checklist')) {
        initMasterReview();
    }

    if (document.getElementById('master-audit-list')) {
        initMasterAudit();
    }

    if (document.getElementById('was-master-settings-form')) {
        initMasterSettings();
    }

    async function initMasterAudit() {
        const tb = document.getElementById('master-audit-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/audit-logs');
            tb.innerHTML = (data || []).map(l => `
                <tr>
                    <td>${l.created_at}</td>
                    <td>${l.user_login || 'Sistema'}</td>
                    <td>${l.tenant_name || '-'}</td>
                    <td>${l.action}</td>
                    <td>${l.entity_type}</td>
                    <td><small>IP: ${l.ip_address || '-'}</small></td>
                    <td><small>${l.metadata}</small></td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhum log encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar logs.</td></tr>'; }
    }

    async function initMasterSettings() {
        const form = document.getElementById('was-master-settings-form');
        const btn = document.getElementById('was-btn-save-master-settings');
        if (!form || !btn) return;

        try {
            const data = await wasApiFetch('/admin/settings');
            if (data) {
                document.getElementById('master_graph_version').value = data.master_graph_version || 'v25.0';
                document.getElementById('master_msg_rate_limit').value = data.master_msg_rate_limit || 60;
                document.getElementById('master_log_retention').value = data.master_log_retention || 90;
            }
        } catch (err) { console.error('Error loading master settings:', err); }

        btn.addEventListener('click', async () => {
            const payload = {
                master_graph_version: document.getElementById('master_graph_version').value,
                master_msg_rate_limit: document.getElementById('master_msg_rate_limit').value,
                master_log_retention: document.getElementById('master_log_retention').value
            };
            try {
                await wasApiFetch('/admin/settings', 'POST', payload);
                alert('Configurações master salvas com sucesso!');
            } catch (err) { alert(err.message); }
        });
    }

    async function initMasterReview() {
        const list = document.getElementById('master-review-checklist');
        if (!list) return;
        try {
            const data = await wasApiFetch('/admin/app-review/checklist');
            list.innerHTML = (data || []).map(i => `
                <li>
                    <span>${i.label}</span>
                    <span class="status-indicator status-${i.status === 'done' ? 'done' : 'pending'}">
                        ${i.status.toUpperCase()}
                    </span>
                </li>
            `).join('');
        } catch (err) { list.innerHTML = 'Erro ao carregar checklist.'; }
    }

    async function initMasterTokens() {
        const tb = document.getElementById('master-tokens-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/tokens');
            tb.innerHTML = (data || []).map(k => `
                <tr>
                    <td><strong>${k.tenant_name}</strong></td>
                    <td><code>${k.prefix}</code></td>
                    <td>${k.length} chars</td>
                    <td><span class="was-status-badge" style="background: ${k.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${k.status === 'active' ? '#166534' : '#991b1b'};">
                        ${k.status.toUpperCase()}
                    </span></td>
                    <td>${k.expires_at || 'NEVER'}</td>
                    <td><span style="color: var(--danger); font-size: 0.8rem;">${k.last_error || '-'}</span></td>
                    <td>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Testar Token</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhum token encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar tokens.</td></tr>'; }
    }

    async function initMasterWebhooks() {
        const tb = document.getElementById('master-webhooks-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/webhooks');
            tb.innerHTML = (data || []).map(w => `
                <tr>
                    <td>${w.received_at}</td>
                    <td><strong>${w.tenant_name || 'Sistema'}</strong></td>
                    <td><code>${w.event_type || 'UNKNOWN'}</code></td>
                    <td>${w.signature_valid ? '✅ Válida' : '❌ Inválida'}</td>
                    <td><span class="was-status-badge" style="background: ${w.processing_status === 'processed' ? '#dcfce7' : '#fee2e2'}; color: ${w.processing_status === 'processed' ? '#166534' : '#991b1b'};">
                        ${w.processing_status.toUpperCase()}
                    </span></td>
                    <td>
                        <button class="button" onclick="alert('Ver payload bruto: ${btoa(w.payload || '')}')">Payload</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhum evento recebido.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar webhooks.</td></tr>'; }
    }

    async function initMasterTemplates() {
        const tb = document.getElementById('master-templates-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/templates');
            tb.innerHTML = (data || []).map(m => `
                <tr>
                    <td><strong>${m.tenant_name}</strong></td>
                    <td>${m.name}<br><small>Meta ID: ${m.meta_template_id || '-'}</small></td>
                    <td>${m.category}</td>
                    <td>${m.language}</td>
                    <td><span class="was-status-badge" style="background: ${m.status === 'APPROVED' ? '#dcfce7' : '#fee2e2'}; color: ${m.status === 'APPROVED' ? '#166534' : '#991b1b'};">
                        ${m.status.toUpperCase()}
                    </span></td>
                    <td>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Ver Payload</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhum template encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar templates.</td></tr>'; }
    }

    async function initMasterOnboardings() {
        const tb = document.getElementById('master-onboardings-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/onboardings');
            tb.innerHTML = (data || []).map(s => `
                <tr>
                    <td><strong>${s.tenant_name}</strong><br><small>Por: ${s.user_login}</small></td>
                    <td><span class="was-status-badge" style="background: #e2e8f0; color: #475569;">
                        ${s.status.toUpperCase()}
                    </span></td>
                    <td>
                        <small>WABA: ${s.waba_id || '-'}</small><br>
                        <small>Phone: ${s.phone_number_id || '-'}</small>
                    </td>
                    <td><span style="color: var(--danger); font-size: 0.8rem;">${s.error_message || '-'}</span></td>
                    <td>${s.started_at}</td>
                    <td>
                        <button class="button" onclick="alert('Ver payload bruto: ${btoa(s.raw_session_payload || '')}')">Logs</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhuma sessão encontrada.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar onboardings.</td></tr>'; }
    }

    async function initMasterPhones() {
        const tb = document.getElementById('master-phones-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/phone-numbers');
            tb.innerHTML = (data || []).map(p => `
                <tr>
                    <td><strong>${p.tenant_name}</strong><br><small>ID: ${p.tenant_id}</small></td>
                    <td><code>${p.phone_number_id}</code></td>
                    <td>${p.display_phone_number || '-'}</td>
                    <td>${p.verified_name || '-'}</td>
                    <td><span class="was-status-badge" style="background: ${p.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${p.status === 'active' ? '#166534' : '#991b1b'};">
                        ${p.status.toUpperCase()}
                    </span></td>
                    <td>${p.quality_rating || 'UNKNOWN'}</td>
                    <td>${p.is_default ? '✅' : '-'}</td>
                    <td>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Testar Envio</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="8">Nenhum número encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="8">Erro ao carregar números.</td></tr>'; }
    }

    async function initMasterWabas() {
        const tb = document.getElementById('master-wabas-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/wabas');
            tb.innerHTML = (data || []).map(w => `
                <tr>
                    <td><strong>${w.tenant_name}</strong><br><small>ID: ${w.tenant_id}</small></td>
                    <td><code>${w.waba_id}</code></td>
                    <td>${w.name || '-'}</td>
                    <td><span class="was-status-badge" style="background: ${w.status === 'connected' ? '#dcfce7' : '#fee2e2'}; color: ${w.status === 'connected' ? '#166534' : '#991b1b'};">
                        ${w.status.toUpperCase()}
                    </span></td>
                    <td>${w.webhook_subscription_status || 'UNKNOWN'}</td>
                    <td>${w.created_at}</td>
                    <td>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Sync Templates</button>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Inscrever Webhook</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhuma WABA encontrada.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar WABAs.</td></tr>'; }
    }

    async function initMasterApps() {
        const tb = document.getElementById('master-apps-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/meta-apps');
            tb.innerHTML = (data || []).map(a => `
                <tr>
                    <td><strong>${a.name}</strong></td>
                    <td><code>${a.app_id}</code></td>
                    <td>${a.graph_version}</td>
                    <td>${a.config_id || '-'}</td>
                    <td>${a.environment.toUpperCase()}</td>
                    <td><span class="was-status-badge" style="background: ${a.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${a.status === 'active' ? '#166534' : '#991b1b'};">
                        ${a.status.toUpperCase()}
                    </span></td>
                    <td>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Editar</button>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Testar</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhum app cadastrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar apps.</td></tr>'; }
    }

    async function initMasterTenants() {
        const tb = document.getElementById('master-tenants-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/tenants');
            tb.innerHTML = (data || []).map(t => `
                <tr>
                    <td><strong>${t.name}</strong><br><small>ID: ${t.id}</small></td>
                    <td><code>${t.slug}</code></td>
                    <td>${t.plan.toUpperCase()}</td>
                    <td><span class="was-status-badge" style="background: ${t.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${t.status === 'active' ? '#166534' : '#991b1b'};">
                        ${t.status.toUpperCase()}
                    </span></td>
                    <td>${t.created_at}</td>
                    <td>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Editar</button>
                        <button class="button" onclick="alert('Funcionalidade em desenvolvimento')">Ver WABAs</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhum cliente encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar clientes.</td></tr>'; }
    }

    async function initMasterDashboard() {
        try {
            const data = await wasApiFetch('/admin/overview');
            const mapping = {
                'master-stat-tenants': data.tenants,
                'master-stat-wabas': data.wabas,
                'master-stat-phones': data.phones,
                'master-stat-templates': data.templates,
                'master-stat-webhooks': data.webhooks_today,
                'master-stat-onboarding-failures': data.onboarding_failures
            };
            for (const [id, value] of Object.entries(mapping)) {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? 0;
            }

            // Load Master Apps
            const appsData = await wasApiFetch('/admin/meta-apps');
            const appsList = document.getElementById('master-active-apps-list');
            if (appsList && appsData) {
                appsList.innerHTML = appsData.map(app => `
                    <div style="padding: 10px; border-bottom: 1px solid #eee;">
                        <strong>${app.name}</strong><br>
                        <small>ID: ${app.app_id} | ${app.environment}</small><br>
                        <span class="was-status-badge" style="background: ${app.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${app.status === 'active' ? '#166534' : '#991b1b'}; font-size: 0.7rem; padding: 2px 6px;">
                            ${app.status.toUpperCase()}
                        </span>
                    </div>
                `).join('') || '<p>Nenhum app cadastrado.</p>';
            }

            // Mocked Alerts for NOC feel
            const alertsList = document.getElementById('master-alerts-list');
            if (alertsList) {
                alertsList.innerHTML = `
                    <tr>
                        <td><span style="color: var(--danger);">CRÍTICO</span></td>
                        <td>Webhooks</td>
                        <td>Nenhum evento recebido nas últimas 2h.</td>
                        <td><button class="button">Verificar</button></td>
                    </tr>
                    <tr>
                        <td><span style="color: var(--warning);">AVISO</span></td>
                        <td>Templates</td>
                        <td>3 templates rejeitados recentemente.</td>
                        <td><button class="button">Ver Logs</button></td>
                    </tr>
                `;
            }

        } catch (err) { console.error('Master Dashboard Error:', err); }
    }

    async function initDashboard() {
        try {
            const data = await wasApiFetch('/dashboard');
            const mapping = {
                'stat-wa-accounts': data.whatsapp_accounts,
                'stat-active-numbers': data.active_numbers,
                'stat-messages-today': data.messages_today,
                'stat-open-conversations': data.open_conversations,
                'stat-templates': data.templates
            };
            for (const [id, value] of Object.entries(mapping)) {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? 0;
            }
        } catch (err) { console.error('Dashboard Error:', err); }
    }

    /**
     * Inbox
     */
    if (document.getElementById('was-conversations-list')) {
        initInbox();
    }

    function initInbox() {
        const btnRefresh = document.getElementById('was-refresh-conversations');
        const sendBtn = document.getElementById('was-send-message');
        const inputField = document.getElementById('was-message-input');
        const openTplBtn = document.getElementById('was-open-templates-inbox');

        if (btnRefresh) btnRefresh.addEventListener('click', fetchConversations);
        if (inputField && sendBtn) {
            inputField.addEventListener('input', () => sendBtn.disabled = inputField.value.trim() === '');
            inputField.addEventListener('keypress', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
            sendBtn.addEventListener('click', sendMessage);
        }
        if (openTplBtn) openTplBtn.addEventListener('click', () => { if (!currentConversationId) return alert('Selecione uma conversa.'); openInboxTplModal(); });
        
        const closeTplBtn = document.getElementById('was-close-inbox-tpl-modal');
        if (closeTplBtn) closeTplBtn.addEventListener('click', () => {
            const modal = document.getElementById('was-inbox-tpl-modal');
            if (modal) modal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            const modal = document.getElementById('was-inbox-tpl-modal');
            if (e.target === modal) modal.style.display = 'none';
        });

        initAttachmentLogic();
        fetchConversations();
    }

    function initAttachmentLogic() {
        const attachBtn = document.getElementById('was-attach-media');
        const menu = document.getElementById('was-attachment-menu');
        const fileInput = document.getElementById('was-media-input');

        if (!attachBtn || !menu || !fileInput) return;

        attachBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        });

        document.addEventListener('click', () => menu.style.display = 'none');

        document.querySelectorAll('.was-attach-item').forEach(item => {
            item.addEventListener('click', () => {
                const type = item.dataset.type;
                fileInput.dataset.targetType = type;
                
                // Ajustar accept do input
                if (type === 'image') fileInput.accept = 'image/*';
                else if (type === 'audio') fileInput.accept = 'audio/*';
                else if (type === 'video') fileInput.accept = 'video/*';
                else fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt';

                fileInput.click();
            });
        });

        fileInput.addEventListener('change', async (e) => {
            if (!fileInput.files.length || !currentConversationId) return;
            
            const file = fileInput.files[0];
            const mediaType = fileInput.dataset.targetType;
            let caption = '';

            if (mediaType !== 'audio') {
                caption = prompt('Adicionar uma legenda? (Opcional)', '');
            }

            try {
                // Feedback visual temporário
                const history = document.getElementById('was-messages-history');
                const tempId = 'temp-' + Date.now();
                const tempDiv = document.createElement('div');
                tempDiv.id = tempId;
                tempDiv.className = 'was-message was-message-out';
                tempDiv.innerHTML = `<div class="was-message-content"><div class="was-loading-media">Enviando ${mediaType}...</div></div>`;
                history.appendChild(tempDiv);
                scrollToBottom();

                const formData = new FormData();
                formData.append('file', file);
                if (caption) formData.append('caption', caption);

                const res = await fetch(`${wasConfig.restUrl}/conversations/${currentConversationId}/messages/${mediaType}`, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': wasConfig.nonce },
                    body: formData
                });

                const data = await res.json();
                tempDiv.remove();

                if (data.success) {
                    // Recarregar conversa para pegar a URL real ou renderizar manual se tivermos a URL
                    // Como não temos a URL local fácil aqui (o blob), vamos recarregar o histórico ou confiar no renderMessage
                    // Para melhor UX, vamos forçar um reload rápido da conversa
                    loadConversation(currentConversationId, document.getElementById('was-chat-contact-name').textContent);
                } else {
                    alert(data.message || 'Erro ao enviar arquivo.');
                }

            } catch (err) {
                alert('Erro na conexão ao enviar arquivo.');
                console.error(err);
            } finally {
                fileInput.value = '';
            }
        });
    }

    async function fetchConversations() {
        const listContainer = document.getElementById('was-conversations-list');
        if (!listContainer) return;
        listContainer.innerHTML = '<div class="was-loading-state">Carregando...</div>';
        try {
            const response = await wasApiFetch('/conversations');
            const conversations = response.data || [];
            listContainer.innerHTML = '';
            if (conversations.length === 0) { listContainer.innerHTML = '<div class="was-empty-state">Vazio</div>'; return; }
            conversations.forEach(conv => {
                const item = document.createElement('div');
                item.className = 'was-conversation-item';
                const name = conv.profile_name || conv.wa_id || 'Contato';
                item.innerHTML = `<div class="was-conv-avatar">👤</div><div class="was-conv-details"><strong>${name}</strong><span>${conv.wa_id}</span></div>`;
                item.addEventListener('click', () => loadConversation(conv.id, name));
                listContainer.appendChild(item);
            });
        } catch (err) { listContainer.innerHTML = `<div class="was-error-state">${err.message}</div>`; }
    }

    async function loadConversation(id, contactName) {
        currentConversationId = id;
        document.getElementById('was-no-conversation-selected').style.display = 'none';
        document.getElementById('was-active-chat').style.display = 'flex';
        document.getElementById('was-chat-contact-name').textContent = contactName;
        const historyContainer = document.getElementById('was-messages-history');
        historyContainer.innerHTML = '<div class="was-loading-state">...</div>';
        try {
            const response = await wasApiFetch(`/conversations/${id}`);
            const messages = response.data?.messages || [];
            historyContainer.innerHTML = '';
            messages.forEach(msg => {
                const text = msg.text_body || msg.body || '';
                const payload = (msg.message_type === 'template' && msg.raw_payload) ? JSON.parse(msg.raw_payload) : null;
                renderMessage(text, msg.direction, msg.message_type, payload, msg.media_url, msg.media_filename, msg.created_at);
            });
            scrollToBottom();
        } catch (err) { historyContainer.innerHTML = 'Erro ao carregar histórico.'; }
    }

    async function sendMessage() {
        if (!currentConversationId) return;
        const inputField = document.getElementById('was-message-input');
        const sendBtn = document.getElementById('was-send-message');
        const body = inputField.value.trim();
        if (!body) return;
        inputField.disabled = true; sendBtn.disabled = true;
        try {
            const res = await wasApiFetch(`/conversations/${currentConversationId}/messages/text`, 'POST', { text: body });
            if (res.success) {
                renderMessage(body, 'outbound', 'text', null, null, null, new Date().toISOString());
                inputField.value = '';
                scrollToBottom();
            } else {
                alert(res.message || 'Erro ao enviar.');
            }
        } catch (err) { alert('Erro ao enviar.'); }
        finally { inputField.disabled = false; sendBtn.disabled = false; inputField.focus(); }
    }

    function renderMessage(text, direction, type = 'text', payload = null, mediaUrl = null, mediaFilename = null, timestamp = null) {
        const history = document.getElementById('was-messages-history');
        if (!history) return;

        // Formatar hora
        let timeStr = '';
        if (timestamp) {
            const date = new Date(timestamp);
            timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else {
            timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = `was-message ${direction === 'outbound' || direction === 'sent' ? 'was-message-out' : 'was-message-in'}`;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'was-message-content';
        
        if (type === 'template') {
            let header = '';
            let footer = '';
            let body = text;
            let buttons = [];

            // Tenta extrair dados do snapshot amigável
            try {
                if (payload) {
                    header = payload.header || '';
                    footer = payload.footer || '';
                    body = payload.body || text;
                    buttons = payload.buttons || [];
                }
            } catch(e) {}

            let buttonsHtml = '';
            if (buttons && buttons.length > 0) {
                buttonsHtml = `<div class="was-tpl-btns">
                    ${buttons.map(btn => `<div class="was-tpl-btn-item">${btn.text}</div>`).join('')}
                </div>`;
            }

            contentDiv.innerHTML = `<div class="was-template-card">
                ${header ? `<div class="was-tpl-header">${header}</div>` : ''}
                <div class="was-tpl-body">${body}</div>
                ${footer ? `<div class="was-tpl-footer">${footer}</div>` : ''}
                ${buttonsHtml}
            </div>`;
        } else if (type === 'image' && mediaUrl) {
            contentDiv.innerHTML = `<img src="${mediaUrl}" alt="Imagem" loading="lazy">
                                    ${text ? `<div style="padding-top:4px;">${text}</div>` : ''}`;
        } else if (type === 'audio' && mediaUrl) {
            contentDiv.innerHTML = `<audio controls src="${mediaUrl}"></audio>`;
        } else if (type === 'video' && mediaUrl) {
            contentDiv.innerHTML = `<video controls src="${mediaUrl}"></video>
                                    ${text ? `<div style="padding-top:4px;">${text}</div>` : ''}`;
        } else if (type === 'document' && mediaUrl) {
            contentDiv.innerHTML = `<a href="${mediaUrl}" target="_blank" class="was-doc-card">
                                        <span class="dashicons dashicons-media-document"></span>
                                        <div class="was-doc-info">
                                            <span class="was-doc-name">${mediaFilename || 'Documento'}</span>
                                        </div>
                                    </a>
                                    ${text ? `<div style="padding-top:4px;">${text}</div>` : ''}`;
        } else {
            contentDiv.textContent = text;
        }

        const timeSpan = document.createElement('span');
        timeSpan.className = 'was-message-time';
        timeSpan.textContent = timeStr;
        contentDiv.appendChild(timeSpan);

        msgDiv.appendChild(contentDiv);
        history.appendChild(msgDiv);
    }

    function scrollToBottom() {
        const el = document.getElementById('was-messages-history');
        if (el) el.scrollTop = el.scrollHeight;
    }

    /**
     * Templates & Wizard
     */
    if (document.getElementById('was-open-create-wizard')) {
        initTemplates();
    }

    function initTemplates() {
        const openBtn = document.getElementById('was-open-create-wizard');
        const wizardForm = document.getElementById('was-complex-template-form');
        const syncBtn = document.getElementById('sync-templates');

        if (openBtn) openBtn.addEventListener('click', () => { 
            editingTemplateId = null; 
            wizardForm?.reset(); 
            wizardButtons = []; 
            wizardVariables = {};
            const nameInput = document.getElementById('wiz-tpl-name');
            if (nameInput) nameInput.disabled = false;
            document.getElementById('was-template-wizard').style.display = 'block'; 
            document.querySelector('#was-template-wizard h2').textContent = 'Criar Modelo';
            setWizardStep(1); 
            renderWizardButtons(); 
            renderVariablesList();
            updatePreview();
        });

        document.getElementById('wiz-next')?.addEventListener('click', () => setWizardStep(wizardStep + 1));
        document.getElementById('wiz-prev')?.addEventListener('click', () => setWizardStep(wizardStep - 1));
        document.getElementById('wiz-cancel')?.addEventListener('click', () => document.getElementById('was-template-wizard').style.display = 'none');
        
        // Add Button Listeners
        document.querySelectorAll('.wiz-add-btn-type').forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.type;
                let newBtn = { type, text: 'Botão' };
                if (type === 'URL') newBtn.url = 'https://';
                if (type === 'PHONE_NUMBER') newBtn.phone_number = '+';
                if (type === 'COPY_CODE') newBtn.example = 'CUPOM20';
                
                wizardButtons.push(newBtn);
                renderWizardButtons();
                updatePreview();
            });
        });

        // Name Normalization
        document.getElementById('wiz-tpl-name')?.addEventListener('input', (e) => {
            if (editingTemplateId) return;
            const original = e.target.value;
            const normalized = original.toLowerCase()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Remove acentos
                .replace(/[^a-z0-9_]/g, '_') // Apenas minúsculas, números e underline
                .replace(/_+/g, '_'); // Evita múltiplos underscores
            
            if (original !== normalized) e.target.value = normalized;
            updatePreview();
        });

        ['wiz-body-text', 'wiz-header-text', 'wiz-footer-text', 'wiz-tpl-lang'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => {
                if (id === 'wiz-body-text') syncVariablesFromText();
                updatePreview();
            });
        });

        // Toggle Header Input
        document.getElementById('wiz-header-type')?.addEventListener('change', (e) => {
            const container = document.getElementById('wiz-header-text-container');
            if (container) {
                container.style.display = e.target.value === 'TEXT' ? 'block' : 'none';
            }
            updatePreview();
        });

        // Insert Variable Logic
        document.getElementById('wiz-add-var')?.addEventListener('click', () => {
            const textarea = document.getElementById('wiz-body-text');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const varName = prompt('Nome da variável (ex: nome, pedido):', 'var');
            if (!varName) return;

            const newText = text.substring(0, start) + `{{${varName}}}` + text.substring(end);
            textarea.value = newText;
            
            syncVariablesFromText();
            textarea.focus();
            updatePreview();
            renderVariablesList();
        });

        if (wizardForm) wizardForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('wiz-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';

            const payload = {
                name: document.getElementById('wiz-tpl-name').value,
                category: wizardForm.querySelector('input[name="category"]:checked').value,
                language: document.getElementById('wiz-tpl-lang').value,
                header: { 
                    type: document.getElementById('wiz-header-type').value, 
                    text: document.getElementById('wiz-header-text').value 
                },
                body: { 
                    text: document.getElementById('wiz-body-text').value,
                    variables: Object.entries(wizardVariables).map(([k, v]) => ({ key: k, example: v }))
                },
                footer: { text: document.getElementById('wiz-footer-text').value },
                buttons: wizardButtons
            };

            const method = editingTemplateId ? 'PUT' : 'POST';
            const endpoint = editingTemplateId ? `/templates/${editingTemplateId}` : '/templates';

            try {
                const res = await wasApiFetch(endpoint, method, payload);
                if (res.success) {
                    alert(editingTemplateId ? 'Template atualizado!' : 'Template enviado para aprovação!');
                    location.reload();
                }
            } catch (err) { 
                alert(err.message || 'Erro ao salvar template'); 
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar para Aprovação';
            }
        });

        if (syncBtn) syncBtn.addEventListener('click', async () => {
            syncBtn.disabled = true;
            syncBtn.textContent = 'Sincronizando...';
            try { 
                await wasApiFetch('/templates/sync', 'POST'); 
                alert('Sincronização concluída com sucesso!');
                fetchTemplates(); 
            } catch (err) {
                alert(err.message || 'Erro ao sincronizar. Verifique se o Token e o WABA ID são válidos.');
            } finally { 
                syncBtn.disabled = false; 
                syncBtn.textContent = 'Sincronizar com Meta';
            }
        });

        fetchTemplates();
    }

    function syncVariablesFromText() {
        const text = document.getElementById('wiz-body-text').value;
        const matches = text.match(/{{\s*([a-zA-Z0-9_]+)\s*}}/g) || [];
        const currentVars = matches.map(m => m.replace(/{{\s*|\s*}}/g, ''));
        
        // Remove variáveis que não existem mais
        Object.keys(wizardVariables).forEach(k => {
            if (!currentVars.includes(k)) delete wizardVariables[k];
        });
        
        // Adiciona novas
        currentVars.forEach(v => {
            if (!wizardVariables[v]) wizardVariables[v] = 'Exemplo';
        });
    }

    async function fetchTemplates() {
        const tbody = document.getElementById('template-list-body');
        if (!tbody) return;
        try {
            const data = await wasApiFetch('/templates');
            tbody.innerHTML = (data || []).map(t => {
                let color = '#555';
                const st = (t.status || 'DRAFT').toUpperCase();
                if (st === 'APPROVED') color = '#25d366';
                else if (st === 'REJECTED') color = '#dc2626';
                else if (st === 'PENDING' || st === 'IN_REVIEW') color = '#f59e0b';
                else if (st === 'DELETED') color = '#999';

                return `<tr>
                    <td><strong>${t.name}</strong><br><small style="color:#888">${t.waba_id}</small></td>
                    <td>${t.category}</td>
                    <td>${t.language}</td>
                    <td><span style="font-weight:600; color:${color}; border:1px solid ${color}33; padding:2px 6px; border-radius:4px; background:${color}11;">${st}</span></td>
                    <td style="display:flex; gap:5px; flex-wrap:wrap;">
                        <button type="button" class="button was-btn-send-tpl" data-id="${t.id}" data-name="${t.name}">Enviar</button>
                        <button type="button" class="button was-btn-dup-tpl" data-id="${t.id}">Duplicar</button>
                        <button type="button" class="button was-btn-edit-tpl" data-id="${t.id}">Editar</button>
                        <button type="button" class="button button-link-delete was-btn-del-tpl" data-id="${t.id}">Excluir</button>
                    </td>
                </tr>`;
            }).join('') || '<tr><td colspan="5">Vazio</td></tr>';
            
            document.querySelectorAll('.was-btn-send-tpl').forEach(btn => btn.addEventListener('click', () => openSendModal(btn.dataset.id, btn.dataset.name)));
            
            document.querySelectorAll('.was-btn-del-tpl').forEach(btn => btn.addEventListener('click', async () => {
                if(confirm('Tem certeza que deseja excluir permanentemente este template?')) {
                    try {
                        btn.disabled = true;
                        btn.textContent = '...';
                        await wasApiFetch(`/templates/${btn.dataset.id}`, 'DELETE');
                        fetchTemplates();
                    } catch(err) {
                        alert(err.message || 'Erro ao excluir');
                        btn.disabled = false;
                        btn.textContent = 'Excluir';
                    }
                }
            }));

            document.querySelectorAll('.was-btn-dup-tpl').forEach(btn => btn.addEventListener('click', async () => {
                const newName = prompt('Digite um nome para a cópia (apenas minúsculas e _):');
                if (newName) {
                    try {
                        btn.disabled = true;
                        btn.textContent = '...';
                        await wasApiFetch(`/templates/${btn.dataset.id}/duplicate`, 'POST', { new_name: newName });
                        alert('Template duplicado!');
                        fetchTemplates();
                    } catch(err) {
                        alert(err.message || 'Erro ao duplicar');
                        btn.disabled = false;
                        btn.textContent = 'Duplicar';
                    }
                }
            }));

            document.querySelectorAll('.was-btn-edit-tpl').forEach(btn => btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    btn.disabled = true;
                    btn.textContent = '...';
                    const template = await wasApiFetch(`/templates/${id}`);
                    populateWizard(template);
                } catch (err) {
                    alert('Erro ao carregar template para edição');
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Editar';
                }
            }));

        } catch (err) { 
            tbody.innerHTML = '<tr><td colspan="5">Erro ao carregar templates.</td></tr>'; 
        }
    }

    function setWizardStep(step) {
        wizardStep = step;
        document.querySelectorAll('.was-wizard-step-content').forEach(el => el.style.display = 'none');
        document.getElementById(`step-${step}`).style.display = 'block';
        document.querySelectorAll('.step-item').forEach(el => el.classList.toggle('active', parseInt(el.dataset.step) === step));
        const bP = document.getElementById('wiz-prev'), bN = document.getElementById('wiz-next'), bS = document.getElementById('wiz-submit');
        if (bP) bP.style.display = step > 1 ? 'inline-block' : 'none';
        if (bN) bN.style.display = step < 5 ? 'inline-block' : 'none';
        if (bS) bS.style.display = step === 5 ? 'inline-block' : 'none';
        
        if (step === 5) {
            renderVariablesList();
            renderChecklist();
        }
    }

    function updatePreview() {
        const hType = document.getElementById('wiz-header-type')?.value || 'NONE';
        const hText = document.getElementById('wiz-header-text')?.value || '';
        const b = document.getElementById('wiz-body-text')?.value || '';
        const f = document.getElementById('wiz-footer-text')?.value || '';
        
        const ph = document.getElementById('pre-header');
        const pb = document.getElementById('pre-body');
        const pf = document.getElementById('pre-footer');
        const pbtns = document.getElementById('pre-buttons');

        if (ph) {
            ph.textContent = hText;
            ph.style.display = hType === 'TEXT' && hText ? 'block' : 'none';
        }

        if (pb) {
            let processedBody = b;
            Object.entries(wizardVariables).forEach(([k, v]) => {
                // Escape para usar no RegExp
                const safeKey = k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                processedBody = processedBody.replace(new RegExp(`{{\\s*${safeKey}\\s*}}`, 'g'), `<span style="color:#2563eb; font-weight:bold;">${v}</span>`);
            });
            pb.innerHTML = processedBody || 'Sua mensagem aparecerá aqui...';
        }

        if (pf) {
            pf.textContent = f;
            pf.style.display = f ? 'block' : 'none';
        }

        if (pbtns) {
            pbtns.innerHTML = wizardButtons.map(btn => `<div class="was-wa-btn-item">${btn.text}</div>`).join('');
            pbtns.style.display = wizardButtons.length > 0 ? 'block' : 'none';
        }
    }

    function populateWizard(t) {
        editingTemplateId = t.id;
        document.getElementById('was-template-wizard').style.display = 'block';
        const title = document.querySelector('#was-template-wizard h2');
        const nameInput = document.getElementById('wiz-tpl-name');
        if (title) title.textContent = 'Editar Modelo';
        if (nameInput) { nameInput.value = t.name || ''; nameInput.disabled = true; }
        document.getElementById('wiz-tpl-lang').value = t.language || 'pt_BR';
        const catInput = document.querySelector(`input[name="category"][value="${t.category}"]`);
        if (catInput) catInput.checked = true;

        let payload = {};
        try { payload = t.friendly_payload ? JSON.parse(t.friendly_payload) : {}; } catch(e) {}

        const hType = payload.header?.type || (t.header_type === 'TEXT' ? 'TEXT' : 'NONE');
        const hText = payload.header?.text || '';
        document.getElementById('wiz-header-type').value = hType;
        document.getElementById('wiz-header-text').value = hText;
        document.getElementById('wiz-header-text-container').style.display = hType === 'TEXT' ? 'block' : 'none';
        document.getElementById('wiz-body-text').value = payload.body?.text || t.body_text || '';
        document.getElementById('wiz-footer-text').value = payload.footer?.text || t.footer_text || '';
        wizardButtons = payload.buttons || [];
        try { if (!wizardButtons.length && t.buttons_json) wizardButtons = JSON.parse(t.buttons_json); } catch(e) {}
        
        wizardVariables = {};
        if (payload.body?.variables) {
            payload.body.variables.forEach(v => { wizardVariables[v.key] = v.example; });
        }
        syncVariablesFromText(); // Garante consistência

        setWizardStep(1);
        renderWizardButtons();
        updatePreview();
    }

    function renderVariablesList() {
        const container = document.getElementById('wiz-variables-examples');
        if (!container) return;
        const entries = Object.entries(wizardVariables);
        if (entries.length === 0) {
            container.innerHTML = '<p class="description">Nenhuma variável detectada no corpo da mensagem.</p>';
            return;
        }
        container.innerHTML = '<h4>Exemplos de Variáveis</h4><p class="description">Insira valores reais para que a Meta aprove seu modelo.</p>';
        entries.forEach(([k, v]) => {
            const row = document.createElement('div');
            row.style.marginBottom = '10px';
            row.innerHTML = `<label style="display:block; font-size:11px; font-weight:bold;">{{${k}}}</label>
                             <input type="text" value="${v}" oninput="updateVariableExample('${k}', this.value)" style="width:100%;" placeholder="Valor de exemplo">`;
            container.appendChild(row);
        });
    }

    function renderWizardButtons() {
        const container = document.getElementById('wiz-buttons-list');
        if (!container) return;
        container.innerHTML = wizardButtons.map((btn, i) => {
            let fields = `<input type="text" value="${btn.text}" oninput="updateWizardButton(${i}, 'text', this.value)" style="width:100%;" placeholder="Texto do botão">`;
            
            if (btn.type === 'URL') {
                fields += `<input type="text" value="${btn.url || ''}" oninput="updateWizardButton(${i}, 'url', this.value)" style="width:100%; margin-top:5px;" placeholder="URL (ex: https://...)">`;
                if (btn.url?.includes('{{')) {
                    fields += `<input type="text" value="${btn.example || ''}" oninput="updateWizardButton(${i}, 'example', this.value)" style="width:100%; margin-top:5px;" placeholder="Exemplo para a variável da URL">`;
                }
            } else if (btn.type === 'PHONE_NUMBER') {
                fields += `<input type="text" value="${btn.phone_number || ''}" oninput="updateWizardButton(${i}, 'phone_number', this.value)" style="width:100%; margin-top:5px;" placeholder="Telefone (ex: +55...)">`;
            } else if (btn.type === 'COPY_CODE') {
                fields += `<input type="text" value="${btn.example || ''}" oninput="updateWizardButton(${i}, 'example', this.value)" style="width:100%; margin-top:5px;" placeholder="Código de exemplo">`;
            }

            return `<div class="wiz-btn-edit-item">
                <span class="remove-btn" onclick="removeWizardButton(${i})">×</span>
                <div style="font-size:10px; font-weight:bold; margin-bottom:5px; color:#64748b;">${btn.type}</div>
                ${fields}
            </div>`;
        }).join('');
    }

    function renderChecklist() {
        const list = document.getElementById('was-checklist-items');
        if (!list) return;
        const name = document.getElementById('wiz-tpl-name')?.value || '';
        const body = document.getElementById('wiz-body-text')?.value || '';
        const hType = document.getElementById('wiz-header-type')?.value || 'NONE';
        const hText = document.getElementById('wiz-header-text')?.value || '';

        const checks = [
            { label: 'Nome válido', pass: /^[a-z0-9_]+$/.test(name) },
            { label: 'Mensagem principal preenchida', pass: body.trim().length > 0 },
            { label: 'Variáveis possuem exemplos', pass: Object.values(wizardVariables).every(v => v && v.toString().trim().length > 0) },
            { label: 'Cabeçalho válido', pass: hType === 'NONE' || hText.trim().length > 0 },
            { label: 'Botões válidos', pass: wizardButtons.every(b => {
                const textOk = (b.text || '').trim().length > 0;
                if (!textOk) return false;
                if (b.type === 'URL') return (b.url || '').startsWith('https://');
                if (b.type === 'PHONE_NUMBER') return (b.phone_number || '').startsWith('+');
                return true;
            }) }
        ];

        list.innerHTML = checks.map(c => `<li style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
            <span style="color:${c.pass ? '#25d366' : '#ef4444'}; font-weight:bold;">${c.pass ? '✅' : '❌'}</span>
            <span style="color:${c.pass ? '#1e293b' : '#ef4444'}">${c.label}</span>
        </li>`).join('');
        
        const submitBtn = document.getElementById('wiz-submit');
        if (submitBtn) submitBtn.disabled = !checks.every(c => c.pass);
    }

    // --- Modal de Envio de Template ---
    window.openSendModal = async function(id, name) {
        const modal = document.getElementById('was-send-template-modal');
        if (!modal) return;
        document.getElementById('send-tpl-id').value = id;
        document.getElementById('send-tpl-name-display').textContent = name;
        try {
            const tpl = await wasApiFetch(`/templates/${id}`);
            const varMap = tpl.variable_map ? JSON.parse(tpl.variable_map) : {};
            const inputsContainer = document.getElementById('was-tpl-variables-inputs');
            inputsContainer.innerHTML = '';
            const varKeys = Object.keys(varMap);
            if (varKeys.length === 0) {
                inputsContainer.innerHTML = '<p class="description">Este modelo não possui variáveis no corpo da mensagem.</p>';
            } else {
                varKeys.forEach(k => {
                    inputsContainer.innerHTML += `<div style="margin-bottom:10px;"><label><strong>Variável {{${k}}}</strong> (Mapeada como: ${varMap[k]})</label><input type="text" class="tpl-var-input regular-text" data-key="${k}" style="width:100%; margin-top:4px;" required></div>`;
                });
            }
            modal.style.display = 'block';
        } catch (err) { alert('Erro ao carregar detalhes do template para envio.'); }
    };

    const closeSendModal = document.getElementById('was-close-send-modal');
    if (closeSendModal) closeSendModal.addEventListener('click', () => { document.getElementById('was-send-template-modal').style.display = 'none'; });

    const sendTplForm = document.getElementById('was-send-template-form');
    if (sendTplForm) {
        sendTplForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('send-tpl-id').value;
            const to = document.getElementById('send-tpl-to').value;
            const varInputs = document.querySelectorAll('.tpl-var-input');
            const variables = Array.from(varInputs).reduce((acc, inp) => { acc[inp.dataset.key] = inp.value; return acc; }, {});
            const submitBtn = sendTplForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Enviando...'; submitBtn.disabled = true;
            try {
                const res = await wasApiFetch(`/templates/${id}/send`, 'POST', { to_phone: to, variables: variables });
                if (res.success) {
                    alert('Template enviado com sucesso!');
                    document.getElementById('was-send-template-modal').style.display = 'none';
                    
                    // Se a conversa aberta for a mesma que recebeu o template, renderiza na tela
                    if (res.conversation_id && currentConversationId == res.conversation_id) {
                        const snapshot = {
                            header: res.rendered_header,
                            body: res.rendered_body,
                            footer: res.rendered_footer,
                            buttons: res.buttons
                        };
                        renderMessage(res.rendered_body || 'Modelo enviado', 'outbound', 'template', snapshot);
                        scrollToBottom();
                    } else if (!currentConversationId) {
                        // Se não tem conversa aberta, talvez queira atualizar a lista lateral
                        fetchConversations();
                    }
                }
            } catch(err) { alert(err.message || 'Erro ao enviar template'); }
            finally { submitBtn.textContent = originalText; submitBtn.disabled = false; }
        });
    }

    /**
     * Settings & Logs
     */
    if (document.getElementById('log-list-body')) initLogs();
    if (document.getElementById('was-meta-config-form')) initSettingsMeta();

    async function initLogs() {
        const tb = document.getElementById('log-list-body');
        if (!tb) return;
        try {
            const logs = await wasApiFetch('/audit-logs');
            tb.innerHTML = logs.map(l => `<tr><td>${l.created_at}</td><td>${l.action}</td><td>${l.entity_type}</td><td><small>${l.metadata}</small></td></tr>`).join('') || '<tr><td colspan="4">Vazio</td></tr>';
        } catch (err) { tb.innerHTML = 'Erro.'; }
    }

    async function initSettingsMeta() {
        const form = document.getElementById('was-meta-config-form');
        if (!form) return;
        try {
            const data = await wasApiFetch('/meta/config');
            if (data) {
                document.getElementById('app_id').value = data.app_id || '';
                document.getElementById('app_secret').value = data.app_secret || '';
                document.getElementById('primary_phone_number_id').value = data.primary_phone_number_id || '';
                document.getElementById('meta_access_token').value = data.meta_access_token || '';
                document.getElementById('waba_id').value = data.waba_id || '';
                document.getElementById('verify_token').value = data.verify_token || '';
                document.getElementById('webhook_url').value = data.webhook_url || '';
                if (document.getElementById('config_id')) document.getElementById('config_id').value = data.config_id || '';
                if (document.getElementById('embedded_signup_url')) {
                    document.getElementById('embedded_signup_url').value = data.embedded_signup_url || '';
                    if (document.getElementById('was-start-embedded-signup')) document.getElementById('was-start-embedded-signup').href = data.embedded_signup_url || '#';
                }
            }
        } catch (err) { console.error('Error fetching meta config:', err); }

        const saveConfig = async (e) => {
            if (e) e.preventDefault();
            const payload = {
                app_id: document.getElementById('app_id').value,
                app_secret: document.getElementById('app_secret').value,
                verify_token: document.getElementById('verify_token').value,
                primary_phone_number_id: document.getElementById('primary_phone_number_id').value,
                meta_access_token: document.getElementById('meta_access_token').value,
                waba_id: document.getElementById('waba_id').value,
                config_id: document.getElementById('config_id') ? document.getElementById('config_id').value : '',
                embedded_signup_url: document.getElementById('embedded_signup_url') ? document.getElementById('embedded_signup_url').value : ''
            };
            try {
                await wasApiFetch('/meta/config', 'POST', payload);
                alert('Configurações salvas com sucesso!');
            } catch (err) { alert(err.message || 'Erro ao salvar configurações'); }
        };

        const btnSaveMeta = document.getElementById('was-btn-save-meta');
        if (btnSaveMeta) btnSaveMeta.addEventListener('click', saveConfig);
        const btnSaveEmbedded = document.getElementById('was-btn-save-embedded');
        if (btnSaveEmbedded) btnSaveEmbedded.addEventListener('click', saveConfig);
    }
    async function openInboxTplModal() {
        const modal = document.getElementById('was-inbox-tpl-modal');
        const list = document.getElementById('was-inbox-tpl-list');
        if (!modal) return;
        modal.style.display = 'block'; list.innerHTML = '...';
        try {
            const data = await wasApiFetch('/templates');
            const approved = (data || []).filter(t => t.status === 'APPROVED');
            list.innerHTML = approved.map(t => {
                const body = t.body_text || '';
                const preview = body.length > 50 ? body.substring(0, 50) + '...' : body;
                return `<div class="was-tpl-select-item" onclick="sendTemplateFromInbox(${t.id})">
                    <strong>${t.name}</strong><br>
                    <small>${preview}</small>
                </div>`;
            }).join('') || 'Nenhum template aprovado.';
        } catch (err) { list.innerHTML = 'Erro.'; }
    }

    window.sendTemplateFromInbox = async (id) => {
        if (!confirm('Enviar este template?')) return;
        try {
            const res = await wasApiFetch(`/templates/${id}/send`, 'POST', { conversation_id: currentConversationId });
            if (res.success) {
                alert('Enviado!'); 
                document.getElementById('was-inbox-tpl-modal').style.display = 'none';
                const snapshot = {
                    header: res.rendered_header,
                    body: res.rendered_body,
                    footer: res.rendered_footer,
                    buttons: res.buttons
                };
                renderMessage(res.rendered_body || 'Modelo enviado', 'outbound', 'template', snapshot);
                scrollToBottom();
            }
        } catch (err) { alert(err.message); }
    };
});
