<?php
	//run as: php newcorpus.php [setup php file] [mysql username] [database_name]
	//e.g.	: php newcorpus.php histpop-setup.php barona shpps

	//reads the setup file defined as the first argument on the command line.
	require_once($argv[1]);
	
	//regex patterns needed throughout
	$cpos_pattern = "/^\\s+([0-9]+):/";
	$textid_pattern = "/<".$text_id_att."\\s([^>]+)>/";  // <text_id (212)>

	$KWICDelim = "--%%%--";
	$hit_pattern = "/$KWICDelim(.*)$KWICDelim/";
	$left_context_pattern = "/>: (.{100}) $KWICDelim/";
	$right_context_pattern = "/$KWICDelim(.{100})$/";

	
	//get database connection, as will need throughout
	//mysql username is second command line argument
	//database name is third command line argument
	$mysql_username = $argv[2];
	$database_name = $argv[3];
	echo "MySQL Password: ";
	system('stty -echo');
	$mysql_password = trim(fgets(STDIN));
	system('stty echo');
	// add a new line since the users CR didn't echo
	echo "\n";
	
	mb_language('uni');
	mb_internal_encoding('UTF-8');
	$conn = mysql_connect("localhost", $mysql_username, $mysql_password, TRUE) or die(mysql_error());
	mysql_select_db($database_name,$conn) or die( "Unable to select database"); //change database name if necessary
	echo "Connected to database\n";
	
	
	//remove from database if already exists
	
	//first remove corpus specific tables
	$types_table = $cqp_name."_pntypes";
	$tokens_table = $cqp_name."_pntokens";
	$texts_table = $cqp_name."_texts";
	$sql = "DROP TABLE IF EXISTS $types_table";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	$sql = "DROP TABLE IF EXISTS $tokens_table";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	$sql = "DROP TABLE IF EXISTS ".$tokens_table."_structures";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	$sql = "DROP TABLE IF EXISTS $texts_table";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	
	$sql = "SELECT c_id FROM corpora WHERE cqp_name=\"$cqp_name\"";
	$result = mysql_query($sql) or die(mysql_error()."\n".$sql);
	if($row = mysql_fetch_row($result)) {
		echo "Deleting $cqp_name from database.\n";
		$c_id = $row[0];
		$sql = "SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME=\"c_id\" AND TABLE_SCHEMA=\"shpps\"";
		$result = mysql_query($sql) or die(mysql_error()."\n".$sql);
		while($row = mysql_fetch_array($result)) {
			$sql = "DELETE FROM $row[0] WHERE c_id=$c_id";
			mysql_query($sql) or die(mysql_error()."\n".$sql);
		}
	}
	
	//add corpus to database
	echo "Adding corpus to database.\n";
	$sql = "INSERT INTO corpora VALUES(NULL,\"$cqp_name\",\"$display_name\",\"$text_id_att\")";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	$c_id = mysql_insert_id();
	
	//add search types
	$i = 1;
	foreach($search_types as $st=>$desc) {
		$sql = "INSERT INTO search_types VALUES($c_id, \"$st\", \"$desc\", $i)";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
		$i++;
	}
	
	//add within structures
	$i = 1;
	foreach($structures as $s) {
		$sql = "INSERT INTO within_structures VALUES($c_id, \"$s\", $i)";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
		$i++;
	}
	
	//add filter atts
	$i = 1;
	foreach($filter_atts as $fa) {
		$sql = "INSERT INTO filter_atts VALUES($c_id, \"$fa[0]\", \"$fa[1]\", \"$fa[2]\", $i)";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
		$i++;
	}
	
	//add group atts
	$i = 1;
	foreach($group_atts as $ga) {
		$sql = "INSERT INTO group_atts VALUES($c_id, \"$ga\", $i)";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
		$i++;
	}	

	//setup folder for cqp files		
	$folder = $cqp_name."-cqp/";
	if(!file_exists($folder)) {
		mkdir($folder);
	}


	//The following finds all structure instances (as listed in setup file ($structures array)) in corpus, and adds to structures table in database with cpos.
	//Also finds text structures, and adds these too.
	echo "Finding and adding structures to database: ".implode(", ", $structures)."\n";
	$structures[] = $text_element;

	//first run the cqp commands.
	$file = $folder."structure-commands.txt";	
	$fh = fopen($file, 'w') or die("Cannot open file: ".$file);
	fwrite($fh, $cqp_name.";\n");
	fwrite($fh, "set Context 0;\n");
	fwrite($fh, "set ShowTagAttributes on;\n");
	fwrite($fh, "set PrettyPrint off;\n");
	fwrite($fh, "set LeftKWICDelim \"--%%%--\";\n");
	fwrite($fh, "set RightKWICDelim \"--%%%--\";\n");
	fwrite($fh, "set PrintStructures \"$text_id_att\";\n");
		
	foreach($structures as $structure) {
		fwrite($fh, $structure."_query_start = <".$structure.">[];\n");
		fwrite($fh, $structure."_query_end = []</".$structure.">;\n");
		fwrite($fh, "cat ".$structure."_query_start > \"".$folder.$structure."-start-results.txt\";\n");
		fwrite($fh, "cat ".$structure."_query_end > \"".$folder.$structure."-end-results.txt\";\n");
	}
	fclose($fh);
	system("cqp -r /srv/corpora/cqpweb-data/registry -f ".$file. " 2> ".$folder."structure-errors.txt");
	
	
	//now add to database
	foreach($structures as $structure) {
		$fh_in = fopen($folder.$structure."-start-results.txt", 'r');
		$s_id = 0;
		$s_starts = array();
		while(($line = fgets($fh_in)) !== false) {
			//get cpos
			$cpos_search = preg_match($cpos_pattern, $line, $cpos_match) or die("cpos isn't present in output.");
			$cpos = $cpos_match[1];
						
			$s_id++;			
			$s_starts[$s_id] = $cpos;			
		}
		
		$fh_in = fopen($folder.$structure."-end-results.txt", 'r');
		$s_id = 0;
		while(($line = fgets($fh_in)) !== false) {
			//get cpos
			$cpos_search = preg_match($cpos_pattern, $line, $cpos_match) or die("cpos isn't present in output.");
			$cpos_end = $cpos_match[1];

			//get text_id
			$textid_search = preg_match($textid_pattern, $line, $textid_match) or die("$text_id_att isn't present in output.");
			$text_id = $textid_match[1];

			$s_id++;

			$cpos_start = $s_starts[$s_id];

			$sql = "INSERT INTO structures VALUES($c_id, \"$structure\", $s_id, $text_id, $cpos_start, $cpos_end)";
			mysql_query($sql) or die(mysql_error()."\n".$sql);
		}
	}

	
	
	
	
	
	//find placenames and add to database:
	$printstructures = array($text_id_att, $pn_id_att[0]);
	foreach($pn_type_atts as $pn_type_att) {
		$printstructures[] = $pn_type_att[0];
	}
	foreach($pn_token_atts as $pn_token_att) {
		$printstructures[] = $pn_token_att[0];
	}
	echo "Finding placenames and adding to database: ".implode(", ", $printstructures)."\n";

	$file = $folder."pn-commands.txt";	
	$fh = fopen($file, 'w') or die("Cannot open file: ".$file);
	fwrite($fh, $cqp_name.";\n");
	fwrite($fh, "set Context 100;\n");
	fwrite($fh, "set ShowTagAttributes on;\n");
	fwrite($fh, "set PrettyPrint off;\n");
	fwrite($fh, "set LeftKWICDelim \"--%%%--\";\n");
	fwrite($fh, "set RightKWICDelim \"--%%%--\";\n");
	fwrite($fh, "set PrintStructures \"".implode(",",$printstructures)."\";\n");
	fwrite($fh, "pn_query = <".$pn_element.">[]+</".$pn_element.">;\n");
	fwrite($fh, "cat pn_query > \"".$folder."pn-results.txt\";\n"); //placename metadata, etc
	fwrite($fh, "dump pn_query > \"".$folder."pn-cpos-results.txt\";\n"); //placename matchstart and matchend
	fclose($fh);
	system("cqp -r /srv/corpora/cqpweb-data/registry -f ".$file. " 2> ".$folder."placename-errors.txt");
	
	$matchends = array();
	$fh_in = fopen($folder."pn-cpos-results.txt", 'r');
	while(($line = fgets($fh_in)) !== false) {
		$la = explode("\t", $line);
		$matchends[$la[0]] = $la[1];
	}
		
	//create placename types table
	$sql = "CREATE TABLE $types_table (type_id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT, $pn_id_att[0] $pn_id_att[1] NOT NULL";
	foreach($pn_type_atts as $att) {
		$sql .= ", $att[0] $att[1] NOT NULL";
	}
	$sql .= ", corpus_count MEDIUMINT UNSIGNED NOT NULL, PRIMARY KEY (type_id), UNIQUE INDEX ($pn_id_att[0])";
	foreach($pn_type_atts as $att) {
		$sql .= ", INDEX($att[0])";
	}
	$sql .= ") DEFAULT CHARSET=utf8, DEFAULT COLLATE=utf8_unicode_ci";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	
	//create placename tokens - structure refs table
	$sql = "CREATE TABLE ".$tokens_table."_structures (token_id MEDIUMINT UNSIGNED NOT NULL, s_name VARCHAR(16) NOT NULL, s_id MEDIUMINT UNSIGNED NOT NULL, PRIMARY KEY (token_id, s_name), INDEX(s_name), INDEX(s_id)) DEFAULT CHARSET=utf8, DEFAULT COLLATE=utf8_unicode_ci;";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	//create placename tokens table
	$sql = "CREATE TABLE $tokens_table (token_id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT, type_id MEDIUMINT UNSIGNED NOT NULL, text_id MEDIUMINT UNSIGNED NOT NULL";
	foreach($pn_token_atts as $att) {
		$sql .= ", $att[0] $att[1] NOT NULL";
	}
	$sql .= ", matchstart INT UNSIGNED NOT NULL, matchend INT UNSIGNED NOT NULL, corpus_text VARCHAR(128) NOT NULL, left_context CHAR(100) NOT NULL, right_context CHAR(100) NOT NULL, PRIMARY KEY (token_id), INDEX (text_id), UNIQUE INDEX (matchstart), UNIQUE INDEX (matchend)";
	foreach($pn_token_atts as $att) {
		$sql .= ", INDEX($att[0])";
	}
	$sql .= ") DEFAULT CHARSET=utf8, DEFAULT COLLATE=utf8_unicode_ci";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	
	$fh_in = fopen($folder."pn-results.txt", 'r');
	while(($line = fgets($fh_in)) !== false) {
		//get corpus text
		$hit_search = preg_match($hit_pattern, $line, $hit_match) or die("corpus text hit (between $KWICDelim) isn't present in output.");
		$corpus_text = mysql_real_escape_string(stripslashes($hit_match[1]));

		//get left context
		$left_context_search = preg_match($left_context_pattern, $line, $left_context_match) or die("Error: left context not present in CQP output.");
		$left_context = mysql_real_escape_string(stripslashes($left_context_match[1]));
			
		//get right context
		$right_context_search = preg_match($right_context_pattern, $line, $right_context_match) or die("Error: right context not present in CQP output.");
		$right_context = mysql_real_escape_string(stripslashes($right_context_match[1]));

		//get cpos
		$cpos_search = preg_match($cpos_pattern, $line, $cpos_match) or die("cpos isn't present in output.");
		$matchstart = $cpos_match[1];
		
		//lookup matchend
		$matchend = $matchends[$matchstart];
			
		//get text_id
		$textid_search = preg_match($textid_pattern, $line, $textid_match) or die("$text_id_att isn't present in output.");
		$text_id = $textid_match[1];
		
		$pattern = "/<".$pn_id_att[0]."\\s([^>]+)>/";  // <enamex_gazref (geonames:123)>
		$search = preg_match($pattern, $line, $match);
		
		if(!$search || $match[1]=="no_value") {
			echo "$pn_id_att[0] isn't present in output line.\n$line";
			continue;
		}
		
		$pn_id = $match[1];
		if(startsWith($pn_id_att[1], "VARCHAR") || startsWith($att[1], "ENUM")) { //if VARCHAR, then quote for database addition
			$pn_id = "\"".$pn_id."\"";
		}
		
		$pn_token_vals = array();
		foreach($pn_token_atts as $att) {
			$pattern = "/<".$att[0]."\\s([^>]+)>/";  // <enamex_sw (w123)>
			$search = preg_match($pattern, $line, $match) or die("$att[0] isn't present in output line.\n$line");
			if(startsWith($att[1], "VARCHAR") || startsWith($att[1], "ENUM")) { //add quotes if varchar for database entry
				$pn_token_vals[] = "\"$match[1]\"";
			}
			else {
				$pn_token_vals[] = $match[1];
			}
		}
		
		$pn_type_vals = array();
		foreach($pn_type_atts as $att) {
			$pattern = "/<".$att[0]."\\s([^>]+)>/";  // <enamex_long (52.01234)>
			$search = preg_match($pattern, $line, $match) or die("$att[0] isn't present in output line.\n$line");
			if(startsWith($att[1], "VARCHAR") || startsWith($att[1], "ENUM")) { //add quotes if varchar for database entry
				$pn_type_vals[] = "\"$match[1]\"";
			}
			else {
				$pn_type_vals[] = $match[1];
			}
		}
		
		
		//add to types table
		$sql = "SELECT type_id, corpus_count FROM $types_table WHERE $pn_id_att[0] = $pn_id LIMIT 1";
		$result = mysql_query($sql) or die(mysql_error()."\n".$sql);
		if($row = mysql_fetch_array($result)) { //type exists, so get type_id and increment corpus count.
			$type_id = $row['type_id'];
			$newcount = $row['corpus_count']+1;
			$sql = "UPDATE $types_table SET corpus_count = $newcount WHERE type_id=$type_id";
			mysql_query($sql) or die(mysql_error()."\n".$sql);
		}
		else { //type doesn't exist, so add with corpus count of 1
			$sql = "INSERT INTO $types_table VALUES (NULL, $pn_id, ".implode(", ", $pn_type_vals).", 1)";
			mysql_query($sql) or die(mysql_error()."\n".$sql);
			$type_id = mysql_insert_id();
		}
		
		//add to tokens table
		if(empty($pn_token_vals)) {
			$sql = "INSERT INTO $tokens_table VALUES (NULL, $type_id, $text_id, $matchstart, $matchend, \"$corpus_text\", \"$left_context\", \"$right_context\")";
		}
		else {
			$sql = "INSERT INTO $tokens_table VALUES (NULL, $type_id, $text_id, ".implode(", ", $pn_token_vals).", $matchstart, $matchend, \"$corpus_text\", \"$left_context\", \"$right_context\")";
		}
		mysql_query($sql) or die(mysql_error()."\n".$sql);
		$token_id = mysql_insert_id();
		$sql = "INSERT INTO ".$tokens_table."_structures SELECT $token_id, s_name, s_id FROM structures WHERE c_id=$c_id AND text_id=$text_id AND cpos_start<=$matchstart AND cpos_end>=$matchstart;";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
	}	
	
	//add text metadata
	
	//create text metadata table
	$sql = "CREATE TABLE $texts_table (text_id MEDIUMINT UNSIGNED NOT NULL";
	foreach($text_meta_fields as $field) {
		$sql .= ", $field[0] $field[2]";
	}
	$sql .= ") DEFAULT CHARSET=utf8, DEFAULT COLLATE=utf8_unicode_ci";
	mysql_query($sql) or die(mysql_error()."\n".$sql);

	//read from meta table
	$fh_in = fopen($text_meta_file, 'r');
	while(($line = fgets($fh_in)) !== false) {
		$la = explode("\t", $line);
		$vals = array($la[0]); //add text_id
		foreach($text_meta_fields as $field) {
			if(startsWith($field[2], "VARCHAR") || startsWith($field[2], "ENUM")) {
				$vals[] = "\"".$la[$field[1]]."\"";
			}
			else {
				$vals[] = $la[$field[1]];
			}
		}
		$sql = "INSERT INTO $texts_table VALUES (".implode(", ", $vals).")";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
	}
	fclose($fh_in);


	//populate output_atts, these are the database columns to include in the outputted csv files.
	$sql = "INSERT INTO output_atts VALUES ($c_id, \"pntypes\",\"".$pn_id_att[0]."\")";
	mysql_query($sql) or die(mysql_error()."\n".$sql);
	foreach($pn_type_atts as $att) {
		$sql = "INSERT INTO output_atts VALUES ($c_id, \"pntypes\",\"".$att[0]."\")";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
	}
	foreach($pn_token_atts as $att) {
		$sql = "INSERT INTO output_atts VALUES ($c_id, \"pntokens\",\"".$att[0]."\")";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
	}
	foreach($text_meta_fields as $att) {
		$sql = "INSERT INTO output_atts VALUES ($c_id, \"texts\",\"".$att[0]."\")";
		mysql_query($sql) or die(mysql_error()."\n".$sql);
	}




	mysql_close();
	
	function startsWith($haystack, $needle) {
 	   return !strncmp($haystack, $needle, strlen($needle));
	}

	function endsWith($haystack, $needle) {
	    $length = strlen($needle);
	    if ($length == 0) {
	        return true;
	    }
	    return (substr($haystack, -$length) === $needle);
	}
?>