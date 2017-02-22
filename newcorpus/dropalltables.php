<?php
	//drops all tables, preserves database settings/permissions, etc.
	//run as: php dropalltables.php [mysql username] [database_name]

	//get database connection, as will need throughout
	//mysql username is first command line argument
	//database name is second command line argument
	$mysql_username = $argv[1];
	$database_name = $argv[2];
	echo "MySQL Password: ";
	system('stty -echo');
	$mysql_password = trim(fgets(STDIN));
	system('stty echo');
	// add a new line since the users CR didn't echo
	echo "\n";

	mb_language('uni');
	mb_internal_encoding('UTF-8');
	$conn = mysql_connect(localhost, $mysql_username, $mysql_password, TRUE) or die(mysql_error());
	mysql_select_db($database_name,$conn) or die( "Unable to select database"); //change database name if necessary
	echo "Connected to database\n";
	
	$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = \"$database_name\"";
	$result = mysql_query($sql) or die(mysql_error()."\n".$sql);
	while($row = mysql_fetch_row($result)) {
		$drop_sql = "DROP TABLE IF EXISTS ".$row[0];
		echo $drop_sql."\n";
		mysql_query($drop_sql) or die(mysql_error()."\n".$drop_sql);
	}
	
	echo "done\n";
	
	mysql_close();
?>