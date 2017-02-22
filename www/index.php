<?php
	require_once("includes_path.php");
	require_once($includes_path."head.php");
	
	//sort out log in
	if(!$_SESSION['logged']) {
		echo "<div><p>Please log in or register.</p></div>";
		require_once($includes_path."foot.php");
		exit();
	}
	
	if($no_corpora) {
		echo "<div><p>No corpora are currently available under your username.</p></div>";
		require_once($includes_path."foot.php");
		exit();
	}
	if(!isset($c_id)) {
		echo "<div><p>Please select a corpus.</p><ul>";
		foreach($corpora as $c => $name) {
			printf("<li><a href=\"index.php?c_id=%d\">%s</a></li>", $c, $name);
		}
		echo "</ul></div>";
		require_once($includes_path."foot.php");
		exit();
	}

	//stuff for query box		
	$query_name = $_POST['queryname'];
	
	if(isset($_POST['deletequery'])) {
		$query_id = $_POST['queryselect'];
		if($query_id>0) {
			require_once($includes_path."db_methods.php");
			delete_query($query_id);
			require_once($includes_path."cqp_methods.php");
			delete_analysis_files($query_id, null, null);
			$query_id=-1;
		}
	}
	else if(isset($_POST['savequery'])) {
		$cqp_query = $_POST['cqp_query'];
		require_once($includes_path."db_methods.php");
		$query_id = save_query($c_id, $_SESSION['uid'], $query_name, $cqp_query);
	}
	else {
		$query_id = $_POST['queryselect'];
	}
	
	if(isset($_POST['querybuild'])) {
		require_once($includes_path."cqp_methods.php");
		$casesensitive = array();
		if(isset($_POST['casesensitive'])) {
			$casesensitive = $_POST['casesensitive'];
		}
		$cs = array();
		$terms = count($_POST['p_att']);
		for($i=0;$i<$terms;$i++) {
			$cs[$i] = (in_array(($i+1), $casesensitive) ? 1 : 0);
		}	
		$cqp_query = build_query($_POST['p_att'], $_POST['searchterm'], $cs);
		$query_id = 0;
	}
	else if(isset($_POST['cqp_query'])) {
		$cqp_query = stripslashes($_POST['cqp_query']);
	}
	else {
		$cqp_query = "";
	}	
	
	//stuff for proximity box		
	$proximity_name = $_POST['proximityname'];
	
	if(isset($_POST['deleteproximity'])) {
		$proximity_id = $_POST['proximityselect'];
		if($proximity_id>0) {
			require_once($includes_path."db_methods.php");
			delete_proximity($proximity_id);
			require_once($includes_path."cqp_methods.php");
			delete_analysis_files(null, $proximity_id, null);
			$proximity_id=-1;
		}
	}
	else if(isset($_POST['saveproximity'])) {
		require_once($includes_path."db_methods.php");
		$proximity_id = save_proximity($c_id, $_SESSION['uid'], $proximity_name, $_POST['lookleft'], $_POST['lookright'], $_POST['within_count'], $_POST['within_type']);
	}
	else {
		$proximity_id = $_POST['proximityselect'];
	}
	
	
	if(isset($_POST['deletefilter'])) {
		$filter_id = $_POST['filterselect'];
		if($filter_id>0) {
			require_once($includes_path."cqp_methods.php");
			delete_analysis_files(null, null, $filter_id);
			require_once($includes_path."db_methods.php");
			delete_filter($filter_id);
			$filter_id=0;
		}
	}	
	else if(isset($_POST['savefilter'])) {
		$filter_name = $_POST['filtername'];
		$sql = "SELECT table_column FROM filter_atts WHERE c_id=$c_id AND search_type=\"group\"";
		$result = mysql_query($sql) or die("Error SH701");
		$filter_group_selections = array();
		while($row = mysql_fetch_array($result)) {
			$field = $row['table_column'];
			if(isset($_POST[$field."-filter"])) {
				if(count($_POST[$field."-filter"])<intval($_POST[$field."-count"])) {
					$filter_group_selections[$field] = $_POST[$field."-filter"];
				}
			}
		}
		
		$sql = "SELECT table_column FROM filter_atts WHERE c_id=$c_id AND search_type=\"range\"";
		$result = mysql_query($sql) or die("Error SH701a");
		$filter_ranges = array();
						
		while($row = mysql_fetch_array($result)) {
			$field = $row['table_column'];
			$min = $_POST[$field."-min"];
			$max = $_POST[$field."-max"];
			$minmin = $_POST[$field."-minmin"];
			$maxmax = $_POST[$field."-maxmax"];
			if($min=="" && $max=="") {
				//ignore, not part of filter
			}
			else {
				if($min=="") {
					$min = $minmin; //use field's min
				}
				else if($max=="") {
					$max = $maxmax; //use field's max
				}
				else if(floatval($min)<floatval($minmin)) {
					$min = $minmin; //doesn't make sense for min to be less than field's min
				}
				else if(floatval($max)>floatval($maxmax)) {
					$max = $maxmax; //doesn't make sense for max to be more than field's max
				}
				$filter_ranges[$field] = array("min"=>$min, "max"=>$max); 
			}
		}
		
		require_once($includes_path."db_methods.php");
		$filter_id = save_filter($c_id, $_SESSION['uid'], $filter_name, $filter_group_selections, $filter_ranges);
	}
	else {
		$filter_id = $_POST['filterselect'];
	}
	
	
	//Run actual analysis if clicked...
	if(isset($_POST['runanalysis'])) {
		if($query_id>0 && $proximity_id>0 && $filter_id > -1) { //must have selected query, proximity and filter.
			require_once($includes_path."cqp_methods.php");
			run_analysis($c_id, $query_id, $proximity_id, $filter_id, isset($_POST['pntokens_table']), isset($_POST['tokens_table']), isset($_POST['types_table']), $_POST['grouped_by']);
		}
		else {
//			printf("<p>$query_id $proximity_id $filter_id</p>");
		}
	}
	
	
	//delete all files
	if(isset($_POST['deleteanalysis'])) {
		require_once($includes_path."db_methods.php");
		delete_analysis_files_from_db($query_id,$proximity_id,$filter_id);
		require_once($includes_path."cqp_methods.php");
		delete_analysis_files($query_id,$proximity_id,$filter_id);
	}
	
	//now the actual analysis form
?>	
	<div class="form" style="width: 95%;">
	<form id="analysis" action="index.php" method="post">
	<input id="cid" type="hidden" name="c_id" value="<?php echo $c_id; ?>" />
	<h4>Analysis</h4>
	<div>
	<fieldset>
	<legend>Query: 
	<select name="queryselect" id="queryselect">
	<option label="Add new query" value="-1">Add new query</option>
<?php
	$sql = sprintf("SELECT query_id, query_name, ts FROM queries WHERE c_id=%d AND user_id=%d ORDER BY ts ASC", $c_id, $_SESSION['uid']);
	$result = mysql_query($sql) or die("Error SH850");
	while($row = mysql_fetch_array($result)) {
		printf("<option label=\"%s (%s)\" value=\"%d\"%s>%s (%s)</option>", $row['query_name'], $row['ts'], $row['query_id'], ($query_id==$row['query_id'] ? " selected=\"selected\"" : ""), $row['query_name'], $row['ts']);
	}
?>
	</select>
	</legend>
	<div id="querydisplaydiv">
<?php
	include("querydisplay.php");
?>	
	</div>
	</fieldset>
	
	<fieldset>
	<legend>Proximity: 
	<select name="proximityselect" id="proximityselect">
	<option label="Add new proximity range" value="-1">Add new proximity range</option>
<?php
	$sql = sprintf("SELECT proximity_id, proximity_name, ts FROM proximities WHERE c_id=%d AND user_id=%d ORDER BY ts ASC", $c_id, $_SESSION['uid']);
	$result = mysql_query($sql) or die("Error SH850p");
	while($row = mysql_fetch_array($result)) {
		printf("<option label=\"%s (%s)\" value=\"%d\"%s>%s (%s)</option>", $row['proximity_name'], $row['ts'], $row['proximity_id'], ($proximity_id==$row['proximity_id'] ? " selected=\"selected\"" : ""), $row['proximity_name'], $row['ts']);
	}
?>
	</select>
	</legend>
	<div id="proximitydisplaydiv">
<?php
	include("proximitydisplay.php");
?>	
	</div>
	</fieldset>
	
	<fieldset>
	<legend>Filter: 
	<select name="filterselect" id="filterselect">
	<option label="No filter" value="0">No Filter</option>
	<option label="Add new filter" value="-1">Add new filter</option>
<?php
	$sql = sprintf("SELECT filter_id, filter_name, ts FROM filters WHERE c_id=%d AND user_id=%d ORDER BY ts ASC", $c_id, $_SESSION['uid']);
	$result = mysql_query($sql) or die("Error SH851");
	while($row = mysql_fetch_array($result)) {
		printf("<option label=\"%s (%s)\" value=\"%d\"%s>%s (%s)</option>", $row['filter_name'], $row['ts'], $row['filter_id'], ($filter_id==$row['filter_id'] ? " selected=\"selected\"" : ""), $row['filter_name'], $row['ts']);
	}
?>	
	</select></legend>
	<div id="filterdisplaydiv">
<?php
	include("filterdisplay.php");
?>
	</div>
	</fieldset>
	
	<fieldset>
	<legend>Tables</legend>
	<div>
	<p><b>Overall:</b><br />
	<input type="hidden" name="cbsubmitted" value="1" />
	&nbsp;&nbsp;<input type="checkbox" name="pntokens_table"<?php echo (isset($_POST['cbsubmitted']) ? (isset($_POST['pntokens_table']) ? " checked=\"checked\"" : "") : "checked=\"checked\""); ?> value="1" /> All Placename Tokens
	&nbsp;&nbsp;<input type="checkbox" name="tokens_table"<?php echo (isset($_POST['cbsubmitted']) ? (isset($_POST['tokens_table']) ? " checked=\"checked\"" : "") : "checked=\"checked\""); ?> value="1" /> Match Tokens
	&nbsp;&nbsp;<input type="checkbox" name="types_table"<?php echo (isset($_POST['cbsubmitted']) ? (isset($_POST['types_table']) ? " checked=\"checked\"" : "") : "checked=\"checked\""); ?> value="1" /> Match Types
	</p>
	<p><b>Counts for:</b><br />

<?php
	$sql = "SELECT text_table_column FROM group_atts WHERE c_id=$c_id ORDER BY display_order";
	$result = mysql_query($sql) or die("Error occurred collecting group atts");
	while($row = mysql_fetch_array($result)) {
		$column = $row['text_table_column'];
		printf("\n&nbsp;&nbsp;<input type=\"checkbox\" name=\"grouped_by[]\" value=\"%s\" %s /> %s", $column, (isset($_POST['cbsubmitted']) ? (in_array(($column), $_POST['grouped_by']) ? " checked=\"checked\"" : "") : "checked=\"checked\""), $column);
	}
?>
	</p>
	</div>
	</fieldset>
	
	
	<p style="text-align: right;"><input type="hidden" name="c_id" value="<?php echo $c_id; ?>" />
	<input type="submit" name="runanalysis" value="Run analysis" />
	</p>
	</div>
	</div>
		
	<div class="form" style="width: 95%;">
	<h4>Output files</h4>
	<div id="fileslistdiv">
<?php
	include("fileslist.php");
?>
	</div>
	<div><p style="text-align: right;"><input type="submit" name="deleteanalysis" value="Delete files" /></p></div>
	</div>
	
	</form>

<?php	
	require_once($includes_path."foot.php");
?>