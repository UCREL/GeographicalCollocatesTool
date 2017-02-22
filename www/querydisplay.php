<?php
	$q_id = $query_id;
	if(isset($_POST['q_id'])) {
		$q_id = $_POST['q_id'];
	}
	
	if(isset($_POST['c_id'])) {
		$c_id = $_POST['c_id'];
	}
		
	if($q_id<1) { //add new query
		require_once("includes_path.php");
		require_once($includes_path."db_connect.php");
		$sql = "SELECT attribute, description FROM search_types WHERE c_id=$c_id ORDER BY display_order ASC";
		$result = mysql_query($sql) or die("Error getting positional attributes.<br />".mysql_error()."</br />".$sql);
?>
	<table class="data" id="buildtable">
	<tr>
	<th class="data">Level</th>
	<th class="data">Search term</th>
	<th class="data">CS</th>	
	</tr>
	<tr>
	<td class="data">
	<select name="p_att[]" id="searchtype">
<?php
		while($row = mysql_fetch_array($result)) {
			printf("<option label=\"%s\" value=\"%s\">%s</option>", $row['description'], $row['attribute'], $row['description']);
		}
?>
	</select>
	</td>
	<td class="data" id="searchcell"><?php include("default-search.php"); ?></td>
	<td class="data"><input type="checkbox" name="casesensitive[]" value="1" /></td>
	<td><img class="plusrow" alt="Add row" title="Add row" src="plus.png" height="20px" width="20px" /><img class="minusrow" alt="Remove row" title="Add row" src="minus.png" height="20px" width="20px" /></td>
	</tr>
	</table>
	
	<p><input type="submit" name="querybuild" value="Build Query" />
	</p>

	<p>
	<textarea name="cqp_query" cols="100" rows="2"><?php echo $cqp_query; ?></textarea>
	</p>
	
	<p>Name: <input type="text" name="queryname" value="<?php echo $query_name; ?>" size="32" maxlength="32" />
	<input type="submit" name="savequery" value="Add" />
	</p>
	
<?php
	}
	else {
		require_once("includes_path.php");
		require_once($includes_path."db_methods.php");
		$query = load_query($q_id);
		echo "\n<p class=\"code\">".htmlentities($query)."</p>";
?>
		<p style="text-align: right"><input type="submit" name="deletequery" value="Delete query" /></p>
<?php
	}
?>