<?php
if (!defined('ABSPATH')) {
    exit;
}

function sfd_ajax_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sfd_nonce')) {
        wp_send_json_error(array('message' => 'Security error: invalid nonce.'));
        return;
    }

    try {
        $type = sanitize_text_field($_POST['type'] ?? 'matches');
        $league = sanitize_text_field($_POST['league'] ?? '2021');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        $allowed_types = array('matches', 'teams', 'standings');
        if (!in_array($type, $allowed_types)) {
            throw new Exception('Invalid data type.');
        }

        if (!class_exists('SimpleFootballData')) {
            throw new Exception('Class SimpleFootballData not found.');
        }

        $plugin = new SimpleFootballData();

        if (!method_exists($plugin, 'football_form_shortcode')) {
            throw new Exception('football_form_shortcode does not exist.');
        }

        $atts = array(
            'default_type' => $type,
            'default_league' => $league,
            'show_form' => false,
        );

        if (!empty($date_from)) {
            $atts['date_from'] = $date_from;
        }

        if (!empty($date_to)) {
            $atts['date_to'] = $date_to;
        }

        $result = $plugin->football_form_shortcode($atts);

        if (empty($result)) {
            throw new Exception('Empty result.');
        }

        wp_send_json_success($result);

    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}

add_action('wp_ajax_sfd_get_data', 'sfd_ajax_handler');
add_action('wp_ajax_nopriv_sfd_get_data', 'sfd_ajax_handler');
?>
