<?php
class InsteonHub
{
protected $socket;
protected $host;
protected $persist;
protected $debugHandler;
protected $transport;
protected $inst_coder;
protected $messages;
protected $incompl_messages;
public static $defaultDebug=false;
public $debug;
protected $last;

/**
 *
 */
	public function __construct($host,$port,$persist=false,$debugHandler=null)
	{
		$this->debug = self::$defaultDebug;
		$this->debugHandler = $debugHandler ? $debugHandler : 'error_log';
	
		
		$this->transport = new SocketTransport(array($host),$port);

		//$this->transport->setRecvTimeout(900000); // 15 min
		//$this->transport->setSendTimeout(30000);

		if (DEBUG_INSTEON) $this->transport->debug = true;
		
		$this->inst_coder = new InsteonCoder();
		
		$this->messages = new SplQueue();
		$this->incompl_messages = new SplQueue();
		
		$this->last = strtotime(date("Y-m-d H:i:s"));
		
		$return=$this->transport->open() && $this->transport->write(hex2bin("0273"));
		
	}

	public function getMessage(){

		$short_message = 0;
		$result = "";
		while ($this->messages->count()<1) {
	
			$result.= bin2hex($this->transport->readAll());
			while ($result) {
				$plm_decode_result = $this->inst_coder->plm_decode($result);
				// check for to short for PLM message, if so save result for rest
				if (!array_key_exists("extdata", $plm_decode_result)) $plm_decode_result['extdata'] = Null;
				switch ($plm_decode_result['extdata'])
				{
					case ERROR_MESSAGE_TO_SHORT:  		// leave result and wait for more
						echo "ERROR_MESSAGE_TO_SHORT"." Waiting for more"."\n";
						if ($short_message++ > 2) {
							$result = "";
							$short_message = 0;
						}
						break;
					case ERROR_STX_MISSING:				// not handled yet. 
						echo "ERROR_STX_MISSING"." Not storing"."\n";
						echo date("Y-m-d H:i:s")."plm_decode_result:\n";
						print_r($plm_decode_result);
						$result = "";								// Clear result padding
						break;
					default:
						if (DEBUG_INSTEON) echo date("Y-m-d H:i:s")." +++plm_decode_result\n";
						if (DEBUG_INSTEON) print_r($plm_decode_result);
						if (DEBUG_INSTEON) echo date("Y-m-d H:i:s")." ===end plm_decode_result\n";
						$this->addMessage($plm_decode_result);
						if ($plm_decode_result['length'] > 0) {		// This was a readable message
							$result = substr($result,$plm_decode_result['length']);	
						} else { 									// non recognize code, throw all away (or mayby try next 4?
							$result = "";
						}
						break;
				}
			} 
		}
		return $this->messages->dequeue();
	}
	
	private function addMessage($plm_decode_result) {
		if (array_key_exists("x10",$plm_decode_result)) {		// handle x10 message
			$x10 = $plm_decode_result['x10'];
			if (!array_key_exists("commandID",$x10)) {   		// Enqueue incomplete messages and exit
  				//Complete messages and push 
				$incompl ['code'] = $x10['code'];
				$incompl ['unit'] = $x10['unit'];
				$incompl ['plmcmdID'] = $plm_decode_result['plmcmdID'];
				$incompl ['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
				$this->incompl_messages->enqueue($incompl); 
				return ;								
			} else {													// Complete messages and push				
				unset ($newincompl);
				foreach ($this->incompl_messages as $incompl) {			// Handle many addresses with 1 command
					if ($incompl['code'] == $x10['code'] && ($incompl['plmcmdID'] == $plm_decode_result['plmcmdID'] || $x10['commandID'] == COMMAND_STATUSON || $x10['commandID'] == COMMAND_STATUSOFF)) {
						$compl = $incompl;
						$compl['commandID'] = $x10['commandID'];
						$compl['inout'] = $plm_decode_result['inout'];
						$compl ['plmcmdID'] = $plm_decode_result['plmcmdID'];
						$compl ['message'] .= "\n".$plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
						$this->messages->enqueue($compl);
						//if (DEBUG_INSTEON) print_r($compl);
						if ($compl['commandID'] == 5) {			// Push extra message for status request response (does not have an Unit Code)
							$newincompl ['code'] = $compl['code'];
							$newincompl ['unit'] = $compl['unit'];
							$newincompl ['plmcmdID'] = $plm_decode_result['plmcmdID'];
							$newincompl ['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
						}
					} 			
					$this->incompl_messages->dequeue(); // dequeue , different code or handled message  
					// Should only trow old ones away... 
				}
				if (isset($newincompl)) $this->incompl_messages->enqueue($newincompl); 
				//echo "3pushing newincompl".$this->incompl_messages->count()."\n";
			}
		} else {
			$compl['inout'] = $plm_decode_result['inout'];
			$compl['code'] = "I";
			if (array_key_exists("from",$plm_decode_result)) {
				$compl['unit'] = strtoupper($plm_decode_result['from']);
			} elseif (array_key_exists("to",$plm_decode_result)) {
 				$compl['unit']  = strtoupper($plm_decode_result['to']);
			} else {
				$compl['unit'] = NULL;
			}
			if (array_key_exists("insteon",$plm_decode_result)) {
				$insteon = $plm_decode_result['insteon'];
				if (array_key_exists("data",$insteon )) $compl['data'] = $insteon['data'];
				if (array_key_exists("extdata",$insteon )) $compl['extdata'] = $insteon['extdata'];
//				$rescommands = mysql_query("SELECT * FROM ha_mf_commands_detail WHERE ha_mf_commands_detail.id =".$insteon['commandID']);
//				$rowcommands = mysql_fetch_array($rescommands);
//				if (!$rowcommands)  mySqlError($mysql);
				$compl['commandID'] = $insteon['commandID'];
			}
			$compl ['plmcmdID'] = $plm_decode_result['plmcmdID'];
			$compl ['message'] = $plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
			$this->messages->enqueue($compl);
		}
	}

	public function __destruct()
	{
		$this->transport->close();
	}
}
?>