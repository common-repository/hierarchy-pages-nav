<?php
/**
 * Hierarchy Pages Navigation - search page contents
 * 
 */

defined( 'ABSPATH' ) || exit;

class HPGNAV_search {

	public function __construct() {

        add_filter( 'hpgnav_additional_nav_icon', array( $this, 'search_button'), 10, 3 );
        
        add_action( 'wp_ajax_nopriv_hpgnav_search', array( $this, 'ajax_hpgnav_search' ) );
        add_action( 'wp_ajax_hpgnav_search', array( $this, 'ajax_hpgnav_search' ) );        
    }
    
    function search_button( $add_html, $postid, $root_pid) {
        $root_url = get_page_link( $root_pid );
        $logo     = HPGNAV_toc::$m_opt['logo'];
        $title    = HPGNAV_toc::$m_opt['title'];
        
        ob_start();
?>
<div class="nav-icon icon-search">
  <label class="nav-dialog-button"><a href="" onclick="location.href='#hpgnav-search-dialog'; return false;"><span class="icon-mark search-mark"><?php HPGNAV_lib::get_svg( array( 'icon' => 'search' ), true ); ?></span><span class="icon-guide"><?php esc_html_e('Search', 'hpgnav'); ?></span></a></label>
  <div id="hpgnav-search-dialog" class="nav-dialog-overlay">
    <div class="nav-list-wrap">
      <div class="nav-header" style="border:none;">
        <div class="nav-title">
          <a href="<?php echo esc_url($root_url); ?>"><span class="logo-image"><img width="48" height="48" src="<?php echo esc_url($logo); ?>"></span><span class="main-title"><strong><?php echo esc_html($title); ?></strong></span></a>
          <span class="close-mark"><a class="hpgnav-dialog-close" href="###"><?php HPGNAV_lib::get_svg( array( 'icon' => 'clearclose' ), true ); ?></a></span>
        </div>
        <form role="search" id="hpgnav-search-form">
          <details>
            <summary><?php esc_html_e('Search Option', 'hpgnav'); ?></summary>
            <fieldset>
              <span class="search-type"><label><input type="radio" name="s-type" value="strict" /><?php esc_html_e('Exact match', 'hpgnav'); ?></label></span>
              <span class="search-type"><label><input type="radio" name="s-type" value="gen_ci" checked /><?php esc_html_e('Case insensitive (general ci)', 'hpgnav'); ?></label></span>
              <?php if(function_exists('mb_convert_kana')){ ?>
                <span class="search-type"><label><input type="radio" name="s-type" value="uni_ci" /><?php esc_html_e('Fuzzy (unicode ci)', 'hpgnav'); ?></label></span>
              <?php } ?>
              <div class="search-guide">
                <p><?php esc_html_e('* Entering multiple keywords separated by space will result in AND search. To match a sequence of words or space as a phrase, enclose the search string in double quotes, such as "example word".', 'hpgnav'); ?></p>
              </div>
            </fieldset>            
          </details>
          <input type="search" id="hpgnav-search-field" name="hpgnav-search-word" value=""  title="<?php esc_html_e('Search', 'hpgnav'); ?>">
          <button id="hpgnav-search-submit" class="button submit search" href="" onclick="HPGNAV_search_submit('<?php echo esc_html(wp_create_nonce('hpgnav_search')); ?>','<?php echo absint($postid); ?>','<?php echo absint($root_pid); ?>');return false;"><?php esc_html_e('Search', 'hpgnav'); ?></button>
        </form>         
      </div>
      <div id="hpgnav-search-result"></div>
    </div>
  </div>
</div>        
<?php 
        $add_html .= ob_get_clean();   
        return $add_html;        
    }

    //JS側でサーチワードをハイライト表示するための正規表現パターン生成
    private function create_js_reg_pattern( $s_type, $keys ) {
        $pattern = '';
        foreach ($keys as $idx => $key){
            if(0 !== $idx){
                $pattern .= '|';
            }
            //SQL LIKE のワイルドカードを正規表現に変換
            $key = str_replace("%", '.*?', $key);
            $key = str_replace("_", '.', $key);
            $key = str_replace("\.*?", '%', $key);
            $key = str_replace("\.", '_', $key);

            if($s_type === 'uni_ci' && function_exists('mb_convert_kana')){
                //SQL unicode_ci あいまい検索準拠の正規表現パターン生成
                $mbkey = '';
                $mblen = mb_strlen( $key, 'UTF-8');
                for($mbi=0; $mbi<$mblen; $mbi++){
                    $mb = mb_substr($key, $mbi, 1, 'UTF-8');
                    if (preg_match('/[0-9]+/u', $mb)){
                        $mbkey .= '[' . $mb . mb_convert_kana($mb, "N") . ']';
                    } elseif (preg_match('/[０-９]+/u', $mb)){
                        $mbkey .= '[' . $mb . mb_convert_kana($mb, "n") . ']';

                    } elseif (preg_match('/[a-z]+/u', $mb)){
                        $mbkey .= '[' . $mb . mb_convert_kana($mb, "R") . mb_convert_kana(strtoupper($mb), "R") . ']';
                    } elseif (preg_match('/[A-Z]+/u', $mb)){
                        $mbkey .= '[' . $mb . mb_convert_kana($mb, "R") . mb_convert_kana(strtolower($mb), "R") . ']';
                    } elseif (preg_match('/[ａ-ｚ]+/u', $mb)){
                        $hmb  = mb_convert_kana($mb, "r");
                        $humb = strtoupper($hmb);
                        $mbkey .= '[' . $mb . mb_convert_kana($humb, "R") . $hmb . $humb . ']';
                    } elseif (preg_match('/[Ａ-Ｚ]+/u', $mb)){
                        $hmb  = mb_convert_kana($mb, "r");
                        $hlmb = strtolower($hmb); 
                        $mbkey .= '[' . $mb . mb_convert_kana($hlmb, "R") . $hmb . $hlmb . ']';

                    } elseif (preg_match('/[\s\"\/\+\-\\\\' . preg_quote("!#$%&'()*,.:;<=>?@[]^_`{|}~") . ']+/u', $mb)){
                        //!   "   #   $   %   &   '   (   )   *   +   ,   -   .   /   :   ;   <   =   >   ?   @   [   \   ]   ^   _   `   {   |   }   ~
                        // "'\~ mb_convert_kana() で全角にならない
                        if($mb == "." ){
                            $mb2 = mb_substr($key, $mbi + 1, 1, 'UTF-8');
                            if($mb2 != "*"){
                                $mbkey .= '.';
                            } else {
                                $mbkey .= '.*?';
                                $mbi += 2;
                            }
                        } elseif($mb == '"' ){
                            $mbkey .= '[\"”]';
                        } elseif($mb == "'" ){
                            $mbkey .= "[\'’]";
                        } elseif($mb == '\\' ){
                            $mbkey .= '[\\\\￥]';
                        } elseif($mb == "~" ){
                            $mbkey .= '[\~～]';
                        } else {
                            $mbkey .= '[' . preg_quote("$mb") . mb_convert_kana($mb, "SA") . ']';
                        }
                    } elseif (preg_match('/[　！”＃＄％＆’（）＊＋、－．／：；＜＝＞［￥］＾＿｀｛｜｝～]+/u', $mb)){
                        // ” ’ ￥ ～ mb_convert_kana() で半角にならない
                        if($mb == "．" ){
                            $mbkey .= $mb;
                        } elseif($mb == "　" ){
                            $mbkey .= '[　\s]';
                        } elseif($mb == '”' ){
                            $mbkey .= '[”\"]';
                        } elseif($mb == "’" ){
                            $mbkey .= "[’\']";
                        } elseif($mb == "￥" ){
                            $mbkey .= '[￥\\\\]';
                        } elseif($mb == "～" ){
                            $mbkey .= '[～\~]';
                        } else {
                            $mbkey .= '[' . $mb . preg_quote( mb_convert_kana($mb, "a") ) . ']';
                        }
                    } elseif (preg_match('/\p{Hiragana}+/u', $mb)) {
                        $mbkey .= '[' . $mb . mb_convert_kana($mb, "C") . mb_convert_kana($mb, "h") . ']';

                    } elseif (preg_match('/[\p{Katakana}ー]+/u', $mb)) {
                        if(preg_match('/[｡-ﾟ]+/u', $mb)){
                            $mbkey .= '[' . $mb . mb_convert_kana($mb, "KV") . mb_convert_kana($mb, "HV") . ']';
                        } else {
                            $mbkey .= '[' . $mb . mb_convert_kana($mb, "c") . mb_convert_kana($mb, "k") . ']';                                            
                        }
                    } else {
                        $mbkey .= $mb;
                    }
                }
                if(!empty($mbkey)){
                    $key = $mbkey;
                }
            }                            
            $pattern .= '(' . $key . ')';
        }
        return $pattern;
    }
    
    public function ajax_hpgnav_search() {
        check_ajax_referer( 'hpgnav_search' );
        
        $req_pid = (isset($_POST['post_id']))? absint($_POST['post_id']) : 0;
        if( $req_pid > 0 ){
            $html    = '';
            $pattern = '';
            $root_pid= (isset( $_POST['root_pid']))? absint($_POST['root_pid']) : 0;
            $s_type  = (isset( $_POST['s_type']))? wp_kses( stripslashes($_POST['s_type']), 'strip' ) : 'gen_ci';
            $s_key   = (isset( $_POST['s_key']))? wp_kses( stripslashes($_POST['s_key']), 'strip' ) : '';
            if(!empty($s_key)){
                global $wpdb;
                $db  = new HPGNAV_db();                
                $val = $db->get_hpgnav_page($req_pid);
                if(!empty($val->root_pid) && $val->root_pid == $root_pid){
                    $m_opt = apply_filters( 'hpgnav_additional_get_root', array(), $val->root_pid );
                    if(empty($m_opt)){
                        $m_opt = HPGNAV_manager::$m_option;
                    }
                    if(!empty($m_opt['nav_mode'])){
                        $navtype = (current_user_can( 'read_private_pages' ))? 'publish,private' : 'publish';
                        $pages = HPGNAV_lib::get_hierarchy_pages( $val->root_pid, $navtype );
                        $pids  = array_keys( $pages );                        
                        $post__in  = implode( ',', array_map( 'absint', $pids ) );
                        
                        // BINARY     : Exact match 完全一致（大文字小文字を区別）
                        // general_ci : Case insensitive 大文字小文字を区別しない
                        // unicode_ci : Fuzzy  あいまい検索（大文字小文字半角全角ひらがな/カタカナ/一部記号）                        
                        $charset   = $wpdb->charset;
                        $collate   = $wpdb->collate;
                        $like_type = "LIKE";
                        if($s_type === 'strict'){
                            $like_type = "LIKE BINARY";
                        } elseif($s_type === 'uni_ci'){
                            if($collate === 'utf8mb4_unicode_520_ci'){
                                $like_type = "COLLATE utf8mb4_unicode_520_ci LIKE";                                                           
                            } else {
                                $like_type = "COLLATE {$charset}_unicode_ci LIKE";                           
                            }
                        } else { //gen_ci
                            $like_type = "COLLATE {$charset}_general_ci LIKE";
                        }
                        
                        if(substr( $s_key, 0, 1) == '"' && substr( $s_key, -1) == '"'){
                            //フレーズ検索はワイルドカード %, _ をエスケープ
                            $s_key   = substr( $s_key, 1, -1);
                            $keys[0] = $wpdb->esc_like( $s_key );
                        } else {
                            $s_key   = preg_replace('`　`u', ' ',  $s_key);                            
                            $s_key   = preg_replace('`\s{2,}`u', ' ',  $s_key);                            
                            $keys    = explode(' ', $s_key );
                        }
                        $pattern  = $this->create_js_reg_pattern( $s_type, $keys );
                        $likekeys = array_map( function($v) { return '%' . $v . '%'; }, $keys);

                        $query  = "SELECT * FROM {$wpdb->posts} WHERE ID IN ($post__in)";                    
                        foreach ($keys as $idx => $key){
                            $query .= " AND CONCAT(post_title,post_excerpt,post_content) {$like_type} %s";
                        }
                        $query .= " GROUP BY ID ORDER BY FIELD(ID,$post__in)";

                        $g_posts  = $wpdb->get_results( $wpdb->prepare( $query, $likekeys) );                      
                        if ( !empty($g_posts) && is_array( $g_posts ) && empty($wpdb->last_error) ) {
                            foreach ( $g_posts as $_post ) {
                                if(!empty($_post->ID)){
                                    $title   = apply_filters( 'hpgnav_the_title',   $_post->post_title, $_post->ID );
                                    $excerpt = apply_filters( 'hpgnav_the_excerpt', $_post->post_excerpt, $_post );

                                    //SQLでは post_content 内にあるブロック(<!-- -->)やタグ内のクラス、アトリビュートも検索してしまっているので
                                    //それらを除外してコンテンツテキスト部分でマッチするかPHPで再評価する
                                    //例えば img タグ内に image-123 等のID等があると image という検索でマッチしてしまう
                                    $text = $title . ' ' . $excerpt . ' ' . $_post->post_content;
                                    $text = preg_replace("/<script.+?<\/script>/su", '', $text);
                                    $text = preg_replace("/<style.+?<\/style>/su", '', $text);
                                    $text = preg_replace("/<[^>]*?>/su", '', $text);
                                    $pregflags = ($s_type === 'strict')? 'su' : 'sui';
                                    if(preg_match("/$pattern/$pregflags", $text)){
                                        $alink = '<a href="' . esc_url( get_permalink( $_post->ID )) . '#search_highlight" onclick="HPGNAV_reload_sameurl(this);">';
                                        $html .= '<li><div id="post-list-' . absint($_post->ID) . '">' . $alink . '<span class="match-title">' . esc_html($title) . '</span><br><span class="match-excerpt">' . esc_html($excerpt) . '</span></a></div></li>';                                        
                                    }
                                }
                            }
                        }
                        if(!empty($html)){
                            $html = '<ul class="hpgnav-match-list">' . $html . '</ul>';
                        } else {
                            $html = esc_html__( 'No result found', 'hpgnav');
                        }                        
                    }
                }
            }
            $response = array();
            $response['success'] = true;
            $response['data']    = wp_kses($html, HPGNAV_lib::get_allowed_tags());
            $response['pattern'] = $pattern;
            $response['flags']   = ($s_type === 'strict')? 'gsu' : 'gsui';
            wp_send_json( $response );                
        }
        wp_die( 0 );        
    }            
}
    