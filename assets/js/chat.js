(function() {
    'use strict';

    let activeConvId = typeof CONV_ID !== 'undefined' ? CONV_ID : null;
    window.__activeConvId = activeConvId;
    let skillsCache = [];
    let csrfToken = null;
    window.__csrfToken = '';

    /* ── Clipboard helper (shared with sidebar.js) ── */
    window.copyToClipboard = async function(text, btn) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                // http:// fallback (XAMPP over plain HTTP has no clipboard API)
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            if (btn) {
                const prev = btn.innerHTML;
                btn.innerHTML = '✓';
                btn.classList.add('copied');
                setTimeout(() => { btn.innerHTML = prev; btn.classList.remove('copied'); }, 1200);
            }
        } catch (e) {
            console.warn('Copy failed', e);
        }
    };

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

    /* ── Projects ── */

    let activeProjectId = parseInt(localStorage.getItem('skillapp-project')) || null;
    window.__activeProjectId = activeProjectId;
    const projectSelector = document.getElementById('projectSelector');
    const newProjectBtn = document.getElementById('newProjectBtn');
    const exportProjectBtn = document.getElementById('exportProjectBtn');

    async function loadProjects() {
        const res = await fetch('api/projects.php');
        const projects = await res.json();
        if (!Array.isArray(projects) || projects.length === 0) return;

        if (!activeProjectId || !projects.some(p => p.id === activeProjectId)) {
            activeProjectId = projects[0].id;
        }
        window.__activeProjectId = activeProjectId;
        localStorage.setItem('skillapp-project', activeProjectId);

        projectSelector.innerHTML = '';
        projects.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            if (p.id === activeProjectId) opt.selected = true;
            projectSelector.appendChild(opt);
        });
    }

    async function switchProject(id) {
        activeProjectId = id;
        window.__activeProjectId = id;
        localStorage.setItem('skillapp-project', id);
        if (window.closePreview) window.closePreview();

        await loadConversations();
        const firstId = convSelector.value ? parseInt(convSelector.value) : null;
        if (firstId) {
            await switchConversation(firstId);
        } else {
            await createConversation();
        }
    }

    if (projectSelector) projectSelector.addEventListener('change', () => switchProject(parseInt(projectSelector.value)));

    if (newProjectBtn) newProjectBtn.addEventListener('click', async () => {
        const name = prompt('New project name:');
        if (!name || !name.trim()) return;
        const res = await fetch('api/projects.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
            body: JSON.stringify({ name: name.trim() })
        });
        const data = await res.json();
        if (data.error) { alert(data.error); return; }
        await loadProjects();
        await switchProject(data.id);
    });

    if (exportProjectBtn) exportProjectBtn.addEventListener('click', () => {
        if (!activeProjectId) return;
        window.location.href = 'api/projects.php?action=export&id=' + activeProjectId;
    });

    /* ── Conversation CRUD ── */

    async function loadConversations() {
        convList.innerHTML = '<li class="loading-state"><span class="spinner"></span> Loading conversations...</li>';

        let convs;
        try {
            const url = 'api/conversations.php' + (activeProjectId ? '?project_id=' + activeProjectId : '');
            const res = await fetch(url);
            convs = await res.json();
            if (!Array.isArray(convs)) throw new Error(convs && convs.error ? convs.error : 'Unexpected response');
        } catch (e) {
            convList.innerHTML = '<li class="empty-msg">Could not load conversations</li>';
            return;
        }

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
            nameSpan.title = 'Open conversation';
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
        if (window.closePreview) window.closePreview(); // file/html viewer follows the old chat — close it

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
            body: JSON.stringify({ project_id: activeProjectId || 0 })
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
        messageList.classList.add('loading-state');
        messageList.innerHTML = '<span class="spinner"></span> Loading messages...';

        let msgs;
        try {
            const res = await fetch('api/messages.php?conversation_id=' + activeConvId);
            msgs = await res.json();
            if (!Array.isArray(msgs)) throw new Error(msgs && msgs.error ? msgs.error : 'Unexpected response');
        } catch (e) {
            messageList.classList.remove('loading-state');
            messageList.innerHTML = '<div class="empty-msg" style="text-align:center;padding:2rem;">Could not load messages</div>';
            return;
        }

        messageList.classList.remove('loading-state');
        messageList.innerHTML = '';
        msgs.forEach(m => addMessage(m.role, m.content, m.created_at));
        scrollToBottom();
    }

    /* ── Message rendering (bubble + timestamp, markdown for assistant) ── */

    function formatTime(ts) {
        const d = ts ? new Date(ts) : new Date();
        if (isNaN(d.getTime())) return '';
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return sameDay ? time : d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' + time;
    }

    function escapeHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Minimal safe markdown: input is HTML-escaped first, then formatted.
    function renderMarkdown(text) {
        let src = escapeHtml(text);
        const codeBlocks = [];

        // Fenced code blocks first, so nothing inside them is formatted
        src = src.replace(/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, (_, lang, code) => {
            codeBlocks.push('<pre><code>' + code.replace(/\n$/, '') + '</code></pre>');
            return '\n\n\u0000' + (codeBlocks.length - 1) + '\u0000\n\n';
        });

        src = src
            .replace(/`([^`\n]+)`/g, '<code>$1</code>')
            .replace(/^###### (.+)$/gm, '<h6>$1</h6>')
            .replace(/^##### (.+)$/gm, '<h5>$1</h5>')
            .replace(/^#### (.+)$/gm, '<h4>$1</h4>')
            .replace(/^### (.+)$/gm, '<h3>$1</h3>')
            .replace(/^## (.+)$/gm, '<h2>$1</h2>')
            .replace(/^# (.+)$/gm, '<h1>$1</h1>')
            .replace(/^\s*([-*_]){3,}\s*$/gm, '<hr>')
            .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
            .replace(/\[([^\]]+)\]\((https?:[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

        // Lists: group consecutive bullet / numbered lines
        src = src.replace(/(?:^[ \t]*[-*] .+\n?)+/gm, block => {
            const items = block.trim().split('\n').map(l => '<li>' + l.replace(/^[ \t]*[-*] /, '') + '</li>').join('');
            return '<ul>' + items + '</ul>\n';
        });
        src = src.replace(/(?:^[ \t]*\d+\. .+\n?)+/gm, block => {
            const items = block.trim().split('\n').map(l => '<li>' + l.replace(/^[ \t]*\d+\. /, '') + '</li>').join('');
            return '<ol>' + items + '</ol>\n';
        });

        // Paragraphs: split on blank lines; keep block-level HTML as-is
        src = src.split(/\n{2,}/).map(part => {
            const t = part.trim();
            if (!t) return '';
            if (/^<(h\d|ul|ol|pre|hr|blockquote)/.test(t) || /^\u0000\d+\u0000$/.test(t)) return t;
            return '<p>' + t.replace(/\n/g, '<br>') + '</p>';
        }).join('');

        return src.replace(/\u0000(\d+)\u0000/g, (_, i) => codeBlocks[i]);
    }

    // Wrap each code block with a hover Copy button
    function enhanceBubble(bubble) {
        bubble.querySelectorAll('pre').forEach(pre => {
            if (pre.parentElement.classList.contains('code-wrap')) return;
            const wrap = document.createElement('div');
            wrap.className = 'code-wrap';
            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(pre);
            const btn = document.createElement('button');
            btn.className = 'code-copy-btn';
            btn.type = 'button';
            btn.textContent = 'Copy';
            btn.addEventListener('click', () => window.copyToClipboard(pre.textContent, btn));
            wrap.appendChild(btn);
        });
    }

    // "✅ Content generated as artifact: **file.md**" acks render as a tidy chip
    function artifactChipHtml(content) {
        const m = content.match(/^✅ Content generated as artifact:\s*\*{0,2}(.+?)\*{0,2}$/);
        if (!m) return null;
        return '<span class="artifact-chip">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">'
            + '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
            + '<span>Saved as artifact</span><strong>' + escapeHtml(m[1]) + '</strong></span>';
    }

    function addMessage(role, content, time) {
        const row = document.createElement('div');
        row.className = 'message-row ' + role;

        const div = document.createElement('div');
        div.className = 'message ' + role;
        if (role === 'assistant') {
            div.classList.add('md-body');
            const chip = artifactChipHtml(content);
            div.innerHTML = chip !== null ? chip : renderMarkdown(content);
            if (!chip) enhanceBubble(div);
        } else {
            div.textContent = content;
        }
        div.__raw = content;

        const meta = document.createElement('div');
        meta.className = 'msg-meta';

        const timeEl = document.createElement('span');
        timeEl.className = 'msg-time';
        timeEl.textContent = formatTime(time);
        meta.appendChild(timeEl);

        const copyBtn = document.createElement('button');
        copyBtn.className = 'msg-copy-btn';
        copyBtn.type = 'button';
        copyBtn.title = 'Copy message';
        copyBtn.innerHTML = '⧉';
        copyBtn.addEventListener('click', () => window.copyToClipboard(div.__raw || div.textContent, copyBtn));
        meta.appendChild(copyBtn);

        row.appendChild(div);
        row.appendChild(meta);
        messageList.appendChild(row);
        scrollToBottom();
        return div;
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
        chatInput.style.height = 'auto';
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

        // Create assistant bubble for streaming (rendered as plain text while
        // tokens arrive; converted to markdown once the stream completes)
        const bubble = addMessage('assistant', '', null);
        bubble.classList.add('streaming');
        bubble.textContent = '▌';
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
                            if (data.title) loadConversations(); // conversation was auto-titled
                            if (data.saved && data.saved.length) {
                                bubble.innerHTML = artifactChipHtml('✅ Content generated as artifact: ' + data.saved[0].filename);
                                if (window.refreshSidebar) {
                                    window.refreshSidebar(activeConvId);
                                }
                            } else {
                                bubble.innerHTML = renderMarkdown(fullContent);
                                bubble.__raw = fullContent;
                                enhanceBubble(bubble);
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
    let lastSkillsLoad = 0;

    async function loadSkills() {
        lastSkillsLoad = Date.now();
        try {
            const res = await fetch('api/skills_list.php');
            const data = await res.json();
            skillsCache = Array.isArray(data) ? data : [];
        } catch (e) {
            skillsCache = [];
        }
    }

    // If the skill list failed to load (e.g. GitHub rate limit), retry when the user needs it
    function ensureSkillsLoaded() {
        if (skillsCache.length === 0 && Date.now() - lastSkillsLoad > 10000) {
            loadSkills().then(() => updateAutocomplete());
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

        // Once the query contains a space the command is finished — stop suggesting
        if (query.includes(' ')) {
            autocomplete.classList.add('hidden');
            return;
        }

        let matches = [];

        if (isSkill) {
            ensureSkillsLoaded();
            let pool = skillsCache.filter(s => s.id.toLowerCase().startsWith(query));
            if (pool.length === 0) {
                pool = skillsCache.filter(s => s.id.toLowerCase().includes(query));
            }
            matches = pool.map(s => ({
                type: 'skill',
                id: s.id,
                display: '/' + s.id,
                desc: s.description
            }));
        } else {
            let pool = filesCache.filter(f => f.toLowerCase().startsWith(query));
            if (pool.length === 0) {
                pool = filesCache.filter(f => f.toLowerCase().includes(query));
            }
            matches = pool.map(f => ({
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
        const acOpen = !autocomplete.classList.contains('hidden');

        // Autocomplete selection takes priority: Enter/Tab accepts, it must NOT send the message
        if ((e.key === 'Enter' || e.key === 'Tab') && acOpen) {
            const selected = autocomplete.querySelector('.selected') || autocomplete.querySelector('.autocomplete-item');
            if (selected) {
                e.preventDefault();
                const type = selected.dataset.type;
                const id = selected.dataset.id;

                const textBeforeCursor = chatInput.value.slice(0, chatInput.selectionStart);
                const slashIdx = textBeforeCursor.lastIndexOf('/');
                const atIdx = textBeforeCursor.lastIndexOf('@');
                const triggerIdx = Math.max(slashIdx, atIdx);

                insertCommand(type, id, triggerIdx);
                return;
            }
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
            return;
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
        if (e.key === 'Escape') autocomplete.classList.add('hidden');
    });

    chatInput.addEventListener('input', updateAutocomplete);

    // Auto-grow the composer with content (up to ~8 lines)
    function autoGrow() {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 200) + 'px';
    }
    chatInput.addEventListener('input', autoGrow);
    autoGrow();

    chatInput.addEventListener('blur', () => setTimeout(() => autocomplete.classList.add('hidden'), 200));

    // Keep focus in the input when clicking a suggestion so blur doesn't hide the list mid-click
    autocomplete.addEventListener('mousedown', (e) => e.preventDefault());

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
        await loadProjects();
        await loadConversations();
        // If the server-picked conversation isn't in the active project, jump to one that is
        const inProject = document.querySelector('#convList li[data-id="' + activeConvId + '"]');
        if (!inProject) {
            const firstId = convSelector.value ? parseInt(convSelector.value) : null;
            if (firstId) {
                await switchConversation(firstId);
                return;
            }
        }
        if (activeConvId) await loadMessages();
        if (window.refreshSidebar) window.refreshSidebar(activeConvId);
    })();
})();
