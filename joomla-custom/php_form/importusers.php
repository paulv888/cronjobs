<?php

// No direct access
defined('_JEXEC') or die('Restricted access');

echo "<pre>";
//print_r($this->data);
//echo $this->data['mc_import_mongoDB___period_raw'].'</br>';
//echo $this->data['mc_import_mongoDB___filename'].'</br>';
$file = $this->data['mc_import_mongoDB___filename'];
$data = file_get_contents($_SERVER['DOCUMENT_ROOT'].$file);
$data = preg_replace('~\R~u', ",", $data);
$data = '['.rtrim($data, ",").']';
//echo $data.'</br>';
$dataArray = json_decode($data,1);
//print_r($dataArray);

// Get a db connection.
$db = JFactory::getDbo();
 
 
 $count = 0;

foreach ($dataArray as $key => $user) {
	unset($dataArray[$key]['hash']);
	unset($dataArray[$key]['salt']);

	$products = $dataArray[$key]['products'];
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
	$dataArray[$key]['commerce'] = $db->quote($commerce);
	$dataArray[$key]['commerce_customer'] = $db->quote($commerceCustomer);
	$dataArray[$key]['ticket'] = $db->quote($ticket);

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
