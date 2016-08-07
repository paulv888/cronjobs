<?php  
require("../myclasses/OAuth.php");  
require_once '../includes.php';

define( 'DEBUG_WBUG', TRUE );

	
	$row = FetchRow("SELECT * FROM ha_mi_oauth20 where id ='YAHOO'");
	if (DEBUG_WBUG) echo '<pre>';
	
	$credentials['method'] = $row['method'];
	$credentials['client_id'] = $row['clientID'];
	$credentials['secret'] = $row['secret'];
	print_r($credentials);
	
	
    $url = "https://query.yahooapis.com/v1/yql";  
    $args = array();  
    $args["q"] = 'select * from weather.forecast where woeid in (12773052) and u="c"';  
	$args["diagnostics"] = "true";
	// $args["debug"] = "true";
    $args["format"] = "json";  
	print_r($args);

	$get = RestClient::get($url,$args,$credentials);
	$rsp= $get->getResponse();
	echo $get->getResponseCode().'\n';
	
	//print_r($rsp);
    $result = json_decode($rsp);  
    print_r($result);  
	$result = $result->{'query'}->{'results'}->{'channel'};
    print_r($result);  

	echo $result->{'astronomy'}->{'sunrise'}."\n";
	echo $result->{'astronomy'}->{'sunset'}."\n";
	$tsr = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunrise'})));
	$tss = date("H:i", strtotime(preg_replace("/:(\d) /",":0$1 ",$result->{'astronomy'}->{'sunset'})));
	$tpb = time();
	echo $tsr."\n";
	echo $tss."\n";
	if ($tpb>$tsr && $tpb<$tss) { $daynight = 'd'; } else { $daynight = 'n'; }
	echo $daynight."\n";
?>  