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

        $today = date('Y-m-d');
        $post_id = (int) $_POST['post_id'];

        if($today === get_post_meta($post_id, '_group_space_action_'.$action, true)){
            return array('success' => false, 'message' => 'Diese Aktion kann nur einmal am Tag ausgeführt werden');
        }

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
                    $link = $groupPad->add_comment($this->$share['content']);
                    $response['success'] = true;
                    $response['message'] =  'Folgender Kommentar wurde auf der Pinnwand veröffentlicht: <br>'.$share['content'].'<br>'.$link;
                }
                update_post_meta($post_id, '_group_space_action_'.$action, $today);
                break;
            case 'do_add_log':
                $protokoll =  $groupPad->search_pad('Protokoll');
                if($protokoll) {
                    $protokoll = $protokoll['content'];
                    $groupPad->add_history($protokoll);
                    $response['success'] = true;
                    $response['message'] =  'Protokoll wurde unter Protokollen gespeichert';
                }
                update_post_meta($post_id, '_group_space_action_'.$action, $today);
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

        $is_onetime= $prompt_arr['singleplay'];
        if(get_post_meta($group_id, 'ai_'.$prompt_arr['key'], true) && $is_onetime){
            return array('success' => false, 'message' => 'Diese Aktion kann nur einmal ausgeführt werden');
        }
        $pads =[];
        if ($action !== 'chat'){
           // user sollen $actions maximal alle 5 Minuten ausführen können
            $wait_time = get_option('options_ai_wait_time', 5);
            $delay = $wait_time;
            if($action === 'tops-check'){
                // tops-check kann nur alle 60 Sekunden ausgeführt werden
                $wait_time = 1;

            }
            $delay = $wait_time * 60;

            $wait_time_message = ($wait_time>1)? $wait_time.' Minuten': $wait_time.' Minute';

            if(!empty(get_post_meta($group_id, '_ai_action_last_use_'.$action, true)) && (time() - get_post_meta($group_id, '_ai_action_last_use_'.$action, true)) < $delay){
                return array('success' => true, 'message' => 'Ich wurde gerade eben gleiche gerade gefragt. Wurde es in der Gruppe schon wahrgenommen und bearbeitet? Versuche es gerne in '.$wait_time_message.' erneut.');
            }
            update_post_meta($group_id, '_ai_action_last_use_'.$action, time());
        }

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
                $pads[] = $groupPad->search_pad('Notizen');
                $pads[] = $groupPad->search_pad('Verabredungen');


                break;
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
                    $chat_messages = $groupPad->getChatMessages($padID,2);
                    $prompt = "\n\n# Chatchachrichten:\n";
                    $new_messages = '';
                    $do_not_reply = false;
                    if($chat_messages){
                        $bot_name = get_option('options_bot_name', 'KI-Bot');
                        date_default_timezone_set('Europe/Berlin');
                        $messages = array();
                        foreach($chat_messages as $key => $msg){

                            $messageDate = (new DateTime())->setTimestamp((int)($msg['time']/1000))->format('Ymd');
                            $messageDateTime = (new DateTime())->setTimestamp((int)($msg['time']/1000))->format('Y-m-d H:i:s');
                            $today = (new DateTime())->format('Ymd');
                            $yesterday = (new DateTime('yesterday'))->format('Ymd');

                            //if ($messageDate === $today || $messageDate === $yesterday) {
                            if ($messageDate === $today ) {
                                $do_not_reply = false;
                                if($bot_name === $msg['userName']){
                                    $do_not_reply = true;
                                    continue;
                                }
                                $save_message = array(
                                    'time' => $messageDateTime,
                                    'userName' => $msg['userName'],
                                    'text' => $msg['text']
                                );
                                $messages[] = sprintf('[%s] %s: %s',$save_message['time'], $save_message['userName'], $save_message['text']);
                            }
                        }
                        $new_messages = implode("\n",$messages);
                    }
                    if(empty($new_messages) && $action === 'chat'){
                        $response['success'] = true;
                        $response['message'] = 'Es gibt keine (neuen) Chatnachrichten, auf die ich mich beziehen kann.';
                        return $response;
                    }else{
                        if($do_not_reply && $action === 'chat'){
                            $response['success'] = true;
                            $response['message'] = 'Ich habe bereits auf die letzte Chatnachricht geantwortet';
                            return $response;
                        }
                        $prompt .= $new_messages;
                    }
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
                        $link = $groupPad->add_comment($answer);
                        $message = 'Ich habe einen Kommentar veröffentlicht: <br>'.$link;
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
            $agenda_html = $this->get_empty_agenda($groupPad);
            $padID = $groupPad->get_group_padID();

            $groupPad->setHTML($padID, $agenda_html,0,$groupPad->botID());
        }
        return array('success' => true, 'message' => 'Agenda wurde erneuert');

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
        $parser = new Parsedown();
        return $parser->text($agenda);
    }

}

new GroupSpace_Ajax();
