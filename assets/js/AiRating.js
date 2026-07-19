const aiRating = {
    state: {
        ratings: {},
        replyId: null,
        traceId: null,
        isSubmitting: false,
        feedbackSubmitted: false,
        hideTitleOnSuccess: false
    },

    init() {
        if (!$('.ai-star-rating').length) {
            return;
        }

        const self = this;

        $('.ai-star-rating').each(function () {
            const replyId = $(this).data('reply-id');
            const active = $(this).find('.star.active').length;
            if (active > 0 && replyId) {
                self.state.ratings[replyId] = active;
            }
        });

        this.bindEvents()
    },

    bindEvents() {
        const self = this;

        $(document).on('mouseenter', '.ai-star-rating .star', function () {
            const $container = $(this).closest('.ai-star-rating');
            const rating = $(this).data('rating');
            self.highlightStars($container, rating);
        });

        $(document).on('mouseleave', '.ai-star-rating .star', function () {
            const $container = $(this).closest('.ai-star-rating');
            const replyId = $container.data('reply-id');
            const saved = self.state.ratings[replyId] || 0;
            self.highlightStars($container, saved);
        });

        // Star click - submit rating
        $(document).on('click', '.ai-star-rating .star', function (e) {
            e.preventDefault();
            self.handleStarClick($(this));
        });

        // Submit feedback button
        $(document).on('click', '#aiRatingFeedbackSubmit', function () {
            self.handleFeedbackSubmit();
        });

        // Remove error state when user starts typing
        $(document).on('input', '#aiRatingFeedbackText', function () {
            if ($(this).val().trim() !== '') {
                $(this).removeClass('is-invalid');
                $('#aiRatingFeedbackError').hide();
            }
        });

        // Handle modal close
        $('#aiRatingFeedbackModal').on('hidden.bs.modal', function () {
            self.handleModalClose();
        });
    },

    handleStarClick($star) {
        const $container = $star.closest('.ai-star-rating');
        const rating = $star.data('rating');
        const replyId = $container.data('reply-id');
        const traceId = $container.data('trace-id');

        this.state.ratings[replyId] = rating;
        this.state.replyId = replyId;
        this.state.traceId = traceId;
        this.state.feedbackSubmitted = false;

        this.highlightStars($container, rating);

        // Show feedback modal for low (1-2) or high (4-5) ratings, submit immediately for neutral
        if (rating <= 2 || rating >= 4) {
            this.resetModal();
            this.updateModalContent(rating);
            $('#aiRatingFeedbackModal').modal('show');
        } else {
            this.submitRating(replyId, rating, '', traceId, false);
        }
    },

    updateModalContent(rating) {
        if (typeof window.aiRatingLang === 'undefined') {
            return;
        }

        const $modal = $('#aiRatingFeedbackModal');
        const $title = $modal.find('#aiRatingFeedbackModalLabel');
        const $message = $modal.find('#aiRatingFeedbackForm p');
        const $textarea = $modal.find('#aiRatingFeedbackText');

        if (rating <= 3) {
            // Low rating - ask for improvement feedback
            $title.text(window.aiRatingLang.feedbackTitleLow);
            $message.text(window.aiRatingLang.feedbackMessageLow);
            $textarea.attr('placeholder', window.aiRatingLang.feedbackPlaceholderLow);
        } else {
            // High rating - ask for positive feedback
            $title.text(window.aiRatingLang.feedbackTitleHigh);
            $message.text(window.aiRatingLang.feedbackMessageHigh);
            $textarea.attr('placeholder', window.aiRatingLang.feedbackPlaceholderHigh);
        }
    },

    handleFeedbackSubmit() {
        const feedback = $('#aiRatingFeedbackText').val().trim();

        if (!feedback) {
            $('#aiRatingFeedbackError').show();
            $('#aiRatingFeedbackText').addClass('is-invalid').focus();
            return;
        }

        this.state.feedbackSubmitted = true;
        const rating = this.state.ratings[this.state.replyId] || 0;

        this.submitRating(this.state.replyId, rating, feedback, this.state.traceId, true);
    },

    handleModalClose() {
        if (this.state.feedbackSubmitted) {
            this.resetModal();
            return;
        }

        const saved = this.state.ratings[this.state.replyId] || 0;

        if (saved > 0) {
            this.submitRating(this.state.replyId, saved, '', this.state.traceId, false);
        }

        this.resetModal();
    },

    highlightStars($container, rating) {
        $container.find('.star').each(function () {
            const r = $(this).data('rating');
            $(this).toggleClass('active', r <= rating)
                .toggleClass('inactive', r > rating);
        });
    },

    submitRating(replyId, rating, feedback, traceId, fromModal) {
        if (this.state.isSubmitting) return;

        this.state.isSubmitting = true;
        const normalizedRating = parseInt(rating, 10) || 0;
        if (fromModal) {
            $('#aiRatingFeedbackLoader').show();
            $('#aiRatingFeedbackForm').hide();
            this.toggleActionButtons(false);
        }

        const ticketId = window.ticketData?.tid;

        $.ajax({
            url: 'aiagent/ticket/feedback',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                trace_id: traceId,
                ticket_id: ticketId,
                reply_id: parseInt(replyId),
                star_rating: normalizedRating,
                comment: feedback,
                token: csrfToken
            })
        }).done(() => {
            if (rating <= 2) {
                this.reportInaccurate(traceId, ticketId, replyId, feedback)
                    .fail(() => console.warn('report-inaccurate failed'));
            }

            if (fromModal) {
                this.state.hideTitleOnSuccess = false;
                this.showSuccessMessage();
            } else if (normalizedRating === 3) {
                this.state.feedbackSubmitted = true;
                this.resetModal();
                this.state.hideTitleOnSuccess = true;
                $('#aiRatingFeedbackModal').modal('show');
                this.showSuccessMessage();
            }
        }).fail(() => {
            if (fromModal) {
                $('#aiRatingFeedbackError').show();
                this.toggleActionButtons(true);
            }
        }).always(() => {
            this.state.isSubmitting = false;
        });
    },

    reportInaccurate(traceId, ticketId, replyId, feedback) {
        return $.ajax({
            url: 'aiagent/ticket/report-inaccurate',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                replyId: replyId,
                status: "Pending",
                ticket_id: ticketId,
                trace_id: traceId,
                comment: feedback,
                token: csrfToken
            })
        });
    },

    showSuccessMessage() {
        $('#aiRatingFeedbackLoader').hide();
        $('#aiRatingFeedbackError').hide();
        $('#aiRatingFeedbackForm').hide();
        $('#aiRatingFeedbackSuccess').show();
        this.toggleActionButtons(false);

        const $title = $('#aiRatingFeedbackModal .modal-title');
        if (this.state.hideTitleOnSuccess) {
            $title.hide();
        } else {
            $title.show();
        }

        setTimeout(() => {
            $('#aiRatingFeedbackModal').modal('hide');
        }, 2000);
    },

    resetModal() {
        $('#aiRatingFeedbackLoader').hide();
        $('#aiRatingFeedbackSuccess').hide();
        $('#aiRatingFeedbackError').hide();
        $('#aiRatingFeedbackForm').show();
        $('#aiRatingFeedbackText').val('').removeClass('is-invalid');
        this.toggleActionButtons(true);
        $('#aiRatingFeedbackModal #aiRatingFeedbackModalLabel').show();
        this.state.hideTitleOnSuccess = false;
    },

    toggleActionButtons(shouldShow) {
        $('#aiRatingFeedbackCancel, #aiRatingFeedbackSubmit').toggle(shouldShow);
    }
};
