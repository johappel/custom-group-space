<?php

class etherpad
{
    public function __construct()
    {
        add_shortcode('etherpad', [$this, 'etherpad_shortcode']);
    }

    public function etherpad_shortcode($atts)
    {
        $pad= new GroupPad(get_the_ID());
        $url = $pad->get_group_pad_url();
        ob_start();
        ?>
            <div>
                <!-- Etherpad Iframe -->
                <iframe id="etherpadIframe" src="<?php echo $url; ?>" width="100%" height="600px"></iframe>
                [aitoolbar]
            </div>
        <!-- JavaScript zum Ändern der Etherpad-URL -->
        <script>
            function resizeEtherpadIframe(j) {
                const etherpadIframe = document.querySelector('#etherpadIframe');
                const toolbar = document.querySelector('.group-space-toolbar');
                const jitsi = document.querySelector('#jitsi-container');
                const jitsifr = document.querySelector('#jitsi-container > iframe');
                let marginbottom = 30;
                if (window.innerWidth < 782) {
                    if(jitsi && jitsifr){
                        jitsi.style.height = '90px';
                        jitsifr.style.height = '90px';
                    }
                    marginbottom = 45;
                }else{
                    if(jitsi && jitsifr){
                        jitsi.style.height = '150px';
                        jitsifr.style.height = '150px';
                    }
                }
                etherpadIframe.style.height = (window.innerHeight - etherpadIframe.offsetTop - toolbar.offsetHeight - marginbottom) + 'px';
                etherpadIframe.style.margin = '-5px 0 auto';
            }
            setTimeout(function() {
                resizeEtherpadIframe();
            }, 200);
            window.onresize = resizeEtherpadIframe;
        </script>
        <?php
        return do_shortcode(ob_get_clean());
    }
}
