<?php
/** City Surprise: native WordPress tasks, shared bilingual submissions, private photos. */
defined('ABSPATH') || exit;
require_once __DIR__.'/city-eggs-admin.php';
require_once __DIR__.'/city-eggs-upload.php';
require_once __DIR__.'/city-eggs-setup.php';
require_once __DIR__.'/activities-layout.php';
require_once __DIR__.'/city-surprise-comments.php';
require_once __DIR__.'/city-surprise-cover.php';
require_once __DIR__.'/city-surprise-content.php';
function tkcq_et(){return function_exists('tkc_lang')&&tkc_lang()==='et';}
function tkcq_text($en,$et){return tkcq_et()?$et:$en;}
function tkcq_deadline_parse($input){
    $zone=new DateTimeZone('Europe/Tallinn');$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)$input,$zone);
    if(!$date||$date->format('Y-m-d\TH:i')!==$input)return 0;
    return $date->getTimestamp();
}
function tkcq_deadline_label($id){$ts=(int)get_post_meta($id,'_tkcq_deadline',true);return $ts?wp_date('d.m.Y H:i T',$ts,new DateTimeZone('Europe/Tallinn')):'';}
function tkcq_accessible($id){$p=get_post($id);return $p&&$p->post_type==='tkc_quest'&&($p->post_status==='publish'||($p->post_status==='private'&&current_user_can('edit_post',$id))||(in_array($p->post_status,['draft','pending','future'],true)&&is_preview()&&current_user_can('edit_post',$id)));}
function tkcq_open($id,$now=null){return tkcq_accessible($id)&&in_array(get_post_status($id),['publish','private'],true)&&(int)get_post_meta($id,'_tkcq_deadline',true)>($now===null?time():(int)$now);}
function tkcq_field($id,$key,$lang=null){$lang=$lang?: (tkcq_et()?'et':'en');$en=get_post_meta($id,'_tkcq_'.$key,true);return $lang==='et'?(get_post_meta($id,'_tkcq_'.$key.'_et',true)?:$en):$en;}
function tkcq_title($id,$lang=null){$lang=$lang?:(tkcq_et()?'et':'en');return $lang==='et'?(get_post_meta($id,'_tkcq_title_et',true)?:get_the_title($id)):get_the_title($id);}
function tkcq_url($id,$lang=null){$pages=get_post_meta($id,'_tkcq_pages',true);$lang=$lang?:(tkcq_et()?'et':'en');return !empty($pages[$lang])?get_permalink($pages[$lang]):get_permalink($id);}
function tkcq_winner($id){$entry=(int)get_post_meta($id,'_tkcq_winner',true);if(!$entry||get_post_status($entry)!=='publish'||get_post_type($entry)!=='flamingo_inbound'||(int)get_post_meta($entry,'_tkcq_quest_id',true)!==$id)return 0;return $entry;}
add_action('init',function(){
 register_post_type('tkc_quest',['labels'=>['name'=>'City Surprise / 城市彩蛋','singular_name'=>'City Surprise','add_new_item'=>'Add City Surprise / 新建一期','edit_item'=>'Edit City Surprise / 编辑任务','all_items'=>'All rounds / 全部期次','featured_image'=>'Clue image / 线索图片','set_featured_image'=>'Choose clue image / 选择线索图片','remove_featured_image'=>'Remove clue image'],'public'=>true,'publicly_queryable'=>true,'exclude_from_search'=>true,'has_archive'=>false,'rewrite'=>false,'show_in_rest'=>false,'menu_icon'=>'dashicons-location-alt','menu_position'=>22,'supports'=>['title','editor','thumbnail','revisions'],'capability_type'=>'page','map_meta_cap'=>true]);
});
add_filter('use_block_editor_for_post_type',function($use,$type){return $type==='tkc_quest'?false:$use;},10,2);
add_action('wp_enqueue_scripts',function(){wp_enqueue_style('tkc-city-eggs',plugins_url('city-eggs.css',__FILE__),[],'1.5.3');});
add_filter('the_content',function($content){return !is_admin()&&is_singular('tkc_quest')&&get_the_ID()===get_queried_object_id()?tkcq_detail(['id'=>get_the_ID()]):$content;},20);
add_action('template_redirect',function(){
 if(is_singular('tkc_quest')&&!is_preview()&&get_post_status(get_queried_object_id())==='publish'){$id=get_queried_object_id();$pages=get_post_meta($id,'_tkcq_pages',true);if(!empty($pages['en'])&&get_post_status($pages['en'])==='publish'){wp_safe_redirect(tkcq_url($id),302);exit;}}
 if(is_page()&&(get_post_meta(get_queried_object_id(),'_tkcq_source',true)||has_shortcode(get_post_field('post_content',get_queried_object_id()),'tkc_city_eggs')||has_shortcode(get_post_field('post_content',get_queried_object_id()),'tkc_city_surprise'))){nocache_headers();if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);}
 if(is_page()&&get_post_meta(get_queried_object_id(),'_tkcq_source',true)){
  $id=(int)get_post_meta(get_queried_object_id(),'_tkcq_source',true);
  if(!tkcq_accessible($id)){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();}
  elseif(tkcq_open($id)){nocache_headers();}
 }
});
function tkcq_listing($attrs){
 $a=shortcode_atts(['limit'=>6,'scope'=>'all'],is_array($attrs)?$attrs:[]);$limit=min(24,max(1,absint($a['limit'])));$scope=in_array($a['scope'],['ongoing','past'],true)?$a['scope']:'all';$param=$scope==='all'?'egg_page':'surprise_'.$scope.'_page';$anchor='city-surprise-'.$scope;$page=min(10000,max(1,absint($_GET[$param]??1)));$filter=$scope==='all'?[]:[['key'=>'_tkcq_deadline','value'=>time(),'compare'=>$scope==='ongoing'?'>':'<=','type'=>'NUMERIC']];
 $query=new WP_Query(['post_type'=>'tkc_quest','post_status'=>'publish','posts_per_page'=>$limit,'paged'=>$page,'lang'=>'','suppress_filters'=>true,'meta_key'=>'_tkcq_deadline','meta_query'=>$filter,'orderby'=>'meta_value_num','order'=>$scope==='ongoing'?'ASC':'DESC']);
 $out='<div class="tkcq-list" id="'.esc_attr($anchor).'">';if(!$query->posts)return $out.'<p class="tkc-note">'.esc_html($scope==='past'?tkcq_text('Completed City Surprise activities will appear here.','Lõppenud City Surprise’i tegevused ilmuvad siia.'):tkcq_text('The next City Surprise is coming soon. Check back for a clue and share your discovery.','Järgmine City Surprise on peagi tulekul. Tule tagasi, leia vihje ja jaga oma avastust.')).'</p></div>';
 $out.='<div class="tkcq-grid">';foreach($query->posts as$p){$id=$p->ID;$open=tkcq_open($id);$img=tkcq_card_image($id);$out.='<article class="tkc-card tkcq-card"><a class="tkcq-card-link" href="'.esc_url(tkcq_url($id)).'">'.($img?'<img src="'.esc_url($img).'" alt="'.esc_attr(tkcq_title($id)).'" loading="lazy">':'').'<div class="tkc-card-body"><span class="tkc-event-status">'.esc_html($open?tkcq_text('Open for check-ins','Osalemine avatud'):tkcq_text('Closed','Lõppenud')).'</span><h4>'.esc_html(tkcq_title($id)).'</h4><p>'.esc_html(tkcq_field($id,'hint')).'</p><p>'.esc_html(tkcq_text('Deadline: ','Tähtaeg: ').tkcq_deadline_label($id)).'</p><span>'.esc_html($open?tkcq_text('Explore this round','Vaata selle vooru ülesannet'):tkcq_text('View this round','Vaata seda vooru')).'</span></div></a></article>';}$out.='</div>';
 if($query->max_num_pages>1)$out.='<nav class="tkcq-pagination" aria-label="'.esc_attr(tkcq_text('Challenge pages','Voorude leheküljed')).'">'.paginate_links(['base'=>str_replace('999999999','%#%',esc_url(add_query_arg($param,999999999,get_permalink()))),'current'=>$page,'total'=>$query->max_num_pages,'prev_text'=>tkcq_text('← Previous','← Eelmised'),'next_text'=>tkcq_text('Next →','Järgmised →'),'add_fragment'=>'#'.$anchor]).'</nav>';
 return $out.'</div>';
}
add_shortcode('tkc_city_surprise','tkcq_listing');
add_shortcode('tkc_city_eggs','tkcq_listing');
function tkcq_detail($attrs){
 $id=absint($attrs['id']??0);if(!tkcq_accessible($id))return '';$open=tkcq_open($id);$post=get_post($id);$lang=tkcq_et()?'et':'en';
 $out='<section class="tkc-page-intro tkc-section tkcq-intro"><p class="tkc-label">'.esc_html(tkcq_text('City Surprise','City Surprise')).'</p><h1>'.esc_html(tkcq_title($id)).'</h1><p><span class="tkc-event-status">'.esc_html($open?tkcq_text('Open for check-ins','Osalemine avatud'):tkcq_text('Closed','Lõppenud')).'</span></p><p>'.esc_html(tkcq_text('Deadline (Tallinn time): ','Tähtaeg (Tallinna aeg): ').tkcq_deadline_label($id)).'</p></section><div class="tkc-body tkcq-detail">';
 if($post->post_status==='private')$out.='<p class="tkc-note">Private verification task — only visible to website managers.</p>';
 $image=get_the_post_thumbnail_url($id,'full');if($image)$out.='<figure class="tkcq-clue"><img src="'.esc_url($image).'" alt="'.esc_attr(tkcq_text('Location clue: ','Asukoha vihje: ').tkcq_title($id)).'"><figcaption>'.esc_html(tkcq_text('Find the place shown in this image.','Leia pildil kujutatud koht.')).'</figcaption></figure>';
 $body=$lang==='et'?(get_post_meta($id,'_tkcq_body_et',true)?:$post->post_content):$post->post_content;
 $out.='<div class="tkcq-task">'.tkcq_task_markup($body,tkcq_field($id,'hint'),tkcq_field($id,'gesture'),$lang).'</div>';
 $winner=tkcq_winner($id);if(!$open&&$winner&&get_post_meta($id,'_tkcq_winner_public',true))$out.='<section class="tkc-note tkcq-winner"><h2>'.esc_html(tkcq_text('Selected participant','Väljavalitud osaleja')).'</h2><p>'.esc_html(get_post_meta($winner,'_tkcq_nickname',true)).'</p></section>';
 if($open){$forms=get_option('tkcq_forms',[]);$fid=absint($forms[$lang]??0);if($fid&&class_exists('WPCF7_ContactForm')){
 $out.='<p><a class="tkc-button" href="#city-egg-check-in">'.esc_html(tkcq_text('Submit a check-in','Saada avastusfoto')).'</a></p><section id="city-egg-check-in" class="tkcq-submit"><h2>'.esc_html(tkcq_text('Submit a check-in','Saada avastusfoto')).'</h2>';
 $GLOBALS['tkcq_form_context']=['id'=>$id,'form'=>$fid];try{$out.=do_shortcode('[contact-form-7 id="'.$fid.'"]');}finally{unset($GLOBALS['tkcq_form_context']);}$out.='</section>';
 }else{$out.='<p class="tkc-note">'.esc_html(tkcq_text('Check-ins are temporarily unavailable. Please try again later.','Osalemisvorm ei ole ajutiselt saadaval. Palun proovi hiljem uuesti.')).'</p>';}}
 else $out.='<p class="tkc-note">'.esc_html(tkcq_text('This round has ended. New check-ins are no longer accepted.','See voor on lõppenud. Uusi osalemisfotosid enam vastu ei võeta.')).'</p>';
 return $out.tkcq_thread($id).'</div>';
}
add_shortcode('tkc_city_egg','tkcq_detail');
