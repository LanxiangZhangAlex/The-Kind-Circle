<?php
defined('ABSPATH') || exit;
function tkcq_is_form($form=null){$form=$form?: (class_exists('WPCF7_ContactForm')?WPCF7_ContactForm::get_current():null);return $form&&in_array((int)$form->id(),array_map('intval',get_option('tkcq_forms',[])),true);}
function tkcq_form_lang($form=null){$form=$form?:WPCF7_ContactForm::get_current();$forms=get_option('tkcq_forms',[]);return $form&&isset($forms['et'])&&(int)$forms['et']===(int)$form->id()?'et':'en';}
function tkcq_supports_heic(){return wp_image_editor_supports(['mime_type'=>'image/heic']);}
function tkcq_upload_limit(){return max(1,min(10, (int)floor((wp_max_upload_size()-MB_IN_BYTES)/MB_IN_BYTES)))*MB_IN_BYTES;}
function tkcq_private_dir($create=false){
 $root=untrailingslashit(wp_normalize_path(ABSPATH));$dir=wp_normalize_path(dirname($root).'/tkc-private-city-eggs');
 if($create&&!is_dir($dir))wp_mkdir_p($dir);
 if(!is_dir($dir)||!is_writable($dir))return new WP_Error('storage','Private photo storage is not available.');
 $real=wp_normalize_path(realpath($dir));$public=wp_normalize_path(realpath($root));
 if(!$real||!$public||strpos($real.'/',$public.'/')===0)return new WP_Error('storage','Photo storage must be outside the public website directory.');
 if($create){@chmod($dir,0750);if(!file_exists($dir.'/.htaccess'))file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n");if(!file_exists($dir.'/index.html'))file_put_contents($dir.'/index.html','');}
 return $real;
}
function tkcq_photo_path($entry){$name=get_post_meta($entry,'_tkcq_photo',true);if(!is_string($name)||!preg_match('/^[a-f0-9]{48}\.jpg$/D',$name))return false;$dir=tkcq_private_dir();if(is_wp_error($dir))return false;$path=$dir.'/'.$name;return is_file($path)?$path:false;}
function tkcq_photo_url($entry){return wp_nonce_url(add_query_arg(['action'=>'tkcq_photo','entry'=>absint($entry)],admin_url('admin-post.php')),'tkcq_photo_'.$entry);}
function tkcq_serve_photo(){
 if(!is_user_logged_in()||!current_user_can('edit_pages')){status_header(403);exit('Access denied.');}
 $entry=absint($_GET['entry']??0);if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')),'tkcq_photo_'.$entry)){status_header(403);exit('Access denied.');}
 if(get_post_type($entry)!=='flamingo_inbound'||!get_post_meta($entry,'_tkcq_quest_id',true)||get_post_status($entry)==='trash'){status_header(404);exit('Photo not found.');}
 $path=tkcq_photo_path($entry);if(!$path){status_header(404);exit('Photo not found.');}
 nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('Content-Type: image/jpeg');header('X-Content-Type-Options: nosniff');header('X-Robots-Tag: noindex, nofollow, noarchive');header('Content-Disposition: inline; filename="city-egg-entry-'.$entry.'.jpg"');header('Content-Length: '.filesize($path));readfile($path);exit;
}
add_action('admin_post_tkcq_photo','tkcq_serve_photo');
add_action('admin_post_nopriv_tkcq_photo',function(){status_header(403);nocache_headers();exit('Access denied.');});
add_action('before_delete_post',function($id){if(get_post_type($id)!=='flamingo_inbound'||!get_post_meta($id,'_tkcq_quest_id',true))return;$path=tkcq_photo_path($id);if($path)wp_delete_file($path);});
add_filter('wpcf7_form_hidden_fields',function($fields){if(empty($GLOBALS['tkcq_form_context'])||!tkcq_is_form())return $fields;$ctx=$GLOBALS['tkcq_form_context'];$expires=time()+DAY_IN_SECONDS;$fields['tkcq_id']=$ctx['id'];$fields['tkcq_expires']=$expires;$fields['tkcq_public_version']='public-v1';$fields['tkcq_signature']=hash_hmac('sha256',$ctx['id'].'|'.$ctx['form'].'|'.$expires.'|public-v1',wp_salt('auth'));if(is_user_logged_in())$fields['_wpnonce']=wp_create_nonce('wp_rest');return $fields;});
function tkcq_submission_task($submission,$form){
 $id=absint($submission->get_posted_data('tkcq_id'));$expires=absint($submission->get_posted_data('tkcq_expires'));$signature=(string)$submission->get_posted_data('tkcq_signature');
 $expected=hash_hmac('sha256',$id.'|'.$form->id().'|'.$expires.'|public-v1',wp_salt('auth'));
 if(!get_option('tkcq_public_checkins_v1')||$submission->get_posted_data('tkcq_public_version')!=='public-v1'||!$expires||$expires<time()||$expires>time()+2*DAY_IN_SECONDS||!hash_equals($expected,$signature)||!tkcq_accessible($id))return 0;
 return $id;
}
add_filter('wpcf7_validate',function($result,$tags){if(!tkcq_is_form())return $result;$form=WPCF7_ContactForm::get_current();$s=WPCF7_Submission::get_instance();$id=$s?tkcq_submission_task($s,$form):0;$et=tkcq_form_lang($form)==='et';
 if(!$id||!tkcq_open($id))foreach($tags as$tag)if($tag->name==='quest-nickname'){$result->invalidate($tag,$et?'See voor on lõppenud või vorm on aegunud. Palun värskenda lehte.':'This round has ended or the form has expired. Please refresh the page.');break;}
 return $result;
},30,2);
// This form records submissions without emailing any participant or administrator.
add_filter('wpcf7_skip_mail',function($skip,$form){return tkcq_is_form($form)?true:$skip;},100,2);
add_filter('wpcf7_flamingo_submit_if',function($statuses){return tkcq_is_form()?[]:$statuses;},100);
function tkcq_store_photo($source){
 if(!is_file($source)||filesize($source)>tkcq_upload_limit())return new WP_Error('photo','Photo exceeds the upload limit.');
 $mime=function_exists('wp_get_image_mime')?wp_get_image_mime($source):false;$allowed=['image/jpeg','image/png','image/webp'];if(tkcq_supports_heic())$allowed=array_merge($allowed,['image/heic','image/heif']);if(!$mime||!in_array($mime,$allowed,true))return new WP_Error('photo','Please upload a supported photo file.');$info=@getimagesize($source);if($info&&$info[0]*$info[1]>50000000)return new WP_Error('photo','Please upload a photo up to 50 megapixels.');
 if(class_exists('Imagick')){try{$probe=new Imagick();$probe->pingImage($source);$too_big=$probe->getImageWidth()*$probe->getImageHeight()>50000000;$multiple=$probe->getNumberImages()>1;$probe->clear();if($too_big||$multiple)return new WP_Error('photo','Please upload one still photo up to 50 megapixels.');}catch(Throwable $e){return new WP_Error('photo','The photo could not be read. Please try a JPG image.');}}
 $dir=tkcq_private_dir(true);if(is_wp_error($dir))return $dir;
 $editor=wp_get_image_editor($source);if(is_wp_error($editor))return new WP_Error('photo','The photo could not be processed. Please try a JPG image.');
 if(method_exists($editor,'maybe_exif_rotate'))$editor->maybe_exif_rotate();$size=$editor->get_size();if(!$size||$size['width']*$size['height']>50000000)return new WP_Error('photo','Please upload a photo up to 50 megapixels.');if($size['width']>2560||$size['height']>2560){$res=$editor->resize(2560,2560,false);if(is_wp_error($res))return new WP_Error('photo','The photo could not be resized. Please try another image.');}$editor->set_quality(88);
 $name=bin2hex(random_bytes(24)).'.jpg';$saved=$editor->save($dir.'/'.$name,'image/jpeg');if(is_wp_error($saved)||!is_file($dir.'/'.$name))return new WP_Error('photo','The photo could not be saved.');
 if(class_exists('Imagick')){try{$clean=new Imagick($dir.'/'.$name);$clean->stripImage();$clean->writeImage($dir.'/'.$name);$clean->clear();}catch(Throwable $e){wp_delete_file($dir.'/'.$name);return new WP_Error('photo','The photo could not be prepared. Please try another image.');}}
 @chmod($dir.'/'.$name,0640);return $name;
}
add_action('wpcf7_before_send_mail',function($form,&$abort,$s){
 if(!tkcq_is_form($form))return;$et=tkcq_form_lang($form)==='et';$id=tkcq_submission_task($s,$form);$name='';$entry_id=0;$comment_id=0;$lock='';
 try{
  if(!$id||!tkcq_open($id))throw new RuntimeException($et?'See voor on lõppenud.':'This round is closed.');
  if(!class_exists('Flamingo_Inbound_Message'))throw new RuntimeException('Submission storage is unavailable.');
  $raw_email=trim((string)$s->get_posted_data('quest-email'));$email=sanitize_email($raw_email);if(!$email||!is_email($email)||$email!==$raw_email||strlen($email)>254)throw new RuntimeException($et?'Palun sisesta kehtiv e-posti aadress.':'Please enter a valid email address.');if(!in_array('1',array_map('strval',(array)$s->get_posted_data('quest-public')),true))throw new RuntimeException($et?'Palun kinnita, et sinu postitus on avalik.':'Please confirm that your check-in will be public.');
  $nickname=sanitize_text_field((string)$s->get_posted_data('quest-nickname'));$place=sanitize_text_field((string)$s->get_posted_data('quest-place'));$address=sanitize_text_field((string)$s->get_posted_data('quest-address'));$comment=sanitize_textarea_field((string)$s->get_posted_data('quest-comment'));
  if(!$nickname||mb_strlen($nickname)>80||mb_strlen($place)>180||mb_strlen($address)>240||mb_strlen($comment)>3000)throw new RuntimeException($et?'Palun kontrolli sisestatud teksti pikkust.':'Please check the text lengths.');
  $files=$s->uploaded_files();$uploaded=$files['quest-photo']??[];$uploaded=is_array($uploaded)?$uploaded:[$uploaded];if(count($uploaded)!==1||!is_file($uploaded[0]))throw new RuntimeException($et?'Palun lisa üks foto.':'Please attach one photo.');$source=$uploaded[0];
  $fingerprint=hash_hmac('sha256',wp_json_encode([$id,strtolower($email),'public-v1',$nickname,$place,$address,$comment,hash_file('sha256',$source)]),wp_salt('nonce'));
  $existing=get_posts(['post_type'=>'flamingo_inbound','post_status'=>'publish','numberposts'=>1,'lang'=>'','suppress_filters'=>true,'meta_key'=>'_tkcq_fingerprint','meta_value'=>$fingerprint]);if($existing&&tkcq_photo_path($existing[0]->ID)){if(!(int)get_post_meta($existing[0]->ID,'_tkcq_comment_id',true))tkcq_insert_public_comment($existing[0]->ID,$id,$nickname,$comment);return;}
  $lock='tkcq_lock_'.$fingerprint;$old=(int)get_option($lock);if($old&&$old<time()-600)delete_option($lock);if(!add_option($lock,time(),'','no')){$lock='';throw new RuntimeException($et?'Sinu foto on töötlemisel. Palun oota ja proovi uuesti.':'This check-in is already being processed. Please wait and try again.');}
  $rate='tkcq_rate_'.hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),wp_salt('auth'));$count=(int)get_transient($rate);if($count>=30)throw new RuntimeException($et?'Liiga palju katseid. Palun proovi 15 minuti pärast uuesti.':'Too many uploads. Please try again in 15 minutes.');
  $name=tkcq_store_photo($source);if(is_wp_error($name))throw new RuntimeException($name->get_error_message());
  // Check again after image processing, so a deadline passing during upload is honoured.
  if(!tkcq_open($id))throw new RuntimeException($et?'See voor on lõppenud.':'This round is closed.');
  $entry=Flamingo_Inbound_Message::add(['channel'=>'tkc-city-eggs','status'=>'mail_sent','subject'=>'City Surprise — '.get_the_title($id),'from'=>$nickname,'from_name'=>$nickname,'from_email'=>'','fields'=>['nickname'=>$nickname,'photo'=>'Submitted photo — open in City Surprise / Check-ins','email'=>$email,'recommended-place'=>$place,'place-address'=>$address,'comment'=>$comment],'meta'=>['city_egg_id'=>$id,'language'=>tkcq_form_lang($form)],'consent'=>[],'timestamp'=>time()]);
  $entry_id=method_exists($entry,'id')?(int)$entry->id():(int)$entry->id;if(!$entry_id)throw new RuntimeException('The check-in could not be saved.');
  update_post_meta($entry_id,'_tkcq_quest_id',$id);update_post_meta($entry_id,'_tkcq_nickname',$nickname);update_post_meta($entry_id,'_tkcq_place',$place);update_post_meta($entry_id,'_tkcq_address',$address);update_post_meta($entry_id,'_tkcq_comment',$comment);update_post_meta($entry_id,'_tkcq_photo',$name);update_post_meta($entry_id,'_tkcq_fingerprint',$fingerprint);update_post_meta($entry_id,'_tkcq_language',tkcq_form_lang($form));update_post_meta($entry_id,'_tkcq_email',$email);update_post_meta($entry_id,'_tkcq_public_consent','public-v1');update_post_meta($entry_id,'_tkcq_public_consent_at',gmdate('c'));
  $comment_id=tkcq_insert_public_comment($entry_id,$id,$nickname,$comment);
  if((int)get_post_meta($entry_id,'_tkcq_quest_id',true)!==$id||!tkcq_photo_path($entry_id))throw new RuntimeException('The check-in could not be saved. Please try again.');
  set_transient($rate,$count+1,15*MINUTE_IN_SECONDS);
 }catch(Throwable $e){$abort=true;$s->set_response($e->getMessage());if($comment_id)wp_trash_comment($comment_id);if($entry_id)wp_trash_post($entry_id);if(is_string($name)&&$name){$dir=tkcq_private_dir();if(!is_wp_error($dir)&&preg_match('/^[a-f0-9]{48}\.jpg$/D',$name))wp_delete_file($dir.'/'.$name);}}
 finally{if($lock)delete_option($lock);}
},20,3);
// Extra private-photo tools within Flamingo; direct media URLs are never stored publicly.
add_action('add_meta_boxes_flamingo_inbound',function($post){if(!get_post_meta($post->ID,'_tkcq_quest_id',true))return;add_meta_box('tkcq-private-photo','City Surprise — private photo',function($p){if(!current_user_can('edit_pages'))return;echo '<p><a href="'.esc_url(tkcq_photo_url($p->ID)).'" target="_blank" rel="noopener">View private photo</a></p><img style="max-width:100%;max-height:500px" src="'.esc_url(tkcq_photo_url($p->ID)).'" alt="Submitted check-in photo">';},'flamingo_inbound');});
