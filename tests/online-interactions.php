<?php
/** Boundary tests with synthetic WP records and a real SQLite uniqueness constraint.
 * No website connection, real participants, emails or browser cookies are used.
 */
define('ABSPATH', '/'); define('DAY_IN_SECONDS', 86400); define('MINUTE_IN_SECONDS', 60);
$GLOBALS['options'] = ['tkci_schema' => '1']; $GLOBALS['transients'] = []; $GLOBALS['replies'] = [];
$GLOBALS['types'] = [11 => 'flamingo_inbound', 12 => 'flamingo_inbound', 100 => 'tkc_online', 200 => 'tkc_quest'];
$GLOBALS['states'] = [11 => 'publish', 12 => 'publish', 100 => 'publish', 200 => 'publish'];
$GLOBALS['meta'] = [11 => ['_tkcsky_project' => 100, '_tkcsky_review' => 'approved', '_tkcsky_consent' => 'public-v1', '_tkcsky_published_at' => 123], 12 => ['_tkcq_comment_id' => 3]];
$GLOBALS['checkin'] = (object)['comment_ID' => 3, 'comment_post_ID' => 200, 'comment_approved' => '1'];
class TestDB {
    public $prefix = 'wp_'; public $db;
    function __construct() { $this->db = new SQLite3(':memory:'); $this->db->exec('CREATE TABLE wp_tkc_online_likes (kind TEXT, entry_id INTEGER, visitor_hash TEXT, created_at TEXT, PRIMARY KEY(kind,entry_id,visitor_hash))'); }
    function prepare($sql, ...$values) { $i=0; return preg_replace_callback('/%[sd]/', function($m) use(&$i,$values){$v=$values[$i++]; return $m[0]==='%d'?(string)(int)$v:"'".SQLite3::escapeString($v)."'";},$sql); }
    function query($sql) { $ok=$this->db->exec(str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql)); return $ok?$this->db->changes():false; }
    function get_var($sql) { return $this->db->querySingle($sql); }
    function delete($table,$where,$formats) { $args=[];$clauses=[];foreach($where as$k=>$v){$clauses[]=$k.'=%s';$args[]=$v;} return $this->query($this->prepare('DELETE FROM '.$table.' WHERE '.implode(' AND ',$clauses),...$args)); }
}
$GLOBALS['wpdb'] = new TestDB();
function add_action(...$args) {} function add_filter(...$args) {}
function get_option($key) { return $GLOBALS['options'][$key] ?? false; }
function add_option($key,$value,...$unused) { if(isset($GLOBALS['options'][$key])) return false; $GLOBALS['options'][$key]=$value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key,$value,$expires) { $GLOBALS['transients'][$key]=$value; }
function get_post_type($id) { return $GLOBALS['types'][$id] ?? false; }
function get_post_status($id) { return $GLOBALS['states'][$id] ?? false; }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_comment($id) { return $id===3?$GLOBALS['checkin']:null; }
function tkcq_comment_entry($c) { return $c===$GLOBALS['checkin']?12:0; }
function tkcq_photo_path($id) { return '/synthetic/city.jpg'; }
function tkcsky_photo_path($id) { return '/synthetic/sky.jpg'; }
function wp_salt($kind) { return 'test-only-'.$kind; }
function wp_unslash($v) { return $v; } function wp_slash($v) { return $v; }
function wp_parse_url(...$args) { return parse_url(...$args); }
function home_url($path) { return 'https://example.test'.$path; }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/','',strtolower($v)); }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function sanitize_textarea_field($v) { return trim(strip_tags($v)); }
function absint($v) { return abs((int)$v); }
function nocache_headers() {}
function check_ajax_referer(...$args) { return ($_POST['nonce'] ?? '')==='test-nonce'; }
function is_ssl() { return true; }
function current_time(...$args) { return gmdate('Y-m-d H:i:s'); }
function wp_date($format,$ts,$zone) { return (new DateTime('@'.$ts))->setTimezone($zone)->format($format); }
function wp_check_comment_disallowed_list(...$args) { return strpos($args[3],'blocked-word')!==false; }
function get_comments($args) {
    $rows=array_filter($GLOBALS['replies'],function($c)use($args){
        if($c->comment_post_ID!==$args['post_id'] || $c->comment_type!==$args['type'])return false;
        if(isset($args['parent']) && $c->comment_parent!==$args['parent'])return false;
        if(($args['status']??'')==='approve' && $c->comment_approved!==1)return false;
        if(isset($args['meta_key']) && ($c->meta[$args['meta_key']]??'')!==$args['meta_value'])return false;
        foreach($args['meta_query']??[] as$q)if(is_array($q)&&($c->meta[$q['key']]??null)!=$q['value'])return false;
        return true;
    });
    if(!empty($args['count']))return count($rows);
    usort($rows,function($a,$b){return $b->comment_ID<=>$a->comment_ID;});
    return array_slice($rows,$args['offset']??0,$args['number']??count($rows));
}
function wp_insert_comment($data) { $id=count($GLOBALS['replies'])+10; $data['comment_ID']=$id;$data['comment_date_gmt']='2026-11-08 10:00:00';$data['meta']=$data['comment_meta'];$GLOBALS['replies'][$id]=(object)$data;return $id; }
class JsonResult extends RuntimeException { public $success,$data,$status;function __construct($success,$data,$status){$this->success=$success;$this->data=$data;$this->status=$status;} }
function wp_send_json_success($data) { throw new JsonResult(true,$data,200); }
function wp_send_json_error($data,$status=400) { throw new JsonResult(false,$data,$status); }
require dirname(__DIR__).'/site-source/tkc-setup/online-interactions.php';
$checks=0;
function check($condition,$name) { global $checks; if(!$condition)throw new RuntimeException('FAILED: '.$name);$checks++; }
function call_api($extra=[]) {
    $_POST=array_merge(['nonce'=>'test-nonce','kind'=>'sky','entry'=>'11','lang'=>'en','operation'=>'state'],$extra);
    try {tkci_ajax();}catch(JsonResult $r){return $r;} throw new RuntimeException('No JSON result');
}
function visitor($token) { $_COOKIE['tkc_online_visitor']=$token.'.'.hash_hmac('sha256',$token,wp_salt('auth')); }
$_SERVER=['REQUEST_METHOD'=>'POST','HTTP_ORIGIN'=>'https://example.test','REMOTE_ADDR'=>'192.0.2.1'];$_COOKIE=[];
check(call_api()->success,'guest can read a public entry');
foreach(['private','trash']as$state){$GLOBALS['states'][11]=$state;check(call_api(['operation'=>'like','liked'=>'1'])->status===404,'nonpublic entry blocks writes');}$GLOBALS['states'][11]='publish';
foreach(['pending','rejected']as$review){$GLOBALS['meta'][11]['_tkcsky_review']=$review;check(call_api(['operation'=>'reply','nickname'=>'Test','comment'=>'Hidden'])->status===404,'unapproved photo blocks comments');}$GLOBALS['meta'][11]['_tkcsky_review']='approved';
$GLOBALS['meta'][11]['_tkcsky_consent']='';check(call_api()->status===404,'missing public consent blocks reads');$GLOBALS['meta'][11]['_tkcsky_consent']='public-v1';
$GLOBALS['states'][100]='private';check(call_api()->status===404,'private project blocks child entry');$GLOBALS['states'][100]='publish';
check(call_api(['kind'=>'city','entry'=>12])->success,'public City Surprise entry works');
$GLOBALS['checkin']->comment_approved='0';check(call_api(['kind'=>'city','entry'=>12])->status===404,'withdrawn check-in is inaccessible');$GLOBALS['checkin']->comment_approved='1';
check(call_api(['nonce'=>'expired'])->status===403,'expired nonce rejected');
$_SERVER['HTTP_ORIGIN']='https://attacker.test';check(call_api()->status===403,'cross-origin request rejected');$_SERVER['HTTP_ORIGIN']='https://example.test';
$_SERVER['REQUEST_METHOD']='GET';check(call_api()->status===405,'GET cannot write');$_SERVER['REQUEST_METHOD']='POST';
foreach(['entry','nonce','nickname','comment']as$key)check(call_api([$key=>['bad']])->status===400,'array field rejected: '.$key);
check(call_api(['kind'=>'other'])->status===404,'unknown target kind rejected');
visitor(str_repeat('a',48));$first=call_api(['operation'=>'like','liked'=>'1']);check($first->data===['count'=>1,'liked'=>true],'guest heart saved');
check(call_api(['operation'=>'like','liked'=>'1'])->data['count']===1,'repeated desired like is idempotent');
check(call_api()->data['liked']===true,'liked state persists for same signed browser');
visitor(str_repeat('b',48));check(call_api()->data['liked']===false,'other browser starts unliked');
check(call_api(['operation'=>'like','liked'=>'1'])->data['count']===2,'second visitor increases shared count');
check(call_api(['operation'=>'like','liked'=>'0'])->data['count']===1,'unlike removes only own vote');
check(call_api(['kind'=>'city','entry'=>12])->data['count']===0,'counts isolated between submissions');
$_COOKIE['tkc_online_visitor']=str_repeat('a',48).'.forged';check(call_api()->data['liked']===false,'forged cookie cannot claim another visitor vote');
foreach([['nickname'=>'','comment'=>'hello'],['nickname'=>'Test','comment'=>''],['nickname'=>'Test','comment'=>str_repeat('x',2001)],['nickname'=>'Test','comment'=>'hello','website'=>'spam']]as$input)check(call_api(array_merge(['operation'=>'reply'],$input))->status===400,'invalid guest reply rejected');
$ok=call_api(['operation'=>'reply','nickname'=>'Guest','comment'=>"Great sky! <script>alert(1)</script>"]);
check($ok->success&&$ok->data['comments']===1,'guest posts without account or email');
$row=array_values($GLOBALS['replies'])[0];check($row->comment_author_email===''&&$row->comment_author_IP===''&&$row->comment_agent===''&&$row->user_id===0,'reply stores no identity/contact fields');
check(strpos($row->comment_content,'<script>')===false,'comment HTML stripped');
check(call_api(['operation'=>'reply','nickname'=>'Guest','comment'=>"Great sky! <script>alert(1)</script>"])->data['comments']===1,'duplicate submission is not inserted twice');
$rows=call_api(['operation'=>'comments'])->data['replies'];check(array_keys($rows[0])===['id','nickname','comment','date'],'public comment response uses explicit safe field list');
$other=call_api(['operation'=>'reply','kind'=>'city','entry'=>12,'nickname'=>'Guest','comment'=>'Nice discovery']);check($other->success,'City Surprise guest reply saved');
check(call_api(['operation'=>'comments','kind'=>'city','entry'=>12])->data['comments']===1,'City thread has own reply');
check(call_api(['operation'=>'comments'])->data['comments']===1,'Sky thread not mixed with City thread');
$last=end($GLOBALS['replies']);check($last->comment_parent===3,'City reply attaches to correct native check-in');
$last->comment_approved=0;check(call_api(['operation'=>'comments','kind'=>'city','entry'=>12])->data['comments']===0,'native moderation hides reply and updates count');
$GLOBALS['transients']=[];call_api(['operation'=>'reply','nickname'=>'Guest','comment'=>'blocked-word']);check(call_api()->data['comments']===1,'disallowed comment is not public');
$GLOBALS['transients']=[];for($i=0;$i<8;$i++)call_api(['operation'=>'reply','nickname'=>'Guest','comment'=>'Rate test '.$i]);check(call_api(['operation'=>'reply','nickname'=>'Guest','comment'=>'Over limit'])->status===429,'anonymous comment rate limit enforced');
echo 'PASS: '.$checks." interaction boundary checks\n";
