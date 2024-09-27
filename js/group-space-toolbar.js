(function($) {
    'use strict';

    var GroupSpace = {
        init: function() {
            this.cacheDom();
            this.bindEvents();
        },

        cacheDom: function() {
            this.$toolbar = $('.group-space-toolbar');
            this.$modal = $('#group-space-modal');
            this.$modalContent = this.$modal.find('.toolbar-modal-content div.inner_content');
            this.$closeButton = this.$modal.find('.toolbar-modal-content .close');
        },

        bindEvents: function() {
            this.$toolbar.on('click', '.group-space-action', this.handleAction.bind(this));
            this.$closeButton.on('click', this.closeModal.bind(this));
        },

        handleAction: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            var action = $button.data('action');
            var postId = this.$toolbar.data('post-id');

            $.ajax({
                url: group_space_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'group_space_action',
                    nonce: group_space_ajax.nonce,
                    custom_action: action,
                    post_id: postId
                },
                success: this.handleResponse.bind(this)
            });
        },

        handleResponse: function(response) {
            if (response.success) {
                if(response.message){
                    this.$modalContent.html('<p>' + response.message + '</p>');
                    this.load_modal_content_scripts();
                    this.$modal.css('display', 'flex');
                }
            } else {
                alert('Fehler: ' + response.message);
            }
        },

        closeModal: function() {
            this.$modal.hide();
        },

        load_modal_content_scripts: function() {

            $('.pad-version-link').on('click', function(e) {
                e.preventDefault();
                var $button = $(e.currentTarget);
                var postId = $button.data('post-id');
                var version = $button.data('version');


                $.ajax({
                    url: group_space_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'group_space_action',
                        nonce: group_space_ajax.nonce,
                        custom_action: 'open-pad',
                        post_id: postId,
                        timestamp: version
                    },
                    success: function(response) {

                        if (response.success) {
                            var modal = $('#group-space-modal');
                            var modalContent = modal.find('.toolbar-modal-content div.inner_content');
                            if(response.message){
                                modalContent.html('<p>' + response.message + '</p>');
                            }
                        } else {
                            alert('Fehler: ' + response.message);
                        }
                    }
                });
            });

        }
    };

    $(document).ready(function() {
        GroupSpace.init();
    });

})(jQuery);
