(function() {
    'use strict';

    let currentConvId = null;
    let previewModal = null;

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
            renderList(list, items, 'artifact');
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

    /* ── Preview modal ── */

    function openPreview(id) {
        showPreviewModal('api/artifacts.php?action=preview&id=' + id);
    }

    function openUploadPreview(id) {
        showPreviewModal('api/uploads.php?action=preview&id=' + id);
    }

    async function showPreviewModal(url) {
        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.error) { alert(data.error); return; }

            if (!previewModal) {
                previewModal = document.createElement('div');
                previewModal.className = 'preview-overlay';
                previewModal.innerHTML = `
                    <div class="preview-modal">
                        <div class="preview-header">
                            <span class="preview-title"></span>
                            <a class="preview-download" target="_blank">Download</a>
                            <button class="preview-close">✕</button>
                        </div>
                        <div class="preview-body"></div>
                    </div>
                `;
                previewModal.addEventListener('click', (e) => {
                    if (e.target === previewModal) closePreview();
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closePreview();
                });
                previewModal.querySelector('.preview-close').addEventListener('click', closePreview);
                document.body.appendChild(previewModal);
            }

            previewModal.querySelector('.preview-title').textContent = data.name || 'Preview';
            const body = previewModal.querySelector('.preview-body');

            if (data.type === 'html') {
                body.innerHTML = data.html;
            } else if (data.type === 'pdf') {
                body.innerHTML = '<iframe src="' + escapeHtml(data.url) + '" style="width:100%;height:100%;border:none;"></iframe>';
            } else if (data.type === 'image') {
                body.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;"><img src="' + escapeHtml(data.url) + '" style="max-width:100%;max-height:100%;object-fit:contain;"></div>';
            } else if (data.type === 'text') {
                body.innerHTML = '<pre>' + escapeHtml(data.content || '') + '</pre>';
            }

            const dlLink = previewModal.querySelector('.preview-download');
            if (data.url) {
                dlLink.href = data.url;
                dlLink.style.display = 'inline';
            } else {
                dlLink.style.display = 'none';
            }

            previewModal.classList.add('visible');
        } catch (e) {
            console.error('Preview failed', e);
        }
    }

    function closePreview() {
        if (previewModal) previewModal.classList.remove('visible');
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
