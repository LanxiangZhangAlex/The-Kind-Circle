<?php
/**
 * Plugin Name: The Kind Circle Site Tools
 * Description: TKC bilingual page setup, shared event presentation, and form workflow integration. Uses Polylang, Events Manager, Contact Form 7, Flamingo and Newsletter.
 * Version: 1.7.5
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__.'/past-events.php';
require_once __DIR__.'/editable-content.php';
require_once __DIR__.'/city-eggs.php';
require_once __DIR__.'/event-gallery.php';
require_once __DIR__.'/sky-city.php';
require_once __DIR__.'/team-page.php';

add_action('admin_menu',function(){add_management_page('TKC Website','TKC Website','manage_options','tkc-website','tkcs_admin');});
function tkcs_admin(){
    if(!current_user_can('manage_options'))return;
    echo '<div class="wrap"><h1>The Kind Circle website</h1>';
    if(get_option('tkc_setup_done')){
        echo '<div class="notice notice-success"><p>Bilingual pages, navigation and forms are configured.</p></div>';
        echo '<p>Maintain page content in Pages, activities and registrations in Events, enquiries in Flamingo, forms in Contact, and subscriptions in Newsletter.</p>';
        echo '<p>Update application and enquiry status in the follow-up table below. Open the message to read its full contents.</p>';
        echo '<p>All English and Estonian event pages share one Events Manager booking record. Add the Estonian title and description in the TKC bilingual event panel.</p>';
        echo '<p>Retain backups before updates and periodically review old form records according to the privacy policy.</p>';
        $map=get_option('tkc_page_map',[]);echo '<table class="widefat striped"><thead><tr><th>Page</th><th>English</th><th>Eesti</th></tr></thead><tbody>';
        foreach($map as $key=>$langs){echo '<tr><td>'.esc_html($key).'</td>';foreach(['en','et']as$lang)echo '<td><a href="'.esc_url(get_edit_post_link($langs[$lang])).'">Edit</a> · <a href="'.esc_url(get_permalink($langs[$lang])).'">View</a></td>';echo '</tr>';}
        echo '</tbody></table>';
        tkcs_status_table();
        echo '<h2>Event content source</h2><p>Maintain activity titles, descriptions, dates, images and registration settings in Events. Use the TKC bilingual event details panel for Estonian content. Linked Pages are presentation routes, not separate copies of event text.</p>';
        if(!get_option('tkc_events_source_migrated')){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcs_events_source');echo '<input type="hidden" name="action" value="tkcs_events_source"><p>Preserve existing page content in a private backup, then switch existing event pages to read directly from Events. Current event content and booking settings are preserved.</p>';submit_button('Use Events as content source');echo '</form>';}
        tkcs_history_import_form();tkcs_history_preview_form();
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcs_improve');echo '<input type="hidden" name="action" value="tkcs_improve">';submit_button('Apply configuration improvements');echo '</form>';
    }else{
        echo '<p>Create the approved TKC pages, bilingual navigation, enquiry forms and initial activity. Existing unrelated content is preserved. The default WordPress sample post and page are moved to Trash.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcs_setup');echo '<input type="hidden" name="action" value="tkcs_setup">';submit_button('Set up TKC website');echo '</form>';
    }
    echo '</div>';
}
add_action('admin_post_tkcs_setup','tkcs_setup');
function tkcs_setup(){
    if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcs_setup');
    if(get_option('tkc_setup_done')){wp_safe_redirect(admin_url('tools.php?page=tkc-website'));exit;}
    foreach(['pll_set_post_language','pll_save_post_translations']as$fn)if(!function_exists($fn))wp_die('Activate Polylang and configure English and Estonian first.');
    foreach(['WPCF7_ContactForm','Flamingo_Inbound_Message','EM_Event','Newsletter']as$class)if(!class_exists($class))wp_die('Activate all required plugins first: '.esc_html($class));
    if(!in_array('et',pll_languages_list(),true)||!in_array('en',pll_languages_list(),true))wp_die('English and Estonian must be configured first.');
    $data=json_decode(file_get_contents(__DIR__.'/content.json'),true);if(!is_array($data))wp_die('Content data unavailable.');
    $map=get_option('tkc_page_map',[]);
    foreach($data as$key=>$langs){foreach($langs as$lang=>$page){
        if(empty($map[$key][$lang])){
            $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$page['title'],'post_name'=>($key==='home'?'welcome':$key).($lang==='et'?'-et':''),'post_content'=>'','comment_status'=>'closed'],true);
            if(is_wp_error($id))wp_die(esc_html($id->get_error_message()));$map[$key][$lang]=$id;update_option('tkc_page_map',$map,false);
        }
        pll_set_post_language($map[$key][$lang],$lang);update_post_meta($map[$key][$lang],'tkc_key',$key);
    }pll_save_post_translations($map[$key]);}
    update_option('page_on_front',$map['home']['en']);update_option('show_on_front','page');update_option('wp_page_for_privacy_policy',$map['privacy-policy']['en']);
    $pll=get_option('polylang',[]);$pll['hide_default']=0;$pll['force_lang']=1;$pll['redirect_lang']=1;$pll['default_lang']='en';update_option('polylang',$pll);
    $forms=get_option('tkc_form_map',[]);
    foreach(['contact','volunteer','materials','financial','merchandise','partnership']as$type){foreach(['en','et']as$lang){
        if(empty($forms[$type][$lang])){$forms[$type][$lang]=tkcs_create_form($type,$lang,get_permalink($map['privacy-policy'][$lang]));update_option('tkc_form_map',$forms,false);}
    }}
    foreach($data as$key=>$langs){foreach($langs as$lang=>$page){
        $content=str_replace('@@ASSET@@',get_theme_file_uri('assets'),$page['content']);
        $content=preg_replace_callback('/@@PAGE:([a-z-]+)@@/',function($m)use($map,$lang){return isset($map[$m[1]][$lang])?esc_url(get_permalink($map[$m[1]][$lang])):home_url('/');},$content);
        $content=preg_replace_callback('/@@FORM:([a-z-]+):(en|et)@@/',function($m)use($forms){return '[contact-form-7 id="'.absint($forms[$m[1]][$m[2]]).'"]';},$content);
        wp_update_post(['ID'=>$map[$key][$lang],'post_content'=>$content]);update_post_meta($map[$key][$lang],'tkc_description',$page['description']??'');
    }}
    $labels=['home'=>['Home','Avaleht'],'activities'=>['Activities','Tegevused'],'about-us'=>['About us','Meist'],'get-involved'=>['Get involved','Löö kaasa'],'support-us'=>['Support us','Toeta meid'],'partnerships'=>['Partnerships','Koostöö'],'resources'=>['Resources','Ressursid'],'contact'=>['Contact','Kontakt']];
    $locations=get_theme_mod('nav_menu_locations',[]);
    foreach(['en','et']as$lang){$name='TKC '.strtoupper($lang);$menu=wp_get_nav_menu_object($name);$mid=$menu?$menu->term_id:wp_create_nav_menu($name);if(is_wp_error($mid))continue;
        if(!wp_get_nav_menu_items($mid)){foreach($labels as$key=>$ls){wp_update_nav_menu_item($mid,0,['menu-item-title'=>$ls[$lang==='et'?1:0],'menu-item-object-id'=>$map[$key][$lang],'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish']);}}
        $locations['primary-'.$lang]=$mid;
    }set_theme_mod('nav_menu_locations',$locations);
    foreach(['dbem_rsvp_enabled'=>1,'dbem_bookings_anonymous'=>1,'dbem_bookings_registration_disable'=>1,'dbem_bookings_login_form'=>0,'dbem_bookings_approval'=>0,'dbem_bookings_double'=>0,'dbem_bookings_currency'=>'EUR','dbem_bookings_notify_admin'=>'info@thekindcircle.ee','dbem_mail_sender_name'=>'The Kind Circle MTÜ','dbem_gmap_is_active'=>0]as$k=>$v)update_option($k,$v);
    $nl=get_option('newsletter_main',[]);$nl['sender_email']='info@thekindcircle.ee';$nl['sender_name']='The Kind Circle MTÜ';$nl['reply_to']='info@thekindcircle.ee';$nl['page']=$map['newsletter-service']['en'];update_option('newsletter_main',$nl);update_option('newsletter_main_et',array_merge(get_option('newsletter_main_et',[]),['page'=>$map['newsletter-service']['et']]));
    $ns=get_option('newsletter_subscription',[]);$ns['noconfirmation']=0;$ns['optin_override']=0;$ns['welcome_disabled']=0;update_option('newsletter_subscription',$ns);
    $nf=get_option('newsletter_form',[]);$nf['privacy_status']=1;$nf['privacy_use_wp_url']=1;update_option('newsletter_form',$nf);
    // Turn the existing approved external-registration activity into one canonical event record.
    if(!get_option('tkc_initial_event')){
        $event=new EM_Event();$event->event_name='Tea, Books & Connections';$event->event_start_date='2026-11-07';$event->event_end_date='2026-11-07';$event->event_start_time='13:00:00';$event->event_end_time='15:00:00';$event->event_timezone='Europe/Tallinn';$event->event_rsvp=0;$event->post_content='<p>Tea culture, quiet reading and optional community activities at Chamber Teahouse, Marati tn 5/3, Tallinn.</p>';$event->force_status='publish';
        if($event->save()){$pid=$event->post_id;update_option('tkc_initial_event',$pid);update_post_meta($pid,'tkc_external',1);update_post_meta($pid,'tkc_venue','Chamber Teahouse, Marati tn 5/3, Tallinn');update_post_meta($pid,'tkc_activity_language','Contact organiser / Küsi korraldajalt');update_post_meta($pid,'tkc_title_et','Tee, raamatud ja kohtumised');update_post_meta($pid,'tkc_pages',$map['tea-books-connections']);update_post_meta($pid,'tkc_image',tkcs_asset('tea'));}
    }
    foreach(['Coffee Chats','Reading Sessions','Language Exchange','Cultural Activities','Community Volunteering','Workshops']as$cat){if(taxonomy_exists('event-categories')&&!term_exists($cat,'event-categories'))wp_insert_term($cat,'event-categories');}
    // Preserve recoverability of the stock installer sample content.
    $sample=get_page_by_path('sample-page');if($sample&&$sample->post_title==='Sample Page')wp_trash_post($sample->ID);
    $hello=get_page_by_path('hello-world',OBJECT,'post');if($hello&&$hello->post_title==='Hello world!')wp_trash_post($hello->ID);
    update_option('default_comment_status','closed');update_option('default_ping_status','closed');update_option('timezone_string','Europe/Tallinn');
    update_option('tkc_setup_done',wp_date('c'));flush_rewrite_rules();wp_safe_redirect(admin_url('tools.php?page=tkc-website'));exit;
}
function tkcs_create_form($type,$lang,$privacy){
    $et=$lang==='et';$titles=['contact'=>['General enquiry','Üldine päring'],'volunteer'=>['Volunteer application','Vabatahtliku avaldus'],'materials'=>['Material donation','Materjalide annetus'],'financial'=>['Financial support enquiry','Rahalise toetuse päring'],'merchandise'=>['Merchandise enquiry','Meenete päring'],'partnership'=>['Partnership enquiry','Koostööpäring']];$title=$titles[$type][$et?1:0];
    $form=WPCF7_ContactForm::get_template(['title'=>'TKC '.$title.' ['.strtoupper($lang).']','locale'=>$et?'et':'en_GB']);
    $markup='<p><label>'.($et?'Sinu nimi':'Your name').' [text* your-name autocomplete:name maxlength:120]</label></p><p><label>'.esc_attr(tkcs_local_setting('newsletter_email')).' [email* your-email autocomplete:email maxlength:200]</label></p>';
    if($type==='volunteer')$markup.='<p><label>'.($et?'Huvipakkuv roll':'Role of interest').' [select* role include_blank "'.($et?'Kohaliku kogukonna kontaktisik':'Local community liaison').'" "'.($et?'Ürituste korraldus':'Event planning and delivery').'" "'.($et?'Sotsiaalmeedia ja kommunikatsioon':'Social media and communications').'" "'.($et?'Loovtöö ja materjalid':'Creative and materials support').'" "'.($et?'Muu idee':'Another idea').'"]</label></p>';
    if($type==='contact')$markup.='<p><label>'.($et?'Teema':'Topic').' [select* topic include_blank "'.($et?'Üldine küsimus':'General question').'" "'.($et?'Tegevused':'Activities').'" "'.($et?'Vabatahtlik tegevus':'Volunteering').'" "'.($et?'Koostöö':'Partnerships').'" "'.($et?'Annetused':'Donations').'" "'.($et?'Ressursi soovitus':'Resource suggestion').'"]</label></p>';
    if($type==='partnership')$markup.='<p><label>'.($et?'Organisatsioon (soovi korral)':'Organisation (optional)').' [text organisation maxlength:160]</label></p>';
    $markup.='<p><label>'.($et?'Sõnum':'Your message').' [textarea* your-message maxlength:5000]</label></p><div class="tkc-honey" aria-hidden="true"><label>Leave empty [text tkc-website tabindex:-1 autocomplete:off]</label></div>';
    $markup.='<p>[acceptance privacy] '.($et?'Olen lugenud ':'I have read the ').'<a href="'.esc_url($privacy).'">'.($et?'privaatsusteavet':'privacy notice').'</a>'.($et?' ja mõistan, kuidas minu päringut käsitletakse.':' and understand how my enquiry will be handled.').' [/acceptance]</p><p>[submit "'.($et?'Saada sõnum':'Send message').'"]</p>';
    $mail=['active'=>true,'subject'=>'[TKC] '.$title.' — [your-name]','sender'=>'The Kind Circle <info@thekindcircle.ee>','recipient'=>'info@thekindcircle.ee','body'=>"$title\nName: [your-name]\nEmail: [your-email]\n".($type==='volunteer'?"Role: [role]\n":'').($type==='contact'?"Topic: [topic]\n":'').($type==='partnership'?"Organisation: [organisation]\n":'')."\n[your-message]\n\nSent from [_post_url]",'additional_headers'=>'Reply-To: [your-email]','attachments'=>'','use_html'=>false,'exclude_blank'=>true];
    $reply=['active'=>true,'subject'=>$et?'The Kind Circle: saime sinu sõnumi':'The Kind Circle: we received your message','sender'=>'The Kind Circle <info@thekindcircle.ee>','recipient'=>'[your-email]','body'=>$et?"Tere [your-name],\n\nAitäh ühendust võtmast. Saime sinu sõnumi ja võtame vajaduse korral ühendust. See on kättesaamise kinnitus, mitte rolli, annetuse ega koostöö heakskiit.\n\nThe Kind Circle MTÜ\ninfo@thekindcircle.ee":"Hello [your-name],\n\nThank you for getting in touch. We received your message and will follow up as appropriate. This confirms receipt, rather than acceptance of a role, donation or partnership.\n\nThe Kind Circle MTÜ\ninfo@thekindcircle.ee",'additional_headers'=>'Reply-To: info@thekindcircle.ee','attachments'=>'','use_html'=>false,'exclude_blank'=>true];
    $messages=$form->prop('messages');$messages['mail_sent_ok']=$et?'Aitäh! Sinu sõnum on saadetud.':'Thank you! Your message has been sent.';$messages['validation_error']=$et?'Palun kontrolli märgitud välju.':'Please check the highlighted fields.';$messages['mail_sent_ng']=$et?'Sõnumi saatmine ei õnnestunud. Palun kirjuta info@thekindcircle.ee.':'The message could not be sent. Please email info@thekindcircle.ee.';
    $form->set_properties(['form'=>$markup,'mail'=>$mail,'mail_2'=>$reply,'messages'=>$messages,'additional_settings'=>"flamingo_email: \"[your-email]\"\nflamingo_name: \"[your-name]\"\nflamingo_subject: \"$title\""]);
    $form->save();return $form->id();
}
// Lightweight local honeypot, without sending visitor data to an external anti-spam service.
add_filter('wpcf7_spam',function($spam){$s=WPCF7_Submission::get_instance();return $spam||($s&&trim((string)$s->get_posted_data('tkc-website'))!=='');});
add_shortcode('tkc_newsletter',function(){
    if(!shortcode_exists('newsletter_form'))return '<p><a href="mailto:info@thekindcircle.ee">info@thekindcircle.ee</a></p>';
    $et=function_exists('tkc_lang')&&tkc_lang()==='et';$privacy=function_exists('tkc_page_url')?tkc_page_url('privacy-policy'):get_privacy_policy_url();
    return do_shortcode('[newsletter_form][newsletter_field name="email" label="'.esc_attr(tkcs_local_setting('newsletter_email')).'" placeholder="you@example.com"][newsletter_field name="privacy" label="'.esc_attr(tkcs_local_setting('newsletter_consent')).'" url="'.esc_url($privacy).'"][newsletter_field name="submit" button_label="'.esc_attr(tkcs_local_setting('newsletter_submit')).'"][/newsletter_form]');
});
function tkcs_spaces_remaining($event){
    $pid=(int)$event->post_id;
    if(!$event->event_rsvp||tkcs_event_has_ended($pid)||get_post_meta($pid,'tkc_external_url',true)||get_post_meta($pid,'tkc_external',true)||(isset($event->event_active_status)&&(int)$event->event_active_status===0))return '';
    $bookings=$event->get_bookings();$remaining=max(0,(int)$bookings->get_available_spaces());$total=max(0,(int)$bookings->get_spaces());
    if(!$bookings->is_open()&&$remaining>0)return '';
    $text=tkcs_local_setting('spaces_label').': '.number_format_i18n($remaining).($total?' / '.number_format_i18n($total):'');
    return '<p class="tkc-event-spaces">'.esc_html($text).'</p>';
}
add_shortcode('tkc_events',function($attrs){
    if(!class_exists('EM_Events'))return '';$a=shortcode_atts(['scope'=>'future','limit'=>12],$attrs);$events=EM_Events::get(['scope'=>$a['scope']==='past'?'past':'future','limit'=>min(50,max(1,absint($a['limit']))),'orderby'=>'event_start_date','order'=>'ASC']);$et=function_exists('tkc_lang')&&tkc_lang()==='et';
    if(!$events)return '<p>'.esc_html(tkcs_local_setting('future_empty')).'</p>';
    $out='<div class="tkc-events-list">';foreach($events as$event){$pid=$event->post_id;$pages=get_post_meta($pid,'tkc_pages',true);$lang=$et?'et':'en';$url=!empty($pages[$lang])?get_permalink($pages[$lang]):get_permalink($pid);$title=$et?(get_post_meta($pid,'tkc_title_et',true)?:$event->event_name):$event->event_name;$img=get_the_post_thumbnail_url($pid,'large')?:get_post_meta($pid,'tkc_image',true);$external=get_post_meta($pid,'tkc_external',true);$venue=get_post_meta($pid,'tkc_venue',true);if(!$venue&&$event->location_id)$venue=$event->get_location()->location_name;
        if($external){$status=$et?'Registreerimine partneri kaudu':'Partner registration';}elseif($event->event_rsvp){$b=$event->get_bookings();$status=$b->get_available_spaces()<=0?($et?'Kohad täis':'Full'):($b->is_open()?($et?'Registreerimine avatud':'Registration open'):($et?'Registreerimine suletud':'Registration closed'));}else{$status=$et?'Tegevus':'Activity';}
        $out.='<a class="tkc-event-card" href="'.esc_url($url).'">'.($img?'<img src="'.esc_url($img).'" alt="" loading="lazy">':'').'<div class="tkc-card-body"><span class="tkc-event-status">'.esc_html($status).'</span><h3>'.esc_html($title).'</h3>'.tkcs_spaces_remaining($event).'<p class="tkc-event-meta">'.esc_html($event->event_start_date.' · '.substr($event->event_start_time,0,5).'–'.substr($event->event_end_time,0,5)).'<br>'.esc_html($venue).'</p><p class="tkc-event-meta">'.esc_html($et?'Tegevuse keel: ':'Activity language: ').esc_html(get_post_meta($pid,'tkc_activity_language',true)?:($et?'Küsi korraldajalt':'Contact organiser')).'</p><span>'.esc_html($et?'Vaata tegevust':'View activity').'</span></div></a>';
    }return $out.'</div>';
});
add_shortcode('tkc_past_activities','tkcs_past_activities');
// Maintain bilingual presentation pages while keeping one canonical booking record.
add_action('add_meta_boxes',function(){add_meta_box('tkc-event-language','TKC bilingual event details','tkcs_event_fields','event','normal');add_meta_box('tkc-followup','TKC follow-up','tkcs_followup','flamingo_inbound','side');});
function tkcs_event_fields($post){wp_nonce_field('tkcs_event','tkcs_event_nonce');foreach(['tkc_title_et'=>'Estonian title','tkc_venue'=>'Venue / address','tkc_activity_language'=>'Activity language (not website language)','tkc_external_url'=>'External registration URL (leave blank for on-site booking)']as$key=>$label){echo '<p><label>'.esc_html($label).'<br><input style="width:100%" name="'.esc_attr($key).'" value="'.esc_attr(get_post_meta($post->ID,$key,true)).'"></label></p>';}echo '<p><strong>Estonian description</strong></p>';wp_editor(get_post_meta($post->ID,'tkc_content_et',true),'tkc_content_et',['textarea_name'=>'tkc_content_et','textarea_rows'=>10,'media_buttons'=>true]);echo '<p>English and Estonian pages use the same event booking record and capacity. Configure tickets and booking availability in Events Manager.</p>';}
add_action('save_post_event',function($id){if(empty($_POST['tkcs_event_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tkcs_event_nonce'])),'tkcs_event')||!current_user_can('edit_post',$id)||wp_is_post_revision($id))return;foreach(['tkc_title_et','tkc_venue','tkc_activity_language','tkc_external_url','tkc_content_et']as$key){$raw=isset($_POST[$key])?wp_unslash($_POST[$key]):'';$value=$key==='tkc_external_url'?esc_url_raw($raw):($key==='tkc_content_et'?wp_kses_post($raw):sanitize_text_field($raw));update_post_meta($id,$key,$value);}update_post_meta($id,'tkc_external',!empty($_POST['tkc_external_url']));},20);
add_filter('em_event_save',function($result,$event){if($result&&get_option('tkc_setup_done'))tkcs_sync_event($event);return $result;},50,2);
// Event trash/unpublish actions do not always run Events Manager's save filter.
add_action('transition_post_status',function($new,$old,$post){
    if($post->post_type!=='event'||$new===$old)return;
    $pages=get_post_meta($post->ID,'tkc_pages',true);
    if(!is_array($pages))return;
    foreach($pages as$page_id){
        if(get_post_type($page_id)!=='page'||get_post_status($page_id)==='trash')continue;
        wp_update_post(['ID'=>(int)$page_id,'post_status'=>$new==='publish'?'publish':'draft']);
    }
},20,3);
function tkcs_sync_event($event){
    if(!function_exists('pll_set_post_language')||!$event->post_id)return;$pid=$event->post_id;$pages=get_post_meta($pid,'tkc_pages',true);$pages=is_array($pages)?$pages:[];$map=get_option('tkc_page_map',[]);
    foreach(['en','et']as$lang){
        $title=$lang==='et'?(get_post_meta($pid,'tkc_title_et',true)?:get_the_title($pid)):get_the_title($pid);
        $content='<!-- wp:shortcode -->[tkc_event_detail id="'.absint($pid).'"]<!-- /wp:shortcode -->';
        $post=['post_title'=>$title,'post_type'=>'page','post_status'=>get_post_status($pid)==='publish'?'publish':'draft','post_content'=>$content];
        $old=!empty($pages[$lang])?get_post($pages[$lang]):null;
        if($old&&$old->post_type==='page'){
            if($old->post_status==='trash')continue;
            $post['ID']=$old->ID;
            // Preserve original content before replacing legacy/static page bodies.
            if($old->post_content!==$content&&!get_post_meta($old->ID,'_tkc_before_events_source',true))update_post_meta($old->ID,'_tkc_before_events_source',['title'=>$old->post_title,'content'=>$old->post_content,'parent'=>$old->post_parent,'slug'=>$old->post_name,'status'=>$old->post_status,'saved_at'=>current_time('mysql')]);
            // Do not change an existing page's parent or slug: public URLs stay stable.
        }else{$post['post_parent']=$map['activities'][$lang]??0;}
        $id=wp_insert_post($post,true);
        if(!is_wp_error($id)){$pages[$lang]=$id;pll_set_post_language($id,$lang);update_post_meta($id,'_tkc_event_source',$pid);}
    }
    if(count($pages)===2)pll_save_post_translations($pages);update_post_meta($pid,'tkc_pages',$pages);
}
add_shortcode('tkc_event_detail',function($attrs){
    $pid=absint($attrs['id']??0);if(!$pid||get_post_status($pid)!=='publish'||!function_exists('em_get_event'))return '';
    $post=get_post($pid);$event=em_get_event($pid,'post_id');$et=function_exists('tkc_lang')&&tkc_lang()==='et';
    $title=$et?(get_post_meta($pid,'tkc_title_et',true)?:$post->post_title):$post->post_title;
    $body=$et?(get_post_meta($pid,'tkc_content_et',true)?:$post->post_content):$post->post_content;
    $date=tkcs_event_date_label($pid);if(get_post_meta($pid,'tkc_date_precision',true)!=='month'&&!in_array(get_post_meta($pid,'tkc_time_precision',true),['start','date'],true)&&!$event->event_all_day)$date.=' · '.$event->output_times();
    $out='<section class="tkc-page-intro tkc-section"><h1>'.esc_html($title).'</h1><p>'.esc_html($date).'</p><p>'.esc_html(get_post_meta($pid,'tkc_venue',true)).'</p><p>'.esc_html(get_post_meta($pid,'tkc_activity_language',true)).'</p></section><div class="tkc-body">';
    $image=get_the_post_thumbnail_url($pid,'large')?:get_post_meta($pid,'tkc_image',true);
    $out.=tkcs_spaces_remaining($event);
    if($image)$out.='<figure class="wp-block-image"><img src="'.esc_url($image).'" alt="'.esc_attr($title).'" loading="lazy"></figure>';
    $out.=tkcs_event_description($body,$pid);$url=get_post_meta($pid,'tkc_external_url',true);
    if(tkcs_event_has_ended($pid))$out.='<p class="tkc-note">'.esc_html(tkcs_local_setting('ended')).'</p>';
    elseif($url)$out.='<a class="tkc-button" href="'.esc_url($url).'">'.esc_html($et?'Registreeru partneri lehel':'Register on partner website').'</a>';
    elseif($event->event_rsvp)$out.=$event->output_booking_form();
    $out.='<p>'.esc_html(tkcs_local_setting('questions')).'<a href="mailto:'.esc_attr(tkcs_public_email()).'">'.esc_html(tkcs_public_email()).'</a></p></div>';return $out;
});
add_action('admin_post_tkcs_events_source',function(){
    if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcs_events_source');
    if(!class_exists('EM_Event'))wp_die('Activate Events Manager first.');
    $events=get_posts(['post_type'=>'event','post_status'=>['publish','draft','pending','future','private'],'numberposts'=>-1,'suppress_filters'=>true]);
    foreach($events as$event){if(get_post_meta($event->ID,'tkc_pages',true))tkcs_sync_event(new EM_Event($event->ID,'post_id'));}
    update_option('tkc_events_source_migrated',current_time('mysql'),false);
    wp_safe_redirect(admin_url('tools.php?page=tkc-website&event_source=1'));exit;
});
add_action('add_meta_boxes_page',function($post){if(get_post_meta($post->ID,'_tkc_event_source',true))add_meta_box('tkc-event-source','Event content is managed in Events',function($page){$id=(int)get_post_meta($page->ID,'_tkc_event_source',true);echo '<p>This page displays live event content. Edit the event instead of replacing the shortcode here.</p><p><a class="button button-primary" href="'.esc_url(get_edit_post_link($id)).'">Edit event content</a></p>';},'page','side','high');});
function tkcs_followup($post){wp_nonce_field('tkcs_followup','tkcs_followup_nonce');$status=get_post_meta($post->ID,'tkc_followup_status',true);echo '<label>Status <select name="tkc_followup_status">';foreach(['new'=>'New','reviewing'=>'Reviewing','contacted'=>'Contacted','closed'=>'Closed']as$key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($status?:'new',$key,false).'>'.esc_html($label).'</option>';echo '</select></label><p>Save the message to update follow-up status.</p>';}
add_action('save_post_flamingo_inbound',function($id){if(empty($_POST['tkcs_followup_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tkcs_followup_nonce'])),'tkcs_followup')||!current_user_can('edit_post',$id))return;$value=sanitize_key(wp_unslash($_POST['tkc_followup_status']??'new'));if(in_array($value,['new','reviewing','contacted','closed'],true))update_post_meta($id,'tkc_followup_status',$value);});

function tkcs_asset($name,$size='large'){
    $assets=get_option('tkc_asset_map',[]);if(!empty($assets[$name])){$url=wp_get_attachment_image_url($assets[$name],$size);if($url)return $url;}
    $ext=in_array($name,['logo','kindy','dumplings'],true)?'png':'jpg';return get_theme_file_uri('assets/'.$name.'.'.$ext);
}
add_action('admin_post_tkcs_improve',function(){
    if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('tkcs_improve');
    $opts=get_option('polylang',[]);$opts['redirect_lang']=1;$opts['hide_default']=0;update_option('polylang',$opts);if(function_exists('PLL')){PLL()->options['redirect_lang']=true;PLL()->options['hide_default']=false;PLL()->model->clean_languages_cache();}
    $menus=[];foreach(['en','et']as$lang){$menu=wp_get_nav_menu_object('TKC '.strtoupper($lang));if($menu){$menus[$lang]=$menu->term_id;if(function_exists('pll_set_term_language'))pll_set_term_language($menu->term_id,$lang);}}
    if(count($menus)===2&&function_exists('pll_save_term_translations'))pll_save_term_translations($menus);update_option('tkc_menu_map',$menus,false);
    require_once ABSPATH.'wp-admin/includes/image.php';$assets=get_option('tkc_asset_map',[]);$replacements=[];
    foreach(['logo','kindy','coffee-group','hiking','tea','dumplings']as$name){
        $ext=in_array($name,['logo','kindy','dumplings'],true)?'png':'jpg';$file=get_theme_file_path('assets/'.$name.'.'.$ext);if(!is_readable($file))continue;
        if(empty($assets[$name])){$upload=wp_upload_bits('tkc-'.$name.'.'.$ext,null,file_get_contents($file));if(!empty($upload['error']))continue;$mime=wp_check_filetype($upload['file']);$id=wp_insert_attachment(['post_title'=>'TKC '.ucwords(str_replace('-',' ',$name)),'post_mime_type'=>$mime['type'],'post_status'=>'inherit'],$upload['file']);if(!is_wp_error($id)){wp_update_attachment_metadata($id,wp_generate_attachment_metadata($id,$upload['file']));$assets[$name]=$id;update_option('tkc_asset_map',$assets,false);}}
        if(!empty($assets[$name])){$url=wp_get_attachment_image_url($assets[$name],'large');if($url)$replacements[get_theme_file_uri('assets/'.$name.'.'.$ext)]=$url;}
    }
    foreach(get_option('tkc_page_map',[])as$langs){foreach($langs as$id){$post=get_post($id);if($post){$content=strtr($post->post_content,$replacements);if($content!==$post->post_content)wp_update_post(['ID'=>$id,'post_content'=>$content]);}}}
    if(!empty($assets['logo'])){set_theme_mod('custom_logo',$assets['logo']);update_option('site_icon',$assets['logo']);}
    $event=get_option('tkc_initial_event');if($event){update_post_meta($event,'tkc_image',tkcs_asset('tea'));update_post_meta($event,'tkc_activity_language','');}
    foreach(get_option('tkc_form_map',[])as$type=>$langs){if(empty($langs['et']))continue;$form=WPCF7_ContactForm::get_instance($langs['et']);if(!$form)continue;$messages=$form->prop('messages');$messages=array_merge($messages,['invalid_required'=>'Palun täida see väli.','invalid_email'=>'Palun sisesta kehtiv e-posti aadress.','invalid_too_long'=>'Sisestatud tekst on liiga pikk.','accept_terms'=>'Palun kinnita enne saatmist, et oled privaatsusteabega tutvunud.']);$markup=str_replace('Leave empty','Jäta tühjaks',$form->prop('form'));$markup=str_replace('include_blank','first_as_label "Vali sobiv variant"',$markup);$form->set_properties(['messages'=>$messages,'form'=>$markup]);$form->save();}
    tkcs_localise_services();flush_rewrite_rules();wp_safe_redirect(admin_url('tools.php?page=tkc-website&improved=1'));exit;
});
function tkcs_status_table(){
    $posts=get_posts(['post_type'=>'flamingo_inbound','post_status'=>'publish','numberposts'=>20,'suppress_filters'=>true]);if(!$posts)return;
    echo '<h2>Recent enquiries and applications</h2><table class="widefat striped"><thead><tr><th>Received</th><th>Message</th><th>Follow-up</th></tr></thead><tbody>';
    foreach($posts as$post){$status=get_post_meta($post->ID,'tkc_followup_status',true)?:'new';echo '<tr><td>'.esc_html(get_the_date('Y-m-d H:i',$post)).'</td><td><a href="'.esc_url(admin_url('admin.php?page=flamingo_inbound&action=edit&post='.$post->ID)).'">'.esc_html($post->post_title).'</a></td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('tkcs_status_'.$post->ID);echo '<input type="hidden" name="action" value="tkcs_status"><input type="hidden" name="message_id" value="'.absint($post->ID).'"><select name="status">';foreach(['new'=>'New','reviewing'=>'Reviewing','contacted'=>'Contacted','closed'=>'Closed']as$key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html($label).'</option>';echo '</select> <button class="button">Save</button></form></td></tr>';}
    echo '</tbody></table>';
}
add_action('admin_post_tkcs_status',function(){if(!current_user_can('manage_options'))wp_die('Not authorised.');$id=absint($_POST['message_id']??0);check_admin_referer('tkcs_status_'.$id);$status=sanitize_key(wp_unslash($_POST['status']??''));if(get_post_type($id)==='flamingo_inbound'&&in_array($status,['new','reviewing','contacted','closed'],true))update_post_meta($id,'tkc_followup_status',$status);wp_safe_redirect(admin_url('tools.php?page=tkc-website'));exit;});
add_action('save_post_event',function($id){if(current_user_can('edit_post',$id)&&!wp_is_post_revision($id))add_action('shutdown',function()use($id){if(class_exists('EM_Event')&&get_option('tkc_setup_done'))tkcs_sync_event(new EM_Event($id,'post_id'));});},100);
add_action('template_redirect',function(){if(is_singular('event')){$pages=get_post_meta(get_queried_object_id(),'tkc_pages',true);$lang=function_exists('pll_current_language')?(pll_current_language('slug')?:'en'):'en';if(!empty($pages[$lang])&&get_post_status($pages[$lang])==='publish'){wp_safe_redirect(get_permalink($pages[$lang]),302);exit;}}});
add_action('wp_enqueue_scripts',function(){if(function_exists('tkc_page_url')){wp_enqueue_script('tkc-booking-confirmation',plugins_url('booking-confirmation.js',__FILE__),[],'1.0.0',true);wp_add_inline_script('tkc-booking-confirmation','window.tkcConfirmationUrl='.wp_json_encode(tkc_page_url('registration-confirmation')).';','before');}});

function tkcs_localise_services(){
    update_option('dbem_mail_sender_address','info@thekindcircle.ee');
    $pages=get_option('tkc_page_map',[]);foreach(['en','et']as$lang){if(!empty($pages['newsletter-service'][$lang])){update_option('newsletter_main_'.$lang,array_merge(get_option('newsletter_main_'.$lang,[]),['page'=>$pages['newsletter-service'][$lang]]));}}
    $main=get_option('newsletter_main',[]);$main['track']=0;update_option('newsletter_main',$main);
    $form=get_option('newsletter_form_et',[]);$form['subscribe']='Liitu uudiskirjaga';$form['email']='E-posti aadress';update_option('newsletter_form_et',$form);
    $sub=get_option('newsletter_subscription_et',[]);$sub=array_merge($sub,[
        'confirmation_text'=>'<p>Kinnituskiri on teel sinu e-postkasti. Palun ava selles olev link ja kontrolli vajaduse korral rämpsposti kausta.</p>',
        'confirmation_subject'=>'Palun kinnita TKC uudiskirjaga liitumine',
        'confirmation_message'=>'<p>TKC uudiskirjaga liitumise kinnitamiseks <a href="{subscription_confirm_url}">vajuta siia</a>.</p><p>Kui sa ei soovinud liituda, võid seda kirja eirata.</p>',
        'confirmation_message_plain_text'=>'Kinnita TKC uudiskirjaga liitumine: {subscription_confirm_url}',
        'confirmed_text'=>'<p>Sinu liitumine on kinnitatud. Tere tulemast The Kind Circle’i!</p>',
        'confirmed_subject'=>'Tere tulemast TKC uudiskirja lugejaks',
        'confirmed_message'=>'<p>Aitäh liitumast! Saad uudiseid meie tegevuste kohta.</p><p><a href="{unsubscription_url}">Loobu uudiskirjast</a></p>',
        'error_text'=>'<p>See aadress on juba liitunud. Küsimuste korral kirjuta info@thekindcircle.ee.</p>'
    ]);update_option('newsletter_subscription_et',$sub);
    $un=get_option('newsletter_unsubscription_et',[]);$un=array_merge($un,[
        'unsubscribe_text'=>'<p>Palun kinnita, et soovid uudiskirjast loobuda.</p><p>[newsletter_unsubscribe_button label="Kinnita loobumine" /]</p>',
        'unsubscribed_text'=>'<p>Oled uudiskirjast loobunud. Soovi korral saad hiljem uuesti liituda.</p>',
        'error_text'=>'<p>Tellimust ei leitud. Rohkem midagi tegema ei pea.</p>',
        'reactivated_text'=>'<p>Sinu uudiskirjaga liitumine on taastatud.</p>'
    ]);update_option('newsletter_unsubscription_et',$un);
}
function tkcs_et(){return !is_admin()&&function_exists('pll_current_language')&&pll_current_language('slug')==='et';}
add_filter('gettext',function($translated,$text,$domain){
    if($domain!=='events-manager'||!tkcs_et())return $translated;
    $map=['Tickets'=>'Osalemiskohad','Registration Information'=>'Registreerimise andmed','Booking Summary'=>'Registreerimise kokkuvõte','Name'=>'Nimi','Phone'=>'Telefon','E-mail'=>'E-post','Email'=>'E-post','Comment'=>'Kommentaar','Comments'=>'Kommentaarid','Submit Booking'=>'Registreeru','Total Price'=>'Kokku','Quantity'=>'Kogus','Ticket'=>'Osalemiskoht','Price'=>'Hind','Standard Ticket'=>'Tavakoht','This event is fully booked.'=>'Kõik kohad on täis.','Bookings are closed for this event.'=>'Sellele tegevusele registreerimine on lõppenud.','I consent to my submitted data being collected and stored as outlined by the site %s.'=>'Nõustun esitatud andmete kogumise ja säilitamisega vastavalt veebilehe teabele: %s.'];return $map[$text]??$translated;
},20,3);
foreach([
 'dbem_bookings_header_reg_info'=>'Registreerimise andmed','dbem_bookings_header_tickets'=>'Osalemiskohad','dbem_bookings_header_summary'=>'Registreerimise kokkuvõte','dbem_bookings_submit_button'=>'Registreeru','dbem_bookings_submit_button_processing'=>'Töötleme ...','dbem_bookings_form_msg_full'=>'Kõik kohad on täis.','dbem_bookings_form_msg_closed'=>'Sellele tegevusele registreerimine on lõppenud.','dbem_bookings_form_msg_cancelled'=>'See tegevus on tühistatud.','dbem_data_privacy_consent_text'=>'Nõustun esitatud andmete kogumise ja säilitamisega vastavalt veebilehe teabele: %s.'
]as$key=>$text){add_filter('pre_option_'.$key,function($pre)use($text){return tkcs_et()?$text:$pre;});}
