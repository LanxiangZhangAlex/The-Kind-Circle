<?php
/** Dynamic event archive and one-time migration of the original recap cards. */
defined('ABSPATH') || exit;

function tkcs_event_has_ended($id){
    $end=get_post_meta($id,'_event_end',true);
    return $end && $end<=gmdate('Y-m-d H:i:s');
}
function tkcs_past_query_args($limit=6,$page=1){
    return ['post_type'=>'event','post_status'=>'publish','posts_per_page'=>$limit,'paged'=>$page,
        'lang'=>'','suppress_filters'=>true,'ignore_sticky_posts'=>true,
        'meta_query'=>['relation'=>'AND',
            'end_time'=>['key'=>'_event_end','value'=>gmdate('Y-m-d H:i:s'),'compare'=>'<=','type'=>'DATETIME'],
            ['relation'=>'OR',['key'=>'_event_active_status','compare'=>'NOT EXISTS'],['key'=>'_event_active_status','value'=>'0','compare'=>'!=']]],
        'orderby'=>['end_time'=>'DESC','ID'=>'DESC']];
}
function tkcs_event_date_label($id){
    $start=get_post_meta($id,'_event_start_date',true);$end=get_post_meta($id,'_event_end_date',true);
    if(get_post_meta($id,'tkc_date_precision',true)==='month')return substr($start,0,7);
    $label=$start.($end&&$end!==$start?' – '.$end:'');
    if(get_post_meta($id,'tkc_time_precision',true)==='start')$label.=' · '.substr(get_post_meta($id,'_event_start_time',true),0,5);
    return $label;
}
function tkcs_past_card($post){
    $id=$post->ID;$et=function_exists('tkc_lang')&&tkc_lang()==='et';$lang=$et?'et':'en';
    $title=$et?(get_post_meta($id,'tkc_title_et',true)?:$post->post_title):$post->post_title;
    $pages=get_post_meta($id,'tkc_pages',true);$url=!empty($pages[$lang])&&get_post_status($pages[$lang])==='publish'?get_permalink($pages[$lang]):get_permalink($id);
    $image=get_the_post_thumbnail_url($id,'large')?:get_post_meta($id,'tkc_image',true);
    $body=$et?(get_post_meta($id,'tkc_content_et',true)?:$post->post_content):($post->post_excerpt?:$post->post_content);
    $excerpt=wp_trim_words(wp_strip_all_tags(strip_shortcodes($body)),26,'…');
    $venue=get_post_meta($id,'tkc_venue',true);
    $out='<article class="tkc-card tkc-past-card"><a href="'.esc_url($url).'" style="display:block;color:inherit;text-decoration:none;height:100%">';
    if($image)$out.='<img src="'.esc_url($image).'" alt="" loading="lazy" style="display:block;width:100%;height:220px;object-fit:cover">';
    $out.='<div class="tkc-card-body"><span class="tkc-event-status">'.esc_html($et?'Varasem tegevus':'Past activity').'</span><h3>'.esc_html($title).'</h3><p class="tkc-event-meta">'.esc_html(tkcs_event_date_label($id)).($venue?'<br>'.esc_html($venue):'').'</p><p>'.esc_html($excerpt).'</p><span>'.esc_html($et?'Vaata tagasivaadet':'Read recap').'</span></div></a></article>';
    return $out;
}
function tkcs_past_activities($attrs=[]){
    $et=function_exists('tkc_lang')&&tkc_lang()==='et';$a=shortcode_atts(['limit'=>function_exists('tkcs_setting')?tkcs_setting('past_limit'):6],is_array($attrs)?$attrs:[]);
    $limit=min(24,max(1,absint($a['limit'])));$page=isset($_GET['tkc_past_page'])?min(10000,max(1,absint($_GET['tkc_past_page']))):1;
    $args=tkcs_past_query_args($limit,$page);$query=new WP_Query($args);
    if($query->max_num_pages&&$page>$query->max_num_pages){$page=(int)$query->max_num_pages;$args['paged']=$page;$query=new WP_Query($args);}
    $out='<div id="tkc-past-activities" style="scroll-margin-top:32px">';
    if(!$query->posts)return $out.'<p>'.esc_html(tkcs_local_setting('past_empty')).'</p></div>';
    $out.='<div class="tkc-events-list">';foreach($query->posts as$post)$out.=tkcs_past_card($post);$out.='</div>';
    $first=($page-1)*$limit+1;$last=min($page*$limit,(int)$query->found_posts);
    $out.='<p class="tkc-event-meta" style="margin-top:24px">'.esc_html(sprintf($et?'Kuvatud %1$d–%2$d / %3$d tegevust':'Showing %1$d–%2$d of %3$d activities',$first,$last,$query->found_posts)).'</p>';
    if($query->max_num_pages>1){
        $base=str_replace('999999999','%#%',esc_url(add_query_arg('tkc_past_page',999999999,get_permalink())));
        $links=paginate_links(['base'=>$base,'format'=>'','current'=>$page,'total'=>$query->max_num_pages,'mid_size'=>1,'end_size'=>1,'type'=>'array','prev_text'=>$et?'← Eelmised':'← Previous','next_text'=>$et?'Järgmised →':'Next →','add_fragment'=>'#tkc-past-activities']);
        $out.='<nav class="tkc-past-pagination" aria-label="'.esc_attr($et?'Varasemate tegevuste leheküljed':'Past activities pages').'" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">';
        foreach($links?:[]as$link)$out.='<span style="display:inline-block;padding:8px 12px;border:1px solid #c8d2cb;border-radius:5px">'.$link.'</span>';
        $out.='</nav>';
    }
    return $out.'</div>';
}

// Seed data is used only for the one-time import, never as a frontend data source.
function tkcs_history_seed_data(){return [
    'coffee'=>['Coffee chats and shared ideas','Kohvihommikud ja ühised ideed','Informal conversations, practical knowledge and making connections.','Vabad vestlused, praktilised teadmised ja uued sidemed.','coffee-group','2026-10-17','14:00'],
    'dumplings'=>['Dumplings Without Borders','Piirideta pelmeenid','Cooking and cultural exchange through a shared table.','Kokkamine ja kultuurivahetus ühise laua ümber.','dumplings','2023-09-17',''],
    'outdoors'=>['Exploring outdoors together','Koos loodust avastamas','We have organised several outdoor activities, bringing people together for walks, local discovery and shared experiences in nature.','Oleme korraldanud mitmeid ühiseid õuetegevusi, tuues inimesed kokku jalutuskäikudeks, ümbruskonna avastamiseks ja ühisteks looduselamusteks.','hiking','2026-10-03','']
];}
function tkcs_history_import_form(){
    $done=get_option('tkc_history_event_map',[]);
    echo '<h2>Historical activities</h2><p>Past activities are read from published Events after their end time, six per page, newest first. Draft, trashed and cancelled events are excluded. Edit the activity in Events to maintain its recap.</p>';
    if(count($done)<3){
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcs_history_import');echo '<input type="hidden" name="action" value="tkcs_history_import"><p>Import the original recap cards once. Enter a real date (YYYY-MM-DD) or known month (YYYY-MM). Month-only dates are explicitly labelled as month precision. No exact dates or times are invented.</p>';
        foreach(tkcs_history_seed_data()as$key=>$item){if(!empty($done[$key]))continue;echo '<p><label>'.esc_html($item[0]).' <input name="history_date['.esc_attr($key).']" value="'.esc_attr($item[5]).'" placeholder="YYYY-MM-DD or YYYY-MM" required pattern="[0-9]{4}-[0-9]{2}(-[0-9]{2})?"></label></p>';}
        submit_button('Import historical activities into Events');echo '</form>';
    }else{echo '<ul>';foreach($done as$id)echo '<li><a href="'.esc_url(get_edit_post_link($id)).'">'.esc_html(get_the_title($id)).'</a></li>';echo '</ul>';}
}
function tkcs_history_dates($raw){
    if(!preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/',$raw,$m))return false;
    $year=(int)$m[1];$month=(int)$m[2];$day=isset($m[3])?(int)$m[3]:1;
    if($year<1900||!checkdate($month,$day,$year))return false;
    $start=sprintf('%04d-%02d-%02d',$year,$month,$day);$precision=isset($m[3])?'day':'month';
    $end=$precision==='month'?(new DateTimeImmutable($start,wp_timezone()))->format('Y-m-t'):$start;
    if($end>=wp_date('Y-m-d'))return false;
    return ['start'=>$start,'end'=>$end,'precision'=>$precision];
}
add_action('admin_post_tkcs_history_import',function(){
    if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcs_history_import');
    if(!class_exists('EM_Event'))wp_die('Activate Events Manager first.');
    $map=get_option('tkc_history_event_map',[]);$dates=[];
    foreach(tkcs_history_seed_data()as$key=>$item){if(!empty($map[$key]))continue;$raw=sanitize_text_field(wp_unslash($_POST['history_date'][$key]??''));$dates[$key]=tkcs_history_dates($raw);if(!$dates[$key])wp_die('Enter a valid past date or month for '.esc_html($item[0]).'. No changes were made.');}
    foreach(tkcs_history_seed_data()as$key=>$item){
        if(!empty($map[$key]))continue;$date=$dates[$key];$event=new EM_Event();
        $event->event_name=$item[0];$event->post_content='<p>'.esc_html($item[2]).'</p>';
        $event->event_start_date=$date['start'];$event->event_end_date=$date['end'];$event->event_start_time='00:00:00';$event->event_end_time='23:59:59';$event->event_all_day=empty($item[6])?1:0;if(!empty($item[6])){$event->event_start_time=$item[6].':00';$event->event_end_time=$item[6].':00';}$event->event_timezone=wp_timezone_string();$event->event_rsvp=0;$event->force_status='publish';
        if(!$event->save())wp_die('Import could not be completed. Already imported records were preserved. '.esc_html(implode(' ',$event->get_errors())));
        $id=(int)$event->post_id;$map[$key]=$id;update_option('tkc_history_event_map',$map,false);
        update_post_meta($id,'tkc_title_et',$item[1]);update_post_meta($id,'tkc_content_et','<p>'.esc_html($item[3]).'</p>');update_post_meta($id,'tkc_date_precision',$date['precision']);
        update_post_meta($id,'tkc_time_precision',empty($item[6])?'date':'start');
        update_post_meta($id,'tkc_image',tkcs_asset($item[4]));$assets=get_option('tkc_asset_map',[]);if(!empty($assets[$item[4]]))set_post_thumbnail($id,$assets[$item[4]]);
        wp_update_post(['ID'=>$id,'comment_status'=>'closed']);tkcs_sync_event(new EM_Event($id,'post_id'));
    }
    wp_safe_redirect(admin_url('tools.php?page=tkc-website&history_imported=1'));exit;
});
add_action('add_meta_boxes_event',function($post){
    if(get_post_meta($post->ID,'tkc_date_precision',true)==='month')add_meta_box('tkc-history-precision','Historical date precision',function(){echo '<p>Only the month is documented for this historical activity. The calendar range covers that month, and the public recap displays YYYY-MM without claiming an exact date or time.</p>';},'event','side','high');
});

// Let organisers change how historical dates are displayed from the Event editor.
add_action('add_meta_boxes_event',function(){add_meta_box('tkc-recap-display','TKC recap date display',function($post){
    wp_nonce_field('tkcs_recap_display','tkcs_recap_display_nonce');
    echo '<p><label for="tkc-time-precision">Time display</label><br><select id="tkc-time-precision" name="tkc_time_precision">';
    foreach(['full'=>'Start and end time','start'=>'Known start time only','date'=>'Date only (time not recorded)']as$key=>$label)echo '<option value="'.esc_attr($key).'" '.selected(get_post_meta($post->ID,'tkc_time_precision',true)?:'full',$key,false).'>'.esc_html($label).'</option>';
    echo '</select></p><p>For imported recaps without a documented end time, only the known date or start time is shown. Maintain all recap text and photos in this Event.</p>';
},'event','side','default');});
add_action('save_post_event',function($id){
    if(wp_is_post_revision($id)||!current_user_can('edit_post',$id)||empty($_POST['tkcs_recap_display_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tkcs_recap_display_nonce'])),'tkcs_recap_display'))return;
    $mode=sanitize_key(wp_unslash($_POST['tkc_time_precision']??'full'));
    if(in_array($mode,['full','start','date'],true))update_post_meta($id,'tkc_time_precision',$mode);
},21);

// Private preview exercises the same renderer with two real recaps per page.
function tkcs_history_preview_form(){
    $pages=get_option('tkc_history_preview_pages',[]);
    if($pages){echo '<p>Private pagination previews (not visible to visitors): ';foreach($pages as$lang=>$id)if(get_post_status($id)==='private')echo '<a href="'.esc_url(get_permalink($id)).'">'.esc_html(strtoupper($lang)).'</a> ';echo '</p>';}
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcs_history_preview');echo '<input type="hidden" name="action" value="tkcs_history_preview"><input type="hidden" name="close" value="'.($pages?'1':'0').'">';submit_button($pages?'Remove private pagination previews':'Create private pagination previews','secondary');echo '</form>';
}
add_action('admin_post_tkcs_history_preview',function(){
    if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcs_history_preview');$pages=get_option('tkc_history_preview_pages',[]);
    if(!empty($_POST['close'])){foreach($pages as$id)if(get_post_type($id)==='page'&&get_post_meta($id,'_tkc_archive_preview',true))wp_trash_post($id);delete_option('tkc_history_preview_pages');}
    else{
        foreach(['en','et']as$lang){if(!empty($pages[$lang])&&get_post_status($pages[$lang])==='private')continue;
            $id=wp_insert_post(['post_type'=>'page','post_status'=>'private','post_title'=>'TKC archive pagination preview '.strtoupper($lang),'post_content'=>'<!-- wp:shortcode -->[tkc_past_activities limit="2"]<!-- /wp:shortcode -->','comment_status'=>'closed','meta_input'=>['_tkc_archive_preview'=>1]],true);
            if(is_wp_error($id))wp_die(esc_html($id->get_error_message()));$pages[$lang]=$id;if(function_exists('pll_set_post_language'))pll_set_post_language($id,$lang);
        }
        if(function_exists('pll_save_post_translations'))pll_save_post_translations($pages);update_option('tkc_history_preview_pages',$pages,false);
    }
    wp_safe_redirect(admin_url('tools.php?page=tkc-website'));exit;
});
