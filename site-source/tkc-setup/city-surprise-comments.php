<?php
/** Public check-ins use native WordPress comment moderation; emails stay in private entry meta. */
defined('ABSPATH') || exit;
function tkcq_public_disclosure($lang){return $lang==='et'?'Minu hüüdnimi, foto, soovitused ja kommentaar avaldatakse selle tegevuse all. Minu e-posti aadress jääb privaatseks.':'My nickname, photo, recommendations and comment will appear below this activity. My email stays private.';}
function tkcq_comment_entry($comment){
 $c=get_comment($comment);if(!$c||$c->comment_type!=='tkcq_checkin')return 0;$entry=(int)get_comment_meta($c->comment_ID,'_tkcq_entry',true);
 if(get_post_type($entry)!=='flamingo_inbound'||get_post_status($entry)!=='publish'||(int)get_post_meta($entry,'_tkcq_quest_id',true)!==(int)$c->comment_post_ID||(int)get_post_meta($entry,'_tkcq_comment_id',true)!==(int)$c->comment_ID||get_post_meta($entry,'_tkcq_public_consent',true)!=='public-v1')return 0;return $entry;
}
function tkcq_public_photo_url($entry){return add_query_arg(['action'=>'tkcq_public_photo','entry'=>absint($entry)],admin_url('admin-post.php'));}
function tkcq_serve_public_photo(){
 $entry=absint($_GET['entry']??0);$comment=get_comment((int)get_post_meta($entry,'_tkcq_comment_id',true));$round=(int)get_post_meta($entry,'_tkcq_quest_id',true);
 if(!$comment||$comment->comment_approved!=='1'||tkcq_comment_entry($comment)!==$entry||get_post_type($round)!=='tkc_quest'||get_post_status($round)!=='publish'){$path=false;}else{$path=tkcq_photo_path($entry);}
 nocache_headers();header('Cache-Control: no-store, max-age=0');header('X-Content-Type-Options: nosniff');header('X-Robots-Tag: noindex, noarchive');if(!$path){status_header(404);exit('Photo not found.');}
 header('Content-Type: image/jpeg');header('Content-Disposition: inline; filename="city-surprise-check-in.jpg"');header('Content-Length: '.filesize($path));readfile($path);exit;
}
add_action('admin_post_tkcq_public_photo','tkcq_serve_public_photo');add_action('admin_post_nopriv_tkcq_public_photo','tkcq_serve_public_photo');
function tkcq_insert_public_comment($entry,$round,$nickname,$comment){
 // Store the contact email only in protected Flamingo data, never in the public comment object.
 $cid=wp_insert_comment(wp_slash(['comment_post_ID'=>$round,'comment_author'=>$nickname,'comment_author_email'=>'','comment_author_url'=>'','comment_author_IP'=>'','comment_agent'=>'','comment_content'=>$comment,'comment_type'=>'tkcq_checkin','comment_parent'=>0,'user_id'=>0,'comment_approved'=>1,'comment_meta'=>['_tkcq_entry'=>$entry]]));
 if(!$cid)throw new RuntimeException('The check-in could not be published. Please try again.');update_post_meta($entry,'_tkcq_comment_id',$cid);return $cid;
}
function tkcq_thread($round){
 $page=min(10000,max(1,absint($_GET['checkin_page']??1)));$limit=10;$args=['post_id'=>$round,'type'=>'tkcq_checkin','status'=>'approve','orderby'=>'comment_date_gmt','order'=>'DESC','meta_query'=>[['key'=>'_tkcq_entry','compare'=>'EXISTS']]];
 // Native moderation is authoritative; legacy private entries never enter this comment stream.
 $total=(int)get_comments(array_merge($args,['count'=>true]));$pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);$comments=get_comments(array_merge($args,['number'=>$limit,'offset'=>($page-1)*$limit]));
 $out='<section id="community-checkins" class="tkcq-thread" aria-label="'.esc_attr(tkcq_text('Community check-ins','Osalejate avastusfotod')).'"><h2>'.esc_html(tkcq_text('Community check-ins','Osalejate avastusfotod')).'</h2>';
 if(!$comments)return $out.'<p class="tkc-note">'.esc_html(tkcq_text('No check-ins yet. Be the first to share your discovery!','Avastusfotosid veel pole. Jaga esimesena oma avastust!')).'</p></section>';
 $out.='<div class="tkcq-comments">';foreach($comments as$c){$entry=tkcq_comment_entry($c);if(!$entry)continue;$photo=get_post_status($round)==='publish'?tkcq_public_photo_url($entry):tkcq_photo_url($entry);
 $out.='<article class="tkcq-comment" id="comment-'.absint($c->comment_ID).'"><header><strong>'.esc_html($c->comment_author).'</strong><time datetime="'.esc_attr(get_comment_date('c',$c)).'">'.esc_html(wp_date('d.m.Y H:i',strtotime($c->comment_date_gmt.' UTC'),new DateTimeZone('Europe/Tallinn'))).'</time></header><a class="tkcq-comment-photo" href="'.esc_url($photo).'" target="_blank" rel="noopener"><img src="'.esc_url($photo).'" alt="'.esc_attr(tkcq_text('Check-in photo by ','Avastusfoto: ').$c->comment_author).'" loading="lazy"></a>';
 foreach(['place'=>tkcq_text('Recommended place','Soovitatud koht'),'address'=>tkcq_text('Place address','Koha aadress')]as$key=>$label){$value=get_post_meta($entry,'_tkcq_'.$key,true);if($value!=='')$out.='<p><strong>'.esc_html($label).':</strong> '.esc_html($value).'</p>';}
 if($c->comment_content!=='')$out.='<div class="tkcq-comment-text">'.wpautop(esc_html($c->comment_content)).'</div>';$out.='</article>';
 }$out.='</div>';
 if($pages>1)$out.='<nav class="tkcq-pagination" aria-label="'.esc_attr(tkcq_text('Check-in pages','Avastusfotode leheküljed')).'">'.paginate_links(['base'=>str_replace('999999999','%#%',esc_url(add_query_arg('checkin_page',999999999,get_permalink()))),'current'=>$page,'total'=>$pages,'prev_text'=>tkcq_text('← Previous','← Eelmised'),'next_text'=>tkcq_text('Next →','Järgmised →'),'add_fragment'=>'#community-checkins']).'</nav>';
 return $out.'</section>';
}
add_action('wp_enqueue_scripts',function(){if(is_page()&&get_post_meta(get_queried_object_id(),'_tkcq_source',true))wp_enqueue_script('tkcq-public-checkins',plugins_url('city-surprise-comments.js',__FILE__),[],'1.5.1',true);});
add_action('trashed_post',function($id){if(get_post_type($id)==='flamingo_inbound'&&($cid=(int)get_post_meta($id,'_tkcq_comment_id',true)))wp_trash_comment($cid);});
add_action('untrashed_post',function($id){if(get_post_type($id)==='flamingo_inbound'&&($cid=(int)get_post_meta($id,'_tkcq_comment_id',true)))wp_untrash_comment($cid);});
add_action('before_delete_post',function($id){if(get_post_type($id)==='flamingo_inbound'&&($cid=(int)get_post_meta($id,'_tkcq_comment_id',true)))wp_trash_comment($cid);});
add_filter('comment_row_actions',function($actions,$c){$entry=tkcq_comment_entry($c);if($entry&&current_user_can('edit_pages'))$actions['tkcq_entry']='<a href="'.esc_url(add_query_arg(['post_type'=>'tkc_quest','page'=>'tkcq-checkins','entry'=>$entry],admin_url('edit.php'))).'">Private contact / 联系邮箱</a>';return $actions;},10,2);
function tkcq_public_setup_box(){
 if(get_option('tkcq_public_checkins_v1')){echo '<p>New check-ins appear as public comments below each activity. Manage visibility in Comments. Contact emails stay private in Check-ins; older private submissions stay private.</p>';return;}
 echo '<h2>Public check-ins / 公开投稿</h2><p>Add required email and publication notice to both existing forms. Preserve their other text and fields. Old private submissions will not be published.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcq_public_setup');echo '<input type="hidden" name="action" value="tkcq_public_setup">';submit_button('Enable public check-ins / 启用公开投稿');echo '</form>';
}
add_action('admin_post_tkcq_public_setup',function(){
 if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcq_public_setup');if(get_option('tkcq_public_checkins_v1')){wp_safe_redirect(admin_url('edit.php?post_type=tkc_quest&page=tkcq-setup'));exit;}
 $forms=get_option('tkcq_forms',[]);$prepared=[];$backup=['time'=>gmdate('c'),'forms'=>[],'privacy'=>[]];
 foreach(['en','et']as$lang){$f=WPCF7_ContactForm::get_instance($forms[$lang]??0);if(!$f)wp_die('City Surprise forms missing.');$markup=$f->prop('form');$backup['forms'][$f->id()]=$f->get_properties();
 if(strpos($markup,'quest-email')===false){$email='<p><label>'.($lang==='et'?'E-posti aadress * (ei avaldata)':'Email * (not public)').' [email* quest-email autocomplete:email maxlength:254]</label></p>';$markup=preg_replace('/(<p><label>[^<]*\[text\* quest-nickname[^\]]*\]<\/label><\/p>)/','$1'.$email,$markup,1,$n);if(!$n)wp_die('Nickname field layout changed. No forms were updated.');}
 if(strpos($markup,'quest-public')===false)$markup=preg_replace('/<p>\[submit /','<p>[acceptance quest-public]'.tkcq_public_disclosure($lang).'[/acceptance]</p><p>[submit ',$markup,1);
 if(strpos($markup,'quest-public')===false)wp_die('Submit button not found. No forms were updated.');$prepared[$lang]=[$f,$markup];
 }
 $map=get_option('tkc_page_map',[]);foreach($map['privacy-policy']??[]as$lang=>$id){$p=get_post($id);if($p)$backup['privacy'][$id]=$p->post_content;}update_option('tkcq_public_setup_backup',$backup,false);
 foreach($prepared as$lang=>$data){list($f,$markup)=$data;$settings=$f->prop('additional_settings');if(strpos($settings,'acceptance_as_validation:')===false)$settings.="\nacceptance_as_validation: on";$f->set_properties(['form'=>$markup,'additional_settings'=>$settings]);$f->save();}
 foreach($map['privacy-policy']??[]as$lang=>$id){$p=get_post($id);if(!$p)continue;$text=$lang==='et'?'City Surprise’is kogume hüüdnime, e-posti aadressi, avastusfoto ning vabatahtlikud kohasoovitused, aadressi ja kommentaari. Uue vormi kaudu avaldamisega nõustudes kuvatakse hüüdnimi, foto, soovitused ja kommentaar avalikult selle tegevuse all. E-posti aadress on ainult korraldajatele osalemisega seotud ühenduse võtmiseks. Varem privaatsena esitatud andmed jäävad privaatseks. Avaldada võime ka valitud osaleja hüüdnime. Osalemine ei lisa sind uudiskirja saajate hulka. Andmete säilitamisel järgime eespool kirjeldatud tegevusandmete säilitamise korda.':'For City Surprise we collect your nickname, email, check-in photo and any optional place recommendations, address and comment. By agreeing to publication in the new form, your nickname, photo, recommendations and comment appear publicly below the activity. Your email is only visible to organisers for participation-related contact. Earlier private submissions remain private. We may also announce the selected participant’s nickname. Taking part does not subscribe you to marketing emails. The activity-record retention policy described above applies.';
 $content=preg_replace('/(<h2[^>]*id="tkcq-privacy"[^>]*>.*?<\/h2>.*?<!-- wp:paragraph -->)\s*<p>.*?<\/p>/s','$1<p>'.esc_html($text).'</p>',$p->post_content,1,$count);if($count)wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$content]));}
 update_option('tkcq_public_checkins_v1',gmdate('c'),false);wp_safe_redirect(admin_url('edit.php?post_type=tkc_quest&page=tkcq-setup'));exit;
});
function tkcq_comment_admin_link($entry){
 $cid=(int)get_post_meta($entry,'_tkcq_comment_id',true);if(!$cid){echo '<p>Private legacy submission / 早期私有投稿</p>';return;}$c=get_comment($cid);
 echo '<p>Public comment status: <strong>'.esc_html($c?wp_get_comment_status($c):'unavailable').'</strong> · <a class="button" href="'.esc_url(admin_url('comment.php?action=editcomment&c='.$cid)).'">Manage comment / 管理公开投稿</a></p>';
}

add_filter('get_comment_link',function($link,$c){return $c->comment_type==='tkcq_checkin'?tkcq_url((int)$c->comment_post_ID).'#comment-'.absint($c->comment_ID):$link;},10,2);
// Use a publication-specific validation message rather than the default terms wording.
add_action('admin_init',function(){
 if(!current_user_can('manage_options')||get_option('tkcq_public_messages_v1')||!get_option('tkcq_public_checkins_v1')||!class_exists('WPCF7_ContactForm'))return;
 foreach(get_option('tkcq_forms',[])as$lang=>$id){$f=WPCF7_ContactForm::get_instance($id);if(!$f)continue;$messages=$f->prop('messages');$messages['accept_terms']=$lang==='et'?'Palun kinnita oma postituse avaldamine.':'Please confirm that your check-in will be public.';$f->set_properties(['messages'=>$messages]);$f->save();}
 update_option('tkcq_public_messages_v1',1,false);
});
