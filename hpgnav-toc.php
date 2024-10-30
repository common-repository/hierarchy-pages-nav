<?php
/**
 * Hierarchy Pages Navigation - pages list and table of contents
 * 
 */

defined( 'ABSPATH' ) || exit;

class HPGNAV_toc {

    static $m_opt;
    static $pgtoc;

	public function __construct() {
        self::$m_opt = array();
        self::$pgtoc = null;
        
        //post password expires change
        //パスワードのCookie保存期限デフォルト10日から変更
        add_filter( 'post_password_expires', function($expires) {
            if(!empty(HPGNAV_manager::$m_option['pw-expires'])){
                $nexp = absint(HPGNAV_manager::$m_option['pw-expires']);
                if($nexp > 0 && $nexp <= 999){
                    $expires = time() + $nexp * 60 * 60;
                }
            }
            return $expires;
        }, 10,1);            

        add_action('template_redirect', function(){
            global $post;
            if ( !empty( $post->ID ) && is_singular() ){            
                $db  = new HPGNAV_db();                
                self::$pgtoc = $db->get_hpgnav_page($post->ID);
                if(!empty(self::$pgtoc)){
                    
                    self::$m_opt = apply_filters( 'hpgnav_additional_get_root', array(), self::$pgtoc->root_pid );
                    if(empty(self::$m_opt)){
                        self::$m_opt = HPGNAV_manager::$m_option;
                    }

                    // noindex
                    $n_type = self::$m_opt['noindex_type'];
                    if(!empty( $n_type )){
                        $noindex = false;
                        if($n_type === 'all'){
                            $noindex = true;                                
                        } elseif($n_type === 'exc_top'){
                            if($post->ID !== absint(self::$pgtoc->root_pid)){
                                $noindex = true;                                
                            }
                        } elseif($n_type === 'exc_public'){
                            if($post->post_status === 'private' || !empty($post->post_password)){
                                $noindex = true;                                
                            }
                        }
                        if($noindex){
                            add_action( 'wp_head', function(){
                                remove_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );
                                echo wp_kses('<meta name="robots" content="noindex,follow" >' . PHP_EOL, array('meta' => array( 'name' => true, 'content' => true )));
                            }, 0 );                            
                        }
                    }
            
                    // post-password required - password skip filter
                    //  $required true  - 照合必要
                    //            false - 照合不要または照合一致
                    //  $post     Post data.
                    $roles = self::$m_opt['skip_password'];
                    if(!empty( $roles ) && is_user_logged_in()){
                        add_filter( 'post_password_required',  function( $required, $post ) use(&$roles){
                            if($required){
                                $arrole = array_filter( array_map("trim", explode(',', $roles)));
                                foreach ($arrole as $role) {
                                    if (current_user_can($role, $post->ID)){
                                        $required = false;
                                        break;
                                    }
                                }
                            }
                            return $required;                            
                        }, 1000, 2 );
                    }

                    wp_enqueue_style( 'hpgnav-style', plugins_url( 'style.css', __FILE__ ), array(), filemtime( __DIR__ . '/style.css' ));                    
                    $opt = HPGNAV_manager::$v_option;
                    $direction = ($opt['pos_x'] === 'right')? 'row-reverse' : 'row';
                    $navcss = "#hpgnav-nav {{$opt['pos_x']}:{$opt['offset_x']}{$opt['unit_x']}; {$opt['pos_y']}:{$opt['offset_y']}{$opt['unit_y']};}";
                    $navcss .= ".nav-dialog-button .icon-mark {color:{$opt['color']}; background-color:{$opt['background']};}";
                    $navcss .= ".nav-dialog-button .nav-mark {border-color:{$opt['border']};}";
                    $navcss .= ".nav-dialog-button .icon-guide {color:{$opt['color']};}";
                    $navcss .= ".nav-icons {flex-direction:{$direction};}";
                    $navcss .= ".nav-icons .sub-panel {flex-direction:{$direction}; background-color:{$opt['background']}; border-color:{$opt['border']};}";
                    $navcss .= ".nav-icons .nav-page {color:{$opt['color']};}";
                    wp_add_inline_style( 'hpgnav-style', $navcss );
                    
                    wp_enqueue_script( 'hpgnav-js', plugins_url( 'js/hpgnav.js', __FILE__ ), array(), filemtime( __DIR__ . '/js/hpgnav.js' ), true );		
                    $hpgnav_inline = 'const hpgnav_set = ' . wp_json_encode( array( 
                        'ajax_url'      => admin_url( 'admin-ajax.php' ),
                        'nonce'         => wp_create_nonce( 'hpgnav_get_toc' ),
                        'scroll_action' => ((empty($opt['scroll_action']))? 0 : 1),
                        'disallowed'    => esc_html__('Strings containing symbol characters < or > cannot be searched', 'hpgnav'),
                        'searching'     => '<img decoding="sync" src="' . plugins_url( 'img/ajax-loader.gif', __FILE__ ) . '" /><span style="margin-left: 10px;">' . esc_html__('Searching...', 'hpgnav') . '</span>',
                    ));                    
                    wp_add_inline_script( 'hpgnav-js', $hpgnav_inline, 'before' );
            
                    //Before deciding which template to use
                    add_filter('the_content', array($this, 'anchorlink'), 10, 1);                
                }
            }            
        } );

        add_action('wp_ajax_hpgnav_get_toc', array('HPGNAV_toc', 'ajax_get_toc'));
        add_action('wp_ajax_nopriv_hpgnav_get_toc', array('HPGNAV_toc', 'ajax_get_toc'));        
	}

    //SVG icon data
    static function get_navicon() {
        $svg = '<svg class="navicon-define" aria-hidden="true" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">';
        $svg .= '<defs>';
        $svg .= '<symbol id="navicon-clearclose" viewBox="0 0 24 24"><path d="M18.984 6.422l-5.578 5.578 5.578 5.578-1.406 1.406-5.578-5.578-5.578 5.578-1.406-1.406 5.578-5.578-5.578-5.578 1.406-1.406 5.578 5.578 5.578-5.578z"></path></symbol>';
        $svg .= '<symbol id="navicon-search" viewBox="0 0 26 28"><path d="M18 13c0-3.859-3.141-7-7-7s-7 3.141-7 7 3.141 7 7 7 7-3.141 7-7zM26 26c0 1.094-0.906 2-2 2-0.531 0-1.047-0.219-1.406-0.594l-5.359-5.344c-1.828 1.266-4.016 1.937-6.234 1.937-6.078 0-11-4.922-11-11s4.922-11 11-11 11 4.922 11 11c0 2.219-0.672 4.406-1.937 6.234l5.359 5.359c0.359 0.359 0.578 0.875 0.578 1.406z"></path></symbol>';
        $svg .= '<symbol id="navicon-chevron-left" viewBox="0 0 21 28"><path d="M18.297 4.703l-8.297 8.297 8.297 8.297c0.391 0.391 0.391 1.016 0 1.406l-2.594 2.594c-0.391 0.391-1.016 0.391-1.406 0l-11.594-11.594c-0.391-0.391-0.391-1.016 0-1.406l11.594-11.594c0.391-0.391 1.016-0.391 1.406 0l2.594 2.594c0.391 0.391 0.391 1.016 0 1.406z"></path></symbol>';
        $svg .= '<symbol id="navicon-chevron-right" viewBox="0 0 19 28"><path d="M17.297 13.703l-11.594 11.594c-0.391 0.391-1.016 0.391-1.406 0l-2.594-2.594c-0.391-0.391-0.391-1.016 0-1.406l8.297-8.297-8.297-8.297c-0.391-0.391-0.391-1.016 0-1.406l2.594-2.594c0.391-0.391 1.016-0.391 1.406 0l11.594 11.594c0.391 0.391 0.391 1.016 0 1.406z"></path></symbol>';
        $svg .= '<symbol id="navicon-list-ul" viewBox="0 0 28 28"><path d="M6 22c0 1.656-1.344 3-3 3s-3-1.344-3-3 1.344-3 3-3 3 1.344 3 3zM6 14c0 1.656-1.344 3-3 3s-3-1.344-3-3 1.344-3 3-3 3 1.344 3 3zM28 20.5v3c0 0.266-0.234 0.5-0.5 0.5h-19c-0.266 0-0.5-0.234-0.5-0.5v-3c0-0.266 0.234-0.5 0.5-0.5h19c0.266 0 0.5 0.234 0.5 0.5zM6 6c0 1.656-1.344 3-3 3s-3-1.344-3-3 1.344-3 3-3 3 1.344 3 3zM28 12.5v3c0 0.266-0.234 0.5-0.5 0.5h-19c-0.266 0-0.5-0.234-0.5-0.5v-3c0-0.266 0.234-0.5 0.5-0.5h19c0.266 0 0.5 0.234 0.5 0.5zM28 4.5v3c0 0.266-0.234 0.5-0.5 0.5h-19c-0.266 0-0.5-0.234-0.5-0.5v-3c0-0.266 0.234-0.5 0.5-0.5h19c0.266 0 0.5 0.234 0.5 0.5z"></path></symbol>';
        $svg .= '</defs>';
        $svg .= '</svg>';
        return $svg;
    }

   static function h_list( $content, $depth ) {
        $hlist = array();
        $area = ($depth == 1)? '1' : "1-$depth";
		$pattern = "|<h([$area]).*?>(.*?)<\/h[$area].*?>|usi";
		if(false !== preg_match_all($pattern, $content, $matches)){
            $idx = 0;
            $tag_sum = array(0, 0, 0, 0, 0, 0, 0);
            foreach ($matches[1] as $v){
                $hnum  = absint($matches[1][$idx]);
                $title = esc_html($matches[2][$idx]);
                $idx++;
                if( $hnum >= 1 && $hnum <= $depth){
                    if(empty($title)){
                        continue;
                    }
                    $tag_sum[$hnum]++;
                    array_push( $hlist, array('tag'=>'h' . $hnum, 'title'=>$title, 'number'=>$tag_sum[$hnum], 'depth'=>$hnum));
                }
            }
        }
        return $hlist;
    }

    //カレントページコンテンツ内の heading タグに TOC anchorlink IDを付ける (the_content hook)
    function anchorlink( $content ) {
        global $post;
        if(!empty(self::$m_opt['nav_mode']) && !empty(self::$pgtoc) && self::$pgtoc->root_pid == self::$m_opt['root_pid']){
            $depth = self::$m_opt['h_tag_depth'];
            
            $root_pid = self::$pgtoc->root_pid;
            $hlist    = self::$pgtoc->hlist;
            $toc      = self::$pgtoc->toc;
            if(empty($toc)){
                //ブロック、ショートコード等処理前の生データを対象に見出し抽出
                $hlist = self::h_list( $post->post_content, $depth );
                $url   = get_page_link( $post->ID );
                $toc   = self::toc_create( $hlist, true, $url );
                
                $db = new HPGNAV_db();                
                $db->set_hpgnav_page( false, $post->ID, $root_pid, $hlist, $toc );
            }
            if(!empty($hlist)){
                foreach ($hlist as $h){
                    if(!empty($h)){
                        $pattern = "/(<{$h['tag']}.*?>" . preg_quote( "{$h['title']}" ) . ")(<\/{$h['tag']}.*?>)/usi";
                        $anchor  = '<span id="toc_' . esc_html($h['tag']) . absint($h['number']) . '" class="toc-anchor"></span>';
                        $content = preg_replace_callback( $pattern, function($match) use($anchor) {                           
                            return $match[1] . $anchor . $match[2];
                        }, $content, 1);
                    }
                }                        
            }

            $navhtml = self::nav_create( $post->ID, $root_pid );
            $navhtml .= self::get_navicon();

            add_action( 'wp_footer', function() use(&$navhtml) {                
                echo wp_kses($navhtml, HPGNAV_lib::get_allowed_tags());
            }, 9999 );

            do_action( 'hpgnav_additional_output' );                    
        }
        return $content;
    }   

    static function _toc_links( $hlist, $before, $after, $url='' ) {
        $last_depth = 0;
        $toc = '';
        while($h = array_shift($hlist)){
            if($last_depth < absint($h['depth'])){
                for($n=$last_depth; $n < absint($h['depth']); $n++ ){
                    $toc .= $before;
                }
                $toc .= '<li>';
            } else {
                $toc .= '<li>';
            }
            $toc .= '<a class="toc-link" href="' . esc_url($url) . '#toc_' . esc_html($h['tag']) . absint($h['number']) .'">' . esc_html($h['title']) . '</a>';

            $last_depth = absint($h['depth']);

            $nh = current($hlist);
            if(!empty($nh)){
                if($last_depth < absint($nh['depth'])){
                } else if($last_depth > absint($nh['depth'])){
                    $toc .= '</li>';
                    for($n=$last_depth; $n > absint($nh['depth']); $n-- ){
                        $toc .= $after ;
                    }
                }
            } else {
                $toc .= '</li>';
            }
        }
        if($last_depth > 0){
            for($n=$last_depth; $n > 0; $n-- ){
                $toc .= $after;
            }
        }
        return $toc;
    }
    
    //heading データリストを HTML へ変換
    static function toc_create( $hlist, $number=false, $url='' ) {
        $toc = '<br>';
        if(!empty($hlist)){
            if($number === false){
                $before = '<ul>' . PHP_EOL;
                $after  = '</ul>' . PHP_EOL;
            } else {
                $before = '<ol>' . PHP_EOL;
                $after  = '</ol>' . PHP_EOL;
            }
            $toc = self::_toc_links( $hlist, $before, $after, $url );
        }
        return $toc;
    }

    //フロントサイド　ナビページリストの個別ページ部のHTML
    static function nav_page_item( $page, $toc, $current_pid ) {
        $open = $current = '';
        $pageurl = get_page_link( $page['ID'] );
        $go2page = esc_html__('Go to Page', 'hpgnav');
        if($page['ID'] == $current_pid){
            $open = ' open';
            $current = ' current-page';
            if(!empty($toc)){
                //current page url replace
                $toc = str_replace($pageurl, '', $toc);
            }
            $pageurl = '#';
            $go2page = esc_html__('Page Top', 'hpgnav');
        }

        $item  = '<div class="nav-page-list' . $current . '">';
        $item .=   '<details' . $open . '>';
        $item .=   '<summary id="nav-page-' . absint($page['ID']) . '">' . esc_html($page['post_title']) . '</summary>';            
        $item .=   '<div class="toc-links">';        
        $item .=     '<p><a class="page-top toc-link" href="' . esc_url($pageurl) .'">' . $go2page . '</a></p>';
        $item .=     '<div id="toc-' . absint($page['ID']) . '">' . $toc . '</div>';
        $item .=   '</div>';
        $item .=   '</details>';
        $item .= '</div>';
        return $item;
    }
        
	static function nav_create( $postid, $root_pid ) {        
        $navlist = '';
        $root_url= get_page_link( $root_pid );
        $prev_url= '';
        $next_url= '';
        $navtype = (current_user_can( 'read_private_pages' ))? 'publish,private' : 'publish';
        $pages = HPGNAV_lib::get_hierarchy_pages( $root_pid, $navtype );
        if(is_array($pages)){
            $db = new HPGNAV_db();                
            $val = $db->get_all_hpgnav_page($root_pid);            
            $navlist =  '<div class="nav-list-wrap">';
            $navlist .= '<div class="nav-header">';
            $navlist .=   '<div class="nav-title">';
            $navlist .=     '<a href="' . esc_url($root_url) . '"><span class="logo-image"><img width="48" height="48" src="' . esc_url(self::$m_opt['logo']) . '"></span><span class="main-title"><strong>' . self::$m_opt['title'] .'</strong></span></a>';
                            //クローズ時のターゲットは href="###" をダミー指定して遷移なしにダイアログを閉じる
            $navlist .=     '<span class="close-mark"><a class="hpgnav-dialog-close" href="###" >' . HPGNAV_lib::get_svg( array( 'icon' => 'clearclose' )) . '</a></span>';
            $navlist .=   '</div>';
            $navlist .= '</div>';         
            $navlist .= '<div class="nav-content">';
            $navlist .= '<ul class="nav-list page-depth-0">';
            $cdepth = 0;
            $ulopen = 1;
            $liopen = 0;
            foreach($pages as $pid => $pg){
                $toc = '';
                if(isset($val[$pid])){
                    $toc = $val[$pid]->toc;
                }
                if($pg['depth'] > $cdepth ){
                    $cdepth = $pg['depth'];
                    $navlist .= '<ul class="page-depth-' . absint($cdepth) . '">';    
                    $navlist .= '<li>' . self::nav_page_item( $pg, $toc, $postid );
                    $ulopen++;
                    $liopen++;
                } elseif($pg['depth'] < $cdepth ){
                    for($dp=$cdepth; $pg['depth'] < $dp; $dp--){
                        $navlist .= '</ul>';    
                        $ulopen--;                        
                    }
                    $cdepth = $pg['depth'];
                    $navlist .= '</li>';    
                    $navlist .= '<li>' . self::nav_page_item( $pg, $toc, $postid );
                } else {
                    if($liopen > 0){
                        $navlist .= '</li>';
                        $liopen--;
                    }
                    $navlist .= '<li>' . self::nav_page_item( $pg, $toc, $postid );
                    $liopen++;
                }
            } 
            for(; $liopen > 0; $liopen--){
                $navlist .= '</li>';
            }
            for(; $ulopen > 0; $ulopen--){
                $navlist .= '</ul>';    
            }                
            $navlist .= '</div>';
            $navlist .= '</div>';
            
            $idx = array_keys( $pages );
            $num = array_search( $postid, $idx );
            if($num !== false){
                if($num > 0){
                    $prev_url = get_page_link( $idx[$num - 1] );
                }
                if($num >= 0 && $num < count($idx) - 1){
                    $next_url = get_page_link( $idx[$num + 1] );                    
                }
            }
        }

        $opt = HPGNAV_manager::$v_option;
        $direction = ($opt['pos_x'] === 'right')? 'row-reverse' : 'row';        
        $html = '';
        if(!empty($navlist)){
            $html .= '<div id="hpgnav-nav" class="hpgnav-display">';
            $html .=   '<div class="nav-icons ' . $direction . '">';
            $html .=     '<div class="nav-icon icon-toc">';
            //目次プラグインのJSによるページ内スクロール処理等でリンクにイベントが設定されることがあるので onclick を使用して干渉を回避
            //$html .=       '<label class="nav-dialog-button" ><a href="#hpgnav-toc-open"><div class="icon-mark nav-mark">' . HPGNAV_lib::get_svg( array( 'icon' => 'list-ul' )) . '</div></a></label>';
            $html .=       '<label class="nav-dialog-button" ><a href="" onclick="HPGNAV_nav_toc_open(); return false;"><div class="icon-mark nav-mark">' . HPGNAV_lib::get_svg( array( 'icon' => 'list-ul' )) . '</div></a></label>';
            $html .=       '<div id="hpgnav-toc-open" class="nav-dialog-overlay">' . $navlist . '</div>';
            $html .=     '</div>';
            $html .=     '<div class="sub-panel">';

            $html .=     apply_filters( 'hpgnav_additional_nav_icon', '', $postid, $root_pid );                                      

            if(!empty($prev_url) || !empty($next_url)){
                $prev_attr = (empty($prev_url))? 'disabled="disabled"' : 'href="' . esc_url($prev_url) . '" ';
                $next_attr = (empty($next_url))? 'disabled="disabled"' : 'href="' . esc_url($next_url) . '" ';
                $html .=   '<div class="page-panel">';
                $html .=     '<div class="nav-icon icon-prev">';
                $html .=       '<a class="nav-page" ' . $prev_attr . '><span>' . HPGNAV_lib::get_svg( array( 'icon' => 'chevron-left' )) . '</span><span class="icon-guide">' . esc_html__('Prev', 'hpgnav') . '</span></a>';
                $html .=     '</div>';
                $html .=     '<div class="nav-icon icon-next">';
                $html .=       '<a class="nav-page" ' . $next_attr . '><span class="icon-guide">' . esc_html__('Next', 'hpgnav') . '</span><span>' . HPGNAV_lib::get_svg( array( 'icon' => 'chevron-right' )) . '</span></a>';
                $html .=     '</div>';
                $html .=   '</div>';
            }
            $html .=     '</div>';
            $html .=   '</div>';            
            $html .= '</div>';            
        }
        return $html;        
	}

    static function ajax_get_toc() {
        check_ajax_referer( 'hpgnav_get_toc' );
        
        global $post;
        if( isset($_POST['post_id'])){
            $req_pid = absint( $_POST['post_id'] );
            $tochtml = '';
            $db = new HPGNAV_db();                
            $val = $db->get_hpgnav_page($req_pid);
            if(!empty($val)){
                $url = get_page_link( $req_pid );
                $hlist = $val->hlist;
                $toc   = $val->toc;
                if(empty($toc)){
                    $root_pid = $val->root_pid;
                    $m_opt = apply_filters( 'hpgnav_additional_get_root', array(), $root_pid );
                    if(empty($m_opt)){
                        $m_opt = HPGNAV_manager::$m_option;
                    }
                    if($root_pid == $m_opt['root_pid'] && !empty($m_opt['nav_mode'])){
                        $rpost = get_post($req_pid);
                        $hlist = self::h_list( $rpost->post_content, $m_opt['h_tag_depth'] );
                        $tochtml = self::toc_create( $hlist, true, $url );                        
                        $db->set_hpgnav_page( false, $req_pid, $root_pid, $hlist, $tochtml );
                    }
                } else {
                    $tochtml = $val->toc;                    
                }
                if(!empty($tochtml)){
            		if ( !empty( $post->ID ) && $post->ID == $req_pid ){
                        //current page url replace
                        $tochtml = str_replace($url, '', $tochtml);
                    }
                }                
            }
            wp_send_json_success( wp_kses($tochtml, HPGNAV_lib::get_allowed_tags()) );
        }
        wp_die( 0 );        
    }
    
	public static function init() {
        require_once( __DIR__ . '/hpgnav-search.php');
        $hpgnav_toc = new HPGNAV_toc();	    
        $search     = new HPGNAV_search();
    }   
}
add_action( 'init', array( 'HPGNAV_toc', 'init' ) );