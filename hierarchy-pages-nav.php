<?php
/*
  Plugin Name: Hierarchy Pages Nav
  Plugin URI: https://celtislab.net/en/wp-plugin-hpgnav/
  Description: Document hierarchy pages like a book and provide easy navigation between pages.
  Author: enomoto@celtislab
  Requires at least: 6.2
  Tested up to: 6.4
  Requires PHP: 7.4 
  Version: 0.9.7
  Text Domain: hpgnav
  License: GPLv2
*/
//ナビは編集者以上用（publish,private）と通常用（publish）の２種類
//wp_hpgnav_page テーブルに TOC データなければ（初回表示またはAjax要求時に）そのページのTOCを生成保存
//記事の登録/更新時に wp_hpgnav_page テーブルの該当 TOC データクリア
//目次プラグイン（JSによるページ内スクロール機能）等を使用している場合は干渉する場合あり

defined( 'ABSPATH' ) || exit;

require_once( __DIR__ . '/hpgnav-db.php');

if(is_admin()) {
    register_activation_hook( __FILE__,   'hpgnav_plugin_activation' );
    register_deactivation_hook( __FILE__, 'hpgnav_plugin_deactivation' );
    register_uninstall_hook(__FILE__,     'hpgnav_plugin_uninstall');
}

//プラグイン有効時の処理
function hpgnav_plugin_activation() {
    $db = new HPGNAV_db();
    $db->table_create();
}

//プラグイン無効時の処理
function hpgnav_plugin_deactivation() {   
}

//プラグイン削除時の処理
function hpgnav_plugin_uninstall() {
    $db = new HPGNAV_db();
    $db->table_drop();
    delete_option('hpgnav_mng');
    delete_option('hpgnav_view');
}


class HPGNAV_manager {

    static $m_option;    
    static $v_option;    
    static $regist_data;
    
    public function __construct() {
        require_once( __DIR__ . '/hpgnav-utils.php');
        require_once( __DIR__ . '/hpgnav-toc.php');

        self::$m_option = wp_parse_args( get_option('hpgnav_mng', array()),  self::get_default_opt('hpgnav_mng'));
        self::$v_option = wp_parse_args( get_option('hpgnav_view', array()), self::get_default_opt('hpgnav_view'));       
        self::$regist_data = array();
            
        if (is_admin()) {            
            require_once( __DIR__ . '/hpgnav-setting.php');            
            $setting = new HPGNAV_setting();
        }
    }
    
    public static function get_default_opt($type) {
        if($type == 'hpgnav_mng'){
            return array( 
                'nav_mode'      => true,
                'root_pid'      => '',
                'title'         => '',
                'logo'          => plugins_url( 'img/hpgnav-logo-64x64.png', __FILE__ ),
                'h_tag_depth'   => '2',
                'noindex_type'  => '',
                'skip_password' => '',
                'pw-expires'    => 240
            );            
        } elseif($type == 'hpgnav_view'){
            return array( 
                'pos_x'         => 'left',
                'pos_y'         => 'bottom',
                'offset_x'      => 16,
                'offset_y'      => 128,
                'unit_x'        => 'px',
                'unit_y'        => 'px',
                'color'         => '#fff',
                'border'        => '#bbdefb',
                'background'    => '#1e88e5',
                'scroll_action' => ''
            );            
        }
        return false;
    }

    public static function init() {
        $hpgnav_manager = new HPGNAV_manager;
        load_plugin_textdomain('hpgnav', false, basename( dirname( __FILE__ ) ).'/languages' );
    }
}
add_action( 'plugins_loaded', array( 'HPGNAV_manager', 'init' ) );
