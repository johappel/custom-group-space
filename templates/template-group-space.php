<?php
/*
Template Name: Gruppenraum Template
*/

get_header('gruppenraum'); // Optional: Spezieller Header für Gruppenraum

while ( have_posts() ) : the_post();
    // Deine Lernmodule hier
    //todo: in abhängigkeit von den Gruppen Einstellungen (post_meta:'group_space_tools') die Lernmodule anzeigen
    echo do_shortcode('[meetjitsi]');
    echo do_shortcode('[etherpad]');


endwhile;

get_footer('gruppenraum'); // Optional: Spezieller Footer für Gruppenraum
