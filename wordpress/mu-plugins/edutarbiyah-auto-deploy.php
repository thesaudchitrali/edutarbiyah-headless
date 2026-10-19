<?php
/**
 * Plugin Name: EduTarbiyah Auto-Deploy to GitHub Actions
 * Plugin URI: https://edutarbiyah.com
 * Description: Automatically triggers GitHub Actions repository_dispatch to rebuild and deploy the headless Astro static site on WordPress post, page, or WooCommerce product updates.
 * Version: 1.0.0
 * Author: EduTarbiyah Engineering
 * License: GPL-2.0+
 */

defined('ABSPATH') || exit;

// Configuration Defaults
if (!defined('EDUTARBIYAH_GITHUB_REPO')) {
    define('EDUTARBIYAH_GITHUB_REPO', get_option('edutarbiyah_github_repo', ''));
}
if (!defined('EDUTARBIYAH_GITHUB_TOKEN')) {
    define('EDUTARBIYAH_GITHUB_TOKEN', get_option('edutarbiyah_github_token', ''));
}
if (!defined('EDUTARBIYAH_GITHUB_EVENT')) {
    define('EDUTARBIYAH_GITHUB_EVENT', 'edutarbiyah_build_deploy');
}

/**
 * Trigger deployment when content changes.
 */
function edutarbiyah_schedule_deploy($post_id = 0, $post = null, $update = null) {
    // Avoid drafts, autosaves, revisions
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if ($post && in_array($post->post_status, ['auto-draft', 'trash', 'inherit'])) {
        return;
    }

    // Debounce: prevent triggering multiple dispatches within 60 seconds
    $lock = get_transient('edutarbiyah_deploy_lock');
    if ($lock) {
        return;
    }
    set_transient('edutarbiyah_deploy_lock', true, 60);

    // Asynchronous non-blocking HTTP dispatch to GitHub Actions
    edutarbiyah_dispatch_github_action([
        'trigger_type' => 'content_updated',
        'post_id'      => $post_id,
        'post_title'   => $post ? $post->post_title : '',
        'timestamp'    => current_time('mysql'),
    ]);
}

add_action('save_post', 'edutarbiyah_schedule_deploy', 10, 3);
add_action('woocommerce_update_product', 'edutarbiyah_schedule_deploy', 10, 1);
add_action('woocommerce_new_product', 'edutarbiyah_schedule_deploy', 10, 1);
add_action('trashed_post', 'edutarbiyah_schedule_deploy', 10, 1);

/**
 * Sends repository_dispatch to GitHub API.
 */
function edutarbiyah_dispatch_github_action($client_payload = []) {
    $repo  = defined('EDUTARBIYAH_GITHUB_REPO') ? EDUTARBIYAH_GITHUB_REPO : get_option('edutarbiyah_github_repo');
    $token = defined('EDUTARBIYAH_GITHUB_TOKEN') ? EDUTARBIYAH_GITHUB_TOKEN : get_option('edutarbiyah_github_token');

    if (empty($repo) || empty($token)) {
        error_log('[EduTarbiyah Auto-Deploy] GitHub repo or PAT token not configured.');
        return false;
    }

    $url = "https://api.github.com/repos/{$repo}/dispatches";
    $body = wp_json_encode([
        'event_type'     => EDUTARBIYAH_GITHUB_EVENT,
        'client_payload' => $client_payload,
    ]);

    // Asynchronous non-blocking request so WordPress admin UI stays lightning fast
    $response = wp_remote_post($url, [
        'method'      => 'POST',
        'timeout'     => 5,
        'redirection' => 5,
        'httpversion' => '1.1',
        'blocking'    => false,
        'headers'     => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/vnd.github.v3+json',
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'EduTarbiyah-WordPress-AutoDeploy',
        ],
        'body'        => $body,
    ]);

    return true;
}

/**
 * Add Admin Tool to trigger manual build or configure settings.
 */
add_action('admin_menu', function () {
    add_management_page(
        'EduTarbiyah Deploy',
        'Deploy Frontend',
        'manage_options',
        'edutarbiyah-deploy',
        'edutarbiyah_deploy_admin_page'
    );
});

function edutarbiyah_deploy_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';

    if (isset($_POST['edutarbiyah_trigger_now']) && check_admin_referer('edutarbiyah_deploy_action')) {
        delete_transient('edutarbiyah_deploy_lock');
        $success = edutarbiyah_dispatch_github_action([
            'trigger_type' => 'manual_admin_trigger',
            'user'         => wp_get_current_user()->user_login,
            'timestamp'    => current_time('mysql'),
        ]);

        if ($success) {
            $notice = '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Build and deploy triggered via GitHub Actions.</p></div>';
        } else {
            $notice = '<div class="notice notice-error is-dismissible"><p><strong>Failed:</strong> Please verify your GitHub Repository and Token in wp-config.php or below.</p></div>';
        }
    }

    if (isset($_POST['edutarbiyah_save_settings']) && check_admin_referer('edutarbiyah_settings_action')) {
        update_option('edutarbiyah_github_repo', sanitize_text_field($_POST['github_repo']));
        update_option('edutarbiyah_github_token', sanitize_text_field($_POST['github_token']));
        $notice = '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }

    $current_repo = get_option('edutarbiyah_github_repo', defined('EDUTARBIYAH_GITHUB_REPO') ? EDUTARBIYAH_GITHUB_REPO : '');
    $current_token = get_option('edutarbiyah_github_token', defined('EDUTARBIYAH_GITHUB_TOKEN') ? EDUTARBIYAH_GITHUB_TOKEN : '');

    echo '<div class="wrap">';
    echo '<h1>EduTarbiyah Static Frontend Deployment</h1>';
    echo $notice;
    echo '<p>Rebuild and synchronize the headless Astro static site whenever you update courses, products, or articles.</p>';
    
    echo '<div class="card" style="max-width: 650px; padding: 20px; margin-top: 20px;">';
    echo '<h2>Manual Deployment</h2>';
    echo '<p>Click below to immediately trigger a clean static build and FTP upload to your hosting server:</p>';
    echo '<form method="post">';
    wp_nonce_field('edutarbiyah_deploy_action');
    echo '<input type="submit" name="edutarbiyah_trigger_now" class="button button-primary button-hero" value="Trigger Static Deploy Now">';
    echo '</form>';
    echo '</div>';

    echo '<div class="card" style="max-width: 650px; padding: 20px; margin-top: 20px;">';
    echo '<h2>GitHub Webhook Configuration</h2>';
    echo '<form method="post">';
    wp_nonce_field('edutarbiyah_settings_action');
    echo '<table class="form-table">';
    echo '<tr><th><label for="github_repo">GitHub Repo (owner/repo)</label></th>';
    echo '<td><input name="github_repo" type="text" id="github_repo" value="' . esc_attr($current_repo) . '" class="regular-text" placeholder="username/edutarbiyah-headless" /></td></tr>';
    echo '<tr><th><label for="github_token">Personal Access Token (PAT)</label></th>';
    echo '<td><input name="github_token" type="password" id="github_token" value="' . esc_attr($current_token) . '" class="regular-text" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" /><p class="description">Requires <code>repo</code> scope permission.</p></td></tr>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="edutarbiyah_save_settings" class="button button-secondary" value="Save Configuration"></p>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
}
