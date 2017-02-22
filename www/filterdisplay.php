<?php
	$f_id = $filter_id;
	if(isset($_POST['f_id'])) {
		$f_id = $_POST['f_id'];
	}
	
	if(isset($_POST['c_id'])) {
		$c_id = $_POST['c_id'];
	}
	
	if($f_id<0) { //add new filter
		require_once("includes_path.php");	
		require_once($includes_path."db_connect.php");

?>	
	<p>Name: <input type="text" name="filtername" value="<?php echo $filter_name; ?>" size="32" maxlength="32" />
	<input type="submit" name="savefilter" value="Add" />
	</p>
	<table width="100%">
	<tr>
<?php
	$sql = "SELECT cqp_name, source_table, table_column, search_type FROM filter_atts, corpora WHERE filter_atts.c_id=$c_id AND corpora.c_id=$c_id ORDER BY display_order";
	$result = mysql_query($sql) or die("Error SH701");
	$count = 0;
	while($row = mysql_fetch_array($result)) {
		$count++;
		if($count%4==1) { //i.e. 4 fields and then drop to new row.
?>
		</tr>
		<tr>
<?php		
		}
		$cqpname = $row["cqp_name"];
		$field = $row["table_column"];
		$source_table = $row["source_table"];
		$table_column = $row["table_column"];
		$search_type = $row["search_type"];
		
		$pntokens_table = $cqpname."_pntokens";
		$pntypes_table = $cqpname."_pntypes";
		$texts_table = $cqpname."_texts";
?>
		<td class="filter">
		<fieldset>
		<legend><?php echo $field; ?></legend>

<?php		
		if($search_type=="group") {
?>
			<input type="checkbox" style="float: right;" class="checkall" checked="checked" />
<?php
			if($source_table=="texts") {
				$count_sql = "SELECT $texts_table.$field, COUNT(*) AS freq FROM $texts_table, $pntokens_table WHERE $pntokens_table.text_id=$texts_table.text_id GROUP BY $texts_table.$field";
			}
			else if($source_table="pntypes") {
				$count_sql = "SELECT $pntypes_table.$field, COUNT(*) AS freq FROM $pntypes_table, $pntokens_table WHERE $pntokens_table.type_id=$pntypes_table.type_id GROUP BY $pntypes_table.$field";
			}
			else {
				$count_sql = "SELECT $pntokens_table.$field, COUNT(*) AS freq FROM $pntokens_table GROUP BY $pntokens_table.$field";
			}

			$count_result = mysql_query($count_sql) or die("Error SH702<br />".$count_sql);
			printf("<input type=\"hidden\" name=\"%s-count\" value=\"%d\" />", $field, mysql_num_rows($count_result));
			while($count_row = mysql_fetch_array($count_result)) {
				$val = $count_row[$field];
				$freq = $count_row['freq'];
				printf("<br /><input type=\"checkbox\" name=\"%s-filter[]\" value=\"%s\" checked=\"checked\" /> %s (%d)", $field, $val, $val, $freq);
			}
		}
		
		else {
			$range_sql = "SELECT MIN($field) AS minimum, MAX($field) AS maximum FROM ".$cqpname."_".$source_table;
			$range_result = mysql_query($range_sql) or die("Error SH702a<br />".$range_sql);
			$range_row = mysql_fetch_array($range_result);
			printf("<p><input type=\"text\" name=\"%s-min\" value=\"\" size=\"8\" maxlength=\"32\" /><input type=\"hidden\" name=\"%s-minmin\" value=\"%s\" /> (%s) &#x2013;</br /><input type=\"text\" name=\"%s-max\" value=\"\" size=\"8\" maxlength=\"32\" /><input type=\"hidden\" name=\"%s-maxmax\" value=\"%s\" /> (%s)</p>", $field, $field, $range_row['minimum'], $range_row['minimum'], $field, $field, $range_row['maximum'], $range_row['maximum']);
		}
		
		echo "\n</fieldset>\n</td>";
	}
?>
	</tr>
	</table>
	</div>	
<?php	
	}


	else if($f_id>0) {
		require_once("includes_path.php");	
		require_once($includes_path."db_methods.php");
		$filter = load_filter($f_id);
		foreach($filter as $field => $vals) {
?>
			<p><b><?php echo $field ?>: </b><i><?php echo $vals; ?></i></p>
<?php
		}
?>
	<p style="text-align: right"><input type="submit" name="deletefilter" value="Delete Filter" /></p>
<?php
	}
?>