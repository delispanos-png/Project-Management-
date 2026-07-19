const aiSuggestions = {
    state: {
        isLoading: false,
        currentSuggestion: null,
        ticketId: null,
        sanitizedTicketData: null,
        traceId: null,
        originalReplies: null,
        selectedTone: 'friendly',
        selectedLength: 'detailed',
        hasGenerated: false,
        references: null,
        licenseValid: false,
        licenseMessage: '',
        licenseReason: ''
    },

    init() {
        if (typeof window.aiLang === 'undefined') return;

        this.state.ticketId = this.getTicketId();
        this.loadLicenseStatus();
        this.bindEvents();
    },

    loadLicenseStatus() {
        const container = $('#ticketReplyAiSuggestions');
        if (container.length) {
            this.state.licenseValid = container.data('license-valid') !== 0 && container.data('license-valid') !== '0';
            this.state.licenseMessage = container.data('license-message') || '';
            this.state.licenseReason = container.data('license-reason') || '';
        }
    },

    bindEvents() {
        const self = this;

        // Toggle AI suggestions panel
        $(document).on('click', '.btn-ai-suggestions-manage', function (e) {
            e.preventDefault();
            self.togglePanel($(this));
        });

        // Initial generate button
        $(document).on('click', '#generateAiSuggestionInitial', function (e) {
            e.preventDefault();
            self.generateAiSuggestion();
        });

        // Regenerate button (in tone/length controls)
        $(document).on('click', '#generateAiSuggestion', function (e) {
            e.preventDefault();
            self.regenerateAiSuggestion();
        });

        $(document).on('click', '#aiConfirmGenerate', function () {
            self.proceedWithGeneration();
        });

        $(document).on('change', '#aiToneSelector', function () {
            self.state.selectedTone = $(this).val();
        });

        $(document).on('change', '#aiLengthSelector', function () {
            self.state.selectedLength = $(this).val();
        });

        // Auto-resize textareas
        $(document).on('input', '#aiTicketSubject, #aiTicketContent, #aiReplyHistory', function () {
            self.autoResizeTextarea(this);
        });

        // Real-time validation for subject and content fields
        $(document).on('blur', '#aiTicketSubject', function () {
            const value = $(this).val().trim();
            if (!value) {
                self.showValidationError('#aiTicketSubject', window.aiLang?.subjectRequired);
            }
        });

        $(document).on('blur', '#aiTicketContent', function () {
            const value = $(this).val().trim();
            if (!value) {
                self.showValidationError('#aiTicketContent', window.aiLang?.contentRequired);
            }
        });

        $(document).on('input', '#aiTicketSubject, #aiTicketContent', function () {
            const $field = $(this);
            if ($field.hasClass('is-invalid') && $field.val().trim()) {
                $field.removeClass('is-invalid');
                $field.closest('.ai-form-section').find('.ai-validation-error').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });

        // Reply editor changes
        $(document).on('input', '.reply-editor', function () {
            self.autoResizeTextarea(this);
            self.updateConversationHistory();
        });

        // Thread collapse/expand
        $(document).on('click', '.thread-toggle', function (e) {
            e.preventDefault();
            self.toggleThread($(this));
        });

        // Individual reply collapse/expand
        $(document).on('click', '.reply-toggle', function (e) {
            e.preventDefault();
            self.toggleReply($(this));
        });

        // Ticket content section toggle
        $(document).on('click', '.ticket-content-section .section-toggle', function (e) {
            e.preventDefault();
            self.toggleTicketContentSection($(this));
        });
    },

    togglePanel($button) {
        // Check license status BEFORE opening panel to prevent sanitization API call
        if (!this.state.licenseValid) {
            this.showLicenseError();
            return;
        }

        const $panel = $('#ticketReplyAiSuggestions');
        const isVisible = $panel.is(':visible');

        if (isVisible) {
            $panel.fadeOut();
            $button.removeClass('active');
        } else {
            $panel.fadeIn();
            $button.addClass('active');
            // Clear any lingering validation errors from previous session
            this.clearValidationErrors();
            setTimeout(() => this.loadTicketData(), 100);
        }
    },

    loadTicketData() {
        if (!window.ticketData?.tid) {
            this.showMessage(window.aiLang.ticketDataNotAvailable, 'danger');
            return;
        }

        this.showLoading(window.aiLang.sanitizingAndLoading);

        const decodeHtmlEntities = (text) => {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            return textarea.value;
        };

        $.ajax({
            url: '/whmcs/admin/aiagent/ticket/sanitize',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                tid: window.ticketData.tid,
                message: decodeHtmlEntities(window.ticketData.message),
                token: csrfToken
            })
        })
            .done(response => {
                if (!response.sanitized?.subject || !response.sanitized?.message) {
                    this.hideLoading();
                    this.populateFields({subject: '', message: window.ticketData.message});
                    this.showMessage(window.aiLang.sanitizationFailed, 'danger');
                    return;
                }

                this.state.sanitizedTicketData = response.sanitized;
                this.state.traceId = response.trace_id;
                this.state.originalReplies = response.sanitized.replies;

                this.populateFields(response.sanitized);
                this.buildReplyThread(response.sanitized.replies);
                this.hideLoading();

                const count = this.state.originalReplies.length;
                this.showMessage(window.aiLang.dataLoadedSanitized.replace('%s', count), 'success');
            })
            .fail((xhr) => {
                this.handleAjaxError(xhr, window.aiLang.sanitizationFailed, () => {
                    this.populateFields({subject: '', message: window.ticketData.message});
                });
            });
    },

    populateFields(data) {
        $('#aiTicketSubject').val(data.subject);
        $('#aiTicketContent').val(data.message);
        this.autoResizeTextarea($('#aiTicketSubject')[0]);
        this.autoResizeTextarea($('#aiTicketContent')[0]);
        this.updateConversationHistory();

        // Clear any existing validation errors since fields now have content
        this.clearValidationErrors();
    },

    buildReplyThread(replies) {
        const $thread = $('#replyThread');
        const hasReplies = replies.length > 0;

        // Toggle ticket-content-section: expanded if no replies, collapsed if replies exist
        const $ticketContentSection = $('.ticket-content-section');
        const $toggleBtn = $ticketContentSection.find('.section-toggle');
        const $content = $ticketContentSection.find('.section-content');

        if (hasReplies) {
            // Collapse ticket content section when replies exist (no animation on initial load)
            $ticketContentSection.addClass('collapsed');
            $content.hide();
            $toggleBtn.html('<i class="fas fa-chevron-down"></i>').data('expanded', false);
        } else {
            // Expand ticket content section when no replies (no animation on initial load)
            $ticketContentSection.removeClass('collapsed');
            $content.show();
            $toggleBtn.html('<i class="fas fa-chevron-up"></i>').data('expanded', true);
        }

        if (!replies.length) {
            $thread.html(`<div class="no-replies"><p>${window.aiLang.noRepliesFound}</p></div>`);
            return;
        }

        const header = `
            <div class="reply-thread-header">
                <h5><i class="fas fa-comments"></i> ${window.aiLang.replyThread.replace('%s', replies.length)}</h5>
                <button type="button" class="btn btn-sm btn-link thread-toggle" data-expanded="false">
                    <i class="fas fa-chevron-down"></i> ${window.aiLang.expandAll}
                </button>
            </div>
            <div class="reply-thread-content" style="display: none;">`;

        const replyHtml = replies.map((reply, index) => this.buildReplyHtml(reply, index + 1)).join('');

        $thread.html(header + replyHtml + '</div>');
    },

    buildReplyHtml(reply, index) {
        const isStaff = reply.role === 'staff';
        const icon = isStaff ? 'fas fa-user-tie' : 'fas fa-user';
        const roleName = isStaff ? (reply.admin_id || window.aiLang.supportStaff) : window.aiLang.customer;

        const truncatedPreview = reply.message.length > 50
            ? reply.message.substring(0, 50) + '...'
            : reply.message;

        return `
            <div class="reply-message ${isStaff ? 'staff-reply' : 'customer-reply'}">
                <div class="reply-header">
                    <div class="reply-meta">
                        <i class="${icon}"></i>
                        <strong>${roleName}</strong>
                        <span class="reply-date">${this.formatDate(reply.date)}</span>
                    </div>
                    <div class="reply-preview">
                        <span class="reply-preview-text">${this.escapeHtml(truncatedPreview)}</span>
                    </div>
                    <div class="reply-controls">
                        <button type="button" class="btn btn-xs btn-link reply-toggle" data-target="reply-${index}">
                            <i class="fas fa-chevron-down"></i> ${window.aiLang.expand}
                        </button>
                    </div>
                </div>
                <div class="reply-content" id="reply-${index}" style="display: none;">
                    <div class="reply-edit-section">
                        <label>${window.aiLang.editMessageContent}</label>
                        <textarea class="form-control reply-editor" data-reply-id="${index}" rows="4">${reply.message}</textarea>
                    </div>
                </div>
            </div>`;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    toggleThread($btn) {
        const isExpanded = $btn.data('expanded') === true || $btn.data('expanded') === 'true';
        const $content = $('.reply-thread-content');

        if (isExpanded) {
            // Collapse all
            $content.slideUp(200);
            $('.reply-content').slideUp(200);

            // Update the main collapse/expand button
            $btn.html(`<i class="fas fa-chevron-down"></i> ${window.aiLang.expandAll}`)
                .data('expanded', false);

            // Update all individual reply toggle buttons to collapsed state
            $('.reply-toggle').each(function() {
                const target = $(this).data('target');
                $(this).html(`<i class="fas fa-chevron-down"></i> ${window.aiLang.expand}`);
            });
        } else {
            // Expand all
            $content.slideDown(200);

            // Update the main collapse/expand button
            $btn.html(`<i class="fas fa-chevron-up"></i> ${window.aiLang.collapseAll}`)
                .data('expanded', true);
        }
    },

    toggleReply($btn) {
        const target = $btn.data('target');
        if (!target) return;

        const $content = $(`#${target}`);
        const isVisible = $content.is(':visible');

        $content.slideToggle(200);
        $btn.html(`<i class="fas fa-chevron-${isVisible ? 'down' : 'up'}"></i> ${isVisible ? window.aiLang.expand : window.aiLang.collapse}`);

        if (!isVisible) {
            this.autoResizeTextarea($content.find('.reply-editor')[0]);
        }
    },

    toggleTicketContentSection($btn) {
        const isExpanded = $btn.data('expanded') === true || $btn.data('expanded') === 'true';
        const $section = $btn.closest('.ticket-content-section');
        const $content = $section.find('.section-content');

        if (isExpanded) {
            // Collapse
            $content.slideUp(300);
            $btn.html('<i class="fas fa-chevron-down"></i>').data('expanded', false);
            $section.addClass('collapsed');
        } else {
            // Expand
            $content.slideDown(300);
            $btn.html('<i class="fas fa-chevron-up"></i>').data('expanded', true);
            $section.removeClass('collapsed');
        }
    },

    updateConversationHistory() {
        const content = $('#aiTicketContent').val();
        let history = content ? `[${window.aiLang.customer}]: ${content}\n\n` : '';

        $('.reply-editor').each((index, element) => {
            const replyId = $(element).data('reply-id');
            const replyContent = $(element).val();

            if (replyContent && this.state.originalReplies?.[replyId - 1]) {
                const role = this.state.originalReplies[replyId - 1].role;
                const roleName = role === 'staff' ? window.aiLang.staff : window.aiLang.customer;
                history += `[${roleName}]: ${replyContent}\n\n`;
            }
        });

        $('#aiReplyHistory').val(history.trim());
        this.autoResizeTextarea($('#aiReplyHistory')[0]);
    },

    generateAiSuggestion() {
        // Check license status as backup (defense in depth)
        if (!this.state.licenseValid) {
            this.showLicenseError();
            return;
        }

        if (!this.state.sanitizedTicketData) {
            this.showMessage(window.aiLang.noSanitizedData, 'danger');
            return;
        }

        const subject = $('#aiTicketSubject').val().trim();
        const content = $('#aiTicketContent').val().trim();

        if (!subject) {
            this.showValidationError('#aiTicketSubject', window.aiLang.subjectRequired);
            return;
        }

        if (!content) {
            this.showValidationError('#aiTicketContent', window.aiLang.contentRequired);
            return;
        }

        this.clearValidationErrors();

        $('#aiConfirmModal').modal('show');
    },

    proceedWithGeneration() {
        $('#aiConfirmModal').modal('hide');
        this.performGeneration(false);
    },

    regenerateAiSuggestion() {

        this.performGeneration(true);
    },

    performGeneration(isRegeneration = false) {
        const loadingMessage = isRegeneration ? window.aiLang.regenerating : window.aiLang.generatingAiSuggestion;

        this.showLoading(loadingMessage);

        const editedReplies = [];
        $('.reply-editor').each((index, element) => {
            const content = $(element).val().trim();
            if (content) editedReplies.push(content);
        });

        const payload = {
            ...this.state.sanitizedTicketData,
            subject: $('#aiTicketSubject').val(),
            message: $('#aiTicketContent').val(),
            replies: editedReplies,
            trace_id: this.state.traceId,
            token: csrfToken
        };

        // Add regenerate_params as a separate parameter (not encrypted)
        if (isRegeneration) {
            payload.regenerate_params = {
                tone: this.state.selectedTone,
                length: this.state.selectedLength
            };
        }

        $.ajax({
            url: '/whmcs/admin/aiagent/ticket/suggest',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload)
        })
            .done(response => {
                if (!response.suggested_response) {
                    this.hideLoading();
                    this.showMessage(window.aiLang.suggestionFailed, 'danger');
                    return;
                }

                this.state.currentSuggestion = response.suggested_response;
                this.state.traceId = response.trace_id;
                this.state.hasGenerated = true;
                this.state.references = this.getReferences(response);

                $('input[name="trace_id"]').val(response.trace_id);

                this.insertSuggestionToReply();
                this.displayReferences();
                this.hideLoading();

                // Show tone/length controls and hide initial button after first generation
                if (!isRegeneration) {
                    $('#aiInitialGenerateButton').fadeOut(200, function() {
                        $('#aiToneLengthControls').removeClass('ai-hidden').hide().fadeIn(300);
                    });
                }

                // Scroll to reply box and show ready message
                setTimeout(() => {
                    this.scrollToReplyBox();
                }, 300);
            })
            .fail((xhr) => {
                this.handleAjaxError(xhr, window.aiLang.suggestionFailed);
            });
    },

    /**
     * Extract error message from AJAX response.
     *
     * Priority:
     * 1. Translated message from backend (response.message)
     * 2. Translated error_code from window.aiLang (using underscore key)
     * 3. HTTP status-specific fallback
     * 4. Default fallback message
     *
     * @param {Object} xhr - jQuery XHR object
     * @param {string} fallbackMessage - Default message if extraction fails
     * @return {string} Error message to display
     */
    extractErrorMessage(xhr, fallbackMessage) {
        try {
            const response = xhr.responseJSON;

            // Priority 1: Use translated message from backend
            if (response && response.message) {
                return response.message;
            }

            // Priority 2: Try to translate error_code (underscore format: aiagent_error_*)
            if (response && response.error_code && window.aiLang[response.error_code]) {
                return window.aiLang[response.error_code];
            }

            // Priority 3: HTTP status-specific fallbacks
            const statusMessages = {
                429: window.aiLang.aiagent_error_rate_limit_exceeded,
                504: window.aiLang.aiagent_error_llm_timeout,
                503: window.aiLang.aiagent_error_service_unavailable,
                401: window.aiLang.aiagent_error_auth_failed,
                400: window.aiLang.aiagent_error_invalid_input
            };

            if (statusMessages[xhr.status]) {
                return statusMessages[xhr.status];
            }
        } catch (e) {
            console.error('Error parsing AJAX error response:', e);
        }

        // Priority 4: Return fallback message
        return fallbackMessage;
    },

    /**
     * Handle AJAX request failure - centralized error handler for all endpoints.
     *
     * @param {Object} xhr - jQuery XHR object
     * @param {string} defaultMessage - Default error message
     * @param {Function} callback - Optional callback to execute after showing error
     */
    handleAjaxError(xhr, defaultMessage, callback) {
        this.hideLoading();

        const errorMessage = this.extractErrorMessage(xhr, defaultMessage);
        this.showMessage(errorMessage, 'danger');

        if (callback && typeof callback === 'function') {
            callback();
        }
    },

    insertSuggestionToReply() {
        if (!this.state.currentSuggestion) {
            this.showMessage(window.aiLang.noAiSuggestion, 'danger');
            return;
        }

        const $replyBox = $('#replymessage');
        if (!$replyBox.length) {
            this.showMessage(window.aiLang.unableToFindReplyBox, 'danger');
            return;
        }

        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.replymessage) {
            CKEDITOR.instances.replymessage.setData(this.state.currentSuggestion);
        } else {
            $replyBox.val(this.state.currentSuggestion);
        }
    },

    scrollToReplyBox() {
        const $replyBox = $('#replymessage');
        if (!$replyBox.length) {
            return;
        }

        const $scrollTarget = this.getScrollTarget($replyBox);
        const scrollOffset = 100;
        const scrollDuration = 600;

        $('html, body').animate(
            {scrollTop: $scrollTarget.offset().top - scrollOffset},
            scrollDuration,
            () => {
                this.showResponseReadyNotice($scrollTarget);
                this.focusReplyBox($replyBox);
            }
        );
    },

    getScrollTarget($replyBox) {
        if (typeof CKEDITOR === 'undefined' || !CKEDITOR.instances.replymessage) {
            return $replyBox;
        }

        const editorContainer = CKEDITOR.instances.replymessage.container;
        return editorContainer ? $(editorContainer.$) : $replyBox;
    },

    showResponseReadyNotice($scrollTarget) {
        const noticeClass = 'ai-response-ready-notice';
        const fadeInDuration = 300;
        const displayDuration = 4000;
        const fadeOutDuration = 300;

        // Remove existing notice if present
        $(`.${noticeClass}`).remove();

        const $notice = this.createResponseNotice(noticeClass);

        $scrollTarget.before($notice);
        $notice
            .fadeIn(fadeInDuration)
            .delay(displayDuration)
            .fadeOut(fadeOutDuration, function() {
                $(this).remove();
            });
    },

    createResponseNotice(className) {
        return $(`<div class="${className}">`)
            .html(`<i class="fas fa-check-circle"></i> ${window.aiLang.responseReady}`)
            .hide();
    },

    focusReplyBox($replyBox) {
        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.replymessage) {
            CKEDITOR.instances.replymessage.focus();
        } else {
            $replyBox.focus();
        }
    },

    autoResizeTextarea(element) {
        if (element?.style) {
            element.style.height = 'auto';
            element.style.height = `${element.scrollHeight + 2}px`;
        }
    },

    showLoading(message) {
        this.state.isLoading = true;

        // Disable both generate buttons
        $('#generateAiSuggestion, #generateAiSuggestionInitial')
            .prop('disabled', true)
            .html(`<i class="fas fa-spinner fa-spin"></i> ${window.aiLang.generating}`);

        let $overlay = $('.ai-loading-overlay');
        if (!$overlay.length) {
            $('#ticketReplyAiSuggestions').append('<div class="ai-loading-overlay"></div>');
            $overlay = $('.ai-loading-overlay');
        }

        const subtitle = this.getLoadingSubtitle(message);
        $overlay.html(`
            <div class="ai-loading-content">
                <div class="ai-loading-main">
                    <div class="ai-loading-spinner">
                        <div class="spinner"></div>
                    </div>
                    <div class="ai-loading-text">${message}</div>
                </div>
                <div class="ai-loading-subtitle">${subtitle}</div>
            </div>
        `).addClass('active');
    },

    getLoadingSubtitle(message) {
        const subtitles = {
            [window.aiLang.loadingTicketData]: window.aiLang.retrievingConversation,
            [window.aiLang.sanitizingAndLoading]: window.aiLang.removingSensitiveInfo,
            [window.aiLang.generatingAiSuggestion]: window.aiLang.aiAnalyzing
        };
        return subtitles[message] || window.aiLang.processingRequest;
    },


    hideLoading() {
        this.state.isLoading = false;

        // Restore button states
        $('#generateAiSuggestion').prop('disabled', false)
            .html(`<i class="fas fa-sync-alt"></i> ${window.aiLang.regenerate}`);

        $('#generateAiSuggestionInitial').prop('disabled', false)
            .html(`<i class="fas fa-sparkles"></i> ${window.aiLang.generate}`);

        $('.ai-loading-overlay').removeClass('active');
    },

    showLicenseError() {
        this.showMessage(this.state.licenseMessage, 'danger');
    },

    showMessage(message, type = 'info') {
        $('.ai-alert').remove();

        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        const html = `
            <div class="alert alert-${type} ai-alert" role="alert">
                <i class="fas ${icon}"></i> ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>`;

        $('#ticketReplyAiSuggestions').prepend(html);

        if (type === 'success') {
            setTimeout(() => $('.ai-alert').fadeOut(), 2000);
        }
    },

    showValidationError(fieldSelector, message) {
        const $field = $(fieldSelector);
        const $formSection = $field.closest('.ai-form-section');

        $field.addClass('is-invalid');

        $formSection.find('.ai-validation-error').remove();

        $formSection.append(`<div class="ai-validation-error"><i class="fas fa-exclamation-circle"></i> ${message}</div>`);

        $field.focus();

        $field.one('input', () => {
            $field.removeClass('is-invalid');
            $formSection.find('.ai-validation-error').fadeOut(200, function() {
                $(this).remove();
            });
        });
    },

    clearValidationErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.ai-validation-error').remove();
    },

    formatDate(dateString) {
        if (!dateString) return window.aiLang.unknownDate;

        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return window.aiLang.invalidDate;
            return `${date.toLocaleDateString()} ${date.toLocaleTimeString()}`;
        } catch (error) {
            return window.aiLang.invalidDate;
        }
    },

    getReferences(response) {
        if (response && Array.isArray(response.source_references)) {
            return response.source_references;
        }
        return [];
    },

    displayReferences() {
        const $referencesContainer = $('#aiReferencesSection');
        const $referencesBody = $referencesContainer.find('.references-body');

        if (!$referencesContainer.length) {
            return;
        }

        const content = this.hasReferences()
            ? this.buildReferencesContentBody()
            : this.buildNoReferencesContent();

        $referencesBody.html(content);
        $referencesContainer.fadeIn(300);
    },

    hasReferences() {
        return this.state.references && this.state.references.length > 0;
    },

    buildReferencesContentBody() {
        const referencesList = this.state.references
            .map(ref => this.buildReferenceItem(ref))
            .join('');

        return `
            <p class="references-description">${window.aiLang.referencesDescription}</p>
            <div class="references-list">
                ${referencesList}
            </div>`;
    },

    buildReferenceItem(ref) {
        return `
            <div class="reference-item">
                <i class="fas fa-external-link-alt"></i>
                <a href="${ref.url}" target="_blank" rel="noopener noreferrer">${ref.title}</a>
            </div>`;
    },

    buildNoReferencesContent() {
        return `
            <div class="no-references">
                <i class="fas fa-info-circle"></i>
                <p>${window.aiLang.noReferencesAvailable}</p>
            </div>`;
    },

    getTicketId() {
        const match = window.location.search.match(/id=(\d+)/);
        return match ? match[1] : null;
    }
};

// Initialize on document ready
$(document).ready(() => {
    aiSuggestions.init();
});
