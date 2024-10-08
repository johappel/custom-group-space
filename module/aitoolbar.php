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
            if($prompt['active']){
                switch($prompt['key']){
                    case 'progress':
                        $modal_titel = 'Analyse des Fortschritts';
                        break;
                    case 'tops-check':
                        $modal_titel = 'Feedback zu den Tagesordnungspunkten';
                        break;
                    case 'share':
                        $modal_titel = 'Mitteilung an die Community';
                        break;
                    default:
                        $modal_titel = ''.$prompt['label'];
                        break;
                }
                echo '<button class="group-space-action" data-action="'.$prompt['key'].'" data-title="'.$modal_titel.'" title="KI: '.$prompt['label'].'">
                    <span class="dashicons '.$prompt['icon'].'"></span>
                    <span>'.$prompt['label'].'</span>
                </button>';
            }
        }
    }
    public function group_space_render_toolbar() {

        $post_id = get_the_ID();

        $label = "Agenda erneuern";
        $initialmeeting = get_post_meta($post_id, 'group_initial_meeting', true);
        if($initialmeeting){
            $label = "Neue Agenda";
        }
        $group_tool_ai_integration = (boolean)  get_field('group_tool_ai_integration', $post_id);
        ob_start();
        ?>
        <div class="group-space-toolbar" data-post-id="<?php echo esc_attr($post_id); ?>">
            <button class="group-space-action" data-action="save-pad" data-title="Pad Version speichern" title="Pad Version speichern">
                <span class="dashicons dashicons-cloud-saved"></span>
                <span>Speichern</span>
            </button>
            <button class="group-space-action" data-action="list-saved-pads" data-title="Gespeicherte Pads wiederherstellen" title="Pad öffnen">
                <span class="dashicons dashicons-cloud-upload"></span>
                <span>Öffnen</span>
            </button>
            <button class="group-space-action" data-action="set-agenda" data-title="Vorhandene Agenda überschreiben" title="Leeres Agenda Formular öffnen">
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <span><?php echo $label;?></span>
            </button>

            <?php if($group_tool_ai_integration):?>

                <?php if($initialmeeting): ?>
                    <button class="group-space-action" data-action="list-protocols" data-title="Letzte Protokolle" title="Protokolle zeigen">
                    <span class="dashicons dashicons-admin-page"></span>
                        <span>Protokolle</span>
                    </button>

                    <div class="toolbar-spacer"><span></span>KI-Tools: </div>
                    <?php $this->print_ai_toolbar(); ?>
                <?php else: ?>
                    <button class="group-space-action" data-action="set-initialmeeting" data-title="Gruppenseite aus Agenda ergänzen" title="Daten für Gruppenseite übernehmen">
                        <span class="dashicons dashicons-saved"></span>
                        <span>Wir sind fertig</span>
                    </button>

                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div id="group-space-modal">
            <div class="toolbar-modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Modal Titel</h2>
                    <button class="close">&times;</button>
                </div>
                <div class="inner_content">
                    <!-- Inhalt wird hier eingefügt -->
                </div>
                <div class="modal-footer">
                    <!-- Buttons werden hier dynamisch eingefügt -->
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

}
