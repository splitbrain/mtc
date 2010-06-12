<?php
/**
 * My Two Cents - A simple comment system
 * 2005-2007 (c) Andreas Gohr <andi@splitbrain.org>
 */


if(!defined('MTC')) define('MTC','MTC'); // used for all POST/GET vars and CSS classes

// The MTC main class
class MTC {
    // DB connection
    var $db_host    = 'localhost';
    var $db_user    = 'mtc';
    var $db_pass    = 'mtc';
    var $db_name    = 'mtc';

    // web path to the class file
    var $self       = 'mtc.class.php';

    // antispam
    var $blacklist  = 'blacklist.txt';
    var $captcha    = true;
    var $audio      = false;
    var $showmail   = false;

    // output
    var $addcss     = true;
    var $target     = '';

    // admin stuff
    var $adminpass  = '';
    var $notify     = '';
    var $gravopts   = '&amp;rating=R';


    // language strings
    var $lang = array(
                    'name'      => 'Your Name:',
                    'email'     => 'Your E-Mail:',
                    'web'       => 'Website (optional):',
                    'captcha'   => 'Security Code:',
                    'comment'   => 'Your two Cents:',
                    'info'      => 'No HTML allowed. URLs will be linked with nofollow attribute. Whitespace is preserved.',
                    'audio'     => 'Click to hear the security code spelled.',
                    'nofield'   => 'Sorry, you need to fill all necessary fields!',
                    'noemail'   => 'Sorry, this mail address doesn\'t look valid.',
                    'noweb'     => 'Sorry, this web address doesn\'t look valid.',
                    'nocaptcha' => 'Sorry, the security code was wrong.',
                    'nospam'    => 'Sorry, spamming is not allowed here.',
                );

    // you may want to change this for more secure captchas
    var $secret     = 'CHANGEME!';


    // internal only
    var $db_link;
    var $captchafnt;
    var $audiodir = '';
    var $message  = '';
    var $page     = '';
    var $seedlen  = 0;
    var $seedpos  = 0;

    /**
     * Constructor
     */
    function MTC(){
        // set some defaults
        $this->captchafnt = glob(dirname(__FILE__).'/MTC/fonts/*.ttf');
        $this->audiodir = dirname(__FILE__).'/MTC/audio/';
    }

    /**
     * Initialize variables
     */
    function setup(){
        // auto set the secret (for lazy ones)
        $this->secret .= $_SERVER['HTTP_USER_AGENT'];
        $this->secret .= $_SERVER['SERVER_SOFTWARE'];
        $this->secret .= __FILE__;
        $this->secret = md5($this->secret);


        // use initialized random generator to create two secret numbers
        srand(hexdec(substr($this->secret,0,6))); // init random generator
        $this->seedpos = rand(0,4);
        $this->seedlen = rand(3,5);
        srand(); // make generator random again
    }


    /**
     * To be called in the head section. Handles intializing
     * and POST/GET variables
     */
    function init($page = ''){
        $this->setup();

        if(!$page){
            $this->page = $_SERVER['PHP_SELF'];
        }else{
            $this->page = $page;
        }

        if($this->target){
            $this->target = 'target="'.$this->target.'"';
        }

        // we do not touch other's variables
        if(get_magic_quotes_gpc() && !defined('MAGIC_QUOTES_STRIPPED')){
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
     * return the number of comments
     */
    function comment_count($page=''){
        if(!$page) $page = $this->page;
        $page = md5($page);

        $sql = "SELECT COUNT(*) as cnt
                  FROM mtc_comments
                 WHERE page = '$page'";
        $handle = $this->_get_dbhandle();
        if(!$handle) return false;
        $result = mysql_query($sql,$handle);
        $row = mysql_fetch_assoc($result);
        mysql_free_result($result);
        return $row['cnt'];
    }

    /**
     * List available comments
     */
    function comments($page=''){
        if(!$page) $page = $this->page;
        $page = md5($page);

        $sql = "SELECT id, name, mail, web, text, date
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
        echo '<label for="'.MTC.'_name">'.$this->lang['name'].'</label>';
        echo '<input type="text" name="'.MTC.'[name]" value="'.htmlspecialchars($_POST[MTC]['name']).'" id="'.MTC.'_name" />';
        echo '</div>';

        echo '<div class="'.MTC.'_mail">';
        echo '<label for="'.MTC.'_mail">'.$this->lang['email'].'</label>';
        echo '<input type="text" name="'.MTC.'[mail]" value="'.htmlspecialchars($_POST[MTC]['mail']).'" id="'.MTC.'_mail" />';
        echo '</div>';

        echo '<div class="'.MTC.'_web">';
        echo '<label for="'.MTC.'_web">'.$this->lang['web'].'</label>';
        echo '<input type="text" name="'.MTC.'[web]" value="'.htmlspecialchars($_POST[MTC]['web']).'" id="'.MTC.'_web" />';
        echo '</div>';

        if($this->captcha){
            echo '<div class="'.MTC.'_captcha">';
            echo '<label for="'.MTC.'_captcha">'.$this->lang['captcha'].'</label>';
            echo '<input type="text" name="'.MTC.'[captcha]" value="" id="'.MTC.'_captcha" />';
            echo '</div>';
            $this->print_captcha();
        }

        echo '<div class="'.MTC.'_text">';
        echo '<label for="'.MTC.'_text">'.$this->lang['comment'].'</label>';
        echo '<textarea cols="40" rows="10" name="'.MTC.'[text]" id="'.MTC.'_text">'.htmlspecialchars($_POST[MTC]['text']).'</textarea>';
        echo '</div>';

        echo '<input type="submit" value="Save" />';
        echo '<p class="'.MTC.'_info">'.$this->lang['info'].'</p>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Defines how a comment is printed. You may want to tweak this, but using CSS should be
     * enough usually
     */
    function format_comment($row){
        static $number = 0;
        $number++;

        $md5 = md5($row['mail']);
        $obf = strtr($row['mail'],array('@' => ' [at] ', '.' => ' [dot] ', '-' => ' [dash] '));

        $text = htmlspecialchars($row['text']);
        $text = preg_replace('/\t/','    ',$text);
        $text = preg_replace('/  /',' &nbsp;',$text);
        $text = preg_replace_callback('/((https?|ftp):\/\/[\w-?&;:#~=\.\/\@]+[\w\/])/ui',
                                      array($this,'_format_link'),$text);
        $text = nl2br($text);

        $opts = str_replace('@MD5@',$md5,$this->gravopts);

        echo '<div class="'.MTC.'_comment" id="comment-'.$number.'">';
        echo '<img src="http://www.gravatar.com/avatar.php?gravatar_id='.$md5.$opts.'" alt="" />';
        echo '<a href="#comment-'.$number.'" rel="self bookmark" class="'.MTC.'_link">'.$number.'</a>';

        echo '<div class="'.MTC.'_text">';
        echo $text;
        echo '</div>';

        echo '<div class="'.MTC.'_info">';

        echo '<div class="'.MTC.'_date">';
        echo $row['date'];
        echo '</div>';

        echo '<div class="'.MTC.'_user">';
        if($row['web']){
            echo '<a href="'.htmlspecialchars($row['web']).'" rel="nofollow" '.$this->target.' class="url">';
            echo htmlspecialchars($row['name']);
            echo '</a>';
        }elseif($this->showmail){
            echo '<a href="mailto:'.htmlspecialchars($obf).'" class="mail">';
            echo htmlspecialchars($row['name']);
            echo '</a>';
        }else{
            echo htmlspecialchars($row['name']);
        }
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
        echo '#'.MTC."_form div.".MTC."_web  { float: left; clear: left;}";
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
        $this->setup();

        $text = $this->_decrypt($_REQUEST[MTC]['captcha']);

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
     * Stiches an audio CAPTCHA
     */
    function captcha_audio(){
        $this->setup();

        $text = $this->_decrypt($_REQUEST[MTC]['captcha']);
        $text = strtolower($text);

        // prepare wave files
        $wavs = array();
        for($i=0;$i<5;$i++){
            $wavs[] = $this->audiodir.'/'.$text{$i}.'.wav';
        }
        // send stiched one
        header('Content-type: audio/x-wav');
        header('Content-Disposition: attachment;filename=captcha.wav');
        echo $this->_joinwavs($wavs);
    }

    /**
     * Return an encrypted image string
     */
    function _encrypt(){
        $code  = $this->_gen_rand(5);              // clear text image code
        $seed  = $this->_gen_rand($this->seedlen); // clear text seed
        $ccode = $this->x_Encrypt($code,$this->secret.$seed); // crypted code
        $cseed = $this->x_Encrypt($seed,$this->secret);       // crypted code
        // now combine
        $crypt = substr($ccode,0,$this->seedpos).$cseed.substr($ccode,$this->seedpos);
        return base64_encode($crypt);
    }

    /**
     * Return the decrypted image string
     */
    function _decrypt($input){
        $input = base64_decode($input);
        $cseed = substr($input,$this->seedpos,$this->seedlen);
        $seed  = $this->x_Decrypt($cseed,$this->secret);
        $ccode = substr($input,0,$this->seedpos).substr($input,$this->seedpos+$this->seedlen);
        return $this->x_Decrypt($ccode,$this->secret.$seed);
    }

    /**
     * Print the HTML for the CAPTCHA
     */
    function print_captcha(){
        $crypt = $this->_encrypt();
        echo '<input type="hidden" name="MTC[code]" value="'.$this->_hexescape($crypt).'" style="display:none" />';

        if($this->audio){
            echo '<a href="'.$this->self.'?MTC[do]=audio&amp;MTC[captcha]='.rawurlencode($crypt).'" title="'.$this->lang['audio'].'">';
        }
        echo '<img src="'.$this->self.'?MTC[do]=captcha&amp;MTC[captcha]='.rawurlencode($crypt).'" width="200" height="50" alt="CAPTCHA" class="'.MTC.'_captcha" border="0" />';
        if($this->audio){
            echo '</a>';
        }
    }

    /**
     * Generate a random code
     */
    function _gen_rand($max){
        $code = '';
        for($i=0;$i<$max;$i++){
            $code .= chr(rand(65, 90));
        }
        return $code;
    }

    /**
     * Callback to autolink a URL (with shortening)
     */
    function _format_link($match){
        $url = $match[1];
        str_replace("\\\\'","'",$url);
        if(strlen($url) > 40){
            $title = substr($url,0,30).' &hellip; '.substr($url,-10);
        }else{
            $title = $url;
        }
        $link = '<a href="'.$url.'" '.$this->target.' rel="nofollow">'.$title.'</a>';
        return $link;
    }

    /**
     * Join multiple wav files
     *
     * All wave files need to have the same format and need to be uncompressed.
     * The headers of the last file will be used (with recalculated datasize
     * of course)
     *
     * @link http://ccrma.stanford.edu/CCRMA/Courses/422/projects/WaveFormat/
     * @link http://www.thescripts.com/forum/thread3770.html
     * @link http://www.splitbrain.org/blog/2006-11/15-joining_wavs_with_php
     */
    function _joinwavs($wavs){
        $fields = join('/',array( 'H8ChunkID', 'VChunkSize', 'H8Format',
                                  'H8Subchunk1ID', 'VSubchunk1Size',
                                  'vAudioFormat', 'vNumChannels', 'VSampleRate',
                                  'VByteRate', 'vBlockAlign', 'vBitsPerSample' ));

        $data = '';
        foreach($wavs as $wav){
            $fp     = fopen($wav,'rb');
            if(!$fp) die('failed to load wav file');
            $header = fread($fp,36);
            $info   = unpack($fields,$header);

            // read optional extra stuff
            if($info['Subchunk1Size'] > 16){
                $header .= fread($fp,($info['Subchunk1Size']-16));
            }

            // read SubChunk2ID
            $header .= fread($fp,4);

            // read Subchunk2Size
            $size  = unpack('vsize',fread($fp, 4));
            $size  = $size['size'];

            // read data
            $data .= fread($fp,$size);
        }

        return $header.pack('V',strlen($data)).$data;
    }


    /**
     * Prints the form tags for admin
     */
    function _admin_opts($id){
        if(!$_REQUEST[MTC]['admin']) return;
        echo '<div class="'.MTC.'_admform">';
        echo '<form action="#'.MTC.'_form" method="post" accept-charset="utf-8">';
        echo '<input type="hidden" name="'.MTC.'[admin]" value="1" style="display:none" />';
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
        $web  = trim($_POST[MTC]['web']);
        $text = trim($_POST[MTC]['text']);
        $cptc = trim($_POST[MTC]['captcha']);
        $code = trim($_POST[MTC]['code']);

        if(! ($page && $name && $mail && $text) ){
            $this->message .= $this->lang['nofield'];
            return;
        }

        if($this->captcha){
            if(!$cptc || !$code ||
               (strtoupper($cptc) != strtoupper($this->_decrypt($code)))
              ){
                $this->message .= $this->lang['nocaptcha'];
                return;
            }
        }else{
            $code = $this->_gen_rand(5);
        }

        if($web && !preg_match('=https?://=i',$web)){
            $this->message .= $this->lang['noweb'];
            return;
        }

        if(!$this->_isvalid_mail($mail)){
            $this->message .= $this->lang['noemail'];
            return;
        }

        if($this->_check_blacklist($text)){
            $this->message .= $this->lang['nospam'];
            return;
        }

        $ip   = $_SERVER['REMOTE_ADDR'];
        $this->_mail($page,$name,$mail,$web,$text,$ip,$_SERVER['PHP_SELF']);

        $url  = addslashes($page);
        $page = md5($page);
        $name = addslashes($name);
        $mail = addslashes($mail);
        $web  = addslashes($web);
        $text = addslashes($text);
        $ip   = addslashes($ip);
        $code = addslashes($code);

        $sql = "INSERT IGNORE INTO mtc_comments
                        SET page = '$page',
                            name = '$name',
                            mail = '$mail',
                            web  = '$web',
                            text = '$text',
                            date = NOW(),
                            ip = '$ip',
                            url  = '$url',
                            captcha = MD5('$code')";

        $handle = $this->_get_dbhandle();
        if(!$handle) return;
        mysql_query($sql,$handle);

        // clear form
        $_POST[MTC]['page'] = '';
        $_POST[MTC]['name'] = '';
        $_POST[MTC]['mail'] = '';
        $_POST[MTC]['web']  = '';
        $_POST[MTC]['text'] = '';
    }

    /**
     * If the notify property is set this function will send a
     * mail for each comment created.
     */
    function _mail($page,$name,$mail,$web,$text,$ip,$url){
        if(!$this->notify) return;

        $body  = "The following comment was added:\n\n";
        $body .= "Name: $name\n";
        $body .= "Mail: $mail\n";
        $body .= "Web : $web\n";
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
            $chunksize = 200;
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
     * Escape a given string as hex entities
     */
    function _hexescape($string){
        $encode = '';
        for ($x=0; $x < strlen($string); $x++) $encode .= '&#x' . bin2hex($string{$x}).';';
        return $encode;
    }
    /**
     * Escape a given string as url entities
     */
    function _urlescape($string){
        $encode = '';
        for ($x=0; $x < strlen($string); $x++) $encode .= '%' . bin2hex($string{$x});
        return $encode;
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

if($_REQUEST['MTC']['do'] == 'captcha'){
    $mtc = new MTC();
    $mtc->captcha_image();
}elseif($_REQUEST['MTC']['do'] == 'audio'){
    $mtc = new MTC();
    $mtc->captcha_audio();
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
?>
