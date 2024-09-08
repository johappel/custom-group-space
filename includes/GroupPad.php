<?php
require_once 'Etherpad_API.php';
require_once plugin_dir_path(__DIR__) . '/vendor/autoload.php'; // Ensure autoload is included
use LLPhant\OpenAIConfig;
use LLPhant\Chat\OpenAIChat;

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

    protected $api_version = '1.3.0';
    protected $api_url;
    protected $chat; //KI-Bot
    protected $mapper_prefix = 'ass';

    public function __construct($group_id=null){
        add_filter( 'https_ssl_verify', '__return_false' );

        if(!class_exists('OpenAIConfig')){
            require_once plugin_dir_path(__DIR__) . '/vendor/theodo-group/llphant/src/OpenAIConfig.php';


        }
        $config = new OpenAIConfig();
        $config->apiKey = get_option('options_openai_api_key');
        $config->model = get_option('options_openai_model','gpt-4o-mini');
        $this->chat = new OpenAIChat($config);

        $this->api_key = get_option('options_pad_api_key');
        if(!$this->api_key){
            new WP_Error('no_api_key', 'No OPENAI API Key found');
            return;
        }
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
        if(!$group_id){
            //should never happen
            error_log('No group ID found');
            return;
        }
        $this->is_valid = true;
        // KI-Bot
        $this->bot_author_id = get_option('etherpad_bot_author_id');
        if(!$this->bot_author_id){

            $slug = sanitize_title(get_bloginfo('name'));
            $this->bot_author_id = $this->createAuthorIfNotExistsFor($slug,'KI-Bot', $this->mapper_prefix);
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

        $this->is_valid = true;
    }
    /**
     * @return string
     * Returns the group pad URL
     */
    public function get_group_pad_url(){
        return $this->get_pad_url($this->group_padName);
    }
    public function get_group_padID(){
        return $this->group_padID;
    }
    public function ai(){
        return $this->chat;
    }
    public function botID(){
        return $this->bot_author_id;
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
    public function get_main_goal(){
        $goal = get_post_meta($this->group_id, 'group_exerpt', true);
        if(!$goal){
            return false;
        }else{
            $main_goal = get_post_meta($this->group_id, 'group_main_goal', true);
            if(!$main_goal){
                $kontext ="\nBeschreibung der Gruppe:";
                $kontext .= get_post_meta($this->group_id, 'group_context', true);
                $kontext .= "\n\nBeschreibung der Herausforderung: $goal";

                $main_goal = $this->getAiResponse('main-goal',$kontext);
                update_post_meta($this->group_id, 'group_main_goal', $main_goal);
            }
            return $main_goal;
        }
    }
    public function get_group_challenge(){
        $description = get_post_meta($this->group_id, 'group_context', true);
        if(!$description) {
            return false;
        }else{
            $challenge = get_post_meta($this->group_id, 'group_challenge', true);
            if(!$challenge){
                $goal = get_post_meta($this->group_id, 'group_exerpt', true);
                $kontext ="\nBeschreibung der Gruppe:";
                $kontext .= $description;
                $kontext .= "\n\nZiel: $goal";
                $challenge = $this->ai()->generateText('Mit dem folgenden Text hat sich die  Gruppe selbst beschrieben: '.$kontext."\n\nFormuliere prägnant, welche Herausforderungen und Aufgaben die Gruppe beschäftigen");
                update_post_meta($this->group_id, 'group-challenge', $challenge);
            }
            return $challenge;
        }
    }

    /**
     * @param $group_padID
     * @return string
     * Returns response from LLM
     */
    public function getAiResponse($key, $context = ''){
        $prompts = get_field('prompts', 'options');
        foreach ($prompts as $prompt) {
            if($prompt['key'] == $key){
                return $this->ai()->generateText($prompt['prompt'].$context);
                break;
            }
        }
        return false;
    }



    private function set_constitutional_Agenda() {

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
        $agenda = get_field('etherpad_initial_template', 'options');
        $agenda = str_replace('{TODAY}', $today, $agenda);
        $agenda = str_replace('{STARTTIME}', $now, $agenda);
        $agenda = str_replace('{GROUPNAME}',$group->post_title , $agenda);
        $agenda = str_replace('{USER}',$current_user->display_name, $agenda);

        $groupPad->setHTML($padID, $agenda);

        return array('success' => true);

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
    public function getProgress(){

        $default_prompt = "Vergleiche den aktuellen Inhalt in einem EtherPad mit der älteren gespeicherten Version und 
        identifiziere den Fortschritt, der in der neuen Version erzielt wurde. 
        Achte dabei besonders auf Verbesserungen in der Argumentation, geplanter Vorhaben/Aufgaben, Klarheit und Struktur. 
        Formuliere dann eine kurze, prägnante Analyse des Fortschritts und gib konkrete Anregungen 
        zur weiteren Verfeinerung des Inhaltes, die die Schreiber leicht übernehmen oder anpassen können:";
        $etherpad_progress_prompt = get_option('etherpad_progress_prompt', $default_prompt);

        $versions = get_current_and_last_content_version();

        $etherpad_progress_prompt ."\n\n# Aktuelle Version: \n {$versions[0]}\n\n";
        $etherpad_progress_prompt ."# Ältere Version: \n {$versions[1]}";

        $this->appendChatMessage($this->group_padID,'Mein Analyse gleich unten im Etherpad ist nur als Anregung gedacht und kann gerne angepasst werden.');


        $responseText = $this->chat->generateText($etherpad_progress_prompt);

        $this->appendText($this->group_padID, $responseText);


    }

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

}
