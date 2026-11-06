<?php
defined('ABSPATH') || exit;
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('editor-styles');
    add_editor_style('style.css');
    register_nav_menus(['primary-en'=>'English navigation','primary-et'=>'Eesti navigation']);
});
add_action('wp_enqueue_scripts', function () { wp_enqueue_style('tkc-community',get_stylesheet_uri(),[],wp_get_theme()->get('Version')); });
function tkc_lang() { return function_exists('pll_current_language') ? (pll_current_language('slug') ?: 'en') : 'en'; }
function tkc_text($en,$et) { return tkc_lang()==='et' ? $et : $en; }
function tkc_page_url($key, $lang=null) {
    $map=get_option('tkc_page_map',[]); $lang=$lang ?: tkc_lang();
    return isset($map[$key][$lang]) ? get_permalink($map[$key][$lang]) : home_url('/');
}
function tkc_navigation() {
    $menus=get_option('tkc_menu_map',[]);$menu=$menus[tkc_lang()]??('TKC '.strtoupper(tkc_lang()));
    $items=wp_get_nav_menu_items($menu,['suppress_filters'=>true]);
    if(!$items)return;echo '<ul>';
    foreach($items as$item){$url=$item->object==='page'?get_permalink($item->object_id):$item->url;echo '<li'.(get_queried_object_id()==$item->object_id?' class="current-menu-item"':'').'><a href="'.esc_url($url).'">'.esc_html($item->title).'</a></li>';}
    echo '</ul>';
}
add_shortcode('tkc_header',function () {
    ob_start(); ?>
    <a class="tkc-skip" href="#main-content"><?php echo esc_html(tkc_text('Skip to content','Liigu sisu juurde')); ?></a>
    <header class="tkc-header"><div class="tkc-header-inner">
    <a class="tkc-brand" href="<?php echo esc_url(tkc_page_url('home')); ?>"><img src="<?php echo esc_url((get_theme_mod('custom_logo')?wp_get_attachment_image_url(get_theme_mod('custom_logo'),'thumbnail'):tkcs_asset('logo','thumbnail'))); ?>" width="60" height="60" alt=""><span><strong><?php echo esc_html(tkcs_setting('brand')); ?></strong><small><?php echo esc_html(tkcs_setting('tagline')); ?></small></span></a>
    <nav class="tkc-nav" aria-label="<?php echo esc_attr(tkc_text('Main navigation','Põhinavigatsioon')); ?>"><?php tkc_navigation(); ?></nav>
    <details class="tkc-mobile"><summary><?php echo esc_html(tkc_text('Menu','Menüü')); ?></summary><nav aria-label="<?php echo esc_attr(tkc_text('Mobile navigation','Mobiilinavigatsioon')); ?>"><?php tkc_navigation(); ?></nav></details>
    <nav class="tkc-lang" aria-label="<?php echo esc_attr(tkc_text('Language','Keel')); ?>">
    <?php foreach(['en'=>'EN','et'=>'ET'] as $lang=>$label) { $translated=function_exists('pll_get_post') ? pll_get_post(get_queried_object_id(),$lang) : 0; $url=$translated ? get_permalink($translated) : tkc_page_url('home',$lang); echo '<a href="'.esc_url($url).'" lang="'.esc_attr($lang).'" hreflang="'.esc_attr($lang).'"'.(tkc_lang()===$lang?' aria-current="true"':'').'>'.esc_html($label).'</a>'; } ?>
    </nav></div></header>
    <?php return ob_get_clean();
});
add_shortcode('tkc_footer',function () {
    $lang=tkc_lang();ob_start(); ?>
    <footer class="tkc-footer"><div class="tkc-footer-top"><div><h2><?php echo esc_html(tkcs_setting('org_name')); ?></h2><p><?php echo esc_html(tkcs_local_setting('footer_intro')); ?></p><a href="mailto:<?php echo esc_attr(tkcs_public_email()); ?>"><?php echo esc_html(tkcs_public_email()); ?></a><?php echo tkcs_social_icons(); ?><p><?php echo esc_html(tkcs_setting('location')); ?><br><?php echo esc_html(tkc_text('Registry code','Registrikood')); ?>: <?php echo esc_html(tkcs_setting('registry')); ?></p></div>
    <div><h2><?php echo esc_html(tkcs_local_setting('footer_first')); ?></h2><?php tkcs_footer_menu(1,$lang); ?></div>
    <div><h2><?php echo esc_html(tkcs_local_setting('footer_second')); ?></h2><?php tkcs_footer_menu(2,$lang);echo do_shortcode('[tkc_newsletter]'); ?></div></div>
    <div class="tkc-footer-bottom"><span>© <?php echo esc_html(wp_date('Y').' '.tkcs_setting('org_name')); ?></span><span><a href="<?php echo esc_url(tkc_page_url('privacy-policy')); ?>"><?php echo esc_html(get_the_title(url_to_postid(tkc_page_url('privacy-policy')))); ?></a> &nbsp; / &nbsp; <a href="<?php echo esc_url(tkc_page_url('accessibility')); ?>"><?php echo esc_html(get_the_title(url_to_postid(tkc_page_url('accessibility')))); ?></a></span><span><?php echo esc_html(tkcs_setting('footer_tagline')); ?></span></div></footer>
    <?php return ob_get_clean();
});
add_action('wp_head',function () {
    if(is_singular()) { $description=get_post_meta(get_queried_object_id(),'tkc_description',true); if($description) echo '<meta name="description" content="'.esc_attr($description).'">' . "\n"; }
});
add_filter('wp_robots',function($robots){ if(is_page()){ $key=get_post_meta(get_queried_object_id(),'tkc_key',true);if(strpos($key,'confirmation')!==false){$robots['noindex']=true;$robots['nofollow']=true;}} return $robots; });
