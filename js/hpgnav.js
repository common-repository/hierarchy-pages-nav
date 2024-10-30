/* 
 * Page Navigation - table of contents and search function
 * 
 *  Version: 1.0.0
 *  Author: enomoto@celtislab
 *  Author URI: https://celtislab.net/
 *  License: GPLv2* 
 */

window.addEventListener('DOMContentLoaded', function(){ 

    let autoscroll = 0;
    let last_pos   = -1;    
    let h_target   = '';
   
    //デバウンシング : 200ms の間にスクロールが停止した場合のみ実行    
    function scroll_stop() {
        const current_pos = window.scrollY;
        const isTop    = current_pos <= 200;
        const isBottom = current_pos >= document.body.offsetHeight - window.innerHeight - 300;      
        const el_nav   = document.querySelector('.hpgnav-display');
        
        if(isTop || isBottom || last_pos < 0){
            //top + 200 または bottom - 300 の位置以内なら常に表示
            if( el_nav.classList.contains('hide') ){
                el_nav.classList.remove('hide');
            }
            autoscroll = 0;
        } else if( autoscroll === 0 ) {
            if(last_pos + 100 < current_pos){
                //scroll down 100px以上なら非表示
                const target_t = getComputedStyle(document.querySelector('#hpgnav-toc-open'), ":target");
                const target_s = getComputedStyle(document.querySelector('#hpgnav-search-dialog'), ":target");                
                if( !el_nav.classList.contains('hide') && target_t.display != 'block' && target_s.display != 'block' ){
                    el_nav.classList.add('hide');
                }
            } else if(last_pos - 50 > current_pos){  
                //scroll up   50px以上なら表示
                if( el_nav.classList.contains('hide') ){
                    el_nav.classList.remove('hide');
                }
            }
        }
        if(autoscroll > 0){
            autoscroll--;
        }
        last_pos = current_pos;
    }
    function debounce(func, delay) {
        let debounceTimeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(function() {
                func.apply(context, args);
            }, delay);
        };
    }
    if(hpgnav_set['scroll_action'] === 1){
        window.addEventListener('scroll', debounce(scroll_stop, 200));
    }


    //ページ内指定位置へのスムーズスクロール(ズレ対策リトライ)
    const links = document.querySelectorAll('.toc-link');
    for (let i = 0; i < links.length; i++) {
        links[i].addEventListener('click', function() {
            autoscroll = 1;
            h_target   = '';
            let retry  = 0;
            if(this.hash === ''){
                //Page Top
                const top = window.pageYOffset;
                if(top > 10){
                    window.scrollTo({ behavior: "smooth", top: 0 });
                }
            } else {
                //交差監視
                h_target = document.querySelector( this.hash );
                retry = 10;
                const observer = new IntersectionObserver((entries) => { 
                    for(const e of entries){
                        if(e.isIntersecting){
                            retry = 0;
                        }
                    }
                });
                observer.observe( h_target );
            }
            //スクロール終了監視
            let timeoutId;
            function is_scrollend() {
                if(retry > 0) {
                    clearTimeout( timeoutId ) ;
                    timeoutId = setTimeout( function () {
                        if (retry > 0 && h_target !== '') {
                            //レジーロードなどでスクロール終了が交差外だった場合の補正
                            retry--;
                            autoscroll++;
                            h_target.scrollIntoView({behavior: "smooth", block: "start", inline: "nearest"});
                        }
                    }, 500 ) ;
                } else {
                    if(h_target !== ''){
                        //最後にもう一回補正
                        autoscroll++;
                        h_target.scrollIntoView({behavior: "smooth", block: "start", inline: "nearest"});
                    }
                    window.removeEventListener('scroll', is_scrollend, false);            
                }
            }
            window.addEventListener('scroll', is_scrollend, false);            
        }, false);
    }

    //目次リストのオープン
    HPGNAV_nav_toc_open = function () {
        //current-toc class clear
        const current_toc_link = document.querySelector('.nav-page-list a.toc-link.current-toc');
        if(current_toc_link){
            current_toc_link.classList.remove('current-toc');
        }        
        // 表示範囲にあるTOC要素取得
        const el_toc = document.querySelectorAll('.toc-anchor');
        let current_toc = null;
        if(el_toc.length > 0){
            const view_top = window.scrollY;
            const view_bottom = view_top + window.innerHeight;

            // TOC位置情報計算
            const el_toc_p = Array.from(el_toc).map(span => span.closest(':is(h1, h2, h3, h4, h5, h6)'));
            let tocPositions = [];
            el_toc_p.forEach(function(el, index) {
              const el_rect = el.getBoundingClientRect();
              const el_top  = el_rect.top + view_top;
              const el_bottom = el_top + el_rect.height;

              //h1-6 tag の paddingを考慮
              const computedStyle = window.getComputedStyle(el);
              const pos_top_offset = parseFloat(computedStyle.paddingTop);
              const pos_bottom_offset = parseFloat(computedStyle.paddingBottom);
  
              tocPositions.push({
                top: el_top + pos_top_offset,
                bottom: el_bottom - pos_bottom_offset
              });
            });

            for (let i = 0; i < tocPositions.length; i++) {
              const toc = tocPositions[i];
              
              if (view_bottom < toc.top &&  i === 0) {
                // 表示エリアの最後の位置が最初のTOC位置より小さい場合は対象のTOCなし
                break;
              } else if (view_top <= toc.top && view_bottom >= toc.bottom) {
                // 表示エリア内に表示されている最初のTOC
                current_toc = el_toc_p[i];
                break;
              } else if (view_top >= toc.top && view_bottom < tocPositions[i + 1]?.top) {
                // 表示エリア内にTOCが表示されていない場合は上部に隠れたTOC
                current_toc = el_toc_p[i];
                break;
              } else if (view_top >= toc.top && i === tocPositions.length - 1) {
                // 表示エリアのトップ位置が最後のTOC位置より大きい場合は最後のTOC
                current_toc = el_toc_p[i];
                break;
              }
            }
            if(current_toc !== null){
                //current-toc class add
                const el_child = current_toc.querySelector('span.toc-anchor');
                if(el_child){
                    const toc_link = document.querySelector('.nav-page-list.current-page a.toc-link[href="#' + el_child.id + '"]');
                    if(toc_link){
                        if( !toc_link.classList.contains('current-toc') ){
                            toc_link.classList.add('current-toc');
                        }                        
                    }
                }
            }
        }        
        location.href= '#hpgnav-toc-open';
    }

    //details タブオープン時の処理
    const tabs = document.querySelectorAll('.nav-page-list > details');
    for (let i = 0; i < tabs.length; i++) {
        tabs[i].addEventListener('toggle', function(event) {
            if (this.open) {
                const summary = this.querySelector('summary');
                if(summary !== null){
                    const id = summary.id.match(/nav\-page\-([0-9]+)/);
                    if( id !== null ){
                        const toc = this.querySelector('#toc-' + id[1]);
                        if(toc !== null && toc.innerHTML === ''){
                            let params = new URLSearchParams();
                            params.append("action", "hpgnav_get_toc");
                            params.append("post_id", id[1] );
                            params.append("_ajax_nonce", hpgnav_set['nonce']);
                            params.append("_ajax_plf", "hierarchy-pages-nav,hierarchy-pages-nav-addon");
                            fetch( hpgnav_set['ajax_url'],{ method:'POST', credentials:"same-origin", body:params })
                            .then( function(response){
                                if(response.ok) {
                                    return response.json();
                                }
                                throw new Error('Network response was not ok.');
                            })
                            .then( function(json) {
                                if(json.data !== ''){
                                    toc.innerHTML = json.data;
                                }
                            })                    
                            .catch( function(error){
                            })                            
                        }                        
                    }                    
                }            
            }
        });
    }
    
    //検索
    let s_input   = '';
    let s_pattern = '';
    let s_flags   = '';
    let s_result  = '';
    if (window.sessionStorage) {
        s_input   = window.sessionStorage.getItem('hpgnav_search_input');
        s_pattern = window.sessionStorage.getItem('hpgnav_search_pattern');
        s_flags   = window.sessionStorage.getItem('hpgnav_search_flags');
        s_result  = window.sessionStorage.getItem('hpgnav_search_result');

        const s_type_input = document.querySelectorAll('input[name=s-type]');
        const gettype = window.sessionStorage.getItem('hpgnav_search_type');
        if(gettype == 'strict' || gettype == 'gen_ci' || gettype == 'uni_ci'){
            for(let el of s_type_input) {
                if(el.value == gettype){
                    el.checked = true;                    
                } else {
                    el.checked = false;
                }
            }            
        }        
        for(let el of s_type_input) {
            el.addEventListener('change',function(){
                const type = document.querySelector('input[name=s-type]:checked').value;
                window.sessionStorage.setItem('hpgnav_search_type', type);
            });            
        }
        if(s_input){
            document.querySelector('#hpgnav-search-field').value = s_input;
            if(s_result){
                document.querySelector('#hpgnav-search-result').innerHTML = s_result;
            }            
        }
    }
    
    function set_search_cache( input, pattern, flags, result){
        if (window.sessionStorage) {
            window.sessionStorage.setItem('hpgnav_search_input', input);
            window.sessionStorage.setItem('hpgnav_search_pattern', pattern);
            window.sessionStorage.setItem('hpgnav_search_flags', flags);            
            window.sessionStorage.setItem('hpgnav_search_result', result);
        }
    }
    
    HPGNAV_reload_sameurl = function (link) {
        const ch_offset   = location.href.indexOf('#') || location.href.length;
        const current_url = location.href.substr(0, ch_offset);

        const target_href = link.getAttribute("href");
        const th_offset   = target_href.indexOf('#') || target_href.length;
        const target_url  = target_href.substr(0, th_offset);
        
        if (current_url === target_url) {
            location.hash = '#search_highlight'; 
            location.reload();
        }
    }    
                
    HPGNAV_search_submit = function( nonce, post_id, root_pid ){
        const search_button = document.querySelector('#hpgnav-search-submit');
        if(! search_button.getAttribute('disabled')){
            search_button.setAttribute('disabled', true);
            const search_type = document.querySelector('input[name=s-type]:checked').value;
            const search_key  = document.querySelector('#hpgnav-search-field').value;
            const match_list  = document.querySelector('#hpgnav-search-result');
            if( search_key === "" ){
                match_list.innerHTML = '';
                set_search_cache( '', '', '', '' );
                search_button.removeAttribute('disabled');
            } else if( search_key.search( /[<>]/g ) !== -1 ){
                match_list.innerHTML = hpgnav_set['disallowed'];
                set_search_cache( '', '', '', '' );
                search_button.removeAttribute('disabled');
            } else {               
                match_list.innerHTML = hpgnav_set['searching'];
                let params = new URLSearchParams();
                params.append("action", "hpgnav_search");
                params.append("post_id", post_id );
                params.append("root_pid", root_pid );
                params.append("s_type", search_type );        
                params.append("s_key", search_key );        
                params.append("_ajax_nonce", nonce );
                params.append("_ajax_plf", "hierarchy-pages-nav,hierarchy-pages-nav-addon");
                fetch( hpgnav_set['ajax_url'],{ method:'POST', credentials:"same-origin", body:params })
                .then( function(response){
                    if(response.ok) {
                        return response.json();
                    }
                    throw new Error('response error.');
                })
                .then( function(json) {
                    match_list.innerHTML = '';
                    if(json.data !== ''){
                        match_list.innerHTML = json.data;
                    }
                    set_search_cache( search_key, json.pattern, json.flags, json.data);
                    search_button.removeAttribute('disabled');
                })                    
                .catch( function(error){
                    match_list.innerHTML = error;            
                    set_search_cache( '', '', '', '' );
                    search_button.removeAttribute('disabled');
                })            
            }  
        }
    }
    
    //ハイライト表示 - メインコンテンツ内のテキストノードを再帰的に検索
    let highlight = location.hash
    let s_root_content = document.querySelector('#content');
    if(!s_root_content){
        s_root_content = document.querySelector('main');
        if(!s_root_content){
            s_root_content = document.querySelector('[id*="post-"]');
        }
    }
    if(s_root_content && highlight === '#search_highlight' && s_pattern !== ''){
        try {
            highlightTextNodes( s_root_content, s_pattern, s_flags);
        } catch (error) {
            console.error('hightTextNodes function error', error);
        }        
    }

    function highlightTextNodes(element, s_pattern, s_flags) {
        if (element.nodeType === Node.TEXT_NODE) {
            const text = element.textContent;
            const highlightedText = text.replace(new RegExp(s_pattern, s_flags), '<mark class="search-highlight">$&</mark>');
            if (text !== highlightedText) {
                //置き換え後の要素を元の要素前に挿入してから元の要素を取り除く
                const wrapper = document.createElement('div');
                wrapper.innerHTML = highlightedText;
                const highlightedNodes = Array.from(wrapper.childNodes);
                highlightedNodes.forEach( function(highlightedNode) {
                    element.parentNode.insertBefore(highlightedNode, element);
                });
                element.parentNode.removeChild(element);
            }                
        } else {
            const childNodes = element.childNodes;
            for (let i = 0; i < childNodes.length; i++) {
                const child = childNodes[i];
                if(!child.classList || child.classList.contains('search-highlight') === false){
                    highlightTextNodes( child, s_pattern, s_flags);
                }
            }
        }
    }
});    
