<?php
class Position
{
    public function Add($position) {
		global $mysql_link;
		//	ticker	name 	status 	buy_sell 	type 	entry_strategy 	
		//	stop 	target 	trend 	source 	exit_strategy 	win_ratio 	pot_loss 	next_earning_date
		echo "Open Position: ".$position['ticker']."<br/>\r\n";
    
		$mysql="INSERT INTO `trd_positions` (".
    		"ticker, ".
    		"name, ".
    		"status, ".
    		"buy_sell, ".
    		"type,".
    		"account )".
			"VALUES (".
    			"'".$position['ticker']."', ".
    			"'".$position['name']."', ".
    			"'".$position['status']."', ".
    			"'".$position['buy_sell']."', ".
    			"'".$position['type']."', ".
    			"'"."IBB"."') ";
		if (!mysql_query($mysql)) mySqlError($mysql);
		$newid=mysql_insert_id($mysql_link);

		$mysql="INSERT INTO `trd_pos_performance` (posid) " .
				"VALUES ('".$newid."' ) ";
		if (!mysql_query($mysql)) mySqlError($mysql);
		$mysql="INSERT INTO `trd_pos_links` (posid) " .
				"VALUES ('".$newid."' ) ";
		if (!mysql_query($mysql)) mySqlError($mysql);
		$mysql="INSERT INTO `trd_pos_strategy` (posid) " .
				"VALUES ('".$newid."' ) ";
		if (!mysql_query($mysql)) mySqlError($mysql);
		
		return $newid;
    }

	public function Update($position) {

		global $mysql_link;
		//	ticker	name 	status 	buy_sell 	type 	entry_strategy 	
		//	stop 	target 	trend 	source 	exit_strategy 	win_ratio 	pot_loss 	next_earning_date
		echo "Update Position: ".$position['ticker']."<br/>\r\n";
	//	UPDATE `homeautomation`.`trd_positions` SET `name` = 'name' WHERE `trd_positions`.`id` =726;

    	$mysql="UPDATE `trd_positions` ".
			"SET ".
    		"ticker ='".$position['ticker']."', ".
    		"name ='".$position['name']."', ".
    		"status ='".$position['status']."', ".
    		"buy_sell ='".$position['buy_sell']."', ".
    		"type ='".$position['type']."',".
    		"account ='"."IBB"."' ".
			"WHERE id = '".$position['id']."'";

		if (!mysql_query($mysql)) mySqlError($mysql);
		
		$newid=mysql_insert_id($mysql_link);

		$mysql="INSERT INTO `trd_pos_performance` (posid) " .
				"VALUES ('".$newid."' ) ";
		
		if (!mysql_query($mysql)) mySqlError($mysql);
		
		$mysql="INSERT INTO `trd_pos_performance` (posid) " .
				"VALUES ('".$newid."' ) ";
		
		if (!mysql_query($mysql)) mySqlError($mysql);

		return $newid;
    }
	
    public function Close($id) {
        echo "Closing: ".$id." <br/>\r\n";
        $mysql="UPDATE `trd_positions` " . 
		        "SET ". 
			    	"status='"."CLD"."' ".
				"WHERE id='".$id."' and status='ACT'";
		if (!mysql_query($mysql)) mySqlError($mysql);
    }

    public function Find($ticker) {
        echo "Finding Position: ".$ticker."<br/>\r\n";
        $ticker=str_replace(" ", "",$ticker);  // remove spaces FROM  ticker for OPT

		$mysql="SELECT * FROM  `trd_positions` ".
				"WHERE ticker='".$ticker."' and status IN ('ACT','PRE')";
	//echo $mysql;
	//		if ($position=FetchRow("trd_positions", $mysql)) {
		if ($position=FetchRow($mysql)) {
			$this->position=$position;
			if ($my_array = $this->GetValue(($this->position['status']=='ACT' || $this->position['status']=='PRE' ? "OPEN" : "CLOSE"),$this->position['id'])) {
				//$my_array = array_merge($my_array1, $my_array2);
				$this->position=array_merge($this->position, $my_array);
			}
			return $this->position;
		} else {
			$this->position=false;
			return false;
		}
	}

	public function Copy($ticker, $close_qty) {
		$position=self::Find($ticker);
		$newid=self::Add($position);
		// copy notes
		CopyRow("trd_pos_notes","posid='".$position['id']."'",$newid);
		// copy checklist
		CopyRow("trd_pos_checklist","posid='".$position['id']."'",$newid);
		// Leave Errors
		//
		// copy borders
    	$ord= new Orders("OPEN");
    	$ord->posid=$position['id'];
		while (abs($close_qty)>0 AND $order=$ord->FindNext()) {
			switch (TRUE) {
	   			case (abs($order['qty'])==abs($close_qty)):		// 1) open=close -> Move Whole Order to new one
	   				$order['posid']=$newid;
	    			$ord->Update($order);
	    			$close_qty=0;
	    			//("trd_pos_open","posid='".$position['id']."'",$newid, $close_qty);
       				break;
	    		case (abs($order['qty'])>abs($close_qty)):		// 2) order more than close -> close part
	   				$order_copy=$order;
	   				$order_copy['posid']=$newid;
	   				if ($order['qty']>0) {
	   					$order['qty']=$order['qty']+$close_qty;
	   					$order_copy['qty']=-$close_qty;		// Long
	   				} else {
	   					$order['qty']=-1*(abs($order['qty'])-$close_qty);
	   					$order_copy['qty']=-$close_qty;		// Short
	   				}
					$order['comm']=0;
	   				$ord->Add($order_copy);
	    			$ord->Update($order);
       				break;
	    		case (abs($order['qty'])<abs($close_qty)):			// 3) open less than close -> Move Whole Order
	   				$order['posid']=$newid;
	    			$ord->Update($order);
	   				$close_qty-=abs($order['qty']);
       				break;
			}
    	}
		// copy sorders
		// CopyRow("trd_pos_close","posid='".$position['id']."'",$newid);
		// copy trd_pos_performance
		CopyRow("trd_pos_performance","posid='".$position['id']."'",$newid);
		CopyRow("trd_pos_strategy","posid='".$position['id']."'",$newid);
		return $newid; 
	}	

    private function GetValue($oc,$posid) {
    	$ord= new Orders($oc);
    	$ord->posid=$posid;
    	$result = array();
    	$result['total']=0;
    	$result['avgprice']=0;
    	$result['qty']=0;
    	$result['comm']=0;
    	$result['date']='';
    	while ($order=$ord->FindNext()) {
    		$result['total']+=$order['qty']*$order['price']+$order['comm'];
    		$result['qty']+=$order['qty'];
    		$result['comm']+=$order['comm'];
    	}
    	if ($result['total']!=0) {
    		$result['avgprice']=$result['total']/$result['qty'];
    	}
    	$ord=NULL;
    	return $result;
    }
}
?>