<html>
<head>
<title>My Two Cents Test page</title>

<?php
    require_once('mtc.class.php');
    $MTC = new MTC();
    $MTC->audio = true;
    //$MTC->notify = 'andi@splitbrain.org';
    $MTC->init('my page');
?>
</head>
<body>

My usual HTML page

<hr />

<p><?php echo $MTC->comment_count();?> comment(s) will follow.</p>

<hr />
<?php $MTC->comments(); ?>

<hr />

<p>Add your comment</p>

<?php $MTC->comment_form(); ?>

</body>
</html>
