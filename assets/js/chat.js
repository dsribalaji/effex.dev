(function() {
    'use strict';

    let activeConvId = typeof CONV_ID !== 'undefined' ? CONV_ID : null;
    window.__activeConvId = activeConvId;
    let skillsCache = [];
    let csrfToken = null;
    window.__csrfToken = '';

    async function loadCsrfToken() {
        try {
            const res = await fetch('api/csrf_token.php');
            const data = await res.json();
            csrfToken = data.token;
            window.__csrfToken = data.token;
        } catch (e) {
            console.warn('CSRF token unavailable');
        }
    }

    const messageList = document.getElementById('messageList');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const convSelector = document.getElementById('convSelector');
    const convList = document.getElementById('convList');
    const newConvBtn = document.getElementById('newConvBtn');
    const clearChatBtn = document.getElementById('clearChatBtn');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const autocomplete = document.getElementById('autocomplete');

    /* ── Conversation CRUD ── */

    async function loadConversations() {
        const res = await fetch('api/conversations.php');
        const convs = await res.json();

        convSelector.innerHTML = '';
        convList.innerHTML = '';

        convs.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.title;
            if (c.id === activeConvId) opt.selected = true;
            convSelector.appendChild(opt);

            const li = document.createElement('li');
            li.className = 'conv-item';
            li.dataset.id = c.id;
            if (c.id === activeConvId) li.classList.add('active');

            const nameSpan = document.createElement('span');
            nameSpan.className = 'conv-name';
            nameSpan.textContent = c.title;
            nameSpan.title = 'Click to rename';
            nameSpan.addEventListener('click', () => switchConversation(c.id));

            const renameBtn = document.createElement('button');
            renameBtn.className = 'conv-rename';
            renameBtn.innerHTML = '✏️';
            renameBtn.title = 'Rename conversation';
            renameBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                renameConversation(c.id, c.title);
            });

            const delBtn = document.createElement('button');
            delBtn.className = 'conv-del';
            delBtn.textContent = '✕';
            delBtn.title = 'Delete conversation';
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteConversation(c.id, c.title);
            });

            li.appendChild(nameSpan);
            li.appendChild(renameBtn);
            li.appendChild(delBtn);
            convList.appendChild(li);
        });
    }

    async function switchConversation(id) {
        activeConvId = id;
        window.__activeConvId = id;
        history.replaceState(null, '', '?conv=' + id);

        convSelector.value = id;
        document.querySelectorAll('#convList li').forEach(li => {
            li.classList.toggle('active', parseInt(li.dataset.id) === id);
        });

        await loadMessages();
        if (window.refreshSidebar) window.refreshSidebar(id);
    }

    async function createConversation() {
        const res = await fetch('api/conversations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
            body: JSON.stringify({})
        });
        const data = await res.json();
        await loadConversations();
        await switchConversation(data.id);
    }

    async function deleteConversation(id, title) {
        if (!confirm('Delete conversation "' + title + '"? This cannot be undone.')) return;
        try {
            const res = await fetch('api/conversations.php?id=' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': csrfToken || '' },
            });
            const data = await res.json();
            if (data.success) {
                const wasActive = id === activeConvId;
                await loadConversations();
                if (wasActive) {
                    const firstId = convSelector.value ? parseInt(convSelector.value) : null;
                    if (firstId) {
                        await switchConversation(firstId);
                    } else {
                        await createConversation();
                    }
                }
            }
        } catch (e) {
            console.error('Delete conversation failed', e);
        }
    }

    /* ── Messages ── */

    async function loadMessages() {
        if (!activeConvId) return;
        const res = await fetch('api/messages.php?conversation_id=' + activeConvId);
        const msgs = await res.json();

        messageList.innerHTML = '';
        msgs.forEach(m => addMessage(m.role, m.content, false));
        scrollToBottom();
    }

    function addMessage(role, content, save = false) {
        const div = document.createElement('div');
        div.className = 'message ' + role;
        div.textContent = content;
        messageList.appendChild(div);
        scrollToBottom();
    }

    function scrollToBottom() {
        messageList.scrollTop = messageList.scrollHeight;
    }

    /* ── Send message (SSE streaming) ── */

    let streaming = false;

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text || !activeConvId || streaming) return;

        chatInput.value = '';
        streaming = true;
        sendBtn.disabled = true;

        addMessage('user', text);

        // Parse @references
        const references = [];
        const atRegex = /@([^\s]+)/g;
        let match;
        while ((match = atRegex.exec(text)) !== null) {
            references.push(match[1]);
        }

        // Create assistant bubble for streaming
        const bubble = document.createElement('div');
        bubble.className = 'message assistant streaming';
        bubble.textContent = '▌';
        messageList.appendChild(bubble);
        scrollToBottom();

        try {
            const res = await fetch('api/chat_stream.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
                body: JSON.stringify({ conversation_id: activeConvId, message: text, references })
            });

            if (!res.ok) {
                const errText = await res.text();
                bubble.textContent = 'Error: ' + res.status + ' ' + errText;
                bubble.classList.remove('streaming');
                streaming = false;
                sendBtn.disabled = false;
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let fullContent = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed || !trimmed.startsWith('data: ')) continue;
                    try {
                        const data = JSON.parse(trimmed.slice(6));
                        if (data.token) {
                            fullContent += data.token;
                            bubble.textContent = fullContent + '▌';
                            scrollToBottom();
                        }
                        if (data.done) {
                            if (data.saved && data.saved.length) {
                                bubble.innerHTML = `✅ Content generated as artifact: <strong>${data.saved[0].filename}</strong>`;
                                if (window.refreshSidebar) {
                                    window.refreshSidebar(activeConvId);
                                }
                            } else {
                                bubble.textContent = fullContent;
                            }
                            bubble.classList.remove('streaming');
                        }
                        if (data.error) {
                            bubble.textContent = 'Error: ' + data.error;
                            bubble.classList.remove('streaming');
                        }
                    } catch (e) {
                        // skip malformed JSON
                    }
                }
            }
        } catch (e) {
            bubble.textContent = 'Error: ' + e.message;
            bubble.classList.remove('streaming');
        }

        streaming = false;
        sendBtn.disabled = false;
    }

    /* ── Autocomplete for / commands and @ files ── */
    let filesCache = [];

    async function loadSkills() {
        try {
            const res = await fetch('api/skills_list.php');
            skillsCache = await res.json();
        } catch (e) {
            skillsCache = [];
        }
    }

    window.refreshFilesCache = async function() {
        if (!activeConvId) return;
        try {
            const [artRes, uplRes] = await Promise.all([
                fetch('api/artifacts.php?action=list&conversation_id=' + activeConvId),
                fetch('api/uploads.php?action=list&conversation_id=' + activeConvId)
            ]);
            const artifacts = await artRes.json();
            const uploads = await uplRes.json();
            
            // Combine and deduplicate by filename
            const allFiles = [...(Array.isArray(uploads) ? uploads : []), ...(Array.isArray(artifacts) ? artifacts : [])];
            const uniqueFiles = [];
            const seen = new Set();
            for (const f of allFiles) {
                if (f && f.filename && !seen.has(f.filename)) {
                    seen.add(f.filename);
                    uniqueFiles.push(f.filename);
                }
            }
            filesCache = uniqueFiles;
        } catch (e) {
            filesCache = [];
        }
    };

    function updateAutocomplete() {
        const text = chatInput.value;
        const cursor = chatInput.selectionStart;
        const textBeforeCursor = text.slice(0, cursor);
        
        const slashIdx = textBeforeCursor.lastIndexOf('/');
        const atIdx = textBeforeCursor.lastIndexOf('@');
        
        // Find whichever is closer to the cursor
        const triggerIdx = Math.max(slashIdx, atIdx);
        
        if (triggerIdx === -1 || (triggerIdx > 0 && textBeforeCursor[triggerIdx - 1] !== ' ' && textBeforeCursor[triggerIdx - 1] !== '\n')) {
            autocomplete.classList.add('hidden');
            return;
        }

        const isSkill = textBeforeCursor[triggerIdx] === '/';
        const query = textBeforeCursor.slice(triggerIdx + 1).toLowerCase();
        
        let matches = [];
        
        if (isSkill) {
            matches = skillsCache.filter(s => s.id.toLowerCase().startsWith(query)).map(s => ({
                type: 'skill',
                id: s.id,
                display: '/' + s.id,
                desc: s.description
            }));
        } else {
            matches = filesCache.filter(f => f.toLowerCase().startsWith(query)).map(f => ({
                type: 'file',
                id: f,
                display: '@' + f,
                desc: 'File reference'
            }));
        }

        if (matches.length === 0) {
            autocomplete.classList.add('hidden');
            return;
        }

        autocomplete.innerHTML = '';
        matches.forEach((m, i) => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item' + (i === 0 ? ' selected' : '');
            div.dataset.type = m.type;
            div.dataset.id = m.id;
            div.innerHTML = '<strong>' + m.display + '</strong> <span class="skill-desc">' + m.desc + '</span>';
            div.addEventListener('click', () => insertCommand(m.type, m.id, triggerIdx));
            autocomplete.appendChild(div);
        });

        autocomplete.classList.remove('hidden');
    }

    function insertCommand(type, id, triggerIdx) {
        const text = chatInput.value;
        const cursor = chatInput.selectionStart;
        
        const beforeTrigger = text.slice(0, triggerIdx);
        const afterCursor = text.slice(cursor);
        
        const prefix = type === 'skill' ? '/' : '@';
        
        chatInput.value = beforeTrigger + prefix + id + ' ' + afterCursor;
        autocomplete.classList.add('hidden');
        chatInput.focus();

        if (type === 'skill') {
            // Set active_skill on conversation
            fetch('api/conversations.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
                body: JSON.stringify({ id: activeConvId, active_skill: id })
            }).catch(() => {});
        }
    }

    /* ── Event listeners ── */

    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('collapsed'));

    convSelector.addEventListener('change', () => switchConversation(parseInt(convSelector.value)));

    newConvBtn.addEventListener('click', createConversation);

    sendBtn.addEventListener('click', sendMessage);

    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            const items = autocomplete.querySelectorAll('.autocomplete-item');
            if (items.length === 0) return;
            e.preventDefault();
            let idx = Array.from(items).findIndex(el => el.classList.contains('selected'));
            if (e.key === 'ArrowDown') idx = Math.min(idx + 1, items.length - 1);
            else idx = Math.max(idx - 1, 0);
            items.forEach(el => el.classList.remove('selected'));
            items[idx].classList.add('selected');
        }
        if (e.key === 'Enter' && !autocomplete.classList.contains('hidden')) {
            const selected = autocomplete.querySelector('.selected');
            if (selected) {
                e.preventDefault();
                const type = selected.dataset.type;
                const id = selected.dataset.id;
                
                const textBeforeCursor = chatInput.value.slice(0, chatInput.selectionStart);
                const slashIdx = textBeforeCursor.lastIndexOf('/');
                const atIdx = textBeforeCursor.lastIndexOf('@');
                const triggerIdx = Math.max(slashIdx, atIdx);
                
                insertCommand(type, id, triggerIdx);
            }
        }
        if (e.key === 'Escape') autocomplete.classList.add('hidden');
    });

    chatInput.addEventListener('input', updateAutocomplete);

    chatInput.addEventListener('blur', () => setTimeout(() => autocomplete.classList.add('hidden'), 200));

    /* ── Clear chat ── */
    clearChatBtn.addEventListener('click', async () => {
        if (!confirm('Clear all messages in this conversation?')) return;
        
        const res = await fetch('api/clear_messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
            body: JSON.stringify({ conversation_id: activeConvId })
        });
        
        if (res.ok) {
            messageList.innerHTML = '';
            if (window.refreshSidebar) window.refreshSidebar(activeConvId);
        } else {
            alert('Failed to clear chat');
        }
    });

    /* ── Rename conversation ── */
    async function renameConversation(id, currentTitle) {
        const newTitle = prompt('Rename conversation:', currentTitle);
        if (!newTitle || newTitle.trim() === currentTitle) return;
        
        const res = await fetch('api/conversations.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
            body: JSON.stringify({ id, title: newTitle.trim() })
        });
        
        if (res.ok) {
            await loadConversations();
            if (id === activeConvId) {
                document.title = `${newTitle.trim()} - SkillApp`;
            }
        } else {
            alert('Failed to rename conversation');
        }
    }

    /* ── Init ── */

    (async function init() {
        await loadCsrfToken();
        await loadSkills();
        await loadConversations();
        if (activeConvId) await loadMessages();
    })();
})();
