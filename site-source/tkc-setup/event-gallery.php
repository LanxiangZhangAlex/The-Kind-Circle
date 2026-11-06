<?php
/** Native classic-editor galleries, rendered as readable images with a keyboard-accessible lightbox. */
defined('ABSPATH') || exit;
function tkcs_event_gallery($attrs,$post_id){
 $attrs=is_array($attrs)?$attrs:[];$ids=wp_parse_id_list($attrs['ids']??($attrs['include']??''));
 if(!$ids)$ids=get_posts(['post_type'=>'attachment','post_status'=>'inherit','post_parent'=>$post_id,'post_mime_type'=>'image','numberposts'=>-1,'orderby'=>'menu_order ID','order'=>'ASC','fields'=>'ids','exclude'=>wp_parse_id_list($attrs['exclude']??'')]);
 $items=[];foreach($ids as$id){if(!wp_attachment_is_image($id))continue;$url=wp_get_attachment_image_url($id,'full');$image=wp_get_attachment_image($id,'large',false,['loading'=>'lazy','decoding'=>'async']);if(!$url||!$image)continue;$caption=wp_get_attachment_caption($id);$items[]='<figure class="tkc-gallery-item"><a class="tkc-gallery-link" href="'.esc_url($url).'" aria-label="'.esc_attr(function_exists('tkc_lang')&&tkc_lang()==='et'?'Ava suurem pilt':'Open larger image').'">'.$image.'</a>'.($caption?'<figcaption>'.esc_html($caption).'</figcaption>':'').'</figure>';}
 if(!$items)return '';$columns=min(3,max(1,(int)($attrs['columns']??2)));$columns=min($columns,count($items));return '<div class="tkc-event-gallery" data-tkc-gallery style="--tkc-gallery-columns:'.$columns.'">'.implode('',$items).'</div>';
}
function tkcs_event_description($body,$post_id){
 $html=shortcode_unautop(wpautop(wp_kses_post($body)));
 // Only enable the native gallery shortcode here; leave other shortcodes unchanged.
 return preg_replace_callback('/'.get_shortcode_regex(['gallery']).'/s',function($m)use($post_id){if($m[1]==='['&&$m[6]===']')return substr($m[0],1,-1);return tkcs_event_gallery(shortcode_parse_atts($m[3]),$post_id);},$html);
}
add_action('wp_enqueue_scripts',function(){
 if(!is_singular())return;$id=get_queried_object_id();if(get_post_type($id)!=='event'&&!get_post_meta($id,'_tkc_event_source',true))return;
 wp_enqueue_style('tkc-event-gallery',plugins_url('event-gallery.css',__FILE__),[],'1.6.0');wp_enqueue_script('tkc-event-gallery',plugins_url('event-gallery.js',__FILE__),[],'1.6.0',true);
});
add_action('add_meta_boxes_event',function(){add_meta_box('tkc-event-gallery-help','Activity gallery / 活动相册',function(){echo '<p><strong>Add Media → Create Gallery</strong>，选择多张照片后点击 Create a new gallery，拖动排序，再 Insert gallery。推荐 1–2 列，最多显示 3 列；手机自动为 1 列。</p><p>前台会使用大尺寸图片，点击可放大、左右切换；支持方向键、Esc 和手机滑动。图片留白为白色。</p><p>以后点击正文中的相册，再点铅笔即可添加、删除或排序。英文与爱沙尼亚语正文可各自插入相册；不要去 Pages 修改活动展示页。</p>';},'event','side');});
