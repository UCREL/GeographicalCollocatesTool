<?php
	$q_id = $query_id;
	$p_id = $proximity_id;
	$f_id = $filter_id;

	if(isset($_POST['q_id'])) {
		$q_id = $_POST['q_id'];
	}
	if(isset($_POST['p_id'])) {
		$p_id = $_POST['p_id'];
	}
	if(isset($_POST['f_id'])) {
		$f_id = $_POST['f_id'];
	}
		
	if($q_id>0 && $p_id>0 && $f_id>-1) {
		
		require_once("includes_path.php");
		require_once($includes_path."db_connect.php");
		require_once($includes_path."cqp_methods.php");
		$sql = "SELECT filename, description, size, ts FROM analysis_files WHERE query_id=$q_id AND proximity_id=$p_id AND filter_id=$f_id ORDER BY ts ASC, description ASC";
		$result = mysql_query($sql) or die("Error SH981");
?>
		<table class="filelist" width="95%">
		<tr>
		<th class="filelist">File</th>
		<th class="filelist">Created</th>
		<th class="filelist" style="text-align: right;">Size</th>
		<th class="filelist">&nbsp;</th>
		</tr>
<?php
		while($row=mysql_fetch_array($result)) {
			$folder = get_folder($q_id, $p_id, $f_id);
?>
		<tr>
		<td class="filelist"><a href="<?php echo $folder.$row['filename']; ?>" target="_blank"><?php echo $row['description']; ?></a></td>
		<td class="filelist"><?php echo $row['ts']; ?></td>	
		<td class="filelist" style="text-align: right;"><?php echo $row['size']; ?></td>
		<td class="filelist"><input type="hidden" name="delfilename[]" value="<?php echo $row['filename'] ?>" /><img class="deletefile" src="delete.png" width="15px" height="15px" alt="Delete file" title="Delete file" /></td>
		</tr>
<?php
		}
?>
		</table>
<?php		
		if(mysql_num_rows($result)>0) {
			printf("<p style=\"text-align: center;\"><a href=\"allfiles.php?q=%d&amp;p=%d&amp;f=%d\"><b>Download archive of all files</b></a></p>", $q_id, $p_id, $f_id);
		}
		else {
			echo "<p>&nbsp;</p>";
		}		
	}
	else {
		echo "<p>&nbsp;</p>";
	}	
?>