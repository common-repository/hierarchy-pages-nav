<?php
/*
  Hierarchy Pages Manager Setting
*/
defined( 'ABSPATH' ) || exit;

class HPGNAV_setting {
    
    static $m_opt;
    static $v_opt;

    public function __construct() {
        self::$m_opt = HPGNAV_manager::$m_option;
        self::$v_opt = HPGNAV_manager::$v_option;

        add_action( 'transition_post_status', array('HPGNAV_setting', 'clear_page_toc'), 10, 3 );
        add_action( 'deleted_post', array('HPGNAV_setting', 'delete_page_toc'), 10, 2 );
        add_action( 'update_option_permalink_structure', array( 'HPGNAV_setting', 'clear_all_toc'));

        if( current_user_can( 'edit_others_pages' ) ) { //editor 編集者以上の権限
            add_action('admin_menu', function(){
                $page = add_options_page('Hierarchy Pages Manager', esc_html__('Hierarchy Pages Navigation', 'hpgnav'), 'edit_others_pages', 'hpgnav-setting', array('HPGNAV_setting', 'my_option_page'));
                add_action('admin_print_scripts-' . $page, array('HPGNAV_setting', 'my_option_scripts'));
                add_action('admin_print_styles-' . $page, array('HPGNAV_setting', 'my_option_css'));
            } ); 
            
            add_action('admin_init', function(){
                //設定オプション更新時の検証用コールバック登録  
                //  引数１：グループ名(settings_fields関数で使用 nonce )
                //  引数２：オプション名(DB update_option() name
                if (isset($_POST['option_page']) && $_POST['option_page'] === 'hpgnav-setting'){
                    check_admin_referer( 'hpgnav-setting-options' );
                    if (isset($_POST['hpgnav_mng'])){
                        register_setting('hpgnav-setting', 'hpgnav_mng',  array('HPGNAV_setting', 'm_options_validate'));                    
                    }
                    if (isset($_POST['hpgnav_view'])){
                        register_setting('hpgnav-setting', 'hpgnav_view', array('HPGNAV_setting', 'v_options_validate'));
                    }                    
                }
            } ); 

            add_action('wp_ajax_hpgnav_list_refresh', array( 'HPGNAV_setting', 'ajax_list_refresh'));        
            add_action('wp_ajax_hpgnav_bulk_action',  array( 'HPGNAV_setting', 'ajax_bulk_action'));        
        }        
    }

    static function my_option_css() {
        wp_enqueue_style("wp-jquery-ui-dialog");
        wp_enqueue_style('wp-color-picker');          
    ?>
    <style type="text/css">            
.hpgnav_logo_bottun {vertical-align: sub;}
.hpgnav_logo_bottun > input.button {margin-left: 12px; vertical-align: top;}
.hpgnav-bulk-action { float:left; margin-bottom:8px; }
.hpgnav-sort-action { float:right; text-align:right; margin-bottom:8px; }
.hpgnav-bulk-action span { margin-left:10px; }
.hpgnav-sort-action span { margin-right:10px; }
.hierarchy-pages-wrap { background:#fff; padding-top:1em; }
#hierarchy-pages { clear:both; min-height:30vh; max-height:80vh; overflow-y:auto; padding:0; margin-bottom:20px; border:1px solid #c3c4c7; background:#fff;}
#hierarchy-pages ul { margin: 0; padding: 0; }
#hierarchy-pages ul.page-list ul{ margin:0 0 8px 24px; }
#hierarchy-pages ul.page-list li{ line-height:2.2; margin-bottom:0; border:1px solid #eee; border-right:none; background:#fff;}
#hierarchy-pages ul.page-list > li:first-child { margin-bottom:0; border:none;}
#hierarchy-pages ul[class*='page-depth'] > li:hover{ cursor:move; }
#hierarchy-pages li.guide-highlight{ border:1px solid #2271b1 !important; background:#f5f5f5;}
#hierarchy-pages .sortable li.mjs-nestedSortable-expanded > .post-info > .dashicons-minus:before { content: "\f347"; color: #2196F3; cursor: pointer;} 
#hierarchy-pages .sortable li.mjs-nestedSortable-collapsed > .post-info > .dashicons-minus:before { content: "\f345"; color: #FF5722; cursor: pointer;}
#hierarchy-pages .sortable li.mjs-nestedSortable-collapsed > ul { display: none;}
.is-child.dashicons { color: transparent; margin: 5px .7em;}
.post-title { margin-left: .7em; white-space:nowrap; overflow:hidden; }
.post-info  { padding-left:10px; }
.post-status{ min-width:200px; max-width:200px; text-align:center; white-space:nowrap; overflow:hidden; float:right; cursor:pointer;}
.v-draft    { background-color:#dcdcde; }
.v-pending  { background-color:mistyrose; }
.v-future   { background-color:oldlace; }
.v-publish  { background-color:honeydew; }
.v-password { background-color:lavender; }
.v-private  { background-color:lightyellow; }
.dlg-select-item { margin:1em; }
.dlg-sub-item { margin:0.5em 1.5em; }
.hierarchy-pages-options { display:flex;}
.widefat.left70 { max-width: 70%; }
.right30 { max-width: 30%;  margin: 0 0 0 1em;}
@media screen and (max-width: 782px){ 
    .regular-text { max-width: 100%; width: 100%; }
    .hierarchy-pages-options { display:block;}
    .widefat.left70 { max-width: 100%; }
    .right30 { max-width: 100%;  margin: 1em 0;}
}
@media screen and (max-width: 476px){
    .widefat td, .widefat th { padding: 8px 4px;}
}
.widefat th { width:25%; }
.widefat p.memo-text { margin:2px; font-size:13px; line-height:1.5em; }
.widefat td.submit {text-align: right;}
.widefat td.submit span { margin-right: 10px;}
.hpgnav-appearance-option select { vertical-align:baseline; }
.hpgnav-appearance-option details { border:1px solid #c3c4c7;}
.hpgnav-appearance-option details table.widefat { border:none; border-top:1px solid #c3c4c7;}
.hpgnav-appearance-option summary {font-size:15px; padding:0.7em; background:#fff; cursor:pointer;}
select.nav-position-select { width:96px; }
    </style>
    <?php
        do_action( 'hpgnav_additional_admin_css' );        
    }
    
    static function my_option_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');        
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_script( 'hpgnav-sortable', plugins_url( 'js/jquery.mjs.nestedSortable.js', __FILE__ ), array(), null, true );
        wp_enqueue_script( 'hpgnav-admin', plugins_url( 'js/hpgnav-admin.js', __FILE__ ), array('wp-i18n'), null, true );         
        wp_set_script_translations( 'hpgnav-admin', 'hpgnav', dirname( __FILE__ ) . '/languages' );

        do_action( 'hpgnav_additional_enqueue_script' );
    }    

    /**
     * dropdown list
     *
     * @param string $name - HTML field name
     * @param array  $items - array of (key => description) to display.  If description is itself an array, only the first column is used
     * @param string $selected - currently selected value
     * @param mixed  $args - arguments to modify the display
     */
    static function dropdown($name, $items, $selected, $args = null, $display = false) {
        $defaults = array(
            'id' => $name,
            'none' => false,
            'class' => null,
            'multiple' => false,
        );

        if (!is_array($items))
            return;

        if (empty($items))
            $items = array();

        // Items is in key => value format.  If value is itself an array, use only the 1st column
        foreach ($items as $key => &$value) {
            if (is_array($value))
                $value = array_shift($value);
        }

        extract(wp_parse_args($args, $defaults));

        // If 'none' arg provided, prepend a blank entry
        if ($none) {
            if ($none === true)
                $none = '&nbsp;';
            $items = array('' => $none) + $items;    // Note that array_merge() won't work because it renumbers indexes!
        }

        if (!$id)
            $id = $name;

        $name  = ($name) ? ' name="' . esc_html($name) . '"' : '';
        $id    = ($id)   ? ' id="'   . esc_html($id)   . '"' : '';
        $class = ($class)? ' class="'. esc_html($class). '"' : '';
        $multiple = ($multiple) ? ' multiple="multiple"' : '';

        $html  = '<select' . $name . $id . $class . $multiple  .'>';
        foreach ((array) $items as $key => $label) {
            $html .= '<option value="' . esc_html($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';
        if($display){
            echo wp_kses( $html, array(
                'select' => array( 'name' => true, 'id' => true, 'class' => true, 'multiple' => true ), 
                'option' => array( 'value' => true, 'selected' => true )
                ));
        } else {
            return $html;
        }
    }

    //===========================================================================================
    //ポストステータスが変化(ソート含む)したら wp_hpgnav_page テーブルの該当TOCデータクリア
    //※設定ページの h_tag_depth オプション変更時は hpgnav-settings.php で該当TOCデータをクリア
    static function clear_page_toc($new_status, $old_status, $post) {
        if(!empty($post->ID) && ! defined( 'DOING_AUTOSAVE' )){
            // draft 下書き / pending 承認待ち / future 予約中 / publish 公開 /  private 非公開 / trash ゴミ箱 / auto-draft 自動保存 / inherit 継承
            if(in_array($new_status, array('publish','private','trash'))){
                $db = new HPGNAV_db();
                $db->clear_toc_hpgnav_page( $post->ID );
            }
        }
    }    

    static function delete_page_toc($postid, $post) {
        if(!empty($postid)){
            $db = new HPGNAV_db();
            $db->delete_hpgnav_page( $postid );
        }
    }

    static function clear_all_toc() {
        $db = new HPGNAV_db();
        $db->clear_all_toc_hpgnav_page( 0 );        
    }
    
    //===========================================================================================
    //wp_ajax called function
    //===========================================================================================
    static function ajax_list_refresh() {
        check_ajax_referer("hpgnav-list-ajax");
        if (isset($_POST['action']) && $_POST['action'] === 'hpgnav_list_refresh') {
            if( !current_user_can( 'edit_others_pages' ) )
                wp_die(-1);

            if (isset($_POST['root_pid'])) {
                $html = '';
                $pid = absint( $_POST['root_pid']);
                if(!empty($pid)) {
                    $pages = HPGNAV_lib::get_hierarchy_pages( $pid, 'publish,private,draft,pending,future' );
                    $html  = HPGNAV_lib::hierarchy_pages_to_html_sortable($pages);
                }                
                $response = array();
                $response['success'] = true;
                $response['data'] = wp_kses($html, HPGNAV_lib::get_allowed_tags());
                wp_send_json( $response );
            }
        } 
        wp_die(0);
    }
    
    static function ajax_bulk_action() {
        check_ajax_referer("hpgnav-bulk-ajax");
        if (isset($_POST['action']) && $_POST['action'] === 'hpgnav_bulk_action') {
            if( !current_user_can( 'edit_others_pages' ) )
                wp_die(-1);
            
            if (isset($_POST['root_pid'])) {
                $html = '';
                $pid = absint( $_POST['root_pid']);
                if(!empty(get_post($pid))){
                    if($_POST['select'] === 'sorted_update'){
                        $json_pages = wp_kses( stripslashes($_POST['ids']), 'strip' );
                        $sort_pages = json_decode( $json_pages, true );
                        if ( JSON_ERROR_NONE === json_last_error() ) {
                            if(isset($sort_pages[0]['id']) && absint($sort_pages[0]['id']) === absint($pid)){
                                if(isset($sort_pages[0]['children'])){
                                    HPGNAV_lib::set_children_order( $pid, $sort_pages[0]['children']);                            
                                }
                            }                        
                        }
                    } else {
                        $ids  = explode(',', trim( wp_kses( stripslashes($_POST['ids']), 'strip' ), ' ,') );
                        $main = wp_kses( stripslashes($_POST['opt_main']), 'strip' );
                        $sub  = wp_kses( stripslashes($_POST['opt_sub']), 'strip' );
                         
                        if($_POST['select'] === 'publish'){
                            foreach ($ids as $id) {
                                $gp = (array)get_post($id);
                                if(!empty($gp) && ($main != $gp['post_status'] || $sub != $gp['post_password']) ){
                                    if(in_array($main, array('publish', 'private', 'password'))){
                                        if($main == 'password'){
                                            $gp['post_status']   = 'publish';
                                            $gp['post_password'] = $sub;
                                        } else {
                                            $gp['post_status']   = $main;
                                            $gp['post_password'] = '';                                        
                                        }                                    
                                        wp_insert_post($gp);                                        
                                    }
                                }
                            }                    
                        } elseif($_POST['select'] === 'unpublish'){
                            foreach ($ids as $id) {
                                $gp = (array)get_post($id);
                                if(!empty($gp) && ($main != $gp['post_status'] || ($main == 'future' && $sub != $gp['post_date'])) ){
                                    if(in_array($main, array('draft', 'pending', 'future'))){
                                        $gp['post_status'] = $main;
                                        if($main == 'future' && !empty($sub)){                                        
                                            $ldate = new DateTime( $sub );
                                            if(!empty($ldate)){
                                                $fdate = $ldate->format('Y-m-d H:i:s');
                                                $gp['post_date'] = wp_resolve_post_date( $fdate );
                                                $gp['post_date_gmt'] = get_gmt_from_date( $gp['post_date'] );                                            
                                            }
                                        }
                                        wp_insert_post($gp);
                                    }
                                }
                            }                    
                        } elseif($_POST['select'] === 'disscussion'){
                            foreach ($ids as $id) {
                                $gp = (array)get_post($id);
                                if(!empty($gp) && ($main != $gp['comment_status'] || $main != $gp['ping_status']) ){
                                    if(in_array($main, array('open', 'closed'))){
                                        $gp['comment_status'] = $main;
                                        $gp['ping_status']    = $main;                                        
                                        wp_insert_post($gp);
                                    }
                                }
                            }
                        }
                    }                    
                    $pages = HPGNAV_lib::get_hierarchy_pages( $pid, 'publish,private,draft,pending,future' );
                    $html  = HPGNAV_lib::hierarchy_pages_to_html_sortable($pages);
                }                
                $response = array();
                $response['success'] = true;
                $response['data'] = wp_kses($html, HPGNAV_lib::get_allowed_tags());                
                wp_send_json( $response );
            }
        } 
        wp_die(0);
    }

    /***************************************************************************
     * 各種設定
     ************************************************************************* */
    static function my_option_page() {
        global $wp_settings_errors;        
        $plugin_info = get_file_data(__DIR__ . '/hierarchy-pages-nav.php', array('Version' => 'Version'), 'plugin');

        $sel_id = 0;
        $mng = HPGNAV_manager::get_default_opt('hpgnav_mng');
        
        //設定更新後のリダイレクトか？
        $is_settings_updated = false;
    	if ( !empty( $wp_settings_errors ) ) {
            foreach ( (array) $wp_settings_errors as $key => $stat ) {
                if ( isset($stat['code']) && $stat['code'] === 'settings_updated') {
                    $is_settings_updated = true;
                    break;
                }
            }            
        }        

        $sel_root = apply_filters( 'hpgnav_additional_select_root', 0 );
        
        HPGNAV_manager::$regist_data = apply_filters( 'hpgnav_additional_get_manage', array() );
        if(empty(HPGNAV_manager::$regist_data) || $is_settings_updated ){
            $mng = self::$m_opt;
            if(!empty(get_post($mng['root_pid']))){
                $sel_id = $mng['root_pid'];
            }
        } elseif( !empty($sel_root) && !empty(get_post($sel_root))){
            $nmng = apply_filters( 'hpgnav_additional_get_root', array(), $sel_root );
            if(!empty($nmng)){
                $sel_id = $sel_root;
                $mng = $nmng; 
            }
        }        
        //site-wide common value
        $mng['pw-expires'] = (!empty(self::$m_opt['pw-expires']))? self::$m_opt['pw-expires'] : 240;                

        if(!empty($sel_id)) {
            $pages = HPGNAV_lib::get_hierarchy_pages( $sel_id, 'publish,private,draft,pending,future' );
        }    
        ?>
        <h2><?php esc_html_e('Hierarchy Pages Navigation', 'hpgnav'); ?><span style='font-size: 13px; margin-left:12px;'><?php echo esc_html("Version {$plugin_info['Version']}"); ?></span></h2>      
        <?php do_action( 'hpgnav_additional_manage' ); ?>
        <form method="post" autocomplete="off" class="wrap" action="options.php">
          <?php 
            settings_fields( 'hpgnav-setting');
            $colum_l = (!class_exists('HPGNAV_mng_setting') && version_compare( $plugin_info['Version'], '1.0.0', '>='))? 'left70' : '';
          ?>
          <div class="hierarchy-pages-options">
              <table class="widefat <?php echo esc_html($colum_l); ?>">
                <tbody>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Navigation', 'hpgnav'); ?></label></th>
                    <td><label><input type="checkbox" name="hpgnav_mng[nav_mode]" value="1" <?php checked($mng['nav_mode'], 1, true); ?> /> <?php esc_html_e('Enable', 'hpgnav'); ?></label></td>
                  </tr>              
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Title', 'hpgnav'); ?></label></th>
                    <td><input type="text" class="regular-text" name="hpgnav_mng[title]" value="<?php echo esc_html($mng['title']); ?>" /></td>
                  </tr>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Logo', 'hpgnav'); ?></label></th>
                    <td>
                        <span id="hpgnav_logo_image">
                        <?php if(!empty($mng['logo'])){
                            echo '<img width="48" height="48" src="' . esc_url($mng['logo']) . '">'; 
                        } ?>
                        </span>
                        <span class="hpgnav_logo_bottun">
                        <input id="hpgnav_logo_btn" type="button" class="button" value=<?php esc_html_e('Select', 'hpgnav'); ?> />
                        <input id="hpgnav_logo_clr" type="button" class="button" value=<?php esc_html_e('Clear', 'hpgnav'); ?> />
                        </span>
                        <input id="hpgnav_logo" type="hidden" name="hpgnav_mng[logo]" value="<?php echo esc_url($mng['logo']); ?>" />
                    </td>
                  </tr>              
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Post ID at the root of hierarchy pages', 'hpgnav'); ?></label></th>
                    <td><input type="text" class="medium-text" name="hpgnav_mng[root_pid]" value="<?php echo esc_html($mng['root_pid']); ?>" /></td>
                  </tr>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Heading tags depth', 'hpgnav'); ?></label></th>
                    <td>
                    <?php $clist = array('1' => esc_html__('h1', 'hpgnav'),
                                         '2' => esc_html__('h1 - h2', 'hpgnav'),
                                         '3' => esc_html__('h1 - h3', 'hpgnav'),
                                         '4' => esc_html__('h1 - h4', 'hpgnav'),
                                         '5' => esc_html__('h1 - h5', 'hpgnav'),
                                         '6' => esc_html__('h1 - h6', 'hpgnav'));
                        self::dropdown('hpgnav_mng[h_tag_depth]', $clist, $mng['h_tag_depth'], null, true );
                    ?>
                    </td>
                  </tr>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Search engine noindex', 'hpgnav'); ?></label></th>
                    <td>
                    <?php $clist = array(''          => esc_html__('disable', 'hpgnav'),
                                         'exc_public' => esc_html__('pages other than public', 'hpgnav'),
                                         'exc_top'    => esc_html__('pages other than top page', 'hpgnav'),
                                         'all'        => esc_html__('all pages', 'hpgnav'));
                        self::dropdown('hpgnav_mng[noindex_type]', $clist, $mng['noindex_type'], null, true );
                    ?>
                    </td>
                  </tr>              
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('User capabilities for post password skip', 'hpgnav'); ?></label></th>
                    <td>
                        <a target="_blank" rel="noopener" href="https://wordpress.org/documentation/article/roles-and-capabilities/"><?php esc_html_e('Capabilities', 'hpgnav'); ?></a><?php esc_html_e(' to skip passwords for password protected pages', 'hpgnav') ?><br>
                        <?php esc_html_e('* Set `read` for all logged in users ', 'hpgnav'); ?><br>                    
                        <input type="text" class="medium-text" name="hpgnav_mng[skip_password]" value="<?php echo esc_html($mng['skip_password']); ?>"  />
                    </td>
                  </tr> 
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Post password cookies expiration', 'hpgnav'); ?></label></th>
                    <td>
                        <span><input name="hpgnav_mng[pw-expires]" type="number" step="1" min="1" max="999" value="<?php echo esc_html($mng['pw-expires']); ?>" class="small-text" /><?php esc_html_e(' (hours)', 'hpgnav'); ?></span><br>
                        <span><?php esc_html_e('* Settings affect password-protected posts site-wide', 'hpgnav') ?></span>
                    </td>
                  </tr>               
                  <tr valign="top">
                    <th scope="row"></th>
                    <td class="submit">
                      <?php do_action( 'hpgnav_additional_button' ); ?>
                      <span><input type="submit" class="button button-primary" name="hpgnav_mng_save" value=<?php esc_html_e('Save', 'hpgnav'); ?> /></span>
                    </td>
                  </tr>
                </tbody>  
              </table>
              <?php if(!empty($colum_l)){ ?>
                <div class="right30">
                  <div style="background-color: #f0fff0; border:1px solid #70c370; padding:4px 20px;" >
                     <p><strong><?php esc_html_e('Introduction of Addon', 'hpgnav'); ?></strong></p>
                     <p><?php esc_html_e('Thank you for using Hierarchy Pages Navigation. We offer addon to manage multiple hierarchical pages. Please consider using Addon!', 'hpgnav'); ?></p>
                     <p><?php esc_html_e('See more information ', 'hpgnav'); ?><a target="_blank" rel="noopener" href="https://celtislab.net/en/wp-plugin-hpgnav/"> Hierarchy Pages Navigation</a></p>
                  </div>
                </div>                    
              <?php } ?> 
          </div>
        </form>        
        <div class="hierarchy-pages-wrap wrap">
            <form id="hpgnav-bulk-action-form" method="post" autocomplete="off">
                <div class="hpgnav-bulk-action">
                  <span class="hpgnav-list-all"><label><input type='checkbox' name='hpgnav-all-select' onclick="HPGNAV_all_select()"/>All</label></span>
                  <span class="hpgnav-action-select">
                  <?php
                    $clist = array( '' => esc_html__('Bulk Actions', 'hpgnav'), 'publish' => esc_html__('Publish', 'hpgnav'), 'unpublish' => esc_html__('Unpublish', 'hpgnav'), 'disscussion' => esc_html__('Discussion status', 'hpgnav'));
                    self::dropdown('hpgnav_bulk_action', $clist, '', null, true );
                  ?>
                  <span><input type="submit" name="hpgnav_bulk_apply" class="button action" value="<?php esc_html_e('Apply', 'hpgnav') ?>" onclick="HPGNAV_bulkactionDialog('<?php echo esc_html(wp_create_nonce('hpgnav-bulk-ajax')); ?>');return false;" /></span>
                  </span>
                </div>
                <div class="hpgnav-sort-action">
                  <span><input type="submit" name="hpgnav_list_refresh" class="button action" value="<?php esc_html_e('Refresh list', 'hpgnav') ?>" onclick="HPGNAV_list_refresh('<?php echo esc_html(wp_create_nonce('hpgnav-list-ajax')); ?>');return false;" /></span>
                  <span><input type="submit" name="hpgnav_list_update" class="button action" value="<?php esc_html_e('Sorted list update', 'hpgnav') ?>" onclick="HPGNAV_list_update('<?php echo esc_html(wp_create_nonce('hpgnav-bulk-ajax')); ?>');return false;" /></span>
                </div>                
                <div id="hierarchy-pages">
                <?php if (!empty($pages)) {
                    HPGNAV_lib::hierarchy_pages_to_html_sortable($pages, true);
                } ?>
                </div>
            </form>
        </div>

        <div id="hpgnav-publish-dialog" title="<?php esc_html_e('Publish', 'hpgnav'); ?>" style="display : none;">
            <form id="hpgnav-publish-form">
                <p><strong><?php esc_html_e('Swith to publish - Bulk set publish status of selected pages', 'hpgnav'); ?></strong></p>
                <fieldset>             
                    <div class="dlg-select-item"><label><input type="radio" name="p-mode" value="publish" checked /><?php esc_html_e('Public - Visible to everyone', 'hpgnav'); ?></label></div>                
                    <div class="dlg-select-item"><label><input type="radio" name="p-mode" value="private" /><?php esc_html_e('Private - Visible to admins and editors role', 'hpgnav'); ?></label></div>
                    <div class="dlg-select-item">
                        <label><input type="radio" name="p-mode" value="password" /><?php esc_html_e('Password protected - Password required to view', 'hpgnav'); ?></label>
                        <div class="dlg-sub-item">
                            <input name="p-password" type="text" placeholder="Use a secure password" value="">
                            <p><?php esc_html_e('*Specific login users can be excluded by using password-skip option', 'hpgnav'); ?></p>
                        </div>
                    </div>
                </fieldset>        
            </form>
        </div>
        <div id="hpgnav-unpublish-dialog" title="<?php esc_html_e('Unpublish', 'hpgnav'); ?>" style="display : none;">
            <form id="hpgnav-unpublish-form">
                <p><strong><?php esc_html_e('Switch to unpublish - Bulk set status of selected pages to draft/pending/future', 'hpgnav'); ?></strong></p>
                <fieldset>             
                    <div class="dlg-select-item"><label><input type="radio" name="u-mode" value="draft" checked /><?php esc_html_e('Draft - Switch to draft', 'hpgnav'); ?></label></div>                
                    <div class="dlg-select-item"><label><input type="radio" name="u-mode" value="pending" /><?php esc_html_e('Pending - Switch to pending', 'hpgnav'); ?></label></div>
                    <div class="dlg-select-item">
                        <label><input type="radio" name="u-mode" value="future" /><?php esc_html_e('Future - Switch to future', 'hpgnav'); ?></label>
                        <div class="dlg-sub-item"><input name="future-datetime" type="datetime-local" value="" /></div>
                    </div>
                </fieldset>        
            </form>
        </div>
        <div id="hpgnav-discussion-dialog" title="<?php esc_html_e('Discussion status', 'hpgnav'); ?>" style="display : none;">
            <form id="hpgnav-discussion-form">
                <p><strong><?php esc_html_e('Discussion - Bulk set status of selected pages to comments/pingbacks/trackbacks', 'hpgnav'); ?></strong></p>
                <fieldset>             
                    <div class="dlg-select-item"><label><input type="radio" name="d-mode" value="closed" checked /><?php esc_html_e('Close', 'hpgnav'); ?></label></div>
                    <div class="dlg-select-item"><label><input type="radio" name="d-mode" value="open"  /><?php esc_html_e('Open', 'hpgnav'); ?></label></div>                
                </fieldset>        
            </form>
        </div>
        <div id="hpgnav-sorted-dialog" title="<?php esc_html_e('Sorted list update', 'hpgnav'); ?>" style="display : none;">
            <form id="hpgnav-sorted-form">
                <p><strong><?php esc_html_e('Click OK to save sorted data to wp posts database.', 'hpgnav'); ?></strong></p>
            </form>
        </div>
        
        <div class="hpgnav-appearance-option wrap">
            <details>
            <summary><?php esc_html_e('Navigation appearance settings', 'hpgnav'); ?></summary>            
            <form method="post" autocomplete="off" action="options.php">
              <?php 
                settings_fields( 'hpgnav-setting');
                $opt = self::$v_opt;
              ?>
              <table class="widefat">
                <tbody>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Color', 'hpgnav'); ?></label></th>
                    <td>
                      <fieldset>
                          <p><input type="text" id="hpgnav_color" name="hpgnav_view[color]" value="<?php echo esc_html($opt['color']); ?>" data-default-color="#555"><span class="hpgnav_color"><?php esc_html_e('Navi icon', 'hpgnav'); ?></span></p>                   
                          <p><input type="text" id="hpgnav_background" name="hpgnav_view[background]" value="<?php echo esc_html($opt['background']); ?>" data-default-color="#fff"><span class="hpgnav_color"><?php esc_html_e('Background', 'hpgnav'); ?></span></p>                   
                          <p><input type="text" id="hpgnav_border" name="hpgnav_view[border]" value="<?php echo esc_html($opt['border']); ?>" data-default-color="#555"><span class="hpgnav_color"><?php esc_html_e('Border', 'hpgnav'); ?></span></p>                   
                      </fieldset>                    
                    </td>
                  </tr>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Position', 'hpgnav'); ?></label></th>
                    <td>
                      <p><?php
                          $pos_x = array('left' => esc_html__('Left', 'hpgnav'), 'right' => esc_html__('Right', 'hpgnav'));
                          self::dropdown('hpgnav_view[pos_x]', $pos_x, $opt['pos_x'], array('class'=>'nav-position-select'), true );
                          echo '<span> <input name="hpgnav_view[offset_x]" type="number" step="1" min="0" max="1000" value="' . esc_html($opt['offset_x']) . '" class="small-text" /> </span>';
                          self::dropdown('hpgnav_view[unit_x]', array('px'=>'px', '%'=>'%', 'vw'=>'vw', 'vh'=>'vh'), $opt['unit_x'], null, true );
                      ?>                      
                      </p>                      
                      <p><?php
                          $pos_y = array('top' => esc_html__('Top', 'hpgnav'), 'bottom' => esc_html__('Buttom', 'hpgnav'));
                          self::dropdown('hpgnav_view[pos_y]', $pos_y, $opt['pos_y'], array('class'=>'nav-position-select'), true );
                          echo '<span> <input name="hpgnav_view[offset_y]" type="number" step="1" min="0" max="1000" value="' . esc_html($opt['offset_y']) . '" class="small-text" /> </span>';
                          self::dropdown('hpgnav_view[unit_y]', array('px'=>'px', '%'=>'%', 'vw'=>'vw', 'vh'=>'vh'), $opt['unit_y'], null, true );
                      ?>                      
                      </p>
                    </td>
                  </tr>
                  <tr valign="top">
                    <th scope="row"><label><?php esc_html_e('Scroll action', 'hpgnav'); ?></label></th>
                    <td><label><input type="checkbox" name="hpgnav_mng[scroll_action]" value="1" <?php checked($opt['scroll_action'], 1, true); ?> /> <?php esc_html_e('Hide navigation on scroll up, show on scroll down.', 'hpgnav'); ?></label></td>
                  </tr>
                  <tr valign="top">
                    <th scope="row"></th>
                    <td class="submit">
                      <span><input type="submit" class="button button-primary" name="hpgnav_view_save" value=<?php esc_html_e('Save', 'hpgnav'); ?> /></span>
                    </td>                      
                  </tr>
                </tbody>
              </table>
            </form>
        </details>
        </div>
        <p><strong><?php esc_html_e('[Important]', 'hpgnav'); ?></strong></p>
        <ol class="setting-notice">
            <li><?php esc_html_e('When using the page cache, set the cache expiration period within 12 hours (to support Ajax communication). Also, exclude private and password protected pages from caching.', 'hpgnav'); ?></li>
            <li><?php esc_html_e('Navigation automatically determines whether it is for editors or above (public, private) or general (public), depending on the viewing users permissions.', 'hpgnav'); ?></li>
            <li><?php esc_html_e('Using css nesting. Please use the latest version of the browser that supports css nesting.', 'hpgnav'); ?></li>
        </ol>        
        <?php
    }

    /**
    *  入力データのチェックと更新 update_option hpgnav_mng / hpgnav_view が実行される
    */
    static function m_options_validate($input) {
        $default = HPGNAV_manager::get_default_opt('hpgnav_mng');
        $pid     = (isset( $input['root_pid']))? absint($input['root_pid']) : $default['root_pid'];
                                
        $input['nav_mode']      = ( !empty($input['nav_mode'])) ? 1 : 0 ;
        $input['root_pid']      = ( !empty(get_post($pid)))? $pid : $default['root_pid'];
        $input['title']         = ( !empty($input['title']))? wp_kses( stripslashes($input['title']), 'strip' ) : $default['title'];
        $input['logo']          = ( !empty($input['logo']))? esc_url($input['logo']) : $default['logo'];
        $input['h_tag_depth']   = ( $input['h_tag_depth'] >= '1' && $input['h_tag_depth'] <= '6')? $input['h_tag_depth'] : $default['h_tag_depth'];
        $input['noindex_type']  = ( in_array($input['noindex_type'], array('exc_public', 'exc_top', 'all'))) ?  $input['noindex_type'] : $default['noindex_type'];
        $input['skip_password'] = ( !empty($input['skip_password']))? wp_kses( stripslashes($input['skip_password']), 'strip' ) : $default['skip_password'];
        $input['pw-expires']    = ( !empty($input['pw-expires'])) ? absint($input['pw-expires']) : $default['pw-expires'];

        if(!empty($pid)){
            //新規または root_pid 変更時更新
            $pages = HPGNAV_lib::get_hierarchy_pages( $pid, 'publish,private' );
            if(is_array($pages)){
                $db = new HPGNAV_db();                
                foreach($pages as $id => $pg){
                    $val = $db->get_hpgnav_page($id);
                    $new = (is_object($val))? false : true;
                    $hlist = (!$new)? $val->hlist : array();
                    $toc   = (!$new)? $val->toc : '';
                    if($new || $pid != $val->root_pid){
                        $db->set_hpgnav_page( $new, $id, $pid, $hlist, $toc );
                    }
                }
            }
        }
        if(!empty(self::$m_opt['root_pid']) && $pid == self::$m_opt['root_pid']){
            //h_tag_depth 変更時はそのルートIDに属するすべてのTOCをクリア
            if($input['h_tag_depth'] != self::$m_opt['h_tag_depth']){
                $db = new HPGNAV_db();                
                $db->clear_all_toc_hpgnav_page( $pid );
            }
        }        

        do_action( 'hpgnav_additional_set_manage', $input);        

        return $input;        
    }
    static function v_options_validate($input) {
        $default = HPGNAV_manager::get_default_opt('hpgnav_view');
        $offsetx = (is_numeric($input['offset_x']))? absint($input['offset_x']) : $default['offset_x']; 
        $offsety = (is_numeric($input['offset_y']))? absint($input['offset_y']) : $default['offset_y']; 

        $input['color']         = ( !empty(sanitize_hex_color( $input['color'] )))? sanitize_hex_color($input['color']) : $default['color']; 
        $input['background']    = ( !empty(sanitize_hex_color( $input['background'] )))? sanitize_hex_color($input['background']) : $default['background']; 
        $input['border']        = ( !empty(sanitize_hex_color( $input['border'] )))? sanitize_hex_color($input['border']) : $default['border']; 
        $input['pos_x']         = ( in_array( $input['pos_x'], array('left', 'right') )) ? $input['pos_x'] : $default['pos_x'];
        $input['pos_y']         = ( in_array( $input['pos_y'], array('top', 'buttom') )) ? $input['pos_y'] : $default['pos_y'];
        $input['offset_x']      = ( $offsetx >= 0 && $offsetx <= 1000)? $offsetx : $default['offset_x'];
        $input['offset_y']      = ( $offsety >= 0 && $offsety <= 1000)? $offsety : $default['offset_y'];
        $input['unit_x']        = ( in_array( $input['unit_x'], array('px', '%', 'vw', 'vh') )) ? $input['unit_x'] : $default['unit_x'];
        $input['unit_y']        = ( in_array( $input['unit_y'], array('px', '%', 'vw', 'vh') )) ? $input['unit_y'] : $default['unit_y'];
        $input['scroll_action'] = ( !empty($input['scroll_action'])) ? 1 : 0 ;            

        return $input;
    }
}
