<?php
/*
	a00: ask price
	b00: bid price
	g00: day’s range low
	h00: day’s range high
	j10: market cap
	v00: volume
	a50: ask size
	b60: bid size
	b30: ecn bid
	o50: ecn bid size
	z03: ecn ext hr bid
	z04: ecn ext hr bid size
	b20: ecn ask
	o40: ecn ask size
	z05: ecn ext hr ask
	z07: ecn ext hr ask size
	h01: ecn day’s high
	g01: ecn day’s low
	h02: ecn ext hr day’s high
	g11: ecn ext hr day’s low
	t10: last trade time, will be in unix epoch format
	t50: ecnQuote/last/time
	t51: ecn ext hour time
	t53: RTQuote/last/time
	t54: RTExthourQuote/last/time
	l10: last trade
	l90: ecnQuote/last/value
	l91: ecn ext hour price
	l84: RTQuote/last/value
	l86: RTExthourQuote/last/value
	c10: quote/change/absolute
	c81: ecnQuote/afterHourChange/absolute
	c60: ecnQuote/change/absolute
	z02: ecn ext hour change
	z08: ecn ext hour change
	c63: RTQuote/change/absolute
	c85: RTExthourQuote/afterHourChange/absolute
	c64: RTExthourQuote/change/absolute
	p20: quote/change/percent
	c82: ecnQuote/afterHourChange/percent
	p40: ecnQuote/change/percent
	p41: ecn ext hour percent change
	z09: ecn ext hour percent change
	p43: RTQuote/change/percent
	c86: RTExtHourQuote/afterHourChange/percent
	p44: RTExtHourQuote/change/percent
*/


function GetPrices($input,$output) {
	
	// Reset Price direction
	$mysql="UPDATE ".$output." as o ".
		"LEFT JOIN ".$input." AS i ON i.id = o.posid ".
 		"SET ". 
 			"pricedirection = '0' ".
			"WHERE status IN ('ACT','PRE') and type<>'TRF'";
	if (!mysql_query($mysql)) {
		MySqlError($mysql);
	} 

	// Get all open STOCK positions
	$mysql="SELECT i.id, i.ticker, o.posid, o.pricedirection FROM ".$output." as o ".
			"LEFT JOIN ".$input." AS i ON i.id = o.posid ".
			"WHERE (pricedirection = '0')";
	$res_positions = mysql_query($mysql);
	$myrows=mysql_numrows($res_positions);
	$sub_symbols = "";
	While ($position=mysql_fetch_array($res_positions)) {
		$sub_symbols=$sub_symbols.$position['ticker'].',';
	}

	$response=GetYahooQuotes($sub_symbols);

	$marketopen=strpos($response["market"]["m_open_close"],"Markets close in");
	
	reset($response);
	$positionsupdated = 0;
	while (list($symbol, $quote) = each($response)) {
	//	$date = date("Y-m-d");// current date
	//	$date = date("Y-m-d H:i:s",strtotime(date("Y-m-d", strtotime($date)) . " ".$response['udt']));			// Bug around midnight 00:30 = Tomorrow 

   		if ($symbol=="market") {
			$mysql="UPDATE `trd_markets` SET " .
				"`m_open_close` = '".$quote["m_open_close"]."', " .
				"`m_local_time` = '".date("Y-m-d H:i:s",strtotime($quote["m_local_time"]))."' WHERE `trd_markets`.`id` =1";
   			if (!mysql_query($mysql)) {
				MySqlError($mysql);
			} 
   		} else {

			$mysql="SELECT * FROM ".$input.
					" WHERE ticker='".$symbol."'";
			$res_positions = mysql_query($mysql);
			$position=mysql_fetch_array($res_positions);
	
// Prices good for open and after
// Extend for closed and pre
			if ($marketopen) {
				if (isset($quote["t51"])) {	// RT
					$date= date("Y-m-d H:i:s");
				}
				if (isset($quote["l84"])) {
					$last=$quote["l84"];
				} else {
					$last=$quote["l10"];
				}
			} else {
				if (isset($quote["l86"]) AND isset($quote["c85"])) { //doesnt work , gettn l91 during market open, need to interpret close
					$afterHours=TRUE;
					$date= date("Y-m-d H:i:s",$quote["t51"]);
					$last=floatval($quote["l86"]);
				}  elseif (isset($quote["l84"])) {
					$date= date("Y-m-d H:i:s");
					$last=floatval($quote["l84"]);
				}  elseif (isset($quote["l91"])) {
					$date= date("Y-m-d H:i:s");
					$last=floatval($quote["l91"]);
				}  else {
					$date= date("Y-m-d H:i:s");
					$last=floatval($quote["l10"]);
				}
			}
			if (isset($quote["c63"])) {
				$change=floatval($quote["c63"]);
			} else {
				$change=0;
			}
			if (isset ($quote["v53"])) $volume=$quote["v53"];
			$percchange=floatval($quote["p43"]);
			if (isset($quote["l84"])) {
				$previousclose=$quote["l84"]-$change;
			} else {
				$previousclose=$quote["l10"]-$change;
			}
			//					" `pricedirection` = '".$pricedirection."'," .

			$pricedirection=SetPriceDirection($change,$position['buy_sell'],!isset($quote["t51"]));
			
			$mysql="UPDATE ".$output." as o".
					" LEFT JOIN ".$input." as i ON o.posid = i.id ".
					"SET ".
					" `prev_close` = '".$previousclose."'," . 
					" `last` = '".$last."'," .
					" `pricedirection` = '".$pricedirection."'," .
					" `today_difference` = '".$change."'," .
					" `today_difference_perc` = '".$percchange ."'".
					" WHERE ticker='".$symbol."'";
			if (!mysql_query($mysql)) {
				MySqlError($mysql);
			} else {	
				$positionsupdated++;
			}
   		}
    }
	
	return $positionsupdated;

}


function GetYahooQuotes($sub_symbols) {

	grab_yahoo_stock_index_streamerapi($sub_symbols);

	$str = file_get_contents('tmp.txt');
	//$STR = file_get_contents('Multi_ok.txt');
	//$STR = file_get_contents('Multi_ok.txt');
	//$STR = file_get_contents('rt_all_ok.txt');

	$matchArr = NULL;

//{parent.yfs_mktmcb({"m_open_close":"US Markets are closed","m_local_time":"Thu, Jan 12, 2012, 9:55pm EST"});}catch(e){}</script><script>
//{parent.yfs_u1f({"OPTR":{l86:"12.84",l91:"12.84",t51:"1326403507"}});}catch(e){}</script><script>
//{parent.yfs_u1f({"^DJI":{l10:"12471.02",p20:"+0.17",a00:"0.00",a50:"0",b00:"0.00",b60:"0",l84:"12471.02",c63:"+21.57",p43:"+0.17",v53:"128,233,848",g53:"12385.08",h53:"12483.62"}});}catch(e){}</script><script>
	
	
	//preg_match('/parent.yfs_u1f\((.*)\);/', $STR, $matchArr);
	preg_match_all('/parent.yfs_u1f\((.*?)\);|parent.yfs_mktmcb\((.*?)\);/', $str, $matchArr);
	//echo '<pre>';  print_r($matchArr); echo '</pre>';


	$comb=array();
	foreach ($matchArr[1] as $arr) {
		if (!empty($arr)) {
			$arr = json_decode_v2($arr, TRUE);
			$key=key($arr);
			if (!empty($comb[$key])) {
		  		$comb[$key]=array_replace($comb[$key],$arr[$key]);
		  	} else {
				$comb[$key]=$arr[$key];
		  	}
	  	}
	}
	if (!empty($matchArr[2])) {
		$arr= json_decode('{"market":'.$matchArr[2][0].'}', TRUE);
	  	$key=key($arr);
		$comb[$key]=$arr[$key];
	}

//	echo '<pre>';	  print_r($comb);	  echo '</pre>';
	
	return $comb;
}

	
function grab_yahoo_stock_index_streamerapi($symbol) {
	
	
  $URL = 'http://streamerapi.finance.yahoo.com/streamer/1.0?s=' . $symbol;
  
  $URL_field = '&k=a00,a50,b00,b60,c63,c85,c86,g53,h53,j10,l10,l84,l86,l91,p20,p43,t10,t50,t51,t53,t54,v53';
  
  $URL_postfix = '&callback=parent.yfs_u1f&mktmcb=parent.yfs_mktmcb&gencallback=parent.yfs_gencb&mu=1';

  $URL = $URL . $URL_field . $URL_postfix;

  # When using CURLOPT_FILE, pass it the file handle that is open 
  # for write only (eg fopen('blahblah', 'w')). If you also open 
  # the file for reading (eg fopen('blahblah', 'w+')), curl will 
  # fail with error 23. 
  $fp = fopen('tmp.txt', 'w');

  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, $URL);

  # When you are using CURLOPT_FILE to download directly into a file 
  # you must close the file handler after the curl_close() otherwise 
  # the file will be incomplete and you will not be able to use it 
  # until the end of the execution of the php process.
  curl_setopt($ch, CURLOPT_FILE, $fp);

  curl_setopt($ch, CURLOPT_TIMEOUT, 3);

  //curl_setopt($ch, CURLOPT_VERBOSE, 1);

  curl_exec($ch);

  curl_close($ch);

  # at this point your file is not complete and corrupted.
  fclose($fp);

  return;
}

function json_decode_v2($json, $assoc = FALSE){

  	$json = str_replace(array("\n","\r"), "", $json);

  	//$str = preg_replace("/([a-zA-Z0-9_]+?):/" , "\"$1\":", $str); // fix variable names
  	$json = preg_replace('/([{,])(\s*)([^"]+?)\s*:/', '$1"$3":', $json);
  	
  	return json_decode($json, $assoc);
}

function SetPriceDirection($difference,$buy,  $delayed) {

	if ($difference==0)  {
		$pricedirection=1;
	} elseif ($difference>0) {
		$pricedirection=2;
	} elseif ($difference<0) {
		$pricedirection=3;
	}
	if ($buy=="SELL" and $pricedirection>0) {
		$pricedirection=$pricedirection+3;
	}
	if ($delayed and $pricedirection>0) {
		$pricedirection=$pricedirection+6;
	}
	return $pricedirection;
}
?>
