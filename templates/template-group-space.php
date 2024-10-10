<?php
/*
Template Name: Gruppenraum Template
*/

get_header('gruppenraum'); // Optional: Spezieller Header für Gruppenraum

while ( have_posts() ) : the_post();
    if(get_option('options_is_videoconference_header'))
    {
        $videoconference = get_field('group_tool_videoconference', get_the_ID());
        if($videoconference=='jitsi' or empty($videoconference)){
            echo do_shortcode('[meetjitsi]');
        }else{
            $group = get_post(get_the_ID());
            $blog= get_bloginfo('blogname');
            $videoconference_url = get_field('group_tool_videoconference_url', get_the_ID());
            if(empty($videoconference_url)){
                $videoconference_url = get_option('options_videoconference_default_url', 'https://meet.jit.si');
            }
            $videoconference_label = get_field('group_tool_videoconference_label', get_the_ID());
            if(empty($videoconference_label)){
                $videoconference_label = get_option('options_videoconference_default_label', 'Jitsi Meet');
                $videoconference_label = 'Jitsi Meet';
            }
            echo '<div class="ct-container-fluid group-space-header">';
            echo '<div class="blogname">' .$blog. '</div>';
            echo '<div class="conference-button"><a class="button" target="_blank" href="'.$videoconference_url.'" target="_blank"><span class="dashicons dashicons-microphone"></span> '.$videoconference_label.' Videokonferenz  öffnen</a></div>';
            echo '<div class="groupname">' . $group->post_title . '</div>';
            echo '</div>';

        }
    }
    echo do_shortcode('[etherpad]');


endwhile;

get_footer('gruppenraum'); // Optional: Spezieller Footer für Gruppenraum
