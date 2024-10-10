<?php
require_once 'Etherpad_API.php';
require_once 'OpenAIClient.php';
require_once plugin_dir_path(__DIR__) . '/vendor/autoload.php'; // Ensure autoload is included
//use LLPhant\OpenAIConfig;
//use LLPhant\Chat\OpenAIChat;
use League\HTMLToMarkdown\HtmlConverter;

class GroupPad extends Etherpad_API
{
    protected $base_url;
    protected $api_key;
    protected $group_id;
    protected $is_valid = false;
    protected $group_padName;
    protected $pad_groupID;
    protected $group_padID;
    protected $author_id;
    protected $bot_author_id;
    protected $bot_name;

    protected $api_version = '1.3.0';
    protected $api_url;
    protected $chat; //KI-Bot
    protected $mapper_prefix = 'ass';

    public function __construct($group_id=null){
        add_filter( 'https_ssl_verify', '__return_false' );

        if(!class_exists('OpenAIConfig')){
            require_once plugin_dir_path(__DIR__) . '/vendor/theodo-group/llphant/src/OpenAIConfig.php';
        }

//        $config = new OpenAIConfig();
//        $config->model = get_option('options_openai_model','gpt-4o-mini-2024-07-18');
//        $config->apiKey = get_option('options_openai_api_key');
//        if(!$config->apiKey){
//            new WP_Error('no_api_key', 'No OPENAI API Key found');
//            return;
//        }
//        $this->chat = new OpenAIChat($config);
        $system_prompt = "Du moderierst Schulentwicklung in evangelischen Schulen, förderst pädagogische Konzepte, " .
            "digitale Tools und KI-Einsatz. " .
            "Du vermittelst praxisnah Methoden, stärkst evangelische Werte, Vielfalt und interreligiösen Dialog. " .
            "Du kennst deutsche Bildungsstandards und schulrechtliche Rahmenbedingungen.";
        $model = get_option('options_openai_model','gpt-4o-mini-2024-07-18');
        $system_prompt = get_option('options_system_prompt',$system_prompt);
        $this->chat = new OpenAIClient($model);
        $this->chat->setSystemMessage($system_prompt);


        $this->api_key = get_option('options_pad_api_key');
        $this->base_url = get_option('options_pad_base_url');
        if(!$this->base_url){
            // create Admin notice
            new WP_Error('no_base_url', 'No Etherpad URL found');
            return;
        }
        $obj=parent::__construct($this->base_url, $this->api_key);
        if(is_array($obj) && $obj['error']){
            error_log($obj['error']);
            new WP_Error('api_error', $obj['error']);
            $this->is_valid = false;
        }

        if($group_id){
            $this->group_id = $group_id;
        }else {
            $this->group_id = (int)get_the_ID();
        }
        if(!$this->group_id){
            //should never happen
            error_log('No group ID found');
            return;
        }


        $this->is_valid = true;
        // KI-Bot
        $this->bot_author_id = get_option('etherpad_bot_author_id');
        $this->bot_name = get_option('options_bot_name','KI-Bot');
        if(!$this->bot_author_id){
            $slug = sanitize_title(get_bloginfo('name'));
            $this->bot_author_id = $this->createAuthorIfNotExistsFor($slug,$this->bot_name, $this->mapper_prefix);
            update_option('etherpad_bot_author_id', $this->bot_author_id);
            sleep(1);
        }
        $user = wp_get_current_user();
        // Author ID des aktuellen Benutzers
        $this->author_id = get_user_meta($user->ID, 'etherpad_author_id', true);
        if(!$this->author_id){
            $this->author_id = $this->createAuthorIfNotExistsFor($user->user_login,$user->display_name, $this->mapper_prefix);
            update_user_meta($user->ID, 'etherpad_author_id', $this->author_id);
        }

        // Group Pad ID
        $this->group_padName = get_post_meta($this->group_id, 'group_padName', true);
        if(!$this->group_padName || !is_string($this->group_padName )){
            //create random pad id
            $randomString = bin2hex(random_bytes(15));
            $this->group_padName = substr(strtr($randomString, '+/', '-_'), 0, 20);
            $this->createPad($this->group_padname, 'Neues GroupPad der Gruppe '.get_the_title($group_id), $this->bot_author_id);
            update_post_meta($group_id, 'group_padName', $this->group_padName);
        }

        $this->pad_groupID = get_post_meta($this->group_id, 'pad_groupID', true);
        if(!$this->pad_groupID || !is_string($this->pad_groupID)){
            $this->pad_groupID = $this->createGroup();
            update_post_meta($group_id, 'pad_groupID', $this->pad_groupID);
        }

        $this->group_padID = get_post_meta($this->group_id, 'group_padID', true);
        if(!$this->group_padID || !is_string($this->group_padID)){

            $this->group_padID = $this->createGroupPad($this->group_padName, $this->pad_groupID, $this->author_id);
            update_post_meta($group_id, 'group_padID', $this->group_padID);
        }
        $this->check_group_pad();

        $this->is_valid = true;
    }
    public function check_group_pad()
    {
        $bool = false;
        $history = $this->get_history();
        if(!$history){
            $agenda = $this->get_constitutional_Agenda(get_the_title($this->group_id));
            $this->setHTML($this->group_padID, $agenda);
            $this->add_history('Gruppe wurde eingerichtet');
            $bool = true;
        }
        return $bool;
    }
    /**
     * @return string
     * Returns the group pad URL
     */
    public function get_group_pad_url(){
        return $this->get_pad_url($this->group_padName);
    }
    public function get_group_id(){
        return $this->group_id;
    }
    public function get_group_padID(){
        return $this->group_padID;
    }
    public function ai($max_tokens=2000, $model=null, $structured=false){
        return new $this->chat;
//        $config = new OpenAIConfig();
//
//        $apiKey = get_option('options_openai_api_key');
//        if(!$model)
//            $model= get_option('options_openai_model','gpt-4o-mini');
//
//        $config->apiKey = $apiKey;
//        $config->model = $model;
//        $system_prompt = "Du moderierst Schulentwicklung in evangelischen Schulen, förderst pädagogische Konzepte, " .
//            "digitale Tools und KI-Einsatz. " .
//            "Du vermittelst praxisnah Methoden, stärkst evangelische Werte, Vielfalt und interreligiösen Dialog. " .
//            "Du kennst deutsche Bildungsstandards und schulrechtliche Rahmenbedingungen.";
//
////        $config->modelOptions=[
////            'max_tokens'=>$max_tokens,
////            'messages' => [
////                ["role"=>"system", "content"=>$system_prompt]
////            ],
////            "response_format" => $structured? ['type' => 'json_schema', 'json_schema' => ['strict' => 'true']] : ['type' => 'json_object']
////        ];
//        $this->chat = new OpenAIChat($config);
//        return new $this->chat;

    }
    public function botID(){
        return $this->bot_author_id;
    }
    public function bot_name(){
        return $this->bot_name;
    }
    /**
     * @param $padName
     * @return string
     * Returns the pad URL
     */
    public function get_pad_url($padName)
    {
        $query = $this->get_auth_session_url_query($this->createSession($this->pad_groupID, $this->author_id), $this->pad_groupID, $padName);
        return $this->base_url . '/' . $query;
    }

    /**
     * @param $group_padID
     * @return string
     * Returns the HTML content of the Etherpad
     */
    public function get_initial_meeting()
    {
        $meeting_timestamp = get_post_meta($this->group_id, 'group_initial_meeting', true);
        if($meeting_timestamp){
            return date('d.m.Y', $meeting_timestamp);
        }
        return false;
    }
    public function set_initial_meeting()
    {
        $html = $this->getHTML($this->group_padID);

        $config = new OpenAIConfig();
        $config->apiKey = get_option('options_openai_api_key');
        $config->model = get_option('options_openai_model','gpt-4o-mini');
        $config->response_format = '{ "type": "json_schema", "json_schema": {...} }';
        $config->temperature = 0.2;
        //$config->response_format = '{ "type": "json_object", "json_schema": {...} }';
        $chat = new OpenAIChat($config);

        // Konvertiere HTML in Markdown
        $converter = new League\HTMLToMarkdown\HtmlConverter();
        $markdown = $converter->convert($html);


        $default_prompt = "Extrahiere aus dem Inhalt Namen, Ziel, Herausforderungen und Aufgaben der Gruppe und gib sie als JSON-Objekt zurück in dieser Form zurück: ".
            "{ \"name\": \"(Name der Gruppe)\", \"goal\": \"(Ziel der Gruppe)\", \"challenge\": \"(Herausforderungen und Aufgaben)\" } \n\n # Kontext: {context}\n";

        $prompt = $this->get_assistant_prompt('initial_meeting_extraction',['context'=>$markdown],$default_prompt);


        $json = $chat->generateText($prompt);
        if(is_string($json)){
            $json = str_replace('```json', '', $json);
            $json = str_replace('```', '', $json);
            $data = json_decode($json, true);
        }else{
            $data = $json;
        }

        if($data){

            update_post_meta($this->group_id, 'group_initial_meeting', time());
            update_post_meta($this->group_id, 'group_title', $data['name']);
            update_post_meta($this->group_id, 'group_goal', $data['goal']);
            update_post_meta($this->group_id, 'group_content', $data['challenge']);
            update_post_meta($this->group_id, 'group_challenge', $data['challenge']);


            $group_post = get_post($this->group_id);

            $post_array = array();
            $post_array['ID'] = $group_post->ID;
            $post_array['post_content'] = $data['challenge'];
            $post_array['post_excerpt'] = $data['goal'];
            $post_array['post_title'] = $data['name'];
            $post_array['post_status'] = 'publish';
            $post_array['post_type'] = 'group_post';

            wp_update_post($post_array);

            return $data;
        }
        error_log('No Data');
    }
    public function get_goal(){
        $goal = get_post_meta($this->group_id, 'group_goal', true);
        if(!$goal){
            $challenge = $this->get_challenge();
            $goal = get_post_meta($this->group_id, 'group_goal', true);
        }
        return $goal;
    }
    public function get_challenge($force = false){
        $challenge = get_post_meta($this->group_id, 'group_challenge', true);
        if($challenge && !$force){
            return $challenge;
        }else{
            $group_post = get_post($this->group_id);
            $pinwall_post_id = get_post_meta($this->group_id, '_pinwall_post', true);
            if(!$force && empty($group_post->post_content) && $pinwall_post_id){
                $pinwall_post = get_post($pinwall_post_id);
                $kontext = $pinwall_post->post_content;
                $comments= get_comments(array('post_id' => $pinwall_post_id));
                if($comments){
                    $kontext .= "\n\nKommentare:\n";
                }
                foreach ($comments as $comment){
                    $kontext .= "\n\n".$comment->comment_content;
                }
                //Herausforderungen und Aufgaben
                $default_prompt = 'Folgender Beitrag hat dazu geführt, eine Arbeitsgruppe zu gründen: '.
                    "\n\n{context}\n\n----\n\n".
                    "Formuliere Herausforderungen und Aufgaben, die sich aus dem Inhalt und den Kommentaren ergeben";
                $prompt = $this->get_assistant_prompt('get_challenge_challenges',['context'=>$kontext],$default_prompt);
                $challenge = $this->ai()->generateText($prompt);

                //Name der Gruppe
                $default_prompt = "Gib der Arbeitsgruppe einen Namen, der das Ziel und die Herausforderungen widerspiegelt: {context}";
                $prompt = $this->get_assistant_prompt('get_challenge_name',['context'=>$challenge],$default_prompt);
                $title = $this->ai()->generateText($prompt);

                //Ziel der Gruppe
                $default_prompt = "Formuliere zu folgenden Herausforderungen und Aufgaben der Arbeitsgruppe ein prägnantes Ziel: {context}";
                $prompt = $this->get_assistant_prompt('get_challenge_goal',['context'=>$challenge],$default_prompt);
                $goal = $this->ai()->generateText($prompt);

            }else{
                $group_content = get_post_meta($this->group_id, 'group_content', true);

                $kontext = "Titel:\n\n". $group_post->post_title ."\n\nExerpt:\n\n". $group_post->post_excerpt ."\n\nKontent:\n\n".$group_content;
                $challenge = $this->ai()->generateText('Folgender Inhalt liegt für eine Arbeitsgruppe vor: '.
                    "\n\n".
                    $kontext.
                    "\n\n----\n\n".
                    "Formuliere Herausforderungen und Aufgaben, die sich aus dem Inhalt und den Kommentaren ergeben"
                );
                $title = $this->ai()->generateText("Gib der Arbeitsgruppe einen Namen, der das Ziel und die Herausforderungen widerspiegelt: ".$challenge);
                $goal = $this->ai()->generateText("Formuliere ein prägnantes Ziel zu folgenden Herausforderungen und Aufgaben: ".$challenge);
            }

            update_post_meta($this->group_id, 'group_goal', $goal);
            update_post_meta($this->group_id, 'group_content', $challenge);
            update_post_meta($this->group_id, 'group_challenge', "Ziel dieser Arbeitsgruppe\n\n".$goal."\n\nHerausforderungen:\n\n".$challenge);

            $group_post->post_content = $kontext;
            $group_post->post_excerpt = $goal;
            $group_post->post_title = $title;



            $post_array = array();
            $post_array['ID'] = $this->group_id;
            $post_array['post_content'] = $kontext;
            $post_array['post_excerpt'] = $goal;
            $post_array['post_title'] = $title;
            $post_array['post_status'] = 'publish';
            $post_array['post_type'] = 'group_post';

            wp_update_post($post_array);

            wp_update_post($group_post);
            return $challenge;
        }

    }

    public function save_pad(){
        $padhtml = $this->getHTML($this->group_padID);
        $entry = [];
        $entry['date'] = time();
        $entry['content'] = $padhtml;
        add_post_meta($this->group_id, 'group_pad', $entry);
    }
    public function get_pad($timestamp){
        $logs = get_post_meta($this->group_id, 'group_pad' );
        if($logs){
            foreach ($logs as $log){
                if($log['date'] == $timestamp){
                    return $log['content'];
                }
            }
        }

        return false;
    }
    public function list_saved_pads(){
        if(!get_post_meta($this->group_id, 'group_pad' ))
            $this->save_pad();
        $logs = get_post_meta($this->group_id, 'group_pad' );
        $history = "";
        if($logs){
            foreach ($logs as $log){
                date_default_timezone_set('Europe/Berlin');
                $date = date('d.m.Y H:i', $log['date']);
                $link = '<a class="pad-version-link" href="#'.$log['date'].'" data-post-id="'.$this->group_id.'" data-version="'.$log['date'].'">'.$date.'</a>';
                $history = "<li>".$link."</li>".$history;
            }
        }
        $history = "<h1>Gespeicherte Pads:</h1><ul>".$history;
        $history .= "</ul>";
        return $history;
    }
    public function add_progress($content){
        $entry = [];

        // round to 15 minutes
        $entry['date'] = round(time() / 900) * 900;

        $entry['content'] = $content;
        add_post_meta($this->group_id, 'group_progress', $entry);
    }
    public function get_progress_feedback()
    {
        $logs = get_post_meta($this->group_id, 'group_progress' );
        if($logs){
            foreach ($logs as $log){
                $date = date('d.m.Y H:i', $log['date']);
                $content = $log['content'];
                $progress .= "\n\n".$date.":\n".$content;
            }
        }
        return $progress;
    }

    public function get_assistant_prompt($function,$args ,$defaut_prompt=""){
        $assistants = get_field('assist', 'options');
        foreach ($assistants as $assistant){
            if($assistant['key'] == $function){

                foreach ($args as $key => $value){
                    $assistant['prompt'] = str_replace('{'.$key.'}', $value, $assistant['prompt']);
                }

                return $assistant['prompt'];
            }
        }
        return $defaut_prompt;
    }


    /**
     * @param $content
     * @return void
     * Adds a protocol entry to the group
     */
    public function add_history($content){
        $entry = [];
        // round to 15 minutes
        $entry['date'] = round(time() / 900) * 900;
        $entry['content'] = $content;
        add_post_meta($this->group_id, 'group_history', $entry);
    }
    /**
     * @return string
     * Returns the protocol history of the group
     */
    public function get_history(){
        $logs = get_post_meta($this->group_id, 'group_history' );
        if(!$logs){
            return false;
        }
        $history = "\n# Protokolle: \n";
        if($logs){
            foreach ($logs as $log){
                $date = date('d.m.Y H:i', $log['date']);
                $content = $log['content'];
                $history .= "\n\n".$date.":\n".$content;
            }
        }
        return $history;
    }

    public function add_comment($content){
        $post_id = get_post_meta($this->group_id, '_pinwall_post', true);
        if(!$post_id){
            $post_id = $this->group_id;
        }
        $pre_content = "<strong>Bericht aus dem Meeting der Arbeitsgruppe: ".get_the_title($this->group_id)."<strong><br>";
        wp_insert_comment(array(
            'comment_post_ID' => $post_id,
            'comment_approved' => 1,
            'comment_content' => $pre_content.$content,
            'comment_author' => 'KI-Bot',
            'comment_author_email' => 'bot@nomail.com'
        ));
    }

    public function set_constitutional_Agenda() {

        $post_id = $_POST['post_id'];
        $group = get_post($post_id);
        $groupPad = new GroupPad($post_id);

        $agenda = $this->get_constitutional_Agenda($group->post_title);
        $groupPad->setHTML($groupPad->group_padID, $agenda);

        return array('success' => true);

    }
    public function get_constitutional_Agenda($title){
        $tz = 'Europe/Berlin';
        $timestamp = time();
        $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
        $dt->setTimestamp($timestamp); //adjust the object to correct timestamp
        $today =  $dt->format('d.m.Y');
        $now =  $dt->format('d.m.Y, H:i');

        $current_user = wp_get_current_user();

        $agenda = get_field('etherpad_initial_template', 'options');
        $agenda = str_replace('{TODAY}', $today, $agenda);
        $agenda = str_replace('{STARTTIME}', $now, $agenda);
        $agenda = str_replace('{GROUPNAME}',$title , $agenda);
        $agenda = str_replace('{USER}',$current_user->display_name, $agenda);
        return $agenda;
    }
    /**
     * @param $padID
     * @param $html
     * @return array|bool
     * Returns the current and last content version of the etherpad
     */
    public function get_current_and_last_content_version(){
        if(!$this->is_valid){
            return false;
        }

        $padID = $this->group_padID;
        $version = date("ymdHi");
        $last_version = get_post_meta($this->group_id, 'group_pad_lastversion', true);
        $versions = (array)get_post_meta($this->group_id, 'group_pad_version', true);
        if($last_version){
            $last_version_text = $versions[$last_version];
        }


        if(!$last_version || $last_version + 5 < $version){
            $text = $this->getText($padID);
            $versions[$version] = $text;
            update_post_meta($this->group_id, 'group_pad_versions', $versions);
            update_post_meta($this->group_id, 'group_pad_lastversion', $version);
        }
        if(!empty($text)){
            return [$text, $last_version_text];
        }
        return false;

    }
    public function getAgendaProgress(){

    }
//    public function getProgress(){
//
//        $default_prompt = "Vergleiche den aktuellen Inhalt in einem EtherPad mit der älteren gespeicherten Version und
//        identifiziere den Fortschritt, der in der neuen Version erzielt wurde.
//        Achte dabei besonders auf Verbesserungen in der Argumentation, geplanter Vorhaben/Aufgaben, Klarheit und Struktur.
//        Formuliere dann eine kurze, prägnante Analyse des Fortschritts und gib konkrete Anregungen
//        zur weiteren Verfeinerung des Inhaltes, die die Schreiber leicht übernehmen oder anpassen können:";
//        $etherpad_progress_prompt = get_option('etherpad_progress_prompt', $default_prompt);
//
//        $versions = get_current_and_last_content_version();
//
//        $etherpad_progress_prompt ."\n\n# Aktuelle Version: \n {$versions[0]}\n\n";
//        $etherpad_progress_prompt ."# Ältere Version: \n {$versions[1]}";
//
//        $this->appendChatMessage($this->group_padID,'Mein Analyse gleich unten im Etherpad ist nur als Anregung gedacht und kann gerne angepasst werden.');
//
//
//        $responseText = $this->chat->generateText($etherpad_progress_prompt);
//
//        $this->appendText($this->group_padID, $responseText);
//
//
//    }

    public function get_prompt($prompt,$context)
    {
        $prompts = [
            'agenda-plausibility' => "
            Die folgende Agenda gehört zu einer Projektarbeitsgruppe, die ein **Gesamtziel** im Kontext formulierte **Herausforderungen** erreichen möchte.
            Überprüfe:
            - ist  Gesamtziel klar und verständlich formuliert?
            - trägt die Agenda und der Gruppenchat (siehe **Chatverlauf**) dazu bei , das Gesamtziel zu erreichen
            - steht das formulierte **(Teil)ziel** des Meetings im Einklang mit dem Gesamtziel?
            - sind die Verabredungen** und Zwischenergebnisse klar und verständlich formuliert?
            - sind die Verabredungen und Zwischenergebnisse hilfreich, um das Ziel des Meetings zu erreichen?
            - sind die Verabredungen und Zwischenergebnisse messbar und überprüfbar?            
            Formuliere eine kurze, prägnante Analyse zu den bisherigen Inhalten und gib konkrete Anregungen die helfen könnten, 
            Ziele, Verabredungen und Zwischenergebnisse zu verfeinern um die Ziele zu erreichen.",

            'progress' => "Vergleiche den aktuellen Inhalt in einem EtherPad mit der älteren gespeicherten Version und 
            identifiziere den Fortschritt, der in der neuen Version erzielt wurde. 
            Achte dabei besonders auf Verbesserungen in der Argumentation, geplanter Vorhaben/Aufgaben, Klarheit und Struktur. 
            Formuliere dann eine kurze, prägnante Analyse des Fortschritts und gib konkrete Anregungen 
            zur weiteren Verfeinerung des Inhaltes, die die Schreiber leicht übernehmen oder anpassen können:",

            'agenda-feedback' => "Gib ein kurzes Feedback zum bisherigen Verlauf des Meeting, 
            soweit es sich dieser aus den Mitschriften im EtherPad und im Chatverlauf erkennen lässt.
            Stelle Fragen, die die Gruppe dazu anregen, Ziele, Verabredungen und Zwischenergebnisse im weiteren Verlauf des Meetings zu formulieren und zu verfeinern.",

            'feedback' => "Analysiere die Inhalte im EtherPad und im Chatverlauf. Gib ein Feedback, das die bisherigen Ergebnisse zusammenfasst und offene Fragen und Herausforderungen benennt.",

            'brainstorm' => "Starte eine Brainstorming-Sitzung im EtherPad und sammle Ideen zu einem bestimmten Thema. 
            Achte darauf, dass alle Ideen aufgeschrieben werden und keine Idee als schlecht oder unpassend abgetan wird.",
            'aganda-summary' => "Fasse die Ergebnisse des Meetings in einem EtherPad zusammen.",

            'summary' => "Schreibe eine Zusammenfassung des Inhalts eines EtherPads. 
            Achte darauf, dass die Zusammenfassung klar und prägnant ist und die wichtigsten Punkte des Inhalts enthält.",

            'short-summary' => "Schreibe eine komprimierte Zusammenfassung (max. 500 Zeichen) des Inhalts eines EtherPads. 
            Achte darauf, dass die Zusammenfassung klar und prägnant ist und die wichtigsten Position enthält.",

            'group-progress' => "Fasse den bisherigen Chatverlauf zum Gruppenprozess zusammen und gib einen Überblick über 
            die bisherigen Diskussionen und Ergebnisse im Kontext von Gesamtziel und Herausforderungen.",

            'share' => "Teile eine Idee, einen Gedanken oder eine Information aus dem EtherPad, 
            der einer breiteren Gruppe von Menschen zugänglich gemacht werden soll und formuliere diese so, dass sie verständlich und ansprechend ist."

        ];

        $context = "\n---- \n#Kontext:\n$context ----\n";

        return $prompts[$prompt].$context;
    }

    public function extractHTML($html, $term) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $headings = $xpath->query("//h1|//h2|//h3|//h4|//h5|//h6");
        $results = [];

        foreach ($headings as $index => $heading) {
            if (stripos($heading->textContent, $term) !== false) {
                $title = $heading->textContent;
                $content = '';
                $currentNode = $heading->nextSibling;
                $headingLevel = substr($heading->nodeName, 1);

                while ($currentNode) {
                    if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                        preg_match('/h[1-6]/', $currentNode->nodeName) &&
                        substr($currentNode->nodeName, 1) <= $headingLevel) {
                        break;
                    }
                    $content .= $dom->saveHTML($currentNode);
                    $currentNode = $currentNode->nextSibling;
                }

                $results[] = [$title, trim($content)];
            }
        }

        return $results;
    }

    public function DOM_appendHTML($html, $search_term, $append_html="") {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $headings = $xpath->query("//h1|//h2|//h3|//h4|//h5|//h6");

        foreach ($headings as $heading) {
            if (stripos($heading->textContent, $search_term) !== false) {
                $currentNode = $heading->nextSibling;
                $headingLevel = substr($heading->nodeName, 1);

                while ($currentNode) {
                    $nextNode = $currentNode->nextSibling;
                    if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                        preg_match('/h[1-6]/', $currentNode->nodeName) &&
                        substr($currentNode->nodeName, 1) <= $headingLevel) {
                        break;
                    }
                    $currentNode = $nextNode;
                }

                $fragment = $dom->createDocumentFragment();
                @$fragment->appendXML($append_html);

                if ($currentNode) {
                    $currentNode->parentNode->insertBefore($fragment, $currentNode);
                } else {
                    $heading->parentNode->appendChild($fragment);
                }

                break;  // Nur den ersten gefundenen Abschnitt modifizieren
            }
        }

        return $dom->saveHTML();
    }

    public function DOM_replaceHTML($html, $search_term, $replace_html) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $headings = $xpath->query("//h1|//h2|//h3|//h4|//h5|//h6");

        foreach ($headings as $heading) {
            if (stripos($heading->textContent, $search_term) !== false) {
                $currentNode = $heading->nextSibling;
                $headingLevel = substr($heading->nodeName, 1);
                $nodesToRemove = [];

                while ($currentNode) {
                    if ($currentNode->nodeType === XML_ELEMENT_NODE &&
                        preg_match('/h[1-6]/', $currentNode->nodeName) &&
                        substr($currentNode->nodeName, 1) <= $headingLevel) {
                        break;
                    }
                    $nodesToRemove[] = $currentNode;
                    $currentNode = $currentNode->nextSibling;
                }

                // Entfernen der alten Inhalte
                foreach ($nodesToRemove as $node) {
                    $node->parentNode->removeChild($node);
                }

                // Einfügen des neuen Inhalts
                $fragment = $dom->createDocumentFragment();
                @$fragment->appendXML($replace_html);
                $heading->parentNode->insertBefore($fragment, $currentNode);

                break;  // Nur den ersten gefundenen Abschnitt ersetzen
            }
        }

        return $dom->saveHTML();
    }
    /**
     * Function to search for a specific term in the content of the Group-Pad pad and return the first pad that matches the search term.
     * @param string $term The term to search for in the pad content.
     * @return array An array containing the title and content of the first pad that matches the search term.
     */
    public function search_pad($term, $padID = null) {
        if(!$padID){
            $padID = $this->group_padID;
        }
        $html = get_transient('pad_'.$padID);
        if(!$html){
            $html = $this->getHTML($padID);
            set_transient('pad_'.$padID, $html, 20);
        }
        $html = $this->getHTML($padID);

        //$results = $this->searchHeadersByTerm($html, $term);
        $results = $this->extractHTML($html, $term);

        if(empty($results)|| count($results) == 0){
            return ['title'=>"", 'content'=>""];
        }

        $result['title'] = $results[0][0];
        $result['content'] = $results[0][1];

        //$result['content'] = '<h1>'.$result['title'].'</h1>'.$result['content'];
        return $result;

    }
    public function append_pad($term, $newContent, $padID = null) {
        if(!$padID){
            $padID = $this->group_padID;
        }

        $html = get_transient('pad_'.$padID);
        if(!$html){
            $html = $this->getHTML($padID);
            set_transient('pad_'.$padID, $html, 20);
        }
        $newHtml = $this->DOM_appendHTML($html, $term, $newContent);
        //$this->setHTML($padID, $newHtml);
        return $newHtml;

    }
    public function replace_pad($term, $newContent, $padID = null) {
        if(!$padID){
            $padID = $this->group_padID;
        }

        $html = get_transient('pad_'.$padID);
        if(!$html){
            $html = $this->getHTML($padID);
            set_transient('pad_'.$padID, $html, 20);
        }
        $newHtml = $this->DOM_replaceHTML($html, $term, $newContent);
        //$this->setHTML($padID, $newHtml);
        return $newHtml;

    }
    public function html_to_markdown($html) {
        $converter = new HtmlConverter();
        return $converter->convert($html);
    }
    public function markdown_to_html($markdown) {
        $converter = new Parsedown();
        return $converter->text($markdown);
    }


}
