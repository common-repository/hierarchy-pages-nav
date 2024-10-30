<?php
/**
 * Hierarchy Pages Navigation utility functions
 *
 */

defined( 'ABSPATH' ) || exit;

class HPGNAV_lib {

    public function __construct() {}

    //nestedSortable.js nestedSortable('toHierarchy') result
    static function set_children_order( $parent_id, $children) {
        $parent = $parent_id;
        foreach ($children as $order => $pg) {
            if(isset($pg['id'])){
                $pid = absint( $pg['id'] );              
                $gp  = (array)get_post( $pid );
                if(!empty($gp) && ($parent_id != $gp['post_parent'] || $order != $gp['menu_order']) ){
                    //post_parent and menu_order update
                    $gp['post_parent'] = $parent_id;
                    $gp['menu_order']  = $order;
                    wp_insert_post($gp);
                }
                if(isset($pg['children'])){
                    self::set_children_order( $pid, $pg['children']);
                }
            }
        }
    }
        
    //front page    : post_status 'publish,private' or 'publish'
    //settings page : post_status 'publish,private,draft,pending,future'
    static function get_hierarchy_pages( $root_post_id, $post_status ) {
        $pages = array();        
        $root = get_post($root_post_id);
        if(!empty($root) && !empty($root->post_type)){
            $args = array(
                'child_of' => $root_post_id,
                'sort_column' => 'menu_order',
                'hierarchical' => 1,
                'post_type' => $root->post_type,
                'post_status' => $post_status 
            );

            for($i=0; $i<=5; $i++){
                $depth[$i] = array();                
            }
            $child = get_pages($args);
            $list = array_merge( array($root), $child);
            foreach($list as $pg){
                $arpg = (array)$pg;
                $dn = 0;
                if(empty($pg->post_parent)){
                    if(!in_array($pg->ID, $depth[0])){
                        $depth[0][] = $pg->ID;                            
                    }
                } else {
                    $dn = 5; //Max depth
                    for($i=0; $i<5; $i++){
                        if(in_array($pg->post_parent, $depth[$i])){
                            $dn = $i + 1;
                            break;
                        }                            
                    }
                    if(!in_array($pg->ID, $depth[$dn])){
                        $depth[$dn][] = $pg->ID;                            
                    }                        
                }
                $arpg['depth']  = $dn;
                $pages[$pg->ID] = $arpg;
            }
        }
        return $pages;
    }

    //ソータブルリストの個別ページ部を出力
    static function sortable_page_item( $page ) {
        $editurl = get_edit_post_link( $page['ID'] );
        $visibility = 'v-draft';
        $post_status = esc_html__('draft', 'hpgnav');
        if($page['post_status'] == 'publish'){
            if(empty($page['post_password'])){
                $visibility = 'v-publish';
                $post_status = esc_html__('public', 'hpgnav');
            } else {
                $visibility = 'v-password';
                $post_status = esc_html__('password', 'hpgnav') . " : {$page['post_password']}";
            }
        } elseif($page['post_status'] == 'private'){
            $visibility = "v-private";
            $post_status = esc_html__('private', 'hpgnav');
        } elseif($page['post_status'] == 'pending'){
            $visibility = "v-pending";
            $post_status = esc_html__('pending', 'hpgnav');
        } elseif($page['post_status'] == 'future'){
            $visibility = "v-future";
            $post_status = esc_html__('future', 'hpgnav');
        }
        $tooltip  = "[Post ID] {$page['ID']}" . PHP_EOL;
        $tooltip .= "[Author] {$page['post_author']}" . PHP_EOL;
        $tooltip .= "[Discussion] commen:{$page['comment_status']} ping:{$page['ping_status']}" . PHP_EOL;
        
        $item = '<div class="post-info ' . $visibility . '">';
        $item .= '<label><input type="checkbox" id="post-' . absint($page['ID']) . '" class="post-checkbox" value="" /></label>';
        $item .= '<span class="post-title"><a target="_blank" rel="noreferrer noopener" href="' . esc_url($editurl) .'">' . esc_html($page['post_title']) . '</a></span>';
        $item .= '<span class="is-child dashicons dashicons-minus"></span>';      
        $item .= '<span class="post-status" title="'. esc_html($tooltip) .'">' . esc_html($post_status) . '</span>';
        $item .= '</div>';
        return $item;
    }
    
    //階層ページ配列データを html list へ変換
    static function hierarchy_pages_to_html_sortable( $pages, $display=false ) {
        $list = '';
        if(is_array($pages)){
            $list = '<ul class="sortable page-list">';
            $cdepth = 0;
            $ulopen = 1;
            $liopen = 0;
            foreach($pages as $pid => $pg){
                if($pg['depth'] > $cdepth ){
                    $cdepth = $pg['depth'];
                    $list .= '<ul class="page-depth-' . absint($cdepth) . '">';    
                    $list .= '<li id="page-' . absint($pg['ID']) . '">' . self::sortable_page_item( $pg );
                    $ulopen++;
                    $liopen++;
                } elseif($pg['depth'] < $cdepth ){
                    for($dp=$cdepth; $pg['depth'] < $dp; $dp--){
                        $list .= '</ul>';    
                        $ulopen--;                        
                    }
                    $cdepth = $pg['depth'];
                    $list .= '</li>';    
                    $list .= '<li id="page-' . absint($pg['ID']) . '">' . self::sortable_page_item( $pg );
                } else {
                    if($liopen > 0){
                        $list .= '</li>';
                        $liopen--;
                    }
                    $list .= '<li id="page-' . absint($pg['ID']) . '">' . self::sortable_page_item( $pg );
                    $liopen++;
                }
            } 
            for(; $liopen > 0; $liopen--){
                $list .= '</li>';
            }
            for(; $ulopen > 0; $ulopen--){
                $list .= '</ul>';    
            }                
        }
        if($display){
            echo wp_kses($list, HPGNAV_lib::get_allowed_tags());
        } else {
            return $list;
        }          
    }
    
    /**
     * Return SVG markup.
     *
     * @param array $args {
     *     @type string $icon  Required SVG icon name.
     *     @type string $title Optional SVG title.
     * }
     * @return string SVG markup.
     * 
     * Example : get_svg( array( 'icon' => 'arrow-right', 'title' => esc_html__( 'This is the title', 'textdomain' ) ) );
     * タイトルはアクセシビリティ機能の音声読み上げ対応設定
     */
    public static function get_svg( $args = array(), $display = false, $prefix = 'navicon-' ) {
        if ( empty( $args ) || false === array_key_exists( 'icon', $args )) {
            return '';
        }
        $args = wp_parse_args( $args, array( 'icon' => '', 'title' => '' ) );
        
        $ic_title = (!empty($args['title']))? sanitize_text_field( $args['title'] ) : '';
        $ic_type  = (!empty($args['icon']))? sanitize_text_field( $args['icon'] ) : '';

        // Set aria hidden
        $aria_hidden = ' aria-hidden="true"';
        // Set ARIA
        $aria_labelledby = '';
        if ( !empty($ic_title) ) {
            $aria_hidden     = '';
            $unique_id       = uniqid();
            $aria_labelledby = ' aria-labelledby="title-' . esc_html($unique_id) . '"';
        }

        // SVG markup
        $svg = '<svg class="svgicon icon-' . esc_html($ic_type) . '"' . esc_html($aria_hidden) . esc_html($aria_labelledby) . ' role="img">';
        if ( $args['title'] ) {
            $svg .= '<title id="title-' . esc_html($unique_id) . '">' . esc_html($ic_title) . '</title>';
        }
        $svg .= ' <use xlink:href="#' . esc_html($prefix) . esc_html($ic_type) . '"></use> ';
        $svg .= '</svg>';
        if($display){
            echo wp_kses($svg, HPGNAV_lib::get_allowed_tags());
        } else {
            return $svg;
        }    
    }
    
    //hpgnav ナビゲーション HTML用の許可タグデータ
    public static function get_allowed_tags() {    
        global $allowedposttags;
        $allowed_tags = $allowedposttags;
        // SVG,form,input,select タグを許可
        $allowed_tags['svg']    = array( 'version' => true, 'xmlns' => true, 'xmlns:xlink' => true );
        $allowed_tags['use']    = array( 'xlink:href' => true );
        $allowed_tags['defs']   = array();
        $allowed_tags['symbol'] = array( 'viewbox' => true );
        $allowed_tags['path']   = array( 'd' => true, 'fill' => true );
        $allowed_tags['form']   = array( 'action' => true, 'accept' => true, 'accept-charset' => true, 'enctype' => true, 'method' => true, 'name' => true,	'target' => true );                
        $allowed_tags['input']  = array( 'type' => true, 'name' => true, 'value' => true, 'checked' => true);
        $allowed_tags['select'] = array( 'name' => true, 'multiple' => true );
        $allowed_tags['option'] = array( 'value' => true, 'selected' => true );        
        $allowed_tags['button'] = array( 'href' => true, 'onclick' => true);
        $allowed_tags['a']['onclick'] = true;
        $allowed_tags = array_map( '_wp_add_global_attributes', $allowed_tags );
        return $allowed_tags;
    }
}
