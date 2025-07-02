<?php
/*
Plugin Name: Football Data
Description: Plugin for displaying football data via API
Version: 1.0.0
Author: Pavlo Oberemko
*/

if (!defined('ABSPATH')) exit;

define('SFD_VERSION', '1.0.0');
define('SFD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SFD_PLUGIN_URL', plugin_dir_url(__FILE__));

class SimpleFootballData {
    private $api_key = 'ac454ff29ef0434ca443a67c258f54eb';
    private $api_url = 'https://api.football-data.org/v4/';

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('football_form', [$this, 'football_form_shortcode']);
        require_once SFD_PLUGIN_PATH . 'ajax-handler.php';
    }

    public function enqueue_assets() {
        wp_enqueue_style('simple-football-styles', SFD_PLUGIN_URL . 'assets/css/style.css', [], SFD_VERSION);
        wp_enqueue_script('jquery');
        wp_enqueue_script('simple-football-form', SFD_PLUGIN_URL . 'assets/js/football-form.js', ['jquery'], SFD_VERSION, true);

        wp_localize_script('simple-football-form', 'sfd_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sfd_nonce')
        ]);
    }

    private function get_matches($atts) {
        $league_id = $atts['default_league'];
        $limit = intval($atts['default_limit']);
        $date_from = $atts['date_from'];
        $date_to = $atts['date_to'];

        if ((empty($date_from) && !empty($date_to)) || (!empty($date_from) && empty($date_to))) {
            return '<p class="football-error">Please provide both start and end dates.</p>';
        }

        $url = "competitions/{$league_id}/matches";
        $params = [];

        if (!empty($date_from) && !empty($date_to)) {
            $params['dateFrom'] = $date_from;
            $params['dateTo'] = $date_to;
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $data = $this->api_request($url);

        if (!$data || !isset($data['matches'])) {
            return "<p class='football-error'>" . esc_html($data['message'] ?? 'Error loading matches') . "</p>";
        }

        $matches = array_slice($data['matches'], 0, $limit);

        if (count($matches) === 0 && empty($date_from)) {
            return '<p>No data available for matches. Please try selecting a specific date range, as there may have been no matches in the last month.</p>';
        } elseif (count($matches) === 0) {
            return '<p>No data available for matches. Please try selecting a specific date range.</p>';
        }

        $output = '<div class="football-container">';
        $output .= '<h3>Matches</h3>';
        $output .= '<table class="football-table">';
        $output .= '<thead><tr><th>Date</th><th>Teams</th><th>Score</th><th>Status</th></tr></thead><tbody>';

        foreach ($matches as $match) {
            $date = !empty($match['utcDate']) ? date('d.m.Y H:i', strtotime($match['utcDate'])) : 'No date';
            $home = !empty($match['homeTeam']['name']) ? $match['homeTeam']['name'] : 'TBD';
            $home_crest_url = $match['homeTeam']['crest'] ?? '';
            $away = !empty($match['awayTeam']['name']) ? $match['awayTeam']['name'] : 'TBD';
            $away_crest_url = $match['awayTeam']['crest'] ?? '';
            $score = $this->format_score($match);
            $status = $match['status'] ?? '';

            $output .= "<tr><td>{$date}</td><td><div class='teams-wrapper'><div class='team'>";
            if (!empty($home_crest_url)) {
                $output .= "<img src='" . esc_url($home_crest_url) . "' alt='" . esc_attr($home) . "'>";
            }
            $output .= "<span>" . esc_html($home) . "</span></div><span class='team-divider'>vs</span><div class='team'>";
            if (!empty($away_crest_url)) {
                $output .= "<img src='" . esc_url($away_crest_url) . "' alt='" . esc_attr($away) . "'>";
            }
            $output .= "<span>" . esc_html($away) . "</span></div></div></td><td class='score'>{$score}</td><td><span class='status-{$status}'>" . esc_html($status) . "</span></td></tr>";
        }

        $output .= '</tbody></table></div>';
        return $output;
    }

    private function get_all_leagues() {
        $data = $this->api_request("competitions/");
        if (empty($data['competitions'])) return '';

        $output = '';
        foreach ($data['competitions'] as $league) {
            $output .= '<option value="' . esc_attr($league['id']) . '">' . esc_html($league['name']) . ' (' . esc_html($league['area']['name']) . ')</option>';
        }
        return $output;
    }

    private function api_request($endpoint) {
        $cache_key = 'sfd_' . md5($endpoint);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $response = wp_remote_get($this->api_url . $endpoint, [
            'headers' => ['X-Auth-Token' => $this->api_key],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) return false;

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

    private function format_score($match) {
        $home_score = $match['score']['fullTime']['home'] ?? null;
        $away_score = $match['score']['fullTime']['away'] ?? null;

        if ($home_score !== null && $away_score !== null) {
            return "{$home_score} - {$away_score}";
        }
        return '0 - 0';
    }

    public function football_form_shortcode($atts) {
        $atts = shortcode_atts([
            'default_league' => '2021',
            'default_type'   => 'matches',
            'default_limit'  => '40',
            'date_from'      => '',
            'date_to'        => '',
            'show_form'      => true,
        ], $atts);

        ob_start();

        if ($atts['show_form']) : ?>
            <div class="sfd-form-container">
                <h3 class="sfd-form-title">Select League and Dates</h3>
                <form id="footballForm" class="sfd-form">
                    <div class="form-field">
                        <label for="league">League:</label>
                        <select id="league" name="league" class="sfd-select">
                            <?php echo $this->get_all_leagues(); ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="date_from">From Date:</label>
                        <input type="date" id="date_from" name="date_from" class="sfd-input">
                    </div>
                    <div class="form-field">
                        <label for="date_to">To Date:</label>
                        <input type="date" id="date_to" name="date_to" class="sfd-input">
                    </div>
                    <div class="form-field">
                        <button type="submit" class="sfd-submit-button">Show Data</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div id="sfd-results" class="sfd-results">
            <div id="sfd-loading" class="sfd-loading"><p>Loading data...</p></div>
            <div id="sfd-content" class="sfd-content"><?php echo $this->get_matches($atts); ?></div>
        </div>

        <?php return ob_get_clean();
    }
}

new SimpleFootballData();

register_activation_hook(__FILE__, function() {
    new SimpleFootballData();
});
