<?php
class meetjitsi{

    public function __construct()
    {
        $modules = get_option('cgs_modules', array());
        $modules['meetjitsi']=array('name'=>'Jitsi Meet','class'=>'meetjitsi');
        update_option('cgs_modules', $modules);
        add_shortcode('meetjitsi', array($this, 'html'));
    }

    public function the_user(){
        $user = wp_get_current_user();
        echo $user->display_name;
    }
    public function the_email(){
        $user = wp_get_current_user();
        echo $user->user_email;
    }
    public function the_room(){
        $jitsi_room = get_post_meta(get_the_ID(), 'jitsi_room', true);
        if(empty($jitsi_room)){
            $jitsi_room = sanitize_title(get_bloginfo('name').'_'.get_the_title());
            update_post_meta(get_the_ID(), 'jitsi_room', $jitsi_room);
        }
        echo $jitsi_room;
    }

    public function html(){
        $jitsi_domain = get_option('options_jitsi_server');
        if(strpos($jitsi_domain, 'http') === 0){
            $jitsi_domain = parse_url($jitsi_domain, PHP_URL_HOST);
        }
        $jitsi_api_url = 'https://'.$jitsi_domain.'/external_api.js';
        ob_start();
        ?>
<div id="jitsi-container" style="width: 100%; height: 150px;margin-top:-5px"></div>
<script src="<?php echo $jitsi_api_url?>"></script>
<script>
    // Jitsi Meet API
    // Jitsi Meet IFrame API dokumentation
    //https://jitsi.github.io/handbook/docs/dev-guide/dev-guide-iframe/
    //https://github.com/jitsi/jitsi-meet/blob/master/config.js
    //https://github.com/jitsi/jitsi-meet/blob/master/interface_config.js
    const domain =  '<?php echo $jitsi_domain?>';
    const options = {
        roomName: "<?php $this->the_room();?>",
        width: "100%",
        height: 150,
        parentNode: document.querySelector('#jitsi-container'),
        userInfo: {
            email: '<?php $this->the_email();?>',
            displayName: '<?php $this->the_user();?>'
        },
        configOverwrite: {
            prejoinPageEnabled: false,
            enableWelcomePage:false,
            startWithAudioMuted: false,
            startWithVideoMuted: true,
            defaultBackground: 'blur',
            disableProfile: true,
            disableRemoteMute: true,
            readOnlyName: true,
            prejoinConfig:{
                enabled: false,
            }
            // Weitere Konfigurationsoptionen hinzufügen
        },
        interfaceConfigOverwrite: {
            TOOLBAR_BUTTONS: [
                'microphone', 'camera',
                'select-background', 'desktop', 'fullscreen'
            ],
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            ENABLE_DIAL_OUT:false,
            GENERATE_ROOMNAMES_ON_WELCOME_PAGE:false,
            HIDE_INVITE_MORE_HEADER:true,
            DISABLE_RINGING:true,
            PROVIDER_NAME: 'rpi-virtuell',
            RECENT_LIST_ENABLED: false,
            SHOW_BRAND_WATERMARK: false,
            VIDEO_QUALITY_LABEL_DISABLED: true,
            TILE_VIEW_MAX_COLUMNS: 10,
            SHOW_POWERED_BY:false,
            SHOW_PROMOTIONAL_CLOSE_PAGE:false,
            DISABLE_TRANSCRIPTION_SUBTITLES:true,
            DEFAULT_WELCOME_PAGE_LOGO_URL: 'image/watermark.svg',
            JITSI_WATERMARK_LINK: '/',


            // Weitere Interface-Konfigurationen hinzufügen
        }
    };
    const api = new JitsiMeetExternalAPI(domain, options);
    api.executeCommand('overwriteConfig', {
        toolbarButtons: [
                'microphone', 'camera','witheboard',
                'select-background', 'desktop', 'fullscreen'
        ],
        disableRemoteMute: true,
    });

    // Benutzerdefinierte API-Methoden aufrufen
    api.executeCommand('displayName', '<?php $this->the_user();?>');
    api.executeCommand('toggleWhiteboard');
    api.executeCommand('toggleWhiteboard');
    //api.executeCommand('toggleVideo');
    //api.executeCommand('toggleAudio');


</script>


<?php
        return ob_get_clean();
    }

}
