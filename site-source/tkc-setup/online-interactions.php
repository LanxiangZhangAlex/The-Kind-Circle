<?php
/** Anonymous reactions and native WordPress comments for public online submissions. */
defined('ABSPATH') || exit;

function tkci_text($en, $et, $lang = 'en') { return $lang === 'et' ? $et : $en; }
function tkci_table() { global $wpdb; return $wpdb->prefix . 'tkc_online_likes'; }
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || get_option('tkci_schema') === '1') return;
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = tkci_table();
    dbDelta("CREATE TABLE $table (
        kind varchar(8) NOT NULL,
        entry_id bigint(20) unsigned NOT NULL,
        visitor_hash char(64) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (kind,entry_id,visitor_hash)
    ) " . $wpdb->get_charset_collate() . ';');
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) update_option('tkci_schema', '1', false);
});

/** All AJAX operations recheck the publication state, never merely trust an entry ID. */
function tkci_target($kind, $entry) {
    if (get_post_type($entry) !== 'flamingo_inbound' || get_post_status($entry) !== 'publish') return false;
    if ($kind === 'city') {
        $comment = get_comment((int)get_post_meta($entry, '_tkcq_comment_id', true));
        if (!$comment || $comment->comment_approved !== '1' || tkcq_comment_entry($comment) !== (int)$entry) return false;
        $post = (int)$comment->comment_post_ID;
        if (get_post_type($post) !== 'tkc_quest' || get_post_status($post) !== 'publish' || !tkcq_photo_path($entry)) return false;
        return ['post' => $post, 'parent' => (int)$comment->comment_ID];
    }
    if ($kind === 'sky') {
        $post = (int)get_post_meta($entry, '_tkcsky_project', true);
        if (get_post_type($post) !== 'tkc_online' || get_post_status($post) !== 'publish'
            || get_post_meta($entry, '_tkcsky_review', true) !== 'approved'
            || get_post_meta($entry, '_tkcsky_consent', true) !== 'public-v1'
            || (int)get_post_meta($entry, '_tkcsky_published_at', true) <= 0 || !tkcsky_photo_path($entry)) return false;
        return ['post' => $post, 'parent' => 0];
    }
    return false;
}

function tkci_visitor($create = false) {
    $value = isset($_COOKIE['tkc_online_visitor']) ? (string)wp_unslash($_COOKIE['tkc_online_visitor']) : '';
    $parts = explode('.', $value);
    $token = count($parts) === 2 && preg_match('/^[a-f0-9]{48}$/D', $parts[0])
        && hash_equals(hash_hmac('sha256', $parts[0], wp_salt('auth')), $parts[1]) ? $parts[0] : '';
    if (!$token && $create) {
        $token = bin2hex(random_bytes(24));
        $value = $token . '.' . hash_hmac('sha256', $token, wp_salt('auth'));
        setcookie('tkc_online_visitor', $value, ['expires' => time() + 180 * DAY_IN_SECONDS,
            'path' => wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
    }
    return $token ? hash_hmac('sha256', $token, wp_salt('nonce')) : '';
}
function tkci_likes($kind, $entry, $visitor = '') {
    if (get_option('tkci_schema') !== '1') return ['count' => 0, 'liked' => false];
    global $wpdb; $table = tkci_table();
    return ['count' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE kind=%s AND entry_id=%d", $kind, $entry)),
        'liked' => $visitor !== '' && (bool)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM $table WHERE kind=%s AND entry_id=%d AND visitor_hash=%s", $kind, $entry, $visitor))];
}
function tkci_comment_args($kind, $entry, $target) {
    return ['post_id' => $target['post'], 'parent' => $target['parent'], 'type' => 'tkc_online_reply',
        'status' => 'approve', 'meta_query' => ['relation' => 'AND',
            ['key' => '_tkci_kind', 'value' => $kind], ['key' => '_tkci_entry', 'value' => $entry, 'type' => 'NUMERIC']]];
}
function tkci_reply_count($kind, $entry, $target) { return (int)get_comments(array_merge(tkci_comment_args($kind, $entry, $target), ['count' => true])); }
function tkci_public_replies($kind, $entry, $target, $offset) {
    $rows = get_comments(array_merge(tkci_comment_args($kind, $entry, $target), ['number' => 10, 'offset' => $offset, 'orderby' => 'comment_ID', 'order' => 'DESC']));
    $out = [];
    foreach ($rows as $c) $out[] = ['id' => (int)$c->comment_ID, 'nickname' => $c->comment_author, 'comment' => $c->comment_content,
        'date' => wp_date('d.m.Y H:i', strtotime($c->comment_date_gmt . ' UTC'), new DateTimeZone('Europe/Tallinn'))];
    return $out;
}
function tkci_rate($action, $limit) {
    $key = 'tkci_rate_' . hash_hmac('sha256', $action . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? ''), wp_salt('auth'));
    $count = (int)get_transient($key);
    if ($count >= $limit) return false;
    set_transient($key, $count + 1, 5 * MINUTE_IN_SECONDS); return true;
}
function tkci_widget($kind, $entry) {
    $target = tkci_target($kind, $entry); if (!$target) return '';
    $lang = tkcq_et() ? 'et' : 'en'; $t = function ($en, $et) use ($lang) { return tkci_text($en, $et, $lang); };
    $state = tkci_likes($kind, $entry); $count = tkci_reply_count($kind, $entry, $target); $uid = $kind . '-' . (int)$entry;
    $out = '<div class="tkci-interactions" data-kind="' . esc_attr($kind) . '" data-entry="' . (int)$entry . '" data-lang="' . $lang
        . '" data-endpoint="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('tkci_public')) . '">';
    $out .= '<div class="tkci-actions"><button type="button" class="tkci-like" aria-pressed="false" aria-label="' . esc_attr($t('Like this submission', 'Märgi postitus meeldivaks')) . '" disabled>'
        . '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>'
        . '<span class="tkci-like-count">' . $state['count'] . '</span></button>'
        . '<a class="tkci-comment-toggle" href="#tkci-comments-' . $uid . '">' . esc_html($t('Comments', 'Kommentaarid')) . ' (<span class="tkci-comment-count">' . $count . '</span>)</a></div>';
    $out .= '<details class="tkci-discussion" id="tkci-comments-' . $uid . '"><summary>' . esc_html($t('Read or add a comment', 'Loe või lisa kommentaar')) . '</summary>'
        . '<div class="tkci-replies" aria-live="polite"></div><button class="tkci-more" type="button" hidden>' . esc_html($t('Load more comments', 'Laadi veel kommentaare')) . '</button>'
        . '<form class="tkci-reply-form"><h3>' . esc_html($t('Leave a comment', 'Lisa kommentaar')) . '</h3>'
        . '<p><label for="tkci-name-' . $uid . '">' . esc_html($t('Nickname', 'Hüüdnimi')) . ' *</label><input id="tkci-name-' . $uid . '" name="nickname" type="text" required maxlength="80" autocomplete="nickname"></p>'
        . '<p><label for="tkci-text-' . $uid . '">' . esc_html($t('Comment', 'Kommentaar')) . ' *</label><textarea id="tkci-text-' . $uid . '" name="comment" required maxlength="2000" rows="3"></textarea></p>'
        . '<div class="tkci-honey" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<p class="tkci-public-note">' . esc_html($t('Your nickname and comment will be public. No account is needed.', 'Sinu hüüdnimi ja kommentaar avaldatakse. Kontot pole vaja.')) . '</p>'
        . '<button class="tkci-submit" type="submit">' . esc_html($t('Post comment', 'Avalda kommentaar')) . '</button></form></details>'
        . '<p class="tkci-status" role="status"></p><noscript>' . esc_html($t('Enable JavaScript to like or comment.', 'Meeldimiste ja kommentaaride jaoks luba JavaScript.')) . '</noscript></div>';
    return $out;
}
add_action('wp_enqueue_scripts', function () {
    if (!is_page() || (!get_post_meta(get_queried_object_id(), '_tkcq_source', true) && !get_post_meta(get_queried_object_id(), '_tkcsky_source', true))) return;
    wp_enqueue_style('tkc-online-interactions', plugins_url('online-interactions.css', __FILE__), [], '1.8.0');
    wp_enqueue_script('tkc-online-interactions', plugins_url('online-interactions.js', __FILE__), [], '1.8.0', true);
});

function tkci_ajax() {
    nocache_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_send_json_error(['message' => 'Method not allowed.'], 405);
    foreach (['lang', 'nonce', 'kind', 'entry', 'operation', 'offset', 'liked', 'nickname', 'comment', 'website'] as $key) {
        if (isset($_POST[$key]) && !is_scalar($_POST[$key])) wp_send_json_error(['message' => 'Invalid field.'], 400);
    }
    $lang = ($_POST['lang'] ?? '') === 'et' ? 'et' : 'en';
    $t = function ($en, $et) use ($lang) { return tkci_text($en, $et, $lang); };
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? wp_parse_url($_SERVER['HTTP_ORIGIN']) : null;
    $home = wp_parse_url(home_url('/'));
    if ($origin && (strtolower($origin['host'] ?? '') !== strtolower($home['host'] ?? '') || ($origin['scheme'] ?? '') !== ($home['scheme'] ?? '') || ($origin['port'] ?? null) !== ($home['port'] ?? null))) wp_send_json_error(['message' => 'Invalid origin.'], 403);
    if (!check_ajax_referer('tkci_public', 'nonce', false)) wp_send_json_error(['message' => $t('Please refresh this page and try again.', 'Palun värskenda lehte ja proovi uuesti.')], 403);
    $kind = sanitize_key($_POST['kind'] ?? ''); $entry = absint($_POST['entry'] ?? 0); $target = tkci_target($kind, $entry);
    if (!$target) wp_send_json_error(['message' => $t('This submission is not available.', 'See postitus pole saadaval.')], 404);
    $op = sanitize_key($_POST['operation'] ?? '');
    if ($op === 'state' || $op === 'comments') {
        $data = tkci_likes($kind, $entry, tkci_visitor()); $data['comments'] = tkci_reply_count($kind, $entry, $target);
        if ($op === 'comments') {
            $offset = min(100000, absint($_POST['offset'] ?? 0));
            $data['replies'] = tkci_public_replies($kind, $entry, $target, $offset);
            $data['next'] = $offset + count($data['replies']); $data['more'] = $data['next'] < $data['comments'];
        }
        wp_send_json_success($data);
    }
    if ($op === 'like') {
        if (get_option('tkci_schema') !== '1') wp_send_json_error(['message' => $t('Likes are temporarily unavailable.', 'Meeldimised pole ajutiselt saadaval.')], 503);
        if (!tkci_rate('like', 90)) wp_send_json_error(['message' => $t('Please wait a few minutes and try again.', 'Palun oota mõni minut ja proovi uuesti.')], 429);
        $visitor = tkci_visitor(true); global $wpdb; $table = tkci_table(); $liked = ($_POST['liked'] ?? '') === '1';
        $result = $liked ? $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (kind,entry_id,visitor_hash,created_at) VALUES (%s,%d,%s,%s)", $kind, $entry, $visitor, current_time('mysql', true)))
            : $wpdb->delete($table, ['kind' => $kind, 'entry_id' => $entry, 'visitor_hash' => $visitor], ['%s', '%d', '%s']);
        if ($result === false) wp_send_json_error(['message' => $t('Could not save your like. Please try again.', 'Meeldimist ei saanud salvestada. Proovi uuesti.')], 500);
        wp_send_json_success(tkci_likes($kind, $entry, $visitor));
    }
    if ($op !== 'reply') wp_send_json_error(['message' => 'Unknown operation.'], 400);
    $nickname = sanitize_text_field(wp_unslash($_POST['nickname'] ?? '')); $body = sanitize_textarea_field(wp_unslash($_POST['comment'] ?? ''));
    if ($nickname === '' || $body === '' || mb_strlen($nickname) > 80 || mb_strlen($body) > 2000 || trim((string)($_POST['website'] ?? '')) !== '') wp_send_json_error(['message' => $t('Enter a nickname and a comment (up to 2,000 characters).', 'Sisesta hüüdnimi ja kommentaar (kuni 2000 märki).')], 400);
    if (!tkci_rate('reply', 8)) wp_send_json_error(['message' => $t('Too many comments. Please wait a few minutes.', 'Liiga palju kommentaare. Palun oota mõni minut.')], 429);
    $fingerprint = hash_hmac('sha256', $kind . '|' . $entry . '|' . $nickname . '|' . $body, wp_salt('nonce'));
    $existing = get_comments(['post_id' => $target['post'], 'type' => 'tkc_online_reply', 'status' => 'all', 'number' => 1, 'meta_key' => '_tkci_fingerprint', 'meta_value' => $fingerprint]);
    if ($existing) wp_send_json_success(['comments' => tkci_reply_count($kind, $entry, $target), 'message' => $t('This comment has already been submitted.', 'See kommentaar on juba saadetud.')]);
    $lock = 'tkci_reply_' . $fingerprint;
    if (!add_option($lock, time(), '', false)) {
        if ((int)get_option($lock) > time() - 120) wp_send_json_error(['message' => $t('Your comment is being saved. Please wait.', 'Kommentaari salvestatakse. Palun oota.')], 409);
        delete_option($lock);
        if (!add_option($lock, time(), '', false)) wp_send_json_error(['message' => 'Please try again.'], 409);
    }
    try {
        // No email, IP or browser-agent field is copied into this public comment.
        $approval = wp_check_comment_disallowed_list($nickname, '', '', $body, '', '') ? 'spam' : 1;
        $cid = wp_insert_comment(wp_slash(['comment_post_ID' => $target['post'], 'comment_parent' => $target['parent'],
            'comment_type' => 'tkc_online_reply', 'comment_author' => $nickname, 'comment_author_email' => '', 'comment_author_url' => '',
            'comment_author_IP' => '', 'comment_agent' => '', 'comment_content' => $body, 'user_id' => 0, 'comment_approved' => $approval,
            'comment_meta' => ['_tkci_kind' => $kind, '_tkci_entry' => $entry, '_tkci_fingerprint' => $fingerprint]]));
        if (!$cid) throw new RuntimeException('Comment could not be saved.');
    } catch (Throwable $e) { delete_option($lock); wp_send_json_error(['message' => $t('Could not save your comment. Please try again.', 'Kommentaari ei saanud salvestada. Proovi uuesti.')], 500); }
    delete_option($lock);
    wp_send_json_success(['comments' => tkci_reply_count($kind, $entry, $target), 'message' => $approval === 1 ? $t('Comment posted. Thank you!', 'Kommentaar avaldatud. Aitäh!') : $t('Comment submitted for review.', 'Kommentaar saadetud ülevaatamiseks.')]);
}
add_action('wp_ajax_tkci_public', 'tkci_ajax');
add_action('wp_ajax_nopriv_tkci_public', 'tkci_ajax');
add_filter('get_comment_link', function ($url, $c) {
    if ($c->comment_type !== 'tkc_online_reply') return $url;
    $kind = get_comment_meta($c->comment_ID, '_tkci_kind', true); $entry = (int)get_comment_meta($c->comment_ID, '_tkci_entry', true);
    $target = tkci_target($kind, $entry); if (!$target) return $url;
    return ($kind === 'city' ? tkcq_url($target['post']) . '#comment-' . $target['parent'] : tkcsky_url($target['post']) . '#sky-entry-' . $entry);
}, 20, 2);
add_filter('admin_comment_types_dropdown', function ($types) {
    $types['tkc_online_reply'] = 'Online activity comments / 线上活动评论';
    return $types;
});
