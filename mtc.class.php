<?php
/**
 * My Two Cents - A simple comment system
 * 2005 (c) Andreas Gohr <andi@splitbrain.org>
 */


if(!defined('MTC')) define('MTC','MTC'); // used for all POST/GET vars and CSS classes

// a string to be added to the gravatar url
// see http://gravatar.com/implement.php#section_1_1
if(!defined('GRAVATAR_OPTS')) define('GRAVATAR_OPTS','&amp;rating=R');


class MTC {
    
    var $db_host = 'localhost';
    var $db_user = 'mtc';
    var $db_pass = 'mtc';
    var $db_name = 'mtc';

    // no modifications below
    var $message = '';
    var $db_link;
    var $page;
    var $addcss = true;
    var $blacklist = 'blacklist.txt';

    /**
     * To be called in the head section. Handles intializing
     * and POST/GET variables
     */
    function init($page = ''){
        if(!$page){
            $this->page = $_SERVER['PHP_SELF'];
        }else{
            $this->page = $page;
        }

        if(get_magic_quotes_gpc()){
            if (!empty($_POST[MTC]))    $this->_remove_magic_quotes($_POST[MTC]);
            if (!empty($_GET[MTC]))     $this->_remove_magic_quotes($_POST[MTC]);
            if (!empty($_REQUEST[MTC])) $this->_remove_magic_quotes($_POST[MTC]);
        }

        echo $this->print_css();        

        if($_POST[MTC]['do'] == 'add'){
            $this->_add_comment();
        }

    }

    /**
     * List available comments
     */
    function comments(){
        $page = md5($this->page);

        $sql = "SELECT id, name, mail, text, date
                  FROM mtc_comments
                 WHERE page = '$page'
              ORDER BY date";

        $handle = $this->_get_dbhandle();
        if(!$handle) return;
        $result = mysql_query($sql,$handle);

        while ($row = mysql_fetch_assoc($result)) {
            $this->format_comment($row);
        }
        mysql_free_result($result);
    }

    /**
     * Show the form to add new comments
     */
    function comment_form(){
        echo '<div class="'.MTC.'_form" id="'.MTC.'_form">';
        $this->_print_message();
        echo '<form action="#'.MTC.'_form" method="post" accept-charset="utf-8">';
        echo '<input type="hidden" name="'.MTC.'[do]" value="add" />';
        echo '<input type="hidden" name="'.MTC.'[page]" value="'.htmlspecialchars($this->page).'" />';
      
        echo '<div class="'.MTC.'_name">';
        echo '<label for="'.MTC.'_name">Your Name:</label>';
        echo '<input type="text" name="'.MTC.'[name]" value="'.htmlspecialchars($_POST[MTC]['name']).'" id="'.MTC.'_name" />';
        echo '</div>';

        echo '<div class="'.MTC.'_mail">';
        echo '<label for="'.MTC.'_mail">Your E-Mail:</label>';
        echo '<input type="text" name="'.MTC.'[mail]" value="'.htmlspecialchars($_POST[MTC]['mail']).'" id="'.MTC.'_mail" />';
        echo '</div>';

        echo '<div class="'.MTC.'_text">';
        echo '<label for="'.MTC.'_text">Your two Cents:</label>';
        echo '<textarea cols="40" rows="10" name="'.MTC.'[text]" id="'.MTC.'_text">'.htmlspecialchars($_POST[MTC]['text']).'</textarea>';
        echo '</div>';

        echo '<input type="submit" value="Save" />';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Defines how a comment is printed. You may want to tweak this
     */
    function format_comment($row){
        static $number = 0;
        $number++;

        $md5 = md5($row['mail']);
        $obf = strtr($row['mail'],array('@' => ' [at] ', '.' => ' [dot] ', '-' => ' [dash] '));

        $text = htmlspecialchars($row['text']);
        $text = preg_replace('/  /',' &nbsp;',$text);
        $text = preg_replace('/((https?|ftp):\/\/[\w-?&;#~=\.\/\@]+[\w\/])/ui',
                             '<a href="\\1" target="_blank" rel="nofollow">\\1</a>',
                             $text);
        $text = nl2br($text);

        echo '<div class="'.MTC.'_comment">';
        echo '<a href="#'.MTC.'_'.$number.'" id="'.MTC.'_'.$number.'" class="'.MTC.'_link">'.$number.'</a>';
        echo '<img src="http://www.gravatar.com/avatar.php?gravatar_id='.$md5.GRAVATAR_OPTS.'" alt="" />';

        echo '<div class="'.MTC.'_text">';
        echo $text;
        echo '</div>';

        echo '<div class="'.MTC.'_info">';

        echo '<div class="'.MTC.'_date">';
        echo $row['date'];
        echo '</div>';

        echo '<div class="'.MTC.'_user">';
        echo '<a href="mailto:'.htmlspecialchars($obf).'">';
        echo htmlspecialchars($row['name']);
        echo '</a>';
        echo '</div>';

        echo '</div>';

        echo '<div class="'.MTC.'_clear"></div>';
        echo '</div>';
    }

    /**
     * Prints minimal CSS for initial styling
     */
    function print_css(){
        if(!$this->addcss) return;
        echo '<style type="text/css">';
        echo '.'.MTC."_message { text-align: center; color: #f00 }";

        echo '#'.MTC."_form input { margin-left: 50px; display: block; width: 250px; }";
        echo '#'.MTC."_form textarea { margin-left: 50px; display: block; width: 550px; }";
        echo '#'.MTC."_form div.".MTC."_name { float: left; }";
        echo '#'.MTC."_form div.".MTC."_mail { float: left; }";
        echo '#'.MTC."_form div.".MTC."_text { clear: left; }";

        echo 'div.'.MTC."_comment { border-bottom: 1px solid #000; margin-top: 0.5em; }";
        echo 'div.'.MTC."_comment img { float:left; z-index: 10}";
        echo 'div.'.MTC."_comment a.".MTC."_link { text-decoration: none; position: absolute; color: #999; font-size: 1.5em; z-index:50; float: left; font-weight: bold;}";
        echo 'div.'.MTC."_comment div.".MTC."_date { display: inline; margin-right: 0.5em }";
        echo 'div.'.MTC."_comment div.".MTC."_info { margin-left: 100px; font-style: italic;}";
        echo 'div.'.MTC."_comment div.".MTC."_user { display: inline; }";
        echo 'div.'.MTC."_comment div.".MTC."_text { margin-left: 100px; margin-bottom: 1em; }";
        echo 'div.'.MTC."_comment div.".MTC."_clear { clear: both; line-height: 1px; height: 1px; }";
        echo '</style>';
    }

    function _add_comment(){
        $page = trim($_POST[MTC]['page']);
        $name = trim($_POST[MTC]['name']);
        $mail = trim($_POST[MTC]['mail']);
        $text = trim($_POST[MTC]['text']);

        if(! ($page && $name && $mail && $text) ){
            $this->message .= 'Sorry, you need to fill all fields!';
            return;
        }

        if(!$this->_isvalid_mail($mail)){
            $this->message .= "Sorry, this mail address doesn't look valid.";
            return;
        }

        if($this->_check_blacklist($text)){
            $this->message .= "Sorry, spamming is not allowed.";
            return;
        }

        $page = md5($page);
        $name = addslashes($name);
        $mail = addslashes($mail);
        $text = addslashes($text);
        $ip   = addslashes($_SERVER['REMOTE_ADDR']);
        $url  = addslashes($_SERVER['PHP_SELF']);

        $sql = "INSERT INTO mtc_comments
                        SET page = '$page',
                            name = '$name',
                            mail = '$mail',
                            text = '$text',
                            date = NOW(),
                            ip = '$ip',
                            url  = '$url'";

        $handle = $this->_get_dbhandle();
        if(!$handle) return;
        mysql_query($sql,$handle);

        // clear form
        $_POST[MTC]['page'] = '';
        $_POST[MTC]['name'] = '';
        $_POST[MTC]['mail'] = '';
        $_POST[MTC]['text'] = '';
    }

    function _print_message(){
        if(!$this->message) return;
        print '<div class="'.MTC.'_message">';
        print htmlspecialchars($this->message);
        print '</div>';
    }

    function _get_dbhandle(){
        if($this->link) return $this->link;

        $this->link = @mysql_connect($this->db_host, $this->db_user, $this->db_pass);
        if(!$this->link){
            $this->message .= 'Could not connect to database: '.mysql_error();
            return false;
        }

        if(!@mysql_select_db($this->db_name)){
            $this->message .= 'Could not select database';
            return false;
        }

        return $this->link;
    }

    /**
     * Uses a regular expresion to check if a given mail address is valid
     *
     * May not be completly RFC conform!
     * 
     * @link    http://www.webmasterworld.com/forum88/135.htm
     *
     * @param   string $email the address to check
     * @return  bool          true if address is valid
     */
    function _isvalid_mail($email){
        return eregi("^[0-9a-z]([+-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-z]{2,4}$", $email);
    }


    /**
     * Spamcheck against wordlist
     *
     * Checks the wikitext against a list of blocked expressions
     * returns true if the text contains any bad words
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _check_blacklist($text){
        $blockfile = file($this->blacklist);
        //how many lines to read at once (to work around some PCRE limits)
        if(version_compare(phpversion(),'4.3.0','<')){
            //old versions of PCRE define a maximum of parenthesises even if no
            //backreferences are used - the maximum is 99
            //this is very bad performancewise and may even be too high still
            $chunksize = 40; 
        }else{
            //read file in chunks of 600 - this should work around the
            //MAX_PATTERN_SIZE in modern PCRE
            $chunksize = 600;
        }
        while($blocks = array_splice($blockfile,0,$chunksize)){
            $re = array();
            #build regexp from blocks
            foreach($blocks as $block){
                $block = preg_replace('/#.*$/','',$block);
                $block = trim($block);
                if(empty($block)) continue;
                $re[]  = $block;
            }
            if(preg_match('#('.join('|',$re).')#si',$text)) return true;
        }
        return false;
    }


    /**
     * remove magic quotes recursivly
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _remove_magic_quotes(&$array) {
        foreach (array_keys($array) as $key) {
            if (is_array($array[$key])) {
                remove_magic_quotes($array[$key]);
            }else {
                $array[$key] = stripslashes($array[$key]);
            }
        }
    }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
?>
