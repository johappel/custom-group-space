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
            this.$modalContent = this.$modal.find('.inner_content');
            this.$modalTitle = this.$modal.find('.modal-title');
            this.$closeButton = this.$modal.find('.close');
            this.$modalFooter = this.$modal.find('.modal-footer');
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
            var title = $button.data('title');

            if(action !== 'close') {
                if(title !== undefined) {
                    this.$modalTitle.text('KI-Moderatorin: ' + title);
                }

                $.ajax({
                    url: group_space_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'group_space_action',
                        nonce: group_space_ajax.nonce,
                        custom_action: action,
                        post_id: postId,
                        modal_title: title
                    },
                    success: this.handleResponse.bind(this)
                });
            }
        },

        handleResponse: function(response) {
            if (response.success) {
                if(response.message){
                    this.$modalContent.html('<p>' + response.message + '</p>');
                    $('.progress-bar').hide();
                    this.setModalButtons(response.buttons,true);
                    this.load_modal_content_scripts();
                    this.$modal.css('display', 'flex');
                }
            } else {
                alert('Fehler: ' + response.message);
            }
        },

        setModalButtons: function(buttons,progressBar=false) {
            this.$modalFooter.empty();
            this.$modalFooter.empty();
            if(progressBar){
                var $progressBar = $('<div>', { class: 'progress-bar' });
                this.$modalFooter.append($progressBar);
            }

            if (buttons && buttons.length) {
                buttons.forEach(function(button) {
                    var $btn = $('<button>', {
                        text: button.label,
                        class: [button.action, 'button'].join(' '),
                        'data-action': button.action,
                        'data-post-id': button.postId
                    });
                    this.$modalFooter.append($btn);
                }.bind(this));
            }
        },


        closeModal: function() {
            this.$modal.hide();
        },

        load_modal_content_scripts: function() {
            var thiz = this;
            //this.$modalFooter.append('<div class="progress-bar"></div>'); // Add progress bar initially
            var thiz = this;

            $('.close.button').on('click', (e)=>{
                thiz.closeModal()
            });

            $('.modal-footer button').on('click', function(e) {
                e.preventDefault();
                $('.progress-bar').show();
                thiz.handleAction(e);
            });

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
