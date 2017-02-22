<?php
	require_once("db_connect.php");
	
	//$c_id = corpus id
	//$user_id
	//$filter_name
	//$filter_group_selections, array of ($field => array($vals)). e.g. array("Year" => array("1550", "1551", "1552"));
	//$filter_ranges, array of ($field => array("min"=>min, "max"=>max). e.g. array("enamex_long" => array("min"=>"0.0", "max"=>"50.0"), "enamex_lat"=>array("min"=>"-20.0", "max"=>"20.0")). Both min and max must be present. Min and max are strings, but should contain numbers (int or decimal). Min and max are inclusive.
	function save_filter($c_id, $user_id, $filter_name, $filter_group_selections, $filter_ranges) {
		$filter_name = mysql_real_escape_string(stripslashes($filter_name));
		$sql = sprintf("INSERT INTO filters VALUES(NULL, %d, %d, \"%s\", NOW());", $c_id, $user_id, $filter_name);
		mysql_query($sql) or die("Error occurred. SH801.");
		$f_id = mysql_insert_id();
		foreach($filter_group_selections as $field => $vals) {
			foreach($vals as $val) {
				$sql = sprintf("INSERT INTO filter_group_include VALUES(%d, \"%s\", \"%s\");", $f_id, $field, $val);
				mysql_query($sql) or die("Error occurred. SH802.");
			}
		}
		foreach($filter_ranges as $field => $range) {
			$sql = sprintf("INSERT INTO filter_range_include VALUES(%d, \"%s\", %s, %s);", $f_id, $field, $range['min'], $range['max']);
			mysql_query($sql) or die("Error occurred. SH8021.".$sql.mysql_error());	
		}
		return $f_id;
	}
	
	function load_filter($f_id) {
		$sql = "SELECT field, val FROM filter_group_include WHERE filter_id=$f_id ORDER BY field ASC, val ASC";
		$result = mysql_query($sql) or die("Error occurred. SH803.");
		$filter = array();
		while($row = mysql_fetch_array($result)) {
			$field = $row['field'];
			$val = $row['val'];
			if(!isset($filter[$field])) {
				$filter[$field] = array();
			}
			$filter[$field][] = $val;
		}
		
		$filter_string = array();
		foreach($filter as $k => $vs) {
			$filter_string[$k] = implode(", ", $vs); //convert to string to matchup with range below
		}
		
		$sql = "SELECT field, minimum, maximum FROM filter_range_include WHERE filter_id=$f_id ORDER BY field ASC";
		$result = mysql_query($sql) or die("Error occurred. SH803a.");
		while($row = mysql_fetch_array($result)) {
			$filter_string[$row['field']] = $row['minimum']." - ".$row['maximum'];
		}
		return $filter_string;
	}

	function delete_filter($f_id) {
		$sql = sprintf("DELETE FROM filters WHERE filter_id=%d", $f_id);
		mysql_query($sql) or die("Error occurred. SH8081.");
		$sql = sprintf("DELETE FROM filter_group_include WHERE filter_id=%d", $f_id);
		mysql_query($sql) or die("Error occurred. SH8082.");
		$sql = sprintf("DELETE FROM filter_range_include WHERE filter_id=%d", $f_id);
		mysql_query($sql) or die("Error occurred. SH8083.");		
		$sql = sprintf("DELETE FROM analysis_files WHERE filter_id=%d", $f_id);
		mysql_query($sql) or die("Error occurred deleting analysis files from db.");
	}

		
	function save_proximity($c_id, $user_id, $proximity_name, $lookleft, $lookright, $within_count, $within_type) {
		$proximity_name = "\"".mysql_real_escape_string(stripslashes($proximity_name))."\"";
		if($lookleft=="") {
			$lookleft = "NULL";
		}
		else {
			$lookleft = mysql_real_escape_string(stripslashes($lookleft));
		}
		
		if($lookright=="") {
			$lookright = "NULL";
		}
		else {
			$lookright = mysql_real_escape_string(stripslashes($lookright));
		}
		
		if($within_count=="") {
			$within_count = "NULL";
			$within_type = "NULL";
		}
		else {
			$within_count = mysql_real_escape_string(stripslashes($within_count));
			$within_type = "\"".mysql_real_escape_string(stripslashes($within_type))."\"";
		}

		$sql = "INSERT INTO proximities VALUES(null, $c_id, $user_id, $proximity_name, NOW(), $lookleft, $lookright, $within_type, $within_count)";
		mysql_query($sql) or die("Error occurred adding proximity.");
		return mysql_insert_id();
	}
	
	function load_proximity($p_id) {
		$sql = "SELECT words_left, words_right, within_type, within_count FROM proximities WHERE proximity_id=$p_id";
		$result = mysql_query($sql) or die("Error occurred loading proximity.");
		$row = mysql_fetch_array($result);
		$restrictions = array();
		if($row['words_left']!=NULL) {
			$restrictions[] = ($row['words_left']." Left");
		}
		if($row['words_right']!=NULL) {
			$restrictions[] = ($row['words_right']." Right");
		}
		if($row['within_count']!=NULL) {
			$restrictions[] = ("Within ".$row['within_count']." ".$row['within_type']);
		}
		return $restrictions;
	}
	
	function delete_proximity($p_id) {
		$sql = "DELETE FROM proximities WHERE proximity_id=$p_id";
		mysql_query($sql) or die("Error occurred deleting proximity.");
		$sql = "DELETE FROM analysis_files WHERE proximity_id=$p_id";
		mysql_query($sql) or die("Error occurred deleting analysis files from db.");
	}	
		
	function save_query($c_id, $user_id, $query_name, $query) {
		$query_name = mysql_real_escape_string(stripslashes($query_name));
		$query = mysql_real_escape_string(stripslashes($query));
		$sql = sprintf("INSERT INTO queries VALUES(null, %d, %d, \"%s\", NOW(), \"%s\");", $c_id, $user_id, $query_name, $query);
		mysql_query($sql) or die("Error occurred. SH804.");
		return mysql_insert_id();
	}
	
	function load_query($q_id) {
		$sql = "SELECT query_text FROM queries WHERE query_id=$q_id";
		$result = mysql_query($sql) or die("Error occurred. SH805.");
		$row = mysql_fetch_row($result);
		return $row[0];
	}
	
	function delete_query($q_id) {
		$sql = "DELETE FROM queries WHERE query_id=$q_id";
		mysql_query($sql) or die("Error occurred. SH806.");
		$sql = "DELETE FROM analysis_files WHERE query_id=$q_id";
		mysql_query($sql) or die("Error occurred deleting analysis files from db.");
	}
	
	function add_analysis_files($q_id, $p_id, $f_id, $folder, $analysis_files) {
		foreach($analysis_files as $filename => $description) {
			$size = getfilesize($folder,$filename);
			$time = date("Y-m-d H:i:s", filemtime($folder.$filename));;
			$sql = "INSERT INTO analysis_files VALUES($q_id, $p_id, $f_id, '$filename', '$description', '$size', '$time') ON DUPLICATE KEY UPDATE description='$description', size='$size', ts='$time'";
			mysql_query($sql) or die("Error occurred adding analysis files to db.");
		}
	}
	
	function delete_analysis_files_from_db($q_id, $p_id, $f_id) {
		$sql = "DELETE FROM analysis_files WHERE query_id=$q_id AND proximity_id=$p_id AND filter_id=$f_id";
		mysql_query($sql) or die("Error occurred deleting analysis files from db.");
	}
	
	function getfilesize($folder, $filename) {
		$size = filesize($folder.$filename);
		return format_bytes($size);
	}
	
	function format_bytes($size) {
 	   $units = array(' B', ' KB', ' MB', ' GB', ' TB');
 	   for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
 	   return round($size, 2).$units[$i];
	}
	
?>