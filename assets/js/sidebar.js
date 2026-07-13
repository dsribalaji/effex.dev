(function() {
    'use strict';

    let currentConvId = null;

    /* ── Public: called when conversation changes or after stream ── */
    window.refreshSidebar = function(convId) {
        currentConvId = convId;
        loadArtifacts();
        loadUploads();
        if (window.refreshFilesCache) window.refreshFilesCache();
    };

    async function loadArtifacts() {
        const list = document.getElementById('artifactList');
        if (!list || !currentConvId) return;

        try {
            const res = await fetch('api/artifacts.php?action=list&conversation_id=' + currentConvId);
            const items = await res.json();
            renderArtifactTree(list, items);
        } catch (e) {
            list.innerHTML = '<li class="empty-msg">Error loading artifacts</li>';
        }
    }

    async function loadUploads() {
        const list = document.getElementById('uploadList');
        if (!list || !currentConvId) return;

        try {
            const res = await fetch('api/uploads.php?action=list&conversation_id=' + currentConvId);
            const items = await res.json();
            renderList(list, items, 'upload');
        } catch (e) {
            list.innerHTML = '<li class="empty-msg">Error loading uploads</li>';
        }
    }

    /* ── Artifact tree rendering (grouped by folder) ── */
    function renderArtifactTree(ul, items) {
        if (!items || items.length === 0) {
            ul.innerHTML = '<li class="empty-msg">No artifacts yet</li>';
            return;
        }

        // Group by folder
        const folders = {};
        items.forEach(item => {
            const filename = item.filename;
            const parts = filename.split('/');
            if (parts.length > 1) {
                const folder = parts.slice(0, -1).join('/');
                const file = parts[parts.length - 1];
                if (!folders[folder]) folders[folder] = [];
                folders[folder].push({...item, displayName: file});
            } else {
                if (!folders['root']) folders['root'] = [];
                folders['root'].push({...item, displayName: filename});
            }
        });

        ul.innerHTML = '';
        
        // Sort folders alphabetically, but put 'root' first
        const sortedFolders = Object.keys(folders).sort((a, b) => {
            if (a === 'root') return -1;
            if (b === 'root') return 1;
            return a.localeCompare(b);
        });

        sortedFolders.forEach(folder => {
            const folderFiles = folders[folder];
            
            if (folder !== 'root') {
                // Create folder header
                const folderLi = document.createElement('li');
                folderLi.className = 'artifact-folder';
                folderLi.innerHTML = `
                    <span class="folder-header">
                        <span class="folder-icon">📁</span>
                        <span class="folder-name">${escapeHtml(folder)}</span>
                        <span class="folder-count">(${folderFiles.length})</span>
                    </span>
                    <ul class="folder-files"></ul>
                `;
                const filesUl = folderLi.querySelector('.folder-files');
                folderFiles.forEach(file => renderFileItem(filesUl, file, 'artifact'));
                ul.appendChild(folderLi);
            } else {
                // Root files - render directly
                folderFiles.forEach(file => renderFileItem(ul, file, 'artifact'));
            }
        });
    }

    function renderFileItem(ul, item, type) {
        const li = document.createElement('li');
        li.className = 'file-item';

        const icon = typeIcon(item.file_type || item.mime_type);
        const name = item.displayName || item.filename;

        li.innerHTML = `
            <span class="file-icon">${icon}</span>
            <span class="file-name" title="${escapeHtml(name)}">${escapeHtml(name)}</span>
            <span class="file-size">${formatSize(item.size_bytes)}</span>
            <span class="file-actions">
                <button class="action-btn preview-btn" title="Preview">👁</button>
                <button class="action-btn download-btn" title="Download">↓</button>
                <button class="action-btn delete-btn" title="Delete">✕</button>
            </span>
        `;

        const previewBtn = li.querySelector('.preview-btn');
        const downloadBtn = li.querySelector('.download-btn');
        const deleteBtn = li.querySelector('.delete-btn');

        if (type === 'artifact') {
            previewBtn.addEventListener('click', () => openPreview(item.id));
            downloadBtn.addEventListener('click', () => {
                window.location.href = 'api/artifacts.php?action=download&id=' + item.id;
            });
            deleteBtn.addEventListener('click', () => deleteArtifact(item.id, item.displayName || item.filename));
        } else {
            previewBtn.addEventListener('click', () => openUploadPreview(item.id));
            downloadBtn.addEventListener('click', () => {
                window.location.href = 'api/uploads.php?action=download&id=' + item.id;
            });
            deleteBtn.addEventListener('click', () => deleteUpload(item.id, item.displayName || item.filename));
        }

        ul.appendChild(li);
    }

    function renderList(ul, items, type) {
        if (!items || items.length === 0) {
            ul.innerHTML = '<li class="empty-msg">No ' + (type === 'artifact' ? 'artifacts' : 'uploads') + ' yet</li>';
            return;
        }

        ul.innerHTML = '';
        items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'file-item';

            const icon = typeIcon(item.file_type || item.mime_type);
            const name = item.filename;

            li.innerHTML = `
                <span class="file-icon">${icon}</span>
                <span class="file-name" title="${escapeHtml(name)}">${escapeHtml(name)}</span>
                <span class="file-size">${formatSize(item.size_bytes)}</span>
                <span class="file-actions">
                    <button class="action-btn preview-btn" title="Preview">👁</button>
                    <button class="action-btn download-btn" title="Download">↓</button>
                    <button class="action-btn delete-btn" title="Delete">✕</button>
                </span>
            `;

            const previewBtn = li.querySelector('.preview-btn');
            const downloadBtn = li.querySelector('.download-btn');
            const deleteBtn = li.querySelector('.delete-btn');

            if (type === 'artifact') {
                previewBtn.addEventListener('click', () => openPreview(item.id));
                downloadBtn.addEventListener('click', () => {
                    window.location.href = 'api/artifacts.php?action=download&id=' + item.id;
                });
                deleteBtn.addEventListener('click', () => deleteArtifact(item.id, item.filename));
            } else {
                previewBtn.addEventListener('click', () => openUploadPreview(item.id));
                downloadBtn.addEventListener('click', () => {
                    window.location.href = 'api/uploads.php?action=download&id=' + item.id;
                });
                deleteBtn.addEventListener('click', () => deleteUpload(item.id, item.filename));
            }

            ul.appendChild(li);
        });
    }

    /* ── Preview panel (VS Code-style split editor: expands beside chat, ✕ collapses) ── */

    const previewPanel = document.getElementById('previewPanel');
    const previewTitle = document.getElementById('previewPanelTitle');
    const previewIcon = document.getElementById('previewPanelIcon');
    const previewBody = document.getElementById('previewPanelBody');
    const previewDownload = document.getElementById('previewPanelDownload');
    const previewCloseBtn = document.getElementById('previewPanelClose');

    let currentPreviewKey = null;

    if (previewCloseBtn) previewCloseBtn.addEventListener('click', closePreview);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closePreview();
    });

    function openPreview(id) {
        showPreviewPanel('api/artifacts.php?action=preview&id=' + id,
                         'api/artifacts.php?action=download&id=' + id,
                         'artifact:' + id);
    }

    function openUploadPreview(id) {
        showPreviewPanel('api/uploads.php?action=preview&id=' + id,
                         'api/uploads.php?action=download&id=' + id,
                         'upload:' + id);
    }

    async function showPreviewPanel(url, downloadUrl, key) {
        if (!previewPanel) return;

        // Clicking the same file again toggles the panel closed, like re-clicking a tab
        if (previewPanel.classList.contains('open') && currentPreviewKey === key) {
            closePreview();
            return;
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.error) { alert(data.error); return; }

            currentPreviewKey = key;
            previewTitle.textContent = data.name || 'Preview';
            previewTitle.title = data.name || '';
            previewIcon.textContent = typeIcon(data.type === 'html' ? 'md' : data.type);

            if (data.type === 'html') {
                previewBody.innerHTML = data.html;
            } else if (data.type === 'pdf') {
                previewBody.innerHTML = '<iframe src="' + escapeHtml(data.url) + '" style="width:100%;height:100%;border:none;"></iframe>';
            } else if (data.type === 'image') {
                previewBody.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><img src="' + escapeHtml(data.url) + '" style="max-width:100%;max-height:100%;object-fit:contain;"></div>';
            } else if (data.type === 'text') {
                previewBody.innerHTML = '<pre>' + escapeHtml(data.content || '') + '</pre>';
            }

            previewDownload.href = downloadUrl || data.url || '#';
            previewDownload.style.display = (downloadUrl || data.url) ? 'inline-flex' : 'none';

            previewPanel.classList.add('open');
            previewBody.scrollTop = 0;
        } catch (e) {
            console.error('Preview failed', e);
        }
    }

    function closePreview() {
        if (previewPanel) previewPanel.classList.remove('open');
        currentPreviewKey = null;
    }

    /* ── Delete ── */

    function csrfToken() {
        return window.__csrfToken || '';
    }

    async function deleteArtifact(id, name) {
        if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
        try {
            const res = await fetch('api/artifacts.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (data.success) loadArtifacts();
        } catch (e) {
            console.error('Delete failed', e);
        }
    }

    async function deleteUpload(id, name) {
        if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
        try {
            const res = await fetch('api/uploads.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (data.success) loadUploads();
        } catch (e) {
            console.error('Delete failed', e);
        }
    }

    /* ── Helpers ── */

    function typeIcon(type) {
        const t = (type || '').toLowerCase();
        if (t === 'md') return '📝';
        if (t === 'pdf') return '📄';
        if (t === 'docx') return '📃';
        if (t === 'txt') return '📄';
        if (t.includes('pdf')) return '📄';
        if (t.includes('word') || t.includes('docx')) return '📃';
        return '📎';
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
