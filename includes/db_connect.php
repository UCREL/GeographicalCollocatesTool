<?php
	mb_language('uni');
	mb_internal_encoding('UTF-8');

	$conn = mysql_connect('localhost', "username", "password", TRUE) or die(mysql_error());
	mysql_select_db("db_name",$conn) or die( "Unable to select database");
	$database_connect_done = TRUE;
?>