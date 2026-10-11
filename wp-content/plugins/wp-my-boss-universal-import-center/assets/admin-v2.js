/**
 * UNI-IM-PORT Admin UI v2
 * Version: 1.0.15.3
 */

(function($) {
    'use strict';

    window.WPMB_Modal = {
        open: function(id) {
            $('#' + id).addClass('active').fadeIn(200);
        },
        close: function(id) {
            $('#' + id).removeClass('active').fadeOut(200);
        }
    };

    window.WPMB_Toast = {
        success: function(message) {
            this.show(message, 'success');
        },
        error: function(message) {
            this.show(message, 'error');
        },
        info: function(message) {
            this.show(message, 'info');
        },
        show: function(message, type) {
            type = type || 'info';
            var $toast = $('<div class="wpmb-toast wpmb-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);
            $toast.fadeIn(200);
            setTimeout(function() {
                $toast.fadeOut(200, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    window.WPMB_RTReport = {
        update: function(data) {
            if ($('.wpmb-rt').length) {
                var html = '<div class="wpmb-rt-header">' +
                    '<span class="wpmb-rt-code">' + (data.code || 'N/A') + '</span>' +
                    '<span class="wpmb-rt-counter">สำเร็จ: <strong>' + (data.counts ? data.counts.done : 0) + '</strong> / ' + (data.counts ? data.counts.total : 0) + '</span>' +
                    '</div>';
                $('.wpmb-rt').html(html);
            }
        }
    };

    $(function() {
        // Close modal on overlay click
        $(document).on('click', '.wpmb-modal-overlay', function() {
            $(this).closest('.wpmb-modal').removeClass('active').fadeOut(200);
        });

        // Close modal on close button click
        $(document).on('click', '.wpmb-modal-close', function() {
            $(this).closest('.wpmb-modal').removeClass('active').fadeOut(200);
        });

        // Toggle file list
        $(document).on('click', '.wpmb-toggle-btn', function() {
            var $fileList = $(this).closest('.wpmb-card').find('.wpmb-file-list');
            $fileList.toggleClass('open');
        });
    });
})(jQuery);
