<?php
/**
 * Plugin Name: Custom Gruppenraum Plugin
 * Description: Fügt eine spezielle Ansicht für Gruppenräume hinzu.
 * Version: 1.1
 * Author: Joachim Happel
 */

if (!defined('ABSPATH')) {
    exit; // Direktzugriff verhindern
}

require_once plugin_dir_path(__FILE__). '/vendor/autoload.php';
require_once('includes/GroupSpace_Ajax.php');


class Custom_GroupSpace_Plugin {

    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rules'], 99999);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('template_include', [$this, 'load_custom_template']);
        add_filter( 'body_class', [$this, 'add_group_space_class'] );
        add_action('wp_enqueue_scripts', [$this, 'enqueue_custom_scripts']);

        $files = glob(plugin_dir_path(__FILE__) . 'module/*.php');
        foreach ($files as $file) {
            require_once $file;
            $class = basename($file, '.php');
            new $class();
        }
    }

    public function add_group_space_class( $classes ) {
        if (get_query_var('group-space')) {
            $classes[] = 'group-space';
        }
        return $classes;
    }
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^group_post/([^/]*)/group-space/?$',
            'index.php?pagename=group_post/$matches[1]&group-space=1',
            'top'
        );
    }

    public function add_query_vars($vars) {
        $vars[] = 'group-space';
        return $vars;
    }

    public function load_custom_template($template) {
        /**
         * Wenn der Benutzer eingeloggt und Mitglied der Gruppe ist, wird die Lernraum-Vorlage geladen.
         */
        if(!is_user_logged_in()&&!empty(get_query_var('group-space'))){
            wp_redirect(get_permalink(get_the_ID()));
            die();

        }
        if (!empty(get_query_var('group-space'))) {

            $group_members = get_post_meta(get_the_ID(), '_group_members', true);
            if (!is_array($group_members) || !in_array(get_current_user_id(), $group_members)) {
                wp_redirect(get_permalink(get_the_ID()));
                die();
            }
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

            $new_template = plugin_dir_path(__FILE__) . 'templates/template-group-space.php';

            if (readlink($new_template) || file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    // Optional: Custom Header und Footer laden
    public function enqueue_custom_scripts() {
        if (get_query_var('group-space')) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('group-space',plugin_dir_url(__FILE__).'css/group-space.css');
            wp_enqueue_style('group-toolbar',plugin_dir_url(__FILE__).'css/group-space-toolbar.css');
            wp_enqueue_script('group-space-frontend', plugin_dir_url(__FILE__) . 'js/group-space-toolbar.js', array('jquery'), '1.0.0', true);
            wp_localize_script('group-space-frontend', 'group_space_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('group_space_nonce')
            ));
        }
    }


}

new Custom_GroupSpace_Plugin();
