<?php
/**
 * Plugin Name: EduTarbiyah Form Submissions & Lead Manager
 * Description: Captures and manages all user form submissions, diagnostic assessments, quiz scores, and intake questionnaires with admin reporting and CSV export.
 * Version: 1.0.0
 * Author: EduTarbiyah Platform Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class EduTarbiyah_Form_Submissions {

    const DB_VERSION = '1.0.0';
    const TABLE_NAME = 'edutarbiyah_submissions';

    public function __construct() {
        // Auto-create or upgrade database table
        add_action('init', [$this, 'check_database_table']);

        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Admin menu & subpages
        add_action('admin_menu', [$this, 'register_admin_menu']);

        // CSV Export handler
        add_action('admin_post_edutarbiyah_export_submissions_csv', [$this, 'handle_csv_export']);
        
        // Ajax status update
        add_action('wp_ajax_edutarbiyah_update_submission_status', [$this, 'ajax_update_status']);
    }

    /**
     * Get full table name with WordPress prefix
     */
    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create table if it doesn't exist
     */
    public function check_database_table() {
        $installed_version = get_option('edutarbiyah_submissions_db_version');
        if ($installed_version !== self::DB_VERSION) {
            $this->create_database_table();
            update_option('edutarbiyah_submissions_db_version', self::DB_VERSION);
        }
    }

    public function create_database_table() {
        global $wpdb;
        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_type varchar(64) NOT NULL DEFAULT 'general',
            form_title varchar(191) NOT NULL DEFAULT '',
            user_name varchar(191) DEFAULT NULL,
            user_phone varchar(64) DEFAULT NULL,
            user_email varchar(191) DEFAULT NULL,
            score_summary varchar(191) DEFAULT NULL,
            answers_json longtext DEFAULT NULL,
            user_ip varchar(64) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            status varchar(32) NOT NULL DEFAULT 'new',
            notes text DEFAULT NULL,
            submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_type (form_type),
            KEY user_phone (user_phone),
            KEY submitted_at (submitted_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Register REST API endpoint
     */
    public function register_rest_routes() {
        register_rest_route('edutarbiyah/v1', '/submit-form', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_rest_submission'],
            'permission_callback' => '__return_true', // Public form submission
        ]);

        register_rest_route('edutarbiyah/v1', '/submissions', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_rest_get_submissions'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle incoming form submission from Astro frontend
     */
    public function handle_rest_submission(WP_REST_Request $request) {
        global $wpdb;
        $params = $request->get_json_params();

        if (empty($params) || !is_array($params)) {
            $params = $request->get_body_params();
        }

        $form_type = !empty($params['form_type']) ? sanitize_key($params['form_type']) : 'general';
        $form_title = !empty($params['form_title']) ? sanitize_text_field($params['form_title']) : ucfirst(str_replace('-', ' ', $form_type));
        $user_name = !empty($params['user_name']) ? sanitize_text_field($params['user_name']) : null;
        $user_phone = !empty($params['user_phone']) ? sanitize_text_field($params['user_phone']) : null;
        $user_email = !empty($params['user_email']) ? sanitize_email($params['user_email']) : null;
        $score_summary = !empty($params['score_summary']) ? sanitize_text_field($params['score_summary']) : null;

        // Answers payload
        $answers = !empty($params['answers']) ? $params['answers'] : [];
        if (is_array($answers)) {
            $answers_json = wp_json_encode($answers, JSON_UNESCAPED_UNICODE);
        } else {
            $answers_json = is_string($answers) ? $answers : '';
        }

        // Client info
        $user_ip = !empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        $user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';

        $table_name = $this->get_table_name();
        $inserted = $wpdb->insert(
            $table_name,
            [
                'form_type'     => $form_type,
                'form_title'    => $form_title,
                'user_name'     => $user_name,
                'user_phone'    => $user_phone,
                'user_email'    => $user_email,
                'score_summary' => $score_summary,
                'answers_json'  => $answers_json,
                'user_ip'       => $user_ip,
                'user_agent'    => $user_agent,
                'status'        => 'new',
                'submitted_at'  => current_time('mysql'),
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        if ($inserted === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to save submission in database.',
                'error'   => $wpdb->last_error,
            ], 500);
        }

        $submission_id = $wpdb->insert_id;

        // Optional notification to admin email
        $admin_email = get_option('admin_email');
        if ($admin_email && is_email($admin_email)) {
            $subject = "[EduTarbiyah] New {$form_title} Submission from " . ($user_name ?: 'A Parent');
            $body = "Assalamu Alaikum,\n\nA new submission has been received on EduTarbiyah!\n\n";
            $body .= "Form: {$form_title}\n";
            $body .= "Name: " . ($user_name ?: 'Not specified') . "\n";
            $body .= "WhatsApp: " . ($user_phone ?: 'Not specified') . "\n";
            $body .= "Email: " . ($user_email ?: 'Not specified') . "\n";
            if ($score_summary) {
                $body .= "Result/Score: {$score_summary}\n";
            }
            $body .= "Date: " . current_time('r') . "\n\n";
            $body .= "View all submissions in your WordPress Admin:\n" . admin_url('admin.php?page=edutarbiyah-submissions') . "\n\n";
            $body .= "JazakAllah Khair,\nEduTarbiyah Platform System";

            @wp_mail($admin_email, $subject, $body);
        }

        return new WP_REST_Response([
            'success'       => true,
            'submission_id' => $submission_id,
            'message'       => 'Submission recorded successfully.',
        ], 200);
    }

    /**
     * REST API reader for authenticated admin
     */
    public function handle_rest_get_submissions(WP_REST_Request $request) {
        global $wpdb;
        $table_name = $this->get_table_name();
        $limit = min(intval($request->get_param('per_page') ?: 50), 200);
        $offset = intval($request->get_param('offset') ?: 0);

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name ORDER BY submitted_at DESC LIMIT %d OFFSET %d", $limit, $offset),
            ARRAY_A
        );

        return new WP_REST_Response([
            'success' => true,
            'count'   => count($results),
            'items'   => $results,
        ], 200);
    }

    /**
     * Register WordPress Admin Menu
     */
    public function register_admin_menu() {
        add_menu_page(
            'Form Submissions',
            'Form Submissions',
            'manage_options',
            'edutarbiyah-submissions',
            [$this, 'render_admin_submissions_page'],
            'dashicons-clipboard',
            26
        );
    }

    /**
     * Render WordPress Admin Submissions Page
     */
    public function render_admin_submissions_page() {
        global $wpdb;
        $table_name = $this->get_table_name();

        // Handle single deletion
        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id']) && check_admin_referer('delete_sub_' . $_GET['id'])) {
            $wpdb->delete($table_name, ['id' => intval($_GET['id'])], ['%d']);
            echo '<div class="notice notice-success is-dismissible"><p>Submission deleted successfully.</p></div>';
        }

        // View single submission modal/page
        $view_id = !empty($_GET['view']) ? intval($_GET['view']) : 0;
        if ($view_id) {
            $this->render_single_submission_view($view_id);
            return;
        }

        // Filters & Search
        $form_type_filter = !empty($_GET['form_type']) ? sanitize_key($_GET['form_type']) : '';
        $search = !empty($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        $where = "WHERE 1=1";
        $params = [];

        if ($form_type_filter) {
            $where .= " AND form_type = %s";
            $params[] = $form_type_filter;
        }

        if ($search) {
            $where .= " AND (user_name LIKE %s OR user_phone LIKE %s OR user_email LIKE %s OR score_summary LIKE %s)";
            $wildcard = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
        }

        // Distinct form types for filter pills
        $types = $wpdb->get_results("SELECT form_type, form_title, COUNT(*) as count FROM $table_name GROUP BY form_type", ARRAY_A);

        // Submissions list
        $sql = "SELECT * FROM $table_name $where ORDER BY submitted_at DESC LIMIT 100";
        if (!empty($params)) {
            $submissions = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            $submissions = $wpdb->get_results($sql, ARRAY_A);
        }

        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
        ?>
        <div class="wrap" style="max-width: 1200px;">
            <h1 class="wp-heading-inline" style="font-size: 24px; font-weight: 700; color: #064E3B; margin-bottom: 15px;">
                EduTarbiyah Form Submissions & Leads
            </h1>

            <a href="<?php echo esc_url(admin_url('admin-post.php?action=edutarbiyah_export_submissions_csv&form_type=' . urlencode($form_type_filter))); ?>" class="page-title-action" style="background: #0F766E; color: white; border-color: #0F766E;">
                📥 Export to Excel / CSV
            </a>

            <hr class="wp-header-end">

            <!-- Filter tabs -->
            <ul class="subsubsub" style="margin: 15px 0;">
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=edutarbiyah-submissions')); ?>" class="<?php echo empty($form_type_filter) ? 'current' : ''; ?>">
                        All Forms <span class="count">(<?php echo esc_html($total_count); ?>)</span>
                    </a> |
                </li>
                <?php foreach ($types as $t): ?>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=edutarbiyah-submissions&form_type=' . urlencode($t['form_type']))); ?>" class="<?php echo $form_type_filter === $t['form_type'] ? 'current' : ''; ?>">
                            <?php echo esc_html($t['form_title'] ?: $t['form_type']); ?> <span class="count">(<?php echo esc_html($t['count']); ?>)</span>
                        </a> |
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Search box -->
            <form method="get" style="float: right; margin-bottom: 10px;">
                <input type="hidden" name="page" value="edutarbiyah-submissions" />
                <?php if ($form_type_filter): ?>
                    <input type="hidden" name="form_type" value="<?php echo esc_attr($form_type_filter); ?>" />
                <?php endif; ?>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, phone, email..." style="padding: 4px 10px; border-radius: 6px;" />
                <input type="submit" class="button" value="Search Leads" />
            </form>

            <div style="clear: both;"></div>

            <!-- Main Table -->
            <table class="wp-list-table widefat fixed striped" style="border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <thead>
                    <tr style="background: #F4F8F6;">
                        <th style="width: 50px; font-weight: 700;">ID</th>
                        <th style="width: 140px; font-weight: 700;">Date & Time</th>
                        <th style="width: 160px; font-weight: 700;">Form / Quiz</th>
                        <th style="font-weight: 700;">Parent Name</th>
                        <th style="width: 150px; font-weight: 700;">WhatsApp / Phone</th>
                        <th style="font-weight: 700;">Email</th>
                        <th style="width: 160px; font-weight: 700;">Result / Score</th>
                        <th style="width: 130px; font-weight: 700; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #6B7280;">
                                <em>No submissions recorded yet. When parents fill any assessment or intake form, responses appear here automatically!</em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub): 
                            $clean_phone = preg_replace('/[^0-9]/', '', $sub['user_phone']);
                            if ($clean_phone && substr($clean_phone, 0, 1) === '0') {
                                $clean_phone = '92' . substr($clean_phone, 1);
                            }
                            $wa_link = $clean_phone ? "https://wa.me/{$clean_phone}" : '';
                        ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($sub['id']); ?></strong></td>
                                <td style="font-size: 12px; color: #4B5563;">
                                    <?php echo esc_html(date('M j, Y — g:i A', strtotime($sub['submitted_at']))); ?>
                                </td>
                                <td>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #ECFDF5; color: #064E3B; border: 1px solid #A7F3D0;">
                                        <?php echo esc_html($sub['form_title']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($sub['user_name'] ?: 'Anonymous / Guest'); ?></strong>
                                </td>
                                <td>
                                    <?php if ($clean_phone): ?>
                                        <a href="<?php echo esc_url($wa_link); ?>" target="_blank" style="color: #059669; font-weight: 600; text-decoration: none;">
                                            💬 <?php echo esc_html($sub['user_phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sub['user_email']): ?>
                                        <a href="mailto:<?php echo esc_attr($sub['user_email']); ?>" style="color: #0F766E;">
                                            <?php echo esc_html($sub['user_email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sub['score_summary']): ?>
                                        <span style="font-size: 11px; font-weight: 600; color: #D97706;">
                                            <?php echo esc_html($sub['score_summary']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9CA3AF;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=edutarbiyah-submissions&view=' . $sub['id'])); ?>" class="button button-small" style="background: #064E3B; color: white; border-color: #064E3B;">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Single Detailed Submission View
     */
    private function render_single_submission_view($id) {
        global $wpdb;
        $table_name = $this->get_table_name();
        $sub = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

        if (!$sub) {
            echo '<div class="wrap"><p>Submission not found.</p></div>';
            return;
        }

        $answers = json_decode($sub['answers_json'], true) ?: [];
        $clean_phone = preg_replace('/[^0-9]/', '', $sub['user_phone']);
        if ($clean_phone && substr($clean_phone, 0, 1) === '0') {
            $clean_phone = '92' . substr($clean_phone, 1);
        }
        $wa_link = $clean_phone ? "https://wa.me/{$clean_phone}" : '';
        ?>
        <div class="wrap" style="max-width: 900px;">
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=edutarbiyah-submissions')); ?>" style="text-decoration: none; font-weight: 600;">← Back to All Submissions</a></p>

            <div style="background: white; border-radius: 12px; padding: 25px; border: 1px solid #E5E7EB; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #F3F4F6; padding-bottom: 15px; margin-bottom: 20px;">
                    <div>
                        <span style="display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: #ECFDF5; color: #064E3B;">
                            <?php echo esc_html($sub['form_title']); ?>
                        </span>
                        <h1 style="margin: 8px 0 4px 0; font-size: 22px; color: #111827;">
                            <?php echo esc_html($sub['user_name'] ?: 'Parent Submission'); ?> (#<?php echo esc_html($sub['id']); ?>)
                        </h1>
                        <p style="color: #6B7280; font-size: 13px; margin: 0;">
                            Submitted on <?php echo esc_html(date('l, F j, Y — g:i:s A', strtotime($sub['submitted_at']))); ?>
                        </p>
                    </div>

                    <div style="text-align: right;">
                        <?php if ($clean_phone): ?>
                            <a href="<?php echo esc_url($wa_link); ?>" target="_blank" class="button button-primary" style="background: #25D366; border-color: #25D366; font-weight: 700;">
                                💬 Open WhatsApp Chat
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                    <div style="background: #F9FAFB; padding: 12px 15px; border-radius: 8px; border: 1px solid #E5E7EB;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 700; display: block;">Parent Name</span>
                        <strong style="font-size: 15px; color: #111827;"><?php echo esc_html($sub['user_name'] ?: 'Not Provided'); ?></strong>
                    </div>

                    <div style="background: #F9FAFB; padding: 12px 15px; border-radius: 8px; border: 1px solid #E5E7EB;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 700; display: block;">WhatsApp Phone</span>
                        <strong style="font-size: 15px; color: #059669;"><?php echo esc_html($sub['user_phone'] ?: 'Not Provided'); ?></strong>
                    </div>

                    <div style="background: #F9FAFB; padding: 12px 15px; border-radius: 8px; border: 1px solid #E5E7EB;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #6B7280; font-weight: 700; display: block;">Result / Score</span>
                        <strong style="font-size: 15px; color: #D97706;"><?php echo esc_html($sub['score_summary'] ?: 'N/A'); ?></strong>
                    </div>
                </div>

                <!-- Detailed Responses -->
                <h3 style="font-size: 16px; font-weight: 700; color: #064E3B; margin-bottom: 12px;">Full Diagnostic Responses:</h3>
                
                <?php if (empty($answers)): ?>
                    <p style="color: #6B7280;"><em>No additional questionnaire fields recorded.</em></p>
                <?php else: ?>
                    <table class="widefat striped" style="border: 1px solid #E5E7EB; border-radius: 6px;">
                        <thead>
                            <tr style="background: #F4F8F6;">
                                <th style="width: 35%; font-weight: 700;">Question / Field</th>
                                <th style="font-weight: 700;">User Answer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answers as $key => $val): 
                                $label = ucwords(str_replace(['_', '-'], ' ', $key));
                                if (is_array($val)) {
                                    $val_display = implode(', ', array_map('esc_html', $val));
                                } else {
                                    $val_display = nl2br(esc_html($val));
                                }
                            ?>
                                <tr>
                                    <td style="font-weight: 600; color: #374151;"><?php echo esc_html($label); ?></td>
                                    <td style="color: #111827;"><?php echo $val_display ?: '<span style="color:#9CA3AF;">—</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #F3F4F6; display: flex; justify-content: space-between; align-items: center;">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=edutarbiyah-submissions&action=delete&id=' . $sub['id']), 'delete_sub_' . $sub['id'])); ?>" class="button button-link-delete" onclick="return confirm('Are you sure you want to permanently delete this submission?');">
                        Delete Submission
                    </a>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=edutarbiyah-submissions')); ?>" class="button">
                        Back to List
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle CSV Export
     */
    public function handle_csv_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $this->get_table_name();
        $form_type = !empty($_GET['form_type']) ? sanitize_key($_GET['form_type']) : '';

        $where = "WHERE 1=1";
        $params = [];
        if ($form_type) {
            $where .= " AND form_type = %s";
            $params[] = $form_type;
        }

        $sql = "SELECT * FROM $table_name $where ORDER BY submitted_at DESC";
        $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        $filename = 'edutarbiyah_submissions_' . ($form_type ?: 'all') . '_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel Urdu/Arabic support
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, ['ID', 'Date', 'Form Title', 'Form Type', 'Name', 'Phone', 'Email', 'Score Summary', 'IP Address', 'Answers JSON']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['submitted_at'],
                $row['form_title'],
                $row['form_type'],
                $row['user_name'],
                $row['user_phone'],
                $row['user_email'],
                $row['score_summary'],
                $row['user_ip'],
                $row['answers_json']
            ]);
        }

        fclose($output);
        exit;
    }
}

new EduTarbiyah_Form_Submissions();
