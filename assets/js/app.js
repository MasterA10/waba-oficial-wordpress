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
    let activeSendTemplate = null;

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
                renderMessage(text, msg.direction, msg.message_type, payload);
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
            if (res.wa_message_id) {
                renderMessage(body, 'outbound', 'text');
                inputField.value = '';
                scrollToBottom();
            }
        } catch (err) { alert('Erro ao enviar.'); }
        finally { inputField.disabled = false; sendBtn.disabled = false; inputField.focus(); }
    }

    function renderMessage(text, direction, type = 'text', payload = null) {
        const history = document.getElementById('was-messages-history');
        if (!history) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = `was-message ${direction === 'outbound' || direction === 'sent' ? 'was-message-out' : 'was-message-in'}`;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'was-message-content';
        
        if (type === 'template') {
            const header = payload?.header ? `<div class="was-tpl-header">${payload.header}</div>` : '';
            const footer = payload?.footer ? `<div class="was-tpl-footer">${payload.footer}</div>` : '';
            contentDiv.innerHTML = `<div class="was-template-card">${header}<div class="was-tpl-body">${text}</div>${footer}</div>`;
        } else {
            contentDiv.textContent = text;
        }
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
            editingTemplateId = null; wizardForm?.reset(); wizardButtons = []; 
            document.getElementById('was-template-wizard').style.display = 'block'; 
            setWizardStep(1); renderWizardButtons(); updatePreview();
        });

        document.getElementById('wiz-next')?.addEventListener('click', () => setWizardStep(wizardStep + 1));
        document.getElementById('wiz-prev')?.addEventListener('click', () => setWizardStep(wizardStep - 1));
        document.getElementById('wiz-cancel')?.addEventListener('click', () => document.getElementById('was-template-wizard').style.display = 'none');
        document.getElementById('wiz-add-button')?.addEventListener('click', () => { wizardButtons.push({type:'QUICK_REPLY', text:'Botão'}); renderWizardButtons(); updatePreview(); });

        ['wiz-tpl-name', 'wiz-body-text', 'wiz-header-text', 'wiz-footer-text'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', updatePreview);
        });

        if (wizardForm) wizardForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById('wiz-tpl-name').value,
                category: wizardForm.querySelector('input[name="category"]:checked').value,
                language: document.getElementById('wiz-tpl-lang').value,
                header: { type: 'TEXT', text: document.getElementById('wiz-header-text').value },
                body: { text: document.getElementById('wiz-body-text').value },
                footer: { text: document.getElementById('wiz-footer-text').value },
                buttons: wizardButtons
            };
            try {
                await wasApiFetch('/templates', 'POST', payload);
                alert('Enviado!'); location.reload();
            } catch (err) { alert(err.message); }
        });

        if (syncBtn) syncBtn.addEventListener('click', async () => {
            syncBtn.disabled = true;
            try { await wasApiFetch('/templates/sync', 'POST'); fetchTemplates(); } finally { syncBtn.disabled = false; }
        });

        fetchTemplates();
    }

    async function fetchTemplates() {
        const tbody = document.getElementById('template-list-body');
        if (!tbody) return;
        try {
            const data = await wasApiFetch('/templates');
            tbody.innerHTML = (data || []).map(t => `<tr><td><strong>${t.name}</strong></td><td>${t.category}</td><td>${t.language}</td><td>${t.status}</td><td><button class="button was-btn-send-tpl" data-id="${t.id}" data-name="${t.name}">Enviar</button></td></tr>`).join('') || '<tr><td colspan="5">Vazio</td></tr>';
            document.querySelectorAll('.was-btn-send-tpl').forEach(btn => btn.addEventListener('click', () => openSendModal(btn.dataset.id, btn.dataset.name)));
        } catch (err) { tbody.innerHTML = 'Erro ao carregar.'; }
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
    }

    function updatePreview() {
        const h = document.getElementById('wiz-header-text')?.value || '', b = document.getElementById('wiz-body-text')?.value || '', f = document.getElementById('wiz-footer-text')?.value || '';
        const ph = document.getElementById('pre-header'), pb = document.getElementById('pre-body'), pf = document.getElementById('pre-footer'), pbtns = document.getElementById('pre-buttons');
        if (ph) ph.textContent = h; if (pb) pb.textContent = b; if (pf) pf.textContent = f;
        if (pbtns) pbtns.innerHTML = wizardButtons.map(btn => `<div class="pre-btn">${btn.text}</div>`).join('');
    }

    function renderWizardButtons() {
        const container = document.getElementById('wiz-buttons-list');
        if (!container) return;
        container.innerHTML = wizardButtons.map((btn, i) => `<div style="margin-bottom:5px;"><input type="text" value="${btn.text}" oninput="updateWizardButton(${i}, this.value)" style="width:150px;"> <button type="button" onclick="removeWizardButton(${i})">×</button></div>`).join('');
    }

    window.updateWizardButton = (i, val) => { wizardButtons[i].text = val; updatePreview(); };
    window.removeWizardButton = (i) => { wizardButtons.splice(i, 1); renderWizardButtons(); updatePreview(); };

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
                document.getElementById('verify_token').value = data.verify_token || '';
                document.getElementById('webhook_url').value = data.webhook_url || '';
            }
        } catch (err) {}
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                app_id: document.getElementById('app_id').value,
                app_secret: document.getElementById('app_secret').value,
                verify_token: document.getElementById('verify_token').value,
                primary_phone_number_id: document.getElementById('primary_phone_number_id').value,
                meta_access_token: document.getElementById('meta_access_token').value
            };
            try { await wasApiFetch('/meta/config', 'POST', payload); alert('Salvo!'); } catch (err) { alert(err.message); }
        });
    }

    async function openInboxTplModal() {
        const modal = document.getElementById('was-inbox-tpl-modal');
        const list = document.getElementById('was-inbox-tpl-list');
        if (!modal) return;
        modal.style.display = 'block'; list.innerHTML = '...';
        try {
            const data = await wasApiFetch('/templates');
            const approved = (data || []).filter(t => t.status === 'APPROVED');
            list.innerHTML = approved.map(t => `<div class="was-tpl-select-item" onclick="sendTemplateFromInbox(${t.id})"><strong>${t.name}</strong><br><small>${t.body_text.substring(0, 50)}...</small></div>`).join('') || 'Nenhum template aprovado.';
        } catch (err) { list.innerHTML = 'Erro.'; }
    }

    window.sendTemplateFromInbox = async (id) => {
        if (!confirm('Enviar este template?')) return;
        try {
            await wasApiFetch(`/templates/${id}/send`, 'POST', { conversation_id: currentConversationId });
            alert('Enviado!'); document.getElementById('was-inbox-tpl-modal').style.display = 'none';
            loadConversation(currentConversationId, document.getElementById('was-chat-contact-name').textContent);
        } catch (err) { alert(err.message); }
    };
});
