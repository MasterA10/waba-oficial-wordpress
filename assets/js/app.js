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
            console.error('Failed to load dashboard data', err);
            dashboardEl.insertAdjacentHTML('beforebegin', '<div class="notice notice-error"><p>Erro ao carregar dados do dashboard.</p></div>');
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
        
        if (btnRefresh) {
            btnRefresh.addEventListener('click', fetchConversations);
        }
        
        // Setup Chat UI
        const sendBtn = document.getElementById('was-send-message');
        const inputField = document.getElementById('was-message-input');

        if (inputField) {
            inputField.addEventListener('input', () => {
                sendBtn.disabled = inputField.value.trim() === '';
            });

            inputField.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }

        // Initial Load
        fetchConversations();
    }

    async function fetchConversations() {
        const listContainer = document.getElementById('was-conversations-list');
        if (!listContainer) return;

        listContainer.innerHTML = '<div class="was-loading-state">Carregando conversas...</div>';

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
            listContainer.innerHTML = `<div class="was-error-state">Erro ao carregar conversas: ${err.message}</div>`;
        }
    }

    async function loadConversation(id, contactName) {
        currentConversationId = id;
        document.getElementById('was-no-conversation-selected').style.display = 'none';
        document.getElementById('was-active-chat').style.display = 'flex';
        document.getElementById('was-chat-contact-name').textContent = contactName || 'Contato';
        
        const historyContainer = document.getElementById('was-messages-history');
        historyContainer.innerHTML = '<div class="was-loading-state">Carregando mensagens...</div>';

        try {
            const response = await wasApiFetch(`/conversations/${id}`);
            const messages = response.data?.messages || [];
            
            historyContainer.innerHTML = '';
            
            messages.forEach(msg => {
                renderMessage(msg.body || msg.text_body || 'Mensagem de mídia/template', msg.direction || msg.type);
            });

            scrollToBottom();
        } catch (err) {
            historyContainer.innerHTML = '<div class="was-error-state">Erro ao carregar mensagens.</div>';
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
                renderMessage(body, 'outbound');
                inputField.value = '';
                scrollToBottom();
            } else {
                alert('Erro ao enviar mensagem: ' + (response.message || 'Desconhecido'));
            }
        } catch (err) {
            alert('Falha ao enviar mensagem.');
        } finally {
            inputField.disabled = false;
            sendBtn.disabled = false;
            inputField.focus();
        }
    }

    function renderMessage(text, direction) {
        const historyContainer = document.getElementById('was-messages-history');
        if (!historyContainer) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = `was-message ${direction === 'outbound' || direction === 'sent' ? 'was-message-out' : 'was-message-in'}`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'was-message-content';
        contentDiv.textContent = text;
        
        msgDiv.appendChild(contentDiv);
        historyContainer.appendChild(msgDiv);
    }

    function scrollToBottom() {
        const historyContainer = document.getElementById('was-messages-history');
        if (historyContainer) {
            historyContainer.scrollTop = historyContainer.scrollHeight;
        }
    }

    /**
     * Templates Initialization
     */
    if (page === 'templates') {
        initTemplates();
    }

    function initTemplates() {
        const syncBtn = document.getElementById('sync-templates');
        const openCreateBtn = document.getElementById('was-open-create-modal');
        const closeCreateBtn = document.getElementById('was-close-create-modal');
        const closeSendBtn = document.getElementById('was-close-send-modal');
        const createForm = document.getElementById('was-create-template-form');
        const sendForm = document.getElementById('was-send-template-form');

        if (openCreateBtn) {
            openCreateBtn.addEventListener('click', () => {
                document.getElementById('was-create-template-modal').style.display = 'block';
            });
        }

        if (closeCreateBtn) {
            closeCreateBtn.addEventListener('click', () => {
                document.getElementById('was-create-template-modal').style.display = 'none';
            });
        }

        if (closeSendBtn) {
            closeSendBtn.addEventListener('click', () => {
                document.getElementById('was-send-template-modal').style.display = 'none';
            });
        }

        if (createForm) {
            createForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = {
                    name: document.getElementById('tpl-name').value,
                    language: document.getElementById('tpl-lang').value,
                    category: document.getElementById('tpl-cat').value,
                    body_text: document.getElementById('tpl-body').value
                };

                try {
                    await wasApiFetch('/templates', 'POST', data);
                    alert('Template criado localmente e enviado para aprovação (Simulação)');
                    document.getElementById('was-create-template-modal').style.display = 'none';
                    createForm.reset();
                    fetchTemplates();
                } catch (err) {
                    alert('Erro ao criar template: ' + err.message);
                }
            });
        }

        if (sendForm) {
            sendForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('send-tpl-id').value;
                const to = document.getElementById('send-tpl-to').value;

                try {
                    await wasApiFetch(`/templates/${id}/send`, 'POST', { to });
                    alert('Template enviado com sucesso!');
                    document.getElementById('was-send-template-modal').style.display = 'none';
                    sendForm.reset();
                } catch (err) {
                    alert('Erro ao enviar template: ' + err.message);
                }
            });
        }

        if (syncBtn) {
            syncBtn.addEventListener('click', async () => {
                syncBtn.disabled = true;
                syncBtn.textContent = 'Sincronizando...';
                
                try {
                    const res = await wasApiFetch('/templates/sync', 'POST');
                    alert(res.message || 'Templates sincronizados com sucesso!');
                    fetchTemplates();
                } catch (err) {
                    alert('Erro ao sincronizar templates: ' + err.message);
                } finally {
                    syncBtn.disabled = false;
                    syncBtn.textContent = 'Sincronizar da Meta';
                }
            });
        }

        fetchTemplates();
    }

    async function fetchTemplates() {
        const tbody = document.getElementById('template-list-body');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5">Carregando...</td></tr>';

        try {
            const templates = await wasApiFetch('/templates');
            tbody.innerHTML = '';
            
            if (!templates || templates.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">Nenhum template encontrado.</td></tr>';
                return;
            }

            templates.forEach(t => {
                const tr = document.createElement('tr');
                const canSend = t.status === 'APPROVED' || t.status === 'approved';
                tr.innerHTML = `
                    <td>${t.name}</td>
                    <td>${t.language}</td>
                    <td>${t.category}</td>
                    <td><span class="was-status-badge was-status-${t.status.toLowerCase()}">${t.status}</span></td>
                    <td>
                        ${canSend ? `<button class="was-btn-send-tpl button" data-id="${t.id}" data-name="${t.name}">Enviar</button>` : '<span class="description">Aguardando aprovação</span>'}
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Bind click events for send buttons
            document.querySelectorAll('.was-btn-send-tpl').forEach(btn => {
                btn.addEventListener('click', () => openSendModal(btn.dataset.id, btn.dataset.name));
            });

        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="5" style="color:red;">Erro ao carregar templates.</td></tr>';
        }
    }

    function openSendModal(id, name) {
        document.getElementById('send-tpl-id').value = id;
        document.getElementById('send-tpl-name-display').textContent = name;
        document.getElementById('was-send-template-modal').style.display = 'block';
    }

    /**
     * Logs Initialization
     */
    if (page === 'logs') {
        initLogs();
    }

    if (page === 'settings-meta') {
        initSettingsMeta();
    }

    if (page === 'settings-whatsapp') {
        initSettingsWhatsApp();
    }

    async function initLogs() {
        const tbody = document.getElementById('log-list-body');
        const metaTbody = document.getElementById('meta-log-list-body');
        if (!tbody && !metaTbody) return;

        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5">Carregando Auditoria...</td></tr>';
            try {
                const logs = await wasApiFetch('/audit-logs');
                tbody.innerHTML = '';
                if (!logs || logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5">Nenhum log encontrado.</td></tr>';
                } else {
                    logs.forEach(log => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${log.created_at}</td>
                            <td>ID: ${log.user_id}</td>
                            <td>${log.action}</td>
                            <td>${log.entity_type} (${log.entity_id})</td>
                            <td><small>${log.metadata}</small></td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" style="color:red;">Erro ao carregar auditoria.</td></tr>';
            }
        }

        if (metaTbody) {
            metaTbody.innerHTML = '<tr><td colspan="6">Carregando Meta API Logs...</td></tr>';
            try {
                const metaLogs = await wasApiFetch('/meta-api-logs');
                metaTbody.innerHTML = '';
                if (!metaLogs || metaLogs.length === 0) {
                    metaTbody.innerHTML = '<tr><td colspan="6">Nenhum log técnico encontrado.</td></tr>';
                } else {
                    metaLogs.forEach(log => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${log.created_at}</td>
                            <td>${log.operation}</td>
                            <td>${log.method}</td>
                            <td>${log.status_code}</td>
                            <td>${log.success ? '✅' : '❌'}</td>
                            <td>${log.duration_ms}ms</td>
                        `;
                        metaTbody.appendChild(tr);
                    });
                }
            } catch (err) {
                metaTbody.innerHTML = '<tr><td colspan="6" style="color:red;">Erro ao carregar logs técnicos.</td></tr>';
            }
        }
    }

    async function initSettingsMeta() {
        const form = document.getElementById('was-meta-config-form');
        if (!form) return;
        const status = document.getElementById('was-save-status');
        const generateBtn = document.getElementById('was-generate-token');

        try {
            const data = await wasApiFetch('/meta/config');
            if (data) {
                if (data.app_id) document.getElementById('app_id').value = data.app_id;
                if (data.app_secret) document.getElementById('app_secret').value = data.app_secret;
                if (data.graph_version) document.getElementById('graph_version').value = data.graph_version;
                if (data.verify_token) document.getElementById('verify_token').value = data.verify_token;
            }
        } catch (err) {
            console.error('Failed to load meta config', err);
        }

        if (generateBtn) {
            generateBtn.addEventListener('click', function() {
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                let token = "";
                for (let i = 0; i < 32; i++) {
                    token += charset.charAt(Math.floor(Math.random() * charset.length));
                }
                document.getElementById('verify_token').value = token;
            });
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            status.textContent = 'Salvando...';
            status.style.color = 'inherit';

            const formData = {
                app_id: document.getElementById('app_id').value,
                app_secret: document.getElementById('app_secret').value,
                graph_version: document.getElementById('graph_version').value,
                verify_token: document.getElementById('verify_token').value
            };

            try {
                await wasApiFetch('/meta/config', 'POST', formData);
                status.textContent = 'Salvo com sucesso!';
                status.style.color = 'green';
                setTimeout(() => { status.textContent = ''; }, 3000);
            } catch (err) {
                status.textContent = 'Erro ao salvar: ' + err.message;
                status.style.color = 'red';
            }
        });
    }

    async function initSettingsWhatsApp() {
        const launchBtn = document.getElementById('was-launch-signup');
        const disconnectBtn = document.getElementById('was-disconnect-waba');
        const statusText = document.getElementById('was-status-text');
        const detailsDiv = document.getElementById('was-connection-details');

        if (!launchBtn) return;

        async function checkStatus() {
            try {
                const accounts = await wasApiFetch('/whatsapp/accounts');
                if (accounts && accounts.length > 0) {
                    const account = accounts[0];
                    statusText.textContent = 'Conectado';
                    statusText.style.color = 'green';
                    document.getElementById('was-waba-id').textContent = account.waba_id;
                    document.getElementById('was-phone-number').textContent = account.display_phone_number || 'ID: ' + account.phone_number_id;
                    detailsDiv.style.display = 'block';
                    launchBtn.style.display = 'none';
                    disconnectBtn.style.display = 'inline-block';
                } else {
                    statusText.textContent = 'Não conectado';
                    statusText.style.color = 'red';
                    detailsDiv.style.display = 'none';
                    launchBtn.style.display = 'inline-block';
                    disconnectBtn.style.display = 'none';
                }
            } catch (err) {
                statusText.textContent = 'Erro ao verificar status';
            }
        }

        checkStatus();

        launchBtn.addEventListener('click', function() {
            // Em um ambiente real, aqui chamamos FB.login ou FB.ui
            alert('Iniciando Embedded Signup... (Simulação)');
            // Para fins de teste/MVP, podemos simular o salvamento de um asset
            // await wasApiFetch('/meta/embedded-signup/save-assets', 'POST', { ... });
        });
    }
});
