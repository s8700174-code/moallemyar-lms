<?php
defined('ABSPATH') || exit;

final class MYR_REST {
    public function __construct(private MYR_Auth $auth) {
        add_action('rest_api_init', array($this, 'routes'));
    }

    public function routes(): void {
        register_rest_route('myr/v1', '/auth/identify', array('methods'=>'POST','callback'=>array($this,'identify'),'permission_callback'=>'__return_true'));
        register_rest_route('myr/v1', '/auth/request', array('methods'=>'POST','callback'=>array($this,'request_code'),'permission_callback'=>'__return_true'));
        register_rest_route('myr/v1', '/auth/verify', array('methods'=>'POST','callback'=>array($this,'verify_code'),'permission_callback'=>'__return_true'));
        register_rest_route('myr/v1', '/activity', array('methods'=>'POST','callback'=>array($this,'activity'),'permission_callback'=>fn()=>is_user_logged_in()));
        register_rest_route('myr/v1', '/complete', array('methods'=>'POST','callback'=>array($this,'complete'),'permission_callback'=>fn()=>is_user_logged_in()));
        register_rest_route('myr/v1', '/xapi', array('methods'=>'POST','callback'=>array($this,'xapi'),'permission_callback'=>fn()=>is_user_logged_in()));
    }

    public function identify(WP_REST_Request $request) { return $this->respond($this->auth->request_access((string)$request['national_id'], (string)$request['role'])); }
    public function request_code(WP_REST_Request $request) { return $this->respond($this->auth->request_code((string)$request['phone'])); }
    public function verify_code(WP_REST_Request $request) { return $this->respond($this->auth->verify_code((string)$request['challenge'], (string)$request['code'], (bool)($request['remember'] ?? true))); }

    public function activity(WP_REST_Request $request) {
        $lesson_id = absint($request['lesson_id']);
        $course_id = (int) wp_get_post_parent_id($lesson_id);
        if (!$this->can_access($course_id, $lesson_id)) return new WP_Error('forbidden','دسترسی مجاز نیست.',array('status'=>403));
        $active = min(120, max(0, absint($request['active_delta'])));
        $elapsed = min(120, max(0, absint($request['elapsed_delta'])));
        $this->upsert_progress($course_id, $lesson_id, $active, $elapsed, 'started');
        $this->log($course_id, $lesson_id, 'heartbeat', array('active_delta'=>$active,'elapsed_delta'=>$elapsed,'visibility'=>(string)$request['visibility']), (string)$request['session_id']);
        return rest_ensure_response(array('ok'=>true));
    }

    public function complete(WP_REST_Request $request) {
        $lesson_id = absint($request['lesson_id']);
        $course_id = (int) wp_get_post_parent_id($lesson_id);
        if (!$this->can_access($course_id, $lesson_id)) return new WP_Error('forbidden','دسترسی مجاز نیست.',array('status'=>403));
        global $wpdb;
        if (get_post_meta($course_id, '_myr_require_h5p_complete', true) && $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}myr_progress WHERE user_id=%d AND lesson_id=%d",get_current_user_id(),$lesson_id)) !== 'completed') return new WP_Error('h5p_incomplete','ابتدا فعالیت H5P را کامل کنید.',array('status'=>409));
        $this->upsert_progress($course_id, $lesson_id, 0, 0, 'completed');
        $this->log($course_id, $lesson_id, 'lesson_completed', array(), (string)$request['session_id']);
        $this->maybe_complete_course($course_id);
        return rest_ensure_response(array('ok'=>true,'next_url'=>$this->next_url($course_id, $lesson_id)));
    }

    public function xapi(WP_REST_Request $request) {
        $lesson_id = absint($request['lesson_id']);
        $course_id = (int) wp_get_post_parent_id($lesson_id);
        if (!$this->can_access($course_id, $lesson_id)) return new WP_Error('forbidden','دسترسی مجاز نیست.',array('status'=>403));
        $statement = $request->get_param('statement');
        if (!is_array($statement)) return new WP_Error('bad_statement','رویداد H5P معتبر نیست.',array('status'=>422));
        $encoded = wp_json_encode($statement);
        if (strlen($encoded) > 60000) return new WP_Error('too_large','اندازه رویداد بیش از حد مجاز است.',array('status'=>413));
        $verb = sanitize_key((string)($statement['verb']['display']['en-US'] ?? $statement['verb']['id'] ?? 'xapi'));
        $this->log($course_id, $lesson_id, 'h5p_' . substr($verb, 0, 42), array(
            'statement' => $statement,
            'client_elapsed' => absint($request['client_elapsed']),
            'client_active' => absint($request['client_active']),
        ), (string)$request['session_id']);
        $result = $statement['result'] ?? array();
        if (isset($result['response'])) $this->save_response($course_id, $lesson_id, (string)$result['response'], $statement);
        $extensions = $statement['object']['definition']['extensions'] ?? array();
        $is_child = !empty($extensions['http://h5p.org/x-api/h5p-subContentId']) || str_contains((string)($statement['object']['id'] ?? ''), 'subContentId=');
        $completed = !$is_child && (str_ends_with((string)($statement['verb']['id'] ?? ''), '/completed') || ($result['completion'] ?? false) === true);
        if ($completed) {
            $this->upsert_progress($course_id, $lesson_id, 0, 0, 'completed', $result['score']['raw'] ?? null, $result['score']['max'] ?? null);
            $this->maybe_complete_course($course_id);
        }
        return rest_ensure_response(array('ok'=>true, 'completed'=>$completed));
    }

    private function respond($value) { return is_wp_error($value) ? $value : rest_ensure_response($value); }
    private function can_access(int $course_id, int $lesson_id): bool {
        $uid=get_current_user_id();
        if(!$course_id||get_post_type($lesson_id)!=='myr_lesson'||get_post_status($lesson_id)!=='publish'||!MYR_Core::is_enrolled($uid,$course_id)||!MYR_Core::prerequisites_met($uid,$course_id)||!MYR_Core::lesson_is_released($uid,$course_id,$lesson_id))return false;
        if(!get_post_meta($course_id,'_myr_require_sequence',true))return true;
        $lessons=MYR_Core::course_lessons($course_id);$ids=array_map(fn($l)=>(int)$l->ID,$lessons);$index=array_search($lesson_id,$ids,true);if($index===false||$index===0)return true;
        global $wpdb;return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}myr_progress WHERE user_id=%d AND lesson_id=%d",$uid,$ids[$index-1]))==='completed';
    }

    private function upsert_progress(int $course_id, int $lesson_id, int $active, int $elapsed, string $status, $score=null, $max=null): void {
        global $wpdb; $table=$wpdb->prefix.'myr_progress'; $uid=get_current_user_id(); $now=current_time('mysql',true);
        $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND lesson_id=%d",$uid,$lesson_id));
        if ($id) {
            $sql="UPDATE {$table} SET active_seconds=active_seconds+%d, elapsed_seconds=elapsed_seconds+%d, status=%s, updated_at=%s";
            $existing_status=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id=%d",$id));
            if($existing_status==='completed')$status='completed';
            $args=array($active,$elapsed,$status,$now);
            if ($score!==null) { $sql.=', score=%f'; $args[]=(float)$score; }
            if ($max!==null) { $sql.=', max_score=%f'; $args[]=(float)$max; }
            if ($status==='completed') { $sql.=', completed_at=COALESCE(completed_at,%s)'; $args[]=$now; }
            $sql.=' WHERE id=%d'; $args[]=(int)$id; $wpdb->query($wpdb->prepare($sql,$args));
        } else {
            $wpdb->insert($table,array('user_id'=>$uid,'course_id'=>$course_id,'lesson_id'=>$lesson_id,'status'=>$status,'elapsed_seconds'=>$elapsed,'active_seconds'=>$active,'visits'=>1,'score'=>$score,'max_score'=>$max,'started_at'=>$now,'completed_at'=>$status==='completed'?$now:null,'updated_at'=>$now));
        }
    }

    private function log(int $course_id,int $lesson_id,string $type,array $payload,string $session): void {
        global $wpdb; $wpdb->insert($wpdb->prefix.'myr_events',array('user_id'=>get_current_user_id(),'course_id'=>$course_id,'lesson_id'=>$lesson_id,'session_id'=>sanitize_text_field(substr($session,0,64)),'event_type'=>$type,'payload_json'=>wp_json_encode($payload,JSON_UNESCAPED_UNICODE),'occurred_at'=>current_time('mysql',true)));
    }

    private function save_response(int $course_id,int $lesson_id,string $text,array $statement): void {
        global $wpdb; $table=$wpdb->prefix.'myr_responses'; $uid=get_current_user_id(); $course_visibility=get_post_meta($course_id,'_myr_answer_visibility',true) ?: 'manager';
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id=%d AND lesson_id=%d",$uid,$lesson_id));
        $data=array('response_text'=>wp_strip_all_tags($text),'response_json'=>wp_json_encode($statement,JSON_UNESCAPED_UNICODE),'visibility'=>$course_visibility,'updated_at'=>current_time('mysql',true));
        if ($existing) { $wpdb->query($wpdb->prepare("UPDATE {$table} SET response_text=%s,response_json=%s,visibility=%s,edit_count=edit_count+1,updated_at=%s WHERE id=%d",$data['response_text'],$data['response_json'],$data['visibility'],$data['updated_at'],$existing)); }
        else { $data+=array('user_id'=>$uid,'course_id'=>$course_id,'lesson_id'=>$lesson_id,'display_name_mode'=>'named','moderation_status'=>'approved','edit_count'=>0); $wpdb->insert($table,$data); }
        do_action('myr_response_saved',$uid,$course_id,$lesson_id,$text,$statement);
    }

    private function maybe_complete_course(int $course_id): void {
        global $wpdb; $lessons=MYR_Core::course_lessons($course_id); if(!$lessons)return; $ids=wp_list_pluck($lessons,'ID');
        $done=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}myr_progress WHERE user_id=%d AND status='completed' AND lesson_id IN (".implode(',',array_map('intval',$ids)).")",get_current_user_id()));
        if($done>=count($ids)) $wpdb->update($wpdb->prefix.'myr_enrollments',array('status'=>'completed','completed_at'=>current_time('mysql',true)),array('user_id'=>get_current_user_id(),'course_id'=>$course_id),array('%s','%s'),array('%d','%d'));
    }

    private function next_url(int $course_id,int $lesson_id): string {
        $lessons=MYR_Core::course_lessons($course_id); foreach($lessons as $i=>$lesson){ if((int)$lesson->ID===$lesson_id && isset($lessons[$i+1]) && MYR_Core::lesson_is_released(get_current_user_id(),$course_id,$lessons[$i+1]->ID)) return MYR_Core::page_url('teacher_lesson',array('lesson_id'=>$lessons[$i+1]->ID)); }
        return MYR_Core::page_url('teacher_course',array('course_id'=>$course_id));
    }
}
