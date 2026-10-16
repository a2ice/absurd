<?php
/**
 * Plugin Name: ABSURD — Content Core
 * Description: Local-first performance / show / venue content model for the ABSURD theatre. No frontend theme or client content.
 * Version: 0.1.2
 * Requires PHP: 8.1
 * Text Domain: absurdtheater-core
 */
defined('ABSPATH') || exit;

final class Absurdtheater_Core {
    const VERSION = '0.1.2';
    const STATES = ['scheduled', 'cancelled', 'postponed'];
    const SALES = ['unavailable', 'on_sale', 'sold_out'];

    static function boot(): void {
        add_action('init', [__CLASS__, 'register']);
        add_action('rest_api_init', [__CLASS__, 'rest_routes']);
        add_filter('rest_pre_insert_show', [__CLASS__, 'rest_guard'], 10, 2);
        add_filter('wp_insert_post_data', [__CLASS__, 'classic_guard'], 10, 2);
        add_action('save_post_show', [__CLASS__, 'save_classic'], 20, 2);
        add_action('add_meta_boxes_show', [__CLASS__, 'classic_box']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'editor_assets']);
        add_action('admin_notices', [__CLASS__, 'admin_error']);
        add_action('venue_add_form_fields', [__CLASS__, 'venue_add_fields']);
        add_action('venue_edit_form_fields', [__CLASS__, 'venue_edit_fields']);
        add_action('created_venue', [__CLASS__, 'save_venue_fields']);
        add_action('edited_venue', [__CLASS__, 'save_venue_fields']);
        add_filter('rest_pre_dispatch', [__CLASS__, 'private_rest_guard'], 10, 3);
    }

    static function register(): void {
        register_post_type('performance', [
            'label' => 'Спектакли', 'labels' => ['singular_name' => 'Спектакль'],
            'public' => true, 'show_ui' => true, 'show_in_rest' => true,
            'has_archive' => 'spektakli', 'rewrite' => ['slug' => 'spektakli', 'with_front' => false],
            'menu_icon' => 'dashicons-tickets-alt',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields'],
        ]);
        register_post_type('show', [
            'label' => 'Показы', 'labels' => ['singular_name' => 'Показ'],
            'public' => false, 'show_ui' => true, 'show_in_rest' => true,
            'rest_base' => 'shows', 'publicly_queryable' => false,
            'exclude_from_search' => true, 'has_archive' => false, 'rewrite' => false,
            'show_in_nav_menus' => false, 'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
        ]);
        register_taxonomy('venue', ['show'], [
            'label' => 'Площадки', 'hierarchical' => false, 'public' => false,
            'publicly_queryable' => false, 'show_ui' => true, 'show_admin_column' => true,
            'show_in_rest' => true, 'rest_base' => 'venues', 'rewrite' => false,
            'show_in_nav_menus' => false,
        ]);
        foreach (['age_mark','interval_info','cast_text','seo_title_override','seo_description_override'] as $key) {
            register_post_meta('performance', $key, self::meta_args('string', '', [__CLASS__, 'text_meta']));
        }
        register_post_meta('performance', 'duration_minutes', self::meta_args('integer', 0, 'absint'));
        foreach (['performance_id'] as $key) register_post_meta('show', $key, self::meta_args('integer', 0, 'absint'));
        foreach (['event_state','sale_state','show_start_utc','show_end_utc','show_timezone','show_note','change_notice'] as $key) {
            $default = $key === 'event_state' ? 'scheduled' : ($key === 'sale_state' ? 'unavailable' : '');
            register_post_meta('show', $key, self::meta_args('string', $default, [__CLASS__, 'text_meta']));
        }
        register_post_meta('show', 'ticket_links', [
            'type' => 'array', 'single' => true, 'default' => [],
            'show_in_rest' => ['schema' => [
                'type' => 'array', 'items' => ['type' => 'object',
                    'properties' => [
                        'label' => ['type'=>'string'], 'url'=>['type'=>'string'],
                        'primary'=>['type'=>'boolean'],
                    ],
                    'additionalProperties' => false
                ],
            ]],
            'auth_callback' => [__CLASS__, 'meta_allowed'],
            'sanitize_callback' => [__CLASS__, 'clean_links'],
        ]);
        foreach (['venue_address', 'venue_city', 'venue_timezone'] as $key) {
            register_term_meta('venue', $key, [
                'type'=>'string','single'=>true,'default'=>'',
                'show_in_rest'=>true, 'sanitize_callback'=>'sanitize_text_field',
                'auth_callback'=>[__CLASS__,'term_meta_allowed'],
            ]);
        }
    }
    static function meta_args(string $type, $default, $sanitize): array {
        return ['type'=>$type,'single'=>true,'default'=>$default, 'show_in_rest'=>true,
            'sanitize_callback'=>$sanitize,'auth_callback'=>[__CLASS__,'meta_allowed']];
    }
    static function text_meta($value): string { return sanitize_text_field((string)$value); }
    static function meta_allowed($allowed, $key, $post_id): bool {
        return $post_id > 0 && current_user_can('edit_post', (int)$post_id);
    }
    static function term_meta_allowed($allowed, $key, $term_id): bool {
        return current_user_can('manage_categories');
    }
    static function clean_links($value): array {
        if (!is_array($value)) return [];
        $links=[];
        foreach (array_slice($value, 0, 5) as $raw) {
            if (!is_array($raw)) continue;
            $links[]=[
                'label'=>sanitize_text_field((string)($raw['label'] ?? '')),
                'url'=>esc_url_raw((string)($raw['url'] ?? ''), ['https']),
                'primary'=>!empty($raw['primary']),
            ];
        }
        return $links;
    }
    static function public_ticket_url($url): bool {
        if (!is_string($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return false;
        $p=wp_parse_url($url);
        if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return false;
        if (isset($p['port']) && (int)$p['port'] !== 443) return false;
        $host=strtolower(rtrim($p['host'], '.'));
        if (filter_var($host, FILTER_VALIDATE_IP)) return false; // HTTPS DNS hostnames only; no literal IP.
        if (strlen($host)>253 || !preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/D', $host)) return false;
        if (preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid|example|lan|home|onion)\z/i', $host)) return false;
        return (bool)wp_http_validate_url($url) && esc_url_raw($url, ['https']) !== '';
    }
    static function timezone_ok($name): bool {
        return is_string($name) && in_array($name, DateTimeZone::listIdentifiers(), true);
    }
    static function utc_ok($value): bool {
        if (!is_string($value) || !preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $value)) return false;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d H:i:s') === $value;
    }
    /** Strict local IANA conversion; detects DST gaps AND repeated hours. */
    static function resolve_local(string $local, string $tz) {
        if (!self::timezone_ok($tz) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d$/D', $local)) return new WP_Error('invalid_local_datetime','Specify a valid IANA timezone and YYYY-MM-DDTHH:MM.');
        $wall=str_replace('T',' ',$local).':00';
        $wall_dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$wall,new DateTimeZone('UTC'));
        if (!$wall_dt || $wall_dt->format('Y-m-d H:i:s')!==$wall) return new WP_Error('invalid_local_datetime','Invalid local calendar date.');
        $zone=new DateTimeZone($tz);
        $wall_ts=$wall_dt->getTimestamp();
        $trans=$zone->getTransitions($wall_ts-172800,$wall_ts+172800);
        $offsets=[];
        if (is_array($trans)) foreach ($trans as $t) $offsets[]=(int)$t['offset'];
        $offsets[]=$zone->getOffset($wall_dt);
        $times=[];
        foreach (array_unique($offsets) as $offset) {
            $candidate=$wall_ts-$offset;
            if ((new DateTimeImmutable('@'.$candidate))->setTimezone($zone)->format('Y-m-d H:i:s')===$wall) $times[]=$candidate;
        }
        $times=array_unique($times);
        if (count($times)!==1) return new WP_Error('dst_ambiguous','Local time is missing or ambiguous due to DST. Select an unambiguous time.');
        return gmdate('Y-m-d H:i:s',reset($times));
    }
    static function rest_routes(): void {
        register_rest_route('absurd/v1','/resolve-time',[
            'methods'=>'POST','permission_callback'=>static fn()=>current_user_can('edit_posts'),
            'callback'=>static function(WP_REST_Request $r){
                $v=self::resolve_local((string)$r->get_param('local'),(string)$r->get_param('timezone'));
                if (is_wp_error($v)) return new WP_Error($v->get_error_code(),$v->get_error_message(),['status'=>400]);
                return rest_ensure_response(['utc'=>$v]);
            },
            'args'=>['local'=>['required'=>true,'type'=>'string'],'timezone'=>['required'=>true,'type'=>'string']]
        ]);
    }
    static function private_rest_guard($result, $server, $request) {
        $route=$request->get_route();
        if (!current_user_can('edit_posts') && preg_match('#^/wp/v2/(?:shows|venues)(?:/|$)#', $route)) {
            return new WP_Error('rest_show_private','Shows and venues are private editorial content.', ['status'=>401]);
        }
        return $result;
    }
    static function validate_publish(array $meta, array $venue_ids): array {
        $errors=[];
        $id=absint($meta['performance_id'] ?? 0);
        $perf=$id ? get_post($id) : null;
        if (!$perf || $perf->post_type!=='performance' || $perf->post_status!=='publish') $errors[]='Select an existing published performance.';
        $event=(string)($meta['event_state'] ?? 'scheduled');
        $sale=(string)($meta['sale_state'] ?? 'unavailable');
        $tz=(string)($meta['show_timezone'] ?? '');
        $utc=(string)($meta['show_start_utc'] ?? '');
        if (!in_array($event,self::STATES,true)) $errors[]='Invalid event_state.';
        if (!in_array($sale,self::SALES,true)) $errors[]='Invalid sale_state.';
        if (!self::timezone_ok($tz)) $errors[]='Choose an IANA show timezone.';
        if ($utc !== '' && !self::utc_ok($utc)) $errors[]='Invalid UTC datetime.';
        if ($event==='scheduled' && !self::utc_ok($utc)) $errors[]='Scheduled shows require start UTC.';
        $end=(string)($meta['show_end_utc'] ?? '');
        if ($end!=='' && (!self::utc_ok($end) || !self::utc_ok($utc) || strcmp($end,$utc)<=0)) $errors[]='End UTC must follow start UTC.';
        $venue_ids=array_values(array_unique(array_filter(array_map('absint',$venue_ids))));
        if ($event==='scheduled' && count($venue_ids)!==1) $errors[]='Scheduled shows require exactly one venue.';
        if ($venue_ids) {
            if (count($venue_ids)!==1) $errors[]='Choose at most one venue.';
            else {
                $term=get_term($venue_ids[0],'venue');
                if (!$term || is_wp_error($term)) $errors[]='Venue term does not exist.';
                else {
                    $address=(string)get_term_meta($term->term_id,'venue_address',true);
                    $city=(string)get_term_meta($term->term_id,'venue_city',true);
                    $vtz=(string)get_term_meta($term->term_id,'venue_timezone',true);
                    if ($event==='scheduled' && (mb_strlen($address)<5 || mb_strlen($address)>250 || $city==='' || !self::timezone_ok($vtz))) $errors[]='Venue must have verified address, city and timezone.';
                    if (self::timezone_ok($vtz) && $tz!==$vtz) $errors[]='Show timezone must match selected venue timezone.';
                }
            }
        }
        $links=$meta['ticket_links'] ?? [];
        if (!is_array($links) || count($links)>5) $errors[]='Maximum five ticket links.';
        else {
            $primary=0; $seen=[];
            foreach ($links as $link) {
                if (!is_array($link) || mb_strlen((string)($link['label'] ?? ''))<2 || mb_strlen((string)($link['label'] ?? ''))>80 || !self::public_ticket_url((string)($link['url'] ?? ''))) {
                    $errors[]='Ticket link needs a 2–80 character label and safe public HTTPS URL.'; continue;
                }
                $url=(string)$link['url'];
                if (isset($seen[$url])) $errors[]='Duplicate ticket link.';
                $seen[$url]=true;
                if (!empty($link['primary'])) $primary++;
            }
            if ($primary>1 || (count($links)>1 && $primary!==1)) $errors[]='Select exactly one primary ticket link when multiple links exist.';
            if ($sale==='on_sale' && !$links) $errors[]='on_sale requires a valid HTTPS ticket link.';
        }
        if (mb_strlen((string)($meta['show_note'] ?? ''))>500) $errors[]='Show note exceeds 500 characters.';
        return array_values(array_unique($errors));
    }
    static function meta_for($post_id, array $incoming): array {
        $keys=['performance_id','event_state','sale_state','show_start_utc','show_end_utc','show_timezone','ticket_links','show_note','change_notice'];
        $m=[];
        foreach ($keys as $key) $m[$key]=metadata_exists('post',$post_id,$key) ? get_post_meta($post_id,$key,true) : ($key==='ticket_links'?[]:($key==='event_state'?'scheduled':($key==='sale_state'?'unavailable':'')));
        return array_merge($m,array_intersect_key($incoming,array_flip($keys)));
    }
    static function venue_for($post_id, $incoming=null): array {
        if ($incoming!==null) return is_array($incoming) ? $incoming : [];
        return $post_id ? wp_get_object_terms($post_id,'venue',['fields'=>'ids']) : [];
    }
    static function rest_guard($prepared, WP_REST_Request $request) {
        $post_id=(int)$request->get_param('id');
        $existing=$post_id ? get_post($post_id) : null;
        $status=(string)($request->get_param('status') ?? ($existing ? $existing->post_status : 'draft'));
        if ($status!=='publish') return $prepared; // incomplete drafts permitted.
        if (!$post_id && !current_user_can('publish_posts')) return new WP_Error('no_publish','Publication forbidden.',['status'=>403]);
        $incoming=$request->get_param('meta') ?: [];
        if (!is_array($incoming)) $incoming=[];
        $m=self::meta_for($post_id,$incoming);
        $venue=self::venue_for($post_id,$request->has_param('venues') ? $request->get_param('venues') : ($request->has_param('venue')?$request->get_param('venue'):null));
        $err=self::validate_publish($m,$venue);
        if ($err) return new WP_Error('absurd_invalid_show',implode(' ', $err),['status'=>400,'details'=>$err]);
        return $prepared;
    }
    /** Classic non-REST publish request: never let invalid records become public. */
    static function classic_guard(array $data, array $postarr): array {
        if (($data['post_type'] ?? '')!=='show' || ($data['post_status'] ?? '')!=='publish' || defined('REST_REQUEST') && REST_REQUEST) return $data;
        $pid=(int)($postarr['ID'] ?? 0);
        $incoming=[];
        if (isset($_POST['absurd_meta']) && is_array($_POST['absurd_meta'])) {
            foreach (wp_unslash($_POST['absurd_meta']) as $k=>$v) if (is_string($k)) $incoming[$k]=$v;
        }
        $valid_nonce=isset($_POST['absurd_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['absurd_nonce'])),'absurd_save_show');
        if (!$valid_nonce || !($pid ? current_user_can('edit_post',$pid) : current_user_can('publish_posts'))) {
            $data['post_status']='draft';
            set_transient('absurd_error_'.get_current_user_id(),'Publish blocked: editor nonce or permission missing.',60);
            return $data;
        }
        if (!empty($incoming['show_start_local'])) {
            $utc=self::resolve_local((string)$incoming['show_start_local'],(string)($incoming['show_timezone']??''));
            $incoming['show_start_utc']=is_wp_error($utc)?'INVALID':$utc;
        }
        $venue=isset($_POST['absurd_venue']) ? [absint($_POST['absurd_venue'])] : self::venue_for($pid);
        $err=self::validate_publish(self::meta_for($pid,$incoming),$venue);
        if ($err) {
            $data['post_status']='draft';
            set_transient('absurd_error_'.get_current_user_id(),implode(' ', $err),60);
        }
        return $data;
    }
    static function save_classic($post_id,$post): void {
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!isset($_POST['absurd_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['absurd_nonce'])),'absurd_save_show')) return;
        if (!current_user_can('edit_post',$post_id) || empty($_POST['absurd_meta']) || !is_array($_POST['absurd_meta'])) return;
        $incoming=wp_unslash($_POST['absurd_meta']);
        if (!empty($incoming['show_start_local'])) {
            $utc=self::resolve_local((string)$incoming['show_start_local'],(string)($incoming['show_timezone']??''));
            if (!is_wp_error($utc)) $incoming['show_start_utc']=$utc;
        }
        foreach (['performance_id','event_state','sale_state','show_start_utc','show_timezone','show_end_utc','show_note','change_notice'] as $k) {
            if (!isset($incoming[$k])) continue;
            $v=in_array($k,['performance_id'],true)?absint($incoming[$k]):sanitize_text_field((string)$incoming[$k]);
            update_post_meta($post_id,$k,$v);
        }
        if (isset($_POST['absurd_venue'])) {
            wp_set_object_terms($post_id,absint($_POST['absurd_venue'])?[absint($_POST['absurd_venue'])]:[],'venue');
        }
    }
    static function admin_error(): void {
        $key='absurd_error_'.get_current_user_id();
        if ($s=get_transient($key)) {
            delete_transient($key);
            echo '<div class="notice notice-error"><p>'.esc_html($s).'</p></div>';
        }
    }
    static function classic_box(): void {
        add_meta_box('absurd-show-meta','Show fields (classic editor fallback)',[__CLASS__,'render_classic'],'show','normal','high',['__back_compat_meta_box'=>true]);
    }
    static function render_classic($post): void {
        wp_nonce_field('absurd_save_show','absurd_nonce');
        echo '<p>Gutenberg: edit these in the ABSURD Show Details sidebar. Classic fallback fields:</p>';
        foreach (['performance_id','event_state','sale_state','show_timezone','show_start_utc','show_start_local','show_note'] as $k) {
            $v=get_post_meta($post->ID,$k,true);
            echo '<p><label>'.esc_html($k).' <input style="width:65%" name="absurd_meta['.esc_attr($k).']" value="'.esc_attr((string)$v).'"></label></p>';
        }
        $terms=get_terms(['taxonomy'=>'venue','hide_empty'=>false]);
        echo '<p><label>Venue <select name="absurd_venue"><option value="">— Choose —</option>';
        $current=wp_get_object_terms($post->ID,'venue',['fields'=>'ids']);
        if (!is_wp_error($terms)) foreach ($terms as $term) echo '<option value="'.esc_attr($term->term_id).'" '.selected(in_array($term->term_id,$current),true,false).'>'.esc_html($term->name).'</option>';
        echo '</select></label></p>';
    }
    static function editor_assets(): void {
        $screen=get_current_screen();
        if (!$screen) return;         if ($screen->post_type==='performance') {             wp_enqueue_script('absurd-performance-editor',plugin_dir_url(__FILE__).'performance-editor.js',['wp-plugins','wp-edit-post','wp-element','wp-components','wp-data'],self::VERSION,true);             return;         }         if ($screen->post_type!=='show') return;
        wp_enqueue_script('absurd-show-editor',plugin_dir_url(__FILE__).'editor.js',['wp-plugins','wp-edit-post','wp-element','wp-components','wp-data','wp-api-fetch'],self::VERSION,true);
        $perf=get_posts(['post_type'=>'performance','post_status'=>['publish','draft','pending'],'posts_per_page'=>100,'orderby'=>'title','order'=>'ASC']);
        $venues=get_terms(['taxonomy'=>'venue','hide_empty'=>false]);
        $venue_items=[];
        if (!is_wp_error($venues)) foreach ($venues as $t) $venue_items[]=['id'=>$t->term_id,'name'=>$t->name,'timezone'=>(string)get_term_meta($t->term_id,'venue_timezone',true)];
        wp_add_inline_script('absurd-show-editor','window.absurdCore='.wp_json_encode([
            'performances'=>array_map(static fn($p)=>['id'=>$p->ID,'name'=>$p->post_title,'status'=>$p->post_status],$perf),
            'venues'=>$venue_items, 'timezones'=>DateTimeZone::listIdentifiers(),
        ]).';','before');
    }
    static function venue_add_fields(): void {
        wp_nonce_field('absurd_venue_fields','absurd_venue_nonce');
        foreach (['venue_address'=>'Verified address','venue_city'=>'City','venue_timezone'=>'IANA timezone'] as $k=>$label)
            echo '<div class="form-field"><label for="'.esc_attr($k).'">'.esc_html($label).'</label><input name="'.esc_attr($k).'" id="'.esc_attr($k).'" value=""></div>';
    }
    static function venue_edit_fields($term): void {
        wp_nonce_field('absurd_venue_fields','absurd_venue_nonce');
        foreach (['venue_address'=>'Verified address','venue_city'=>'City','venue_timezone'=>'IANA timezone'] as $k=>$label)
            echo '<tr class="form-field"><th><label for="'.esc_attr($k).'">'.esc_html($label).'</label></th><td><input name="'.esc_attr($k).'" id="'.esc_attr($k).'" value="'.esc_attr((string)get_term_meta($term->term_id,$k,true)).'"></td></tr>';
    }
    static function save_venue_fields($term_id): void {
        if (!isset($_POST['absurd_venue_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['absurd_venue_nonce'])),'absurd_venue_fields') || !current_user_can('manage_categories')) return;
        foreach (['venue_address','venue_city','venue_timezone'] as $key) {
            if (!isset($_POST[$key])) continue;
            $v=sanitize_text_field(wp_unslash($_POST[$key]));
            if ($key==='venue_timezone' && $v!=='' && !self::timezone_ok($v)) continue;
            update_term_meta($term_id,$key,$v);
        }
    }
    /** Bounded UTC-sorted query; avoid PHP-side global sorting and N+1 lookups. */
    static function upcoming(int $limit=20): array {
        $q=new WP_Query([
            'post_type'=>'show','post_status'=>'publish','posts_per_page'=>max(1,min(100,$limit)),
            'no_found_rows'=>true,'update_post_meta_cache'=>true,'update_post_term_cache'=>true,
            'meta_query'=>[
                'start_utc'=>['key'=>'show_start_utc','value'=>gmdate('Y-m-d H:i:s'),'compare'=>'>=','type'=>'DATETIME'],
                'event_state'=>['key'=>'event_state','value'=>'scheduled','compare'=>'='],
            ],
            'orderby'=>['start_utc'=>'ASC','ID'=>'ASC'],
        ]);
        $ids=array_unique(array_filter(array_map(static fn($p)=>(int)get_post_meta($p->ID,'performance_id',true),$q->posts)));
        if (!$ids) return [];
        $performances=get_posts(['post_type'=>'performance','post_status'=>'publish','post__in'=>$ids,'posts_per_page'=>count($ids),'fields'=>'ids']);
        $valid=array_fill_keys(array_map('intval',$performances),true);
        return array_values(array_filter($q->posts,static fn($show)=>isset($valid[(int)get_post_meta($show->ID,'performance_id',true)])));
    }
}
Absurdtheater_Core::boot();
register_activation_hook(__FILE__,static function(){ Absurdtheater_Core::register(); flush_rewrite_rules(); });
register_deactivation_hook(__FILE__,'flush_rewrite_rules');
