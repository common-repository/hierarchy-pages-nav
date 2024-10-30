

(function () { 
    
    const { __ } = wp.i18n;   
    
    HPGNAV_sortable_apply = function(){
        jQuery('ul.sortable').nestedSortable({
            forcePlaceholderSize: true,
            handle: 'div',
            listType: 'ul',
            items: 'li',
            opacity: .6,
            placeholder: 'guide-highlight',
            revert: 250,
            tolerance: 'pointer',
            toleranceElement: '> div',                
            maxLevels: 5,
            isTree: true,
            protectRoot: true,
            excludeRoot: true
        });

        //panel list open/close
        jQuery('.is-child.dashicons').on('click', function() {
            jQuery(this).closest('li').toggleClass('mjs-nestedSortable-collapsed').toggleClass('mjs-nestedSortable-expanded');
        });    
    };

    HPGNAV_all_select = function() {
        const stat = document.querySelector('input[name=hpgnav-all-select]');
        const item = document.querySelectorAll('.post-checkbox');
        for (let i = 0; i < item.length; i++) {
            item[i].checked = stat.checked;
        }
    };
    
    _get_checked_ids = function() {
        let ids = '';
        const item = document.querySelectorAll('.post-checkbox');
        for (let i = 0; i < item.length; i++) {
            if(item[i].checked){
                ids += item[i].id.replace('post-', '') + ',';
            }
        }            
        return ids;
    };

    _bulk_action_ajax = function( nonce, action, idlist, radio, subopt ) {
        jQuery.ajax({ 
            type: 'POST', 
            url: ajaxurl, 
            data: { 
                action: "hpgnav_bulk_action",
                select: action, 
                root_pid: jQuery("input[name='hpgnav_mng[root_pid]']").val(),
                ids: idlist,
                opt_main: radio,
                opt_sub: subopt,
                _ajax_nonce: nonce,
                _ajax_plf: "hierarchy-pages-nav,hierarchy-pages-nav-addon",
            }, 
            dataType: 'json', 
        }).then(
            function (response, dataType) {
                if(response.data !== ''){ 
                    jQuery('#hierarchy-pages').html(response.data); 
                    HPGNAV_sortable_apply();
                } else { alert( response.msg ); }
            },
            function () { /* alert("ajax error"); */ }
        );                
        return;
    };

    HPGNAV_list_refresh = function( nonce ){ 
        jQuery.ajax({ 
            type: 'POST', 
            url: ajaxurl, 
            data: { 
                action: "hpgnav_list_refresh",
                root_pid: jQuery("input[name='hpgnav_mng[root_pid]']").val(),
                _ajax_nonce: nonce,
                _ajax_plf: "hierarchy-pages-nav,hierarchy-pages-nav-addon",                
            }, 
            dataType: 'json', 
        }).then(
            function (response, dataType) {
                if(response.data !== ''){ 
                    jQuery('#hierarchy-pages').html(response.data);
                    HPGNAV_sortable_apply();
                } else { alert( response.msg ); }
            },
            function () { /* alert("ajax error"); */ }
        );                 
    };
    
    HPGNAV_list_update = function( nonce ){   
        jQuery( "#hpgnav-sorted-dialog" ).dialog({
            dialogClass : 'wp-dialog',
            modal       : true,
            autoOpen    : true,
            resizable   : false,
            draggable   : false,
            height      : 'auto',
            width       : '400',
            buttons :
            [{
                text: __("Cancel", 'hpgnav'),
                click: function() {
                    jQuery('#hpgnav-sorted-dialog').dialog('close');  
                }
            },
            {
                text: __("O K", 'hpgnav'),
                click: function() {
                    const pages = JSON.stringify( jQuery('ul.sortable.page-list').nestedSortable('toHierarchy') );
                    _bulk_action_ajax( nonce, 'sorted_update', pages, '', '' );
                    jQuery('#hpgnav-sorted-dialog').dialog( "close" );
                }
            }]
        });
    };

    HPGNAV_bulkactionDialog = function( nonce ){
        const ids = _get_checked_ids();
        if( ids == ''){
            return;
        }
        const bulksel = jQuery("select[name='hpgnav_bulk_action']").val();
        if( bulksel == 'publish'){
            jQuery( "#hpgnav-publish-dialog" ).dialog({
                dialogClass : 'wp-dialog',
                modal       : true,
                autoOpen    : true,
                resizable   : false,
                draggable   : false,
                height      : 'auto',
                width       : '400',
                buttons :                      
                [{
                    text: __("Cancel", 'hpgnav'),
                    click: function() {
                        jQuery('#hpgnav-publish-dialog').dialog('close');  
                    }
                },
                {
                    text: __("O K", 'hpgnav'),
                    click: function() {
                        const stat   = document.querySelector('input[name=p-mode]:checked').value;
                        const subopt = document.querySelector('input[name=p-password]').value;
                        _bulk_action_ajax( nonce, 'publish', ids, stat, subopt );
                        jQuery('#hpgnav-publish-dialog').dialog( "close" );
                    }
                }]                
            });                    
        } else if( bulksel == 'unpublish'){
            jQuery( "#hpgnav-unpublish-dialog" ).dialog({
                dialogClass : 'wp-dialog',
                modal       : true,
                autoOpen    : true,
                resizable   : false,
                draggable   : false,
                height      : 'auto',
                width       : '400',
                buttons :
                [{
                    text: __("Cancel", 'hpgnav'),
                    click: function() {
                        jQuery('#hpgnav-unpublish-dialog').dialog('close');  
                    }
                },
                {
                    text: __("O K", 'hpgnav'),
                    click: function() {
                        const stat   = document.querySelector('input[name=u-mode]:checked').value;
                        const subopt = document.querySelector('input[name=future-datetime]').value;
                        _bulk_action_ajax( nonce, 'unpublish', ids, stat, subopt );
                        jQuery('#hpgnav-unpublish-dialog').dialog( "close" );
                    }
                }]                
            });                    
        } else if( bulksel == 'disscussion'){
            jQuery( "#hpgnav-discussion-dialog" ).dialog({
                dialogClass : 'wp-dialog',
                modal       : true,
                autoOpen    : true,
                resizable   : false,
                draggable   : false,
                height      : 'auto',
                width       : '400',
                buttons :
                [{
                    text: __("Cancel", 'hpgnav'),
                    click: function() {
                        jQuery('#hpgnav-discussion-dialog').dialog('close');  
                    }
                },
                {
                    text: __("O K", 'hpgnav'),
                    click: function() {
                        const stat = document.querySelector('input[name=d-mode]:checked').value;
                        _bulk_action_ajax( nonce, 'disscussion', ids, stat, '' );
                        jQuery('#hpgnav-discussion-dialog').dialog( "close" );
                    }
                }]                
            });                    
        }
        return;
    };            

    jQuery(document).ready(function() { 
        HPGNAV_sortable_apply();

        //Logo select / clear
        jQuery('#hpgnav_logo_btn').click(function (e) {
            e.preventDefault();
            const hpgnav_logo_selectFileFrame = wp.media({
                title: __('Select Logo (suggested size : 48x48 px)', 'hpgnav'),
                button: { text: __('select', 'hpgnav'), multiple: false }, 
                library: { type: 'image'}
            });
            hpgnav_logo_selectFileFrame.open();
            hpgnav_logo_selectFileFrame.on('select', function () {
                const attachment = hpgnav_logo_selectFileFrame.state().get('selection').first().toJSON();
                jQuery('#hpgnav_logo').val(attachment.url);
                jQuery('#hpgnav_logo_image').html('<img width="48" height="48" src="' + attachment.url + '">');                          
            });
            return false;
        });
        jQuery('#hpgnav_logo_clr').click(function(e) {
            e.preventDefault();
            jQuery('#hpgnav_logo').val('');
            jQuery('#hpgnav_logo_image').empty();
        });                      
        
        //navi color setting
        jQuery('#hpgnav_color').wpColorPicker();
        jQuery('#hpgnav_background').wpColorPicker();
        jQuery('#hpgnav_border').wpColorPicker();        
    });
}());
