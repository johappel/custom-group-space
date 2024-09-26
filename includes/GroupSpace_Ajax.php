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
                $response = $this->show_history();
                break;
            case 'set-initialmeeting':
                $response = $this->set_initial_meeting_data();
                break;
            // Fügen Sie hier weitere Aktionen hinzu
            default:
                $response = $this->handle_custom_actions($action);

        }

        wp_send_json($response);
    }

    private function set_initial_meeting_data() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $groupPad->set_initial_meeting();
        return array('success' => true, 'message' => 'Ich habe die Daten aus der Agenda für die <a href="?">Gruppenbeschreibung</a> übernommen. Bitte überprüfe das Ergebnis.');
    }

    private function handle_custom_actions($action)
    {
        $response = array('success' => false, 'message' => 'Aktion nicht gefunden');

        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);

        $prompts = get_field('prompts', 'options');
        foreach ($prompts as $prompt) {
            if ($prompt['active']) {
                if ($prompt['key'] == $action) {
                    $response = $this->handle_ai_action($prompt, $groupPad, $post_id);
                }
            }
        }

        return $response;

    }
    private function handle_ai_action($prompt_arr, $groupPad, $group_id){
        $padID = $groupPad->get_group_padID();
        $messages = $groupPad->getChatHistory($padID);

        $etherpad = "\n\n#Etherpadinhalt:\n".$groupPad->getText($padID);
        $chat = "\n\n#Chatchachrichten:\n".$messages;



        $label = (string) $prompt_arr['label'];
        $message= (string) $prompt_arr['message'];
        $use_context = $prompt_arr['context'];
        $output = (string) $prompt_arr['output'];
        $prompt = (string) $prompt_arr['prompt'];

        $is_onetime= $prompt_arr['singleplay'];
        if(get_post_meta($group_id, 'ai_'.$prompt_arr['key'], true) && $is_onetime){
            return array('success' => false, 'message' => 'Diese Aktion kann nur einmal ausgeführt werden');
        }
        if($prompt_arr['key'] == 'log'){
            $empty_agenda = get_field('etherpad_agenda_template', 'options');
            $pre_prompt = "Für das Meeting wurde deiner Gruppe folgendes Agendaformular zur Verfügung gestellt: \n\n```";
            $pre_prompt .= $empty_agenda .'```';
            $pre_prompt .= "\n\Dieses Formular wurde ausgefüllt: \n\n";
            $prompt = $pre_prompt.$prompt;
        }

        foreach ($use_context as $context) {
            switch ($context){
                case 'challenge':
                    $prompt .= $groupPad->get_challenge();
                    break;
                case 'goal':
                    $prompt .= $groupPad->get_goal();
                    break;
                case 'history':
                    $prompt .= $groupPad->get_history();;
                    break;
                case 'pad':
                    $prompt .= $etherpad;
                    break;
                case 'chat':
                    $prompt .= $chat;
                    break;

                default:
                    $prompt = '';
            }
        }

        $response = array('success' => false);


        if(!empty($prompt)){
            $answer = $groupPad->ai()->generateText($prompt);


            error_log('AI: '.$answer);
            error_log('Output: '.$output);
            switch ($output){
                case 'chat':
                    $groupPad->appendChatMessage($padID, $answer, $groupPad->botID());
                    $response['success'] = true;
                    break;
                case 'pad':
                    $parsedown = new Parsedown();
                    $answer = $parsedown->text($answer);
                    $groupPad->appendHTML($padID, "<hr><h1>$label (AI)<h1>\n\n".$answer);
                    $response['success'] = true;
                    break;
                case 'history':
                    $groupPad->add_history($answer);
                    $response['success'] = true;
                    break;
                case 'progress':
                    $groupPad->add_progress($answer);
                    $message = $groupPad->get_progress_feedback();
                    $groupPad->appendChatMessage($padID, $message, $groupPad->botID());
                    $response['success'] = true;
                    break;
                case 'comment':
                    $groupPad->add_comment($answer);
                    $response['success'] = true;
                    break;
                case 'message':
                    $message=$answer;
                    //markdown answer to html
                    $parsedown = new Parsedown();
                    $message = $parsedown->text($message);
                    $response['success'] = true;
                    break;
                default:
                    $context = '';

            }


        }



        if($message){
            $response['message'] = $message;
        }


        return $response;

    }
    private function show_history() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $history = $groupPad->get_history();
        // convert Markdown to HTML
        $parsedown = new Parsedown();
        $markdown = $parsedown->text($history);
        return array('success' => true, 'message' => $markdown);
    }
    private function set_agenda() {



        $post_id = $_POST['post_id'];
        $group = get_post($post_id);
        $current_user = wp_get_current_user();
        $groupPad = new GroupPad($post_id);

        $initialmeeting = get_post_meta($post_id, 'group_initial_meeting', true);
        if(!$initialmeeting){
            $groupPad->set_constitutional_Agenda();
            return array('success' => true);
        }


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
