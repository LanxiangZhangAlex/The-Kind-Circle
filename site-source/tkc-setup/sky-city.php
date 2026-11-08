<?php
/** Online photo projects; public pages contain no private submission objects. */
defined('ABSPATH') || exit;
require_once __DIR__.'/sky-city-upload.php';
require_once __DIR__.'/sky-city-admin.php';
function tkcsky_text($en,$et){return tkcq_text($en,$et);}
function tkcsky_field($id,$key,$lang=null){$lang=$lang?:(tkcq_et()?'et':'en');$base=$key==='title'?get_the_title($id):get_post_meta($id,'_tkcsky_'.$key,true);return $lang==='et'?(get_post_meta($id,'_tkcsky_'.$key.'_et',true)?:$base):$base;}
function tkcsky_url($id,$lang=null){$pages=get_post_meta($id,'_tkcsky_pages',true);$lang=$lang?:(tkcq_et()?'et':'en');return !empty($pages[$lang])?get_permalink($pages[$lang]):'';}
function tkcsky_accessible($id){return get_post_type($id)==='tkc_online'&&(get_post_status($id)==='publish'||(get_post_status($id)==='private'&&current_user_can('edit_post',$id)));}
function tkcsky_open($id){return tkcsky_accessible($id)&&get_post_meta($id,'_tkcsky_state',true)==='open';}
add_action('init',function(){register_post_type('tkc_online',['labels'=>['name'=>'Online projects / 线上项目','singular_name'=>'Online project','add_new_item'=>'Add online project / 新建项目','edit_item'=>'Edit online project / 编辑项目'],'public'=>false,'show_ui'=>true,'show_in_rest'=>false,'show_in_nav_menus'=>false,'rewrite'=>false,'supports'=>['title','editor','thumbnail','revisions'],'capability_type'=>'page','map_meta_cap'=>true,'menu_icon'=>'dashicons-format-image','menu_position'=>23]);});
add_filter('use_block_editor_for_post_type',function($use,$type){return $type==='tkc_online'?false:$use;},10,2);
add_action('template_redirect',function(){
 if(!is_page())return;$id=(int)get_post_meta(get_queried_object_id(),'_tkcsky_source',true);$body=get_post_field('post_content',get_queried_object_id());
 if($id||has_shortcode($body,'tkc_online_activities')){nocache_headers();if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);}
 if($id&&!tkcsky_accessible($id)){global $wp_query;$wp_query->set_404();status_header(404);nocache_headers();}
});
add_action('wp_enqueue_scripts',function(){
 if(!is_page())return;$body=get_post_field('post_content',get_queried_object_id());if(!get_post_meta(get_queried_object_id(),'_tkcsky_source',true)&&!has_shortcode($body,'tkc_online_activities'))return;
 wp_enqueue_style('tkc-sky-city',plugins_url('sky-city.css',__FILE__),[],'1.8.0');
 if(get_post_meta(get_queried_object_id(),'_tkcsky_source',true)){wp_enqueue_script('tkc-sky-city',plugins_url('sky-city.js',__FILE__),[],'1.8.0',true);wp_enqueue_style('tkc-event-gallery',plugins_url('event-gallery.css',__FILE__),[],'1.8.0');wp_enqueue_script('tkc-event-gallery',plugins_url('event-gallery.js',__FILE__),[],'1.8.0',true);}
});
function tkcsky_project_card($id){
 $state=get_post_meta($id,'_tkcsky_state',true);$label=$state==='open'?tkcsky_text('Open for photos','Fotode saatmine avatud'):($state==='paused'?tkcsky_text('Submissions paused','Fotode saatmine peatatud'):tkcsky_text('Completed','Lõppenud'));
 $image=get_the_post_thumbnail_url($id,'large')?:plugins_url('sky-city-cover.svg',__FILE__);
 return '<article class="tkc-card tkcq-card tkcsky-project"><a class="tkcq-card-link" href="'.esc_url(tkcsky_url($id)).'"><img src="'.esc_url($image).'" alt="'.esc_attr(tkcsky_field($id,'title')).'" loading="lazy"><div class="tkc-card-body"><span class="tkc-event-status">'.esc_html($label).'</span><h4>'.esc_html(tkcsky_field($id,'title')).'</h4><p>'.esc_html(tkcsky_field($id,'summary')).'</p><span>'.esc_html(tkcsky_text('Explore this activity','Vaata tegevust')).'</span></div></a></article>';
}
function tkcsky_online_list($attrs){
 $a=shortcode_atts(['scope'=>'ongoing','limit'=>6],$attrs);$past=$a['scope']==='past';$limit=min(24,max(1,absint($a['limit'])));$param=$past?'online_past_page':'online_ongoing_page';$page=max(1,min(10000,absint($_GET[$param]??1)));$items=[];
 $rounds=get_posts(['post_type'=>'tkc_quest','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','lang'=>'','suppress_filters'=>true,'meta_query'=>[['key'=>'_tkcq_deadline','value'=>time(),'compare'=>$past?'<=':'>','type'=>'NUMERIC']]]);
 foreach($rounds as$id)$items[]=['id'=>$id,'kind'=>'round','sort'=>(int)get_post_meta($id,'_tkcq_deadline',true)];
 $projects=get_posts(['post_type'=>'tkc_online','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','lang'=>'','suppress_filters'=>true,'meta_query'=>[['key'=>'_tkcsky_state','value'=>$past?['ended']:['open','paused'],'compare'=>'IN']]]);
 foreach($projects as$id){$pages=get_post_meta($id,'_tkcsky_pages',true);if(empty($pages['en'])||empty($pages['et']))continue;$items[]=['id'=>$id,'kind'=>'project','sort'=>$past?(int)get_post_meta($id,'_tkcsky_ended_at',true):PHP_INT_MAX];}
 usort($items,function($a,$b)use($past){$c=$a['sort']<=>$b['sort'];return $c?($past?-$c:$c):($a['id']<=>$b['id']);});$total=max(1,(int)ceil(count($items)/$limit));$page=min($total,$page);$out='<div class="tkcq-list" id="online-'.($past?'past':'ongoing').'"><div class="tkcq-grid">';
 foreach(array_slice($items,($page-1)*$limit,$limit)as$item){$id=$item['id'];if($item['kind']==='project'){$out.=tkcsky_project_card($id);continue;}$img=tkcq_card_image($id);$out.='<article class="tkc-card tkcq-card"><a class="tkcq-card-link" href="'.esc_url(tkcq_url($id)).'">'.($img?'<img src="'.esc_url($img).'" alt="'.esc_attr(tkcq_title($id)).'" loading="lazy">':'').'<div class="tkc-card-body"><span class="tkc-event-status">'.esc_html($past?tkcsky_text('Closed','Lõppenud'):tkcsky_text('Open for check-ins','Osalemine avatud')).'</span><h4>'.esc_html(tkcq_title($id)).'</h4><p>'.esc_html(tkcq_field($id,'hint')).'</p><p>'.esc_html(tkcsky_text('Deadline: ','Tähtaeg: ').tkcq_deadline_label($id)).'</p><span>'.esc_html(tkcsky_text('Explore this round','Vaata selle vooru ülesannet')).'</span></div></a></article>';}
 $out.='</div>';if(!$items)$out.='<p class="tkc-note">'.esc_html($past?tkcsky_text('Completed online activities will appear here.','Lõppenud veebitegevused ilmuvad siia.'):tkcsky_text('New online activities are coming soon.','Uued veebitegevused on peagi tulekul.')).'</p>';
 if($total>1)$out.='<nav class="tkcq-pagination" aria-label="'.esc_attr(tkcsky_text('Online activity pages','Veebitegevuste leheküljed')).'">'.paginate_links(['base'=>str_replace('999999999','%#%',esc_url(add_query_arg($param,999999999,get_permalink()))),'total'=>$total,'current'=>$page,'add_fragment'=>'#online-'.($past?'past':'ongoing'),'prev_text'=>'←','next_text'=>'→']).'</nav>';return $out.'</div>';
}
add_shortcode('tkc_online_activities','tkcsky_online_list');
function tkcsky_gallery($project){
 $page=max(1,min(10000,absint($_GET['sky_page']??1)));$q=new WP_Query(['post_type'=>'flamingo_inbound','post_status'=>'publish','posts_per_page'=>12,'paged'=>$page,'lang'=>'','suppress_filters'=>true,'meta_query'=>[['key'=>'_tkcsky_project','value'=>$project,'type'=>'NUMERIC'],['key'=>'_tkcsky_review','value'=>'approved'],['key'=>'_tkcsky_consent','value'=>'public-v1']],'meta_key'=>'_tkcsky_published_at','orderby'=>['meta_value_num'=>'DESC','ID'=>'DESC']]);
 $out='<section id="shared-skies" class="tkcsky-wall"><h2>'.esc_html(tkcsky_text('Shared skies','Jagatud taevad')).'</h2>';
 if(!$q->posts)return $out.'<p>'.esc_html(tkcsky_text('No photos yet. Share the first sky from your city.','Fotosid veel pole. Jaga esimesena oma linna taevast.')).'</p></section>';
 $out.='<div class="tkc-event-gallery tkcsky-grid" data-tkc-gallery style="--tkc-gallery-columns:1">';foreach($q->posts as$p){$id=$p->ID;if(!tkcsky_photo_path($id))continue;$name=get_post_meta($id,'_tkcsky_nickname',true);$city=get_post_meta($id,'_tkcsky_city',true);$message=get_post_meta($id,'_tkcsky_message',true);$ts=(int)get_post_meta($id,'_tkcsky_published_at',true);$date=wp_date('d.m.Y',$ts,new DateTimeZone('Europe/Tallinn'));$url=get_post_status($project)==='publish'?tkcsky_photo_url($id):tkcsky_photo_url($id,true);$caption=$name.' · '.$city.' · '.$date.($message?' — '.$message:'');
 $out.='<figure class="tkcsky-photo" id="sky-entry-'.(int)$id.'"><a class="tkc-gallery-link" href="'.esc_url($url).'" aria-label="'.esc_attr(tkcsky_text('Open photo: ','Ava foto: ').$name.', '.$city).'"><img src="'.esc_url(add_query_arg('size','thumb',$url)).'" alt="'.esc_attr(tkcsky_text('Sky and city in ','Taevas ja linn: ').$city).'" loading="lazy" decoding="async"></a><figcaption><strong>'.esc_html($name).'</strong><span>'.esc_html($city).'</span><time datetime="'.esc_attr(gmdate('c',$ts)).'">'.esc_html($date).'</time>'.($message?'<p>'.esc_html($message).'</p>':'').'<span class="tkcsky-caption">'.esc_html($caption).'</span></figcaption>'.tkci_widget('sky',$id).'</figure>';
 }$out.='</div>';if($q->max_num_pages>1)$out.='<nav class="tkcq-pagination" aria-label="'.esc_attr(tkcsky_text('Photo pages','Fotode leheküljed')).'">'.paginate_links(['base'=>str_replace('999999999','%#%',esc_url(add_query_arg('sky_page',999999999,get_permalink()))),'total'=>$q->max_num_pages,'current'=>$page,'add_fragment'=>'#shared-skies','prev_text'=>tkcsky_text('← Previous','← Eelmised'),'next_text'=>tkcsky_text('Next →','Järgmised →')]).'</nav>';return $out.'</section>';
}
function tkcsky_detail($attrs){
 $id=absint($attrs['id']??0);if(!tkcsky_accessible($id))return '';$lang=tkcq_et()?'et':'en';$post=get_post($id);$body=$lang==='et'?(get_post_meta($id,'_tkcsky_body_et',true)?:$post->post_content):$post->post_content;$open=tkcsky_open($id);$forms=get_option('tkcsky_forms',[]);
 $out='<section class="tkc-page-intro tkc-section"><p class="tkc-label">'.esc_html(tkcsky_text('Online Activities','Veebitegevused')).'</p><h1>'.esc_html(tkcsky_field($id,'title')).'</h1><p>'.esc_html(tkcsky_field($id,'summary')).'</p>'.($open?'<p><a class="tkc-button" href="#share-your-sky">'.esc_html(tkcsky_text('Share Your Sky','Jaga oma taevast')).'</a></p>':'').'</section><div class="tkc-body tkcsky-detail">';
 if($post->post_status==='private')$out.='<p class="tkc-note">Private verification project — only website managers can view this page.</p>';
 $out.=wpautop(do_blocks(wp_kses_post($body)));
 if($open&&!empty($forms[$lang])){$fid=(int)$forms[$lang];$out.='<section class="tkcq-submit" id="share-your-sky"><h2>'.esc_html(tkcsky_text('Share Your Sky','Jaga oma taevast')).'</h2><p>'.esc_html(tkcsky_text('One photo with the sky and a city. No exact address or GPS is needed. Photos appear after review; your email stays private.','Üks foto, millel on taevas ja linn. Täpset aadressi ega GPS-andmeid pole vaja. Fotod avaldatakse pärast ülevaatamist; sinu e-post jääb privaatseks.')).'</p>';$GLOBALS['tkcsky_context']=['id'=>$id,'form'=>$fid];try{$out.=do_shortcode('[contact-form-7 id="'.$fid.'"]');}finally{unset($GLOBALS['tkcsky_context']);}$out.='</section>';}
 else $out.='<p class="tkc-note">'.esc_html(get_post_meta($id,'_tkcsky_state',true)==='ended'?tkcsky_text('This project has ended. You can still explore the shared photos.','Projekt on lõppenud. Jagatud fotosid saab endiselt vaadata.'):tkcsky_text('Photo submissions are currently paused.','Fotode saatmine on praegu peatatud.')).'</p>';
 return $out.tkcsky_gallery($id).'</div>';
}
add_shortcode('tkc_sky_city','tkcsky_detail');
