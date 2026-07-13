<?php
session_start();
require_once __DIR__ . '/lib/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SkillApp</title>
    <script>if(localStorage.getItem("daynight-theme")==="carbon"){document.documentElement.classList.add("carbon");}</script>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="app-container">
        <nav class="top-nav">
            <div class="nav-container">
                <div class="nav-left">
                    <a href="index.php" class="logo">
                        <div class="logo-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        SkillApp
                    </a>
                    <div class="nav-menu">
                        <a href="index.php" class="nav-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7" rx="1"/>
                                <rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/>
                                <rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                            Chat
                        </a>
                        <a href="settings.php" class="nav-link active">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Settings
                        </a>
                    </div>
                </div>
                <div class="nav-right">
                    <div class="theme-toggle">
                        <button class="theme-btn theme-btn-snow active" onclick="setTheme('snow')" title="Snow Edition">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1" x2="12" y2="3"/>
                                <line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/>
                                <line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                        </button>
                        <button class="theme-btn theme-btn-carbon" onclick="setTheme('carbon')" title="Carbon Edition">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </button>
                    </div>
                    <button class="user-menu" onclick="window.location.href='logout.php'">
                        <div class="user-avatar"><?= strtoupper(substr(current_username(), 0, 1)) ?></div>
                        <span class="user-name"><?= htmlspecialchars(current_username()) ?></span>
                    </button>
                </div>
            </div>
        </nav>

        <main class="main-content">
            <div class="card" style="padding:2rem;">
                <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:0.5rem;">My API Keys</h1>
                <p style="color:var(--text-secondary);margin-bottom:1.5rem;">
                    Add your own LLM API key here. The key you <strong>Activate</strong> is used for your chats.
                    Without an active key you cannot chat — every user brings their own key.
                </p>

                <div id="keyList" style="margin-bottom:2rem"></div>

                <h2 style="font-size:1.125rem;font-weight:600;margin-bottom:1rem;">Add New Key</h2>
                <form id="keyForm" class="key-form">
                    <div class="form-group">
                        <label class="form-label">Label</label>
                        <input type="text" name="label" class="form-input" required placeholder="e.g. My Groq Key">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Provider</label>
                        <select name="provider" class="form-input">
                            <option value="openai-compatible">OpenAI/Groq (OpenAI Compatible)</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Base URL</label>
                        <input type="url" name="base_url" class="form-input" required placeholder="e.g. https://api.groq.com/openai/v1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-input" required placeholder="e.g. llama-3.3-70b-versatile">
                    </div>
                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="password" name="api_key" class="form-input" required placeholder="sk-...">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:auto;padding:0.75rem 2rem;align-self:flex-start">Add Key</button>
                </form>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js"></script>
    <script>
    (async function() {
        const keyList = document.getElementById('keyList');
        const form = document.getElementById('keyForm');
        const API = 'api/user_keys.php';

        let csrfToken = '';
        try {
            const r = await fetch('api/csrf_token.php');
            const d = await r.json();
            csrfToken = d.token;
        } catch(e) {}

        async function loadKeys() {
            const res = await fetch(API);
            const keys = await res.json();
            keyList.innerHTML = '';

            if (!Array.isArray(keys) || keys.length === 0) {
                keyList.innerHTML = '<p style="color:var(--text-secondary)">You have no API keys yet. Add one below.</p>';
                return;
            }

            const table = document.createElement('table');
            table.className = 'admin-table';
            table.innerHTML = `
                <thead><tr>
                    <th>Active</th><th>Label</th><th>Base URL</th><th>Model</th><th>Created</th><th></th>
                </tr></thead><tbody></tbody>
            `;
            const tbody = table.querySelector('tbody');

            for (const k of keys) {
                const tr = document.createElement('tr');
                tr.className = k.is_active == 1 ? 'active-key' : '';
                tr.innerHTML = `
                    <td>${k.is_active == 1 ? '✓' : ''}</td>
                    <td>${esc(k.label)}</td>
                    <td style="font-size:0.8125rem;color:var(--text-secondary)">${esc(k.base_url)}</td>
                    <td>${esc(k.model)}</td>
                    <td style="font-size:0.8125rem;color:var(--text-secondary)">${k.created_at || ''}</td>
                    <td>
                        ${k.is_active == 0 ? `<button class="btn-sm activate-btn" data-id="${k.id}" style="width:auto">Activate</button>` : ''}
                        <button class="btn-sm delete-btn" data-id="${k.id}" style="width:auto;background:rgba(239,68,68,0.1);color:var(--danger);border-color:transparent">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            }

            keyList.appendChild(table);

            table.querySelectorAll('.activate-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    await fetch(API, {
                        method: 'PUT',
                        headers: {'Content-Type':'application/json','X-CSRF-Token':csrfToken},
                        body: JSON.stringify({action:'activate', id: parseInt(btn.dataset.id)})
                    });
                    await loadKeys();
                });
            });

            table.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('Delete this API key?')) return;
                    await fetch(API + '?id=' + btn.dataset.id, {
                        method: 'DELETE',
                        headers: {'X-CSRF-Token':csrfToken}
                    });
                    await loadKeys();
                });
            });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const data = {
                label: fd.get('label'),
                provider: fd.get('provider'),
                base_url: fd.get('base_url'),
                model: fd.get('model'),
                api_key: fd.get('api_key'),
            };

            const res = await fetch(API, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-Token':csrfToken},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                form.reset();
                await loadKeys();
            } else {
                alert(result.error || 'Failed to add key');
            }
        });

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s || '';
            return d.innerHTML;
        }

        await loadKeys();
    })();
    </script>
</body>
</html>
