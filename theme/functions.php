<?php
defined('ABSPATH') || exit;

function myrt_setup(): void {
    load_theme_textdomain('moallemyar-theme', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array('height'=>80,'width'=>80,'flex-height'=>true,'flex-width'=>true));
    add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script'));
    add_theme_support('align-wide');
    register_nav_menus(array('primary'=>'فهرست اصلی','footer'=>'فهرست پایین سایت'));
}
add_action('after_setup_theme','myrt_setup');

function myrt_assets(): void {
    wp_enqueue_style('moallemyar-theme', get_stylesheet_uri(), array(), '1.0.0');
    wp_enqueue_script('moallemyar-theme', get_template_directory_uri() . '/assets/theme.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts','myrt_assets');

function myrt_portal_url(): string {
    if (class_exists('MYR_Core')) return is_user_logged_in() ? MYR_Core::page_url('teacher_portal') : MYR_Core::page_url('teacher_login');
    return wp_login_url();
}

function myrt_icon(string $name): string {
    $icons=array(
        'sprout'=>'<path d="M12 22V12m0 0C8 12 5 9 5 5c4 0 7 3 7 7Zm0 0c0-5 3-8 8-8 0 5-3 8-8 8Z"/>',
        'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>','arrow'=>'<path d="m9 18 6-6-6-6"/>',
        'play'=>'<path d="m9 6 9 6-9 6V6Z"/>','book'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V5H6.5A2.5 2.5 0 0 0 4 7.5v12Zm0 0A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'chart'=>'<path d="M4 20V10m6 10V4m6 16v-7m5 7H2"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'brain'=>'<path d="M9.5 4A3.5 3.5 0 0 0 6 7.5v.2A3 3 0 0 0 4 13a3 3 0 0 0 3 3h.5v1a3 3 0 0 0 4.5 2.6V5.5A3 3 0 0 0 9.5 4ZM14.5 4A3.5 3.5 0 0 1 18 7.5v.2a3 3 0 0 1 2 5.3 3 3 0 0 1-3 3h-.5v1a3 3 0 0 1-4.5 2.6V5.5A3 3 0 0 1 14.5 4Z"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>','download'=>'<path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/>',
        'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    );
    return '<svg class="site-icon" viewBox="0 0 24 24" aria-hidden="true">'.($icons[$name]??$icons['sprout']).'</svg>';
}

function myrt_brand(): string {
    if (has_custom_logo()) return '<div class="brand">'.get_custom_logo().'<span class="brand-copy"><strong>'.esc_html(get_bloginfo('name')).'</strong><small>رشد حرفه‌ای معلمان</small></span></div>';
    return '<a class="brand" href="'.esc_url(home_url('/')).'"><span class="brand-mark">'.myrt_icon('sprout').'</span><span class="brand-copy"><strong>'.esc_html(get_bloginfo('name')?:'معلم‌یار').'</strong><small>رشد حرفه‌ای معلمان</small></span></a>';
}

function myrt_seed_home(): void {
    if (get_option('myrt_home_seeded')) return;
    $page=get_page_by_path('home');
    $id=$page?$page->ID:wp_insert_post(array('post_title'=>'خانه','post_name'=>'home','post_content'=>'<!-- صفحه اصلی توسط قالب معلم‌یار نمایش داده می‌شود. -->','post_status'=>'publish','post_type'=>'page'));
    if ($id && !is_wp_error($id)) {update_option('show_on_front','page');update_option('page_on_front',(int)$id);update_option('myrt_home_seeded',1);}
}
add_action('after_switch_theme','myrt_seed_home');

