<?php
require_once 'GroupPad.php';

class GroupSpace_Ajax {
    public function __construct() {
        add_action('wp_ajax_group_space_action', array($this, 'handle_ajax_request'));
        add_action('wp_ajax_nopriv_group_space_action', array($this, 'handle_ajax_request'));
    }

    public function handle_ajax_request() {
        check_ajax_referer('group_space_nonce', 'nonce');

        $action = isset($_POST['custom_action']) ? sanitize_text_field($_POST['custom_action']) : '';
        $response = array('success' => false, 'message' => '');

        switch ($action) {
            case 'set-agenda':
                $response = $this->set_agenda();
                break;
            case 'list-protocols':
                $response = $this->handle_action1();
                break;
            case 'whiteboard':
                $response = $this->handle_action2();
                break;
            // Fügen Sie hier weitere Aktionen hinzu
            default:
                $response['success'] = true;
                $response['message'] = 'Ungültige Aktion';

        }

        wp_send_json($response);
    }

    private function set_agenda() {
        $post_id = $_POST['post_id'];
        $group = get_post($post_id);
        $current_user = wp_get_current_user();

        $groupPad = new GroupPad($post_id);

        $tz = 'Europe/Berlin';
        $timestamp = time();
        $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
        $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
        $today =  $dt->format('d.m.Y');
        $now =  $dt->format('d.m.Y, H:i');

        $padID = $groupPad->get_group_padID();
        $agenda = get_field('etherpad_agenda_template', 'options');
        $agenda = str_replace('{TODAY}', $today, $agenda);
        $agenda = str_replace('{STARTTIME}', $now, $agenda);
        $agenda = str_replace('{GROUPNAME}',$group->post_title , $agenda);
        $agenda = str_replace('{USER}',$current_user->display_name, $agenda);

        error_log('Agenda: '.$agenda);

        $groupPad->setHTML($padID, $agenda,0,$groupPad->botID());

        return array('success' => true);

    }

    private function handle_chat_answer() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $padID = $groupPad->get_group_padID();
        $messages = $groupPad->getChatHistory($padID);

        $chatmessage = $groupPad->ai()->generateText($groupPad->get_prompt('chat',$messages));

        $chatmessage = 'Unterstütze User namentlich im Chat oder schreibe als Moderator einen kurzen (200 Zeichen) impulsgebenden Beitrage in den Chat. : '."\n\n#Bisheriger Chatverlauf:\n".$chatmessage;

        $groupPad->appendChatMessage($padID, $chatmessage, $groupPad->botID());
        return array('success' => true);
    }
    private function handle_action1() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $padID = $groupPad->get_group_padID();
        $messages = $groupPad->getChatHistory($padID);

        $context = "\n#Chatchachrichten:\n".implode("\n", $messages);
        $context .= "\n\n#Etherpadinhalt:\n".$groupPad->getText($padID);

        $prompt ="Lies die Chatnachrichten und den Inhalt des Etherpads und schreibe einen 
        kurzen Impuls (150 Zeichen) zur Weiterarbeit in unserer Gruppe als Chatnachricht $context";

        $chatmessage = $groupPad->ai()->generateText($prompt);

        $groupPad->appendChatMessage($padID, $chatmessage, $groupPad->botID());

        // Implementieren Sie hier die Logik für Aktion 1
        return array('success' => true, 'message' => 'Ich habe im CHat geantwortet');
    }

    private function handle_action2() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $padID = $groupPad->get_group_padID();
        $context = $groupPad->getText($padID);

        $summary = $groupPad->ai()->generateText($groupPad->get_prompt('short-summary',$context));

        $groupPad->appendText($padID, "\n\nAI --------------------- \n".$summary);

        $message = 'AI: ich habe eine Zusammenfassung erstellt und unten im Etherpad hinzugefügt';


        // Implementieren Sie hier die Logik für Aktion 2
        return array('success' => true, 'message' => $message);
    }

}

new GroupSpace_Ajax();
