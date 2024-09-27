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
        $prompts = get_field('prompts', 'options');
        foreach ($prompts as $prompt) {
            if($prompt['active'])
            echo '<button class="group-space-action" data-action="'.$prompt['key'].'">
                <span class="dashicons '.$prompt['icon'].'"></span>
                <span>'.$prompt['label'].'</span>
            </button>';
        }
    }
    public function group_space_render_toolbar() {

        $post_id = get_the_ID();

        $label = "Agenda erneuern";
        $initialmeeting = get_post_meta($post_id, 'group_initial_meeting', true);
        if($initialmeeting){
            $label = "Neue Agenda";
        }

        ob_start();
        ?>
        <div class="group-space-toolbar" data-post-id="<?php echo esc_attr($post_id); ?>">
            <button class="group-space-action" data-action="save-pad">
                <span class="dashicons dashicons-cloud-saved"></span>
                <span>Speichern</span>
            </button>
            <button class="group-space-action" data-action="list-saved-pads">
                <span class="dashicons dashicons-cloud-upload"></span>
                <span>Öffnen</span>
            </button>

            <button class="group-space-action" data-action="set-agenda">
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <span><?php echo $label;?></span>
            </button>

            <?php if($initialmeeting): ?>
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
            <?php else: ?>
                <button class="group-space-action" data-action="set-initialmeeting">
                    <span class="dashicons dashicons-saved"></span>
                    <span>Wir sind fertig</span>
                </button>

            <?php endif; ?>
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
