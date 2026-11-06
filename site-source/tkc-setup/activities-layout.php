<?php
/** One-time, reversible move into native editable Activities columns. */
defined('ABSPATH') || exit;
function tkcq_layout_admin(){
 if(get_option('tkcq_activities_layout_v1')){echo '<p>City Surprise is part of Activities → Online Activities. Maintain rounds here; edit section headings in Pages → Activities.</p>';return;}
 echo '<h2>Activities layout / 活动栏目布局</h2><p>Back up current pages and menu records, then place In-Person Activities on the left and Online Activities on the right. Existing events, check-ins and round details are preserved. Old listing URLs redirect to Activities.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcq_activities_layout');echo '<input type="hidden" name="action" value="tkcq_activities_layout">';submit_button('Apply Activities layout / 应用活动布局');echo '</form>';
}
function tkcq_native_heading($text,$level=2){return '<!-- wp:heading '.wp_json_encode(['level'=>$level]).' --><h'.$level.' class="wp-block-heading">'.esc_html($text).'</h'.$level.'><!-- /wp:heading -->';}
add_action('admin_post_tkcq_activities_layout',function(){
 if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcq_activities_layout');
 if(get_option('tkcq_activities_layout_v1')){wp_safe_redirect(admin_url('edit.php?post_type=tkc_quest&page=tkcq-setup'));exit;}
 $map=get_option('tkc_page_map',[]);$prepared=[];$backup=['time'=>gmdate('c'),'pages'=>[],'menus'=>[],'map'=>$map];
 foreach(['en','et']as$lang){
  $id=absint($map['activities'][$lang]??0);$post=get_post($id);if(!$post)wp_die('Activities page missing. No layout changes applied.');
  $backup['pages'][$id]=['title'=>$post->post_title,'content'=>$post->post_content];$blocks=parse_blocks($post->post_content);$found=false;
  foreach($blocks as&$block){if($block['blockName']!=='core/group'||!preg_match('/\btkc-body\b/',$block['attrs']['className']??''))continue;
   $old=serialize_blocks($block['innerBlocks']);if(strpos($old,'[tkc_events')===false||strpos($old,'[tkc_past_activities')===false)wp_die('Activities blocks have changed. No layout changes applied.');
   $old=preg_replace('/<!-- wp:heading(?: \{[^}]*\})? -->\s*<h2([^>]*)>(.*?)<\/h2>\s*<!-- \/wp:heading -->/s','<!-- wp:heading {"level":3} --><h3$1>$2</h3><!-- /wp:heading -->',$old);
   $et=$lang==='et';
   $left=tkcq_native_heading($et?'Kohapealsed tegevused':'In-Person Activities').$old;
   $right=tkcq_native_heading($et?'Veebitegevused':'Online Activities').'<!-- wp:paragraph {"className":"tkc-label"} --><p class="tkc-label">City Surprise</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>'.($et?'Avasta Tallinnat ühe vihje kaupa. Leia pildil kujutatud koht ja jaga fotot koos nõutud žestiga. Nägu ei pea näitama.':'Explore Tallinn, one clue at a time. Find the place in the photo and share your discovery with the required gesture. No face needed.').'</p><!-- /wp:paragraph -->'.tkcq_native_heading($et?'Käimasolevad tegevused':'Ongoing activities',3).'<!-- wp:shortcode -->[tkc_city_surprise scope="ongoing"]<!-- /wp:shortcode -->'.tkcq_native_heading($et?'Varasemad tegevused':'Past activities',3).'<!-- wp:shortcode -->[tkc_city_surprise scope="past"]<!-- /wp:shortcode -->';
   $replacement='<!-- wp:group {"className":"tkc-body tkcq-activities-body","layout":{"type":"default"}} --><div class="wp-block-group tkc-body tkcq-activities-body"><!-- wp:columns {"className":"tkcq-activity-columns"} --><div class="wp-block-columns tkcq-activity-columns"><!-- wp:column {"className":"tkcq-inperson"} --><div class="wp-block-column tkcq-inperson">'.$left.'</div><!-- /wp:column --><!-- wp:column {"className":"tkcq-online","anchor":"online-activities"} --><div class="wp-block-column tkcq-online" id="online-activities">'.$right.'</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';
   $parsed=parse_blocks($replacement);$block=$parsed[0];$found=true;break;
  }unset($block);if(!$found)wp_die('Activities layout not recognised. No changes applied.');$prepared[$id]=serialize_blocks($blocks);
 }
 $menus=get_option('tkc_menu_map',[]);$locations=get_theme_mod('nav_menu_locations',[]);$primary=[];
 foreach(['en','et']as$lang){$mid=absint($menus[$lang]??($locations['primary-'.$lang]??0));if(!$mid)wp_die('Primary menu missing. No changes applied.');$primary[$lang]=$mid;}
 foreach(wp_get_nav_menus()as$menu)foreach(wp_get_nav_menu_items($menu->term_id,['post_status'=>'any'])?:[]as$item)$backup['menus'][$item->ID]=['post'=>get_post($item->ID,ARRAY_A),'meta'=>get_post_meta($item->ID)];
 foreach($map['privacy-policy']??[]as$id){$p=get_post($id);if($p)$backup['pages'][$id]=['title'=>$p->post_title,'content'=>$p->post_content];}
 update_option('tkcq_activities_layout_backup',$backup,false);
 foreach($prepared as$id=>$content){$result=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$content]),true);if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));}
 $order=['home','activities','get-involved','partnerships','support-us','contact','about-us','resources'];$labels=['home'=>['Home','Avaleht'],'activities'=>['Activities','Tegevused'],'get-involved'=>['Get involved','Löö kaasa'],'partnerships'=>['Partner','Koostöö'],'support-us'=>['Support us','Toeta meid'],'contact'=>['Contact','Kontakt'],'about-us'=>['About us','Meist'],'resources'=>['Resource','Ressursid']];
 foreach($primary as$lang=>$mid){$items=wp_get_nav_menu_items($mid)?:[];foreach($order as$position=>$key){$existing=0;foreach($items as$item)if((int)$item->object_id===(int)($map[$key][$lang]??0)){$existing=$item->ID;break;}if(empty($map[$key][$lang]))continue;wp_update_nav_menu_item($mid,$existing,['menu-item-title'=>$labels[$key][$lang==='et'?1:0],'menu-item-object-id'=>$map[$key][$lang],'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish','menu-item-position'=>$position+1,'menu-item-parent-id'=>0]);}}
 // Remove the former standalone listing links recoverably; keep the routes as redirects.
 $oldids=array_map('intval',array_values($map['city-eggs']??[]));foreach(wp_get_nav_menus()as$menu)foreach(wp_get_nav_menu_items($menu->term_id)?:[]as$item)if(in_array((int)$item->object_id,$oldids,true))wp_trash_post($item->ID);
 foreach($map['privacy-policy']??[]as$id){$p=get_post($id);if(!$p)continue;$content=str_replace(['City Eggs','Linna-aardejahi','Linna-aardejahis'],['City Surprise','City Surprise’i','City Surprise’is'],$p->post_content);if($content!==$p->post_content)wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$content]));}
 foreach(get_option('tkcq_forms',[])as$id){$p=get_post($id);if($p&&strpos($p->post_title,'City Eggs')!==false)wp_update_post(['ID'=>$id,'post_title'=>str_replace('City Eggs','City Surprise',$p->post_title)]);}
 update_option('tkcq_activities_layout_v1',gmdate('c'),false);wp_safe_redirect(admin_url('edit.php?post_type=tkc_quest&page=tkcq-setup'));exit;
});
add_action('template_redirect',function(){
 if(!get_option('tkcq_activities_layout_v1')||!is_page()||is_preview())return;$map=get_option('tkc_page_map',[]);foreach($map['city-eggs']??[]as$lang=>$id)if(get_queried_object_id()===(int)$id&&!empty($map['activities'][$lang])){wp_safe_redirect(get_permalink($map['activities'][$lang]).'#online-activities',302);exit;}
},1);
