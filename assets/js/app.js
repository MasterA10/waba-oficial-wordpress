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

        ['wiz-body-text', 'wiz-header-text', 'wiz-footer-text'].forEach(id => {
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
                processedBody = processedBody.replace(new RegExp(`{{\\s*${k}\\s*}}`, 'g'), `<span style="color:#2563eb; font-weight:bold;">${v}</span>`);
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

    window.updateVariableExample = (key, val) => {
        wizardVariables[key] = val;
        updatePreview();
    };

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

    window.updateWizardButton = (i, field, val) => { 
        wizardButtons[i][field] = val; 
        if (field === 'url') renderWizardButtons(); // Re-render to show/hide dynamic URL example field
        updatePreview(); 
    };
    window.removeWizardButton = (i) => { wizardButtons.splice(i, 1); renderWizardButtons(); updatePreview(); };

    function renderChecklist() {
        const list = document.getElementById('was-checklist-items');
        if (!list) return;
        const name = document.getElementById('wiz-tpl-name').value;
        const body = document.getElementById('wiz-body-text').value;
        const hType = document.getElementById('wiz-header-type').value;
        const hText = document.getElementById('wiz-header-text').value;

        const checks = [
            { label: 'Nome válido', pass: /^[a-z0-9_]+$/.test(name) },
            { label: 'Mensagem principal preenchida', pass: body.trim().length > 0 },
            { label: 'Variáveis possuem exemplos', pass: Object.values(wizardVariables).every(v => v.trim().length > 0) },
            { label: 'Cabeçalho válido', pass: hType === 'NONE' || hText.trim().length > 0 },
            { label: 'Botões válidos', pass: wizardButtons.every(b => b.text.trim().length > 0 && (b.type !== 'URL' || b.url.startsWith('https://'))) }
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
                await wasApiFetch(`/templates/${id}/send`, 'POST', { to_phone: to, variables: variables });
                alert('Template enviado com sucesso!');
                document.getElementById('was-send-template-modal').style.display = 'none';
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
            await wasApiFetch(`/templates/${id}/send`, 'POST', { conversation_id: currentConversationId });
            alert('Enviado!'); document.getElementById('was-inbox-tpl-modal').style.display = 'none';
            loadConversation(currentConversationId, document.getElementById('was-chat-contact-name').textContent);
        } catch (err) { alert(err.message); }
    };
});
