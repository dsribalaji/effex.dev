<?php
session_start();
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_login();

$username = current_username();
$pdo = db();

$stmt = $pdo->prepare('SELECT id, title, created_at FROM conversations WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
$stmt->execute([current_user_id()]);
$activeConv = $stmt->fetch();

if (!$activeConv) {
    $stmt = $pdo->prepare('INSERT INTO conversations (user_id, title) VALUES (?, ?)');
    $stmt->execute([current_user_id(), 'New Chat']);
    $activeConv = ['id' => (int)$pdo->lastInsertId(), 'title' => 'New Chat'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillApp</title>
    <script>if(localStorage.getItem("daynight-theme")==="carbon"){document.documentElement.classList.add("carbon");}</script>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
</head>
<body>
    <div id="app">
        <nav class="top-nav" style="position:static;flex-shrink:0">
            <div class="nav-container">
                <div class="nav-left">
                    <button id="sidebarToggle" class="mobile-menu-btn" title="Toggle sidebar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <a href="index.php" class="logo">
                        <div class="logo-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        SkillApp
                    </a>
                    <div class="nav-divider"></div>
                    <select id="convSelector" class="conv-select"></select>
                    <button id="clearChatBtn" class="btn btn-ghost nav-icon-btn" title="Clear chat">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
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
                    <div class="user-menu">
                        <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
                        <span class="user-name"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <a href="settings.php" class="btn-logout" title="Settings">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                    </a>
                    <a href="logout.php" class="btn-logout" title="Logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </a>
                </div>
            </div>
        </nav>

        <div id="main">
            <aside id="sidebar">
                <div class="sidebar-section">
                    <h3>Project</h3>
                    <div class="project-row">
                        <select id="projectSelector" class="conv-select" style="min-width:0;flex:1"></select>
                        <button id="newProjectBtn" class="nav-icon-btn" title="New project">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="sidebar-section">
                    <h3>Conversations</h3>
                    <ul id="convList"></ul>
                    <button id="newConvBtn" class="btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
                        </svg>
                        New Chat
                    </button>
                </div>
                <div class="sidebar-section">
                    <h3>Artifacts</h3>
                    <ul id="artifactList"><li class="empty-msg">No artifacts yet</li></ul>
                </div>
                <div class="sidebar-section">
                    <h3>Attachments</h3>
                    <div id="uploadArea">
                        <input type="file" id="uploadInput" multiple hidden>
                        <button id="uploadBtn" class="btn-sm">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                            Attach files
                        </button>
                    </div>
                    <ul id="uploadList"><li class="empty-msg">No uploads yet</li></ul>
                </div>
                <div class="sidebar-section sidebar-footer">
                    <button id="exportProjectBtn" class="btn-sm" title="Download all project artifacts as zip">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export project (.zip)
                    </button>
                </div>
            </aside>

            <div id="previewPanel">
                <div class="preview-panel-tab">
                    <span class="preview-panel-icon" id="previewPanelIcon">📄</span>
                    <span class="preview-panel-title" id="previewPanelTitle"></span>
                    <button class="preview-panel-btn" id="previewPanelToggle" title="Toggle preview / code" style="display:none">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                    <button class="preview-panel-btn" id="previewPanelCopy" title="Copy content">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                    <a class="preview-panel-btn" id="previewPanelDownload" title="Download" target="_blank">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                    </a>
                    <button class="preview-panel-btn" id="previewPanelClose" title="Close (Esc)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
                            <path d="M18 6 6 18"/>
                            <path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="preview-body" id="previewPanelBody"></div>
            </div>

            <div id="chatArea">
                <div id="messageList"></div>
                <div id="inputArea">
                    <div class="input-wrapper composer">
                        <button id="chatAttachBtn" class="composer-btn" title="Attach files" type="button">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                        <textarea id="chatInput" rows="1" placeholder="Type / for skills, or ask a question..."></textarea>
                        <button id="sendBtn" class="composer-send" title="Send" type="button">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                                <line x1="12" y1="19" x2="12" y2="5"/>
                                <polyline points="5 12 12 5 19 12"/>
                            </svg>
                        </button>
                        <div id="autocomplete" class="autocomplete-dropdown hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/theme.js?v=<?= filemtime(__DIR__ . '/assets/js/theme.js') ?>"></script>
    <script>
    const CONV_ID = <?= json_encode((int)$activeConv['id']) ?>;
    </script>
    <script src="assets/js/chat.js?v=<?= filemtime(__DIR__ . '/assets/js/chat.js') ?>"></script>
    <script src="assets/js/sidebar.js?v=<?= filemtime(__DIR__ . '/assets/js/sidebar.js') ?>"></script>
    <script src="assets/js/upload.js?v=<?= filemtime(__DIR__ . '/assets/js/upload.js') ?>"></script>
</body>
</html>
