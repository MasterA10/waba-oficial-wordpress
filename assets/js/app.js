document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.wasApp === 'undefined') {
        return;
    }

    const { restUrl, nonce } = window.wasApp;

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
        } catch (err) {
            console.error('Dashboard Error:', err);
        }
    }

    /**
     * Inbox Initialization
     */
    if (document.getElementById('was-conversations-list')) {
        initInbox();
    }

    let currentConversationId = null;

    function initInbox() {
        const btnRefresh = document.getElementById('was-refresh-conversations');
        const sendBtn = document.getElementById('was-send-message');
        const inputField = document.getElementById('was-message-input');
        const openTplBtn = document.getElementById('was-open-templates-inbox');
        const closeTplBtn = document.getElementById('was-close-inbox-tpl-modal');

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
                listContainer.innerHTML = '<div class="was-empty-state">Nenhuma conversa encontrada.</div>';
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
        if (!historyContainer) return;
        historyContainer.innerHTML = '<div class="was-loading-state">Carregando...</div>';

        try {
            const response = await wasApiFetch(`/conversations/${id}`);
            const messages = response.data?.messages || [];
            historyContainer.innerHTML = '';
            messages.forEach(msg => {
                const text = msg.text_body || msg.body || 'Conteúdo indisponível';
                const type = msg.message_type || msg.type || 'text';
                const payload = (type === 'template' && msg.raw_payload) ? JSON.parse(msg.raw_payload) : null;
                renderMessage(text, msg.direction || 'outbound', type, payload);
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
            const response = await wasApiFetch(`/conversations/${currentConversationId}/messages/text`, 'POST', {
                text: body
            });
            if (response.wa_message_id) {
                renderMessage(body, 'outbound', 'text');
                inputField.value = '';
                scrollToBottom();
            }
        } catch (err) {
            alert('Falha ao enviar: ' + err.message);
        } finally {
            inputField.disabled = false;
            sendBtn.disabled = false;
            inputField.focus();
        }
    }

    async function openInboxTplModal() {
        const modal = document.getElementById('was-inbox-tpl-modal');
        const list = document.getElementById('was-inbox-tpl-list');
        if (!modal || !list) return;
        modal.style.display = 'block';
        list.innerHTML = '<p>Carregando...</p>';

        try {
            const templates = await wasApiFetch('/templates');
            list.innerHTML = '';
            const approved = (templates || []).filter(t => t.status === 'APPROVED' || t.status === 'approved');
            if (approved.length === 0) {
                list.innerHTML = '<p>Nenhum modelo aprovado encontrado.</p>';
                return;
            }
            approved.forEach(t => {
                const item = document.createElement('div');
                item.className = 'was-tpl-select-item';
                item.style.padding = '10px';
                item.style.borderBottom = '1px solid #eee';
                item.style.cursor = 'pointer';
                item.innerHTML = `<strong>${t.name}</strong><br><small>${(t.body_text || '').substring(0, 80)}...</small>`;
                item.addEventListener('click', () => {
                    document.getElementById('was-inbox-tpl-modal').style.display = 'none';
                    openSendModal(t.id, t.name);
                });
                list.appendChild(item);
            });
        } catch (err) {
            list.innerHTML = '<p>Erro ao carregar modelos.</p>';
        }
    }

    function renderMessage(text, direction, type = 'text', payload = null) {
        const historyContainer = document.getElementById('was-messages-history');
        if (!historyContainer) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = `was-message ${direction === 'outbound' || direction === 'sent' ? 'was-message-out' : 'was-message-in'}`;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'was-message-content';
        
        if (type === 'template') {
            const header = payload?.header ? `<div class="was-tpl-header">${payload.header}</div>` : '';
            const footer = payload?.footer ? `<div class="was-tpl-footer">${payload.footer}</div>` : '';
            let buttons = '';
            if (payload?.buttons?.length > 0) {
                buttons = '<div class="was-tpl-btns">' + 
                    payload.buttons.map(b => {
                        const icon = b.type === 'URL' ? '<span class="dashicons dashicons-share"></span>' : '<span class="dashicons dashicons-undo"></span>';
                        return `<div class="was-tpl-btn-item">${icon} ${b.text}</div>`;
                    }).join('') + 
                    '</div>';
            }

            contentDiv.innerHTML = `
                <div class="was-template-card">
                    ${header}
                    <div class="was-tpl-body">${text}</div>
                    ${footer}
                    ${buttons}
                </div>`;
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
    if (document.getElementById('was-open-create-wizard')) {
        initTemplates();
    }

    let wizardStep = 1;
    let wizardButtons = [];
    let editingTemplateId = null;
    let activeSendTemplate = null;

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
                editingTemplateId = null;
                wizardForm.reset();
                wizardButtons = [];
                renderWizardButtons();
                updatePreview();
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
                    const path = editingTemplateId ? `/templates/${editingTemplateId}` : '/templates';
                    const method = editingTemplateId ? 'PUT' : 'POST';
                    await wasApiFetch(path, method, friendlyPayload);
                    alert(editingTemplateId ? 'Template atualizado!' : 'Enviado!');
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
                
                // Collect variables
                const varInputs = document.querySelectorAll('.was-var-input');
                const variables = {};
                varInputs.forEach(input => {
                    variables[input.dataset.var] = input.value;
                });

                try {
                    const payload = { to, variables };
                    // If in Inbox, currentConversationId exists
                    if (currentConversationId) payload.conversation_id = currentConversationId;

                    await wasApiFetch(`/templates/${id}/send`, 'POST', payload);
                    alert('Modelo enviado!');
                    document.getElementById('was-send-template-modal').style.display = 'none';
                    sendTplForm.reset();
                    if (document.getElementById('was-chat-contact-name')) {
                        loadConversation(currentConversationId, document.getElementById('was-chat-contact-name').textContent);
                    }
                } catch (err) { alert(err.message); }
            });
        }

        const closeSendModalBtn = document.getElementById('was-close-send-modal');
        if (closeSendModalBtn) {
            closeSendModalBtn.addEventListener('click', () => {
                document.getElementById('was-send-template-modal').style.display = 'none';
            });
        }

        fetchTemplates();
    }

    async function openSendModal(id, name) {
        const modal = document.getElementById('was-send-template-modal');
        if (!modal) return;

        try {
            const tpl = await wasApiFetch(`/templates/${id}`);
            activeSendTemplate = tpl;

            document.getElementById('send-tpl-id').value = id;
            document.getElementById('send-tpl-name-display').textContent = name;
            
            // Extract all variables {{...}}
            const allText = (tpl.header_type === 'TEXT' ? tpl.friendly_payload?.header?.text : '') + ' ' + tpl.body_text + ' ' + (tpl.buttons_json?.map(b => b.url || '').join(' ') || '');
            const vars = [...new Set(allText.match(/\{\{([^}]+)\}\}/g))];

            const container = document.getElementById('was-tpl-variables-inputs');
            container.innerHTML = '';

            if (vars.length > 0) {
                vars.forEach(v => {
                    const varName = v.replace('{{', '').replace('}}', '');
                    const p = document.createElement('p');
                    p.innerHTML = `<label><strong>${varName}</strong></label><br><input type="text" class="was-var-input" data-var="${varName}" style="width:100%;" placeholder="Valor para ${v}" required>`;
                    container.appendChild(p);
                });
                document.getElementById('was-tpl-variables-container').style.display = 'block';

                container.querySelectorAll('.was-var-input').forEach(input => {
                    input.addEventListener('input', updateSendPreview);
                });
            } else {
                document.getElementById('was-tpl-variables-container').style.display = 'none';
            }

            updateSendPreview();
            modal.style.display = 'block';
        } catch (err) {
            alert('Erro ao carregar detalhes: ' + err.message);
        }
    }

    function updateSendPreview() {
        if (!activeSendTemplate) return;

        const varInputs = document.querySelectorAll('.was-var-input');
        const varMap = {};
        varInputs.forEach(input => { varMap[input.dataset.var] = input.value || `{{${input.dataset.var}}}`; });

        const replaceVars = (text) => {
            if (!text) return '';
            return text.replace(/\{\{([^}]+)\}\}/g, (match, name) => varMap[name] || match);
        };

        const preHeader = document.getElementById('send-pre-header');
        const preBody = document.getElementById('send-pre-body');
        const preFooter = document.getElementById('send-pre-footer');
        const preButtons = document.getElementById('send-pre-buttons');

        if (activeSendTemplate.header_type === 'TEXT') {
            preHeader.textContent = replaceVars(activeSendTemplate.friendly_payload?.header?.text);
            preHeader.style.display = 'block';
        } else preHeader.style.display = 'none';

        preBody.textContent = replaceVars(activeSendTemplate.body_text);
        
        if (activeSendTemplate.footer_text) {
            preFooter.textContent = activeSendTemplate.footer_text;
            preFooter.style.display = 'block';
        } else preFooter.style.display = 'none';

        const btns = activeSendTemplate.buttons_json || [];
        if (btns.length > 0) {
            preButtons.innerHTML = btns.map(b => `<div class="was-wa-btn-item">${b.text}</div>`).join('');
            preButtons.style.display = 'block';
        } else preButtons.style.display = 'none';
    }

    async function openEditWizard(id) {
        try {
            const tpl = await wasApiFetch(`/templates/${id}`);
            editingTemplateId = id;
            
            document.getElementById('wiz-tpl-name').value = tpl.name;
            document.getElementById('wiz-tpl-lang').value = tpl.language;
            const catRadio = document.querySelector(`input[name="category"][value="${tpl.category}"]`);
            if (catRadio) catRadio.checked = true;

            document.getElementById('wiz-header-type').value = tpl.header_type || 'NONE';
            document.getElementById('wiz-header-text').value = tpl.friendly_payload?.header?.text || '';
            document.getElementById('wiz-header-text-container').style.display = tpl.header_type === 'TEXT' ? 'block' : 'none';

            document.getElementById('wiz-body-text').value = tpl.body_text;
            document.getElementById('wiz-footer-text').value = tpl.footer_text || '';

            wizardButtons = tpl.buttons_json || [];
            
            renderWizardButtons();
            updatePreview();
            document.getElementById('was-template-wizard').style.display = 'block';
            setWizardStep(1);
        } catch (err) {
            alert('Erro ao carregar template: ' + err.message);
        }
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
            let extraFields = '';
            if (btn.type === 'URL') extraFields = `<input type="text" class="btn-url" data-idx="${idx}" value="${btn.url || ''}" placeholder="https://exemplo.com/{{variavel}}" style="width:100%; margin-top:5px;">`;
            div.innerHTML = `<div style="display:flex; gap:10px; align-items:center;"><select class="btn-type" data-idx="${idx}" style="flex:1;"><option value="QUICK_REPLY" ${btn.type === 'QUICK_REPLY'?'selected':''}>Resposta Rápida</option><option value="URL" ${btn.type === 'URL'?'selected':''}>Abrir Site</option></select> <input type="text" class="btn-text" data-idx="${idx}" value="${btn.text}" style="flex:2;"> <button type="button" class="btn-remove" data-idx="${idx}" style="color:red; background:none; border:none; cursor:pointer;">X</button></div>${extraFields}`;
            container.appendChild(div);
        });
        container.querySelectorAll('.btn-type').forEach(sel => sel.addEventListener('change', (e) => { 
            const idx = e.target.dataset.idx; wizardButtons[idx].type = e.target.value; 
            if (e.target.value === 'URL' && !wizardButtons[idx].url) wizardButtons[idx].url = ''; renderWizardButtons(); updatePreview(); 
        }));
        container.querySelectorAll('.btn-text').forEach(input => input.addEventListener('input', (e) => { wizardButtons[e.target.dataset.idx].text = e.target.value; updatePreview(); }));
        container.querySelectorAll('.btn-url').forEach(input => input.addEventListener('input', (e) => { wizardButtons[e.target.dataset.idx].url = e.target.value; updatePreview(); }));
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
                tr.innerHTML = `<td><strong>${t.name}</strong></td><td>${t.category}</td><td>${t.language}</td><td><span class="was-status-badge was-status-${t.status.toLowerCase()}">${t.status}</span></td><td>
                    <button class="was-btn-edit-tpl button-link" data-id="${t.id}" title="Editar">Editar</button>
                    ${canSend ? `<button class="was-btn-send-tpl button" data-id="${t.id}" data-name="${t.name}">Enviar</button>` : '---'}
                </td>`;
                tbody.appendChild(tr);
            });
            document.querySelectorAll('.was-btn-edit-tpl').forEach(btn => btn.addEventListener('click', () => openEditWizard(btn.dataset.id)));
            document.querySelectorAll('.was-btn-send-tpl').forEach(btn => btn.addEventListener('click', () => openSendModal(btn.dataset.id, btn.dataset.name)));
        } catch (err) { tbody.innerHTML = '<tr><td colspan="5">Erro ao carregar</td></tr>'; }
    }

    /**
     * Logs & Settings
     */
    if (document.getElementById('log-list-body')) initLogs();
    if (document.getElementById('was-meta-config-form')) initSettingsMeta();
    if (document.getElementById('was-launch-signup')) initSettingsWhatsApp();

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

        const urlInput = document.getElementById('webhook_url');
        const tokenBtn = document.getElementById('was-generate-token');
        const tokenInput = document.getElementById('verify_token');
        const statusSpan = document.getElementById('was-save-status');

        // Fetch current config
        try {
            const data = await wasApiFetch('/meta/config');
            if (data) {
                document.getElementById('app_id').value = data.app_id || '';
                document.getElementById('app_secret').value = data.app_secret || '';
                document.getElementById('graph_version').value = data.graph_version || 'v25.0';
                document.getElementById('primary_phone_number_id').value = data.primary_phone_number_id || '';
                tokenInput.value = data.verify_token || '';

                if (urlInput) {
                    urlInput.value = data.webhook_url || '';
                }
            }
        } catch (err) {
            console.error('Error fetching config:', err);
        }

        // Generate Token Logic
        if (tokenBtn && tokenInput) {
            tokenBtn.addEventListener('click', () => {
                const randomToken = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                    .map(b => b.toString(16).padStart(2, '0'))
                    .join('');
                tokenInput.value = randomToken;
            });
        }

        // Save Logic
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (statusSpan) statusSpan.textContent = 'Salvando...';

            const formData = {
                app_id: document.getElementById('app_id').value,
                app_secret: document.getElementById('app_secret').value,
                graph_version: document.getElementById('graph_version').value,
                verify_token: tokenInput.value,
                primary_phone_number_id: document.getElementById('primary_phone_number_id').value
            };

            try {
                await wasApiFetch('/meta/config', 'POST', formData);
                if (statusSpan) statusSpan.textContent = '✅ Configurações salvas com sucesso!';
                setTimeout(() => { if (statusSpan) statusSpan.textContent = ''; }, 3000);
            } catch (err) {
                if (statusSpan) statusSpan.textContent = '❌ Erro ao salvar.';
                alert('Erro: ' + err.message);
            }
        });
    }
    async function initSettingsWhatsApp() {
        const launchBtn = document.getElementById('was-launch-signup');
        const statusText = document.getElementById('was-status-text');
        const detailsDiv = document.getElementById('was-connection-details');
        const wabaIdText = document.getElementById('was-waba-id');

        if (!launchBtn) return;
        try {
            const accounts = await wasApiFetch('/whatsapp/accounts');
            if (accounts && accounts.length > 0) {
                if (statusText) statusText.textContent = 'Conectado';
                if (wabaIdText) wabaIdText.textContent = accounts[0].waba_id;
                if (detailsDiv) detailsDiv.style.display = 'block';
            }
        } catch (err) {}
    }
});
