<?php
defined('ABSPATH') || exit;

final class MYR_H5P {
    public function __construct() {
        add_action('admin_post_myr_h5p_export', array($this, 'download_export'));
    }

    public function available(): bool {
        return class_exists('H5P_Plugin') && class_exists('H5P_Plugin_Admin');
    }

    public function contents(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'h5p_contents';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return array();
        return $wpdb->get_results("SELECT id,title,slug FROM {$table} ORDER BY updated_at DESC, id DESC", ARRAY_A) ?: array();
    }

    public function import_uploaded_file(array $file) {
        if (!$this->available()) return new WP_Error('h5p_missing', 'ابتدا افزونه رسمی H5P را نصب و فعال کنید.');
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return new WP_Error('upload_error', 'بارگذاری فایل H5P کامل نشد.');
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'h5p') return new WP_Error('invalid_type', 'فقط فایل با پسوند h5p پذیرفته می‌شود.');

        $_FILES['h5p_file'] = $file;
        $content = array('disable' => defined('H5PCore::DISABLE_NONE') ? H5PCore::DISABLE_NONE : 0, 'uploaded' => true);
        try {
            $id = H5P_Plugin_Admin::get_instance()->handle_upload($content);
        } catch (Throwable $e) {
            return new WP_Error('h5p_import_failed', $e->getMessage());
        }
        if ($id) return (int) $id;
        $messages = H5P_Plugin::get_instance()->get_h5p_instance('interface')->getMessages('error') ?: array();
        $details = array_map(static fn($message) => wp_strip_all_tags(is_object($message) ? $message->message : (string)$message), $messages);
        return new WP_Error('h5p_invalid', 'بارگذاری H5P انجام نشد. ' . implode(' ', $details));
    }

    public function render(int $content_id): string {
        if (!$content_id) return '<div class="myr-empty">برای این درس هنوز محتوای H5P تعیین نشده است.</div>';
        if (!$this->available()) return '<div class="myr-empty">افزونه رسمی H5P فعال نیست.</div>';
        return do_shortcode('[h5p id="' . $content_id . '"]');
    }

    public function export_url(int $lesson_id): string {
        return wp_nonce_url(admin_url('admin-post.php?action=myr_h5p_export&lesson_id=' . $lesson_id), 'myr_export_' . $lesson_id);
    }

    public function download_export(): void {
        $lesson_id = absint($_GET['lesson_id'] ?? 0);
        if (!$lesson_id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'myr_export_' . $lesson_id)) wp_die('درخواست نامعتبر است.', 403);
        if (!is_user_logged_in()) auth_redirect();
        $course_id = (int) wp_get_post_parent_id($lesson_id);
        if (!MYR_Core::is_enrolled(get_current_user_id(), $course_id) && !current_user_can('edit_myr_lessons')) wp_die('دسترسی مجاز نیست.', 403);
        if (!get_post_meta($course_id, '_myr_allow_h5p_export', true) && !current_user_can('edit_myr_lessons')) wp_die('خروجی این دوره برای فراگیر فعال نیست.', 403);

        global $wpdb;
        $h5p_id = (int) get_post_meta($lesson_id, '_myr_h5p_id', true);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id,title,slug FROM {$wpdb->prefix}h5p_contents WHERE id=%d", $h5p_id));
        if (!$row) wp_die('محتوای H5P پیدا نشد.', 404);
        $uploads = wp_upload_dir();
        $filename = sanitize_file_name(($row->slug ? $row->slug . '-' : '') . $row->id . '.h5p');
        $path = trailingslashit($uploads['basedir']) . 'h5p/exports/' . $filename;
        if (!is_file($path)) wp_die('خروجی هنوز توسط H5P ساخته نشده است. محتوا را یک‌بار در H5P ذخیره کنید.', 404);
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
