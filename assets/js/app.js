document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.wasApp === 'undefined') {
        return;
    }

    const { restUrl, nonce, page } = window.wasApp;

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

        if (body) {
            options.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`WAS API Error [${method} ${path}]:`, error);
            throw error;
        }
    }

    /**
     * Dashboard Initialization
     */
    if (page === 'dashboard') {
        initDashboard();
    }

    async function initDashboard() {
        const dashboardEl = document.querySelector('.was-dashboard');
        if (!dashboardEl) return;

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
        } catch (err) {
            console.error('Dashboard Error:', err);
        }
    }

    /**
     * Inbox Initialization
     */
    if (page === 'inbox') {
        initInbox();
    }

    let currentConversationId = null;

    function initInbox() {
        const btnRefresh = document.getElementById('was-refresh-conversations');
        const listContainer = document.getElementById('was-conversations-list');
        const sendBtn = document.getElementById('was-send-message');
        const inputField = document.getElementById('was-message-input');
        const openTplBtn = document.getElementById('was-open-templates-inbox');
        const closeTplBtn = document.getElementById('was-close-inbox-tpl-modal');

        if (!listContainer) return;

        if (btnRefresh) btnRefresh.addEventListener('click', fetchConversations);
        
        if (inputField && sendBtn) {
            inputField.addEventListener('input', () => {
                sendBtn.disabled = inputField.value.trim() === '';
            });
            inputField.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            sendBtn.addEventListener('click', sendMessage);
        }

        if (openTplBtn) {
            openTplBtn.addEventListener('click', () => {
                if (!currentConversationId) return alert('Selecione uma conversa primeiro.');
                openInboxTplModal();
            });
        }

        if (closeTplBtn) {
            closeTplBtn.addEventListener('click', () => {
                document.getElementById('was-inbox-tpl-modal').style.display = 'none';
            });
        }

        fetchConversations();
    }

    async function fetchConversations() {
        const listContainer = document.getElementById('was-conversations-list');
        if (!listContainer) return;
        listContainer.innerHTML = '<div class="was-loading-state">Carregando...</div>';

        try {
            const response = await wasApiFetch('/conversations');
            const conversations = response.data || [];
            listContainer.innerHTML = '';
            if (conversations.length === 0) {
                listContainer.innerHTML = '<div class="was-empty-state">Nenhuma conversa.</div>';
                return;
            }
            conversations.forEach(conv => {
                const item = document.createElement('div');
                item.className = 'was-conversation-item';
                const displayName = conv.profile_name || conv.wa_id || 'Contato';
                item.innerHTML = `
                    <div class="was-conv-avatar">👤</div>
                    <div class="was-conv-details">
                        <strong>${displayName}</strong>
                        <span>Status: ${conv.status || 'aberto'}</span>
                    </div>
                `;
                item.addEventListener('click', () => loadConversation(conv.id, displayName));
                listContainer.appendChild(item);
            });
        } catch (err) {
            listContainer.innerHTML = `<div class="was-error-state">${err.message}</div>`;
        }
    }

    async function loadConversation(id, contactName) {
        currentConversationId = id;
        document.getElementById('was-no-conversation-selected').style.display = 'none';
        document.getElementById('was-active-chat').style.display = 'flex';
        document.getElementById('was-chat-contact-name').textContent = contactName || 'Contato';
        
        const historyContainer = document.getElementById('was-messages-history');
        historyContainer.innerHTML = '<div class="was-loading-state">Carregando...</div>';

        try {
            const response = await wasApiFetch(`/conversations/${id}`);
            const messages = response.data?.messages || [];
            historyContainer.innerHTML = '';
            messages.forEach(msg => {
                renderMessage(msg.body || msg.text_body || 'Conteúdo indisponível', msg.direction || msg.type, msg.type);
            });
            scrollToBottom();
        } catch (err) {
            historyContainer.innerHTML = '<div class="was-error-state">Erro ao carregar histórico.</div>';
        }
    }

    async function sendMessage() {
        if (!currentConversationId) return;
        const inputField = document.getElementById('was-message-input');
        const sendBtn = document.getElementById('was-send-message');
        const body = inputField.value.trim();
        if (!body) return;

        inputField.disabled = true;
        sendBtn.disabled = true;

        try {
            const response = await wasApiFetch(`/conversations/${currentConversationId}/messages`, 'POST', {
                type: 'text',
                body: body
            });
            if (response.success) {
                renderMessage(body, 'outbound', 'text');
                inputField.value = '';
                scrollToBottom();
            }
        } catch (err) {
            alert('Falha ao enviar.');
        } finally {
            inputField.disabled = false;
            sendBtn.disabled = false;
            inputField.focus();
        }
    }

    async function openInboxTplModal() {
        const modal = document.getElementById('was-inbox-tpl-modal');
        const list = document.getElementById('was-inbox-tpl-list');
        modal.style.display = 'block';
        list.innerHTML = '<p>Carregando...</p>';

        try {
            const templates = await wasApiFetch('/templates');
            list.innerHTML = '';
            const approved = templates.filter(t => t.status === 'APPROVED' || t.status === 'approved');
            if (approved.length === 0) {
                list.innerHTML = '<p>Nenhum modelo aprovado.</p>';
                return;
            }
            approved.forEach(t => {
                const item = document.createElement('div');
                item.className = 'was-tpl-select-item';
                item.style.padding = '10px';
                item.style.borderBottom = '1px solid #eee';
                item.style.cursor = 'pointer';
                item.innerHTML = `<strong>${t.name}</strong><br><small>${t.body_text.substring(0, 80)}...</small>`;
                item.addEventListener('click', () => sendTemplateInChat(t.id, t.name, t.body_text));
                list.appendChild(item);
            });
        } catch (err) {
            list.innerHTML = '<p>Erro ao carregar modelos.</p>';
        }
    }

    async function sendTemplateInChat(id, name, body) {
        if (!confirm(`Deseja enviar o modelo "${name}"?`)) return;
        try {
            const response = await wasApiFetch(`/templates/${id}/send`, 'POST', { 
                conversation_id: currentConversationId 
            });
            if (response.success) {
                renderMessage(body, 'outbound', 'template');
                document.getElementById('was-inbox-tpl-modal').style.display = 'none';
                scrollToBottom();
            }
        } catch (err) {
            alert('Erro: ' + err.message);
        }
    }

    function renderMessage(text, direction, type = 'text') {
        const historyContainer = document.getElementById('was-messages-history');
        if (!historyContainer) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = `was-message ${direction === 'outbound' || direction === 'sent' ? 'was-message-out' : 'was-message-in'}`;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'was-message-content';
        if (type === 'template') {
            contentDiv.innerHTML = `<div class="was-template-card"><div class="was-template-header">📄 Modelo Oficial</div><div class="was-template-body">${text}</div></div>`;
        } else {
            contentDiv.textContent = text;
        }
        msgDiv.appendChild(contentDiv);
        historyContainer.appendChild(msgDiv);
    }

    function scrollToBottom() {
        const historyContainer = document.getElementById('was-messages-history');
        if (historyContainer) historyContainer.scrollTop = historyContainer.scrollHeight;
    }

    /**
     * Templates & Wizard
     */
    if (page === 'templates') {
        initTemplates();
    }

    let wizardStep = 1;
    let wizardButtons = [];

    function initTemplates() {
        const syncBtn = document.getElementById('sync-templates');
        const openWizardBtn = document.getElementById('was-open-create-wizard');
        const cancelWizardBtn = document.getElementById('wiz-cancel');
        const wizardForm = document.getElementById('was-complex-template-form');
        const btnNext = document.getElementById('wiz-next');
        const btnPrev = document.getElementById('wiz-prev');
        const btnSubmit = document.getElementById('wiz-submit');

        if (openWizardBtn) {
            openWizardBtn.addEventListener('click', () => {
                document.getElementById('was-template-wizard').style.display = 'block';
                setWizardStep(1);
            });
        }

        if (cancelWizardBtn) {
            cancelWizardBtn.addEventListener('click', () => {
                if (confirm('Sair do construtor?')) document.getElementById('was-template-wizard').style.display = 'none';
            });
        }

        if (btnNext) btnNext.addEventListener('click', () => setWizardStep(wizardStep + 1));
        if (btnPrev) btnPrev.addEventListener('click', () => setWizardStep(wizardStep - 1));

        ['wiz-tpl-name', 'wiz-body-text', 'wiz-header-text', 'wiz-footer-text', 'wiz-header-type'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updatePreview);
        });

        const headerTypeEl = document.getElementById('wiz-header-type');
        if (headerTypeEl) {
            headerTypeEl.addEventListener('change', (e) => {
                const container = document.getElementById('wiz-header-text-container');
                if (container) container.style.display = e.target.value === 'TEXT' ? 'block' : 'none';
                updatePreview();
            });
        }

        const addVarBtn = document.getElementById('wiz-add-var');
        if (addVarBtn) {
            addVarBtn.addEventListener('click', () => {
                const body = document.getElementById('wiz-body-text');
                const varName = prompt('Nome da variável:', 'nome');
                if (varName && body) {
                    const start = body.selectionStart;
                    const text = body.value;
                    body.value = text.substring(0, start) + '{{' + varName + '}}' + text.substring(body.selectionEnd);
                    body.focus();
                    updatePreview();
                }
            });
        }

        const addBtnBtn = document.getElementById('wiz-add-button');
        if (addBtnBtn) {
            addBtnBtn.addEventListener('click', () => {
                if (wizardButtons.length >= 3) return alert('Máximo 3 botões.');
                wizardButtons.push({ type: 'QUICK_REPLY', text: 'Novo Botão' });
                renderWizardButtons();
                updatePreview();
            });
        }

        if (wizardForm) {
            wizardForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const friendlyPayload = {
                    name: document.getElementById('wiz-tpl-name').value,
                    language: document.getElementById('wiz-tpl-lang').value,
                    category: wizardForm.querySelector('input[name="category"]:checked').value,
                    header: {
                        type: document.getElementById('wiz-header-type').value,
                        text: document.getElementById('wiz-header-text').value
                    },
                    body: { text: document.getElementById('wiz-body-text').value },
                    footer: { text: document.getElementById('wiz-footer-text').value },
                    buttons: wizardButtons,
                    variables: []
                };
                try {
                    btnSubmit.disabled = true;
                    await wasApiFetch('/templates', 'POST', friendlyPayload);
                    alert('Enviado!');
                    document.getElementById('was-template-wizard').style.display = 'none';
                    wizardForm.reset();
                    wizardButtons = [];
                    fetchTemplates();
                } catch (err) { alert(err.message); }
                finally { btnSubmit.disabled = false; }
            });
        }

        if (syncBtn) {
            syncBtn.addEventListener('click', async () => {
                syncBtn.disabled = true;
                await wasApiFetch('/templates/sync', 'POST');
                fetchTemplates();
                syncBtn.disabled = false;
            });
        }

        const sendTplForm = document.getElementById('was-send-template-form');
        if (sendTplForm) {
            sendTplForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('send-tpl-id').value;
                const to = document.getElementById('send-tpl-to').value;
                try {
                    await wasApiFetch(`/templates/${id}/send`, 'POST', { to });
                    alert('Modelo enviado!');
                    document.getElementById('was-send-template-modal').style.display = 'none';
                } catch (err) { alert(err.message); }
            });
        }

        fetchTemplates();
    }

    function setWizardStep(step) {
        wizardStep = step;
        document.querySelectorAll('.was-wizard-step-content').forEach(el => el.style.display = 'none');
        const content = document.getElementById(`step-${step}`);
        if (content) content.style.display = 'block';
        document.querySelectorAll('.step-item').forEach(el => el.classList.toggle('active', parseInt(el.dataset.step) === step));
        document.getElementById('wiz-prev').style.display = step > 1 ? 'inline-block' : 'none';
        document.getElementById('wiz-next').style.display = step < 5 ? 'inline-block' : 'none';
        document.getElementById('wiz-submit').style.display = step === 5 ? 'inline-block' : 'none';
        if (step === 5) renderComplianceCheck();
    }

    function updatePreview() {
        const headerType = document.getElementById('wiz-header-type')?.value;
        const headerText = document.getElementById('wiz-header-text')?.value;
        const bodyText = document.getElementById('wiz-body-text')?.value;
        const footerText = document.getElementById('wiz-footer-text')?.value;
        const preHeader = document.getElementById('pre-header');
        const preBody = document.getElementById('pre-body');
        const preFooter = document.getElementById('pre-footer');
        const preButtons = document.getElementById('pre-buttons');

        if (preHeader) {
            if (headerType === 'TEXT' && headerText) {
                preHeader.textContent = headerText;
                preHeader.style.display = 'block';
            } else preHeader.style.display = 'none';
        }
        if (preBody) preBody.textContent = bodyText || 'Sua mensagem aqui...';
        if (preFooter) {
            if (footerText) {
                preFooter.textContent = footerText;
                preFooter.style.display = 'block';
            } else preFooter.style.display = 'none';
        }
        if (preButtons) {
            if (wizardButtons.length > 0) {
                preButtons.innerHTML = wizardButtons.map(b => `<div class="was-wa-btn-item">${b.text}</div>`).join('');
                preButtons.style.display = 'block';
            } else preButtons.style.display = 'none';
        }
    }

    function renderWizardButtons() {
        const container = document.getElementById('wiz-buttons-list');
        if (!container) return;
        container.innerHTML = '';
        wizardButtons.forEach((btn, idx) => {
            const div = document.createElement('div');
            div.style.background = '#f9f9f9'; div.style.padding = '10px'; div.style.marginBottom = '10px'; div.style.borderRadius = '4px';
            div.innerHTML = `<select class="btn-type" data-idx="${idx}"><option value="QUICK_REPLY" ${btn.type === 'QUICK_REPLY'?'selected':''}>Resposta Rápida</option><option value="URL" ${btn.type === 'URL'?'selected':''}>Abrir Site</option></select> <input type="text" class="btn-text" data-idx="${idx}" value="${btn.text}"> <button type="button" class="btn-remove" data-idx="${idx}" style="color:red; background:none; border:none;">X</button>`;
            container.appendChild(div);
        });
        container.querySelectorAll('.btn-text').forEach(input => input.addEventListener('input', (e) => { wizardButtons[e.target.dataset.idx].text = e.target.value; updatePreview(); }));
        container.querySelectorAll('.btn-remove').forEach(btn => btn.addEventListener('click', (e) => { wizardButtons.splice(e.target.dataset.idx, 1); renderWizardButtons(); updatePreview(); }));
    }

    function renderComplianceCheck() {
        const list = document.getElementById('wiz-checklist');
        if (!list) return;
        const name = document.getElementById('wiz-tpl-name').value;
        const body = document.getElementById('wiz-body-text').value;
        list.innerHTML = `<ul><li>${/^[a-z0-9_]+$/.test(name) ? '✅' : '❌'} Nome válido</li><li>${body.length > 0 ? '✅' : '❌'} Corpo preenchido</li></ul>`;
    }

    async function fetchTemplates() {
        const tbody = document.getElementById('template-list-body');
        if (!tbody) return;
        try {
            const templates = await wasApiFetch('/templates');
            tbody.innerHTML = '';
            if (!templates || templates.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">Vazio</td></tr>';
                return;
            }
            templates.forEach(t => {
                const tr = document.createElement('tr');
                const canSend = t.status === 'APPROVED' || t.status === 'approved';
                tr.innerHTML = `<td><strong>${t.name}</strong></td><td>${t.category}</td><td>${t.language}</td><td><span class="was-status-badge was-status-${t.status.toLowerCase()}">${t.status}</span></td><td>${canSend ? `<button class="was-btn-send-tpl button" data-id="${t.id}" data-name="${t.name}">Enviar</button>` : '---'}</td>`;
                tbody.appendChild(tr);
            });
            document.querySelectorAll('.was-btn-send-tpl').forEach(btn => btn.addEventListener('click', () => {
                document.getElementById('send-tpl-id').value = btn.dataset.id;
                document.getElementById('send-tpl-name-display').textContent = btn.dataset.name;
                document.getElementById('was-send-template-modal').style.display = 'block';
            }));
        } catch (err) { tbody.innerHTML = '<tr><td colspan="5">Erro ao carregar</td></tr>'; }
    }

    if (page === 'logs') initLogs();
    if (page === 'settings-meta') initSettingsMeta();
    if (page === 'settings-whatsapp') initSettingsWhatsApp();

    async function initLogs() {
        const tbody = document.getElementById('log-list-body');
        const metaTbody = document.getElementById('meta-log-list-body');
        if (tbody) {
            try {
                const logs = await wasApiFetch('/audit-logs');
                tbody.innerHTML = logs.map(log => `<tr><td>${log.created_at}</td><td>ID: ${log.user_id}</td><td>${log.action}</td><td>${log.entity_type}</td><td><small>${log.metadata}</small></td></tr>`).join('') || '<tr><td colspan="5">Vazio</td></tr>';
            } catch (err) { tbody.innerHTML = '<tr><td>Erro</td></tr>'; }
        }
        if (metaTbody) {
            try {
                const metaLogs = await wasApiFetch('/meta-api-logs');
                metaTbody.innerHTML = metaLogs.map(log => `<tr><td>${log.created_at}</td><td>${log.operation}</td><td>${log.method}</td><td>${log.status_code}</td><td>${log.success ? '✅' : '❌'}</td><td>${log.duration_ms}ms</td></tr>`).join('') || '<tr><td colspan="6">Vazio</td></tr>';
            } catch (err) { metaTbody.innerHTML = '<tr><td>Erro</td></tr>'; }
        }
    }

    async function initSettingsMeta() {
        const form = document.getElementById('was-meta-config-form');
        if (!form) return;
        try {
            const data = await wasApiFetch('/meta/config');
            if (data) {
                document.getElementById('app_id').value = data.app_id || '';
                document.getElementById('app_secret').value = data.app_secret || '';
                document.getElementById('graph_version').value = data.graph_version || 'v25.0';
                document.getElementById('verify_token').value = data.verify_token || '';
            }
        } catch (err) {}
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                app_id: document.getElementById('app_id').value,
                app_secret: document.getElementById('app_secret').value,
                graph_version: document.getElementById('graph_version').value,
                verify_token: document.getElementById('verify_token').value
            };
            await wasApiFetch('/meta/config', 'POST', formData);
            alert('Salvo!');
        });
    }

    async function initSettingsWhatsApp() {
        const launchBtn = document.getElementById('was-launch-signup');
        if (!launchBtn) return;
        try {
            const accounts = await wasApiFetch('/whatsapp/accounts');
            if (accounts && accounts.length > 0) {
                document.getElementById('was-status-text').textContent = 'Conectado';
                document.getElementById('was-waba-id').textContent = accounts[0].waba_id;
                document.getElementById('was-connection-details').style.display = 'block';
            }
        } catch (err) {}
    }
});
