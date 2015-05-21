<?php
//define( 'DEBUG_CLARIFY', TRUE );
if (!defined('DEBUG_CLARIFY')) define( 'DEBUG_CLARIFY', FALSE );

//define( 'CLARIFY_INITIALIZE' , TRUE);
if (!defined('CLARIFY_INITIALIZE')) define( 'CLARIFY_INITIALIZE', FALSE );

function clarify() {

	if (DEBUG_CLARIFY) echo "<pre>";

	if (CLARIFY_INITIALIZE) {
		echo date("Y-m-d H:i:s").": ".getCases("OpenCases")." Cases Read".CRLF; 			// Initialize with all Cases
		echo date("Y-m-d H:i:s").": ".storeCase(formatData())." Upserted.".CRLF;
		echo date("Y-m-d H:i:s").": ".getCases("CaseToCR")." Cases/CR's Read".CRLF; 		// Initialize with all Cases
		echo date("Y-m-d H:i:s").": ".storeCR(formatData())."  CRs Upserted.".CRLF;
	} else {
		echo date("Y-m-d H:i:s").": ".getCases("Last2Days")." Cases/CR's Read".CRLF;			// No Distinct - Last 3 days
		echo date("Y-m-d H:i:s").": ".storeCase(formatData())." Cases Upserted.".CRLF;
		echo date("Y-m-d H:i:s").": ".storeCR(formatData())." CRs Upserted.".CRLF;
	}


	if (DEBUG_CLARIFY) echo "</pre>";
}

function getCases($query) {

	ini_set('max_execution_time',120);
	
	$username=CLARIFY_USER;
	$password=CLARIFY_PASSWORD;
    $cookie_file_path = $_SERVER['DOCUMENT_ROOT'].'/tmp/cookies.txt';

    //==============================================================
	$url='https://clarify.commandalkon.com/login/login.asp';
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_USERPWD, $username. ':' . $password);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, 0);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	$result=curl_exec ($ch);
	$feedback = $result;
	curl_close ($ch);	
    //=============================================================
	
	
	$fields = array(
		'autosubmit' => "UNDEFINED",
		'password' => "",
		'remember_me' => "on",
		'time_zone' => "Central+Standard+Time",
		'username' => "pvloon",
	);
	$fields_string = "";
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');


    //==============================================================
	$url="https://clarify.commandalkon.com/login/login2.asp";
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_USERPWD, $username. ':' . $password);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, count($fields));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, 0);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	$result=curl_exec($ch);
	$feedback.= "****https://clarify.commandalkon.com/login/login2.asp*****";
	$feedback.= $result;
	curl_close ($ch);	
    //=============================================================

    //==============================================================
	// $url='https://clarify.commandalkon.com/ce/ce_results.asp?objid=268440043&mode=edit&opener=';
    // $ch = curl_init();
	// curl_setopt($ch, CURLOPT_USERPWD, $username. ':' . $password);
	// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	// curl_setopt($ch, CURLOPT_URL, $url);
	// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	// curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
	// curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	// curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	// curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	// curl_setopt($ch, CURLOPT_FAILONERROR, 0);
	// curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	// $result=curl_exec ($ch);
	// $feedback.= "****https://clarify.commandalkon.com/ce/ce_results.asp?objid=268440043&mode=edit&opener=*****";
	// $feedback.= $result;
	// curl_close ($ch);	
    //=============================================================
	
	// Case To CR
	
	switch ($query)
	{
	case "CaseToCR": 
		$fields_string = "query=268440454&obj_type=case&parameters=open%25%2CMC%25&sqlStmt=SELECT%2520t0.id_number%2520%2522Case%2520ID%2522%252C%2520t4.login_name%2520%2522Owner%2522%252C%2520t0.title%2520%2522Case%2520Title%2522%252C%2520t1.title%2520%2522Condition%2522%252C%2520t5.title%2520%2522Status%2522%252C%2520t6.title%2520%2522Case%2520Type*%2522%252C%2520t7.site_id%2520%2522Site%2520ID%2522%252C%2520t7.name%2520%2522Site%2520Name%2522%252C%2520t8.title%2520%2522Priority*%2522%252C%2520t9.title%2520%2522Severity*%2522%252C%2520t10.first_name%2520%2522Contact%2520First%2520Name%2522%252C%2520t10.last_name%2520%2522Contact%2520Last%2520Name%2522%252C%2520CONVERT%2528CHAR%252C%2520t0.creation_time%2529%2520%2522CONPREFIX_Create%2520Date%2522%252C%2520t11.login_name%2520%2522Originator%2522%252C%2520t12.org_id%2520%2522Org%2520ID%2522%252C%2520t7.region%2520%2522Site%2520Region%2522%252C%2520CONVERT%2528CHAR%252C%2520t13.close_date%2529%2520%2522CONPREFIX_Close%2520Date%2522%252C%2520t0.x_customer_issue_no%2520%2522Cust%2520Issue%2522%252C%2520t3.part_number%2520%2522Part%2520Number%2522%252C%2520t0.x_module%2520%2522Module%2522%252C%2520t16.id_number%2520%2522CR%2520ID%2522%252C%2520t16.release_rev%2520%2522CR%2520Fix%2520In%2520Ver%2522%252C%2520t17.title%2520%2522CR%2520Status%2522%252C%2520t0.x_Project_SL%2520%2522S%2526L%2520Number%2522%252C%2520CONVERT%2528CHAR%252C%2520t0.objid%2529%2520%2522objid%2522%2520FROM%2520table_case%2520t0%2520LEFT%2520OUTER%2520JOIN%2520table_mod_level%2520t2%2520ON%2520t0.case_prt2part_info%2520%253D%2520t2.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_part_num%2520t3%2520ON%2520t2.part_info2part_num%2520%253D%2520t3.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_close_case%2520t13%2520ON%2520t0.objid%2520%253D%2520t13.last_close2case%2520%2520LEFT%2520OUTER%2520JOIN%2520table_workaround%2520t14%2520ON%2520t0.case_soln2workaround%2520%253D%2520t14.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_probdesc%2520t15%2520ON%2520t14.workaround2probdesc%2520%253D%2520t15.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520mtm_probdesc12_bug28%2520m0%2520ON%2520m0.probdesc2bug%2520%253D%2520t15.objid%2520LEFT%2520OUTER%2520JOIN%2520table_bug%2520t16%2520ON%2520t16.objid%2520%253D%2520m0.bug2probdesc%2520LEFT%2520OUTER%2520JOIN%2520table_gbst_elm%2520t17%2520ON%2520t16.bug_sts2gbst_elm%2520%253D%2520t17.objid%2520%252C%2520table_condition%2520t1%252C%2520table_user%2520t4%252C%2520table_gbst_elm%2520t5%252C%2520table_gbst_elm%2520t6%252C%2520table_site%2520t7%252C%2520table_gbst_elm%2520t8%252C%2520table_gbst_elm%2520t9%252C%2520table_contact%2520t10%252C%2520table_user%2520t11%252C%2520table_bus_org%2520t12%2520WHERE%2520%2528t0.case_state2condition%2520%253D%2520t1.objid%2529%2520AND%2520%2528t0.case_owner2user%2520%253D%2520t4.objid%2529%2520AND%2520%2528t0.casests2gbst_elm%2520%253D%2520t5.objid%2529%2520AND%2520%2528t0.calltype2gbst_elm%2520%253D%2520t6.objid%2529%2520AND%2520%2528t0.case_reporter2site%2520%253D%2520t7.objid%2529%2520AND%2520%2528t7.primary2bus_org%2520%253D%2520t12.objid%2529%2520AND%2520%2528t0.respprty2gbst_elm%2520%253D%2520t8.objid%2529%2520AND%2520%2528t0.respsvrty2gbst_elm%2520%253D%2520t9.objid%2529%2520AND%2520%2528t0.case_reporter2contact%2520%253D%2520t10.objid%2529%2520AND%2520%2528t0.case_originator2user%2520%253D%2520t11.objid%2529%2520AND%2520%2528t1.title%2520like%2520%257B0%257D%2520AND%2520t3.part_number%2520like%2520%257B1%257D%2529&strClauses=%255B%2522update%257C268455891%257C268436067%257Cstarts%2520with%257Copen%257C0%257C0%257Ccase_state2condition%253Atitle%257C1%2522%252C%2522update%257C268455886%257C0%257Cjoins%2520groups%257CAND%257C0%257C0%257C%257C2%2522%252C%2522update%257C268455888%257C268436071%257Cstarts%2520with%257CMC%257C0%257C0%257C%2528LEFT%2520OUTER%2529%2520case_prt2part_info%253Apart_info2part_num%253Apart_number%257C3%2522%255D";
		
		break;
	case "OpenCases": 
		$fields_string = "query=268440017&obj_type=case&parameters=Closed%2CMC%25&sqlStmt=SELECT%2520t0.id_number%2520%2522Case%2520ID%2522%252C%2520t4.login_name%2520%2522Owner%2522%252C%2520t0.title%2520%2522Case%2520Title%2522%252C%2520t1.title%2520%2522Condition%2522%252C%2520t5.title%2520%2522Status%2522%252C%2520t6.title%2520%2522Case%2520Type*%2522%252C%2520t7.site_id%2520%2522Site%2520ID%2522%252C%2520t7.name%2520%2522Site%2520Name%2522%252C%2520t8.title%2520%2522Priority*%2522%252C%2520t9.title%2520%2522Severity*%2522%252C%2520t10.first_name%2520%2522Contact%2520First%2520Name%2522%252C%2520t10.last_name%2520%2522Contact%2520Last%2520Name%2522%252C%2520CONVERT%2528CHAR%252C%2520t0.creation_time%2529%2520%2522CONPREFIX_Create%2520Date%2522%252C%2520t11.login_name%2520%2522Originator%2522%252C%2520t12.org_id%2520%2522Org%2520ID%2522%252C%2520t7.region%2520%2522Site%2520Region%2522%252C%2520CONVERT%2528CHAR%252C%2520t13.close_date%2529%2520%2522CONPREFIX_Close%2520Date%2522%252C%2520t0.x_customer_issue_no%2520%2522Cust%2520Issue%2522%252C%2520t3.part_number%2520%2522Part%2520Number%2522%252C%2520t0.x_module%2520%2522Module%2522%252C%2520CONVERT%2528CHAR%252C%2520t0.objid%2529%2520%2522objid%2522%2520FROM%2520table_case%2520t0%2520LEFT%2520OUTER%2520JOIN%2520table_mod_level%2520t2%2520ON%2520t0.case_prt2part_info%2520%253D%2520t2.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_part_num%2520t3%2520ON%2520t2.part_info2part_num%2520%253D%2520t3.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_close_case%2520t13%2520ON%2520t0.objid%2520%253D%2520t13.last_close2case%2520%252C%2520table_condition%2520t1%252C%2520table_user%2520t4%252C%2520table_gbst_elm%2520t5%252C%2520table_gbst_elm%2520t6%252C%2520table_site%2520t7%252C%2520table_gbst_elm%2520t8%252C%2520table_gbst_elm%2520t9%252C%2520table_contact%2520t10%252C%2520table_user%2520t11%252C%2520table_bus_org%2520t12%2520WHERE%2520%2528t0.case_state2condition%2520%253D%2520t1.objid%2529%2520AND%2520%2528t0.case_owner2user%2520%253D%2520t4.objid%2529%2520AND%2520%2528t0.casests2gbst_elm%2520%253D%2520t5.objid%2529%2520AND%2520%2528t0.calltype2gbst_elm%2520%253D%2520t6.objid%2529%2520AND%2520%2528t0.case_reporter2site%2520%253D%2520t7.objid%2529%2520AND%2520%2528t7.primary2bus_org%2520%253D%2520t12.objid%2529%2520AND%2520%2528t0.respprty2gbst_elm%2520%253D%2520t8.objid%2529%2520AND%2520%2528t0.respsvrty2gbst_elm%2520%253D%2520t9.objid%2529%2520AND%2520%2528t0.case_reporter2contact%2520%253D%2520t10.objid%2529%2520AND%2520%2528t0.case_originator2user%2520%253D%2520t11.objid%2529%2520AND%2520%2528t1.title%2520%2521%253D%2520%257B0%257D%2520AND%2520t3.part_number%2520like%2520%257B1%257D%2529&strClauses=%255B%2522update%257C268454224%257C268435483%257Cis%2520not%2520equal%2520to%257CClosed%257C0%257C0%257Ccase_state2condition%253Atitle%257C1%2522%252C%2522update%257C268454223%257C0%257Cjoins%2520groups%257CAND%257C0%257C0%257C%257C2%2522%252C%2522update%257C268454222%257C268435496%257Cstarts%2520with%257CMC%257C0%257C0%257C%2528LEFT%2520OUTER%2529%2520case_prt2part_info%253Apart_info2part_num%253Apart_number%257C3%2522%255D";
		break;
	case "Last2Days":
		$fields_string = "query=268440603&obj_type=case&parameters=2%2CMC%25&sqlStmt=SELECT%2520t0.id_number%2520%2522Case%2520ID%2522%252C%2520t4.login_name%2520%2522Owner%2522%252C%2520t0.title%2520%2522Case%2520Title%2522%252C%2520t5.title%2520%2522Condition%2522%252C%2520t6.title%2520%2522Status%2522%252C%2520t7.title%2520%2522Case%2520Type*%2522%252C%2520t8.site_id%2520%2522Site%2520ID%2522%252C%2520t8.name%2520%2522Site%2520Name%2522%252C%2520t9.title%2520%2522Priority*%2522%252C%2520t10.title%2520%2522Severity*%2522%252C%2520t11.first_name%2520%2522Contact%2520First%2520Name%2522%252C%2520t11.last_name%2520%2522Contact%2520Last%2520Name%2522%252C%2520CONVERT%2528CHAR%252C%2520t0.creation_time%2529%2520%2522CONPREFIX_Create%2520Date%2522%252C%2520t12.login_name%2520%2522Originator%2522%252C%2520t13.org_id%2520%2522Org%2520ID%2522%252C%2520t8.region%2520%2522Site%2520Region%2522%252C%2520CONVERT%2528CHAR%252C%2520t14.close_date%2529%2520%2522CONPREFIX_Close%2520Date%2522%252C%2520t0.x_customer_issue_no%2520%2522Cust%2520Issue%2522%252C%2520t3.part_number%2520%2522Part%2520Number%2522%252C%2520t0.x_module%2520%2522Module%2522%252C%2520t17.id_number%2520%2522CR%2520ID%2522%252C%2520t17.release_rev%2520%2522CR%2520Fix%2520In%2520Ver%2522%252C%2520t18.title%2520%2522CR%2520Status%2522%252C%2520t0.x_Project_SL%2520%2522S%2526L%2520Number%2522%252C%2520CONVERT%2528CHAR%252C%2520t0.objid%2529%2520%2522objid%2522%2520FROM%2520table_case%2520t0%2520LEFT%2520OUTER%2520JOIN%2520table_mod_level%2520t2%2520ON%2520t0.case_prt2part_info%2520%253D%2520t2.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_part_num%2520t3%2520ON%2520t2.part_info2part_num%2520%253D%2520t3.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_close_case%2520t14%2520ON%2520t0.objid%2520%253D%2520t14.last_close2case%2520%2520LEFT%2520OUTER%2520JOIN%2520table_workaround%2520t15%2520ON%2520t0.case_soln2workaround%2520%253D%2520t15.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520table_probdesc%2520t16%2520ON%2520t15.workaround2probdesc%2520%253D%2520t16.objid%2520%2520LEFT%2520OUTER%2520JOIN%2520mtm_probdesc12_bug28%2520m0%2520ON%2520m0.probdesc2bug%2520%253D%2520t16.objid%2520LEFT%2520OUTER%2520JOIN%2520table_bug%2520t17%2520ON%2520t17.objid%2520%253D%2520m0.bug2probdesc%2520LEFT%2520OUTER%2520JOIN%2520table_gbst_elm%2520t18%2520ON%2520t17.bug_sts2gbst_elm%2520%253D%2520t18.objid%2520%252C%2520table_act_entry%2520t1%252C%2520table_user%2520t4%252C%2520table_condition%2520t5%252C%2520table_gbst_elm%2520t6%252C%2520table_gbst_elm%2520t7%252C%2520table_site%2520t8%252C%2520table_gbst_elm%2520t9%252C%2520table_gbst_elm%2520t10%252C%2520table_contact%2520t11%252C%2520table_user%2520t12%252C%2520table_bus_org%2520t13%2520WHERE%2520%2528t1.act_entry2case%2520%253D%2520t0.objid%2529%2520AND%2520%2528t0.case_owner2user%2520%253D%2520t4.objid%2529%2520AND%2520%2528t0.case_state2condition%2520%253D%2520t5.objid%2529%2520AND%2520%2528t0.casests2gbst_elm%2520%253D%2520t6.objid%2529%2520AND%2520%2528t0.calltype2gbst_elm%2520%253D%2520t7.objid%2529%2520AND%2520%2528t0.case_reporter2site%2520%253D%2520t8.objid%2529%2520AND%2520%2528t8.primary2bus_org%2520%253D%2520t13.objid%2529%2520AND%2520%2528t0.respprty2gbst_elm%2520%253D%2520t9.objid%2529%2520AND%2520%2528t0.respsvrty2gbst_elm%2520%253D%2520t10.objid%2529%2520AND%2520%2528t0.case_reporter2contact%2520%253D%2520t11.objid%2529%2520AND%2520%2528t0.case_originator2user%2520%253D%2520t12.objid%2529%2520AND%2520%2528t1.entry_time%2520between%2520%2528GetDate%2528%2529%2520-%2520Convert%2528int%252C%2520%257B0%257D%2529%2529%2520AND%2520GetDate%2528%2529%2520AND%2520t3.part_number%2520like%2520%257B1%257D%2529&strClauses=%255B%2522update%257C268456593%257C268436064%257Cwithin%2520last%2520%2528days%2529%257C3%257C0%257C3%257Ccase_act2act_entry%253Aentry_time%257C1%2522%252C%2522update%257C268456594%257C0%257Cjoins%2520groups%257CAND%257C0%257C0%257C%257C2%2522%252C%2522update%257C268456595%257C268436071%257Cstarts%2520with%257CMC%257C0%257C0%257C%2528LEFT%2520OUTER%2529%2520case_prt2part_info%253Apart_info2part_num%253Apart_number%257C3%2522%255D";
		break;
	}
	
    //==============================================================
	$url="https://clarify.commandalkon.com/ce/ajaxResults.asp";
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_USERPWD, $username. ':' . $password);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, count($fields));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, 0);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Requested-With: XMLHttpRequest" , "Accept: application/json, text/javascript, */*; q=0.01"));
	$url_r='https://clarify.commandalkon.com/ce/ce_results.asp?objid=268440043&mode=edit&opener=';
	curl_setopt($ch, CURLOPT_REFERER, $url_r);
	$result=curl_exec ($ch);
	$feedback.= "****https://clarify.commandalkon.com/ce/ajaxResults.asp*****";
	$feedback.= $result;
	curl_close ($ch);	
	

	$result_r = json_decode($result);
	
	// echo "<pre>";
	if (DEBUG_CLARIFY) print_r($result_r);
	// echo "</pre>";
	
	if ($result_r->rowCount > 0) {
	    $file = $_SERVER['DOCUMENT_ROOT'].'/tmp/clarify.txt';
		$result_r->content = str_replace("S&L Number", "S_L Number", $result_r->content);
		$result_r->content = str_replace("&nbsp;", " ", $result_r->content);
		file_put_contents($file, $result_r->content);
	}
	
	return $result_r->rowCount;
	
}

function tdrows($elements) {
	$fields = Array();
	foreach ($elements as $element)
	{
		$fields[]= trim($element->nodeValue);
	}
	return $fields;
}

function formatData() {


    $file = $_SERVER['DOCUMENT_ROOT'].'/tmp/clarify.txt';

	$contents = file_get_contents($file);
    $DOM = new DOMDocument;
    $DOM->loadHTML($contents);

    $items = $DOM->getElementsByTagName('tr');

	$i = 0;
	$insert = Array();
    foreach ($items as $node)
    {
        if ($i == 0) {
			$colnames = tdrows($node->childNodes);
			foreach ($colnames as $key => $colname) {
				$colnames[$key] = str_replace('*', '', $colnames[$key]);
				$colnames[$key] = str_replace(' ', '_', $colnames[$key]);
			}
			$colnames[] = "objid";
			$i++;
		} else {
			$row = tdrows($node->childNodes);
			$attr = $node->getAttribute("ondblclick");
			if (strlen($attr) > 0) {
				$row[] = preg_replace('/\D/', '', $attr);
			}
			$insert[] = array_combine ( $colnames , $row );
			$i++;
		}
			
    }
	return $insert;
}

function storeCase($rows) {

	$i = 0;
	foreach ($rows as $fields) {
		$where['Case_ID'] = $fields['Case_ID'];
		$fields['Create_Date'] = date("Y-m-d H:i:s", strtotime($fields['Create_Date']));
		if ($fields['Condition'] == "Closed") {
			$fields['Close_Date'] = date("Y-m-d H:i:s", strtotime($fields['Close_Date']));
		} else {
			$fields['Close_Date'] = "0000-00-00 00:00:00";
		}
		$fields['Site_ID'].= "_";
		$fields['Priority'] = substr($fields['Priority'],0,1);
		$fields['Severity'] = substr($fields['Severity'],0,1);
		
		if (array_key_exists('CR_ID', $fields)) unset($fields['CR_ID']);
		if (array_key_exists('CR_Fix_In_Ver', $fields)) unset($fields['CR_Fix_In_Ver']);
		if (array_key_exists('CR_Status', $fields)) unset($fields['CR_Status']);
		if (array_key_exists('S_L_Number', $fields)) unset($fields['S_L_Number']);
		
		if (DEBUG_CLARIFY) echo "Row $i ";
		if (DEBUG_CLARIFY) print_r($fields);
		// echo string_to_ascii($fields['Org_ID']);

		if (DEBUG_CLARIFY) print_r($where);
		PDOupsert("mc_cases", $fields, $where);
		$i++;
	}
	return $i;
}

function storeCR($rows) {

	$i = 0;
	foreach ($rows as $fields) {
		if (strlen($fields['CR_ID']) > 0) {
			$where['CR_ID'] = $fields['CR_ID'];
			$CRfields['CR_ID'] = $fields['CR_ID'];
			$CRfields['Case_ID'] = $fields['Case_ID'];
			$CRfields['CR_Fix_In_Ver'] = $fields['CR_Fix_In_Ver'];
			$CRfields['CR_Status'] = $fields['CR_Status'];
			$CRfields['S_L_Number'] = $fields['S_L_Number'];
			if (DEBUG_CLARIFY) echo "Row $i ";
			if (DEBUG_CLARIFY) print_r($CRfields);
			if (DEBUG_CLARIFY) print_r($where);
			PDOupsert("mc_crs", $CRfields, $where);
			$i++;
		}
	}
	return $i;
}
?>
