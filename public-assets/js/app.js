(() => {
    document.documentElement.classList.add('js');

    const wrapSelection = (textarea, before, after = before, placeholder = '') => {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.slice(start, end) || placeholder;
        textarea.setRangeText(before + selected + after, start, end, 'select');
        textarea.focus();
    };

    document.querySelectorAll('[data-markdown-editor]').forEach((textarea) => {
        const tools = document.createElement('div');
        tools.className = 'editor-toolbar';
        [
            ['Bold', () => wrapSelection(textarea, '**', '**', 'bold text')],
            ['Italic', () => wrapSelection(textarea, '*', '*', 'italic text')],
            ['Code', () => wrapSelection(textarea, '`', '`', 'code')],
            ['H2', () => wrapSelection(textarea, '## ', '', 'Heading')],
            ['Link', () => wrapSelection(textarea, '[', '](https://example.com)', 'link text')],
        ].forEach(([label, action]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.addEventListener('click', action);
            tools.appendChild(button);
        });
        textarea.parentNode.insertBefore(tools, textarea);
    });

    document.querySelectorAll('[data-duplicate-title]').forEach((input) => {
        const results = document.querySelector('[data-duplicate-results]');
        if (!(input instanceof HTMLInputElement) || !(results instanceof HTMLElement)) {
            return;
        }

        let timer = 0;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const query = input.value.trim();
            if (query.length < 8) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }

            timer = window.setTimeout(async () => {
                const response = await fetch('/ask/suggest?q=' + encodeURIComponent(query), {headers: {'Accept': 'application/json'}});
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                const items = Array.isArray(data.items) ? data.items : [];
                if (items.length === 0) {
                    results.hidden = true;
                    results.innerHTML = '';
                    return;
                }
                // Rebuild DOM safely — no innerHTML for API-sourced content
                results.innerHTML = '';
                const header = document.createElement('strong');
                header.textContent = 'Possible duplicates';
                results.appendChild(header);
                items.forEach((item) => {
                    const title = String(item.title || '');
                    const slug = String(item.slug || '');
                    const answers = Number(item.answer_count || 0);
                    const a = document.createElement('a');
                    a.href = '/question/' + encodeURIComponent(slug);
                    a.textContent = title;
                    const span = document.createElement('span');
                    span.textContent = answers + ' answers';
                    a.appendChild(span);
                    results.appendChild(a);
                });
                results.hidden = false;
            }, 280);
        });
    });

    let mediaModal = null;
    let mediaImage = null;
    let mediaVideo = null;
    let mediaClose = null;
    let lastMediaFocus = null;

    const closeMediaModal = () => {
        if (!mediaModal || mediaModal.hidden) {
            return;
        }

        mediaModal.hidden = true;
        if (mediaImage) {
            mediaImage.removeAttribute('src');
        }
        if (mediaVideo) {
            mediaVideo.pause();
            mediaVideo.removeAttribute('src');
            mediaVideo.load();
        }
        if (lastMediaFocus && typeof lastMediaFocus.focus === 'function') {
            lastMediaFocus.focus();
        }
    };

    const ensureMediaModal = () => {
        if (mediaModal) {
            return;
        }

        mediaModal = document.createElement('div');
        mediaModal.className = 'media-modal';
        mediaModal.setAttribute('role', 'dialog');
        mediaModal.setAttribute('aria-modal', 'true');
        mediaModal.setAttribute('aria-label', 'Uploaded media preview');
        mediaModal.hidden = true;

        const backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'media-modal-backdrop';
        backdrop.setAttribute('aria-label', 'Close preview');
        backdrop.addEventListener('click', closeMediaModal);

        const frame = document.createElement('div');
        frame.className = 'media-modal-frame';

        mediaClose = document.createElement('button');
        mediaClose.type = 'button';
        mediaClose.className = 'media-modal-close';
        mediaClose.textContent = 'Close';
        mediaClose.addEventListener('click', closeMediaModal);

        mediaImage = document.createElement('img');
        mediaImage.alt = '';
        mediaVideo = document.createElement('video');
        mediaVideo.controls = true;
        mediaVideo.hidden = true;

        frame.appendChild(mediaClose);
        frame.appendChild(mediaImage);
        frame.appendChild(mediaVideo);
        mediaModal.appendChild(backdrop);
        mediaModal.appendChild(frame);
        document.body.appendChild(mediaModal);
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const link = target ? target.closest('a.media-link[data-media-popup]') : null;
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        event.preventDefault();
        ensureMediaModal();
        lastMediaFocus = document.activeElement;

        const thumbnail = link.querySelector('img');
        const linkedVideo = link.querySelector('video');
        const isVideo = link.classList.contains('media-video-link')
            || linkedVideo instanceof HTMLVideoElement
            || /\.(mp4|webm)$/i.test(link.pathname);

        mediaImage.removeAttribute('src');
        mediaVideo.pause();
        mediaVideo.removeAttribute('src');
        mediaVideo.load();
        mediaImage.hidden = isVideo;
        mediaVideo.hidden = !isVideo;
        if (isVideo) {
            mediaVideo.src = link.href;
        } else {
            mediaImage.src = link.href;
            mediaImage.alt = thumbnail ? thumbnail.alt : 'Uploaded media preview';
        }
        mediaModal.hidden = false;
        mediaClose.focus();
    });

    let reportModal = null;
    let reportForm = null;
    let reportReason = null;
    let reportClose = null;
    let lastReportFocus = null;

    const closeReportModal = () => {
        if (!reportModal || reportModal.hidden) {
            return;
        }

        reportModal.hidden = true;
        if (lastReportFocus && typeof lastReportFocus.focus === 'function') {
            lastReportFocus.focus();
        }
    };

    const ensureReportModal = () => {
        if (reportModal) {
            return;
        }

        reportModal = document.createElement('div');
        reportModal.className = 'report-modal';
        reportModal.setAttribute('role', 'dialog');
        reportModal.setAttribute('aria-modal', 'true');
        reportModal.setAttribute('aria-label', 'Report content');
        reportModal.hidden = true;

        const frame = document.createElement('div');
        frame.className = 'report-modal-frame';
        const title = document.createElement('h2');
        title.textContent = 'Report';
        reportForm = document.createElement('form');
        reportForm.method = 'post';
        reportReason = document.createElement('textarea');
        reportReason.name = 'reason';
        reportReason.maxLength = 240;
        reportReason.required = true;
        reportReason.placeholder = 'Briefly describe the problem';
        const csrf = document.createElement('div');
        csrf.dataset.reportCsrf = '1';
        const actions = document.createElement('div');
        actions.className = 'modal-actions';
        const submit = document.createElement('button');
        submit.type = 'submit';
        submit.textContent = 'Send report';
        reportClose = document.createElement('button');
        reportClose.type = 'button';
        reportClose.className = 'button-secondary';
        reportClose.textContent = 'Cancel';
        reportClose.addEventListener('click', closeReportModal);

        actions.appendChild(reportClose);
        actions.appendChild(submit);
        reportForm.appendChild(csrf);
        reportForm.appendChild(reportReason);
        reportForm.appendChild(actions);
        frame.appendChild(title);
        frame.appendChild(reportForm);
        reportModal.appendChild(frame);
        document.body.appendChild(reportModal);
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const button = target ? target.closest('[data-report-open]') : null;
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        event.preventDefault();
        ensureReportModal();
        lastReportFocus = document.activeElement;
        // Validate action is an internal path before assigning (defence-in-depth)
        const reportAction = button.dataset.reportAction || '';
        reportForm.action = /^\/[a-zA-Z0-9\-_/]+$/.test(reportAction) ? reportAction : '';
        // Inject CSRF token via DOM API instead of innerHTML
        const csrfContainer = reportForm.querySelector('[data-report-csrf]');
        if (csrfContainer) {
            // The server embeds the full <input> HTML in the data attribute; parse it safely
            const match = (button.dataset.reportCsrf || '').match(/value="([^"]*)"/);
            if (match) {
                let csrfInput = csrfContainer.querySelector('input[name="_csrf"]');
                if (!csrfInput) {
                    csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_csrf';
                    csrfContainer.appendChild(csrfInput);
                }
                csrfInput.value = match[1];
            }
        }
        reportReason.value = '';
        reportModal.hidden = false;
        reportReason.focus();
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const button = target ? target.closest('[data-quote-comment]') : null;
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const inlineDetailsId = button.dataset.quoteInlineTarget || '';
        const inlineDetails = inlineDetailsId ? document.getElementById(inlineDetailsId) : null;
        if (!(inlineDetails instanceof HTMLDetailsElement)) {
            return;
        }

        inlineDetails.open = true;
        const form = inlineDetails.querySelector('form');
        const textarea = form ? form.querySelector('textarea[name="body"]') : null;
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        event.preventDefault();
        const author = button.dataset.quoteAuthor || 'user';
        const body = button.dataset.quoteBody || '';
        const quote = '> ' + author + ': ' + body.replace(/\s+/g, ' ') + '\n\n';
        const prefix = textarea.value.trim() === '' ? '' : '\n\n';
        const parentInput = form.querySelector('[data-quote-parent-input]');
        const quoteInput = form.querySelector('[data-quote-id-input]');
        const context = form.querySelector('[data-quote-context]');
        const contextText = form.querySelector('[data-quote-context-text]');
        if (parentInput instanceof HTMLInputElement) {
            parentInput.value = button.dataset.quoteParent || '0';
        }
        if (quoteInput instanceof HTMLInputElement) {
            quoteInput.value = button.dataset.quoteParent || '0';
        }
        if (context instanceof HTMLElement) {
            context.hidden = false;
        }
        if (contextText instanceof HTMLElement) {
            contextText.textContent = 'Replying in ' + author + "'s thread";
        }
        textarea.value += prefix + quote;
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        form.scrollIntoView({block: 'center', behavior: 'smooth'});
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const button = target ? target.closest('[data-clear-quote]') : null;
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const form = button.closest('form');
        const parentInput = form ? form.querySelector('[data-quote-parent-input]') : null;
        const quoteInput = form ? form.querySelector('[data-quote-id-input]') : null;
        const context = form ? form.querySelector('[data-quote-context]') : null;
        if (parentInput instanceof HTMLInputElement) {
            parentInput.value = '0';
        }
        if (quoteInput instanceof HTMLInputElement) {
            quoteInput.value = '0';
        }
        if (context instanceof HTMLElement) {
            context.hidden = true;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMediaModal();
            closeReportModal();
        }
    });
})();
