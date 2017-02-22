<?php
	$q_id = $_POST['q_id'];
	$p_id = $_POST['p_id'];
	$f_id = $_POST['f_id'];
	$filename = $_POST['filename'];

	require_once("includes_path.php");		
	require_once($includes_path."cqp_methods.php");
	require_once($includes_path."db_connect.php");
	$sql = "DELETE FROM analysis_files WHERE query_id=$q_id AND proximity_id=$p_id AND filter_id=$f_id AND filename=\"$filename\"";
	$result = mysql_query($sql) or die("Error SH982");
	if(mysql_affected_rows()==1) {
		$folder = get_folder($q_id, $p_id, $f_id);
		$file = $folder.$filename;
		delete_if_exists($file);
	}
?>