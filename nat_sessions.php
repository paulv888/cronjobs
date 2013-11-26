<?php
require 'connect-db.php';
require_once 'logins.php' ;
include_once 'includes/shared_db.php';
include_once 'includes/shared_file.php';
include_once 'includes/shared_ha.php';
include_once 'includes/shared_gen.php';
include_once 'myclasses/RestClient.class.php';
define("MY_DEVICE_ID", 108);

echo ImportSessions()." Nat Sessions Read <br/>\r\n";
echo MoveHistory()." Sessions moved to History <br/>\r\n";
//echo MoveTransactionsPositions()." Positions Opened or Closed  <br/>\r\n";
echo UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
//echo UpdateNames()." New Ip Names added";
//exec('nohup /usr/bin/my-command > /dev/null 2>&1 &'); 


function UpdateNames() {
// Read all unknown
// To array, remove duplicates
// Submit parrallel process for each http://blog.motane.lu/2009/01/02/multithreading-in-php/
//$hostname = gethostbyaddr($_SERVER['SERVER_ADDR']);
}

function MoveHistory() {
    $mysql="INSERT INTO `net_sessions_history` SELECT * FROM `net_sessions` WHERE active=0;";
	$result=mysql_query($mysql);
	if (!$result) mySqlError($mysql);	
	$num_rows = mysql_affected_rows();
    $mysql="DELETE FROM `net_sessions` WHERE active=0;";
	if (!mysql_query($mysql)) mySqlError($mysql);
	return $num_rows;
}

function GetSessions() {

// post($url,$params=null,$user=null,$pwd=null,$contentType="multipart/form-data") {

/*	 <input type="hidden" name="PAGE" value="A02_POST" />
      <input type="hidden" name="THISPAGE" value="A02_POST" />
      <input type="hidden" name="NEXTPAGE" value="J14" />
      <input type="hidden" name="CMSKICK" value="" />
      <input type="hidden" name="PAGE" value="J14" />
      <input type="hidden" name="THISPAGE" value="A02_POST" />
      <input type="hidden" name="NEXTPAGE" value="J14" />
  	  <input type="password" name="PASSWORD"
  	  "javascript:location=\'/xslt?PAGE=A02_POST 
*/
     	
	$fields = array(
						'PAGE' => "A02_POST",
						'THISPAGE' => "A02_POST",
						'NEXTPAGE' => "J14",
						'CMSKICK' => "",
						'PAGE' => "J14",
						'PASSWORD' => FIREWALL_PASSWORD
				);
	
	$post = RestClient::post("http://192.168.2.1/xslt?PAGE=A02_POST",$fields);
	$response= $post->getResponse();
	//echo $response;

	//  	  <pre class="textmono">Redirection is disabled</pre>
	$pattern = "'<pre class=\"textmono\">(.*?)</pre>'si";
	$noresult = preg_match_all($pattern, $response, $matches);
	$lasterr = preg_last_error();
	$t1 = '';
	if ($matches[1][1] != '')
    {
        $t1=$matches[1][1];
    } else {
    	echo $response;
    } 
    	
    
    /*
    current secs since boot: 8602
	session table 935/1024 available, 0/512 used in inbound sessions:
	sess[13]: bkt 11, flags: 0x000001a1, proto: 6, cnt: 5
  	l: 192.168.2.204:61148, f: 157.55.56.166:40033, n: 74.180.120.20:61148
  	lnd: (60,0), fnd: (44,0)
  	
  	replace \n -> space, replace sess[ -> \nsess[, put in array
	*/
    
	$t1 = preg_replace( '/\((\d+),(\d+)\)/', '$1_$2', $t1); // handle lnd: (60,0) and fnd (44,0):
	$find = array ("\n",",sess[","]:","bkt","TCP state","TCP IN: is:","TCP OUT: is:","last used","unack'd","mss","windows_scale");
	$repl = array (",","\nsess=",",","bkt=","TCP state:","IN_is:","OUT_is:","last_used:","unacked:","mss:","windows_scale:");
	$t1 = str_replace($find,$repl, $t1);
//	IN_is: 1434494543, sent: 40984, unacked: 339, mss: 0, windows_scale: 0 ,  OUT_is 3441205173, sent: 4817, unacked: 0, mss: 0, windows_scale: 0
    $sessions = explode("\n", $t1); 
    
	//echo "<pre>";
    //print_r($sessions);
//    echo $sessions[0];
	$a1 = explode(",",$sessions[0]);
	$a2 = explode(":",$a1[1]);
	$secs_boot = $a2[1];
	$a3 = explode("/",$a1[2]);
	$a5 = explode(" ",$a3[0]);
	$a4 = explode("/",$a1[3]);
	$status [0] = "secs_since_boot:".$secs_boot.",sessions_available:". $a5[2].",inbound_sessions:".$a4[0];
	unset($sessions[0]);
	foreach ($sessions  as &$value) {
		$find = array ("/ sent/","/ unacked/","/ mss/","/ windows_scale/");
		$repl = array ("IN_sent","IN_unacked","IN_mss","IN_windows_scale");
		$value = preg_replace($find, $repl, $value, 1);
		$find = array ("/ sent/","/ unacked/","/ mss/","/ windows_scale/");
		$repl = array ("OUT_sent","OUT_unacked","OUT_mss","OUT_windows_scale");
		$value = preg_replace($find, $repl, $value, 1);
		$find = array (",",":"," ");
		$repl = array ("&","=","");
		$value = str_replace($find,$repl, $value);
		parse_str($value,$value);
		$value['last_used'] = $secs_boot - $value['last_used'];
	}
	//print_r($sessions);
	
	//$json_nat = json_encode($output);
	//echo $json_nat;
    //echo "</pre>";
       
	return $sessions;
}

function FindAddress($ip) {
	$mysql="SELECT * ". 
			" FROM  `net_iplookup`" .  
			" WHERE ip='".$ip."'";  
	if ($resset=FetchRow($mysql)) {
		return $resset['id'];
	} else { 			// insert and return id
	    $mysql="INSERT INTO `net_iplookup` (ip, name) values ('".$ip."','".$ip."')";
		$result=mysql_query($mysql);
		if (!$result) mySqlError($mysql);	
		return  mysql_insert_id();
	}
}

function ImportSessions() {
 
	$sessionsresponse=GetSessions();
	
	$sessionsimported=0;
   	$mysql = "UPDATE `net_sessions` SET `active` = '0';";
	if (!mysql_query($mysql))  mySqlError($mysql);
    	
	foreach ($sessionsresponse AS $session) {
    	
		$mysql="SELECT * ". 
				" FROM  `net_sessions`" .  
				" WHERE sessionid='".$session['sess']."'";  
		$ressessions=mysql_query($mysql);
		if ($dbsession=mysql_fetch_array($ressessions)) {
			// check for same ip's???
				$mysql="DELETE ". 
						" FROM  `net_sessions`" .  
						" WHERE sessionid='".$session['sess']."'";  
				if (!mysql_query($mysql)) mySqlError($mysql);
			}

		$local = explode("=", $session['l']);
		$localid= FindAddress($local['0']); 
		$remote = explode("=", $session['f']);
		$remoteid= FindAddress($remote['0']); 
		$firew = explode("=", $session['n']);
		$firewid= FindAddress($firew['0']); 
		$mysql= 'INSERT INTO `net_sessions` (
					`sessionid` ,
					`protocol` ,
					`local_address` ,
					`local_port` ,
					`remote_address` ,
					`remote_port` ,
					`firewall_address` ,
					`firewall_port` ,
					`TCPstate` ,
					`last_used` ,
					`bkt` ,
					`flags` ,
					`count` ,
					`lnd` ,
					`fnd` ,
					`max_idle` ,
					`IN_is` ,
					`IN_sent` ,
					`IN_unacked` ,
					`IN_mss` ,
					`IN_windows_state` ,
					`OUT_is` ,
					`OUT_sent` ,
					`OUT_unacked` ,
					`OUT_mss` ,
					`OUT_windows_state` ,
					`active`
					)
				VALUES (' . 
					'"'.$session['sess'].'",'.
					'"'.$session['proto'].'",'.
					'"'.$localid.'",'.
					'"'.$local[1].'",'.
					'"'.$remoteid.'",'.
					'"'.$remote[1].'",'.
					'"'.$firewid.'",'.
					'"'.$firew[1].'",'.
					'"'.$session['TCPstate'].'",'.
					'"'.$session['last_used'].'",'.
					'"'.$session['bkt'].'",'.
					'"'.$session['flags'].'",'.
					'"'.$session['cnt'].'",'.
					'"'.$session['lnd'].'",'.
					'"'.$session['fnd'].'",'.
					'"'.$session['max_idle'].'",'.
					'"'.$session['IN_is'].'",'.
					'"'.$session['IN_sent'].'",'.
					'"'.$session['IN_unacked'].'",'.
					'"'.$session['IN_mss'].'",'.
					'"'.$session['IN_windows_state'].'",'.
					'"'.$session['OUT_is'].'",'.
					'"'.$session['OUT_sent'].'",'.
					'"'.$session['OUT_unacked'].'",'.
					'"'.$session['OUT_mss'].'",'.
					'"'.$session['OUT_windows_state'].'",'.
					'"1");';
	
			if (!mysql_query($mysql)) mySqlError($mysql);	
			$sessionsimported++;
    }
    
	return $sessionsimported;
}

function MoveTransactionsPositions() {
// ******************************************************************************************************************
//
//		Read IB import table and close/open positions based ON new orders coming in.
//
//		in: 	trd_transactions
//		out: 	trd_positions
//
// ******************************************************************************************************************
//		process order lines
//		check of open position
//			create openposition
//		update open postion
//		remainder of open postion - Y, duplicate remainder
//		{calc totals/profit...}
	$mysql="SELECT * ". 
			" FROM  `trd_transactions`" .  
			" WHERE NOT processed" .  
			" ORDER BY date_time";
	if (!$resorders = mysql_query($mysql)) {
		//echo "Nothing to do<br/>\r\n";
		exit;
	}
//loop till done
//  read not processed
// 		retrieve next order sorted by date/time

	$pos = new Position();
	
	$positionsopenclosed=0;
	while ($transactions = mysql_fetch_array($resorders)) {
		$ext_qty=$transactions['quantity']*$transactions['multiplier'];
		// 		BUY		SELL	POS		ORDER		WHAT						DO
		//1)	100		-100	0					Sell whole pos 				Close Position
		//		-100	100		0					Sell whole pos				Close Position
		//2)	100		-50		50					Sell part pos				Add sale to Open Position?		
		//		-100	50		-50					Sell part pos				Add sale to Open Position?
		//3)	50		-100	-50					Sell more than pos			Close Position and Open new one
		//		-50		100		50					Sell more than pos			Close Position and Open new one
		//4)	100+100			200		Not Exist	Add to Position				Add buy to Position
		//5)	100+100			200		Exist		Update Position				Did not get complete position from IB, qty,price,comm update
		
		if ($position=$pos->Find($transactions['symbol'])) {       
			if ($transactions['buy_sell']==$position['buy_sell']) { // 4)5) 	Open buy=buy or sell=sell postion found. Add to existing position
				$ord= new Orders("OPEN");
				if ($order=$ord->Find($position['id'],$transactions['id'])) { 	// Same order just update values instead of averageing
					$order['orderid']=$transactions['id'];						// TODO:: Partial allocated order should be offset (now can find total quantity of order)
					$order['date']=$transactions['date_time'];
					$order['qty']=$ext_qty;
					$order['price']=$transactions['price'];
					$order['comm']=$transactions['commission'];
					$ord->Update($order);
				} else {														// New order to current position
					$order['posid']=$position['id'];
					$order['orderid']=$transactions['id'];
					$order['date']=$transactions['date_time'];
					$order['qty']=$ext_qty;
					$order['price']=$transactions['price'];
					$order['comm']=$transactions['commission'];
					$ord->Add($order);
					if ($position['status']=='PRE') {
						$position['status']="ACT";
						$position['name']=$transactions['description'];
						$pos->Update($position);
					}
				}
				$ord=NULL;
			} else { 		// Open Position found and buy_sell<>buy_sell
				switch (TRUE) {
					case (abs($position['qty'])<=abs($ext_qty)):		// 1) open=close -> close all
						$ord= new Orders("CLOSE");
						$order['posid']=$position['id'];
						$order['orderid']=$transactions['id'];
						$order['date']=$transactions['date_time'];
						if (abs($position['qty'])<=abs($ext_qty)) {
							$order['qty']=$position['qty'];
						} else {
							$order['qty']=$ext_qty;
						}
						$order['price']=$transactions['price'];
						$order['comm']=$transactions['commission'];
						$ord->Add($order);			
    					$pos->Close($position['id']);
    					$ord=NULL;
						if (abs($position['qty'])<abs($ext_qty)) {		// Create new for remainder 
							$ord= new Orders("OPEN");
	    					$result=MapTransToPosition($transactions);
	    					$position['buy_sell']=$result['position']['buy_sell'];
	    					$newid=$pos->Add($position);		// use old here (copy)
	    					if ($result['order']['qty']>0)	{	//Long
	    						$result['order']['qty']=abs($ext_qty)-abs($position['qty']);
	    					} else {
	    						$result['order']['qty']=-1*abs($ext_qty)-abs($position['qty']);
	    					}
	    					$result['order']['posid']=$newid;
							$ord->Add($result['order']);
						}
   	   					break;
   					case (abs($position['qty'])>abs($ext_qty)):		// 2) open is more than close -> close part
   						//
   						//		-Copy position (Oldest Orders Till qty)
   						//		-Add sales order to Copy
   						//		-Close Copy position
   						//		-Remove qty from Closed position (done during copy orders)
   						//
   						// TODO:: if already closed part today then add to today closed one?
   						$newid=$pos->Copy($transactions['symbol'],$ext_qty);
						$ord= new Orders("CLOSE");
						$order['posid']=$newid;
						$order['orderid']=$transactions['id'];
						$order['date']=$transactions['date_time'];
						if ($position['qty']>0) { 		// Long
							$order['qty']=-$ext_qty;
						} else {
							$order['qty']=$ext_qty;
						}
						$order['price']=$transactions['price'];
						$order['comm']=$transactions['commission'];
						$ord->Add($order);
	  					$pos->Close($newid);
	   					$createnewopen=0;
    					break;
					}
				}
			} else {		// No Open pos found, create new one
				$ord= new Orders("OPEN");
	    		$result=MapTransToPosition($transactions);
	    		$result['position']['status']="ACT";
	    		$newid=$pos->Add($result['position']);
	    		$result['order']['posid']=$newid;
				$ord->Add($result['order']);
				$ord=NULL;
			}
		// DONE: Fix me
		//UPDATE `ha_test`.`trd_transactions` SET `processed` = '1' WHERE `trd_transactions`.`id` =334483766;
		$mysql='UPDATE `trd_transactions` SET `processed`="1" WHERE id="'.$transactions['id'].'"';
		if (!mysql_query($mysql)) mySqlError($mysql);
		}
	
	return 	$positionsopenclosed;
}


function ApplyOrder(&$pos,$position,$transactions,$ext_qty) {

	switch (TRUE) {
		case (abs($position['qty'])<=abs($ext_qty)):		// 1) open=close -> close all
			$sord= new Orders("CLOSE");
			$sorder['posid']=$position['id'];
			$sorder['orderid']=$transactions['id'];
			$sorder['date']=$transactions['date_time'];
			if (abs($position['qty'])<=abs($ext_qty)) {
				$sorder['qty']=$position['qty'];
			} else {
				$sorder['qty']=$ext_qty;
			}
			$sorder['price']=$transactions['price'];
			$sorder['comm']=$transactions['commission'];
			$sord->Add($sorder);			
			$pos->Close($position['id']);
			if (abs($position['qty'])<abs($ext_qty)) {		// Create new for remainder 
				$bord= new Orders("OPEN");
				$result=MapTransToPosition($transactions);
				$position['buy_sell']=$result['position']['buy_sell'];
				$newid=$pos->Add($position);		// use old here (copy)
				if ($result['order']['qty']>0)	{	//Long
					$result['order']['qty']=abs($ext_qty)-abs($position['qty']);
				} else {
    				$result['order']['qty']=-1*abs($ext_qty)-abs($position['qty']);
    			}
				$result['order']['posid']=$newid;
				$sord->Add($result['order']);
				} 
			break;
		case (abs($position['qty'])>abs($ext_qty)):		// 2) open is more than close -> close part
   			//
			//		-Copy position (Oldest Orders Till qty)
			//		-Add sales order to Copy
			//		-Close Copy position
			//		-Remove qty from Closed position (done during copy orders)
			//
			// TODO:: if already closed part today then add to today closed one?
			$newid=$pos->Copy($transactions['symbol'],$ext_qty);
			$sord= new Orders("CLOSE");
			$sorder['posid']=$newid;
			$sorder['orderid']=$transactions['id'];
			$sorder['date']=$transactions['date_time'];
			if ($position['qty']>0) { 		// Long
				$sorder['qty']=-$ext_qty;
			} else {
				$sorder['qty']=$ext_qty;
			}
			$sorder['price']=$transactions['price'];
			$sorder['comm']=$transactions['commission'];
			$sord->Add($sorder);
			$pos->Close($newid);
			break;
		}
}

function UpdateRowvalues() {

	$queries=0;
	// Cleanup Empty Rows created by form edit
	$mysql='DELETE FROM `trd_pos_checklist` WHERE `checklist` = ""';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='DELETE FROM `trd_pos_notes` WHERE `notes` = ""';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='DELETE FROM `trd_pos_errors` WHERE `error` =0';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='DELETE FROM `trd_pos_close` WHERE (`sorderid` = "" OR `sorderid` IS NULL) AND `sqty` =0';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	// Insert missing children
	$mysql='INSERT INTO `trd_pos_links` (`posid`) '.
			'(SELECT  a.id FROM trd_positions AS a '.
			'LEFT JOIN trd_pos_links AS l ON a.id=l.posid WHERE l.posid IS NULL)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='INSERT INTO `trd_pos_strategy` (`posid`) '.
			'(SELECT  a.id FROM trd_positions AS a '.
			'LEFT JOIN trd_pos_strategy AS l ON a.id=l.posid WHERE l.posid IS NULL)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='INSERT INTO `trd_pos_performance` (`posid`) '.
			'(SELECT  a.id FROM trd_positions AS a '.
			'LEFT JOIN trd_pos_performance AS l ON a.id=l.posid WHERE l.posid IS NULL)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Remove any Orphans
	$mysql='DELETE trd_pos_performance from trd_pos_performance '.
			'LEFT JOIN trd_positions ON trd_positions.id=trd_pos_performance.posid WHERE trd_positions.id IS NULL';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='DELETE trd_pos_open from trd_pos_open '.
			'LEFT JOIN trd_positions ON trd_positions.id=trd_pos_open.posid WHERE trd_positions.id IS NULL';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='DELETE trd_pos_close from trd_pos_close '.
			'LEFT JOIN trd_positions ON trd_positions.id=trd_pos_close.posid WHERE trd_positions.id IS NULL';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='DELETE trd_pos_strategy from trd_pos_strategy '.
			'LEFT JOIN trd_positions ON trd_positions.id=trd_pos_strategy.posid WHERE trd_positions.id IS NULL';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update urls positions
	$mysql='UPDATE trd_pos_links AS p left JOIN trd_positions AS a ON a.id=p.posid SET site1=concat("_|-|http://finance.yahoo.com/q?s=",a.ticker)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='UPDATE trd_pos_links AS p left JOIN trd_positions AS a ON a.id=p.posid SET site2=concat("_|-|http://www.freestockcharts.com/?Symbol=",a.ticker)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='UPDATE trd_pos_links AS p left JOIN trd_positions AS a ON a.id=p.posid SET site3=concat("_|-|index.php?option=com_content&view=article&id=118&Itemid=112&tmpl=component&ticker=",a.ticker)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	// update urls idx
	$mysql='UPDATE trd_idx_links AS p left JOIN trd_indexes AS a ON a.id=p.posid SET site3=concat("_|-|index.php?option=com_content&view=article&id=119&Itemid=112&tmpl=component&ticker=",a.ticker)';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// copy status 
	$mysql='UPDATE trd_pos_performance AS p left JOIN trd_positions AS a ON a.id=p.posid SET status_copy=status';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// copy type
	$mysql='UPDATE trd_pos_performance AS p left JOIN trd_positions AS a ON a.id=p.posid SET type_copy=type';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// clear calc fields
	$mysql='UPDATE trd_pos_performance SET '.
			'real_profit=0, '.
			'real_profit_perc=0, '.
			'today_unreal=0, '.
			'today_unreal_perc=0, '.
			'unr_profit=0, '.
			'unr_profit_perc=0, '.
			'real_profit_perc=0, '.
			'profit_balance=0, '.
			'both_profit=0, '.
			'win_loss=0 ' .
			'WHERE status_copy IN ("ACT","CLD")';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update buy total (no sum) LINE
	$mysql='UPDATE trd_pos_open AS o  LEFT JOIN trd_positions AS a ON a.id = o.posid SET btotal = -bqty*bprice+bcomm WHERE status IN ("ACT", "CLD")';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// sell total (no sum) LINE
	$mysql='UPDATE trd_pos_close AS c left JOIN trd_positions AS a ON a.id = c.posid SET stotal = sqty*sprice+scomm WHERE status="CLD"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Sum Lines to performance
	$mysql='UPDATE trd_pos_performance AS target '.
			'INNER JOIN '.
			'( '.
			'select agg.posid, agg.bqty, agg.bcomm, agg.btotal '.
			'from trd_pos_performance T inner join ( '.
				'select posid, sum(bqty) as bqty, sum(bcomm) as bcomm, sum(btotal) as btotal '.
				'from trd_pos_open '.
				'group by posid '.
			') as agg '.
			'where T.posid  = agg.posid '.
			'group by T.posid '.
			') as source '.
			'ON target.posid = source.posid '.
			'SET target.bqty = source.bqty, target.bprice=(abs(source.btotal)-abs(source.bcomm))/source.bqty, target.bcomm=source.bcomm, target.btotal=source.btotal';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Sum Lines to performance
	$mysql='UPDATE trd_pos_performance AS target '.
			'INNER JOIN '.
			'( '.
			'select agg.posid, agg.sqty, agg.scomm, agg.stotal '.
			'from trd_pos_performance T inner join ( '.
				'select posid, sum(sqty) as sqty, sum(scomm) as scomm, sum(stotal) as stotal '.
				'from trd_pos_close '.
				'group by posid '.
			') as agg '.
			'where T.posid  = agg.posid '.
			'group by T.posid '.
			') as source '.
			'ON target.posid = source.posid '.
			'SET target.sqty = source.sqty, target.sprice=(abs(source.stotal) '.
				'-abs(source.scomm))/source.sqty, target.scomm=source.scomm, target.stotal=source.stotal';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// First Position Open date
	$mysql='UPDATE trd_pos_performance p SET first_bdate = ( '.
			'SELECT cast( bdate AS date ) '.
			'FROM trd_pos_open '.
			'WHERE posid = p.posid '.
			'GROUP BY posid '.
			'HAVING min( cast( bdate AS date ) ) )';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	// update profit 
	$mysql='UPDATE trd_pos_performance AS p SET real_profit=btotal+stotal WHERE status_copy IN ("CLD") AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update real_profit_percentage
	$mysql='UPDATE trd_pos_performance as p SET real_profit_perc = real_profit/abs(btotal)*100 WHERE status_copy IN ("CLD") AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update unr_profit 
	$mysql='UPDATE trd_pos_performance AS p SET unr_profit = btotal+bqty*last+bcomm WHERE status_copy="ACT" AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update unreal percentage 
	$mysql='UPDATE trd_pos_performance SET unr_profit_perc = (unr_profit/abs(btotal))*100 WHERE status_copy="ACT" AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update both profit 
	$mysql='UPDATE trd_pos_performance SET both_profit=real_profit WHERE status_copy IN ("CLD") AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update both profit 
	$mysql='UPDATE trd_pos_performance as p SET both_profit=unr_profit WHERE status_copy="ACT" AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update win_loss
	$mysql='UPDATE trd_pos_performance '.
			'SET win_loss=IF(ABS(real_profit_perc)<2,"E", IF(real_profit_perc>0,"W", "L")) '.
			'WHERE (status_copy="CLD" OR status_copy="AUD")AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update max loss
	$mysql='UPDATE trd_pos_strategy AS a '.
		'SET pot_loss = '.
		'(select btotal from trd_pos_performance where posid=a.posid) '.
		'+(select bqty from trd_pos_performance where posid=a.posid) '.
		'*a.stop '.
		'+(select bcomm from trd_pos_performance where posid=a.posid)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update max win
	$mysql='UPDATE trd_pos_strategy AS a '.
		'SET pot_winn = '.
		'(select btotal from trd_pos_performance where posid=a.posid) '.
		'+((select bqty from trd_pos_performance where posid=a.posid))'.
		'*a.target '.
		'+(select bcomm from trd_pos_performance where posid=a.posid) ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update win_loss ratio
	$mysql='UPDATE trd_pos_strategy AS a '.
			'SET win_ratio=ABS(pot_winn/pot_loss) ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update today performance + wrong if bought today
	$mysql='UPDATE trd_pos_performance as p '.
			'SET today_unreal=(last-bprice)*bqty '. 
			'WHERE status_copy="ACT" AND first_bdate=DATE(NOW()) AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql='UPDATE trd_pos_performance as p '.
			'SET today_unreal=(last-prev_close)*bqty '. 
			'WHERE status_copy="ACT" AND first_bdate<>DATE(NOW()) AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// update today unreal percentage (sum btotal)
	$mysql='UPDATE trd_pos_performance as p '.
			'SET today_unreal_perc = today_unreal/abs(btotal)*100 '.
			'WHERE status_copy="ACT" AND type_copy <> "TRF"';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Calculate running balance	
	$mysql=	'SET @balance:=0 ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql=	'CREATE temporary table balances ' .
			'SELECT posid, sdate, @balance:=(@balance+(select real_profit from  trd_pos_performance where posid=c.posid ))  AS balance ' .
			'FROM trd_pos_close AS c LEFT JOIN trd_positions AS a ON c.posid = a.id '.
			'WHERE ((status IN ("AUD","CLD")) and type<>"TRF") ORDER BY sdate';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	$mysql=	'UPDATE trd_pos_performance AS p LEFT JOIN balances as b ON p.posid=b.posid SET profit_balance = b.balance';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	// Update Accounts Deposits 
	// btotal - bcomm = transfer, bcomm is other cost
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.deposits = '.
			'(SELECT sum(btotal-bcomm) AS  deposits  '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid  '.
			'WHERE (a.type = "TRF") AND a.account=acc.account  '.
			'GROUP BY a.account)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Update Accounts Withdrawals
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.withdrawals = '.
			'(SELECT -(sum(stotal-scomm)) AS  withdrawal  '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid  '.
			'WHERE (a.type = "TRF") AND a.account=acc.account  '.
			'GROUP BY a.account)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Update Accounts Commission
	$mysql='UPDATE trd_accounts as acc '. 
			'SET acc.commission = '.
			'(SELECT (sum(p.bcomm)+sum(p.scomm)) AS  comm '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid '.
			'WHERE a.type IN ("STK","OPT") AND a.account=acc.account '.
			'GROUP BY a.account) ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Accounts Other Cost
	$mysql='UPDATE trd_accounts as acc '. 
			'SET acc.other = '.
			'(SELECT (sum(p.bcomm)+sum(p.scomm)) AS  other '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid '.
			'WHERE a.type IN ("TRF") AND a.account=acc.account '.
			'GROUP BY a.account) ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	// Update Accounts Real Profit
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.real_profit = '.
			'(SELECT sum(p.real_profit) AS  real_profit '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid '.
			'WHERE a.type IN ("STK","OPT") AND a.account=acc.account '.
			'GROUP BY a.account)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Update Accounts UNR Real Profit
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.unr_profit = '.
			'(SELECT sum(p.unr_profit) AS unr_profit '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid '.
			'WHERE a.type IN ("STK","OPT") AND a.account=acc.account '.
			'GROUP BY a.account)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Update Accounts Gross Profit
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.profit_gross = '.
			'real_profit-commission-other ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Update Accounts Gross Position Value
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.gpv = '.
			'(SELECT -sum(p.btotal)+sum(p.unr_profit) AS  gpv  '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid '.
			'WHERE (a.type IN ("STK","OPT")) AND a.status = "ACT" AND a.account=acc.account '.
			'GROUP BY a.account)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	
	// Update Cash
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.cash = '.
			'(SELECT sum(p.btotal)+sum(p.stotal) '.
			'FROM trd_positions AS a  '.
			'LEFT JOIN trd_pos_performance p ON a.id = p.posid '.
			'WHERE (a.type IN ("TRF","STK","OPT")) AND (a.status IN ("ACT","CLD","AUD")) AND a.account=acc.account '.
			'GROUP BY a.account)  ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);

	// Update Accounts Net Liquidation Value
	$mysql='UPDATE trd_accounts as acc '.
			'SET acc.nlv = '.
			'gpv+cash ';
	(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
	

	Return $queries;
} 

function Alerts(){

	$mysql="SELECT * ". 
			" FROM  `trd_alerts_dd` WHERE active='Y'" ;
	if (!$resalerts = mysql_query($mysql)) {
		//echo "Nothing to do<br/>\r\n";
		exit;
	}
	
	$inserts = 0;
	while ($alerts = mysql_fetch_array($resalerts)) {
		$mysql=$alerts['sql'];
		$mysql=str_replace("{alert}",$alerts['id'],$mysql);
//		echo $mysql."</br>";
		(!mysql_query($mysql) ? mySqlError($mysql) : $inserts+=mysql_affected_rows());
	}
	return $inserts;

}

function AlertsActions(){


	$mysql="SELECT trd_pos_alerts.id as id, `trd_pos_alerts`.`action_date`, `trd_alert_actions`.`email`, `trd_alert_actions`.`description`, ".
			"`trd_alert_actions`.`message`  ". 
			" FROM  `trd_pos_alerts` ".
			" INNER JOIN `trd_alerts_dd` ON `trd_alerts_dd`.`id`=`trd_pos_alerts`.`alert` ".
			" INNER JOIN `trd_alert_actions` ON `trd_alerts_dd`.`actionid`=`trd_alert_actions`.`id` ".
			" WHERE `trd_pos_alerts`.`action_date` IS NULL AND `trd_pos_alerts`.`processed` <> '1'" ;

	if (!$resactions = mysql_query($mysql)) {
		//echo "Nothing to do<br/>\r\n";
		return 0;
	}
	$headers = 'From: pvloon66@gmail.com' . "\r\n" .
    			'Reply-To: pvloon66@gmail.com' . "\r\n" .
    			'X-Mailer: PHP/' . phpversion();
	$send = 0;
	while ($actions = mysql_fetch_assoc($resactions)) {
		$subject = labelReplace($actions['id'],$actions['description']);
		$message = labelReplace($actions['id'],$actions['message']);
		if ($message == "") { $message=$subject; } 
//	echo "actions['id']:".$actions['id']."</br>";
//	echo "to:".$actions['email']."</br>";
//	echo "subject:".$subject."</br>";
//	echo "message:".$message."</br>";
		if (sendmail($actions['email'], $subject, $message, 'Stock Alert')) {
			$send++;
			$mysql="UPDATE `trd_pos_alerts` ".
					" SET action_date = NOW() ".
					" WHERE `trd_pos_alerts`.`id` = '". $actions['id']. "'" ;
			(!mysql_query($mysql) ? mySqlError($mysql) : $queries+=1);
		}
	}
	
	return $send;

}


function labelReplace($id,$subject) {

	$mysql='SELECT SQL_CALC_FOUND_ROWS DISTINCT `trd_positions`.`ticker` AS `trd_positions___ticker`, `trd_positions`.`ticker` '. 
	' AS `trd_positions___ticker_raw`, `trd_positions`.`empty3` AS `trd_positions___empty3`, `trd_positions`.`empty3` AS `trd_positions___empty3_raw`, '. 
	' `trd_positions`.`name` AS `trd_positions___name`, `trd_positions`.`name` AS `trd_positions___name_raw`, `trd_positions`.`empty4`  '. 
	' AS `trd_positions___empty4`, `trd_positions`.`empty4` AS `trd_positions___empty4_raw`, `trd_positions`.`account` AS  '. 
	'`trd_positions___account_raw`, trd_accounts.name AS `trd_positions___account`, `trd_positions`.`status` AS `trd_positions___status`,  '. 
	'`trd_positions`.`status` AS `trd_positions___status_raw`, `trd_positions`.`buy_sell` AS `trd_positions___buy_sell`,  '. 
	'`trd_positions`.`buy_sell` AS `trd_positions___buy_sell_raw`, `trd_positions`.`type` AS `trd_positions___type`, `trd_positions`.`type`  '. 
	'AS `trd_positions___type_raw`, `trd_positions`.`empty1` AS `trd_positions___empty1`, `trd_positions`.`empty1` AS `trd_positions___empty1_raw`, '. 
	' `trd_positions`.`id` AS `trd_positions___id`, `trd_positions`.`id` AS `trd_positions___id_raw`, `trd_pos_strategy`.`source` AS  '. 
	'`trd_pos_strategy___source_raw`, `trd_source_dd`.`description` AS `trd_pos_strategy___source`, `trd_pos_strategy`.`trend` AS  '. 
	'`trd_pos_strategy___trend`, `trd_pos_strategy`.`trend` AS `trd_pos_strategy___trend_raw`, `trd_pos_strategy`.`entry_strategy` AS  '. 
	'`trd_pos_strategy___entry_strategy_raw`, `trd_strategy`.`description` AS `trd_pos_strategy___entry_strategy`,  '. 
	'`trd_pos_strategy`.`exit_strategy` AS `trd_pos_strategy___exit_strategy_raw`, `trd_exit_strategy`.`description` AS  '. 
	'`trd_pos_strategy___exit_strategy`, `trd_pos_strategy`.`target` AS `trd_pos_strategy___target`, `trd_pos_strategy`.`target`  '. 
	'AS `trd_pos_strategy___target_raw`, `trd_pos_strategy`.`stop` AS `trd_pos_strategy___stop`, `trd_pos_strategy`.`stop` AS  '. 
	'`trd_pos_strategy___stop_raw`, `trd_pos_strategy`.`pot_winn` AS `trd_pos_strategy___pot_winn`, `trd_pos_strategy`.`pot_winn`  '. 
	'AS `trd_pos_strategy___pot_winn_raw`, `trd_pos_strategy`.`pot_loss` AS `trd_pos_strategy___pot_loss`, `trd_pos_strategy`.`pot_loss` '. 
	' AS `trd_pos_strategy___pot_loss_raw`, `trd_pos_strategy`.`win_ratio` AS `trd_pos_strategy___win_ratio`, `trd_pos_strategy`.`win_ratio` '. 
	' AS `trd_pos_strategy___win_ratio_raw`, `trd_pos_strategy`.`next_earning_date` AS `trd_pos_strategy___next_earning_date`,  '. 
	'`trd_pos_strategy`.`next_earning_date` AS `trd_pos_strategy___next_earning_date_raw`, `trd_pos_strategy`.`next_action` AS  '. 
	'`trd_pos_strategy___next_action_raw`, CONCAT("",trd_next_action.description,"") AS `trd_pos_strategy___next_action`,  '. 
	'`trd_pos_strategy`.`id` AS `trd_pos_strategy___id`, `trd_pos_strategy`.`id` AS `trd_pos_strategy___id_raw`, `trd_pos_strategy`.`posid` '. 
	' AS `trd_pos_strategy___posid`, `trd_pos_strategy`.`posid` AS `trd_pos_strategy___posid_raw`, `trd_pos_alerts`.`id` AS  '. 
	'`trd_pos_alerts___id`, `trd_pos_alerts`.`id` AS `trd_pos_alerts___id_raw`, `trd_pos_alerts`.`posid` AS `trd_pos_alerts___posid_raw`,  '. 
	'`trd_positions_1`.`ticker` AS `trd_pos_alerts___posid`, `trd_pos_alerts`.`alert` AS `trd_pos_alerts___alert_raw`,  '. 
	'`trd_alerts_dd`.`description` AS `trd_alerts_dd___description`, `trd_pos_alerts`.`processed` AS `trd_pos_alerts___processed`,  '. 
	'`trd_pos_alerts`.`processed` AS `trd_pos_alerts___processed_raw`, `trd_pos_alerts`.`alert_date` AS `trd_pos_alerts___alert_date`, '. 
	' `trd_pos_alerts`.`alert_date` AS `trd_pos_alerts___alert_date_raw`, `trd_pos_alerts`.`l1` AS `trd_pos_alerts___l1`,  '. 
	'`trd_pos_alerts`.`l1` AS `trd_pos_alerts___l1_raw`, `trd_pos_alerts`.`v1` AS `trd_pos_alerts___v1`, `trd_pos_alerts`.`v1` AS  '. 
	'`trd_pos_alerts___v1_raw`, `trd_pos_alerts`.`l2` AS `trd_pos_alerts___l2`, `trd_pos_alerts`.`l2` AS `trd_pos_alerts___l2_raw`,  '. 
	'`trd_pos_alerts`.`v2` AS `trd_pos_alerts___v2`, `trd_pos_alerts`.`v2` AS `trd_pos_alerts___v2_raw`, `trd_pos_alerts`.`l3` AS  '. 
	'`trd_pos_alerts___l3`, `trd_pos_alerts`.`l3` AS `trd_pos_alerts___l3_raw`, `trd_pos_alerts`.`v3` AS `trd_pos_alerts___v3`,  '. 
	'`trd_pos_alerts`.`v3` AS `trd_pos_alerts___v3_raw`, `trd_pos_alerts`.`l4` AS `trd_pos_alerts___l4`, `trd_pos_alerts`.`l4` AS  '. 
	'`trd_pos_alerts___l4_raw`, `trd_pos_alerts`.`v4` AS `trd_pos_alerts___v4`, `trd_pos_alerts`.`v4` AS `trd_pos_alerts___v4_raw`,  '. 
	'`trd_pos_alerts`.`l5` AS `trd_pos_alerts___l5`, `trd_pos_alerts`.`l5` AS `trd_pos_alerts___l5_raw`, `trd_pos_alerts`.`v5` AS  '. 
	'`trd_pos_alerts___v5`, `trd_pos_alerts`.`v5` AS `trd_pos_alerts___v5_raw`, `trd_pos_open`.`id` AS `trd_pos_open___id`,  '. 
	'`trd_pos_open`.`id` AS `trd_pos_open___id_raw`, `trd_pos_open`.`posid` AS `trd_pos_open___posid`, `trd_pos_open`.`posid` AS  '. 
	'`trd_pos_open___posid_raw`, `trd_pos_open`.`borderid` AS `trd_pos_open___borderid`, `trd_pos_open`.`borderid` AS  '. 
	'`trd_pos_open___borderid_raw`, `trd_pos_open`.`bqty` AS `trd_pos_open___bqty`, `trd_pos_open`.`bqty` AS `trd_pos_open___bqty_raw`,  '. 
	'`trd_pos_open`.`bprice` AS `trd_pos_open___bprice`, `trd_pos_open`.`bprice` AS `trd_pos_open___bprice_raw`, `trd_pos_open`.`bcomm`  '. 
	'AS `trd_pos_open___bcomm`, `trd_pos_open`.`bcomm` AS `trd_pos_open___bcomm_raw`, `trd_pos_open`.`btotal` AS `trd_pos_open___btotal`,  '. 
	'`trd_pos_open`.`btotal` AS `trd_pos_open___btotal_raw`, `trd_pos_open`.`bdate` AS `trd_pos_open___bdate`, `trd_pos_open`.`bdate` AS  '. 
	'`trd_pos_open___bdate_raw`, `trd_pos_close`.`id` AS `trd_pos_close___id`, `trd_pos_close`.`id` AS `trd_pos_close___id_raw`,  '. 
	'`trd_pos_close`.`posid` AS `trd_pos_close___posid`, `trd_pos_close`.`posid` AS `trd_pos_close___posid_raw`, `trd_pos_close`.`sorderid` '. 
	' AS `trd_pos_close___sorderid`, `trd_pos_close`.`sorderid` AS `trd_pos_close___sorderid_raw`, `trd_pos_close`.`sqty` AS  '. 
	'`trd_pos_close___sqty`, `trd_pos_close`.`sqty` AS `trd_pos_close___sqty_raw`, `trd_pos_close`.`sprice` AS `trd_pos_close___sprice`,  '. 
	'`trd_pos_close`.`sprice` AS `trd_pos_close___sprice_raw`, `trd_pos_close`.`scomm` AS `trd_pos_close___scomm`, `trd_pos_close`.`scomm` '. 
	' AS `trd_pos_close___scomm_raw`, `trd_pos_close`.`stotal` AS `trd_pos_close___stotal`, `trd_pos_close`.`stotal` AS  '. 
	'`trd_pos_close___stotal_raw`, `trd_pos_close`.`sdate` AS `trd_pos_close___sdate`, `trd_pos_close`.`sdate` AS `trd_pos_close___sdate_raw`, '. 
	' `trd_pos_notes`.`notes` AS `trd_pos_notes___notes`, `trd_pos_notes`.`notes` AS `trd_pos_notes___notes_raw`, `trd_pos_notes`.`id` AS  '. 
	'`trd_pos_notes___id`, `trd_pos_notes`.`id` AS `trd_pos_notes___id_raw`, `trd_pos_notes`.`posid` AS `trd_pos_notes___posid`,  '. 
	'`trd_pos_notes`.`posid` AS `trd_pos_notes___posid_raw`, `trd_pos_errors`.`error` AS `trd_pos_errors___error_raw`, `trd_errors_dd`.`description` '. 
	' AS `trd_pos_errors___error`, `trd_pos_errors`.`id` AS `trd_pos_errors___id`, `trd_pos_errors`.`id` AS `trd_pos_errors___id_raw`,  '. 
	'`trd_pos_errors`.`posid` AS `trd_pos_errors___posid_raw`, `trd_positions_0`.`ticker` AS `trd_pos_errors___posid`,  '. 
	'`trd_pos_performance`.`id` AS `trd_pos_performance___id`, `trd_pos_performance`.`id` AS `trd_pos_performance___id_raw`,  '. 
	'`trd_pos_performance`.`posid` AS `trd_pos_performance___posid`, `trd_pos_performance`.`posid` AS `trd_pos_performance___posid_raw`, '. 
	' `trd_pos_performance`.`last` AS `trd_pos_performance___last`, `trd_pos_performance`.`last` AS `trd_pos_performance___last_raw`,  '. 
	'`trd_pos_performance`.`prev_close` AS `trd_pos_performance___prev_close`, `trd_pos_performance`.`prev_close` AS  '. 
	'`trd_pos_performance___prev_close_raw`, `trd_pos_performance`.`today_difference` AS `trd_pos_performance___today_difference`,  '. 
	'`trd_pos_performance`.`today_difference` AS `trd_pos_performance___today_difference_raw`, `trd_pos_performance`.`today_difference_perc`  '. 
	'AS `trd_pos_performance___today_difference_perc`, `trd_pos_performance`.`today_difference_perc` AS  '. 
	'`trd_pos_performance___today_difference_perc_raw`, `trd_pos_performance`.`today_unreal` AS `trd_pos_performance___today_unreal`, '. 
	' `trd_pos_performance`.`today_unreal` AS `trd_pos_performance___today_unreal_raw`, `trd_pos_performance`.`today_unreal_perc` AS  '. 
	'`trd_pos_performance___today_unreal_perc`, `trd_pos_performance`.`today_unreal_perc` AS `trd_pos_performance___today_unreal_perc_raw`,  '. 
	'`trd_pos_performance`.`first_bdate` AS `trd_pos_performance___first_bdate`, `trd_pos_performance`.`first_bdate` AS  '. 
	'`trd_pos_performance___first_bdate_raw`, `trd_pos_performance`.`pricedirection` AS `trd_pos_performance___pricedirection`,  '. 
	'`trd_pos_performance`.`pricedirection` AS `trd_pos_performance___pricedirection_raw`, `trd_pos_performance`.`current_lastupdate` '. 
	' AS `trd_pos_performance___current_lastupdate`, `trd_pos_performance`.`current_lastupdate` AS `trd_pos_performance___current_lastupdate_raw`, '. 
	' `trd_pos_performance`.`real_profit` AS `trd_pos_performance___real_profit`, `trd_pos_performance`.`real_profit` AS  '. 
	'`trd_pos_performance___real_profit_raw`, `trd_pos_performance`.`real_profit_perc` AS `trd_pos_performance___real_profit_perc`,  '. 
	'`trd_pos_performance`.`real_profit_perc` AS `trd_pos_performance___real_profit_perc_raw`, `trd_pos_performance`.`unr_profit` AS '. 
	' `trd_pos_performance___unr_profit`, `trd_pos_performance`.`unr_profit` AS `trd_pos_performance___unr_profit_raw`,  '. 
	'`trd_pos_performance`.`unr_profit_perc` AS `trd_pos_performance___unr_profit_perc`, `trd_pos_performance`.`unr_profit_perc` AS  '. 
	'`trd_pos_performance___unr_profit_perc_raw`, `trd_pos_performance`.`win_loss` AS `trd_pos_performance___win_loss`,  '. 
	'`trd_pos_performance`.`win_loss` AS `trd_pos_performance___win_loss_raw`, `trd_pos_performance`.`bqty` AS `trd_pos_performance___bqty`, '. 
	' `trd_pos_performance`.`bqty` AS `trd_pos_performance___bqty_raw`, `trd_pos_performance`.`bprice` AS `trd_pos_performance___bprice`,  '. 
	'`trd_pos_performance`.`bprice` AS `trd_pos_performance___bprice_raw`, `trd_pos_performance`.`bcomm` AS `trd_pos_performance___bcomm`,  '. 
	'`trd_pos_performance`.`bcomm` AS `trd_pos_performance___bcomm_raw`, `trd_pos_performance`.`btotal` AS `trd_pos_performance___btotal`, '. 
	' `trd_pos_performance`.`btotal` AS `trd_pos_performance___btotal_raw`, `trd_pos_performance`.`sqty` AS `trd_pos_performance___sqty`,  '. 
	'`trd_pos_performance`.`sqty` AS `trd_pos_performance___sqty_raw`, `trd_pos_performance`.`sprice` AS `trd_pos_performance___sprice`,  '. 
	'`trd_pos_performance`.`sprice` AS `trd_pos_performance___sprice_raw`, `trd_pos_performance`.`scomm` AS `trd_pos_performance___scomm`,  '. 
	'`trd_pos_performance`.`scomm` AS `trd_pos_performance___scomm_raw`, `trd_pos_performance`.`stotal` AS `trd_pos_performance___stotal`,  '. 
	'`trd_pos_performance`.`stotal` AS `trd_pos_performance___stotal_raw`, `trd_positions`.`id` AS slug , `trd_positions`.`id` AS `__pk_val`  '. 
	'FROM `trd_positions` LEFT JOIN `trd_pos_notes` AS `trd_pos_notes` ON `trd_pos_notes`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_pos_errors` AS `trd_pos_errors` ON `trd_pos_errors`.`posid` = `trd_positions`.`id` '. 
	'LEFT JOIN `trd_errors_dd` AS `trd_errors_dd` ON `trd_errors_dd`.`id` = `trd_pos_errors`.`error`  '. 
	'LEFT JOIN `trd_pos_close` AS `trd_pos_close` ON `trd_pos_close`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_pos_performance` AS `trd_pos_performance` ON `trd_pos_performance`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_pos_open` AS `trd_pos_open` ON `trd_pos_open`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_accounts` AS `trd_accounts` ON `trd_accounts`.`account` = `trd_positions`.`account`  '. 
	'LEFT JOIN `trd_pos_strategy` AS `trd_pos_strategy` ON `trd_pos_strategy`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_source_dd` AS `trd_source_dd` ON `trd_source_dd`.`id` = `trd_pos_strategy`.`source`  '. 
	'LEFT JOIN `trd_strategy` AS `trd_strategy` ON `trd_strategy`.`id` = `trd_pos_strategy`.`entry_strategy`  '. 
	'LEFT JOIN `trd_exit_strategy` AS `trd_exit_strategy` ON `trd_exit_strategy`.`id` = `trd_pos_strategy`.`exit_strategy`  '. 
	'LEFT JOIN `trd_next_action` AS `trd_next_action` ON `trd_next_action`.`id` = `trd_pos_strategy`.`next_action`  '. 
	'LEFT JOIN `trd_pos_links` AS `trd_pos_links` ON `trd_pos_links`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_positions` AS `trd_positions_0` ON `trd_positions_0`.`id` = `trd_pos_errors`.`posid`  '. 
	'LEFT JOIN `trd_pos_alerts` AS `trd_pos_alerts` ON `trd_pos_alerts`.`posid` = `trd_positions`.`id`  '. 
	'LEFT JOIN `trd_positions` AS `trd_positions_1` ON `trd_positions_1`.`id` = `trd_pos_alerts`.`posid`  '. 
	'LEFT JOIN `trd_alerts_dd` AS `trd_alerts_dd` ON `trd_alerts_dd`.`id` = `trd_pos_alerts`.`alert`  '. 
	'WHERE `trd_pos_alerts`.`id` = "'.$id.'"' ;
//	'LEFT JOIN `trd_alert_actions` AS `trd_alert_actions` ON `trd_alerts_dd`.`actionid` = `trd_alert_actions`.`id`  '. 
		
	if (!$resdata = mysql_query($mysql)) {	
		mySqlError($mysql);
		return false;
	}

	
	if ($data = mysql_fetch_assoc($resdata)) {
//	echo "<pre>"; print_r ($data); echo "</pre>";
		foreach ($data as $key => $value) {
			$pattern[$key]="/\{".$key."\}/";
		}
		return preg_replace($pattern, $data, $subject);
	} else {
		return FALSE;
	}
	
}
