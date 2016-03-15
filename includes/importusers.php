<?php

// No direct access
defined('_JEXEC') or die('Restricted access');

define("TYPE_3RD_PARTY", "2");

//echo "<pre>";

//echo  $this->data['mc_import_mongoDB___purge_raw'][0];

if ($this->data['mc_import_mongoDB___purge_raw'][0]==0) {
	$db = JFactory::getDbo();
	$query = $db->getQuery(true);
	// delete all custom keys for user 1001.
	$query->delete($db->quoteName('mongoDB_users'));
	$query->where($db->quoteName('period').' = '.$db->quote(date("Y-m-d",strtotime($this->data['mc_import_mongoDB___period_raw']))));
	$db->setQuery($query);
	$result = $db->execute();
}

//print_r($this->data);
//echo $this->data['mc_import_mongoDB___period_raw'].'</br>';
//echo $this->data['mc_import_mongoDB___filename'].'</br>';
//print_r($this->data['mc_import_mongoDB___db_name_raw']);
//echo $this->data['mc_import_mongoDB___db_name_raw'][0].'</br>';

$file = $this->data['mc_import_mongoDB___filename'];
$type = $this->data['mc_import_mongoDB___file_type_raw'];
$data = file_get_contents($_SERVER['DOCUMENT_ROOT'].$file);
$data = preg_replace('~\R~u', ",", $data);
$data = '['.rtrim($data, ",").']';

//echo $data.'</br>';

$dataArray = json_decode($data,1);

//print_r($dataArray);

// Get a db connection.
$db = JFactory::getDbo();

//exit; 
 
$count = 0;

foreach ($dataArray as $key => $user) {
	unset($dataArray[$key]['hash']);
	unset($dataArray[$key]['salt']);

	if ($type[0] == TYPE_3RD_PARTY) {
		$products [0]['code']="hauler";
		unset($dataArray[$key]['custom']);
		unset($dataArray[$key]['roles']);
	} else {
		$products = $dataArray[$key]['products'];
	}
	$commerce = false;
	$commerceCustomer = "";
	$ticket = false;
	$hauler = false;
	foreach ($products as $product) {
		if ($product['code'] == 'commerce') {
			$commerce = true;
			$commerceCustomer = $product['settings']['customer']['code'];
		} elseif ($product['code'] == 'ticket') {
			$ticket = true;
		} elseif ($product['code'] == 'hauler') {
			$hauler = true;
		} elseif ($product['code'] == 'supply') {
			$supply = true;
		} elseif ($product['code'] == 'jobsite') {
			$supply = jobsite;
		} elseif ($product['code'] == '') {
			//$ticket = true;
		} else {
			JFactory::getApplication()->enqueueMessage("Unknown product code found::".$product['code'].'::');
		}
	}
	unset($dataArray[$key]['products']);
//	echo "$key $user".'</br>';
//	print_r($dataArray[$key]);
	foreach($dataArray[$key] as $field => $values){
		//echo "$field $values".'</br>';
		if ($field == '_id') $dataArray[$key][$field] = $values['$oid'];
		if ($field == 'lastLogin') $dataArray[$key][$field] = date("Y-m-d H:i:s",strtotime($values['$date']));
		if ($field == 'lastLogout') $dataArray[$key][$field] = date("Y-m-d H:i:s",strtotime($values['$date']));
		if (strtolower($value) == 'false') $dataArray[$key][$field] = $db->quote(0);
		if (strtolower($value) == 'true') $dataArray[$key][$field] = $db->quote(1);
		$dataArray[$key][$field] = $db->quote($dataArray[$key][$field]);
	}
	$dataArray[$key]['period'] = $db->quote(date("Y-m-d",strtotime($this->data['mc_import_mongoDB___period_raw'])));
	$dataArray[$key]['db_name'] = $db->quote($this->data['mc_import_mongoDB___db_name_raw'][0]);
	$dataArray[$key]['importdate'] = $db->quote(date("Y-m-d H:i:s",time()));
	$dataArray[$key]['commerce'] = $db->quote($commerce);
	$dataArray[$key]['commerce_customer'] = $db->quote($commerceCustomer);
	$dataArray[$key]['ticket'] = $db->quote($ticket);
	$dataArray[$key]['hauler'] = $db->quote($hauler);

	// Insert columns.
	$columns = array_keys($dataArray[$key]);
	// Insert values.
	$values = array_values($dataArray[$key]);
	 
	// Create a new query object.
	$query = $db->getQuery(true);

	// Prepare the insert query.
	$query
		->insert($db->quoteName('mongoDB_users'))
		->columns($db->quoteName($columns))
		->values(implode(',', $values));
	 
	// Set the query using our newly populated query object and execute it.
	$db->setQuery($query);
	$db->execute();
	$count++;
}


JFactory::getApplication()->enqueueMessage("$count Records imported.");
//print_r($dataArray);

//echo "</pre>";
return;
