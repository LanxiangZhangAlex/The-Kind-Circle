<?php
/** Preserve a fully laid-out editor description; retain legacy mission-only rounds. */
defined('ABSPATH') || exit;

function tkcq_body_has_hint_heading($html) {
    preg_match_all('~<h[1-6]\b[^>]*>(.*?)</h[1-6]>~is', $html, $headings);
    foreach ($headings[1] as $heading) {
        $label = html_entity_decode(wp_strip_all_tags($heading), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = preg_replace('/[\s\x{00a0}]+/u', ' ', $label);
        $label = strtolower(rtrim(trim($label), ': '));
        if (in_array($label, ['hint', 'vihje'], true)) return true;
    }
    return false;
}

function tkcq_task_markup($body, $hint, $gesture, $lang) {
    $body = wpautop(do_blocks(wp_kses_post($body)));
    // A Hint/Vihje heading identifies an editor-owned complete task layout.
    // Return that layout intact rather than adding a second set of task sections.
    if (tkcq_body_has_hint_heading($body)) return $body;
    $et = $lang === 'et';
    return '<h2>' . ($et ? 'Vihje' : 'Hint') . '</h2><p>' . esc_html($hint) . '</p><h2>'
        . ($et ? 'Fotoülesanne' : 'Photo mission') . '</h2>' . $body
        . '<p><strong>' . ($et ? 'Nõutud žest: ' : 'Required gesture: ') . '</strong>' . esc_html($gesture) . '</p><p>'
        . ($et ? 'Jäädvusta koht ja nõutud žest samale fotole. Nägu ei pea näitama.' : 'Include the location and the required gesture in the same photo. You do not need to show your face.') . '</p>';
}
