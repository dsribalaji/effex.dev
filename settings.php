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
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <style>
        .admin-tabs { display:flex; gap:0; margin-bottom:1.5rem; border-bottom:2px solid var(--border-color); }
        .admin-tab { padding:0.75rem 1.5rem; cursor:pointer; border:none; background:none; font-size:0.9rem; color:var(--text-secondary); border-bottom:2px solid transparent; margin-bottom:-2px; }
        .admin-tab.active { color:var(--accent); border-bottom-color:var(--accent); font-weight:600; }
        .admin-table { width:100%; border-collapse:collapse; font-size:0.875rem; }
        .admin-table th, .admin-table td { padding:0.6rem 0.8rem; text-align:left; border-bottom:1px solid var(--border-color); }
        .admin-table th { font-weight:600; color:var(--text-secondary); }
        .admin-table select { padding:0.3rem 0.5rem; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); font-size:0.8rem; }
        .btn-sm { padding:0.3rem 0.6rem; font-size:0.75rem; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); cursor:pointer; }
        .btn-sm:hover { background:var(--border-color); }
        .btn-sm.btn-danger { border-color:var(--danger); color:var(--danger); }
        .btn-sm.btn-danger:hover { background:var(--danger); color:#fff; }
        .btn-sm.btn-success { border-color:var(--success, #22c55e); color:var(--success, #22c55e); }
        .btn-sm.btn-success:hover { background:var(--success, #22c55e); color:#fff; }
        .admin-role-badge { display:inline-block; padding:0.15rem 0.5rem; border-radius:999px; font-size:0.75rem; background:var(--accent); color:#fff; }
        .admin-section-hidden { display:none !important; }
        #addRoleForm { margin-top:1rem; padding:1rem; border:1px solid var(--border-color); border-radius:8px; display:none; }
        #addRoleForm input { width:100%; margin-bottom:0.5rem; }
    </style>
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
                        <label class="form-label">Provider</label>
                        <select name="preset" id="presetSelect" class="form-input">
                            <option value="openrouter">OpenRouter (aggregator — 400+ models)</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="groq">Groq</option>
                            <option value="gemini">Google Gemini</option>
                            <option value="mistral">Mistral AI</option>
                            <option value="deepseek">DeepSeek</option>
                            <option value="together">Together AI</option>
                            <option value="fireworks">Fireworks AI</option>
                            <option value="xai">xAI (Grok)</option>
                            <option value="cerebras">Cerebras</option>
                            <option value="perplexity">Perplexity</option>
                            <option value="ollama">Ollama (local)</option>
                            <option value="lmstudio">LM Studio (local)</option>
                            <option value="custom">Custom (OpenAI compatible)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Base URL</label>
                        <input type="url" name="base_url" id="baseUrlInput" class="form-input" required
                               value="https://openrouter.ai/api/v1" placeholder="e.g. https://api.groq.com/openai/v1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="password" name="api_key" id="apiKeyInput" class="form-input" required placeholder="sk-...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <input type="text" name="model" id="modelInput" class="form-input" list="modelCatalog" required
                                   placeholder="Pick from the catalogue or type a model id" style="flex:1">
                            <button type="button" id="browseModelsBtn" class="btn-sm" style="width:auto;margin:0;white-space:nowrap">Browse models</button>
                        </div>
                        <datalist id="modelCatalog"></datalist>
                        <p id="modelHint" style="font-size:0.75rem;color:var(--text-secondary);margin-top:0.375rem;"></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Label</label>
                        <input type="text" name="label" id="labelInput" class="form-input" required placeholder="e.g. My OpenRouter Key">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:auto;padding:0.75rem 2rem;align-self:flex-start">Add Key</button>
                </form>
            </div>
            <!-- Admin Section (shown only to admins) -->
            <div id="adminSection" style="display:none; margin-top: 3rem; border-top: 1px solid var(--border-color); padding-top: 2rem;">
              <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:1.5rem;">🔒 Admin Panel</h2>
              <!-- Tabs -->
              <div class="admin-tabs">
                <button class="admin-tab active" data-tab="users">Users</button>
                <button class="admin-tab" data-tab="roles">Roles</button>
              </div>
              <!-- Users panel -->
              <div id="adminUsers" class="admin-panel">
                <table class="admin-table">
                  <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Admin</th><th>Actions</th></tr></thead>
                  <tbody id="adminUsersBody"></tbody>
                </table>
              </div>
              <!-- Roles panel -->
              <div id="adminRoles" class="admin-panel" style="display:none">
                <table class="admin-table">
                  <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
                  <tbody id="adminRolesBody"></tbody>
                </table>
                <button id="addRoleBtn" class="btn btn-primary" style="margin-top:1rem">+ Add Role</button>
                <div id="addRoleForm">
                  <input type="text" id="newRoleName" class="form-input" placeholder="Role name (e.g. Developer)" style="margin-bottom:0.5rem">
                  <input type="text" id="newRoleSlug" class="form-input" placeholder="Slug (e.g. developer)" style="margin-bottom:0.5rem">
                  <input type="text" id="newRoleDesc" class="form-input" placeholder="Description" style="margin-bottom:0.5rem">
                  <input type="text" id="newRoleGithubPath" class="form-input" placeholder="GitHub path (e.g. developer/.copilot/skills)" style="margin-bottom:0.5rem">
                  <button id="saveNewRoleBtn" class="btn btn-primary btn-sm">Save Role</button>
                  <button id="cancelNewRoleBtn" class="btn-sm" style="margin-left:0.5rem">Cancel</button>
                </div>
              </div>
            </div>
        </main>
    </div>

    <script src="assets/js/theme.js?v=<?= filemtime(__DIR__ . '/assets/js/theme.js') ?>"></script>
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
            keyList.innerHTML = '<div class="loading-state"><span class="spinner"></span> Loading your keys...</div>';
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

        /* ── Provider presets + model catalogue (OpenRouter-style) ── */
        const PRESETS = {
            openrouter: { base_url: 'https://openrouter.ai/api/v1', provider: 'openai-compatible', catalog: 'openrouter' },
            openai:     { base_url: 'https://api.openai.com/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            anthropic:  { base_url: 'https://api.anthropic.com/v1', provider: 'anthropic', catalog: 'anthropic' },
            groq:       { base_url: 'https://api.groq.com/openai/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            gemini:     { base_url: 'https://generativelanguage.googleapis.com/v1beta/openai', provider: 'openai-compatible', catalog: 'openai-compatible' },
            mistral:    { base_url: 'https://api.mistral.ai/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            deepseek:   { base_url: 'https://api.deepseek.com/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            together:   { base_url: 'https://api.together.xyz/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            fireworks:  { base_url: 'https://api.fireworks.ai/inference/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            xai:        { base_url: 'https://api.x.ai/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            cerebras:   { base_url: 'https://api.cerebras.ai/v1', provider: 'openai-compatible', catalog: 'openai-compatible' },
            perplexity: { base_url: 'https://api.perplexity.ai', provider: 'openai-compatible', catalog: 'openai-compatible' },
            ollama:     { base_url: 'http://localhost:11434/v1', provider: 'openai-compatible', catalog: 'openai-compatible', nokey: true },
            lmstudio:   { base_url: 'http://localhost:1234/v1', provider: 'openai-compatible', catalog: 'openai-compatible', nokey: true },
            custom:     { base_url: '', provider: 'openai-compatible', catalog: 'openai-compatible' },
        };

        const presetSelect = document.getElementById('presetSelect');
        const baseUrlInput = document.getElementById('baseUrlInput');
        const apiKeyInput = document.getElementById('apiKeyInput');
        const modelInput = document.getElementById('modelInput');
        const modelCatalog = document.getElementById('modelCatalog');
        const modelHint = document.getElementById('modelHint');
        const browseBtn = document.getElementById('browseModelsBtn');

        presetSelect.addEventListener('change', () => {
            const p = PRESETS[presetSelect.value];
            baseUrlInput.value = p.base_url;
            // Local servers don't need a key; everyone else does
            apiKeyInput.required = !p.nokey;
            apiKeyInput.placeholder = p.nokey ? '(not needed for local servers)' : 'sk-...';
            modelCatalog.innerHTML = '';
            modelHint.textContent = '';
        });

        browseBtn.addEventListener('click', async () => {
            const p = PRESETS[presetSelect.value];
            browseBtn.disabled = true;
            browseBtn.innerHTML = '<span class="spinner" style="width:12px;height:12px;vertical-align:middle"></span> Loading...';
            modelHint.textContent = '';
            try {
                const res = await fetch('api/models_catalog.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-Token':csrfToken},
                    body: JSON.stringify({
                        provider: p.catalog,
                        base_url: baseUrlInput.value.trim(),
                        api_key: apiKeyInput.value.trim(),
                    })
                });
                const data = await res.json();
                if (data.error) {
                    modelHint.textContent = data.error;
                } else {
                    modelCatalog.innerHTML = '';
                    for (const m of data.models) {
                        const opt = document.createElement('option');
                        opt.value = m.id;
                        if (m.name && m.name !== m.id) opt.label = m.name;
                        modelCatalog.appendChild(opt);
                    }
                    modelHint.textContent = data.models.length + ' models loaded — start typing in the Model field to search the catalogue.';
                    modelInput.focus();
                }
            } catch (e) {
                modelHint.textContent = 'Failed to load models: ' + e.message;
            }
            browseBtn.disabled = false;
            browseBtn.textContent = 'Browse models';
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const data = {
                label: fd.get('label'),
                provider: PRESETS[presetSelect.value].provider,
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
    <script>
    (async function() {
      const ADMIN_API = "api/admin.php";
      const csrfRes = await fetch("api/csrf_token.php");
      const csrfData = await csrfRes.json();
      const csrfToken = csrfData.token;

      let isAdmin = false;
      try {
        const res = await fetch(ADMIN_API + "?action=roles");
        if (res.ok) isAdmin = true;
      } catch (e) {}

      if (!isAdmin) return;

      const adminSection = document.getElementById("adminSection");
      if (adminSection) adminSection.style.display = "block";

      const adminUsersBody = document.getElementById("adminUsersBody");
      const adminRolesBody = document.getElementById("adminRolesBody");

      document.querySelectorAll(".admin-tab").forEach(function(tab) {
        tab.addEventListener("click", function() {
          document.querySelectorAll(".admin-tab").forEach(function(t) { t.classList.remove("active"); });
          tab.classList.add("active");
          document.querySelectorAll(".admin-panel").forEach(function(p) { p.style.display = "none"; });
          var target = document.getElementById("admin" + tab.dataset.tab.charAt(0).toUpperCase() + tab.dataset.tab.slice(1));
          if (target) target.style.display = "block";
        });
      });

      async function loadUsers() {
        var res = await fetch(ADMIN_API + "?action=users");
        var users = await res.json();
        adminUsersBody.innerHTML = users.map(function(u) {
          var isAdm = u.is_admin == 1 || u.is_admin === "1";
          return "<tr>"
            + "<td>" + u.id + "</td>"
            + "<td>" + esc(u.username) + "</td>"
            + "<td>" + esc(u.email) + "</td>"
            + "<td>" + roleDropdown(u.id, u.role_id) + "</td>"
            + "<td>" + (isAdm ? "\ud83d\udc51 Yes" : "\u2014") + "</td>"
            + "<td>"
            + "<button class=\"btn-sm " + (isAdm ? "btn-danger\" data-action=\"demote\"" : "btn-success\" data-action=\"promote\"") + "\" data-user-id=\"" + u.id + "\">"
            + (isAdm ? "Demote" : "Make Admin")
            + "</button>"
            + "</td>"
            + "</tr>";
        }).join("");
      }

      function roleDropdown(userId, currentRoleId) {
        var opts = "<option value=\"0\">\u2014 None \u2014</option>";
        (window._allRoles || []).forEach(function(r) {
          opts += "<option value=\"" + r.id + "\"" + (r.id == currentRoleId ? " selected" : "") + ">" + esc(r.name) + "</option>";
        });
        return "<select class=\"form-input\" style=\"padding:0.3rem 0.5rem;font-size:0.8rem;\" data-user-id=\"" + userId + "\" onchange=\"updateUserRole(" + userId + ", this.value)\">" + opts + "</select>";
      }

      window.updateUserRole = async function(userId, roleId) {
        await fetch(ADMIN_API, {
          method: "POST",
          headers: {"Content-Type":"application/json","X-CSRF-Token":csrfToken},
          body: JSON.stringify({action:"update_role", user_id: userId, role_id: parseInt(roleId) || null})
        });
      };

      adminUsersBody.addEventListener("click", async function(e) {
        var btn = e.target.closest("[data-user-id]");
        if (!btn) return;
        var userId = btn.dataset.userId;
        await fetch(ADMIN_API, {
          method: "POST",
          headers: {"Content-Type":"application/json","X-CSRF-Token":csrfToken},
          body: JSON.stringify({action:"toggle_admin", user_id: parseInt(userId)})
        });
        await loadUsers();
      });

      async function loadRoles() {
        var res = await fetch(ADMIN_API + "?action=roles");
        var roles = await res.json();
        window._allRoles = roles;
        adminRolesBody.innerHTML = roles.map(function(r) {
          return "<tr>"
            + "<td>" + r.id + "</td>"
            + "<td>" + esc(r.name) + "</td>"
            + "<td>" + esc(r.slug) + "</td>"
            + "<td>" + esc(r.description || "") + "</td>"
            + "<td>" + (r.is_active == 1 || r.is_active === "1" ? "Active" : "Inactive") + "</td>"
            + "<td>"
            + "<button class=\"btn-sm btn-danger\" onclick=\"deleteRole(" + r.id + ")\"" + (r.id <= 1 ? " disabled title=\"Cannot delete default role\"" : "") + ">Delete</button>"
            + "</td>"
            + "</tr>";
        }).join("");
        loadUsers();
      }

      window.deleteRole = async function(roleId) {
        if (!confirm("Delete this role? Users assigned to it will lose their role assignment.")) return;
        await fetch(ADMIN_API, {
          method: "POST",
          headers: {"Content-Type":"application/json","X-CSRF-Token":csrfToken},
          body: JSON.stringify({action:"delete_role", id: roleId})
        });
        await loadRoles();
      };

      document.getElementById("addRoleBtn").addEventListener("click", function() {
        document.getElementById("addRoleForm").style.display = "block";
      });
      document.getElementById("cancelNewRoleBtn").addEventListener("click", function() {
        document.getElementById("addRoleForm").style.display = "none";
      });
      document.getElementById("saveNewRoleBtn").addEventListener("click", async function() {
        var name = document.getElementById("newRoleName").value.trim();
        var slug = document.getElementById("newRoleSlug").value.trim();
        var desc = document.getElementById("newRoleDesc").value.trim();
        var ghPath = document.getElementById("newRoleGithubPath").value.trim() || ".copilot/skills";
        if (!name || !slug) { alert("Name and slug are required"); return; }
        var res = await fetch(ADMIN_API, {
          method: "POST",
          headers: {"Content-Type":"application/json","X-CSRF-Token":csrfToken},
          body: JSON.stringify({action:"add_role", name: name, slug: slug, description: desc, github_path: ghPath})
        });
        var data = await res.json();
        if (data.success) {
          document.getElementById("addRoleForm").style.display = "none";
          document.getElementById("newRoleName").value = "";
          document.getElementById("newRoleSlug").value = "";
          document.getElementById("newRoleDesc").value = "";
          document.getElementById("newRoleGithubPath").value = "";
          await loadRoles();
        } else {
          alert(data.error || "Failed to add role");
        }
      });

      function esc(s) {
        if (s == null) return "";
        var d = document.createElement("div");
        d.textContent = String(s);
        return d.innerHTML;
      }

      await loadRoles();
    })();
    </script>
</body>
</html>
