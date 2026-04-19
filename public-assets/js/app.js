(() => {
    document.documentElement.classList.add('js');

    const wrapSelection = (textarea, before, after = before, placeholder = '') => {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.slice(start, end) || placeholder;
        textarea.setRangeText(before + selected + after, start, end, 'select');
        textarea.focus();
    };

    const prefixLines = (textarea, prefix, placeholder = '') => {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.slice(start, end) || placeholder;
        const replacement = selected
            .split(/\r?\n/)
            .map((line) => line.startsWith(prefix) ? line : prefix + line)
            .join('\n');
        textarea.setRangeText(replacement, start, end, 'select');
        textarea.focus();
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const inlineMarkdown = (value) => escapeHtml(value)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" rel="nofollow ugc noopener noreferrer">$1</a>');

    const renderMarkdownPreview = (markdown) => {
        const blocks = String(markdown || '').replace(/\r\n?/g, '\n').split(/\n{2,}/);
        const html = [];
        let listItems = [];
        const flushList = () => {
            if (listItems.length > 0) {
                html.push('<ul>' + listItems.map((item) => '<li>' + inlineMarkdown(item) + '</li>').join('') + '</ul>');
                listItems = [];
            }
        };

        blocks.forEach((block) => {
            const text = block.trim();
            if (!text) {
                return;
            }
            if (/^[-*]\s+/m.test(text)) {
                text.split('\n').forEach((line) => {
                    const match = line.match(/^[-*]\s+(.+)$/);
                    if (match) {
                        listItems.push(match[1]);
                    }
                });
                return;
            }
            flushList();
            if (text.startsWith('### ')) {
                html.push('<h3>' + inlineMarkdown(text.slice(4)) + '</h3>');
                return;
            }
            if (text.startsWith('## ')) {
                html.push('<h2>' + inlineMarkdown(text.slice(3)) + '</h2>');
                return;
            }
            if (text.startsWith('>')) {
                html.push('<blockquote>' + inlineMarkdown(text.replace(/^>\s?/gm, '')).replace(/\n/g, '<br>') + '</blockquote>');
                return;
            }
            html.push('<p>' + inlineMarkdown(text).replace(/\n/g, '<br>') + '</p>');
        });
        flushList();

        return html.length > 0 ? html.join('') : '<p class="meta">Nothing to preview yet.</p>';
    };

    document.querySelectorAll('[data-markdown-editor]').forEach((textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        const form = textarea.form;
        const context = textarea.dataset.editorContext || 'markdown';
        const minWords = Number(textarea.dataset.editorMinWords || 0);
        const autosave = textarea.dataset.editorAutosave === '1' && form instanceof HTMLFormElement;
        const storageKey = autosave ? 'cms-editor-draft:' + form.action + ':' + textarea.name : '';
        const shell = document.createElement('div');
        shell.className = 'markdown-editor markdown-editor-' + context.replace(/[^a-z0-9_-]/gi, '');
        const tools = document.createElement('div');
        tools.className = 'editor-toolbar';
        const preview = document.createElement('div');
        preview.className = 'editor-preview';
        preview.hidden = true;
        preview.setAttribute('aria-live', 'polite');
        const status = document.createElement('div');
        status.className = 'editor-status';
        const count = document.createElement('span');
        const draft = document.createElement('span');
        status.appendChild(count);
        status.appendChild(draft);

        [
            ['bold', 'B', 'Bold', 'Ctrl+B', () => wrapSelection(textarea, '**', '**', 'bold text')],
            ['italic', 'I', 'Italic', 'Ctrl+I', () => wrapSelection(textarea, '*', '*', 'italic text')],
            ['code', '</>', 'Code', 'Ctrl+`', () => wrapSelection(textarea, '`', '`', 'code')],
            ['heading', 'H2', 'Heading', '', () => prefixLines(textarea, '## ', 'Heading')],
            ['quote', '>', 'Quote', '', () => prefixLines(textarea, '> ', 'Quoted text')],
            ['list', 'List', 'List', '', () => prefixLines(textarea, '- ', 'First point\nSecond point')],
            ['link', 'Link', 'Link', '', () => wrapSelection(textarea, '[', '](https://example.com)', 'link text')],
        ].forEach(([tool, mark, label, shortcut, action]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'editor-tool editor-tool-' + tool;
            button.setAttribute('aria-label', String(label));
            const markElement = document.createElement('span');
            markElement.className = 'editor-tool-mark';
            markElement.textContent = String(mark);
            const textElement = document.createElement('span');
            textElement.className = 'editor-tool-text';
            textElement.textContent = String(label);
            button.appendChild(markElement);
            button.appendChild(textElement);
            if (shortcut) {
                button.title = label + ' (' + shortcut + ')';
            }
            button.addEventListener('click', action);
            tools.appendChild(button);
        });

        const previewButton = document.createElement('button');
        previewButton.type = 'button';
        previewButton.className = 'editor-tool editor-preview-toggle';
        previewButton.innerHTML = '<span class="editor-tool-mark">View</span><span class="editor-tool-text">Preview</span>';
        previewButton.addEventListener('click', () => {
            const showing = !preview.hidden;
            preview.hidden = showing;
            textarea.hidden = !showing;
            const text = previewButton.querySelector('.editor-tool-text');
            const mark = previewButton.querySelector('.editor-tool-mark');
            if (text instanceof HTMLElement) {
                text.textContent = showing ? 'Preview' : 'Write';
            }
            if (mark instanceof HTMLElement) {
                mark.textContent = showing ? 'View' : 'Edit';
            }
            previewButton.setAttribute('aria-pressed', showing ? 'false' : 'true');
            if (!showing) {
                preview.innerHTML = renderMarkdownPreview(textarea.value);
            } else {
                textarea.focus();
            }
        });
        tools.appendChild(previewButton);

        const updateStatus = () => {
            const words = (textarea.value.trim().match(/\S+/g) || []).length;
            const chars = textarea.value.length;
            const needed = minWords > 0 && words < minWords ? ' - ' + (minWords - words) + ' more words suggested' : '';
            count.textContent = words + ' words - ' + chars + ' characters' + needed;
            if (!preview.hidden) {
                preview.innerHTML = renderMarkdownPreview(textarea.value);
            }
        };

        if (textarea.parentNode) {
            textarea.parentNode.insertBefore(shell, textarea);
            shell.appendChild(tools);
            shell.appendChild(textarea);
            shell.appendChild(preview);
            shell.appendChild(status);
        }

        if (autosave && storageKey) {
            const saved = localStorage.getItem(storageKey);
            if (saved && textarea.value.trim() === '') {
                textarea.value = saved;
                draft.textContent = 'Draft restored';
            }
            textarea.addEventListener('input', () => {
                localStorage.setItem(storageKey, textarea.value);
                draft.textContent = textarea.value.trim() === '' ? '' : 'Draft saved';
            });
            form.addEventListener('submit', () => {
                localStorage.removeItem(storageKey);
            });
        }

        textarea.addEventListener('input', updateStatus);
        textarea.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
                event.preventDefault();
                wrapSelection(textarea, '**', '**', 'bold text');
                updateStatus();
            }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'i') {
                event.preventDefault();
                wrapSelection(textarea, '*', '*', 'italic text');
                updateStatus();
            }
            if ((event.ctrlKey || event.metaKey) && event.key === '`') {
                event.preventDefault();
                wrapSelection(textarea, '`', '`', 'code');
                updateStatus();
            }
        });
        updateStatus();
    });

    document.querySelectorAll('[data-duplicate-title]').forEach((input) => {
        const root = input.closest('form') || document;
        const results = root.querySelector('[data-duplicate-results]');
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

    document.querySelectorAll('[data-count-input]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        const targetId = input.dataset.countTarget || '';
        const target = targetId ? document.getElementById(targetId) : null;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const updateCount = () => {
            const max = input.maxLength > 0 ? input.maxLength : 0;
            target.textContent = max > 0 ? input.value.length + ' / ' + max : String(input.value.length);
        };
        input.addEventListener('input', updateCount);
        updateCount();
    });

    document.querySelectorAll('[data-file-summary]').forEach((input) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        const target = document.getElementById(input.dataset.fileSummary || '');
        if (!(target instanceof HTMLElement)) {
            return;
        }
        input.addEventListener('change', () => {
            const count = input.files ? input.files.length : 0;
            target.textContent = count === 0
                ? 'Images or short videos can make the problem easier to understand.'
                : count + ' file' + (count === 1 ? '' : 's') + ' selected.';
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
        const mention = '@' + author.replace(/[^a-zA-Z0-9_-]/g, '');
        const quotedLines = body
            .replace(/\r\n?/g, '\n')
            .split('\n')
            .map((line) => line.trim())
            .filter((line, index, lines) => line !== '' || lines[index - 1] !== '')
            .map((line) => '> ' + line)
            .join('\n');
        const quote = ['> ' + mention + ' wrote:', quotedLines, ''].filter((line) => line !== '').join('\n') + '\n\n';
        const parentInput = form.querySelector('[data-quote-parent-input]');
        const quoteInput = form.querySelector('[data-quote-id-input]');
        const context = form.querySelector('[data-quote-context]');
        const contextText = form.querySelector('[data-quote-context-text]');
        const previousQuote = form.dataset.activeQuote || '';
        if (previousQuote && textarea.value.includes(previousQuote)) {
            textarea.value = textarea.value.replace(previousQuote, '').trim();
        }
        const prefix = textarea.value.trim() === '' ? '' : '\n\n';
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
        form.dataset.activeQuote = quote;
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
        if (form instanceof HTMLFormElement) {
            const textarea = form.querySelector('textarea[name="body"]');
            const previousQuote = form.dataset.activeQuote || '';
            if (textarea instanceof HTMLTextAreaElement && previousQuote && textarea.value.includes(previousQuote)) {
                textarea.value = textarea.value.replace(previousQuote, '').trim();
            }
            delete form.dataset.activeQuote;
        }
    });

    const updateVoteBox = (form, payload) => {
        const voteBox = form.closest('[data-vote-box]');
        if (!(voteBox instanceof HTMLElement)) {
            return;
        }

        const voteCount = voteBox.querySelector('[data-vote-count]');
        if (voteCount instanceof HTMLElement) {
            voteCount.textContent = String(Number(payload.votes || 0));
        }

        voteBox.querySelectorAll('[data-vote-button]').forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const isActive = Number(button.dataset.voteValue || 0) === Number(payload.current_vote || 0);
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const updateReactionBar = (form, payload) => {
        const reactionBar = form.closest('[data-reaction-bar]');
        if (!(reactionBar instanceof HTMLElement) || !Array.isArray(payload.reactions)) {
            return;
        }

        const reactionsById = new Map(payload.reactions.map((item) => [Number(item.id || 0), item]));
        reactionBar.querySelectorAll('form[data-async-reaction-form]').forEach((reactionForm) => {
            if (!(reactionForm instanceof HTMLFormElement)) {
                return;
            }

            const reactionId = Number(reactionForm.dataset.reactionId || 0);
            const reaction = reactionsById.get(reactionId);
            if (!reaction) {
                return;
            }

            const button = reactionForm.querySelector('[data-reaction-button]');
            const count = reactionForm.querySelector('[data-reaction-count]');
            if (button instanceof HTMLButtonElement) {
                const isActive = Boolean(reaction.reacted);
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
            if (count instanceof HTMLElement) {
                count.textContent = String(Number(reaction.count || 0));
            }
        });
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!(form instanceof HTMLFormElement) || (!form.hasAttribute('data-async-vote-form') && !form.hasAttribute('data-async-reaction-form'))) {
            return;
        }

        event.preventDefault();
        const submitButtons = Array.from(form.querySelectorAll('button[type="submit"], button:not([type])')).filter((button) => button instanceof HTMLButtonElement);
        submitButtons.forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.disabled = true;
            }
        });

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            });

            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            const payload = await response.json();
            if (response.status === 422) {
                return;
            }

            if (!response.ok || !payload || payload.ok === false) {
                window.location.reload();
                return;
            }

            if (form.hasAttribute('data-async-vote-form')) {
                updateVoteBox(form, payload);
            } else {
                updateReactionBar(form, payload);
            }
        } catch (error) {
            window.location.reload();
        } finally {
            submitButtons.forEach((button) => {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = false;
                }
            });
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMediaModal();
            closeReportModal();
        }
    });

    const usersGrid = document.querySelector('[data-users-directory]');
    const usersSearch = document.getElementById('users-search');
    const usersCount = document.getElementById('users-count');
    const usersSortButtons = document.querySelectorAll('.users-sort-btn');

    if (usersGrid instanceof HTMLElement && usersSearch instanceof HTMLInputElement) {
        const userCards = Array.from(usersGrid.querySelectorAll('.user-card')).filter((card) => card instanceof HTMLElement);
        const usersEmpty = usersGrid.querySelector('.users-empty');
        let currentSort = 'reputation';
        let currentQuery = '';

        const normalizeText = (value) => value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();

        const userName = (card) => {
            const name = card.querySelector('.user-card-name');
            return name instanceof HTMLElement ? normalizeText(name.textContent || '') : '';
        };

        const sortUsers = () => [...userCards].sort((a, b) => {
            if (currentSort === 'name') {
                return userName(a).localeCompare(userName(b));
            }

            const key = currentSort === 'answers' ? 'answers' : currentSort === 'questions' ? 'questions' : 'reputation';
            return Number(b.dataset[key] || 0) - Number(a.dataset[key] || 0);
        });

        const renderUsers = () => {
            const query = normalizeText(currentQuery);
            let visible = 0;

            userCards.forEach((card) => card.remove());
            if (usersEmpty instanceof HTMLElement) {
                usersEmpty.remove();
            }

            sortUsers().forEach((card) => {
                const match = query === '' || userName(card).includes(query);
                card.hidden = !match;
                if (match) {
                    visible += 1;
                }
                usersGrid.appendChild(card);
            });

            if (usersEmpty instanceof HTMLElement) {
                usersEmpty.hidden = visible > 0;
                usersGrid.appendChild(usersEmpty);
            }

            if (usersCount instanceof HTMLElement) {
                usersCount.textContent = visible === userCards.length
                    ? userCards.length + ' member' + (userCards.length !== 1 ? 's' : '')
                    : visible + ' of ' + userCards.length + ' members';
            }
        };

        usersSearch.addEventListener('input', () => {
            currentQuery = usersSearch.value;
            renderUsers();
        });

        usersSortButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', () => {
                usersSortButtons.forEach((item) => {
                    if (item instanceof HTMLElement) {
                        item.classList.remove('is-active');
                    }
                });
                button.classList.add('is-active');
                currentSort = button.dataset.sort || 'reputation';
                renderUsers();
            });
        });
    }
})();
