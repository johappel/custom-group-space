<?php
use League\HTMLToMarkdown\HtmlConverter;
require_once 'GroupPad.php';

class GroupSpace_Ajax {

    public function __construct() {
        add_action('wp_ajax_group_space_action', array($this, 'handle_ajax_request'));
        add_action('wp_ajax_nopriv_group_space_action', array($this, 'handle_ajax_request'));
    }
    public function html_to_markdown($html) {
        $converter = new HtmlConverter();
        return $converter->convert($html);
    }
    public function markdown_to_html($markdown) {
        $converter = new Parsedown();
        return $converter->text($markdown);
    }

    public function handle_ajax_request() {
        check_ajax_referer('group_space_nonce', 'nonce');
        $action = isset($_POST['custom_action']) ? sanitize_text_field($_POST['custom_action']) : '';


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
            case 'save-pad':
                $response = $this->save_pad();
                break;
            case 'list-saved-pads':
                $response = $this->list_saved_pads();
                break;
            case 'open-pad':
                $timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : 0;
                $response = $this->open_pad($timestamp);
                break;
            // Fügen Sie hier weitere Aktionen hinzu
            default:
                $response = $this->handle_custom_actions($action);

        }

        wp_send_json($response);
    }

    private function save_pad() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $groupPad->save_pad();
        return array('success' => true, 'message' => 'Pad gespeichert');
    }
    private function list_saved_pads() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $pads = $groupPad->list_saved_pads();
        return array('success' => true, 'message' => $pads);
    }
    private function open_pad($timestamp) {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $content = $groupPad->get_pad($timestamp);
        $groupPad->setHTML($groupPad->get_group_padID(), $content);
        date_default_timezone_set('Europe/Berlin');
        $date = date('d.m.Y H:i', $timestamp);

        return array('success' => true, 'message' => 'Das Pad vom '.$date.' wurde wiederhergestellt');
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

        $post_id = (int) $_POST['post_id'];
        $modal_title = isset($_POST['modal_title']) ? sanitize_title($_POST['modal_title']) : '';
        if(!$post_id){
            return array('success' => false, 'message' => 'Gruppe nicht gefunden');
        }
        $groupPad = new GroupPad($post_id);

        $prompts = get_field('prompts', 'options');
        foreach ($prompts as $prompt) {
            if ($prompt['active']) {
                if ($prompt['key'] == $action) {
                    $response = $this->handle_ai_action($prompt, $groupPad, $post_id,$modal_title);
                    return $response;
                }
            }
        }
        switch ($action) {
            case 'do_share':
                $share = $groupPad->search_pad('Teilen');
                if($share) {
                    $groupPad->add_comment($share['content']);
                    $response['success'] = true;
                    $response['message'] =  'Folgender Kommentar wurde auf der Pinnwand veröffentlicht: <br>'.$share['content'];
                }
                break;
            case 'do_add_log':
                $protokoll =  $groupPad->search_pad('Protokoll');
                if($protokoll) {
                    $protokoll = $protokoll['content'];
                    $groupPad->add_history($protokoll);
                    $response['success'] = true;
                    $response['message'] =  'Protokoll wurde unter Protokollen gespeichert';
                }
                break;
            default:
                $response = array('success' => false, 'message' => 'Aktion wird nicht unterstützt');
        }

        return $response;

    }
    private function handle_ai_action($prompt_arr, \GroupPad $groupPad, $group_id, $modal_title=null) {

        $label = (string) $prompt_arr['label'];
        $message= (string) $prompt_arr['message'];
        $use_context = $prompt_arr['context'];
        $outputs[] = $prompt_arr['output'];
        $description = (string) $prompt_arr['description'];
        $prompt = (string) $prompt_arr['prompt'];
        $action = (string) $prompt_arr['key'];



        if($description && $modal_title){
            // Wenn ein Modal-Titel übergeben wurde, wird ein Modal geöffnet, der Titel wird als Überschrift verwendet,
            // eine Beschreibung der Aktion angezeigt und ein OK-Button, um diese Aktion tatsächlich auszuführen,
            // oder ein Abbrechen-Button um die Aktion nicht auszuführen.
            $response = array(
                'success' => true,
                'message' => $description,
                'buttons' => array(
                    array(
                        'label' => 'OK',
                        'action' => $action,
                        'postId' => $group_id
                    ),
                    array(
                        'label' => 'Abbrechen',
                        'action' => 'close'

                    )
                )
            );
            return $response;

        }


        $padID = $groupPad->get_group_padID();
        $messages = $groupPad->getChatHistory($padID);

        //$etherpad = "\n\n#Etherpadinhalt:\n".$groupPad->getHtml($padID);
        $chat = "\n\n# Chatchachrichten:\n".$messages;

        $is_onetime= $prompt_arr['singleplay'];
        if(get_post_meta($group_id, 'ai_'.$prompt_arr['key'], true) && $is_onetime){
            return array('success' => false, 'message' => 'Diese Aktion kann nur einmal ausgeführt werden');
        }
        $pads =[];
        switch ($action){
            case 'tops-check':
                $pads[] = $groupPad->search_pad('Tagesordnungspunkte');
                break;
            case 'meeting-feedback':
                $pads[] = $groupPad->search_pad('Agenda');
                $feedback = $groupPad->search_pad('Feedback');;
                if($feedback){
                    $feedback['title'] = 'Dieses Feedback hattest du bereits gegeben. Überarbeite es auf der Basis der aktuellen Meeting-Agenda';
                    $pads[] = $feedback;
                    $replace_output = 'Feedback';
                    $message = 'Ich habe das bisherige Feedback im Etherpad überarbeitet';
                }
                break;
            case 'progress':
                $outputs[] = 'progress';
                $outputs[] = 'message';
//            case 'do_add_log':
//                $protokoll =  $groupPad->search_pad('Protokoll');
//                $protokoll = $protokoll['content'];
//                if($protokoll) {
//                    $groupPad->add_history($protokoll);
//                    $response['success'] = true;
//                    $response['message'] =  'Protokoll wurde gespeichert';
//                    return $response;
//                }
                break;
            case 'log':
                $protokoll =  $groupPad->search_pad('Protokoll');
                if($protokoll && !empty(trim($protokoll['content']))) {
                    $protokoll = $protokoll['content'];
                    $response = array(
                        'success' => true,
                        'message' => '<strong>Kann ich das Protokoll Speichern?</strong>: <hr>'.$protokoll,
                        'buttons' => array(
                            array(
                                'label' => 'OK',
                                'action' => 'do_add_log',
                                'postId' => $group_id
                            ),
                            array(
                                'label' => 'Abbrechen',
                                'action' => 'close'

                            )
                        )
                    );
                    return $response;
                }
                $agenda_arr = $groupPad->search_pad('Agenda');
                $agenda_arr['content'] = ""; //keine personenbezogenen Daten
                $pads[] = $agenda_arr;
                $pads[] = $groupPad->search_pad('Ziel');
                $pads[] = $groupPad->search_pad('Tagesordnung');
                $pads[] = $groupPad->search_pad('Verabredungen');
                $pads[] = $groupPad->search_pad('Anmerkungen');
                $pads[] = $groupPad->search_pad('Reflekion');
                $pads[] = $groupPad->search_pad('Ergebnisse');
                $pads[] = $groupPad->search_pad('Termin');
                $pads[] = $groupPad->search_pad('Verschiedenes');


                break;
            case 'chat':

                $agenda_arr = $groupPad->search_pad('Agenda');
                $agenda_arr['content'] = ""; //keine personenbezogenen Daten
                $pads[] = $agenda_arr;
                $pads[] = $groupPad->search_pad('Ziel');
                $pads[] = $groupPad->search_pad('Tagesordnung');
                $pads[] = $groupPad->search_pad('Verabredungen');
                $pads[] = $groupPad->search_pad('Anmerkungen');
                $pads[] = $groupPad->search_pad('Reflekion');
                $pads[] = $groupPad->search_pad('Ergebnisse');

                break;
//            case 'do_share':
//                    $share = $groupPad->search_pad('Teilen');
//                    if($share) {
//                        $groupPad->add_comment($share['content']);
//                        $response['success'] = true;
//                        $response['message'] =  'Folgender Kommentar wurde auf der Pinnwand veröffentlicht: <br>'.$share['content'];
//                        return $response;
//                    }
//                break;
            case 'share':
                $share = $groupPad->search_pad('Teilen');
                if($share && !empty(trim($share['content']))) {
                    $response = array(
                        'success' => true,
                        'message' => '<strong>Eure Mitteilung an die Community</strong>: <br>'.$share['content'].'<hr>Ich mache jetzt daraus einen Kommentar auf der Pinnwand.',
                        'buttons' => array(
                            array(
                                'label' => 'OK',
                                'action' => 'do_share',
                                'postId' => $group_id
                            ),
                            array(
                                'label' => 'Abbrechen',
                                'action' => 'close'

                            )
                        )
                    );
                    return $response;
                }else{
                    $agenda_arr = $groupPad->search_pad('Agenda');
                    $agenda_arr['content'] = ""; //keine personenbezogenen Daten
                    $pads[] = $agenda_arr;
                    $pads[] = $groupPad->search_pad('Ziel');
                    $pads[] = $groupPad->search_pad('Tagesordnung');
                    $pads[] = $groupPad->search_pad('Notizen');
                    $pads[] = $groupPad->search_pad('Ergebnisse');
                    $pads[] = $groupPad->search_pad('Protokoll');
                }

                break;
            default:


        }
        $pad_context = '';
        foreach ($pads as $pad) {
            if($pad['content']){
                $pad_context .= "<h2>".$pad['title']."</h2>".$pad['content'];
            }
        }


        foreach ($use_context as $context) {
            $prompt .= "\n\n";

            switch ($context){
                case 'goal':
                    $prompt .= "# Gesamtziel der Gruppe:\n\n".$groupPad->get_goal();
                    $prompt .= "\n\n";
                    break;
                case 'challenge':
                    $prompt .= "# Herausforderungen und Aufgaben:\n\n".$groupPad->get_challenge();
                    $prompt .= "\n\n";
                    break;
               case 'history':
                    $prompt .= "# Verlauf\n\n".$groupPad->get_history();
                    $prompt .= "\n\n";
                    break;
                case 'progress':
                    $prompt .= "\n\n---\n\n# Bereits ausgefülltes Meeting Agenda Formular:\n";
                    $prompt .= $this->html_to_markdown($pad_context);
                    $prompt .= "\n\n---\n\n# Zum Vergleich Leeres Agenda Formular:\n\n";
                    $prompt .= $this->html_to_markdown(html_entity_decode(get_field('etherpad_agenda_template', 'options')));
                    $prompt .= "\n\n\n\n---\n\n# Deine bisherige Fortschrittsanalysen\n\n".$groupPad->get_progress_feedback();
                    $prompt .= "\n\n---\n\n";
                    break;
                case 'chat':
                    $prompt .= $chat;
                    $prompt .= "\n\n---\n\n";
                    break;
                case 'pad':
                    $prompt .= "# Kontext Etherpad:\n\n".$this->html_to_markdown($pad_context);
                    break;

                default:
                    $prompt = '';
            }
        }

        $response = array('success' => false);

        if(get_option('options_debug_ai_prompts') ) {
            $response['success'] = true;
            $message = '<h3>Prompt:</h3>'. "\n\nDeine Aufgabe:\n" . $this->markdown_to_html($prompt);
        }

        if(!empty($prompt)){
            $prompt ="\n\nDeine Aufgabe:\n".$prompt;
            try {
                $answer = $groupPad->ai()->generateText($prompt);
            } catch (Exception $e) {
                $response['success'] = true;
                $response['message'] = 'Fehler bei der Kommunikation mit der KI: '.$e->getMessage();
                return $response;
            }


            if(get_option('options_debug_ai_answers')) {
                $response['success'] = true;
                $response['message'] = $message .= '<h3>Antwort:</h3>'.$this->markdown_to_html($answer);
                return $response;
            }

            //Protokoll speichern
//            if($prompt_arr['key'] == 'log'){
//                $groupPad->add_history($answer);
//            }

            foreach ($outputs as $k=>$output) {

                switch ($output) {
                    case 'chat':
                        $groupPad->appendChatMessage($padID, $answer, $groupPad->botID());
                        $response['success'] = true;
                        break;
                    case 'pad':
                        $parsedown = new Parsedown();
                        $answer = $parsedown->text($answer);
                        $groupPad->appendHTML($padID, "<hr><h1>$label (AI)<h1>\n\n" . $answer);
                        $response['success'] = true;
                        break;
                    case 'history':
                        $groupPad->add_history($answer);
                        $response['success'] = true;
                        break;
                    case 'progress':
                        $groupPad->add_progress($answer);
                        $response['success'] = true;
                        break;
                    case 'comment':
                        $groupPad->add_comment($answer);
                        $response['success'] = true;
                        break;
                    case 'message':
                        $message = $answer;
                        //markdown answer to html
                        $parsedown = new Parsedown();
                        $message = $parsedown->text($message);
                        $response['success'] = true;
                        break;
                    default:
                        $context = '';

                }
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
        $groupPad = new GroupPad($post_id);

        $initialmeeting = get_post_meta($post_id, 'group_initial_meeting', true);
        if(!$initialmeeting){
            $groupPad->set_constitutional_Agenda();
        }else{
            $agenda = $this->get_empty_agenda($groupPad);
            $padID = $groupPad->get_group_padID();
            $groupPad->setHTML($padID, $agenda,0,$groupPad->botID());
        }
        return array('success' => true);

    }

    public function get_empty_agenda($groupPad)
    {

        $post_id = $groupPad->get_group_id();
        $tz = 'Europe/Berlin';
        $timestamp = time();
        $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
        $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
        $today =  $dt->format('d.m.Y');
        $now =  $dt->format('d.m.Y, H:i');

        $current_user = wp_get_current_user();
        $group = get_post($post_id);


        $agenda = get_field('etherpad_agenda_template', 'options');
        $agenda = str_replace('{TODAY}', $today, $agenda);
        $agenda = str_replace('{STARTTIME}', $now, $agenda);
        $agenda = str_replace('{GROUPNAME}',$group->post_title , $agenda);
        $agenda = str_replace('{USER}',$current_user->display_name, $agenda);

        return $agenda;
    }
    /**
     * only example
     */
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

    /**
     * only example
     */
    private function handle_action1() {
        $post_id = $_POST['post_id'];
        $groupPad = new GroupPad($post_id);
        $padID = $groupPad->get_group_padID();
        $messages = $groupPad->getChatHistory($padID);

        $context = "\n# Chatchachrichten:\n".implode("\n", $messages);
        $context .= "\n\n# Etherpadinhalt:\n".$groupPad->getText($padID);

        $prompt ="Lies die Chatnachrichten und den Inhalt des Etherpads und schreibe einen 
        kurzen Impuls (150 Zeichen) zur Weiterarbeit in unserer Gruppe als Chatnachricht $context";

        $chatmessage = $groupPad->ai()->generateText($prompt);

        $groupPad->appendChatMessage($padID, $chatmessage, $groupPad->botID());

        // Implementieren Sie hier die Logik für Aktion 1
        return array('success' => true, 'message' => 'Ich habe im CHat geantwortet');
    }
    /**
     * only example
     */
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
