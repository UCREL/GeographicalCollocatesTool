<?php
	header('Content-type: text/html; charset=utf-8');
	require_once("db_connect.php");
	
	if(isset($_REQUEST['c_id']) && $_REQUEST['c_id']!=-1) {
		$c_id = $_REQUEST['c_id'];
	}
	
	if(!isset($logon_url))
		$logon_url = $_SERVER['REQUEST_URI'];

	if(!$checklogin_done)
		include("checklogin.php");
	
	$corpora = array();
	if($_SESSION['logged']) {
		$sql = sprintf("SELECT corpora.c_id, display_name FROM corpora, permissions WHERE corpora.c_id=permissions.c_id AND user_id=%d", $_SESSION['uid']);
		$result = mysql_query($sql) or die("Sorry, an error has occurred. Error 891.");
		while($row = mysql_fetch_array($result)) {
			$corpora[$row['c_id']] = $row['display_name']; 
			if($c_id==$row['c_id']) {
				$corpus_name = $row['display_name'];
			}
		}
	}
	
	$no_corpora = empty($corpora);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Spatial Humanities: Placename proximity search</title>
<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
<script src="http://code.jquery.com/jquery-1.7.2.min.js" type="text/javascript"></script>
<script type="text/javascript">

$(function () {
	$('img.plusrow').live('click', function () {
		var tr = $(this).closest('tr');
		var clonedtr = tr.clone();
		clonedtr.find('input[type=text]').val('');
    	clonedtr.find('#searchcell').load('default-search.php');	
		tr.after(clonedtr);
		var i=1;
		$('#buildtable tr').each(function () {
			$(this).find('input[type=checkbox]').val(i);
			i++;
		});
	});
});
$(function () {
	$('img.minusrow').live('click', function () {
		var rowcount = $('#buildtable tr').length
		if(rowcount > 2) {
			$(this).parent().parent().remove();
		}
		var i=2;
		$('#buildtable tr').each(function () {
			$(this).find('input[type=checkbox]').val(i);
			i++;
		});
	});
});
$(function () {
	$('#searchtype').live('change', function() {
		if($(this).val()=='semtag') {
			var tr = $(this).closest('tr');
			tr.find('#searchcell').load('semtag-search.php');
		}
		else if($(this).val()=='pos') {
			var tr = $(this).closest('tr');
			tr.find('#searchcell').load('pos-search.php');
		}
		else if($(this).val()=='class') {
			var tr = $(this).closest('tr');
			tr.find('#searchcell').load('class-search.php');
		}
		else {
			var tr = $(this).closest('tr');
			tr.find('#searchcell').load('default-search.php');			
		}
	});
});
$(function () {
	$('#search_dd').live('change', function() {
		var tr = $(this).closest('tr');
		tr.find('#searchcell').find('input[type=text]').val($(this).val());
	});
});
$(function () {
	$('img.deletefile').live('click', function() {
		var fn = $(this).parent().find('input[type=hidden]').val();
		
		$.when( $(this).parent().load('deletefile.php', {q_id: $('#queryselect').val(), p_id: $('#proximityselect').val(), f_id: $('#filterselect').val(), filename: fn})  ).done(
		function() { $('#fileslistdiv').load('fileslist.php', {q_id: $('#queryselect').val(), p_id: $('#proximityselect').val(), f_id: $('#filterselect').val()});
		});
	});
});
$(function () {
    $('.checkall').live('change', function () {
        $(this).parents('fieldset:eq(0)').find(':checkbox').attr('checked', this.checked);
    });
});
$(function () {
	$('#queryselect').change(function() {
		$('#querydisplaydiv').load('querydisplay.php', {q_id: $(this).val(), c_id: $('#cid').val()});
		$('#fileslistdiv').load('fileslist.php', {q_id: $('#queryselect').val(), p_id: $('#proximityselect').val(), f_id: $('#filterselect').val()});
	});
});
$(function () {
	$('#proximityselect').change(function() {
		$('#proximitydisplaydiv').load('proximitydisplay.php', {p_id: $(this).val(), c_id: $('#cid').val()});
		$('#fileslistdiv').load('fileslist.php', {q_id: $('#queryselect').val(), p_id: $('#proximityselect').val(), f_id: $('#filterselect').val()});
	});
});
$(function () {
	$('#filterselect').change(function() {
		$('#filterdisplaydiv').load('filterdisplay.php', {f_id: $(this).val(), c_id: $('#cid').val()});
		$('#fileslistdiv').load('fileslist.php', {q_id: $('#queryselect').val(), p_id: $('#proximityselect').val(), f_id: $('#filterselect').val()});
	});
});
</script>
<link href="css.css" rel="stylesheet" type="text/css"/>
</head>
<body>
	<div class="top" style="overflow: hidden;">
	<div class="top" style="width: auto; float: left;">
	<h1><a class="topnu" href="index.php">Spatial Humanities: Placename proximity search</a></h1>
	</div>
	<div id="user" class="top" style="text-align: right; width:280px; float: right;">
	<fieldset>
	<legend>User</legend>
<?php
	if($_SESSION['logged']) {
?>
		<form id="logout" action="index.php" method="post">
		<p>Logged in as <i><?php echo $_SESSION['username']; ?></i>.
		<input type="hidden" name="logout" value="yes" />
		<input style="float: right;" type="submit" value="Logout" /></p>
		<p style="text-align: right; margin: 0px; clear: both;"><a class="top smaller" href="changedetails.php">Change details</a></p>
		</form>
<?php
	}
	else {
?>
		<form id="login" action="<?php echo str_replace("&", "&amp;", $logon_url); ?>" method="post">
		<table>
		<tr>
		<td>Username</td>
		<td><input type="text" name="login_username" size="20" maxlength="32" value="<?php echo $_POST['login_username']; ?>" /></td>
		</tr>
		<tr>
		<td>Password</td>
		<td><input type="password" name="login_password" size="20" maxlength="32" value="<?php echo $_POST['login_password'] ?>"/></td>
		</tr>
		</table>
		<p style="margin: 2px 10px;">
		<input style="float: right;" type="submit" value="Login" />Remember? <input type="checkbox" name="login_remember" value="yes" <?php echo ($_POST['login_remember'] == "yes" ? "checked=\"checked\"" : ""); ?> /></p>
		<p style="text-align: right; margin: 0px; clear: both;"><a class="top smaller" href="passwordreset.php">Forgot password?</a></p>
		<p style="text-align: right; margin: 0px; clear: both;"><a class="top smaller" href="register.php">Register</a></p>
		</form>
<?php
	}
?>
</fieldset>
</div>

<?php if($_SESSION['logged'] && !$no_corpora) { ?>
<div class="corpusselect">
<?php 
	if(isset($corpus_name)) {
		printf("<h2><a class=\"topnu\" href=\"index.php?c_id=%d\">%s</a></h2>", $c_id, $corpus_name);
	}
?>


<form id="corpus" action="index.php" method="post">
	<p style="margin: 0px;"><select name="c_id">
	<option label="Change..." value="-1">Change...</option>
<?php
	foreach($corpora as $c => $name) {
		printf("<option label=\"%s\" value=\"%d\">%s</option>", $name, $c, $name);
	}
?>
	</select>
	<input type="submit" name="corpus" value="Select" /></p>
	</form>
</div>

<?php } ?>
</div>

<?php
	
	if($user->failed) {
?>
		<div><p><b>Login failed. Please note, usernames and passwords are case-sensitive.</b></p></div>
<?php
	}
	
	else if($user->notactive) {
?>
		<div><p><b>Your account has not been activated, please follow the link in the confirmation email. <a href="sendemailconfirm.php">Re-send confirmation?</a></p></div>
<?php
	}
?>


