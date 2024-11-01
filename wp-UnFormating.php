<?php
/*
Plugin Name: WP-UnFormating
Plugin URI: http://www.belive.jp/archives/wp-unformating-1_0/
Description: WP-UnFormating is a simple plugin. As a result, the content enclosed with special tag releases the format.
Author: Masarki Kondo
Version: 1.2.2
Author URI: http://www.belive.jp/
Update: 2008.03.26 I gathered some functions and some variables in class.
Update: 2008.03.26 I was optional and was able to set a parameter.The option supports template tags.
Update: 2008.05.07 I revised it so that '$n' was not converted.
Update: 2008.05.07 I was able to apply this plugin in "the_content" first.
Update: 2008.05.09 Bug Fix. When there was square brackets in options, malfunction occurred.
*/

/**
    @class : WordPress UnFormating Class
**/
class WP_UnFormating_class{

    // class variable
    var $buffer = array();   // escape data
    var $cnt = 0;            // escape counter
    var $subbuf = array();   // sub escape data
    var $optkey = array();   // unformat option
    
    /**
        @function : Constractor for PHP 4
        @param : void
        @return : void
    **/
    function WP_UnFormating_class(){

        // escape 4 -> -1 -> -9999
        add_action('the_content', array(&$this, 'escape'), -9999);
        add_action('the_excerpt', array(&$this, 'escape'),-9999);
        add_action('the_content_rss', array(&$this, 'escape') ,-9999);
        add_action('the_excerpt_rss', array(&$this, 'escape') ,-9999);

        // restore 99 -> 9999
        add_action('the_content', array(&$this, 'restore') ,9999);
        add_action('the_excerpt', array(&$this, 'restore') ,9999);
        add_action('the_content_rss', array(&$this, 'restore') ,9999);
        add_action('the_excerpt_rss', array(&$this, 'restore') ,9999);
    }

    /**
        @function : Constractor
        @param : void
        @return : void
    **/
    function __construct($addpath=false){
        $this->WP_UnFormating_class($addpath);
    }

    /**
        @function : Make replacement label
        @param : key Label key
        @param : num Applay number
        @return : label
    **/
    function mkLabel($key, $num){
        $ntxt = sprintf('%05d', $num);
        $res = "#####_{$key}{$ntxt}_#####";
        return $res;
    }

    /**
        @function : Data Escape
        @param : text An original text
        @return : An escaped text
    **/
    function escape($text){
        $this->buffer = array();
        $reg = '#\[unformat(.*)(?=\[\/unformat[^\]]*\])\[\/unformat[^\]]*\]#Usi';
        $text = preg_replace_callback(
            $reg,
            array( &$this, 'escape_cb'), $text);
        return $text;
    }

    /**
        @function : Data Escape Callback
        @param : matches The arrangement of the matching result
        @return : An escaped code
    **/
    function escape_cb($matches){
        if( !is_array( $this->buffer)) $this->buffer = array();
        $this->cnt++;
        $this->buffer[$this->cnt] = $matches[0];
        $res = $this->mkLabel('WPUFMT', $this->cnt);
        return $res;
    }

    /**
        @function : PHP-CODE Escape Callback
        @param : matches The arrangement of the matching result
        @return : An escaped code
    **/
    function php_escape($matches){
        if( !is_array( $this->subbuf['php'])) $this->subbuf['php'] = array();
        $last = count( $this->subbuf['php']);
        $this->subbuf['php'][] = $matches[0];
        $res = $this->mkLabel('WPUFMT-PHP', $last);
        return $res;
    }

    /**
        @function : Tag Escape Callback
        @param : matches The arrangement of the matching result
        @return : An escaped code
    **/
    function tag_escape($matches){
        if( !is_array( $this->subbuf['tag'])) $this->subbuf['tag'] = array();
        $last = count( $this->subbuf['tag']);
        $this->subbuf['tag'][] = $matches[0];
        $res = $this->mkLabel('WPUFMT-TAG', $last);
        return $res;
    }

    /**
        @function : Data Escape Callback
        @param : matches The arrangement of the matching result
        @return : An escaped code
    **/
    function qt_escape($matches){
        if( !is_array( $this->subbuf['qt'])) $this->subbuf['qt'] = array();
        $last = count( $this->subbuf['qt']);
        $this->subbuf['qt'][] = $matches[0];
        $res = $this->mkLabel('WPUFMT-QT', $last);
        return $res;
    }

    /**
        @function : Data Restore
        @param : text An escaped text
        @return : A changed text
    **/
    function restore($text){
        for($i=0; $i<=$this->cnt;$i++){
            if( !isset( $this->buffer[ $i])) continue;
    
            // PHP Escape
            $reg = '#<[\s\t]*\?php.*\?>#Us';
            $this->buffer[ $i] = preg_replace_callback(
                $reg,
                array( &$this, 'php_escape'), $this->buffer[ $i]);
    
            // Tag Escape
            $reg = '#<[^>]+>#Us';
            $this->buffer[ $i] = preg_replace_callback(
                $reg,
                array( &$this, 'tag_escape'), $this->buffer[ $i]);
    
            // Quotation Escape
            $reg = '#"[^"]*"#Us';
            $this->buffer[ $i] = preg_replace_callback(
                $reg,
                array( &$this, 'qt_escape'), $this->buffer[ $i]);
    
            // context
            $reg = '#\[unformat([^]]*)\](.*)\[\/unformat[^]]*\]#Usi';
            $this->buffer[ $i] = preg_replace_callback(
                $reg,
                array( &$this, 'restore_cb'), $this->buffer[ $i]);
    
            // Main Restore
            $base = $this->mkLabel('WPUFMT', $i);
            $text = str_replace( $base, $this->buffer[ $i], $text);
        }
        return $text;
    }

    /**
        @function : Data Restore Callback
        @param : matches The arrangement of the matching result
        @return : A changed text
    **/
    function restore_cb($matches){
        $opts = array();
        
        // options
        $matches[1] = trim( $matches[1]);
        $matches[1] = preg_replace( '/[\s\t]*=[\s\t]*/', '=', $matches[1]);
        $arr = preg_split( '/[\s\t\r\n]+/', $matches[1]);
        foreach( $arr as $line){
            if( empty( $line)) continue;
            $aArr = explode( '=', $line, 2);
            if( !is_array( $aArr) || empty( $aArr)) continue;
            $aArr[1] = $this->qt_restore( $aArr[1],1);
            $aArr[1] = $this->tag_restore( $aArr[1]);
            ob_start();
            eval(' ?>' . $this->php_restore( $aArr[1]) . '<?php ');
            $aArr[1] = ob_get_contents();
            ob_end_clean();
            $opts[ $aArr[0]] = $aArr[1];
        }
    
        // contents
        $res = $matches[2];
        $res = $this->qt_restore( $res);
        $res = $this->tag_restore( $res);
        $res = $this->php_restore( $res);
        if( is_array( $opts)){
            foreach( $opts as $key => $val){
//                if( $val === null) continue;
                if( isset($this->optkey[ $key])) continue;
                $res = str_replace( $key, $val, $res);
            }
        }
        return $res;
    }

    /**
        @function : PHP-CODE Restore Callback
        @param : text An escaped text
        @param : clr buffer clear
        @return : A changed text
    **/
    function php_restore($text, $clr=false){
        if( !is_array( $this->subbuf['php'])) return $text;
        foreach( $this->subbuf['php'] as $key => $val){
            $val = preg_replace('/<[\s\t]*\?php/', '<?php', $val);
            $rpl = $this->mkLabel('WPUFMT-PHP', $key);
            $text = str_replace( $rpl, $val, $text);
        }
        if( $clr) $this->subbuf['php'] = array();
        return $text;
    }

    /**
        @function : Tag Restore Callback
        @param : text An escaped text
        @param : clr buffer clear
        @return : A changed text
    **/
    function tag_restore($text, $clr=false){
        if( !is_array( $this->subbuf['tag'])) return $text;
        foreach( $this->subbuf['tag'] as $key => $val){
            $rpl = $this->mkLabel('WPUFMT-TAG', $key);
            $text = str_replace( $rpl, $val, $text);
        }
        if( $clr) $this->subbuf['tag'] = array();
        return $text;
    }

    /**
        @function : Quote Restore Callback
        @param : text An escaped text
        @param : trim Quotation clear
        @param : clr buffer clear
        @return : A changed text
    **/
    function qt_restore($text, $trim=false, $clr=false){
        if( !is_array( $this->subbuf['qt'])) return $text;
        foreach( $this->subbuf['qt'] as $key => $val){
            $rpl = $this->mkLabel('WPUFMT-QT', $key);
            $text = str_replace( $rpl, $val, $text);
        }
        if( $trim) $text = trim( $text, '"');
        if( $clr) $this->subbuf['qt'] = array();
        return $text;
    }

    /**
        @function : Data Restore
        @param : text An escaped text
        @return : A changed text
    **/
    function phpver_later($version){
        $res = false;
        if( function_exists('version_compare')){
            $res = version_compare( PHP_VERSION, $version, '>');
        }
        else{
            $ver1 = PHP_VERSION;
            $ver2 = $version;
            $ver1 = strtoupper( $ver1);
            $ver2 = strtoupper( $ver2);
            foreach( array('_', ',', '-', '+', ' ') as $src){
                $ver1 = str_replace( $src, '.', $ver1);
                $ver2 = str_replace( $src, '.', $ver2);
            }
            $revs = array(
                        '/dev/i' => '.-5.',
                        '/alpha/i' => '.-4.',
                        '/beta/i' => '.-3.',
                        '/rc/i' => '.-2.',
                        '/pl\./i' => '.-1.',
                        '/a/i' => '.-4.',
                        '/b/i' => '.-3.',
                        '/[^\-\.0-9]+/' => ''
                    );
            foreach( $revs as $key => $val){
                $ver1 = preg_replace( $key, $val, $ver1);
                $ver2 = preg_replace( $key, $val, $ver2);
            }
            $arr1 = explode('.', $ver1);
            reset( $arr1);
            $cnt1 = count( $arr1);
            $arr2 = explode('.', $ver2);
            reset( $arr2);
            $cnt2 = count( $arr2);
            $cnt = ( $cnt1 < $cnt2) ? $cnt2 : $cnt1;
            for( $i=0; $i<$cnt; $i++){
                if( $arr1[$i] > $arr2[$i]){
                    $res = true;
                    break;
                }
                else
                if( $arr1[$i] == $arr2[$i]){
                    $res = true;
                }
                else
                {
                    $res = false;
                    break;
                }
            }
        }
        return $res;
    }

}

// Call WP_UnFormating_class
new WP_UnFormating_class();

?>
