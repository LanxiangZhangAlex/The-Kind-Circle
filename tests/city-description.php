<?php
/** Description composition regressions; synthetic text only, no live connection. */
define('ABSPATH', '/');
function wp_strip_all_tags($s) { return strip_tags($s); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
// Core formatting/sanitisation remains WordPress's responsibility. These tests
// isolate section composition while preserving the supplied editor markup.
function wpautop($s) { return $s; } function do_blocks($s) { return $s; } function wp_kses_post($s) { return $s; }
require dirname(__DIR__).'/site-source/tkc-setup/city-surprise-content.php';
$checks=0;
function check($value,$label) { global $checks;if(!$value)throw new RuntimeException('FAILED: '.$label);$checks++; }
$en='<h2>Hint</h2><strong>Example district</strong><h2>Photo mission</h2><p>Find either spot.</p><strong>Required gesture: Peace</strong><p>Include the location and the required gesture in the same photo. You do not need to show your face.</p>';
$et='<h2>Vihje</h2><p>Näidiskoht</p><p>Leia üks kohtadest.</p><p><strong>Nõutud žest:</strong> võidumärk</p>';
check(tkcq_task_markup($en,'Other hint','Other gesture','en')===$en,'complete English editor layout is authoritative');
check(tkcq_task_markup($et,'Other hint','Other gesture','et')===$et,'complete Estonian editor layout is authoritative');
check(tkcq_task_markup($en,'Other hint','Other gesture','et')===$en,'English fallback retains its complete layout');
check(substr_count(tkcq_task_markup($en,'Hint','Peace','en'),'<h2>Hint</h2>')===1,'no duplicate Hint heading');
check(substr_count(tkcq_task_markup($en,'Hint','Peace','en'),'Required gesture:')===1,'no duplicate gesture row');
check(substr_count(tkcq_task_markup($en,'Hint','Peace','en'),'Include the location')===1,'no duplicate fixed instruction');
check(strpos(tkcq_task_markup($et,'Other','Peace','et'),'Fotoülesanne')===false,'no unexpected heading inserted into Estonian layout');
foreach(['<h2 class="custom"><strong>HiNt:</strong></h2>','<!-- wp:heading --><h2>Hint</h2><!-- /wp:heading -->',"<h3>\xc2\xa0Vihje \xc2\xa0</h3>"]as$body)check(tkcq_body_has_hint_heading($body),'native and formatted hint heading supported');
foreach(['<p>Here is a hint about the area.</p>','<p>Hint: a clue in a mission-only paragraph.</p>','<h2>About the challenge</h2>','<h2>Hints for finding the place</h2>','<img alt="Hint" src="example.jpg">']as$body)check(!tkcq_body_has_hint_heading($body),'ordinary mission text does not change layout mode');
$mission='<p><strong>Photo mission:</strong> Find a place.</p><p>About this activity.</p>';
$legacy=tkcq_task_markup($mission,'District','Peace','en');
check(strpos($legacy,'<h2>Hint</h2>')===0,'mission-only round retains structured hint first');
check(strpos($legacy,$mission)!==false,'mission-only body preserved');
check(substr_count($legacy,'<h2>Photo mission</h2>')===1,'legacy mission heading retained');
check(strpos($legacy,'Required gesture:')!==false && strpos($legacy,'Peace')!==false,'legacy gesture field retained');
$legacyET=tkcq_task_markup('<p>Leia koht.</p>','Linnaosa','Võidumärk','et');
check(strpos($legacyET,'Fotoülesanne')!==false&&strpos($legacyET,'Nõutud žest:')!==false,'legacy Estonian labels retained');
$escaped=tkcq_task_markup($mission,'<img src=x>','<script>bad</script>','en');
check(strpos($escaped,'&lt;img src=x&gt;')!==false&&strpos($escaped,'&lt;script&gt;')!==false,'structured metadata still escaped');
$media=$en.'<figure><img src="example.jpg" alt="Second clue"></figure>';
check(tkcq_task_markup($media,'District','Peace','en')===$media,'editor photos and their ordering preserved');
echo 'PASS: '.$checks." City Surprise description checks\n";
