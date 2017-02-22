<?php
	$q_id = $_GET['q'];
	$p_id = $_GET['p'];
	$f_id = $_GET['f'];
		
		
	require_once("includes_path.php");
	require_once($includes_path."db_connect.php");
	require_once($includes_path."cqp_methods.php");
	
	$folder = get_folder($q_id, $p_id, $f_id);
	
	$sql = "SELECT filename FROM analysis_files WHERE query_id=$q_id AND proximity_id=$p_id AND filter_id=$f_id";
	$result = mysql_query($sql) or die("Error SH990");
	if(mysql_num_rows($result)>0) {
		$files = array();
		while($row=mysql_fetch_row($result)) {
			$files[] = $folder.$row[0];
		}
		
		$sql = "SELECT query_name FROM queries WHERE query_id=$q_id";
		$result = mysql_query($sql) or die("Error SH991");
		if(mysql_num_rows($result)<1) {
			die("Error SH992");
		}
		$row=mysql_fetch_row($result);
		$queryname = $row[0];
		
		$sql = "SELECT proximity_name FROM proximities WHERE proximity_id=$p_id";
		$result = mysql_query($sql) or die("Error SH991");
		if(mysql_num_rows($result)<1) {
			die("Error SH992");
		}
		$row=mysql_fetch_row($result);
		$proximityname = $row[0];
		
		if($f_id>0) {
			$sql = "SELECT filter_name FROM filters WHERE filter_id=$f_id";
			$result = mysql_query($sql) or die("Error SH993");
			if(mysql_num_rows($result)<1) {
				die("Error SH994");
			}
			$row=mysql_fetch_row($result);
			$filtername = $row[0];
		}
		else {
			$filtername = "";
		}
		$archivefile = $folder.sanitize($queryname."_".$proximityname."_".$filtername, true).".zip";
		delete_if_exists($archivefile);
		//system("zip -qj ".$archivefile." ".implode($files, " "));
		create_zip($files, $archivefile,true);
		
		header("location: ".$archivefile);
	}
	else {
		header("location: index.php");
	}
?>