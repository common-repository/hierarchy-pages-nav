<?php
/**
 * Hierarchy Pages Navigation database functions
 *
 */

class HPGNAV_db {

    private $table_mng, $table_page;
    
    public function __construct() {
        global $wpdb;
        $this->table_mng  = $wpdb->prefix . 'hpgnav_manager';
        $this->table_page = $wpdb->prefix . 'hpgnav_page';
    }

    /**
     * テーブルの存在確認
     * 
     * @return boolean
     */
    public function is_table_exist() {
        global $wpdb;
        $is_table = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_mng));
        if($is_table == $this->table_mng){
            $is_table = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_page));
            if($is_table == $this->table_page){
                return true;
            }
        }
        return false;
    }

    /**
     * データベーステーブル作成
     */
    public function table_create() {

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_mng (
            root_pid bigint(20) NOT NULL,
            nav_mode varchar(20) DEFAULT '1' NOT NULL,
            title varchar(256) DEFAULT '' NOT NULL,
            logo varchar(512)  DEFAULT '' NOT NULL,
            h_tag_depth varchar(20) DEFAULT '2' NOT NULL,
            noindex_type varchar(20) DEFAULT '' NOT NULL,
            skip_password varchar(512) DEFAULT '' NOT NULL,
            UNIQUE KEY root_pid (root_pid)            
        ) $charset_collate;";
        dbDelta( $sql );

        $sql = "CREATE TABLE $this->table_page (
            post_id bigint(20) unsigned NOT NULL,
            root_pid bigint(20) NOT NULL,
            hlist text DEFAULT '' NOT NULL,
            toc text DEFAULT '' NOT NULL,
            UNIQUE KEY post_id (post_id)            
        ) $charset_collate;";
        dbDelta( $sql );
    }

    /**
     * データベーステーブル削除
     */
    public function table_drop() {
        global $wpdb;
		$wpdb->query( $wpdb->prepare("DROP TABLE IF EXISTS %i", $this->table_mng) );
		$wpdb->query( $wpdb->prepare("DROP TABLE IF EXISTS %i", $this->table_page) );
    }

    /**
     * 管理データ取得
     * 
     * @param $root_pid root post ID
     * @return object 
     */
    public function get_hpgnav_mng($root_pid = 0) {
        global $wpdb;
        if(empty($root_pid)){ //all
            $obj = $wpdb->get_results( $wpdb->prepare("SELECT * FROM %i ORDER BY root_pid ASC", $this->table_mng) );
        } else {
            $obj[0] = $wpdb->get_row( $wpdb->prepare("SELECT * FROM %i WHERE root_pid = %d", $this->table_mng, $root_pid) );
        }
        return $obj;
    }
    
    /**
     * 管理データ登録/更新
     */
    public function set_hpgnav_mng($mng) {
        global $wpdb;
        if(empty($mng['root_pid'])){
            return false;   
        }
        $count = $wpdb->get_var( $wpdb->prepare("SELECT COUNT( root_pid ) FROM %i", $this->table_mng) );
        $val = $this->get_hpgnav_mng($mng['root_pid']);
        if(empty($val[0])){
            $res = $wpdb->query( $wpdb->prepare("INSERT INTO %i (root_pid, nav_mode, title, logo, h_tag_depth, noindex_type, skip_password ) VALUES ( %d, %s, %s, %s, %s, %s, %s)",
                $this->table_mng, $mng['root_pid'], $mng['nav_mode'], $mng['title'], $mng['logo'], $mng['h_tag_depth'], $mng['noindex_type'], $mng['skip_password'] ) );
            $count++;
        } else {
            $res = $wpdb->query( $wpdb->prepare("UPDATE %i SET nav_mode = %s, title = %s, logo = %s, h_tag_depth = %s, noindex_type = %s, skip_password = %s WHERE root_pid = %d",
                $this->table_mng, $mng['nav_mode'], $mng['title'], $mng['logo'], $mng['h_tag_depth'], $mng['noindex_type'], $mng['skip_password'], $mng['root_pid'] ) );
        }
        return $count;
    }

    /**
     * 管理データ削除
     */
    public function delete_hpgnav_mng( $root_pid ) {
        global $wpdb;
        $res = $wpdb->query( $wpdb->prepare("DELETE FROM %i WHERE root_pid = %s", $this->table_mng,  $root_pid ));
        $res = $wpdb->query( $wpdb->prepare("DELETE FROM %i WHERE root_pid = %s", $this->table_page, $root_pid ));
    }

    /**
     * ページ関連データ取得
     * 
     */
    public function get_hpgnav_page($post_id) {
        global $wpdb;
        $obj = $wpdb->get_row( $wpdb->prepare("SELECT * FROM %i WHERE post_id = %d", $this->table_page, $post_id) );
        if(is_object($obj)){
            if(!empty($obj->hlist)){
                $obj->hlist = maybe_unserialize( $obj->hlist );
            }
            return $obj;
        }
        return false;
    }

    public function get_all_hpgnav_page($root_pid) {
        global $wpdb;
        $obj = $wpdb->get_results( $wpdb->prepare("SELECT * FROM %i WHERE root_pid = %s", $this->table_page, $root_pid) );
        if(is_array($obj)){
            $pobj = array();
            foreach($obj as $i => $p){
                if(!empty($p->hlist)){
                    $p->hlist = maybe_unserialize( $p->hlist );
                }
                $pobj[$p->post_id] = $p;
            }
            return $pobj;
        }
        return false;
    }
    
    /**
     * ページ関連データ登録/更新
     */
    public function set_hpgnav_page($new, $pid, $root_pid, $hlist, $toc ) {
        global $wpdb;
        if(is_array($hlist) && !empty($hlist)){
            $s_hlist = maybe_serialize( $hlist );    
        } else {
            $s_hlist = '';
        }
        if($new){
            $res = $wpdb->query( $wpdb->prepare("INSERT INTO %i (post_id, root_pid, hlist, toc) VALUES ( %d, %d, %s, %s)", $this->table_page, $pid, $root_pid, $s_hlist, $toc ) );
        } else {
            $res = $wpdb->query( $wpdb->prepare("UPDATE %i SET root_pid = %d, hlist = %s, toc = %s WHERE post_id = %d", $this->table_page, $root_pid, $s_hlist, $toc, $pid) );
        }
        return $res;
    }

    /**
     * ページ関連データ削除
     */
    public function delete_hpgnav_page( $post_id ) {
        global $wpdb;
        $res = $wpdb->query( $wpdb->prepare("DELETE FROM %i WHERE post_id = %s", $this->table_page, $post_id ));
    }    
    public function clear_toc_hpgnav_page( $post_id ) {
        global $wpdb;
        $res = $wpdb->query( $wpdb->prepare("UPDATE %i SET hlist = '', toc = '' WHERE post_id = %d", $this->table_page, $post_id ));
    }    
    
    public function delete_all_hpgnav_page( $root_pid ) {
        global $wpdb;
        $res = $wpdb->query( $wpdb->prepare("DELETE FROM %i WHERE root_pid = %s", $this->table_page, $root_pid ));
    }    
    public function clear_all_toc_hpgnav_page( $root_pid ) {
        global $wpdb;
        if(!empty($root_pid)){
            $res = $wpdb->query( $wpdb->prepare("UPDATE %i SET hlist = '', toc = '' WHERE root_pid = %d", $this->table_page, $root_pid ));
        } else {
            $res = $wpdb->query( $wpdb->prepare("UPDATE %i SET hlist = '', toc = ''", $this->table_page) );            
        }
    }    
}
