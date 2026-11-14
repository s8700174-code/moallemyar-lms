<?php
defined('ABSPATH') || exit;

final class MYR_Admin {
    public function __construct(private MYR_H5P $h5p) {
        add_action('admin_post_myr_save_learner', array($this,'save_learner'));
        add_action('admin_menu', array($this,'menus'));
        add_action('add_meta_boxes', array($this,'metaboxes'));
        add_action('save_post_myr_course', array($this,'save_course'));
        add_action('save_post_myr_lesson', array($this,'save_lesson'));
        add_action('post_edit_form_tag', fn()=>print ' enctype="multipart/form-data"');
        add_action('admin_enqueue_scripts', array($this,'assets'));
        add_action('admin_notices', array($this,'notices'));
        add_action('admin_post_myr_save_settings', array($this,'save_settings'));
        add_action('admin_post_myr_import_teachers', array($this,'import_teachers'));
        add_filter('manage_myr_lesson_posts_columns', array($this,'lesson_columns'));
        add_action('manage_myr_lesson_posts_custom_column', array($this,'lesson_column'),10,2);
    }

    public function menus(): void {
        add_menu_page('معلم‌یار','معلم‌یار','edit_myr_courses','myr-dashboard',array($this,'dashboard'),'dashicons-welcome-learn-more',25);
        add_submenu_page('myr-dashboard','نمای کلی','نمای کلی','edit_myr_courses','myr-dashboard',array($this,'dashboard'));
        add_submenu_page('myr-dashboard','معلمان و والدین','معلمان و والدین','manage_myr_teachers','myr-teachers',array($this,'teachers'));
        add_submenu_page('myr-dashboard','گزارش‌ها','گزارش‌ها','view_myr_reports','myr-reports',array($this,'reports'));
        add_submenu_page('myr-dashboard','تنظیمات','تنظیمات','manage_options','myr-settings',array($this,'settings'));
    }

    public function assets(string $hook): void {
        if (str_contains($hook,'myr') || in_array(get_post_type(),array('myr_course','myr_lesson'),true)) wp_enqueue_style('myr-admin',MYR_URL.'assets/css/admin.css',array(),MYR_VERSION);
    }

    public function metaboxes(): void {
        add_meta_box('myr-course-settings','تنظیمات اجرای دوره',array($this,'course_box'),'myr_course','normal','high');
        add_meta_box('myr-lesson-h5p','محتوای H5P درس',array($this,'lesson_box'),'myr_lesson','normal','high');
        add_meta_box('myr-lesson-course','جایگاه درس',array($this,'lesson_course_box'),'myr_lesson','side','high');
    }

    public function course_box(WP_Post $post): void {
        wp_nonce_field('myr_save_course','myr_course_nonce');
        $all=get_posts(array('post_type'=>'myr_course','post_status'=>array('publish','draft'),'numberposts'=>-1,'exclude'=>array($post->ID)));
        $pre=(array)get_post_meta($post->ID,'_myr_prerequisites',true);
        $fields=array(
            '_myr_duration_days'=>array('مدت پیشنهادی دوره (روز)','number',20),
            '_myr_green_seconds'=>array('پایان محدوده سبز (ثانیه)','number',480),
            '_myr_yellow_seconds'=>array('پایان محدوده زرد (ثانیه)','number',900),
            '_myr_idle_seconds'=>array('تشخیص عدم فعالیت پس از (ثانیه)','number',60),
        );
        echo '<div class="myr-admin-grid"><label><span>مخاطبان دوره</span><select name="_myr_audience">';
        $audience=get_post_meta($post->ID,'_myr_audience',true)?:'teacher';
        foreach(array('teacher'=>'معلمان','parent'=>'والدین','both'=>'معلمان و والدین') as $value=>$label) echo '<option value="'.$value.'" '.selected($audience,$value,false).'>'.$label.'</option>';
        echo '</select></label>';

        foreach($fields as $key=>$f){$v=get_post_meta($post->ID,$key,true); if($v==='')$v=$f[2]; echo '<label><span>'.esc_html($f[0]).'</span><input type="number" min="0" name="'.esc_attr($key).'" value="'.esc_attr($v).'"></label>';}
        echo '<label><span>نمایش پاسخ‌ها</span><select name="_myr_answer_visibility">';
        $vis=get_post_meta($post->ID,'_myr_answer_visibility',true)?:'manager'; foreach(array('manager'=>'فقط مدیر دوره','school'=>'فراگیران هم‌نقش در همان مدرسه','course'=>'فراگیران هم‌نقش در همین دوره','all'=>'فراگیران هم‌نقش مجاز') as $k=>$v) echo '<option value="'.$k.'" '.selected($vis,$k,false).'>'.$v.'</option>'; echo '</select></label>';
        echo '<label><span>پیش‌نیازها</span><select name="_myr_prerequisites[]" multiple size="4">'; foreach($all as $c)echo '<option value="'.$c->ID.'" '.selected(in_array($c->ID,$pre),true,false).'>'.esc_html($c->post_title).'</option>'; echo '</select></label></div>';
        $checks=array('_myr_require_sequence'=>array('ترتیب درس‌ها الزامی باشد',true),'_myr_require_h5p_complete'=>array('رویداد تکمیل H5P برای ادامه الزامی باشد',false),'_myr_allow_revisit'=>array('بازگشت به درس‌های قبل مجاز باشد',true),'_myr_allow_h5p_export'=>array('دریافت خروجی H5P برای فراگیر فعال باشد',true),'_myr_show_privacy_notice'=>array('اطلاع ثبت داده‌ها پیش از شروع نمایش داده شود',true));
        echo '<div class="myr-checks">'; foreach($checks as $k=>$item){$value=metadata_exists('post',$post->ID,$k)?(bool)get_post_meta($post->ID,$k,true):$item[1];echo '<label><input type="checkbox" name="'.$k.'" value="1" '.checked($value,true,false).'> '.$item[0].'</label>';} echo '</div>';
    }

    public function lesson_box(WP_Post $post): void {
        wp_nonce_field('myr_save_lesson','myr_lesson_nonce'); $current=(int)get_post_meta($post->ID,'_myr_h5p_id',true);
        echo '<div class="myr-h5p-box"><p><strong>یک فایل H5P بارگذاری کنید</strong><br><small>فایل به کتابخانه رسمی H5P افزوده و به همین درس متصل می‌شود.</small></p><input type="file" name="myr_h5p_file" accept=".h5p,application/zip">';
        echo '<div class="myr-divider">یا</div><label><span>انتخاب از محتوای موجود H5P</span><select name="_myr_h5p_id"><option value="0">انتخاب نشده</option>'; foreach($this->h5p->contents() as $item)echo '<option value="'.(int)$item['id'].'" '.selected($current,(int)$item['id'],false).'>#'.(int)$item['id'].' — '.esc_html($item['title']).'</option>'; echo '</select></label>';
        if($current){echo '<p class="myr-inline-actions"><a class="button" href="'.esc_url(admin_url('admin.php?page=h5p_new&id='.$current)).'">ویرایش در H5P</a> <a class="button" href="'.esc_url($this->h5p->export_url($post->ID)).'">دریافت فایل H5P</a></p>';}
        if(!$this->h5p->available())echo '<div class="notice notice-warning inline"><p>افزونه رسمی H5P فعال نیست؛ ابتدا آن را نصب و فعال کنید.</p></div>'; echo '</div>';
    }

    public function lesson_course_box(WP_Post $post): void {
        $courses=get_posts(array('post_type'=>'myr_course','post_status'=>array('publish','draft'),'numberposts'=>-1));
        echo '<label class="myr-block"><span>دوره مادر</span><select name="myr_parent_course" required><option value="">انتخاب کنید</option>'; foreach($courses as $c)echo '<option value="'.$c->ID.'" '.selected($post->post_parent,$c->ID,false).'>'.esc_html($c->post_title).'</option>'; echo '</select></label>';
        echo '<label class="myr-block"><span>شماره/ترتیب درس</span><input type="number" min="0" name="menu_order" value="'.(int)$post->menu_order.'"></label>';
        $release=get_post_meta($post->ID,'_myr_release_day',true); if($release==='')$release=max(1,(int)$post->menu_order+1);
        echo '<label class="myr-block"><span>روز بازشدن از زمان عضویت</span><input type="number" min="1" name="_myr_release_day" value="'.(int)$release.'"><small>عدد ۱ یعنی از همان روز اول در دسترس باشد.</small></label>';
    }

    public function save_course(int $id): void {
        if(!isset($_POST['myr_course_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['myr_course_nonce'])),'myr_save_course')||!current_user_can('edit_post',$id)||wp_is_post_revision($id))return;
        $audience=sanitize_key($_POST['_myr_audience']??'teacher');
        update_post_meta($id,'_myr_audience',in_array($audience,array('teacher','parent','both'),true)?$audience:'teacher');
        foreach(array('_myr_duration_days','_myr_green_seconds','_myr_yellow_seconds','_myr_idle_seconds') as $key)update_post_meta($id,$key,absint($_POST[$key]??0));
        update_post_meta($id,'_myr_answer_visibility',sanitize_key($_POST['_myr_answer_visibility']??'manager'));
        update_post_meta($id,'_myr_prerequisites',array_values(array_filter(array_map('absint',(array)($_POST['_myr_prerequisites']??array())))));
        foreach(array('_myr_require_sequence','_myr_require_h5p_complete','_myr_allow_revisit','_myr_allow_h5p_export','_myr_show_privacy_notice') as $key)update_post_meta($id,$key,isset($_POST[$key])?1:0);
    }

    public function save_lesson(int $id): void {
        if(!isset($_POST['myr_lesson_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['myr_lesson_nonce'])),'myr_save_lesson')||!current_user_can('edit_post',$id)||wp_is_post_revision($id))return;
        $h5p_id=absint($_POST['_myr_h5p_id']??0);
        if(isset($_FILES['myr_h5p_file'])&&($_FILES['myr_h5p_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            $result=$this->h5p->import_uploaded_file($_FILES['myr_h5p_file']);
            if(is_wp_error($result))set_transient('myr_admin_notice_'.get_current_user_id(),array('error',$result->get_error_message()),60); else {$h5p_id=(int)$result; set_transient('myr_admin_notice_'.get_current_user_id(),array('success','فایل H5P با موفقیت به درس متصل شد.'),60);}
        }
        update_post_meta($id,'_myr_h5p_id',$h5p_id);
        update_post_meta($id,'_myr_release_day',max(1,absint($_POST['_myr_release_day']??1)));
        $parent=absint($_POST['myr_parent_course']??0); $order=absint($_POST['menu_order']??0);
        remove_action('save_post_myr_lesson',array($this,'save_lesson'));
        wp_update_post(array('ID'=>$id,'post_parent'=>$parent,'menu_order'=>$order));
        add_action('save_post_myr_lesson',array($this,'save_lesson'));
    }

    public function notices(): void {
        if(!$this->h5p->available()&&current_user_can('activate_plugins'))echo '<div class="notice notice-warning"><p><strong>معلم‌یار:</strong> برای بارگذاری و نمایش درس‌ها، افزونه رسمی <a href="'.esc_url(admin_url('plugin-install.php?s=H5P&tab=search&type=term')).'">H5P</a> را نصب و فعال کنید.</p></div>';
        $n=get_transient('myr_admin_notice_'.get_current_user_id()); if($n){delete_transient('myr_admin_notice_'.get_current_user_id()); echo '<div class="notice notice-'.esc_attr($n[0]).' is-dismissible"><p>'.esc_html($n[1]).'</p></div>';}
    }

    public function dashboard(): void {
        global $wpdb; $teachers=count(get_users(array('role__in'=>array('myr_teacher','myr_parent'),'fields'=>'ids'))); $courses=wp_count_posts('myr_course')->publish??0; $done=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}myr_progress WHERE status='completed'");
        echo '<div class="wrap myr-wrap"><div class="myr-title"><div><h1>نمای کلی معلم‌یار</h1><p>وضعیت اجرای دوره‌های آموزشی مدارس</p></div><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type=myr_course')).'">دوره تازه</a></div><div class="myr-stats"><div><span>فراگیران ثبت‌شده</span><strong>'.$teachers.'</strong></div><div><span>دوره‌های فعال</span><strong>'.$courses.'</strong></div><div><span>درس‌های تکمیل‌شده</span><strong>'.$done.'</strong></div><div><span>اتصال H5P</span><strong>'.($this->h5p->available()?'فعال':'نیازمند نصب').'</strong></div></div><div class="myr-panel"><h2>شروع سریع</h2><ol><li>افزونه رسمی H5P را فعال کنید.</li><li>دوره را بسازید و تنظیمات زمان و پیش‌نیاز را تعیین کنید.</li><li>برای هر درس یک فایل H5P بارگذاری کنید.</li><li>معلمان و والدین را ثبت یا با CSV وارد و در دوره عضو کنید.</li></ol></div></div>';
    }

    public function teachers(): void {
        $courses=get_posts(array('post_type'=>'myr_course','post_status'=>'publish','numberposts'=>-1));
        echo '<div class="wrap myr-wrap"><h1>معلمان و والدین</h1><div class="myr-panel"><h2>ثبت یا به‌روزرسانی فراگیر</h2><p>حساب بر اساس نقش و شماره همراه شناسایی می‌شود. کد ملی فقط به‌صورت هش ذخیره می‌شود.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="myr_save_learner">';
        wp_nonce_field('myr_save_learner');
        echo '<div class="myr-admin-grid"><label><span>نقش</span><select name="role"><option value="teacher">معلم</option><option value="parent">والدین</option></select></label>';
        foreach(array('first_name'=>'نام','last_name'=>'نام خانوادگی','phone'=>'شماره همراه','national_id'=>'کد ملی','student_national_id'=>'کد ملی دانش‌آموز (اختیاری، فقط والدین)','school'=>'مدرسه') as $key=>$label) echo '<label><span>'.$label.'</span><input name="'.$key.'" '.(in_array($key,array('phone','national_id'),true)?'required inputmode="numeric"':'').'></label>';
        echo '<label><span>دوره‌ها (انتخاب چندگانه)</span><select name="course_ids[]" multiple size="5">';
        foreach($courses as $course) echo '<option value="'.$course->ID.'">#'.$course->ID.' — '.esc_html($course->post_title).' ('.esc_html(get_post_meta($course->ID,'_myr_audience',true)?:'teacher').')</option>';
        echo '</select></label></div><p><button class="button button-primary">ذخیره فراگیر</button></p></form></div>';
        echo '<div class="myr-panel"><h2>ورود گروهی CSV</h2><p><code>phone,national_id,first_name,last_name,school,course_ids,role,student_national_id</code></p><p>role برابر teacher یا parent؛ در صورت نبود ستون، معلم در نظر گرفته می‌شود. شناسه دوره‌ها با | جدا شود.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data"><input type="hidden" name="action" value="myr_import_teachers">';
        wp_nonce_field('myr_import_teachers');
        echo '<input type="file" name="teachers_csv" accept=".csv,text/csv" required> <button class="button">ورود کاربران</button></form></div><div class="myr-panel"><table class="widefat striped"><thead><tr><th>نام</th><th>نقش</th><th>همراه</th><th>چهار رقم آخر کد ملی</th><th>مدرسه</th><th>ورود اخیر</th></tr></thead><tbody>';
        foreach(get_users(array('role__in'=>array('myr_teacher','myr_parent'),'number'=>500)) as $u) echo '<tr><td>'.esc_html($u->display_name).'</td><td>'.(MYR_Core::learner_role($u->ID)==='parent'?'والدین':'معلم').'</td><td dir="ltr">'.esc_html(get_user_meta($u->ID,'myr_phone',true)).'</td><td>'.esc_html(get_user_meta($u->ID,'myr_national_id_last4',true)).'</td><td>'.esc_html(get_user_meta($u->ID,'myr_school_name',true)).'</td><td>'.esc_html(get_user_meta($u->ID,'myr_last_login',true)?:'هنوز وارد نشده').'</td></tr>';
        echo '</tbody></table></div></div>';
    }

    public function save_learner(): void {
        if(!current_user_can('manage_myr_teachers'))wp_die('دسترسی مجاز نیست.', '', array('response'=>403));
        check_admin_referer('myr_save_learner');
        $result=$this->upsert_learner(wp_unslash($_POST));
        set_transient('myr_admin_notice_'.get_current_user_id(),array(is_wp_error($result)?'error':'success',is_wp_error($result)?$result->get_error_message():'حساب و عضویت‌های فراگیر ذخیره شد.'),60);
        wp_safe_redirect(admin_url('admin.php?page=myr-teachers'));exit;
    }

    public function upsert_learner(array $data) {
        if(!current_user_can('manage_myr_teachers'))return new WP_Error('forbidden','اجازه مدیریت کاربران ندارید.');
        $role=sanitize_key($data['role']??'teacher');
        $phone=MYR_Core::normalize_phone((string)($data['phone']??''));
        $national=MYR_Core::normalize_digits((string)($data['national_id']??''));
        $student=MYR_Core::normalize_digits((string)($data['student_national_id']??''));
        if(!in_array($role,array('teacher','parent'),true)||!preg_match('/^09\d{9}$/',$phone)||!preg_match('/^\d{10}$/',$national)||($student!==''&&!preg_match('/^\d{10}$/',$student)))return new WP_Error('invalid_learner','نقش، شماره همراه یا کد ملی معتبر نیست.');
        $courses=is_array($data['course_ids']??null)?$data['course_ids']:explode('|',(string)($data['course_ids']??''));
        $courses=array_unique(array_filter(array_map('absint',$courses)));
        foreach($courses as $cid){$audience=get_post_meta($cid,'_myr_audience',true)?:'teacher';if(get_post_type($cid)!=='myr_course'||get_post_status($cid)!=='publish'||!in_array($audience,array('both',$role),true))return new WP_Error('invalid_course','دوره انتخاب‌شده با نقش فراگیر سازگار نیست یا منتشر نشده است.');}
        $login=$role.'_'.$phone;$user=get_user_by('login',$login);
        $same_phone=get_users(array('role'=>'myr_'.$role,'number'=>2,'meta_key'=>'myr_phone','meta_value'=>$phone));
        if(!$user&&count($same_phone)===1)$user=$same_phone[0];
        if(count($same_phone)>1)return new WP_Error('duplicate_phone','بیش از یک حساب با این شماره و نقش وجود دارد.');
        if($user&&(!in_array('myr_'.$role,$user->roles,true)||user_can($user,'manage_options')||user_can($user,'view_myr_reports')))return new WP_Error('protected_account','این حساب از این فرم قابل ویرایش نیست.');
        $hash=hash_hmac('sha256',$national,wp_salt('auth'));
        $duplicate=get_users(array('role'=>'myr_'.$role,'number'=>2,'meta_key'=>'myr_national_id_hash','meta_value'=>$hash,'exclude'=>$user?array($user->ID):array()));
        if($duplicate)return new WP_Error('duplicate_identity','این کد ملی قبلاً برای همین نقش ثبت شده است.');
        $first=sanitize_text_field($data['first_name']??'');$last=sanitize_text_field($data['last_name']??'');
        $values=array('first_name'=>$first,'last_name'=>$last,'display_name'=>trim($first.' '.$last)?:($role==='parent'?'والد':'معلم'));
        if($user){$values['ID']=$user->ID;$uid=wp_update_user($values);}else{$values+=array('user_login'=>$login,'user_pass'=>wp_generate_password(32,true,true),'role'=>'myr_'.$role);$uid=wp_insert_user($values);}
        if(is_wp_error($uid))return $uid;
        update_user_meta($uid,'myr_phone',$phone);update_user_meta($uid,'myr_national_id_hash',$hash);update_user_meta($uid,'myr_national_id_last4',substr($national,-4));delete_user_meta($uid,'myr_national_id');
        update_user_meta($uid,'myr_school_name',sanitize_text_field($data['school']??''));
        if($role==='parent'&&$student!=='')update_user_meta($uid,'myr_student_id_hash',hash_hmac('sha256',$student,wp_salt('auth')));
        global $wpdb;
        foreach($courses as $cid)$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}myr_enrollments (user_id,course_id,status,enrolled_at) VALUES (%d,%d,'active',%s) ON DUPLICATE KEY UPDATE status=IF(status='completed','completed','active')",$uid,$cid,current_time('mysql',true)));
        return (int)$uid;
    }

    public function import_teachers(): void {
        if(!current_user_can('manage_myr_teachers'))wp_die('دسترسی مجاز نیست.', '', array('response'=>403));
        check_admin_referer('myr_import_teachers');
        $file=$_FILES['teachers_csv']??null;
        if(!$file||$file['error']!==UPLOAD_ERR_OK)wp_die('فایل CSV دریافت نشد.');
        $handle=fopen($file['tmp_name'],'r');if(!$handle)wp_die('خواندن فایل ممکن نیست.');
        $header=fgetcsv($handle);if(!$header){fclose($handle);wp_die('فایل CSV خالی است.');}
        $headers=array_map(fn($v)=>ltrim(trim(strtolower($v)),"\xEF\xBB\xBF"),$header);
        if(!in_array('phone',$headers,true)||!in_array('national_id',$headers,true)){fclose($handle);wp_die('ستون‌های phone و national_id ضروری هستند.');}
        $count=0;$errors=0;
        while(($row=fgetcsv($handle))!==false){if(count($row)>count($headers)){$errors++;continue;}$data=array_combine($headers,array_pad($row,count($headers),''));if(empty($data['role']))$data['role']='teacher';$result=$this->upsert_learner($data);is_wp_error($result)?$errors++:$count++;}
        fclose($handle);set_transient('myr_admin_notice_'.get_current_user_id(),array($errors?'warning':'success',"{$count} فراگیر ذخیره شد؛ {$errors} ردیف نامعتبر بود."),60);wp_safe_redirect(admin_url('admin.php?page=myr-teachers'));exit;
    }

    public function reports(): void {
        global $wpdb; $course=absint($_GET['course_id']??0); $courses=get_posts(array('post_type'=>'myr_course','post_status'=>'publish','numberposts'=>-1));
        echo '<div class="wrap myr-wrap"><div class="myr-title"><div><h1>گزارش دوره‌ها</h1><p>زمان واقعی، زمان فعال و تکمیل درس‌ها</p></div><form><input type="hidden" name="page" value="myr-reports"><select name="course_id" onchange="this.form.submit()"><option value="0">همه دوره‌ها</option>'; foreach($courses as $c)echo '<option value="'.$c->ID.'" '.selected($course,$c->ID,false).'>'.esc_html($c->post_title).'</option>'; echo '</select></form></div><div class="myr-panel"><table class="widefat striped"><thead><tr><th>فراگیر</th><th>مدرسه</th><th>دوره</th><th>پیشرفت</th><th>زمان واقعی</th><th>زمان فعال</th><th>آخرین فعالیت</th></tr></thead><tbody>';
        $where=$course?$wpdb->prepare('WHERE e.course_id=%d',$course):''; $rows=$wpdb->get_results("SELECT e.user_id,e.course_id,COUNT(DISTINCT p.lesson_id) seen,SUM(p.status='completed') done,SUM(p.elapsed_seconds) elapsed,SUM(p.active_seconds) active,MAX(p.updated_at) last_seen FROM {$wpdb->prefix}myr_enrollments e LEFT JOIN {$wpdb->prefix}myr_progress p ON p.user_id=e.user_id AND p.course_id=e.course_id {$where} GROUP BY e.user_id,e.course_id ORDER BY last_seen DESC");
        foreach($rows as $r){$u=get_userdata($r->user_id);$total=count(MYR_Core::course_lessons($r->course_id));$pct=$total?round(100*$r->done/$total):0;echo '<tr><td>'.esc_html($u?$u->display_name:'—').'</td><td>'.esc_html(get_user_meta($r->user_id,'myr_school_name',true)).'</td><td>'.esc_html(get_the_title($r->course_id)).'</td><td><div class="myr-mini-progress"><i style="width:'.$pct.'%"></i></div>'.$pct.'٪</td><td>'.esc_html($this->duration($r->elapsed)).'</td><td>'.esc_html($this->duration($r->active)).'</td><td>'.esc_html($r->last_seen?:'—').'</td></tr>';}
        echo '</tbody></table></div>';
        $response_where=$course?$wpdb->prepare('WHERE r.course_id=%d',$course):'';
        $responses=$wpdb->get_results("SELECT r.*,u.display_name,p.post_title lesson_title FROM {$wpdb->prefix}myr_responses r JOIN {$wpdb->users} u ON u.ID=r.user_id LEFT JOIN {$wpdb->posts} p ON p.ID=r.lesson_id {$response_where} ORDER BY r.updated_at DESC LIMIT 200");
        echo '<div class="myr-panel"><h2>پاسخ‌های ثبت‌شده H5P</h2><p>این بخش فقط پاسخ نهایی اعلام‌شده توسط H5P را نشان می‌دهد؛ متن پاک‌شده ضبط نمی‌شود.</p><table class="widefat striped"><thead><tr><th>فراگیر</th><th>درس</th><th>پاسخ نهایی</th><th>ویرایش</th><th>زمان ثبت</th></tr></thead><tbody>';
        foreach($responses as $r)echo '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->lesson_title).'</td><td class="myr-response-cell">'.esc_html($r->response_text).'</td><td>'.(int)$r->edit_count.'</td><td>'.esc_html($r->updated_at).'</td></tr>';
        if(!$responses)echo '<tr><td colspan="5">هنوز پاسخی ثبت نشده است.</td></tr>'; echo '</tbody></table></div></div>';
    }

    public function settings(): void {
        $s=MYR_Core::settings(); echo '<div class="wrap myr-wrap"><h1>تنظیمات معلم‌یار</h1><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('myr_save_settings'); echo '<input type="hidden" name="action" value="myr_save_settings"><div class="myr-panel myr-settings">';
        $fields=array('brand_name'=>'نام سامانه','support_text'=>'متن پشتیبانی','sms_endpoint'=>'نشانی API پیامک','sms_token'=>'توکن API پیامک','sms_template'=>'قالب پیامک','otp_ttl'=>'اعتبار کد (ثانیه)','otp_cooldown'=>'فاصله ارسال مجدد (ثانیه)','trusted_days'=>'ماندگاری ورود (روز)','privacy_notice'=>'متن اطلاع‌رسانی ثبت داده‌ها');
        echo '<label><span>روش پیامک</span><select name="sms_driver"><option value="development" '.selected($s['sms_driver'],'development',false).'>آزمایشی (بدون ارسال)</option><option value="generic_json" '.selected($s['sms_driver'],'generic_json',false).'>وب‌سرویس JSON عمومی</option></select></label>'; foreach($fields as $k=>$label){echo '<label><span>'.$label.'</span>'.($k==='privacy_notice'?'<textarea name="'.$k.'">'.esc_textarea($s[$k]).'</textarea>':'<input name="'.$k.'" value="'.esc_attr($s[$k]).'">').'</label>';}
        echo '<p><button class="button button-primary">ذخیره تنظیمات</button></p></div></form></div>';
    }

    public function save_settings(): void {
        if(!current_user_can('manage_options')||!check_admin_referer('myr_save_settings'))wp_die('دسترسی مجاز نیست.'); $old=MYR_Core::settings();
        foreach(array_keys(MYR_Install::defaults()) as $k){if(isset($_POST[$k]))$old[$k]=in_array($k,array('otp_ttl','otp_cooldown','trusted_days'),true)?absint($_POST[$k]):sanitize_textarea_field(wp_unslash($_POST[$k]));} update_option('myr_settings',$old); wp_safe_redirect(admin_url('admin.php?page=myr-settings&updated=1')); exit;
    }

    public function lesson_columns(array $c): array {$c['myr_course']='دوره';$c['myr_h5p']='H5P';return $c;}
    public function lesson_column(string $column,int $id): void {if($column==='myr_course')echo esc_html(get_the_title(wp_get_post_parent_id($id))?:'—');if($column==='myr_h5p')echo (int)get_post_meta($id,'_myr_h5p_id',true)?'متصل':'بدون محتوا';}
    private function duration($seconds): string {$seconds=(int)$seconds;return sprintf('%02d:%02d:%02d',intdiv($seconds,3600),intdiv($seconds%3600,60),$seconds%60);}
}
