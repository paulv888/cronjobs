<?php
include_once 'defines.php';

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

private static $inout_a = Array (
		'0250' => 2,
		'0251' => 2,
		'0252' => 2,
		'0253' => 3,
		'0254' => 2,
		'0255' => 3,
		'0256' => 3,
		'0257' => 3,
		'0258' => 3,
		'0260' => 2,
		'0261' => 1,
		'0262' => 1,
		'0263' => 1,
		'0264' => 3,
		'0265' => 3,
		'0266' => 3,
		'0267' => 3,
		'0268' => 3,
		'0269' => 3,
		'026A' => 3,
		'026B' => 3,
		'026C' => 3,
		'026D' => 3,
		'026E' => 3,
		'026F' => 3,
		'0270' => 2,
		'0271' => 2,
		'0272' => 3,
		'0273' => 3
);

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

		if (MYDEBUG) $this->transport->debug = true;
		
		$this->inst_coder = new InsteonCoder();
		
		$this->messages = new SplQueue();
		$this->incompl_messages = new SplQueue();
		
		$this->last = strtotime(date("Y-m-d H:i:s"));
		echo date("Y-m-d H:i:s").": ".UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
		
		$return=$this->transport->open() && $this->transport->write(hex2bin("0273"));
		
	}

	public function getMessage(){

		while ($this->messages->count()<1) {
		/*		try {
		 $result=$transport->readAll(100);
		}
		catch (Exception $e) {
		echo "<p>There was an error.</p>";
		echo $e->getCode();
		echo $e->getMessage();
		} */
	
			$result = $this->transport->readAll();
			if ($result) {
				$plm_decode_result = $this->inst_coder->plm_decode(bin2hex($result));
				$nowdt = strtotime(date("Y-m-d H:i:s"));
				if ((int)(abs($nowdt-$this->last) / 60)>=14) {
					$this->last = strtotime(date("Y-m-d H:i:s"));
					echo date("Y-m-d H:i:s").": ".UpdateMylink(MY_DEVICE_ID)." My Link Updated <br/>\r\n";
					$this->transport->write(hex2bin("0273"));
				}
				if (MYDEBUG) print_r($plm_decode_result);
				$this->addMessage($plm_decode_result);		
			} else {
//				$result = $this->transport->write(hex2bin("0273"));
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
			} else {											// Complete messages and push				
				foreach ($this->incompl_messages as $incompl) {
					if ($incompl['code'] == $x10['code'] && $incompl['plmcmdID'] == $plm_decode_result['plmcmdID']) {
						$compl = $incompl;
						$compl['commandID'] = $x10['commandID'];
						$compl['inout'] = self::$inout_a[$plm_decode_result['plmcmdID']];
						$compl['sourceID'] = SIGNAL_SOURCE_INSTEON;
						$compl ['message'] .= "\n".$plm_decode_result['plm_string']."\n".$plm_decode_result['plm_message'];
						$this->messages->enqueue($compl);
					} 			
					$this->incompl_messages->dequeue(); // dequeue , different code or handled message  
					// Should only trow old ones away... 
				}
			}
		}
		if (array_key_exists("insteon",$plm_decode_result)) {
			$insteon = $plm_decode_result['insteon'];
			$compl['inout'] = self::$inout_a[$plm_decode_result['plmcmdID']];
			$compl['code'] = "I";
			if (array_key_exists("from",$plm_decode_result)) $compl['unit'] = strtoupper($plm_decode_result['from']); else $compl['unit']  = strtoupper($plm_decode_result['to']);
			if (array_key_exists("data",$insteon )) $compl['data'] = $insteon['data'];
			if (array_key_exists("extdata",$insteon )) $compl['extdata'] = $insteon['extdata'];
			$compl['commandID'] = $insteon['commandID'];
			$compl['sourceID'] = SIGNAL_SOURCE_INSTEON;
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
