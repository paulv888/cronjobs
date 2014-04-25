<?php
require_once 'includes.php';

define("MY_DEVICE_ID", 103);

// TODO: Error Handling http://www.w3schools.com/php/php_error.asp
// 
//		try {
//    		throw new Exception("Error on: $e->getLine() $mysql");
//		} catch(Exception $e) {
//		    echo "The exception was created ON line: " . $e->getLine();
//		}
//die;
// Using FreeStockCharts atm
//	$client = new SoapClient("http://www.webservicex.net/stockquote.asmx?WSDL");
//echo GetCurrentPrice(). " Open Positions Prices Updated <br/>\r\n";
//echo GetPricesWebServiceEx(). " Last Close Prices Updated <br/>\r\n";

echo ImportOrders()." Orders Imported <br/>\r\n";
echo MoveTransactionsPositions()." Positions Opened or Closed  <br/>\r\n";
echo GetPrices("trd_indexes", "trd_idx_performance"). " Indexes Updated <br/>\r\n";
echo GetPrices("trd_positions","trd_pos_performance"). " Open Positions Prices Updated <br/>\r\n";
echo UpdateRowvalues()." Calculation Queries Executed <br/>\r\n";
echo AlertsTR()." Alerts generated <br/>\r\n";
$starttime = '0700';
$endtime = '2000';
if(date('Hi')>$starttime and date('Hi')<$endtime) {
	echo AlertsActionsTR()." Alerts sent <br/>\r\n";
} else {
	echo "Not sending Alerts. ". date('H:i')." outside $starttime - $endtime<br/>\r\n";
}
echo UpdateLink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";


function FindInFlex($flexresponse, $symbol) {
	$found=FALSE;
    foreach ($flexresponse->FlexStatements->FlexStatement->OpenPositions->OpenPosition AS $found) {
		if(str_replace(" ", "",$found['symbol'])==$symbol) { 
			return $found;
		} 
	}
	return FALSE;
}

function MapTransToPosition($transaction) {
	$result['position']['type']=$transaction['assetclass'];
	$result['position']['ticker']=$transaction['symbol'];
	$result['position']['name']=$transaction['description'];
	$result['order']['orderid']=$transaction['id'];
	$result['order']['date']=$transaction['date_time'];
	$result['position']['buy_sell']=$transaction['buy_sell'];
	$result['order']['qty']=$transaction['quantity']*$transaction['multiplier'];
	$result['order']['price']=$transaction['price'];
	$result['order']['comm']=$transaction['commission'];
	return $result;
}

function GetFlexData($queryid) {
//Flex WebQuery:
//https://www.interactivebrokers.com/Universal/servlet/FlexStatementService.SendRequest?t=toker&q=63310
//https://www.interactivebrokers.com/Universal/servlet/FlexStatementService.GetStatement?q=2042042764&t=token&v=2
// DONE: Import to PHP, Check for duplicate Order number
	$mysql="SELECT * ". 
			" FROM  `trd_flexsettings`" .  
			" WHERE id='".$queryid."'";  
	
	if (!$flex = mysql_query($mysql)) {	
		mySqlError($mysql);
		die();
	}

	$flexsettings = mysql_fetch_array($flex);
	$queryid=$flexsettings['query_id'];
	$token=$flexsettings['token'];
   

    $retry=5;
    do {
	$url="https://www.interactivebrokers.com/Universal/servlet/FlexStatementService.SendRequest?t=$token&q=$queryid";
	$response = file_get_contents($url);
	//echo $response;
	$xmlheader="<?xml version='1.0' standalone='yes'?>";
    	$flexresponse = new SimpleXMLElement($xmlheader.$response);
    
    
	if (!is_numeric((string)$flexresponse->code)) {
    		echo "Error: ".$xml->code."<br/>\r\n on: '".$url; 
	    	die();
    	}

	$url = "https://www.interactivebrokers.com/Universal/servlet/FlexStatementService.GetStatement?q=$flexresponse->code&t=$token&v=2" ;
	//echo "Try: $retry <br />$url<br />";
	$response = file_get_contents($url);
	echo $response;
	$xmlheader="<?xml version='1.0' standalone='yes'?>";
	$flexresponse = new SimpleXMLElement($xmlheader.$response);
	$retry--;
	if ($flexresponse->code=="Statement generation in progress. Please try again shortly." or 
		$flexresponse->code=="Invalid request or unable to validate request.") {
		if ($retry >0) {
			echo "Sleeping(5) <br/>\r\n";
			sleep(5);
		} else {
			echo "Unable to retrieve Trade Confirms <br/>\r\n";
			die();				
		}
	}
	else { 
		$retry=-1; 
	}
    } while ($retry>0);
	return $flexresponse;
}

function ImportOrders() {
 
	$flexresponse=GetFlexData('TRADE');
	
	$ordersimported=0;
    foreach ($flexresponse->FlexStatements->FlexStatement->TradeConfirms->Order AS $order) {


		$mysql="SELECT * ". 
				" FROM  `trd_transactions`" .  
				" WHERE id='".$order['orderID']."'";  
		$resorders=mysql_query($mysql);
// DONE:: if exist then compare values for any updates.. bprice, bqty, bcomm and if so update and reprocess
		if ($transactions=mysql_fetch_array($resorders)) {
			if ($transactions['quantity']<>$order['quantity'] or $transactions['commission']<>$order['commission']) { //partially fill came in and was processed
				// partially open position ok, TODO:: partially close not (need to undo previous transaction)
				$mysql="DELETE ". 
						" FROM  `trd_transactions`" .  
						" WHERE id='".$order['orderID']."'";  
				if (!mysql_query($mysql)) mySqlError($mysql);
			}
		}

		$mdate = explode(';', $order['dateTime']);
		$mydate = date("Y-m-d H:i:s",strtotime($mdate[0]." ".$mdate[1]));

		$mysql="SELECT * ". 
				" FROM  `trd_transactions`" .  
				" WHERE id='".$order['orderID']."'";  
		$resorders=mysql_query($mysql);
		if (!mysql_fetch_array($resorders)) {
			$mysql= 'INSERT INTO `trd_transactions` (
					`clientaccountid` ,
					`assetclass` ,
					`symbol` ,
					`description` ,
					`id` ,
					`date_time` ,
					`buy_sell` ,
					`quantity` ,
					`multiplier` ,
					`price` ,
					`amount` ,
					`proceeds` ,
					`commission` ,
					`commissioncurrency` ,
					`ordertype` ,
					`currencyprimary` ,
					`tax` ,
					`underlyingsymbol` ,
					`strike` ,
					`put_call` ,
					`expiry` ,
					`processed`
					)
					VALUES (' .
	   				'"'.$order['accountId'].'",'.
					'"'.$order['assetCategory'].'",'.
					'"'.str_replace(" ", "",$order['symbol']).'",'.
	   				'"'.$order['description'].'",'.
					'"'.$order['orderID'].'",'.
					'"'.$mydate.'",'.
					'"'.$order['buySell'].'",'.
					'"'.$order['quantity'].'",'.
					'"'.$order['multiplier'].'",'.
					'"'.$order['price'].'",'.
					'"'.$order['amount'].'",'.
					'"'.$order['proceeds'].'",'.
	   				'"'.$order['commission'].'",'.
					'"'.$order['commissionCurrency'].'",'.
					'"'.$order['orderType'].'",'.
					'"'.$order['currency'].'",'.
					'"'.$order['tax'].'",'.
					'"'.$order['underlyingSymbol'].'",'.
					'"'.$order['strike'].'",'.
	   				'"'.$order['putCall'].'",'.
	   				'"'.$order['expiry'].'",'.
					'"'.'0'.'")';
			if (!mysql_query($mysql)) mySqlError($mysql);	
			$ordersimported++;
		}
	}
	return $ordersimported;
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
				echo "Case 4/5; Open Pos Found, Add to Existing Position</br>";
				$ord= new Orders("OPEN");
				if ($order=$ord->Find($position['id'],$transactions['id'])) { 	// Same order just update values instead of averageing
					echo "Case 5; Found existing order, Update Order</br>";
					$order['orderid']=$transactions['id'];						// TODO:: Partial allocated order should be offset (now can find total quantity of order)
					$order['date']=$transactions['date_time'];
					$order['qty']=$ext_qty;
					$order['price']=$transactions['price'];
					$order['comm']=$transactions['commission'];
					$ord->Update($order);
				} else {														// New order to current position
					Echo "Case 5; No existing order found, create new order for same position</br>";
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
				echo "Case 1/2/3; Open Position found</br>";
				switch (TRUE) {
					case (abs($position['qty'])<=abs($ext_qty)):		// 1) open=close -> close all
						echo "Case 1</br>";
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
						echo "Case 2;Current Open > Closing; Partial Close</br>";
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
				Echo "Case New; No Open Found, Create New</br>";
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
	$mysql='UPDATE trd_pos_close AS c left JOIN trd_positions AS a ON a.id = c.posid SET stotal = sqty*sprice+scomm WHERE status IN ("ACT", "CLD")';
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

function AlertsTR(){

	$mysql="SELECT * ". 
			" FROM  `trd_alerts_dd` WHERE active=1" ;
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

function AlertsActionsTR(){


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
