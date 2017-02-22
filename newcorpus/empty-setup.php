<?php
	$cqp_name = ""; // Must be the same as the CQPweb short handle (capitals).
	$display_name = ""; // What will appear on the web front end.
	$search_types = array("word"=>"Word", "class"=>"Oxford Simplified Tag", "pos"=>"Part-of-speech tag", "semtag"=>"Semantic Tag", "lemma"=>"Lemma", "hw"=>"Head Word"); //cqpweb positional attributes. Key is cqpweb ref, Value is description for dropdown. Usually just leave as is.
	$structures = array("s", "page"); //These are the structures that can be used for the "within ...", normally s (sentence) is present, so can do within x sentences.
	$pn_element = "enamex"; //the xml element used to denote placenames

	//For the data-type in the below, please use CAPITAL letters (e.g. VARCHAR(32)).
	$pn_id_att = array("enamex_gazref","VARCHAR(32)"); //the xml attribute that is a unique reference to placename type. 2nd value in array for each data type for mysql meta table
	$pn_type_atts = array(array("enamex_long","DOUBLE"), array("enamex_lat","DOUBLE"), array("enamex_type","VARCHAR(16)"), array("enamex_name","VARCHAR(64)")); //xml attributes to placename type, incl. longitude and latitude. 2nd value in array for each data type for mysql meta table
	$pn_token_atts = array(array("enamex_sw","VARCHAR(16)"), array("enamex_conf","DOUBLE"), array("page_seq","SMALLINT UNSIGNED")); //xml attributes related to placename in situ, related to position in text. 2nd value in array for each data type for mysql meta table
	$text_element = "text"; //the xml element to denote a text, usually "text".
	$text_id_att = "text_id"; // the xml attribute for text id, usually "text_id". This values for this field must be integers (fitting into MEDIUMINT UNSIGNED - 0-16777215)
	$text_meta_file = "path/to/metadata.txt"; //tab-delimited metadata file, first field([0]) should be text_id.
	$text_meta_fields = array(array("Year", 1, 	"VARCHAR(9) NOT NULL"), array("Decade", 2, "VARCHAR(9) NOT NULL"), array("CensusDecade", 3, "VARCHAR(9) NOT NULL"), array("TextType", 4, "ENUM('Census', 'Registrar General') NOT NULL"), array("Geography", 5, "VARCHAR(24) NOT NULL"), array("GeoCode", 6, "VARCHAR(4) NOT NULL")); //text metadata fields to extract from tab-delimited file above. Name, column number, db type (examples from HISTPOP)

	$filter_atts = array(array("texts", "TextType", "group"), array("texts", "CensusDecade", "group"), array("texts", "Decade", "group"), array("pntypes", "enamex_type", "group"), array("texts", "Geography", "group"), array("texts", "GeoCode", "group"), array("pntypes", "enamex_long", "range"), array("pntypes", "enamex_lat", "range"), array("pntokens", "enamex_conf", "range"), array("texts", "Year", "group"), array("texts", "text_id", "group")); //to include in filter (table (texts|pntypes|pntokens),column,type(group|range).
	$group_atts = array("TextType", "CensusDecade", "Decade", "Geography", "GeoCode", "Year", "text_id"); //to include as harmonisation groups: should be column in texts table ($text_meta_fields)).
?>