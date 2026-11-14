<?php
defined('ABSPATH') || exit;

final class MYR_Auth {
    public function __construct() {
        add_filter('auth_cookie_expiration', array($this, 'cookie_expiration'), 10, 3);
    }
    public function cookie_expiration(int $length, int $user_id, bool $remember): int {
        return $remember ? max(DAY_IN_SECONDS, (int) MYR_Core::settings()['trusted_days'] * DAY_IN_SECONDS) : $length;
    }
    public function find_user(string $phone, string $role = 'teacher'): ?WP_User {
        if (!in_array($role, array('teacher', 'parent'), true)) return null;
        $users = get_users(array('number'=>2, 'role'=>'myr_'.$role, 'meta_key'=>'myr_phone', 'meta_value'=>MYR_Core::normalize_phone($phone)));
        return count($users) === 1 ? $users[0] : null;
    }
    private function rate_limit() {
        $key = 'myr_auth_ip_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $attempts = (int) get_transient($key);
        if ($attempts >= 20) return new WP_Error('rate_limited', 'تعداد درخواست‌ها زیاد است؛ چند دقیقه دیگر تلاش کنید.', array('status'=>429));
        set_transient($key, $attempts + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }
    public function request_access(string $national_id, string $role) {
        $limit = $this->rate_limit();
        if (is_wp_error($limit)) return $limit;
        $national_id = MYR_Core::normalize_digits($national_id);
        if (!in_array($role, array('teacher', 'parent'), true) || !preg_match('/^\d{10}$/', $national_id)) return new WP_Error('invalid_identity', 'نقش و کد ملی ۱۰ رقمی را به‌درستی وارد کنید.', array('status'=>422));
        $hash = hash_hmac('sha256', $national_id, wp_salt('auth'));
        $query = array('relation'=>'OR', array('key'=>'myr_national_id_hash', 'value'=>$hash));
        if ($role === 'parent') $query[] = array('key'=>'myr_student_id_hash', 'value'=>$hash);
        $users = get_users(array('number'=>2, 'role'=>'myr_'.$role, 'meta_query'=>$query));
        if (count($users) !== 1) return new WP_Error('unknown_identity', 'برای این نقش و کد ملی حساب یکتایی ثبت نشده است؛ با پشتیبانی تماس بگیرید.', array('status'=>404));
        return $this->issue($users[0], $role);
    }
    public function request_code(string $raw_phone) {
        $limit = $this->rate_limit();
        if (is_wp_error($limit)) return $limit;
        $user = $this->find_user($raw_phone);
        if (!$user) return new WP_Error('unknown_phone', 'این شماره برای حساب معلم ثبت نشده است.', array('status'=>404));
        return $this->issue($user, 'teacher');
    }
    private function issue(WP_User $user, string $role) {
        global $wpdb;
        $phone = MYR_Core::normalize_phone((string) get_user_meta($user->ID, 'myr_phone', true));
        if (!preg_match('/^09\d{9}$/', $phone)) return new WP_Error('missing_phone', 'شماره همراه این حساب کامل نیست؛ با پشتیبانی تماس بگیرید.', array('status'=>422));
        $settings = MYR_Core::settings();
        $development = $settings['sms_driver'] === 'development';
        if ($development && !in_array(wp_get_environment_type(), array('local', 'development'), true)) return new WP_Error('sms_config', 'ارسال پیامک برای این سایت هنوز تنظیم نشده است.', array('status'=>503));
        $last = $wpdb->get_var($wpdb->prepare("SELECT created_at FROM {$wpdb->prefix}myr_otps WHERE phone=%s ORDER BY id DESC LIMIT 1", $phone));
        if ($last && time() - strtotime($last.' UTC') < (int) $settings['otp_cooldown']) return new WP_Error('too_soon', 'برای دریافت کد تازه کمی صبر کنید.', array('status'=>429));
        $code = (string) random_int(10000, 99999);
        $ttl = max(60, min(600, (int) $settings['otp_ttl']));
        $inserted = $wpdb->insert($wpdb->prefix.'myr_otps', array('phone'=>$phone, 'code_hash'=>wp_hash_password($code), 'attempts'=>0, 'ip_hash'=>hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 'expires_at'=>gmdate('Y-m-d H:i:s', time()+$ttl), 'created_at'=>current_time('mysql', true)));
        if (!$inserted) return new WP_Error('storage_failed', 'ساخت کد ورود ممکن نشد.', array('status'=>500));
        $otp_id = (int) $wpdb->insert_id;
        $sent = $this->send_sms($phone, $code, $settings);
        if (is_wp_error($sent)) { $wpdb->delete($wpdb->prefix.'myr_otps', array('id'=>$otp_id)); return $sent; }
        $challenge = bin2hex(random_bytes(24));
        set_transient('myr_challenge_'.hash('sha256', $challenge), array('user_id'=>$user->ID, 'otp_id'=>$otp_id, 'role'=>$role), $ttl);
        $out = array('challenge'=>$challenge, 'masked_phone'=>substr($phone,0,4).'••••'.substr($phone,-3), 'expires_in'=>$ttl, 'role'=>$role);
        if ($development) $out['development_code'] = $code;
        return $out;
    }
    public function verify_code(string $challenge, string $code, bool $remember = true) {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{48}$/', $challenge)) return new WP_Error('invalid_challenge', 'ابتدا کد ورود دریافت کنید.', array('status'=>422));
        $key = 'myr_challenge_'.hash('sha256', $challenge);
        $data = get_transient($key);
        if (!$data) return new WP_Error('expired_code', 'کد منقضی شده است؛ کد تازه بگیرید.', array('status'=>410));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}myr_otps WHERE id=%d AND consumed_at IS NULL", $data['otp_id']));
        if (!$row || strtotime($row->expires_at.' UTC') < time()) return new WP_Error('expired_code', 'کد منقضی شده است؛ کد تازه بگیرید.', array('status'=>410));
        if (!$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}myr_otps SET attempts=attempts+1 WHERE id=%d AND attempts<5 AND consumed_at IS NULL", $row->id))) return new WP_Error('locked_code', 'تعداد تلاش‌ها بیش از حد مجاز است؛ کد تازه بگیرید.', array('status'=>429));
        $code = MYR_Core::normalize_digits($code);
        if (!preg_match('/^\d{5}$/', $code) || !wp_check_password($code, $row->code_hash)) return new WP_Error('wrong_code', 'کد واردشده صحیح نیست.', array('status'=>422));
        $user = get_userdata((int)$data['user_id']);
        if (!$user || !in_array('myr_'.$data['role'], $user->roles, true)) return new WP_Error('role_changed', 'دسترسی این حساب تغییر کرده است.', array('status'=>403));
        if (!$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}myr_otps SET consumed_at=%s WHERE id=%d AND consumed_at IS NULL", current_time('mysql',true), $row->id))) return new WP_Error('used_code', 'این کد قبلاً استفاده شده است.', array('status'=>409));
        delete_transient($key);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        update_user_meta($user->ID, 'myr_last_login', current_time('mysql'));
        do_action('wp_login', $user->user_login, $user);
        return array('redirect'=>MYR_Core::page_url($data['role'].'_portal'), 'display_name'=>$user->display_name);
    }
    private function send_sms(string $phone, string $code, array $settings) {
        $filtered = apply_filters('myr_send_sms', null, $phone, $code, $settings);
        if ($filtered !== null) return $filtered;
        if ($settings['sms_driver'] === 'development') return true;
        if ($settings['sms_driver'] !== 'generic_json' || !$settings['sms_endpoint']) return new WP_Error('sms_config', 'سامانه پیامکی تنظیم نشده است.', array('status'=>500));
        $response = wp_remote_post($settings['sms_endpoint'], array('timeout'=>15, 'headers'=>array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$settings['sms_token']), 'body'=>wp_json_encode(array('to'=>$phone,'message'=>str_replace('{{code}}',$code,$settings['sms_template']),'code'=>$code))));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) return new WP_Error('sms_failed', 'ارسال پیامک انجام نشد؛ دوباره تلاش کنید.', array('status'=>502));
        return true;
    }
}
