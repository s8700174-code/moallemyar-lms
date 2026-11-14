<?php
defined('ABSPATH') || exit;

final class MYR_Core {
    private static ?self $instance = null;
    public MYR_H5P $h5p;
    public MYR_Auth $auth;
    public MYR_REST $rest;
    public MYR_Admin $admin;
    public MYR_Frontend $frontend;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action('init', array($this, 'register_content'));
        add_action('init', array($this, 'maybe_upgrade'));
        $this->h5p = new MYR_H5P();
        $this->auth = new MYR_Auth();
        $this->rest = new MYR_REST($this->auth);
        $this->admin = new MYR_Admin($this->h5p);
        $this->frontend = new MYR_Frontend($this->h5p);
    }

    public function maybe_upgrade(): void {
        if (get_option('myr_version') !== MYR_VERSION) MYR_Install::activate();
    }

    public function register_content(): void {
        register_post_type('myr_course', array(
            'labels' => array('name'=>'دوره‌ها','singular_name'=>'دوره','add_new_item'=>'افزودن دوره','edit_item'=>'ویرایش دوره'),
            'public'=>false, 'show_ui'=>true, 'show_in_menu'=>'myr-dashboard', 'supports'=>array('title','editor','thumbnail'),
            'capability_type'=>array('myr_course','myr_courses'), 'map_meta_cap'=>true, 'menu_icon'=>'dashicons-welcome-learn-more',
        ));
        register_post_type('myr_lesson', array(
            'labels' => array('name'=>'درس‌ها','singular_name'=>'درس H5P','add_new_item'=>'افزودن درس H5P','edit_item'=>'ویرایش درس H5P'),
            'public'=>false, 'show_ui'=>true, 'show_in_menu'=>'myr-dashboard', 'supports'=>array('title','page-attributes'),
            'hierarchical'=>true, 'capability_type'=>array('myr_lesson','myr_lessons'), 'map_meta_cap'=>true,
        ));
    }

    public static function settings(): array {
        return wp_parse_args((array) get_option('myr_settings', array()), MYR_Install::defaults());
    }

    public static function page_url(string $key, array $args = array()): string {
        if (self::learner_role() === 'parent' && in_array($key, array('teacher_portal','teacher_course','teacher_lesson'), true)) $key = str_replace('teacher_', 'parent_', $key);
        $id = (int) get_option('myr_page_' . str_replace('-', '_', $key));
        $url = $id ? get_permalink($id) : home_url('/' . $key . '/');
        return $args ? add_query_arg($args, $url) : $url;
    }

    public static function normalize_digits(string $value): string {
        return strtr(trim($value), array('۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'));
    }

    public static function learner_role(int $user_id = 0): string {
        $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
        if (!$user) return '';
        if (in_array('myr_parent', $user->roles, true)) return 'parent';
        if (in_array('myr_teacher', $user->roles, true)) return 'teacher';
        return '';
    }

    public static function audience_allows(int $user_id, int $course_id): bool {
        if (user_can($user_id, 'view_myr_reports')) return true;
        $role = self::learner_role($user_id);
        $audience = get_post_meta($course_id, '_myr_audience', true) ?: 'teacher';
        return $role !== '' && ($audience === 'both' || $audience === $role);
    }

    public static function normalize_phone(string $phone): string {
        $phone = preg_replace('/[^0-9+]/', '', strtr($phone, array('۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9')));
        if (str_starts_with($phone, '+98')) $phone = '0' . substr($phone, 3);
        if (str_starts_with($phone, '98') && strlen($phone) === 12) $phone = '0' . substr($phone, 2);
        return $phone;
    }

    public static function course_lessons(int $course_id): array {
        return get_posts(array('post_type'=>'myr_lesson','post_status'=>'publish','post_parent'=>$course_id,'numberposts'=>-1,'orderby'=>array('menu_order'=>'ASC','date'=>'ASC')));
    }

    public static function is_enrolled(int $user_id, int $course_id): bool {
        if (get_post_type($course_id) !== 'myr_course') return false;
        if (user_can($user_id, 'view_myr_reports')) return true;
        if (get_post_status($course_id) !== 'publish' || !self::audience_allows($user_id, $course_id)) return false;
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}myr_enrollments WHERE user_id=%d AND course_id=%d AND status IN ('active','completed')", $user_id, $course_id));
    }

    public static function prerequisites_met(int $user_id, int $course_id): bool {
        $required = array_filter(array_map('intval', (array) get_post_meta($course_id, '_myr_prerequisites', true)));
        if (!$required) return true;
        global $wpdb;
        foreach ($required as $required_id) {
            $done = $wpdb->get_var($wpdb->prepare("SELECT completed_at FROM {$wpdb->prefix}myr_enrollments WHERE user_id=%d AND course_id=%d", $user_id, $required_id));
            if (!$done) return false;
        }
        return true;
    }

    public static function lesson_is_released(int $user_id, int $course_id, int $lesson_id): bool {
        $day = max(1, (int) (get_post_meta($lesson_id, '_myr_release_day', true) ?: 1));
        global $wpdb;
        $enrolled = $wpdb->get_var($wpdb->prepare("SELECT enrolled_at FROM {$wpdb->prefix}myr_enrollments WHERE user_id=%d AND course_id=%d", $user_id, $course_id));
        if (!$enrolled || $day <= 1) return true;
        $unlock_at = strtotime($enrolled . ' UTC') + (($day - 1) * DAY_IN_SECONDS);
        return time() >= $unlock_at;
    }
}
