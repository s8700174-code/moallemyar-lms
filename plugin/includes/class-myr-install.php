<?php
defined('ABSPATH') || exit;

final class MYR_Install {
    public static function activate(): void {
        self::create_tables();
        self::create_roles();
        self::create_pages();
        update_option('myr_version', MYR_VERSION);
        update_option('myr_settings', wp_parse_args(get_option('myr_settings', array()), self::defaults()));
        // تنظیمات لازم موتور رسمی H5P برای گزارش، ادامه فعالیت و خروجی قابل حمل.
        update_option('h5p_track_user', true);
        update_option('h5p_save_content_state', true);
        update_option('h5p_export', true);
        if (!wp_next_scheduled('myr_daily_cleanup')) wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'myr_daily_cleanup');
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('myr_daily_cleanup');
        flush_rewrite_rules();
    }

    public static function cleanup(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}myr_otps WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 7 DAY)");
    }

    public static function defaults(): array {
        return array(
            'brand_name' => 'معلم‌یار',
            'support_text' => 'ارتباط با پشتیبانی مدرسه',
            'sms_driver' => 'development',
            'sms_endpoint' => '',
            'sms_token' => '',
            'sms_template' => 'کد ورود شما: {{code}}',
            'otp_ttl' => 120,
            'otp_cooldown' => 60,
            'trusted_days' => 30,
            'privacy_notice' => 'زمان حضور و پاسخ‌های نهایی این دوره برای ارائه بازخورد آموزشی ثبت می‌شود.',
        );
    }

    public static function refresh_roles(): void {
        self::create_roles();
    }

    private static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix . 'myr_';

        dbDelta("CREATE TABLE {$p}schools (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          name varchar(190) NOT NULL,
          code varchar(80) NOT NULL DEFAULT '',
          active tinyint(1) NOT NULL DEFAULT 1,
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY code (code)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}enrollments (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          user_id bigint unsigned NOT NULL,
          course_id bigint unsigned NOT NULL,
          status varchar(24) NOT NULL DEFAULT 'active',
          enrolled_at datetime NOT NULL,
          completed_at datetime NULL,
          PRIMARY KEY  (id),
          UNIQUE KEY user_course (user_id,course_id),
          KEY course_status (course_id,status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}progress (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          user_id bigint unsigned NOT NULL,
          course_id bigint unsigned NOT NULL,
          lesson_id bigint unsigned NOT NULL,
          status varchar(24) NOT NULL DEFAULT 'started',
          elapsed_seconds int unsigned NOT NULL DEFAULT 0,
          active_seconds int unsigned NOT NULL DEFAULT 0,
          visits int unsigned NOT NULL DEFAULT 0,
          score decimal(8,2) NULL,
          max_score decimal(8,2) NULL,
          started_at datetime NULL,
          completed_at datetime NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY  (id),
          UNIQUE KEY user_lesson (user_id,lesson_id),
          KEY course_user (course_id,user_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}events (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          user_id bigint unsigned NOT NULL,
          course_id bigint unsigned NOT NULL,
          lesson_id bigint unsigned NOT NULL,
          session_id varchar(64) NOT NULL DEFAULT '',
          event_type varchar(50) NOT NULL,
          payload_json longtext NULL,
          occurred_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY lesson_user (lesson_id,user_id),
          KEY event_time (event_type,occurred_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}responses (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          user_id bigint unsigned NOT NULL,
          course_id bigint unsigned NOT NULL,
          lesson_id bigint unsigned NOT NULL,
          response_text longtext NULL,
          response_json longtext NULL,
          visibility varchar(30) NOT NULL DEFAULT 'manager',
          display_name_mode varchar(20) NOT NULL DEFAULT 'named',
          moderation_status varchar(20) NOT NULL DEFAULT 'approved',
          edit_count int unsigned NOT NULL DEFAULT 0,
          updated_at datetime NOT NULL,
          PRIMARY KEY  (id),
          UNIQUE KEY user_lesson (user_id,lesson_id),
          KEY visibility (visibility,moderation_status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}otps (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          phone varchar(20) NOT NULL,
          code_hash varchar(255) NOT NULL,
          attempts tinyint unsigned NOT NULL DEFAULT 0,
          ip_hash varchar(64) NOT NULL DEFAULT '',
          expires_at datetime NOT NULL,
          consumed_at datetime NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY  (id),
          KEY phone_created (phone,created_at)
        ) {$charset};");
    }

    private static function create_roles(): void {
        add_role('myr_teacher', 'معلم', array('read' => true));
        add_role('myr_parent', 'والدین', array('read' => true));
        $course_caps = array(
            'edit_myr_course','read_myr_course','delete_myr_course','edit_myr_courses','edit_others_myr_courses',
            'publish_myr_courses','read_private_myr_courses','delete_myr_courses','delete_private_myr_courses',
            'delete_published_myr_courses','delete_others_myr_courses','edit_private_myr_courses','edit_published_myr_courses',
        );
        $lesson_caps = array(
            'edit_myr_lesson','read_myr_lesson','delete_myr_lesson','edit_myr_lessons','edit_others_myr_lessons',
            'publish_myr_lessons','read_private_myr_lessons','delete_myr_lessons','delete_private_myr_lessons',
            'delete_published_myr_lessons','delete_others_myr_lessons','edit_private_myr_lessons','edit_published_myr_lessons',
        );
        add_role('myr_course_manager', 'مدیر دوره', array('read'=>true,'upload_files'=>true,'view_myr_reports'=>true));
        $manager = get_role('myr_course_manager');
        if ($manager) {
            foreach (array_merge($course_caps, $lesson_caps, array('edit_h5p_contents','edit_others_h5p_contents','view_h5p_contents','view_others_h5p_contents','view_h5p_results')) as $cap) $manager->add_cap($cap);
        }

        $admin = get_role('administrator');
        if ($admin) {
            foreach (array_merge($course_caps, $lesson_caps, array('view_myr_reports','manage_myr_teachers')) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    private static function create_pages(): void {
        $pages = array(
            'teacher-login' => array('ورود والدین و معلم', '[myr_login]'),
            'parent-portal' => array('سامانه والدین', '[myr_dashboard]'),
            'parent-course' => array('دوره والدین', '[myr_course]'),
            'parent-lesson' => array('درس والدین', '[myr_lesson]'),
            'teacher-portal' => array('سامانه معلم', '[myr_dashboard]'),
            'teacher-course' => array('دوره آموزشی', '[myr_course]'),
            'teacher-lesson' => array('درس', '[myr_lesson]'),
        );
        foreach ($pages as $slug => $data) {
            $existing = get_page_by_path($slug);
            $id = $existing ? $existing->ID : wp_insert_post(array(
                'post_title' => $data[0], 'post_name' => $slug, 'post_content' => $data[1],
                'post_type' => 'page', 'post_status' => 'publish',
            ));
            if ($id && !is_wp_error($id)) update_option('myr_page_' . str_replace('-', '_', $slug), (int) $id);
        }
    }
}
