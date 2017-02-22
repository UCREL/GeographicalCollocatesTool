<?php

	//returns a cqp query
	//$terms contains an array of array(type(word,pos,hw,semtag,class,lemma), value(e.g. "cholera", "JJ", "A1.*"), casesensitive(true|false)
	function build_query($attributes, $values, $casesensitives) {
		$terms = count($attributes);
		if(count($values)!=$terms) {
			return "Error";
		}
		if(count($casesensitives)!=$terms) {
			return "Error";
		}
		$query = "";
		for($i=0;$i<$terms;$i++) {
			$query .= sprintf("[%s='%s'%s] ", $attributes[$i], $values[$i], ($casesensitives[$i] ? "" : " %c"));
		}
		return trim($query);
	}
	
	function run_cqp_query($corpus_cqp_name, $query_text, $folder, $text_id_att, &$analysis_files = array()) {
		$concordances_file = $folder."concordances.txt";
		$indexes_file = $folder."indexes.txt";
//		if(file_exists($concordances_file) && file_exists($indexes_file)) {
//			return true; //no need to re-do query.
//		}
		$commands_file = $folder."commands.txt";
		$errors_file = $folder."errors.txt";
		delete_if_exists($commands_file);
		delete_if_exists($indexes_file);
		delete_if_exists($concordances_file);
		delete_if_exists($errors_file);
		$fh = fopen(($commands_file), 'w') or die(("Cannot open file: ".$commands_file));
		fwrite($fh, "$corpus_cqp_name;");
		fwrite($fh, "\nset ShowTagAttributes on;");
		fwrite($fh, "\nset Context 100;");
		fwrite($fh, "\nset PrettyPrint off;");
		fwrite($fh, "\nset LeftKWICDelim \"--%%%--\";");
		fwrite($fh, "\nset RightKWICDelim \"--%%%--\";");
		fwrite($fh, "\nset PrintStructures \"$text_id_att\";");
		fwrite($fh, ("\nquery = ".stripslashes($query_text).";"));
		fwrite($fh, "\ncat query > \"".$concordances_file."\";");
		fwrite($fh, "\ndump query > \"".$indexes_file."\";");
		fclose($fh);
		system("cqp -r /srv/corpora/cqpweb-data/registry -f ".$commands_file. " 2> ".$errors_file);
		if(filesize($errors_file)==0) {
			$analysis_files["concordances.txt"] = "CWB Query Concordances";
			return true;
		}
		else {
			$analysis_files["errors.txt"] = "CWB Error message";
			return false;
		}
	}
	
	//check works with no within.
	function create_temp_matches_table($c_id, $temp_table_prefix, $folder, $text_id_att, $within_type, $within_count, $filtered, $text_ids) {	
		$concordances_file = $folder."concordances.txt";
		$indexes_file = $folder."indexes.txt";
		if(!file_exists($concordances_file) || !file_exists($indexes_file)) {
			return false; //files must be present to parse.
		}
		
		$fh_in = fopen($indexes_file, 'r');
		$t_indexes = array();
		while(($line = fgets($fh_in)) !== false) {
			$la = explode("\t", $line);
			$t_indexes[$la[0]] = $la[1];
		}
		
		$cpos_pattern = "/^\\s*([0-9]+):/";
		$textid_pattern = "/<".$text_id_att."\\s([^>]+)>/";  // <text_id (212)>
		$KWICDelim = "--%%%--";
		$hit_pattern = "/$KWICDelim(.*)$KWICDelim/";
		$left_context_pattern = "/>: (.{100}) $KWICDelim/";
		$right_context_pattern = "/$KWICDelim(.{100})$/";

		require_once("db_connect.php");
		$temp_table = $temp_table_prefix."_matches";
		$sql = "DROP TABLE IF EXISTS $temp_table";
		mysql_query($sql) or die("Error occurred creating temp tokens table.");
		$sql = "CREATE TEMPORARY TABLE $temp_table (q_start INT UNSIGNED NOT NULL, q_end INT UNSIGNED NOT NULL, text_id MEDIUMINT UNSIGNED NOT NULL, s_id MEDIUMINT UNSIGNED NOT NULL, corpus_text VARCHAR(128) NOT NULL, left_context CHAR(100) NOT NULL, right_context CHAR(100) NOT NULL) DEFAULT charset=utf8, DEFAULT collate=utf8_unicode_ci;";
		mysql_query($sql) or die("Error occurred creating temp tokens table.");

		$fh_in = fopen($concordances_file, 'r');
		while(($line = fgets($fh_in)) !== false) {
			//get text_id
			$textid_search = preg_match($textid_pattern, $line, $textid_match) or die("Error: $text_id_att isn't present in CQP output.");
			$text_id = $textid_match[1];
			
			if($filtered && !in_array($text_id, $text_ids)) {
				continue;
			}
			//get cpos
			$cpos_search = preg_match($cpos_pattern, $line, $cpos_match) or die("Error: cpos isn't present in CQP output.");
			$cpos_start = $cpos_match[1];
			$cpos_end = $t_indexes[$cpos_start];

			//get corpus text
			$hit_search = preg_match($hit_pattern, $line, $hit_match) or die("Error: corpus text hit (between $KWICDelim) isn't present in CQP output.");
			$corpus_text = mysql_real_escape_string(stripslashes($hit_match[1]));
			
			//get left context
			$left_context_search = preg_match($left_context_pattern, $line, $left_context_match) or die("Error: left context not present in CQP output.");
			$left_context = mysql_real_escape_string(stripslashes($left_context_match[1]));
			
			//get right context
			$right_context_search = preg_match($right_context_pattern, $line, $right_context_match) or die("Error: right context not present in CQP output.");
			$right_context = mysql_real_escape_string(stripslashes($right_context_match[1]));


			if($within_type==NULL) {
				$s_id_min=0;
				$s_id_max=0;
			}
			else {
				$sql = "SELECT s_id FROM structures WHERE c_id=$c_id AND s_name=\"$within_type\" AND text_id=$text_id AND cpos_start<=$cpos_start AND cpos_end>=$cpos_end LIMIT 1";
				$result = mysql_query($sql) or die("Error occurred creating temp matches table.");
				$row = mysql_fetch_row($result);
				$s_id = $row[0];
				$s_id_min = $s_id - ($within_count-1); //i.e. if within 1, then should be 0.
				$s_id_max = $s_id + ($within_count-1);
			}
			
			//repeat each match for sentence and surrounding sentences within proximity (e.g. if within 2s, then include sentence before and sentence after as well as source sentence), this allows for quick matching against the placenames table (without having to search within a range).
			for($s_id=$s_id_min;$s_id<=$s_id_max;$s_id++) {
				$sql = "INSERT INTO $temp_table VALUES ($cpos_start, $cpos_end, $text_id, $s_id, \"$corpus_text\", \"$left_context\", \"$right_context\")";
				mysql_query($sql) or die("Error occurred inserting into temp tokens table.");
			}
		}
//		$sql = "CREATE UNIQUE INDEX qstart ON $temp_table (s_id, q_start)";
//		mysql_query($sql) or die("Error occurred creating temp tokens table index.");
//		$sql = "CREATE UNIQUE INDEX qend ON $temp_table (s_id, q_end)";
//		mysql_query($sql) or die("Error occurred creating temp tokens table index.");
		$sql = "CREATE INDEX textid ON $temp_table (text_id)";
//		mysql_query($sql) or die("Error occurred creating temp tokens table index.");
//		$sql = "CREATE UNIQUE INDEX tse ON $temp_table (text_id, s_id, q_start, q_end)";
		mysql_query($sql) or die("Error occurred creating temp tokens table index.");
		$sql = "CREATE INDEX sid ON $temp_table (s_id)";
		mysql_query($sql) or die("Error occurred creating temp tokens table index.");
		$sql = "CREATE INDEX ts ON $temp_table (text_id, s_id)";
		mysql_query($sql) or die("Error occurred creating temp tokens table index.");
	}
	
	function create_filtered_texts($c_id, $temp_table_prefix, $filter_id, $corpus_cqp_name) {
		require_once("db_connect.php");		
		
		$wheres = array();
		
		$sql = "SELECT field, COUNT(*) AS count, val FROM filter_group_include, filter_atts WHERE c_id=$c_id AND filter_id=$filter_id AND source_table=\"texts\" AND search_type=\"group\" AND field=table_column GROUP BY field";
		$result = mysql_query($sql) or die("Error occurred creating temp texts table.");
		
		while($row = mysql_fetch_array($result)) {
			$row_field = $row['field'];
			if($row['count']==1) {
				$row_val = $row['val'];
				$wheres[] = "$row_field=\"$row_val\"";
			}
			else {
				$wheres[] = "$row_field IN (SELECT val FROM filter_group_include WHERE filter_id=$filter_id AND field=\"$row_field\")";
			}
		}
		
		$sql = "SELECT field, minimum, maximum FROM filter_range_include, filter_atts WHERE c_id=$c_id AND filter_id=$filter_id AND source_table=\"texts\" AND search_type=\"range\" AND field=table_column";
		$result = mysql_query($sql) or die("Error occurred creating temp texts table.");
		while($row = mysql_fetch_array($result)) {
			$row_field = $row['field'];
			$row_min = $row['minimum'];
			$row_max = $row['maximum'];
			$wheres[] = "$row_field>=$row_min AND $row_field<=$row_max";
		}
		
		$temp_table = $temp_table_prefix."_texts";
		$text_ids = array();
		if(!empty($wheres)) {
			$sql = "DROP TABLE IF EXISTS $temp_table";
			mysql_query($sql) or die("Error occurred creating temp texts table.");
			$sql = "CREATE TEMPORARY TABLE $temp_table AS (SELECT * FROM ".$corpus_cqp_name."_texts WHERE ". implode(" AND ", $wheres).")";
			mysql_query($sql) or die("Error occurred creating temp texts table.");
			$sql = "ALTER TABLE $temp_table ADD PRIMARY KEY (text_id)";
			mysql_query($sql) or die("Error occurred creating temp texts table.");
			
			$sql = "SELECT text_id FROM $temp_table";
			$result = mysql_query($sql) or die("Error occurred creating temp texts table.");
			while($row = mysql_fetch_row($result)) {
				$text_ids[] = $row[0];
			}
		}
		return $text_ids;
	}
	
	function create_temp_pntokens_table($c_id, $temp_table_prefix, $filter_id, $corpus_cqp_name, $text_filter, $within_type) {
		require_once("db_connect.php");
		
		$wheres = array();
		
		$sql = "SELECT field, COUNT(*) AS count, val FROM filter_group_include, filter_atts WHERE c_id=$c_id AND filter_id=$filter_id AND source_table=\"pntypes\" AND search_type=\"group\" AND field=table_column GROUP BY field";
		$result = mysql_query($sql) or die("Error occurred creating temp pntokens table\n$sql");
		
		while($row = mysql_fetch_array($result)) {
			$row_field = $row['field'];
			if($row['count']==1) {
				$row_val = $row['val'];
				$wheres[] = "typ.$row_field=\"$row_val\"";
			}
			else {
				$wheres[] = "typ.$row_field IN (SELECT val FROM filter_group_include WHERE filter_id=$filter_id AND field=\"$row_field\")";
			}
		}
		
		$sql = "SELECT field, minimum, maximum FROM filter_range_include, filter_atts WHERE c_id=$c_id AND filter_id=$filter_id AND source_table=\"pntypes\" AND search_type=\"range\" AND field=table_column";
		$result = mysql_query($sql) or die("Error occurred creating temp pntokens table.\n$sql");
		while($row = mysql_fetch_array($result)) {
			$row_field = $row['field'];
			$row_min = $row['minimum'];
			$row_max = $row['maximum'];
			$wheres[] = "typ.$row_field>=$row_min AND typ.$row_field<=$row_max";
		}
		
		$include_types = false;
		if(!empty($wheres)) {
			$wheres[] = "tok.type_id=typ.type_id";
			$include_types = true;
		}
		
		
		$sql = "SELECT field, COUNT(*) AS count, val FROM filter_group_include, filter_atts WHERE c_id=$c_id AND filter_id=$filter_id AND source_table=\"pntokens\" AND search_type=\"group\" AND field=table_column GROUP BY field";
		$result = mysql_query($sql) or die("Error occurred creating temp pntokens table.\n$sql");
		
		while($row = mysql_fetch_array($result)) {
			$row_field = $row['field'];
			if($row['count']==1) {
				$row_val = $row['val'];
				$wheres[] = "tok.$row_field=\"$row_val\"";
			}
			else {
				$wheres[] = "tok.$row_field IN (SELECT val FROM filter_group_include WHERE filter_id=$filter_id AND field=\"$row_field\")";
			}
		}
		
		$sql = "SELECT field, minimum, maximum FROM filter_range_include, filter_atts WHERE c_id=$c_id AND filter_id=$filter_id AND source_table=\"pntokens\" AND search_type=\"range\" AND field=table_column";
		$result = mysql_query($sql) or die("Error occurred creating temp pntokens table.\n$sql");
		while($row = mysql_fetch_array($result)) {
			$row_field = $row['field'];
			$row_min = $row['minimum'];
			$row_max = $row['maximum'];
			$wheres[] = "tok.$row_field>=$row_min AND tok.$row_field<=$row_max";
		}				
		
		if($text_filter)
			$wheres[] = "tok.text_id=txt.text_id";

		$within = ($within_type!=null);		
		if($within) {
			$wheres[] = "ps.s_name=\"$within_type\"";
			$wheres[] = "ps.token_id=tok.token_id";
		}
		$temp_table = $temp_table_prefix."_pntokens";
		$tokens_table = $corpus_cqp_name."_pntokens";

		$ps_table = "";
		if($within) {
			$ps_table = ", ".$corpus_cqp_name."_pntokens_structures AS ps";
		}
		$types_table = "";
		if($include_types) {
			$types_table = ", ".$corpus_cqp_name."_pntypes AS typ";
		}
		$texts_table = "";
		if($text_filter) {
			$texts_table = ", ".$temp_table_prefix."_texts AS txt";
		}
		$sql = "DROP TABLE IF EXISTS $temp_table";
		mysql_query($sql) or die("Error occurred creating temp pntokens table.\n$sql");
		$sql = "CREATE TEMPORARY TABLE $temp_table AS (SELECT tok.token_id, tok.text_id, tok.matchstart, tok.matchend".($within ? ", s_id" : "")." FROM $tokens_table AS tok $ps_table $types_table $texts_table".(empty($wheres) ? "" : " WHERE ". implode(" AND ", $wheres)).")";
		mysql_query($sql) or die("Error occurred creating temp pntokens table.\n$sql");
		$sql = "ALTER TABLE $temp_table ADD PRIMARY KEY(token_id)";
		mysql_query($sql) or die("Error occurred creating temp pntokens table index.");
		if($within) {
			$sql = "CREATE INDEX sid ON $temp_table (s_id)";
			mysql_query($sql) or die("Error occurred creating temp pntokens table index.");
		}
		$sql = "CREATE INDEX tid ON $temp_table (text_id)";
		mysql_query($sql) or die("Error occurred creating temp pntokens table index.");
		if($within) {
			$sql = "CREATE INDEX tsid ON $temp_table (text_id, s_id)";
			mysql_query($sql) or die("Error occurred creating temp pntokens table index.");
		}
		$sql = "CREATE UNIQUE INDEX ms ON $temp_table (matchstart)";
		mysql_query($sql) or die("Error occurred creating temp pntokens table index.");
		$sql = "CREATE UNIQUE INDEX me ON $temp_table (matchend)";
		mysql_query($sql) or die("Error occurred creating temp pntokens table index.");
	}
	
	function create_temp_pnmatches_table($temp_table_prefix, $within, $look_left, $look_right, $unique_pntokens) {
		require_once("db_connect.php");
		if($unique_pntokens) {
			$groupby = " GROUP BY pntoken_id";
			$temp_table = $temp_table_prefix."_uniquepnmatches";
		}
		else {
			$groupby = "";
			$temp_table = $temp_table_prefix."_pnmatches";
		}

		$matches_table = $temp_table_prefix."_matches";
		$pntokens_table = $temp_table_prefix."_pntokens";
		
		$wheres = array();
		$wheres[] = "m.text_id=p.text_id";
		
		if($within) {
			$wheres[] = "m.s_id=p.s_id";
		}
			
		//q_start/q_end = query match
		//matchstart/matchend = placename match	
		
		mysql_query("SET sql_mode = 'NO_UNSIGNED_SUBTRACTION'"); //needed for mysql 5.5.5+ so can subtract unsigned ints without causing error, easier than casting. See http://dev.mysql.com/doc/refman/5.5/en/out-of-range-and-overflow.html.
			
		if($look_left!=NULL && $look_right!=NULL) {
			$wheres[] = "((q_start-matchend)<=$look_left OR (matchstart-q_end)<=$look_right)";
		}
		else if($look_left==NULL && $look_right==NULL) {
			//nothing to add
		}
		else if($look_left==NULL) {
			$wheres[] = "((q_start-matchend)>0 OR (matchstart-q_end)<=$look_right)";
		}
		else if($look_right==NULL) {
			$wheres[] = "((q_start-matchend)<=$look_left OR (matchstart-q_end)>0)";
		}
		
		$sql = "DROP TABLE IF EXISTS $temp_table";
		mysql_query($sql) or die("Error occurred creating temp pnmatches table.".$sql);
		$sql = "CREATE TEMPORARY TABLE $temp_table AS (SELECT m.q_start AS match_start, m.q_end AS match_end, m.corpus_text AS match_text, m.left_context AS match_left, m.right_context AS match_right, p.token_id AS pntoken_id FROM $matches_table AS m, $pntokens_table AS p WHERE ".implode(" AND ", $wheres).$groupby.")";
		mysql_query($sql) or die("Error occurred creating temp pnmatches table.".$sql.mysql_error());
		$sql = "CREATE INDEX pid ON $temp_table (pntoken_id)";
		mysql_query($sql) or die("Error occurred creating temp pnmatches table.".$sql);
	}
	
	//corpus_cqp_name = corpus database prefix, e.g. HISTPOP_texts
	function output_tokens_file($c_id, $temp_table_prefix, $corpus_cqp_name, $folder, &$analysis_files=array(), $unique_pntokens) {
		require_once("db_connect.php");

		$columns = array("pntokens.token_id AS PN_TokenID", "pntokens.matchstart AS PN_StartIndex", "pntokens.matchend AS PN_EndIndex", "pntokens.left_context AS PN_LeftContext", "pntokens.corpus_text AS PN_CorpusText", "pntokens.right_context AS PN_RightContext");
				
		$sql = "SELECT table_column FROM output_atts WHERE c_id=$c_id AND source_table=\"pntokens\"";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		while($row = mysql_fetch_array($result)) {
			$columns[] = "pntokens.".$row['table_column']." AS PN_".$row['table_column'];
		}
		
		$columns[] = "pntypes.type_id AS PN_TypeID";
		
		$sql = "SELECT table_column FROM output_atts WHERE c_id=$c_id AND source_table=\"pntypes\"";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		while($row = mysql_fetch_array($result)) {
			$columns[] = "pntypes.".$row['table_column']." AS PN_".$row['table_column'];
		}
		
		$columns[] = "texts.text_id AS Text_ID";
		
		$sql = "SELECT table_column FROM output_atts WHERE c_id=$c_id AND source_table=\"texts\"";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		while($row = mysql_fetch_array($result)) {
			$columns[] = "texts.".$row['table_column']." AS Text_".$row['table_column'];
		}
		
		$columns[] = "pnmatches.match_start AS QM_StartIndex";
		$columns[] = "pnmatches.match_end AS QM_EndIndex";
		$columns[] = "pnmatches.match_left AS QM_LeftContext";
		$columns[] = "pnmatches.match_text AS QM_CorpusText";
		$columns[] = "pnmatches.match_right AS QM_RightContext";
		
		$texts_table = $corpus_cqp_name."_texts";
		$pntokens_table = $corpus_cqp_name."_pntokens";
		$pntypes_table = $corpus_cqp_name."_pntypes";
		if($unique_pntokens)
			$pnmatches_table = $temp_table_prefix."_uniquepnmatches";
		else
			$pnmatches_table = $temp_table_prefix."_pnmatches";
		
		$sql = "SELECT ".implode(", ", $columns)." FROM $texts_table AS texts, $pntokens_table AS pntokens, $pntypes_table AS pntypes, $pnmatches_table AS pnmatches WHERE pnmatches.pntoken_id = pntokens.token_id AND pntokens.type_id = pntypes.type_id AND pntokens.text_id = texts.text_id ORDER BY QM_StartIndex ASC, PN_StartIndex ASC";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		$fn = ($unique_pntokens ? "unique_" : "")."match_tokens.csv";
		output_mysql_result_to_csv($result, $folder.$fn);
		$analysis_files[$fn] = "All query".($unique_pntokens ? " unique " : " ")."match tokens";
	}
	
	//corpus_cqp_name = corpus database prefix, e.g. HISTPOP_texts
	function output_allpntokens_file($c_id, $text_filter, $temp_table_prefix, $corpus_cqp_name, $folder, &$analysis_files=array()) {
		require_once("db_connect.php");

		$columns = array("pntokens.token_id AS PN_TokenID", "pntokens.matchstart AS PN_StartIndex", "pntokens.matchend AS PN_EndIndex", "pntokens.left_context AS PN_LeftContext", "pntokens.corpus_text AS PN_CorpusText", "pntokens.right_context AS PN_RightContext");
				
		$sql = "SELECT table_column FROM output_atts WHERE c_id=$c_id AND source_table=\"pntokens\"";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		while($row = mysql_fetch_array($result)) {
			$columns[] = "pntokens.".$row['table_column']." AS PN_".$row['table_column'];
		}
		
		$columns[] = "pntypes.type_id AS PN_TypeID";
		
		$sql = "SELECT table_column FROM output_atts WHERE c_id=$c_id AND source_table=\"pntypes\"";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		while($row = mysql_fetch_array($result)) {
			$columns[] = "pntypes.".$row['table_column']." AS PN_".$row['table_column'];
		}
		
		$columns[] = "texts.text_id AS Text_ID";
		
		$sql = "SELECT table_column FROM output_atts WHERE c_id=$c_id AND source_table=\"texts\"";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		while($row = mysql_fetch_array($result)) {
			$columns[] = "texts.".$row['table_column']." AS Text_".$row['table_column'];
		}
		
		$texts_table = $corpus_cqp_name."_texts";
		if($text_filter) {
			$texts_table = $temp_table_prefix."_texts";
		}
		$pntokens_table = $corpus_cqp_name."_pntokens";
		$pntypes_table = $corpus_cqp_name."_pntypes";
		
		$sql = "SELECT ".implode(", ", $columns)." FROM $texts_table AS texts, $pntokens_table AS pntokens, $pntypes_table AS pntypes WHERE pntokens.type_id = pntypes.type_id AND pntokens.text_id = texts.text_id ORDER BY PN_StartIndex ASC";
		$result = mysql_query($sql) or die("Error occurred outputting all placenames tokens file.".$sql.mysql_error());
		output_mysql_result_to_csv($result, $folder."placename_tokens.csv");
		
		$analysis_files["placename_tokens.csv"] = "All placename tokens in (filtered) corpus";
	}
	
	//corpus_cqp_name = corpus database prefix, e.g. HISTPOP_texts
	function output_types_file($temp_table_prefix, $corpus_cqp_name, $folder, &$analysis_files=array()) {
		require_once("db_connect.php");
		
		$pntokens_table = $corpus_cqp_name."_pntokens";
		$pntypes_table = $corpus_cqp_name."_pntypes";
		$pnmatches_table = $temp_table_prefix."_pnmatches";
		$pnfiltered_table = $temp_table_prefix."_pntokens";
		
		//first without zeroes
		
		$sql = "SELECT pntypes.*, COUNT(*) AS QM_Freq, COUNT(DISTINCT pntokens.token_id) AS UQM_Freq FROM $pntokens_table AS pntokens, $pntypes_table AS pntypes, $pnmatches_table AS pnmatches WHERE pnmatches.pntoken_id = pntokens.token_id AND pntokens.type_id = pntypes.type_id GROUP BY pntypes.type_id";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		
		//need number of fields, to get count column number.		
		$num_fields = mysql_num_fields($result);
		$key_col = 0;
		$save_cols = array($num_fields-2, $num_fields-1);
		
		$match_counts = output_mysql_result_to_csv($result, $folder."match_types.csv", $key_col, $save_cols);
		$analysis_files["match_types.csv"] = "All query match types";
		
		//now do with zeroes
		//do same query as above but with filtered pntokens table instead pnmatches, thus filtered sub-corpus placename counts
		$sql = "SELECT pntypes.*, COUNT(*) AS SC_Freq FROM $pntokens_table AS pntokens, $pntypes_table AS pntypes, $pnfiltered_table AS pnfiltered WHERE pnfiltered.token_id = pntokens.token_id AND pntokens.type_id = pntypes.type_id GROUP BY pntypes.type_id";
		$result = mysql_query($sql) or die("Error occurred outputting tokens file.".$sql.mysql_error());
		output_mysql_result_to_csv($result, $folder."match_types_with_zeros.csv", $key_col, null, array("QM_Freq", "UQM_Freq"), $match_counts);
		$analysis_files["match_types_with_zeros.csv"] = "All query match types, including zero count placenames";
	}
	
	function output_grouped_counts($temp_table_prefix, $corpus_cqp_name, $folder, $field, $texts_filtered, &$analysis_files=array(), $unique_pntokens) {
		require_once("db_connect.php");
		
		$pntokens_table = $corpus_cqp_name."_pntokens";
		$pntypes_table = $corpus_cqp_name."_pntypes";
		if($unique_pntokens) {
			$pnmatches_table = $temp_table_prefix."_uniquepnmatches";
			$matches_col = "UQM_Freq";
		}
		else {
			$pnmatches_table = $temp_table_prefix."_pnmatches";
			$matches_col = "QM_Freq";
		}
		$pnfiltered_table = $temp_table_prefix."_pntokens";
		if($texts_filtered)
			$texts_table = $temp_table_prefix."_texts";
		else {
			$texts_table = $corpus_cqp_name."_texts";
		}
		
		//get all field vals and construct counts for SELECT
		$sql = "SELECT $field FROM $texts_table GROUP BY $field ORDER BY $field ASC";
		$result = mysql_query($sql) or die("Error occurred outputting $field file.".mysql_error().$sql);
		$field_selects = array();
		$column_headers = array($matches_col);
		while($row=mysql_fetch_row($result)) {
			$val = $row[0];
			//need to remove invalid chars as will be used as column header
			$col_header = preg_replace("/[^A-Za-z0-9_]+/", "_", $val);
			$field_selects[] = "SUM(if(texts.$field = '$val',1,0)) AS ".$field."_".$col_header;
			$column_headers[] = $field."_".$col_header;
		}
		
		//first without zeroes
		$sql = "SELECT pntypes.*, COUNT(*) AS $matches_col, ".implode(", ", $field_selects)." FROM $pntokens_table AS pntokens, $pntypes_table AS pntypes, $pnmatches_table AS pnmatches, $texts_table AS texts WHERE texts.text_id = pntokens.text_id AND pnmatches.pntoken_id = pntokens.token_id AND pntokens.type_id = pntypes.type_id GROUP BY pntypes.type_id";
		$result = mysql_query($sql) or die("Error occurred outputting $field file.".mysql_error().$sql);
		
		//need number of fields, to get count column number.		
		$num_fields = mysql_num_fields($result);
		$key_col = 0;
		//first col is num fields - all fields
		$first_col = $num_fields - sizeof($column_headers);
		//
		$save_cols = range($first_col,$num_fields-1);
		
		$fn = "grouped_".$field.($unique_pntokens ? "_unique" : "")."_matches.csv";
		$match_counts = output_mysql_result_to_csv($result, $folder.$fn, $key_col, $save_cols);
		$analysis_files[$fn] = "$field counts: query".($unique_pntokens ? " unique " : " ")."match types";
		
		//now with zeros, basically types table from output_types_file, with above counts added.
		$sql = "SELECT pntypes.*, COUNT(*) AS SC_Freq FROM $pntokens_table AS pntokens, $pntypes_table AS pntypes, $pnfiltered_table AS pnfiltered WHERE pnfiltered.token_id = pntokens.token_id AND pntokens.type_id = pntypes.type_id GROUP BY pntypes.type_id";
		$result = mysql_query($sql) or die("Error occurred outputting $field file.".$sql.mysql_error());
		$fn = "grouped_".$field.($unique_pntokens ? "_unique" : "")."_matches_with_zeros.csv";
		output_mysql_result_to_csv($result, $folder.$fn, $key_col, null, $column_headers, $match_counts);
		$analysis_files[$fn] = "$field counts: query".($unique_pntokens ? " unique " : " ")."match types, including zero count placenames";
		
		//seperate table with sub corpus counts for each group field, same as without zeros but done over pnfiltered table instead of pnmatches
		$sql = "SELECT pntypes.*, COUNT(*) AS SC_Freq, ".implode(", ", $field_selects)." FROM $pntokens_table AS pntokens, $pntypes_table AS pntypes, $pnfiltered_table AS pnfiltered, $texts_table AS texts WHERE texts.text_id=pnfiltered.text_id AND texts.text_id = pntokens.text_id AND pnfiltered.token_id = pntokens.token_id AND pntokens.type_id = pntypes.type_id GROUP BY pntypes.type_id";
		$result = mysql_query($sql) or die("Error occurred outputting $field file.".$sql.mysql_error());	
		output_mysql_result_to_csv($result, $folder."grouped_".$field."_subcorpus_counts.csv");	
		$analysis_files["grouped_".$field."_subcorpus_counts.csv"] = "$field counts: all placenames in (filtered) corpus";
	}
	
	//option to save a set of cols, returned in array if so, set $key_col to primary key and $save_cols to array of column numbers to save.
	//also option to include saved cols in csv with $extra_headers (array of column headers) and $extra_vals (array of primary keys mapped to array of column values), $key_col must be set to primary key column
	function output_mysql_result_to_csv($result, $file, $key_col=null, $save_cols=null, $extra_headers=null, $extra_vals=null) {
		$num_fields = mysql_num_fields($result);
		$headers = array();
		for($i=0;$i<$num_fields;$i++) {
			$headers[] = mysql_field_name($result, $i);
		}
		if(isset($extra_headers)) {
			$headers = array_merge($headers, $extra_headers);
			$zeros = array_fill(0,sizeof($extra_headers),0); //fill an array of zeros for when key isn't present.
		}
		
		delete_if_exists($file);
		
		$fh = fopen($file, 'w')  or die(("Cannot open file: ".$file));
		fputcsv($fh, $headers);
		
		if(isset($save_cols)) {
			$saved = array();
		}
		while($row = mysql_fetch_row($result)) {
			if(isset($extra_vals)) {
				if(isset($extra_vals[$row[$key_col]])) {
					$row = array_merge($row, $extra_vals[$row[$key_col]]);
				}
				else {
					$row = array_merge($row, $zeros);
				}
			}
			
			fputcsv($fh, $row);
			if(isset($save_cols)) {
				$to_save = array();
				foreach($save_cols as $col) {
					$to_save[] = $row[$col];
				}
				$saved[$row[$key_col]] = $to_save;
			}
		}
		fclose($fh);
		
		if(isset($save_cols)) {
			return $saved;
		}
	}
	
	function run_analysis($c_id, $query_id, $proximity_id, $filter_id, $pntokens_table, $tokens_table, $types_table, $grouped_by) {
		require_once("db_connect.php");
		require_once("db_methods.php");
		$sql = "SELECT cqp_name, text_id_att, display_name FROM corpora WHERE c_id=$c_id";
		$result = mysql_query($sql) or die("Error occurred. SH601.");
		$row = mysql_fetch_array($result);
		$corpus_cqp_name = $row['cqp_name'];
		$text_id_att = $row['text_id_att'];
		$corpus_display_name = $row['display_name'];

		$sql = "SELECT query_name, query_text FROM queries WHERE query_id=$query_id";
		$result = mysql_query($sql) or die("Error occurred. SH602.");
		$row = mysql_fetch_array($result);
		$query_text = $row['query_text'];
		$query_name = $row['query_name'];
		
		$folder = get_folder($query_id, $proximity_id, $filter_id);
		if(!file_exists($folder)) {
			mkdir($folder, 0777, true);
		}
		
		$sql = "SELECT proximity_name, within_type, within_count, words_left, words_right FROM proximities WHERE proximity_id=$proximity_id";
		$result = mysql_query($sql) or die("Error occurred. SH603.");
		$row = mysql_fetch_array($result);
		$within_type = $row['within_type'];
		$within_count = $row['within_count'];
		$look_left = $row['words_left'];
		$look_right = $row['words_right'];
		$within = ($within_type!=NULL); 
		$proximity_name = $row['proximity_name'];
		$restrictions = load_proximity($proximity_id);
		
		$sql = "SELECT filter_name FROM filters WHERE filter_id=$filter_id";
		$result = mysql_query($sql) or die("Error occurred. SH604.");
		$row = mysql_fetch_array($result);
		$filter_name = $row['filter_name'];
		$filter = load_filter($filter_id);
		
		$log_file = $folder."log.txt";
		$fh = fopen(($log_file), 'w') or die(("Cannot open file: ".$log_file));
		
		$temp_table_prefix = "Q".$query_id."P".$proximity_id."F".$filter_id;
		
		fwrite($fh, date("Y-m-d H:i:s"));	
		fwrite($fh, "\nCorpus: $corpus_display_name");
		fwrite($fh, "\nQuery: $query_name: $query_text");
		fwrite($fh, "\nProximity: $proximity_name: ".implode(", ",$restrictions));
		fwrite($fh, "\nFilter: $filter_name: ");
		foreach($filter as $field => $vals) {
			fwrite($fh, "$field ($vals) ");
		}
		
		$analysis_files = array();
		$analysis_files["log.txt"] = "Processing times log";

		fwrite($fh, "\n\nRunning CQP Query: ");
		$time = time();
		$cqp_success = run_cqp_query($corpus_cqp_name, $query_text, $folder, $text_id_att, $analysis_files);
		if($cqp_success) {
			fwrite($fh, date("i:s", time()-$time));
		}
		else {
			fwrite($fh, "failed");
			add_analysis_files($query_id, $proximity_id, $filter_id, $folder, $analysis_files);
			fclose($fh);
			return false;
		}
		$time = time();
		fwrite($fh, "\nFiltering texts: ");
		$text_ids = create_filtered_texts($c_id, $temp_table_prefix, $filter_id, $corpus_cqp_name);
		$text_filter = !empty($text_ids);
		fwrite($fh, date("i:s", time()-$time));
		$time = time();
		fwrite($fh, "\nCreating temp matches table: ");
		create_temp_matches_table($c_id, $temp_table_prefix, $folder, $text_id_att, $within_type, $within_count, $text_filter, $text_ids);
		fwrite($fh, date("i:s", time()-$time));
		$time = time();
		fwrite($fh, "\nCreating temp pntokens table: ");
		create_temp_pntokens_table($c_id, $temp_table_prefix, $filter_id, $corpus_cqp_name, $text_filter, $within_type);
		fwrite($fh, date("i:s", time()-$time));
		$time = time();
		fwrite($fh, "\nCreating temp pnmatches table: ");
		create_temp_pnmatches_table($temp_table_prefix, $within, $look_left, $look_right, TRUE);
		create_temp_pnmatches_table($temp_table_prefix, $within, $look_left, $look_right, FALSE);
		fwrite($fh, date("i:s", time()-$time));

		if($pntokens_table) {
			$time = time();
			fwrite($fh, "\nCreating all placenames tokens csv file: ");
			output_allpntokens_file($c_id, $text_filter, $temp_table_prefix, $corpus_cqp_name, $folder, $analysis_files);
			fwrite($fh, date("i:s", time()-$time));			
		}

		if($tokens_table) {
			$time = time();
			fwrite($fh, "\nCreating match tokens csv file: ");
			output_tokens_file($c_id, $temp_table_prefix, $corpus_cqp_name, $folder, $analysis_files, TRUE);
			output_tokens_file($c_id, $temp_table_prefix, $corpus_cqp_name, $folder, $analysis_files, FALSE);
			fwrite($fh, date("i:s", time()-$time));
		}
		if($types_table) {
			$time = time();
			fwrite($fh, "\nCreating match types csv files: ");
			output_types_file($temp_table_prefix, $corpus_cqp_name, $folder, $analysis_files);
			fwrite($fh, date("i:s", time()-$time));
		}
		
		foreach($grouped_by as $field) {
			$time = time();
			fwrite($fh, "\nCreating grouped by $field csv files: ");
			output_grouped_counts($temp_table_prefix, $corpus_cqp_name, $folder, $field, $text_filter, $analysis_files, TRUE);
			output_grouped_counts($temp_table_prefix, $corpus_cqp_name, $folder, $field, $text_filter, $analysis_files, FALSE);
			fwrite($fh, date("i:s", time()-$time));
		}
		
		
		add_analysis_files($query_id, $proximity_id, $filter_id, $folder, $analysis_files);
		fclose($fh);
		return true;
	}
	
	function get_folder($query_id, $proximity_id, $filter_id) {
		return "cqpfiles/$query_id.$proximity_id.$filter_id/";
	}
	
	function sanitize($string = '', $is_filename = FALSE) {
 		// Replace all weird characters with dashes
 		$string = preg_replace('/[^\w\-'. ($is_filename ? '~_\.' : ''). ']+/u', '-', $string);
		// Only allow one dash separator at a time (and make string lowercase)
	 	return mb_strtolower(preg_replace('/--+/u', '-', $string), 'UTF-8');
	}
	
	function create_zip($files = array(),$destination = '',$overwrite = false) {
	  //if the zip file already exists and overwrite is false, return false
	  if(file_exists($destination) && !$overwrite) { return false; }
	  //vars
	  $valid_files = array();
	  //if files were passed in...
	  if(is_array($files)) {
	    //cycle through each file
	    foreach($files as $file) {
	      //make sure the file exists
	      if(file_exists($file)) {
	        $valid_files[] = $file;
	      }
	    }
	  }
	  //if we have good files...
	  if(count($valid_files)) {
	    //create the archive
	    $zip = new ZipArchive();
	    if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
	      return false;
	    }
	    //add the files
	    foreach($valid_files as $file) {
			$new_filename = substr($file,strrpos($file,'/') + 1);
	      $zip->addFile($file,$new_filename);
	    }
	    //debug
	    //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;

	    //close the zip -- done!
	    $zip->close();

	    //check to make sure the file exists
	    return file_exists($destination);
	  }
	  else
	  {
	    return false;
	  }
	}
	
	function delete_if_exists($file) {
		if(file_exists($file)) {
			unlink($file);
		}
	}
	
	function rrmdir($dir) { 
		foreach(glob($dir . '/*') as $file) { 
	    	if(is_dir($file)) {
				rrmdir($file);
			} 
			else {
				unlink($file);
			}
		}
		rmdir($dir); 
	}
	
	function delete_analysis_files($query_id, $proximity_id, $filter_id) {
		//if any of ids are null, then use regex for any number.
		if($query_id==null) {
			$query_id = "[0-9]+";
		}
		if($proximity_id==null) {
			$proximity_id = "[0-9]+";
		}
		if($filter_id==null) {
			$filter_id = "[0-9]+";
		}
		$folder_pattern = "/$query_id\.$proximity_id\.$filter_id/";
		$base_folder = "cqpfiles";
		foreach(glob($base_folder . '/*') as $file) {
			if(is_dir($file) && preg_match($folder_pattern,$file)==1) {
				rrmdir($file);
			}
		}
	}
?>