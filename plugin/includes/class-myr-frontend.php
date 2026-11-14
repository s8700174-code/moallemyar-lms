<?php
defined('ABSPATH') || exit;

final class MYR_Frontend {
    public function __construct(private MYR_H5P $h5p) {
        add_shortcode('myr_login',array($this,'login'));
        add_shortcode('myr_dashboard',array($this,'dashboard'));
        add_shortcode('myr_course',array($this,'course'));
        add_shortcode('myr_lesson',array($this,'lesson'));
        add_action('wp_enqueue_scripts',array($this,'assets'));
        add_filter('template_include',array($this,'template'));
        add_action('template_redirect',array($this,'guard'));
        add_filter('body_class',fn($classes)=>$this->is_app()?array_merge($classes,array('myr-body')):$classes);
    }

    private function page_ids(): array {return array_filter(array_map('intval',array(get_option('myr_page_teacher_login'),get_option('myr_page_teacher_portal'),get_option('myr_page_teacher_course'),get_option('myr_page_teacher_lesson'),get_option('myr_page_parent_portal'),get_option('myr_page_parent_course'),get_option('myr_page_parent_lesson'))));}
    private function is_app(): bool {return is_page($this->page_ids());}
    public function template(string $template): string {if(is_page((int)get_option('myr_page_teacher_login')))return MYR_DIR.'templates/login-shell.php';return $this->is_app()?MYR_DIR.'templates/app-shell.php':$template;}

    public function guard(): void {
        if (!$this->is_app()) return;
        nocache_headers();
        if (MYR_Core::learner_role()) show_admin_bar(false);
        $login = (int)get_option('myr_page_teacher_login');
        if (is_page($login)) {
            if (is_user_logged_in() && (MYR_Core::learner_role() || current_user_can('view_myr_reports'))) { wp_safe_redirect(MYR_Core::page_url('teacher_portal')); exit; }
            return;
        }
        if (!is_user_logged_in()) { wp_safe_redirect(MYR_Core::page_url('teacher_login')); exit; }
        if (!MYR_Core::learner_role() && !current_user_can('view_myr_reports')) wp_die('این حساب به سامانه آموزشی دسترسی ندارد.', 'دسترسی مجاز نیست', array('response'=>403));
        if (!current_user_can('view_myr_reports')) {
            $role = MYR_Core::learner_role();
            foreach (array('portal','course','lesson') as $part) {
                if (is_page((int)get_option('myr_page_'.($role === 'parent' ? 'teacher' : 'parent').'_'.$part))) {
                    wp_safe_redirect(MYR_Core::page_url($role.'_portal')); exit;
                }
            }
        }
    }

    public function assets(): void {
        if(!$this->is_app())return;
        wp_enqueue_style('myr-frontend',MYR_URL.'assets/css/frontend.css',array(),MYR_VERSION);
        wp_enqueue_script('myr-frontend',MYR_URL.'assets/js/frontend.js',array(),MYR_VERSION,true);
        wp_localize_script('myr-frontend','MYR',array('rest'=>esc_url_raw(rest_url('myr/v1/')),'nonce'=>wp_create_nonce('wp_rest'),'login'=>MYR_Core::page_url('teacher_login'),'portal'=>MYR_Core::page_url('teacher_portal')));
    }

    public function login(): string {
        ob_start(); require MYR_DIR.'templates/login-form.php'; return ob_get_clean();
    }

    public function dashboard(): string {
        global $wpdb; $uid=get_current_user_id(); $user=wp_get_current_user();
        $courses=$wpdb->get_results($wpdb->prepare("SELECT p.* FROM {$wpdb->prefix}posts p INNER JOIN {$wpdb->prefix}myr_enrollments e ON e.course_id=p.ID WHERE e.user_id=%d AND e.status IN ('active','completed') AND p.post_status='publish' ORDER BY e.enrolled_at DESC",$uid));
        $courses=array_values(array_filter($courses,fn($course)=>MYR_Core::is_enrolled($uid,(int)$course->ID)));
        ob_start(); ?>
        <div class="myr-mobile-shell">
          <?php echo $this->header('سلام، '.($user->first_name?:$user->display_name),(MYR_Core::learner_role()==='parent'?'پنل والدین · یادگیری و همراهی با فرزند':'پنل معلم · مسیر رشد حرفه‌ای')); ?>
          <main class="myr-dashboard"><h2 class="myr-section-title">دوره‌های من</h2>
          <?php if(!$courses): ?><div class="myr-empty">هنوز دوره‌ای از طرف مدرسه برای شما فعال نشده است.</div><?php endif; ?>
          <?php foreach($courses as $i=>$course): $p=$this->course_progress($uid,$course->ID);$locked=!MYR_Core::prerequisites_met($uid,$course->ID); if($i===0&&!$locked): ?>
            <section class="myr-featured"><span class="myr-pill">دوره جاری</span><h2><?php echo esc_html($course->post_title); ?></h2><p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($course->post_content),18)); ?></p><div class="myr-progress-label"><span>پیشرفت دوره</span><strong><?php echo $p; ?>٪</strong></div><div class="myr-progress"><i style="width:<?php echo $p; ?>%"></i></div><a class="myr-white-btn" href="<?php echo esc_url(MYR_Core::page_url('teacher_course',array('course_id'=>$course->ID))); ?>">ادامه دوره</a></section>
          <?php else: ?><a class="myr-course-card<?php echo $locked?' locked':''; ?>" href="<?php echo $locked?'#':esc_url(MYR_Core::page_url('teacher_course',array('course_id'=>$course->ID))); ?>"><span class="myr-course-icon"><?php echo $this->icon($locked?'lock':'book'); ?></span><span><strong><?php echo esc_html($course->post_title); ?></strong><small><?php echo $locked?'پس از تکمیل پیش‌نیاز فعال می‌شود':($p?'پیشرفت '.$p.'٪':'بدون پیش‌نیاز'); ?></small></span><b><?php echo $locked?$this->icon('lock'):'←'; ?></b></a><?php endif; endforeach; ?>
          </main><?php echo $this->bottom_nav('home'); ?>
        </div><?php return ob_get_clean();
    }

    public function course(): string {
        global $wpdb; $uid=get_current_user_id();$id=absint($_GET['course_id']??0);$course=get_post($id);
        if(!$course||$course->post_type!=='myr_course'||!MYR_Core::is_enrolled($uid,$id))return $this->error('این دوره برای شما فعال نیست.');
        if(!MYR_Core::prerequisites_met($uid,$id))return $this->error('ابتدا دوره پیش‌نیاز را تکمیل کنید.');
        $lessons=MYR_Core::course_lessons($id);$progress=$this->progress_map($uid,$id);$pct=$this->course_progress($uid,$id);$sequence=(bool)get_post_meta($id,'_myr_require_sequence',true);$allow_revisit=metadata_exists('post',$id,'_myr_allow_revisit')?(bool)get_post_meta($id,'_myr_allow_revisit',true):true;$previous_done=true;
        ob_start();?><div class="myr-mobile-shell"><?php echo $this->header('جزئیات دوره','مسیر آموزشی شما',true); ?><main class="myr-course-view"><section class="myr-course-hero"><span class="myr-pill">دوره آموزشی</span><h1><?php echo esc_html($course->post_title); ?></h1><p><?php echo wp_kses_post(wpautop($course->post_content)); ?></p><div class="myr-progress-label"><span><?php echo count($lessons); ?> درس</span><strong><?php echo $pct; ?>٪</strong></div><div class="myr-progress"><i style="width:<?php echo $pct; ?>%"></i></div></section><div class="myr-days">
        <?php foreach($lessons as $index=>$lesson):$done=($progress[$lesson->ID]['status']??'')==='completed';$released=MYR_Core::lesson_is_released($uid,$id,$lesson->ID);$can=$released&&(!$sequence||$previous_done||$done)&&(!$done||$allow_revisit);$day=max(1,(int)(get_post_meta($lesson->ID,'_myr_release_day',true)?:1));?><a class="myr-day <?php echo $done?'done':($can?'current':'locked'); ?>" href="<?php echo $can?esc_url(MYR_Core::page_url('teacher_lesson',array('lesson_id'=>$lesson->ID))):'#'; ?>"><span class="myr-day-no"><?php echo $done?$this->icon('check'):($index+1); ?></span><span><strong><?php echo esc_html($lesson->post_title); ?></strong><small><?php echo $done?($allow_revisit?'تکمیل شده · مشاهده مجدد':'تکمیل شده'):($can?'آماده مشاهده':(!$released?'روز '.$day.' فعال می‌شود':'پس از درس قبلی')); ?></small></span><b><?php echo $can?'←':$this->icon('lock'); ?></b></a><?php $previous_done=$previous_done&&$done; endforeach; ?></div></main><?php echo $this->bottom_nav('courses'); ?></div><?php return ob_get_clean();
    }

    public function lesson(): string {
        global $wpdb;$uid=get_current_user_id();$id=absint($_GET['lesson_id']??0);$lesson=get_post($id);$course_id=$lesson?(int)$lesson->post_parent:0;$course=get_post($course_id);
        if(!$lesson||$lesson->post_type!=='myr_lesson'||$lesson->post_status!=='publish'||!MYR_Core::is_enrolled($uid,$course_id)||!MYR_Core::prerequisites_met($uid,$course_id)||!MYR_Core::lesson_is_released($uid,$course_id,$id))return $this->error('این درس هنوز در دسترس شما نیست.');
        $lessons=MYR_Core::course_lessons($course_id);$ids=array_map(fn($l)=>(int)$l->ID,$lessons);$index=array_search($id,$ids,true);$progress=$this->progress_map($uid,$course_id);$sequence=(bool)get_post_meta($course_id,'_myr_require_sequence',true);
        if($sequence&&$index>0&&($progress[$ids[$index-1]]['status']??'')!=='completed')return $this->error('ابتدا درس قبلی را تکمیل کنید.');
        $revisit_allowed=metadata_exists('post',$course_id,'_myr_allow_revisit')?(bool)get_post_meta($course_id,'_myr_allow_revisit',true):true;
        if(($progress[$id]['status']??'')==='completed'&&!$revisit_allowed)return $this->error('مشاهده یا ویرایش دوباره این درس از طرف مدیر دوره غیرفعال شده است.');
        $saved=$progress[$id]??array();$elapsed=(int)($saved['elapsed_seconds']??0);$active=(int)($saved['active_seconds']??0);$h5p=(int)get_post_meta($id,'_myr_h5p_id',true);$allow_back=metadata_exists('post',$course_id,'_myr_allow_revisit')?(bool)get_post_meta($course_id,'_myr_allow_revisit',true):true;$prev=($index>0&&$allow_back)?$ids[$index-1]:0;$pct=count($ids)?round((($index+1)/count($ids))*100):0;$s=MYR_Core::settings();
        $responses=$this->shared_responses($course_id,$id);
        ob_start();?><div class="myr-player" data-myr-player data-lesson="<?php echo $id; ?>" data-course="<?php echo $course_id; ?>" data-elapsed="<?php echo $elapsed; ?>" data-active="<?php echo $active; ?>" data-green="<?php echo absint(get_post_meta($course_id,'_myr_green_seconds',true)?:480); ?>" data-yellow="<?php echo absint(get_post_meta($course_id,'_myr_yellow_seconds',true)?:900); ?>" data-idle="<?php echo absint(get_post_meta($course_id,'_myr_idle_seconds',true)?:60); ?>">
          <header class="myr-player-head"><a class="myr-close" href="<?php echo esc_url(MYR_Core::page_url('teacher_course',array('course_id'=>$course_id))); ?>"><?php echo $this->icon('close'); ?></a><div class="myr-player-title"><strong><?php echo esc_html($course->post_title); ?></strong><span>درس <?php echo $index+1; ?> · <?php echo esc_html($lesson->post_title); ?></span></div><div class="myr-timers"><div class="myr-timer" title="زمان سپری‌شده"><span><?php echo $this->icon('clock'); ?></span><b data-timer>00:00:00</b></div><small>فعال: <b data-active-timer>00:00:00</b></small></div></header>
          <div class="myr-player-progress"><i style="width:<?php echo $pct; ?>%"></i></div>
          <div class="myr-player-grid"><aside class="myr-lesson-list"><span class="myr-eyebrow">مراحل دوره</span><h2><?php echo esc_html($course->post_title); ?></h2><nav><?php foreach($lessons as $n=>$l):$done=($progress[$l->ID]['status']??'')==='completed';?><a class="<?php echo $l->ID===$id?'current':($done?'done':''); ?>" href="<?php echo ($done&&$allow_back)||$l->ID===$id?esc_url(MYR_Core::page_url('teacher_lesson',array('lesson_id'=>$l->ID))):'#'; ?>"><i><?php echo $done?$this->icon('check'):($n+1); ?></i><span><strong><?php echo esc_html($l->post_title); ?></strong><small><?php echo $done?'تکمیل‌شده':'درس '.($n+1); ?></small></span></a><?php endforeach; ?></nav></aside>
          <main class="myr-stage"><div class="myr-h5p-card"><div class="myr-h5p-heading"><div><span class="myr-pill">درس <?php echo $index+1; ?> از <?php echo count($lessons); ?></span><h1><?php echo esc_html($lesson->post_title); ?></h1></div><?php if(get_post_meta($course_id,'_myr_allow_h5p_export',true)):?><a class="myr-export" href="<?php echo esc_url($this->h5p->export_url($id)); ?>"><?php echo $this->icon('download'); ?> خروجی H5P</a><?php endif;?></div><div class="myr-h5p-content"><?php echo $this->h5p->render($h5p); ?></div></div>
          <?php if($responses):?><section class="myr-shared"><h3>پاسخ‌های به‌اشتراک‌گذاشته‌شده</h3><?php foreach($responses as $r):?><article><strong><?php echo $r->display_name_mode==='anonymous'?'یک فراگیر':esc_html($r->display_name); ?></strong><p><?php echo esc_html($r->response_text); ?></p></article><?php endforeach;?></section><?php endif;?>
          <footer class="myr-stage-actions"><?php if($prev):?><a class="myr-secondary" href="<?php echo esc_url(MYR_Core::page_url('teacher_lesson',array('lesson_id'=>$prev))); ?>">درس قبل</a><?php endif;?><?php $require_h5p=(bool)get_post_meta($course_id,'_myr_require_h5p_complete',true);?><button class="myr-primary" data-complete data-require-h5p="<?php echo $require_h5p?'1':'0'; ?>" <?php disabled($require_h5p&&(($saved['status']??'')!=='completed')); ?>><?php echo $require_h5p&&(($saved['status']??'')!=='completed')?'ابتدا فعالیت را کامل کنید':'ثبت و ادامه'; ?></button></footer></main></div>
          <?php if(!metadata_exists('post',$course_id,'_myr_show_privacy_notice')||get_post_meta($course_id,'_myr_show_privacy_notice',true)):?><div class="myr-privacy" data-privacy><div><span class="myr-privacy-icon"><?php echo $this->icon('shield'); ?></span><h2>پیش از شروع</h2><p><?php echo esc_html($s['privacy_notice']); ?></p><p class="myr-privacy-detail">زمان واقعی و فعال جداگانه ثبت می‌شوند. متن‌های پاک‌شده ذخیره نمی‌شوند.</p><button class="myr-primary" data-privacy-accept>متوجه شدم؛ شروع درس</button></div></div><?php endif;?>
        </div><?php return ob_get_clean();
    }

    private function header(string $title,string $sub,bool $back=false): string {return '<header class="myr-app-head">'.($back?'<a class="myr-head-action" href="'.esc_url(MYR_Core::page_url('teacher_portal')).'">'.$this->icon('back').'</a>':'<div class="myr-avatar">'.esc_html(mb_substr(wp_get_current_user()->display_name,0,1)).'</div>').'<div><strong>'.esc_html($title).'</strong><span>'.esc_html($sub).'</span></div><a class="myr-head-action" href="'.esc_url(wp_logout_url(MYR_Core::page_url('teacher_login'))).'">'.$this->icon('logout').'</a></header>';}
    private function bottom_nav(string $active): string {return '<nav class="myr-bottom"><a class="'.($active==='home'?'active':'').'" href="'.esc_url(MYR_Core::page_url('teacher_portal')).'">'.$this->icon('home').'<span>خانه</span></a><a class="'.($active==='courses'?'active':'').'" href="'.esc_url(MYR_Core::page_url('teacher_portal')).'">'.$this->icon('book').'<span>دوره‌ها</span></a><a href="#">'.$this->icon('user').'<span>حساب من</span></a></nav>';}
    private function error(string $message): string {return '<main class="myr-error"><div class="myr-logo">'.$this->icon('lock').'</div><h1>دسترسی ممکن نیست</h1><p>'.esc_html($message).'</p><a class="myr-primary" href="'.esc_url(MYR_Core::page_url('teacher_portal')).'">بازگشت به خانه</a></main>';}
    private function progress_map(int $uid,int $course): array {global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}myr_progress WHERE user_id=%d AND course_id=%d",$uid,$course),ARRAY_A);$map=array();foreach($rows as $r)$map[$r['lesson_id']]=$r;return $map;}
    private function course_progress(int $uid,int $course): int {$lessons=MYR_Core::course_lessons($course);if(!$lessons)return 0;$p=$this->progress_map($uid,$course);$done=0;foreach($lessons as $l)if(($p[$l->ID]['status']??'')==='completed')$done++;return (int)round(100*$done/count($lessons));}
    private function shared_responses(int $course,int $lesson): array {global $wpdb;$visibility=get_post_meta($course,'_myr_answer_visibility',true)?:'manager';if($visibility==='manager')return array();$uid=get_current_user_id();$school=get_user_meta($uid,'myr_school_name',true);$where="r.course_id=%d AND r.lesson_id=%d AND r.moderation_status='approved'";$args=array($course,$lesson);$peer_ids=get_users(array('role'=>'myr_'.(MYR_Core::learner_role()?:'teacher'),'fields'=>'ID'));$where.=' AND r.user_id IN ('.implode(',',array_map('intval',$peer_ids?:array(0))).')';if($visibility==='school'){$where.=' AND um.meta_value=%s';$args[]=$school;}return $wpdb->get_results($wpdb->prepare("SELECT r.*,u.display_name FROM {$wpdb->prefix}myr_responses r JOIN {$wpdb->users} u ON u.ID=r.user_id LEFT JOIN {$wpdb->usermeta} um ON um.user_id=u.ID AND um.meta_key='myr_school_name' WHERE {$where} ORDER BY r.updated_at DESC LIMIT 30",$args));}
    private function icon(string $name): string {$icons=array('sprout'=>'<path d="M12 22V12m0 0C8 12 5 9 5 5c4 0 7 3 7 7Zm0 0c0-5 3-8 8-8 0 5-3 8-8 8Z"/>','book'=>'<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V5H6.5A2.5 2.5 0 0 0 4 7.5v12Zm0 0A2.5 2.5 0 0 0 6.5 22H20v-5"/>','lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>','check'=>'<path d="m5 12 4 4L19 6"/>','close'=>'<path d="m6 6 12 12M18 6 6 18"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','download'=>'<path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/>','shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>','back'=>'<path d="m9 18 6-6-6-6"/>','logout'=>'<path d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/>','home'=>'<path d="m3 11 9-8 9 8v10h-6v-6H9v6H3V11Z"/>','user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 22c0-4 3-7 8-7s8 3 8 7"/>');return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($icons[$name]??$icons['book']).'</svg>';}
}
