(function() {
    'use strict';

    const uploadBtn = document.getElementById('uploadBtn');
    const uploadInput = document.getElementById('uploadInput');

    if (!uploadBtn || !uploadInput) return;

    uploadBtn.addEventListener('click', () => uploadInput.click());

    // "+" button inside the chat composer opens the same file picker
    const chatAttachBtn = document.getElementById('chatAttachBtn');
    if (chatAttachBtn) chatAttachBtn.addEventListener('click', () => uploadInput.click());

    uploadInput.addEventListener('change', async () => {
        const files = uploadInput.files;
        if (!files || files.length === 0) return;

        const convId = typeof activeConvId !== 'undefined' ? activeConvId :
                       (window.__activeConvId || null);

        if (!convId) {
            alert('No active conversation. Create a chat first.');
            return;
        }

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('conversation_id', convId);
            if (window.__csrfToken) formData.append('_csrf_token', window.__csrfToken);

            try {
                const res = await fetch('api/uploads.php?action=upload', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': window.__csrfToken || '' },
                    body: formData,
                });
                const data = await res.json();
                if (data.error) {
                    alert('Upload failed for "' + file.name + '": ' + data.error);
                }
            } catch (e) {
                alert('Upload error for "' + file.name + '": ' + e.message);
            }
        }

        uploadInput.value = '';
        if (window.refreshSidebar) window.refreshSidebar(convId);
    });
})();
