<?php
	
	$fh_in = fopen("CLAWS7-tagset.txt", 'r');
	while(($line = fgets($fh_in)) !== false) {
		$a = explode("\t", $line);
		process_pos(trim($a[0]), trim($a[1]));
	}
	
	function process_pos($tag, $desc) {
		printf("\n<option value=\"%s(\d\d)?\" label=\"%s - %s\">%s - %s</option>", $tag, $tag, $desc, $tag, $desc);
	}
?>