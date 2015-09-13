<?php
  // [
  //   {action: update, id: 1, updates: [{id: 1, value: 23}, {id: 2, value: ON},
  //   {action: update-group, id: 1, updates: [{id: 1, value: 23}, {id: 2, value: ON},
  //   {action: add, type: [lutron, zwave, zigbee, kidde]}
  //   {action: remove, id: 2}
  //   {action: set_name, id: 2, name: "foo"}
  // ]

  include '../db.php';
  include '../defines.php';
  header("Content-Type: application/json");

//  echo file_get_contents('php://input');
  $command = json_decode(file_get_contents('php://input'));

//   print_r($command);

  if (!isset($command)) {
    http_response_code(400);
    return;
  }

  $responses = array();

  switch ($command->commandid) 
  {
  case COMMAND_ON:
  case COMMAND_OFF:
	$value = ($command->commandid == COMMAND_ON ? "TRUE" : "FALSE");
	$attr = ' -t ' . escapeshellarg($command->attributeid) . ' -v ' . escapeshellarg($value);
	exec('aprontest -u -m ' . escapeshellarg($command->masterid) . $attr);
	$responses[] = ['status' => 'ok', 'exec' => 'aprontest -u -m ' . escapeshellarg($command->masterid) . $attr];
	break;
  case COMMAND_GET_VALUE:
        $db = get_db();
        $db->busyTimeout(5000);

	$query = <<<SQL
SELECT m.deviceid AS unit, m.username AS deviceName, d.nodeId
	FROM masterDevice AS m 
	LEFT JOIN zwaveDevice AS d ON d.masterId = m.deviceId 
	WHERE m.deviceId = '$command->masterid';
SQL;

        $devices_result = $db->query($query);
        $devices = array();
        while($device = $devices_result->fetchArray(SQLITE3_ASSOC)) {
           $devices[$command->masterid] = $device;
        }

	foreach ($devices as $key => $device) {

	$query = 'SELECT a.attributeId, a.description as attributeName, s.value_get, s.value_set 
	FROM zwaveDeviceState AS s 
	LEFT JOIN zwaveAttribute AS a ON s.attributeId = a.attributeID 
	WHERE s.nodeId = '.$device['nodeId'].';';

		$attr_result = $db->query($query);
	        $attrs = array();
        	while($attr = $attr_result->fetchArray(SQLITE3_ASSOC)) {
		   $id = $attr['attributeId'];
		   unset($attr['attributeId']);
	           $attrs[$id] = $attr;
        	}
		$devices[$key]['attributes'] = $attrs;
	}

        $row['devices'] = $devices;
        $responses[] = $row;
        $db->close();

//print_r($responses);
	break;
  }

  echo json_encode($responses);
?>
