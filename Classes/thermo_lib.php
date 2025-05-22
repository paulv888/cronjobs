<?php

/*
*
* API Class to connect to Radio Thermostat
*
*/

class Thermostat_Exception extends Exception
{
}


class Stat
{

	protected $ch,
						$IP;	// Most likley an URL and port number rather than a strict set of TCP/IP octets.

	// Would prefer these to be private/protected and have get() type functions to return value.
	// But for now, public will do because I'm lazy.
	public $temp =	null,
				 $tmode =	null,
				 $fmode =	null,
				 $override =	null,
				 $hold =	null,
				 $t_heat =	null,
				 $t_cool =	null,
				 $tstate =	null,
				 $isrunning =	null,
				 $fstate =	null,
				 $day =	null,
				 $time =	null,
				 $t_type_post =	null,
				 $humidity = null,
				 $away_heat = null,
				 $here_heat = null,
				 $away_cool = null,
				 $here_cool = null,
				 $setpoint = null;
				 
	public $runTimeCool = null,
				 $runTimeHeat = null,
				 $runTimeCoolYesterday = null,
				 $runTimeHeatYesterday = null;
	//
	public $errStatus = null;
	//
	public $model = null;

	// System vars
	public $uuid = null,
				 $api_version = null,
				 $fw_version = null,
				 $wlan_fw_version = null,
				 $ssid = null,
				 $bssid = null,
				 $channel = null,
				 $security = null,
				 $passphrase = null,
				 $ipaddr = null,
				 $ipmask = null,
				 $ipgw = null,
				 $rssi = null;

	public function __construct( $device )
	{
		$this->URL = $device['connection']['targetaddress'];
		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_USERAGENT, 'A' );
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, 1 );

		$this->debug = isset($GLOBALS['debug']);

		// Stat variables initialization
		$this->temp = 0;
		$this->tmode = $device['type']['internal_type'];
		$this->fmode = 0;
		$this->override = 0;
		$this->hold = 0;
		$this->t_cool = 0;
		$this->t_heat = 0;
		$this->tstate = 0;
		$this->fstate = 0;
		$isrunning = 0;
		$this->day = 0;
		$this->time = 0;
		$this->t_type_post = 0;
		$this->humidity = -1;
		//
		$this->runTimeCool = 0;
		$this->runTimeHeat = 0;
		$this->runTimeCoolYesterday = 0;
		$this->runTimeHeatYesterday = 0;
		//
		$this->errStatus = 0;
		//
		$this->model = 0;

		// System variables
		$this->uuid = 0;
		$this->api_version = 0;
		$this->fw_version = 0;
		$this->wlan_fw_version = 0;
		$this->ssid = 0;
		$this->bssid = 0;
		$this->channel = 0;
		$this->security = 0;
		$this->passphrase = 0;
		$this->IPaddr = 0;
		$this->IPmask = 0;
		$this->IPgw = 0;
		$this->rssi = 0;

		$this->away_heat = to_fahrenheit($device['thermostat']['away_heat_temp_c']);
		$this->here_heat = to_fahrenheit($device['thermostat']['here_temp_heat_c']);
		$this->away_cool = to_fahrenheit($device['thermostat']['away_cool_temp_c']);
		$this->here_cool = to_fahrenheit($device['thermostat']['here_temp_cool_c']);

		// Cloud variables


	}

	public function __destruct()
	{
		curl_close( $this->ch );
	}

	protected function getStatData( $cmd )
	{
		$commandURL = $this->URL . $cmd;

		curl_setopt( $this->ch, CURLOPT_URL, $commandURL );
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, THERMO_CONNECTION_TIMEOUT);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, THERMO_CONNECTION_TIMEOUT);
		if( $this->debug )
		{
			debug ($commandURL, '$commandURL');
		}

		$outputs = curl_exec( $this->ch );
		if (curl_errno ( $this->ch )<>0) throw new Thermostat_Exception( 'getStatData: ' . curl_error($this->ch) );

		if( $this->debug )
		{
			debug ($outputs, 'Thermstat outputs');
		}

		/** Build in one second sleep after each command
			* based on code from phareous - he had 2 second delay here and there
			* The thermostat will stop responding for 20 to 30 minutes (until next WiFi reset) if you overload the connection.
			*
			* Previously I was not using a delay and had not problems.
			*
			* Later on in a many thermostat environment each stat will need to be queries in a thread so that the delays don't
			*	stack up and slow it to a stop.
			*/
		// PV Removed sleep( 1 );

		return $outputs;
	}

	protected function setStatData( $command, $value )
	{
		$commandURL = $this->URL . $command;

		curl_setopt($this->ch, CURLOPT_URL,$commandURL);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($value));
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, THERMO_CONNECTION_TIMEOUT);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, THERMO_CONNECTION_TIMEOUT);

		if( $this->debug)
		{
			debug ($commandURL, 'commandURL');
		}

		if( !$outputs = curl_exec( $this->ch ) )
		{
			throw new Thermostat_Exception( 'setStatData: ' . curl_error($this->ch) );
		}


		// Need to wait for a response...	object(stdClass)#4 (1) { ['success']=> int(0) }
		return $outputs;
	}

	protected function containsTransient( $obj )
	{
		$retval = false;
		foreach ( $obj as $key => &$value )
		{
			if( is_object($value) )
			{
				foreach( $value as $key2 => &$value2 )
				{
					if( $value2 == -1 )
					{
						//if( $this->debug )
						echo 'WARNING (' . date(DATE_RFC822) . '): ' . $key2 . " contained a transient\n";
						// NULL the -1 transient
						//$value2 = NULL;
						$retval = true;
					}
				}
			} elseif( $value == -1 )
				{
				//:if( $this->debug )
				echo 'WARNING (' . date(DATE_RFC822) . '): ' . $key . " contained a transient\n";
				// NULL the -1 transient
				//$value = NULL;
				$retval = true;
				}
		}
		return $retval;
	}

	public function showMe()
	{
		// For now hard coded HTML <br> but later let CSS do that work
		echo '<br><br>Thermostat data (Yaay!	I found the introspection API - hard coding SUCKS)';
		echo '<table id="stat_data">';
		echo '<tr><th>Setting</th><th>Value</th><th>Explanation</th></tr>';

		$rc = new ReflectionClass('Stat');
		$prs = $rc->getProperties();
		$i = 0;
		foreach( $prs as $pr )
		{
			if( $i == 0 )
			{
				$i = 1;
				echo '<tr>';
			}
			else
			{
				$i = 0;
				echo '<tr class="alt">';
			}
			$key = $pr->getName();
			$val = $this->{$pr->getName()};
			if( $key == 'ZIP' || $key == 'ssid' )
			{
// Once we have password protected pages, allow these to be shown?
				$val = 'MASKED';
			}
			echo '<td>' . $key . '</td><td>' . $val . '</td></tr>';
		}
	}

	public function getStat()
	{
		/* Query thermostat for data and check the query for transients.
			 If there are transients repeat query up to 5 times for collecting good data
			 Continue when successful
		*/
		for ($i=1; $i<=5; $i++)
		{
			$outputs = $this->getStatData( '/tstat' );
			// {"temp":80.50,"tmode":2,"fmode":0,"override":0,"hold":0,"t_cool":80.00,"tstate":2,"fstate":1,"time":{"day":2,"hour":18,"minute":36},"t_type_post":0}
			$obj = json_decode( $outputs );

			if( !$this->containsTransient( $obj ) ) {
				break;
			} else {
				if( $i == 5 )
				{
					throw new Thermostat_Exception( 'Too many thermostat transient failures' );
				}
				else
				{
					echo "Transient (" . date(DATE_RFC822) . ") failure " . $i . " retrying...\n";
				}
			}

			if( empty( $obj ) ) {
				throw new Thermostat_Exception( 'No output from thermostat' );
			}
		}

		// Move fetched data to internal data structure
		$this->temp = $obj->{'temp'};						 // Present temp in deg F (or C depending on thermostat setting)
		$this->tmode = $obj->{'tmode'};					 	// Present t-stat mode
		$this->fmode = $obj->{'fmode'};					 	// Present fan mode
		$this->override = $obj->{'override'};		 		// Present override status 0 = off, 1 = on
		$this->hold = $obj->{'hold'};						 // Present hold status 0 = off, 1 = on

		if( $this->tmode == 1 )	{ // mode 1 is heat
			$this->t_heat = $obj->{'t_heat'};			 // Present heat target temperature in deg F
			$this->setpoint = $this->t_heat;
		} else {
			if( $this->tmode == 2 )	{ // mode 2 is cool
				$this->t_cool = $obj->{'t_cool'};			 // Present cool target temperature in deg F
				$this->setpoint = $this->t_cool;
			}
		}
		
		$this->tstate = $obj->{'tstate'};				 // Present heater/compressor state 0 = off, 1 = heating, 2 = cooling
		$this->fstate = $obj->{'fstate'};				 // Present fan state 0 = off, 1 = on
		$this->isrunning = ($this->tstate > 0 ? 1 : 0);
		
		$var1 = $obj->{'time'};									 // Present time
		$this->day = $var1->{'day'};
		$this->time = sprintf(' %2d:%02d',($var1->{'hour'}), $var1->{'minute'} );

		$this->t_type_post = $obj->{'t_type_post'};

		return;
	}

	public function getDataLog()
	{
		$outputs = $this->getStatData( '/tstat/datalog' );
		$obj = json_decode( $outputs );

		$var1 = $obj->{'today'};
		$var2 = $var1->{'heat_runtime'};
		$this->runTimeHeat = ($var2->{'hour'} * 60) + $var2->{'minute'};

		$var2 = $var1->{'cool_runtime'};
		$this->runTimeCool = ($var2->{'hour'} * 60) + $var2->{'minute'};

		$var1 = $obj->{'yesterday'};
		$var2 = $var1->{'heat_runtime'};
		$this->runTimeHeatYesterday = ($var2->{'hour'} * 60) + $var2->{'minute'};

		$var2 = $var1->{'cool_runtime'};
		$this->runTimeCoolYesterday = ($var2->{'hour'} * 60) + $var2->{'minute'};

		return;
	}

	public function getErrors()
	{
		$outputs = $this->getStatData( '/tstat/errstatus' );
		$obj = json_decode( $outputs );
		$this->errStatus = $obj->{'errstatus'};

		return;
	}

	public function getEventLog()
	{
$this->debug = true;
		$outputs = $this->getStatData( '/tstat/eventlog' );
		$obj = json_decode( $outputs );
		$var1 = $obj->{'eventlog'};
$this->debug = false;
		throw new Thermostat_Exception( 'getEventLog() - Not implemented' );

		return;
	}

	public function getFMode()
	{
		$outputs = $this->getStatData( '/tstat/fmode' );
		$obj = json_decode( $outputs );
		$this->fmode = $obj->{'fmode'};					 // Present fan mode

		return;
	}

	public function getHelp()
	{
$this->debug = true;
		$outputs = $this->getStatData( '/tstat/help' );
		$obj = json_decode( $outputs );
$this->debug = false;
		throw new Thermostat_Exception( 'getHelp() - Not implemented' );

		return;
	}

	public function getHold()
	{
		$outputs = $this->getStatData( '/tstat/hold' );
		$obj = json_decode( $outputs );
		$this->hold = $obj->{'hold'};						 // Present hold status 0 = off, 1 = on

		return;
	}

	public function getHumidity()
	{
		$outputs = $this->getStatData( '/tstat/humidity' );	// {"humidity":-1.00} This is example of no sensor.
		$obj = json_decode( $outputs );
		$this->humidity = $obj->{'humidity'};								// Present humidity

		return;
	}

	public function setLED()
	{
		throw new Thermostat_Exception( 'setLED() - Not implemented' );	// Prevent problems for now

		$outputs = $this->getStatData( '/tstat/led' );
		$obj = json_decode( $outputs );

		return;
	}

	public function getModel()
	{
		$outputs = $this->getStatData( '/tstat/model' );	// {"model":"CT50 V1.09"}
		$obj = json_decode( $outputs );
		$this->model = $obj->{'model'};

		return;
	}


	public function getSysName()
	{
		$outputs = $this->getStatData( '/sys/name' ); // {"name":"Home"}
		$obj = json_decode( $outputs );
		$this->sysName = $obj->{'name'};

		return;
	}


	public function getOverride()
	{
		$outputs = $this->getStatData( '/tstat/override' );
		$obj = json_decode( $outputs );

		$this->override = $obj->{'override'};
		return;
	}

	public function getPower()
	{
		$outputs = $this->getStatData( '/tstat/power' );
		$obj = json_decode( $outputs );

		$this->power = $obj->{'power'};	// Milliamps?
		return;
	}

	public function setBeep()
	{
		throw new Thermostat_Exception( 'setBeep() - Not implemented' );	// Prevent problems for now

		$outputs = $this->getStatData( '/tstat/beep' );
		$obj = json_decode( $outputs );

		return;
	}

	public function setUMA()
	{
		throw new Thermostat_Exception( 'setUMA() - Not implemented' );	// Prevent problems for now

		$outputs = $this->getStatData( '/tstat/uma' );
		$obj = json_decode( $outputs );

		return;
	}

	public function setPMA()
	{
		throw new Thermostat_Exception( 'setPMA() - Not implemented' );	// Prevent problems for now

		$outputs = $this->getStatData( '/tstat/pma' );
		$obj = json_decode( $outputs );

		return;
	}

	public function setTemp($setpoint)
	{

		$setpoint=(float)$setpoint;

		if ($this->tmode == 1) {
			if ($setpoint > 78) return array('error'=> 'Temp greater than 24C/78F limit'); 							// Set protection limit, for voice misinterpretation
			$value = array ('t_heat'=>$setpoint);
		} else {
			if ($setpoint < 64) return array('error'=> 'Temp less than 18C/64F limit');    							// Set protection limit, for voice misinterpretation
			$value = array ('t_cool'=>$setpoint);
		}

		if ($this->tmode == 0) return 32;

		$cmd = '/tstat';
		$outputs = $this->setStatData( $cmd, $value );
		if( $this->debug)
		{
			debug($outputs, 'Thermostat outputs' );
		}
		$result = json_decode( $outputs, TRUE );

		if (array_key_exists("success", $result)) {
			$this->setpoint=$setpoint;
			return array('result'=>$result);
		} else
			return array('error'=>$result);
	}

	// Interpret current status based on cool/heat mode and current target temp
	public function getTargetOnOff()
	{

		if ($this->tmode == 0) return STATUS_OFF;
		$this->getTTemp();

		if ($this->tmode == 1) {											// Heating Mode
		//            19      >=     21 - 2   (if target > 20) then on
			if ($this->t_heat >= $this->here_heat - 2) { 					// Target is set for Here
				return STATUS_ON;										 	// On
		//            16.5      <=    15.5 + 2  (if target < 16.5) then off
			} elseif ($this->t_heat <= $this->away_heat + 2) {	 			// Target is set for Away
				return STATUS_OFF;										 	// Off
			} else {														// Target is set somewhere in the middle, not sure what to do switch OFF
		//              target between 16.5 & .19
				return STATUS_UNKNOWN;										// IDK
			}
		} else {															// Cooling mode
//			echo "this->t_cool".$this->t_cool."</br>";
//			echo "this->here_cool".$this->here_cool."</br>";
//			echo "this->away_cool".$this->away_cool."</br>";
		//              25.5    <=     24.5 +  2     (if target < 25.5) then on
			if ($this->t_cool <= $this->here_cool + 2) { 					// Target is set for Here
				return STATUS_ON;										 	// On
		//              28.5    >=     29.5 +  2     (if target > 28.5) then off
			} elseif ($this->t_cool >= $this->away_cool - 2) { 				// Target is set for Away
				return STATUS_OFF;										 	// OFF
			} else {														// Target is set somewhere in the middle, not sure what to do switch OFF
		//              target between 25.5 & 28.5
				return STATUS_UNKNOWN;										 	// IDK
			}
		}
	}

	public function TempAdd($addTemp)
	{
		if ($this->tmode == 0) return 32;
		if ($this->tmode == 1) {											// Heating Mode
			$setpoint = $this->t_heat + $addTemp;							 	// Toggle Off
		} else {
			$setpoint= $this->t_cool + $addTemp;								// Toggle Off
		}

		return $this->setTemp($setpoint);
	}
	
	// Getting current status from device /tstat has to be called before
	// verify current status with antoher call to /tstat
	// if successfull return status (ttemp???)
	public function Toggle($commandID)
	{


		//if ($status === NULL) $status = $this->getTargetOnOff();			// Read target from Thermostat
		
//		echo "stat:".$status."</br>";
		if ($this->tmode == 0) return STATUS_OFF;

		if ($commandID == COMMAND_OFF) { 								// Target is set for Here
			if ($this->tmode == 1) {									// Heating Mode
				$setpoint = $this->away_heat;							 	// Toggle Off
				$status = STATUS_OFF;
			} else {												
				$setpoint = $this->away_cool;								// Toggle Off
				$status = STATUS_OFF;
			}
		} elseif ($commandID == COMMAND_ON) {	 						// Target is set for Away
			if ($this->tmode == 1) {									// Heating Mode
				$setpoint = $this->here_heat;								// Toggle On
				$status = STATUS_ON;
			} else {													// Target is set somewhere in the middle, not sure what to do switch OFF
				$setpoint = $this->here_cool;								// Toggle On
				$status = STATUS_ON;
			}
		} else {
			if ($this->tmode == 1) {									// Heating Mode
				$setpoint = $this->away_heat;							 	// Toggle Off
				$status = STATUS_OFF;
			} else {													// Target is set somewhere in the middle, not sure what to do switch OFF
				$setpoint=$this->away_cool;								// Toggle Off
				$status = STATUS_OFF;
			}
		}
//		echo "ttemp:".$setpoint."</br>";

		$this->setTemp($setpoint);

		return $status;
	}

	public function getTemp()
	{
		$outputs = $this->getStatData( '/tstat/temp' );
		$obj = json_decode( $outputs );
		$this->temp = $obj->{'temp'};						 // Present temp in deg F (or C?)

		return;
	}

	public function getTTemp()
	{
		$outputs = $this->getStatData( '/tstat/ttemp' );
		$obj = json_decode( $outputs );
		$this->t_heat = $obj->{'t_heat'};						 // Heat setpoint
		$this->t_cool = $obj->{'t_cool'};						 // Heat setpoint

		return;
	}

	public function getTimeDay()
	{
		$outputs = $this->getStatData( '/tstat/time/day' );
		$obj = json_decode( $outputs );

		return;
	}

	public function getTimeHour()
	{
		$outputs = $this->getStatData( '/tstat/time/hour' );
		$obj = json_decode( $outputs );

		return;
	}

	public function getTimeMinute()
	{
		$outputs = $this->getStatData( '/tstat/time/minute' );
		$obj = json_decode( $outputs );

		return;
	}

	public function getTime()
	{
		$outputs = $this->getStatData( '/tstat/time' );
		$obj = json_decode( $outputs );

		return;
	}

	// Hard coded to Tuesday for now
	public function setTimeDay()
	{
		throw new Thermostat_Exception( 'setTimeDay() - Not implemented' );	// Prevent problems for now

		$cmd = '/tstat/time/day';
		$value = '{\'day\':2}';	 // 2 = Tuesday
		$outputs = $this->setStatData( $cmd, $value );

		echo var_dump( json_decode( $outputs ) );
		return;
	}

	public function getSysInfo()
	{
		$outputs = $this->getStatData( '/sys' );	// '/sys/info' No longer works
		// {"uuid":"xxxxxxxxxxxx","api_version":113,"fw_version":"1.04.84","wlan_fw_version":"v10.105576"}
		$obj = json_decode( $outputs );

		$this->uuid = $obj->{'uuid'};
		$this->api_version = $obj->{'api_version'};
		$this->fw_version = $obj->{'fw_version'};
		$this->wlan_fw_version = $obj->{'wlan_fw_version'};

		return;
	}

	public function getSysNetwork()
	{
		$outputs = $this->getStatData( '/sys/network' );
		$obj = json_decode( $outputs );

		$this->ssid = $obj->{'ssid'};
		$this->bssid = $obj->{'bssid'};
		$this->channel = $obj->{'channel'};
		$this->security = $obj->{'security'};
		$this->passphrase = $obj->{'passphrase'};
		$this->IPaddr = $obj->{'ipaddr'};
		$this->IPmask = $obj->{'ipmask'};
		$this->IPgw = $obj->{'ipgw'};
		$this->rssi = $obj->{'rssi'};

		return;
	}

	public function getSysCloud()
	{
		$outputs = $this->getStatData( '/sys/cloud' );
		$obj = json_decode( $outputs );

		$this->ssid = $obj->{'ssid'};
		$this->bssid = $obj->{'bssid'};
		$this->channel = $obj->{'channel'};
		$this->security = $obj->{'security'};
		$this->passphrase = $obj->{'passphrase'};
		$this->IPaddr = $obj->{'ipaddr'};
		$this->IPmask = $obj->{'ipmask'};
		$this->IPgw = $obj->{'ipgw'};
		$this->rssi = $obj->{'rssi'};

		return;
	}

}

?>
