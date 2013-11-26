<?php
class Orders {
    // define properties
    public $order;
    public $ticker;
    public $posid;
    
    private $priv_res_order;
    private $type;
    private $orders;
    
    // constructor
    public function __construct($type) {
    	if ($type=='OPEN') {
    		$this->orders='trd_pos_open';
    	} elseif ($type=='CLOSE') {
    		$this->orders='trd_pos_close';
    	}
    	$this->type=$type;
    }
    
    public function Find($posid, $orderid) {
        echo "Finding Order: ".$posid."<br/>\r\n";
        if ($this->type=='OPEN') {
			$mysql="SELECT * FROM ".$this->orders." WHERE posid='".$posid."' AND borderid='".$orderid."'";
        } else {
			$mysql="SELECT * FROM ".$this->orders." WHERE posid='".$posid."' AND sorderid='".$orderid."'";
        }
		if ($priv_order=FetchRow($mysql)) {
			$this->order=$this->genericOrder($priv_order);
			return $this->order;
		} else {
			return false;
		}
	}

	public function FindFirst() {
        echo "Finding First Order: ".$this->posid."<br/>\r\n";
        if ($this->type=='OPEN') {
        	$mysql="SELECT * FROM  ".$this->orders." WHERE posid='".$this->posid."' ORDER BY bdate";
        } else {
        	$mysql="SELECT * FROM  ".$this->orders." WHERE posid='".$this->posid."' ORDER BY sdate";
        }
        $this->priv_res_order = mysql_query($mysql) ;
		if ($this->priv_res_order) {
			$priv_order=mysql_fetch_array($this->priv_res_order);
			if ($priv_order) {
				$this->order=$this->genericOrder($priv_order);
				return $this->order;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function FindNext() {
        echo "Finding Next Order: ".$this->posid."<br/>\r\n";

        if (!$this->priv_res_order) {
        	if ($res=$this->FindFirst()) {
					return $res;
        	} else {
				return false;
        	}
        } else {
			if ($this->priv_res_order) {
				$priv_order=mysql_fetch_array($this->priv_res_order);
				if ($priv_order) {
					$this->order=$this->genericOrder($priv_order);
					return $this->order;
				} else {
					return false;
				}
			} else {
				return false;
			}
        }
	}
	
    public function Update($order) {
        echo "Updating Order: ".$order['orderid']."<br/>\r\n";
        if ($this->type=='OPEN') {
			$mysql="UPDATE ".$this->orders." ". 
					"SET ". 
					" posid='".$order['posid']."',".
					" borderid='".$order['orderid']."',".
					" bdate='".$order['date']."',".
					" bqty='".$order['qty']."',".
					" bprice='".$order['price']."',".
					" bcomm='".$order['comm']."' ".
					"WHERE id='".$order['id']."'";
        } else {
			$mysql="UPDATE ".$this->orders." ". 
					"SET ". 
					" posid='".$order['posid']."',".
					" sorderid='".$order['orderid']."',".
					" sdate='".$order['date']."',".
					" sqty='".$order['qty']."',".
					" sprice='".$order['$price']."',".
					" scomm='".$order['comm']."' ".
					"WHERE id='".$order['$id']."'";
					}
        $result=mysql_query($mysql);
 		if (!result) mySqlError($mysql);
        
		return $result;
					
    }
	
    public function Add($order) {
    	global $mysql_link;
    	
        echo "Adding Order: ".$order['orderid']."<br/>\r\n";
        if ($this->type=='OPEN') {
			$mysql="INSERT INTO ".$this->orders." (posid, borderid, bdate, bqty, bprice, bcomm) " .
						"VALUES ('".
								$order['posid']."','".
								$order['orderid']."','".
								$order['date']."','".
								$order['qty']."','".
						 		$order['price']."','".
						 		$order['comm']."')";  
        } else {
			$mysql="INSERT INTO ".$this->orders." (posid, sorderid, sdate, sqty, sprice, scomm) " .
						"VALUES ('".
								$order['posid']."','".
								$order['orderid']."','".
								$order['date']."','".
								$order['qty']."','".
						 		$order['price']."','".
						 		$order['comm']."')";  
        }
        $result=mysql_query($mysql);
 		if (!result) mySqlError($mysql);
        
		return mysql_insert_id($mysql_link);
    }
    
 	private function genericOrder($order) {
        if ($this->type=='OPEN') {
	 		$result['id']=$order['id'];	
	 		$result['posid']=$order['posid'];	
	 		$result['orderid']=$order['borderid'];	
	 		$result['date']=$order['bdate'];	
	 		$result['qty']=$order['bqty'];	
	 		$result['price']=$order['bprice'];	
	 		$result['comm']=$order['bcomm'];
	 		$result['total']=$order['btotal'];
	 		$result['strategy']=$order['entry_strategy'];
        } else {
	 		$result['id']=$order['id'];	
	 		$result['posid']=$order['posid'];	
	 		$result['orderid']=$order['sorderid'];	
	 		$result['date']=$order['sdate'];	
	 		$result['qty']=$order['sqty'];	
	 		$result['price']=$order['sprice'];	
	 		$result['comm']=$order['scomm'];
	 		$result['total']=$order['stotal'];
	 		$result['strategy']=$order['exit_strategy'];
        }
 		
 		return $result;	
 	}

}
 	


