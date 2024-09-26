<?php
/**
 * Etherpad API Library for WordPress
 * author: Joachim Happel
 * @see https://etherpad.org/doc/v2.2.2/#_http_api
 */


class Etherpad_API {

    protected $api_version = '1.3.0';
    protected $api_url;
    protected $api_key;


    public function __construct($base_url, $api_key) {

        add_filter( 'https_ssl_verify', '__return_false' );
        //check if the base_url ends with a slash
        if (substr($base_url, -1) !== '/') {
            $base_url .= '/';
        }


        $response = wp_remote_get($base_url . 'api');
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }else{
            $body = wp_remote_retrieve_body($response);
            $currentVersion = json_decode($body, true)['currentVersion'];
            if(!version_compare($currentVersion ,$this->api_version, '>=')){
                return array('error' => 'API Version of the Etherpad mismatch. Expected: ' . $this->api_version . ' or higher. Got: ' . $currentVersion);
            }
        }
        $this->api_url = $base_url .'api/' . $this->api_version . '/';
        $this->api_key = $api_key;
        return $this;
    }

    protected function make_request($endpoint, $params, $return = '') {
        $url = $this->api_url . $endpoint;
        $params['apikey'] = $this->api_key;
        $params['headers'] = array('Content-Type' => 'multipart/form-data');
        $response = wp_remote_post($url, array(
            'body' => $params,
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('ResponseError: ' . $response->get_error_message());
            return array('error' => $response->get_error_message());
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if($data['code'] !== 0){
            error_log('Error: ' . $data['message']);
            return false;
        }else{
            if(!empty($return) && isset($data['data'][$return])){
                if($endpoint === 'getChatHistory'){
                    $messages = array();
                    foreach($data['data'][$return] as $key => $message){
                        $messages = array(
                            'time' => $message['time'],
                            'userName' => $message['userName'],
                            'text' => $message['text']
                        );
                        $messages[] = sprintf('[%s] %s: %s',$message['time'], $message['userName'], $message['text']);
                    }
                    return implode("\n",$messages);
                }
                return $data['data'][$return];
            }
            return $data['message']==='ok';
        }
    }
    public function saveRevision($padID, $rev = null) {
        $params = array('padID' => $padID);
        if ($rev) {
            $params['rev'] = $rev;
        }
        return $this->make_request('saveRevision', $params);
    }

    public function createSession($groupID, $authorID)
    {
        $params = array(
            'authorID' => $authorID,
            'groupID' => $groupID,
            'validUntil' => time() + 60 * 60 * 24
        );
        return $this->make_request('createSession', $params, 'sessionID');
    }

    public function deleteSession($sessionID)
    {
        $params = array(
            'sessionID' => $sessionID
        );
        return $this->make_request('deleteSession', $params);
    }

    public function get_auth_session_url_query($sessionID, $groupID = '',$padID = '', $lang = 'de')
    {
        $query = 'auth_session?sessionID=SESSION_ID&groupID=GROUP_ID&padName=PAD_NAME&lang=LANGUAGE';
        $query = str_replace('SESSION_ID', $sessionID, $query);
        $query = str_replace('GROUP_ID', $groupID, $query);
        $query = str_replace('PAD_NAME', $padID, $query);
        return   str_replace('LANGUAGE', $lang, $query);
    }

    public function copyPadWithoutHistory($sourceID, $destinationID, $force = false, $authorId = null) {
        $params = array(
            'sourceID' => $sourceID,
            'destinationID' => $destinationID,
            'force' => $force ? 'true' : 'false'
        );
        if ($authorId) {
            $params['authorId'] = $authorId;
        }
        return $this->make_request('copyPadWithoutHistory', $params );
    }
    public function copyPad($sourceID, $destinationID, $force = false) {
        $params = array(
            'sourceID' => $sourceID,
            'destinationID' => $destinationID,
            'force' => $force ? 'true' : 'false'
        );
        return $this->make_request('copyPad', $params );
    }

    public function createPad($padID, $text = '', $authorId = null) {
        $params = array(
            'padID' => $padID,
            'text' => $text
        );
        if ($authorId) {
            $params['authorId'] = $authorId;
        }
        return $this->make_request('createPad', $params);
    }

    public function deletePad($padID) {
        $params = array(
            'padID' => $padID
        );
        return $this->make_request('deletePad', $params);
    }
    public function createGroupPad($padName,$groupID, $authorId = null) {
        $params = array(
            'padName' => $padName,
            'groupID' => $groupID
        );
        if ($authorId) {
            $params['authorId'] = $authorId;
        }
        return $this->make_request('createGroupPad', $params, 'padID');
    }
    public function createGroup() {
        $params = array();
        return $this->make_request('createGroup', $params,'groupID');
    }

    public function createAuthor($name = null) {
        $params = array();
        if ($name) {
            $params['name'] = $name;
        }
        return $this->make_request('createAuthor', $params,'authorID');
    }
    public function getAuthorName($authorID) {
        $params = array();
        $params['authorID'] = $authorID;
        return $this->make_request('getAuthorName', $params, 'authorName');
    }
    public function createAuthorIfNotExistsFor($user_id,$name,$prefix='wp') {
        $params = array();
        $params['authorMapper'] = $prefix.'_'.$user_id;
        $params['name'] = $name;
        return $this->make_request('createAuthorIfNotExistsFor', $params, 'authorID');
    }

    public function appendChatMessage($padID, $text, $authorID, $time = null) {
        $params = array(
            'padID' => $padID,
            'text' => $text,
            'authorID' => $authorID
        );
        if ($time) {
            $params['time'] = $time;
        }
        return $this->make_request('appendChatMessage', $params);
    }

    public function appendText($padID, $text, $authorId = null) {
        $params = array(
            'padID' => $padID,
            'text' => $text
        );
        if ($authorId) {
            $params['authorId'] = $authorId;
        }
        $this->saveRevision($padID);
        return $this->make_request('appendText', $params);
    }

    public function getText($padID = null, $rev = null) {
        $params = array('padID' => $padID);
        if ($rev) {
            $params['rev'] = $rev;
        }
        return $this->make_request('getText', $params, 'text');
    }
    public function getHTML($padID, $rev = null) {
        $params = array('padID' => $padID);
        if ($rev) {
            $params['rev'] = $rev;
        }
        return $this->make_request('getHTML', $params, 'html');
    }
    public function appendHTML($padID, $html, $authorId = null){
        return $this->setHTML($padID, $html, 1, $authorId);
    }

    /**
     * @param $padID
     * @param $html
     * @param $append 0: replace, 1: append, -1: prepend
     * @param $authorId
     * @return array|bool|mixed|string
     */
    public function setHTML($padID, $html, $append=0, $authorId = null) {
        $html = html_entity_decode($html);
        $currenthtml = $this->getHTML($padID);
        $parts = explode('<body>',$currenthtml);
        $header = $parts[0];
        $body_footer = explode('</body>',$parts[1]);
        $body = $body_footer[0];
        $footer = $body_footer[1];

        if($append === 1){
            $html = $body .'<br><br>'. $html;
        }elseif($append === -1){
            $html = $html.'<br><br>'. $body;
        }
        $html = $header . '<body>' . $html . '</body>' . $footer;

        $params = array(
            'padID' => $padID,
            'html' => $html
        );
        if ($authorId) {
            $params['authorId'] = $authorId;
        }
        $this->saveRevision($padID);

        return $this->make_request('setHTML', $params);
    }

    public function getChatHistory($padID, $start = null, $end = null) {
        $params = array('padID' => $padID);
        if ($start !== null) {
            $params['start'] = $start;
        }
        if ($end !== null) {
            $params['end'] = $end;
        }
        return $this->make_request('getChatHistory', $params, 'messages');
    }
}
