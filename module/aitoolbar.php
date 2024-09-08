<?php

class aitoolbar
{

    /**
     * Fügt via shortcode [group_space_toolbar]. eine Toolbar für Lernräume hinzu
     * @return string
     * @todo: über Optionspage dynamisieren
     */
    public function __construct(){
        add_shortcode('aitoolbar', [$this, 'group_space_render_toolbar']);
    }

    public function print_ai_toolbar(){
        $prompst = get_field('prompts', 'options');
        foreach ($prompst as $prompt) {
            if($prompt['active'])
            echo '<button class="group-space-action" data-action="'.$prompt['key'].'">
                <span class="dashicons '.$prompt['icon'].'"></span>
                <span>'.$prompt['label'].'</span>
            </button>';
        }
    }
    public function group_space_render_toolbar() {

        $post_id = get_the_ID();


        ob_start();
        ?>
        <div class="group-space-toolbar" data-post-id="<?php echo esc_attr($post_id); ?>">
            <button class="group-space-action" data-action="set-agenda">
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <span>Neue Agenda</span>
            </button>
            <button class="group-space-action" data-action="list-protocols">
                <span class="dashicons dashicons-admin-page"></span>
                <span>Protokolle</span>
            </button>
            <button class="group-space-action" data-action="whiteboard">
                <span class="dashicons dashicons-laptop"></span>
                <span>Whiteboard</span>
            </button>
            <div class="toolbar-spacer"><span></span>KI-Tools: </div>
            <?php $this->print_ai_toolbar(); ?>

<!--            <button class="group-space-action" data-action="action1">-->
<!--                <span class="dashicons dashicons-star-filled"></span>-->
<!--                <span>Aktion 1</span>-->
<!--            </button>-->
<!--            <button class="group-space-action" data-action="action2">-->
<!--                <span class="dashicons dashicons-heart"></span>-->
<!--                <span>Aktion 2</span>-->
<!--            </button>-->
<!--            <button class="group-space-action" data-action="chat_answer">-->
<!--                <span class="dashicons dashicons-format-chat"></span>-->
<!--                <span>Chat</span>-->
<!--            </button>-->
            <!-- Fügen Sie hier weitere Buttons hinzu -->
        </div>

        <div id="group-space-modal">
            <div class="toolbar-modal-content">
                <span class="close">&times;</span>
                <div class="inner_content">
                    <p></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

}
