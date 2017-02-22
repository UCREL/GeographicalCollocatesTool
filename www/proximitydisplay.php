<?php
	$p_id = $proximity_id;
	if(isset($_POST['p_id'])) {
		$p_id = $_POST['p_id'];
	}
	
	if(isset($_POST['c_id'])) {
		$c_id = $_POST['c_id'];
	}
		
	if($p_id<1) { //add new query
		require_once("includes_path.php");
		require_once($includes_path."db_connect.php");
		$sql = "SELECT structure FROM within_structures WHERE c_id=$c_id ORDER BY display_order ASC";
		$result = mysql_query($sql) or die("Error getting within structures. Please try again.");
		$within_types = array();
		while($row = mysql_fetch_array($result)) {
			$within_types[] = $row['structure'];
		}
?>
	<table class="data" id="proximitytable">
	<tr>
	<th class="data">Look left</th>
	<td class="data"><input type="text" name="lookleft" value="<?php echo $_POST['lookleft']; ?>" size="2" maxlength="3" /> words</td>
	<th class="data">Look right</th>
	<td class="data"><input type="text" name="lookright" value="<?php echo $_POST['lookright']; ?>" size="2" maxlength="3" /> words</td>
	<th class="data">Within</th>	
	<td class="data"><input type="text" name="within_count" value="<?php echo $_POST['within_count']; ?>" size="2" maxlength="3" /> 
	<select name="within_type">
<?php
	foreach($within_types as $wt) {
		printf("<option label=\"%s\" value=\"%s\">%s</option>", $wt, $wt, $wt);
	}
?>
	</select>
	</td>
	</tr>
	</table>
	
	<p>(Leave any field blank to have no restriction)</p>
	
	<p>Name: <input type="text" name="proximityname" value="<?php echo $proximity_name; ?>" size="32" maxlength="32" />
	<input type="submit" name="saveproximity" value="Add" />
	</p>
	
<?php
	}
	else {
		require_once("includes_path.php");
		require_once($includes_path."db_methods.php");
		$restrictions = load_proximity($p_id);
		echo "\n<p class=\"code\">Restrictions: ".implode(", ",$restrictions)."</p>";
?>
		<p style="text-align: right"><input type="submit" name="deleteproximity" value="Delete proximity" /></p>
<?php
	}
?>