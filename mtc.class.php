<?php
/**
 * My Two Cents - A simple comment system
 * 2005 (c) Andreas Gohr <andi@splitbrain.org>
 */


if(!defined('MTC')) define('MTC','MTC'); // used for all POST/GET vars and CSS classes

// a string to be added to the gravatar url
// see http://gravatar.com/implement.php#section_1_1
if(!defined('GRAVATAR_OPTS')) define('GRAVATAR_OPTS','&amp;rating=R');

// The MTC main class
class MTC {
    var $db_host    = 'localhost';
    var $db_user    = 'mtc';
    var $db_pass    = 'mtc';
    var $db_name    = 'mtc';
    var $addcss     = true;
    var $blacklist  = 'blacklist.txt';
    var $notify     = '';
    var $adminpass  = '';
    var $self       = 'mtc.class.php';
    var $captcha    = true;
    var $page;
    var $secret     = 'CHANGEME!';


    // internal only
    var $db_link;
    var $captchafnt;
    var $message = '';

    /**
     * Constructor
     */
    function MTC(){
        $this->captchafnt = array(
            dirname(__FILE__).'/fonts/Vera.ttf',
            dirname(__FILE__).'/fonts/VeraBd.ttf',
            dirname(__FILE__).'/fonts/VeraIt.ttf',
        );
    }

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
            if (!empty($_GET[MTC]))     $this->_remove_magic_quotes($_GET[MTC]);
            if (!empty($_REQUEST[MTC])) $this->_remove_magic_quotes($_REQUEST[MTC]);
        }

        echo $this->print_css();        

        if($_POST[MTC]['do'] == 'add'){
            $this->_add_comment();
        }else if($_POST[MTC]['do'] == 'del'){
            $this->_del_comment();
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
        echo '<input type="hidden" name="'.MTC.'[do]" value="add" style="display:none" />';
        echo '<input type="hidden" name="'.MTC.'[page]" value="'.htmlspecialchars($this->page).'" style="display:none" />';
      
        echo '<div class="'.MTC.'_name">';
        echo '<label for="'.MTC.'_name">Your Name:</label>';
        echo '<input type="text" name="'.MTC.'[name]" value="'.htmlspecialchars($_POST[MTC]['name']).'" id="'.MTC.'_name" />';
        echo '</div>';

        echo '<div class="'.MTC.'_mail">';
        echo '<label for="'.MTC.'_mail">Your E-Mail:</label>';
        echo '<input type="text" name="'.MTC.'[mail]" value="'.htmlspecialchars($_POST[MTC]['mail']).'" id="'.MTC.'_mail" />';
        echo '</div>';


        if($this->captcha){
            echo '<div class="'.MTC.'_captcha">';
            echo '<label for="'.MTC.'_captcha">Security-Code:</label>';
            echo '<input type="text" name="'.MTC.'[captcha]" value="" id="'.MTC.'_captcha" />';
            echo '</div>';
            $this->print_captcha();
        }

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
        echo '<img src="http://www.gravatar.com/avatar.php?gravatar_id='.$md5.GRAVATAR_OPTS.'" alt="" />';
        echo '<a href="#'.MTC.'_'.$number.'" id="'.MTC.'_'.$number.'" class="'.MTC.'_link">'.$number.'</a>';

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
        $this->_admin_opts($row['id']);
        echo '</div>';
    }

    /**
     * Prints minimal CSS for initial styling. Can be suppressed
     * with the addcss property.
     */
    function print_css(){
        if(!$this->addcss) return;
        echo '<style type="text/css">';
        echo '.'.MTC."_message { text-align: center; color: #f00 }";

        echo '#'.MTC."_form input { margin-left: 50px; display: block; width: 250px; }";
        echo '#'.MTC."_form textarea { margin-left: 50px; display: block; width: 550px; }";
        echo '#'.MTC."_form div.".MTC."_name { float: left; }";
        echo '#'.MTC."_form div.".MTC."_mail { float: left; }";
        echo '#'.MTC."_form div.".MTC."_captcha { float: left; clear: left;}";
        echo '#'.MTC."_form img.".MTC."_captcha { float: left; margin-left: 50px;}";
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

    /**
     * Creates a simple 200x50 CAPTCHA image
     */
    function captcha_image(){
        $text = $this->x_Decrypt($_REQUEST[MTC]['captcha'],$this->secret);

        // create a white image
        $img = imagecreate(200, 50);
        imagecolorallocate($img, 255, 255, 255);

        // add some lines as background noise
        for ($i = 0; $i < 60; $i++) {
            $color = imagecolorallocate($img,rand(100, 250),rand(100, 250),rand(100, 250));
            imageline($img,rand(0,200),rand(0,50),rand(0,200),rand(0,50),$color);
         }

        // draw the letters
        for ($i = 0; $i < strlen($text); $i++){
            $font  = $this->captchafnt[array_rand($this->captchafnt)];
            $color = imagecolorallocate($img, rand(0, 100), rand(0, 100), rand(0, 100));
            $size  = rand(16,25);
            $angle = rand(-30, 30);

            $x = 10 + $i * 40;
            $cheight = $size + ($size*0.5);
            $y = floor(50 / 2 + $cheight / 4);

            imagettftext($img, $size, $angle, $x, $y, $color, $font, $text[$i]);
        }

        header("Content-type: image/jpeg");
        imagejpeg($img);
        imagedestroy($img);
    }

    /**
     * Print the HTML for the CAPTCHA
     */
    function print_captcha(){
        $code = '';
        for($i=0;$i<5;$i++){
            $code .= chr(rand(65, 90));
        }
        $code = $this->x_Encrypt($code,$this->secret);

        echo '<input type="hidden" name="'.MTC.'[code]" value="'.htmlspecialchars($code).'" style="display:none" />';
        echo '<img src="'.$this->self.'?MTC[do]=captcha&amp;MTC[captcha]='.urlencode($code).'" width="200" height="50" alt="CAPTCHA" class="'.MTC.'_captcha" />';
    }

    /**
     * Prints the form tags for admin
     */
    function _admin_opts($id){
        if(!$_REQUEST[MTC]['admin']) return;
        echo '<div class="'.MTC.'_admform">';
        echo '<form action="#'.MTC.'_form" method="post" accept-charset="utf-8">';
        echo '<input type="hidden" name="'.MTC.'[do]" value="del" style="display:none" />';
        echo '<input type="hidden" name="'.MTC.'[page]" value="'.htmlspecialchars($this->page).'" style="display:none" />';
        echo '<input type="hidden" name="'.MTC.'[id]" value="'.$id.'" style="display:none" />';

        echo '<label for="'.MTC.'_admpass">Admin Password:</label>';
        echo '<input type="password" name="'.MTC.'[admpass]" value="" id="'.MTC.'_admpass" />';

        echo '<input type="submit" value="Delete" />';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Does the work for deleting a comment
     */
    function _del_comment(){
        $page    = trim($_POST[MTC]['page']);
        $id      = trim($_POST[MTC]['id']);
        $admpass = $_POST[MTC]['admpass'];

        if(! ($page && $id && $admpass) ){
            $this->message .= 'Sorry, missing parameters! Admin password given?';
            return;
        }

        if($this->adminpass != $admpass){
            $this->message .= 'Sorry, wrong password.';
            return;
        }

        $page = md5($page);
        $id   = addslashes($id);
        $sql = "DELETE FROM mtc_comments
                      WHERE page = '$page'
                        AND id = '$id'";

        $handle = $this->_get_dbhandle();
        if(!$handle) return;
        mysql_query($sql,$handle);

        $this->message = 'Comment deleted';        
    }

    /**
     * Does the work for adding a comment
     */
    function _add_comment(){
        $page = trim($_POST[MTC]['page']);
        $name = trim($_POST[MTC]['name']);
        $mail = trim($_POST[MTC]['mail']);
        $text = trim($_POST[MTC]['text']);
        $cptc = trim($_POST[MTC]['captcha']);
        $code = trim($_POST[MTC]['code']);

        if(! ($page && $name && $mail && $text) ){
            $this->message .= 'Sorry, you need to fill all fields!';
            return;
        }

        if($this->captcha){
            if(!$cptc || !$code ||
               (strtoupper($cptc) != strtoupper($this->x_Decrypt($code,$this->secret)))
              ){
                $this->message .= 'Sorry, the Security Code was wrong';
                return;
            }
        }

        if(!$this->_isvalid_mail($mail)){
            $this->message .= "Sorry, this mail address doesn't look valid.";
            return;
        }

        if($this->_check_blacklist($text)){
            $this->message .= "Sorry, spamming is not allowed.";
            return;
        }

        $ip   = $_SERVER['REMOTE_ADDR'];
        $url  = $_SERVER['PHP_SELF'];
        $this->_mail($page,$name,$mail,$text,$ip,$url);

        $page = md5($page);
        $name = addslashes($name);
        $mail = addslashes($mail);
        $text = addslashes($text);
        $ip   = addslashes($ip);
        $url  = addslashes($url);

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

    /**
     * If the notify property is set this function will send a
     * mail for each comment created.
     */
    function _mail($page,$name,$mail,$text,$ip,$url){
        if(!$this->notify) return;

        $body  = "The following comment was added:\n\n";
        $body .= "Name: $name\n";
        $body .= "Mail: $mail\n";
        $body .= "Date: ".date('r')."\n";
        $body .= "IP  : $ip\n";
        $body .= "Page: $page\n";
        $body .= "URL : http://".$_SERVER['HTTP_HOST'].$url."\n\n";
        $body .= $text;

        $body = base64_encode($body);

        $header  = "MIME-Version: 1.0\n";
        $header .= "Content-Type: text/plain; charset=UTF-8\n";
        $header .= "Content-Transfer-Encoding: base64";

        $subject = '['.MTC.'] New comment added';

        mail($this->notify,$subject,$body,$header);
    }

    /**
     * Output a message if one is set
     */
    function _print_message(){
        if(!$this->message) return;
        print '<div class="'.MTC.'_message">';
        print htmlspecialchars($this->message);
        print '</div>';
    }

    /**
     * Connect to the database and return a handle
     */
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
        if(!@file_exists($this->blacklist)) return false;
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
        if(!is_array($array)) return;
        foreach (array_keys($array) as $key) {
            if (is_array($array[$key])) {
                remove_magic_quotes($array[$key]);
            }else {
                $array[$key] = stripslashes($array[$key]);
            }
        }
    }


    /**
     * Simple XOR encryption
     *
     * @author Dustin Schneider
     * @link http://www.phpbuilder.com/tips/item.php?id=68
     */
    function x_Encrypt($string, $key){
        for($i=0; $i<strlen($string); $i++){
            for($j=0; $j<strlen($key); $j++){
                $string[$i] = $string[$i]^$key[$j];
            }
        }
        return $string;
    }

    /**
     * Simple XOR decryption
     *
     * @author Dustin Schneider
     * @link http://www.phpbuilder.com/tips/item.php?id=68
     */
    function x_Decrypt($string, $key){
        for($i=0; $i<strlen($string); $i++){
            for($j=0; $j<strlen($key); $j++){
                $string[$i] = $key[$j]^$string[$i];
            }
        }
        return $string;
    }
}

/**
 * Main
 */

if($_REQUEST[MTC]['do'] == 'captcha'){
  $mtc = new MTC();
  $mtc->captcha_image();
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
?>
