<?php
/*
1. Put Domain into freezer
2. Replace with a new domain (with same grade and environment, and last stage) from warm-up
3. Release all IPs, and update counts
4. Notify client, client servicing and delivery team
*/

include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
//$jsonString = '{"req1":79,"domain":"sendm.net","ip_wise_counts":{"342":3000,"352":2000}}';
$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////
$obj = new commonFunctions($jsonString);
if(isset($jsonString) and $jsonString!="")
{

	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/ReturnPath_Blacklisted/'.$today_date.'.csv';

	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);

    

     $blacklistedDomainIdArr = $obj->getDomainId($obj->inputJsonArray['domain']);
    $blacklistedDomainId=$blacklistedDomainIdArr[0]['domain_id']; //die;

    //Retain 'childPool_id' of all pools with given domain id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIdsOfDomains($blacklistedDomainId,"childPool_RPDomains");
	//print_r($childPoolIdsArray); //die;

    //delete all entries of the domain id 
    $obj->removeDomain($blacklistedDomainId,"childPool_RPDomains");

    $logsArray["Action1"]="Domain Removed";

    // get new domain from warm up
    $warmedUpDomain = $obj->getDomainFromWarmUp($blacklistedDomainId,"childPool_RPDomains");
    if($warmedUpDomain !='')
    {
         //replanish all the pools with new warmed-up domain 
    	  foreach($childPoolIdsArray as $childPoolId)
    	  {
    	  	$obj->replanishDomain($warmedUpDomain,$childPoolId[0],"childPool_RPDomains");
		 echo "\n $childPoolId[0] Replanied with Warmedup Domain- $warmedUpDomain";
    	  }
	//die;    
        $logsArray["Action2"]="Domain Replanied with Warmedup Domain - $warmedUpDomain";
    }
    else
    {
    	$logsArray["Action2"]="Warmedup Domain not available";
 
    }

    //Insert bad domain id into frezzer
    $obj->putDomainInFreezer($blacklistedDomainId,"childPool_RPDomains");
    $logsArray["Action3"]="Domain put into Freezer";
	
	//Releasing IP
	$obj->releaseIP();
	$logsArray["Action4"]=$json = "IPs are released";
	    
	//update IP wise count
	$obj->UpdateIPWiseCounts();
	$logsArray["Action5"]="IP wise counts are updated";
	
	
	
	/////////////// Blocking Sending functions //////////////////////////////////////////
	   $array = array($obj->req1);
           $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail", " where req1_id=?", $array);

	   $obj->connection_disconnect();
    
	    $obj->connection_db_mail_master();
		$array = array($Req1_Details[0]['cl_id']);
		$Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company", " where cl_id=?", $array);

		$Client_Data = "ClientName: ".$Client_Details[0]['cl_name']."\n Company Name:".$Client_Details[0]['cl_company']."\n Mailer-ID:".$Req1_Details[0]['mailer_id']."\n Sent Date:".$Req1_Details[0]['created_time']."\n Total Sent:".$Req1_Details[0]['total_unique_mail'];
		$array=array(22,$Req1_Details[0]['cl_id'],$Req1_Details[0]['mailer_id'],date('Y-m-d H:i:s'),$Req1_Details[0]['created_time'],$Client_Data,'open');

	       $Exception_Details = $obj->_dbHandlepdo->sql_Select("client_exceptions", "exception_id", " where exception_type_id=? and exception_client_id=? and exception_object_id=? and exception_status=?", array($array[0],$array[1],$array[2],$array[6]));

		if(!isset($Exception_Details[0]['exception_id'])) // check if exception already exist
		{ 
		    $Exception_ID = $obj->_dbHandlepdo->sql_insert("client_exceptions", " exception_type_id,exception_client_id,exception_object_id,exception_open_date_time,exception_closed_date_time,exception_data,exception_status", $array);
		    $array = array(32,$Exception_ID,$Req1_Details[0]['cl_id']);
		    $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
		    $array = array(33,$Exception_ID,$Req1_Details[0]['cl_id']);
		    $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
		  
		}
	    $obj->connection_disconnect();	
	
	////////////////////////////////////////////////////////////////////////////////////

    //write logs
	if (file_exists($csvFileName)) {
	$fp = fopen($csvFileName, 'a');
	} else {
	$fp = fopen($csvFileName, 'w');
	 fputcsv($fp, array_keys($logsArray));

	}
	//Loop through the associative array.
	fputcsv($fp, $logsArray);
	//Finally, close the file pointer.
	fclose($fp);


	//Send email alert to client
	$to="sarah.gidwani@nichelive.com";
	$subject="[Central ATM API] Email Alert to client for Returnpath domain Blacklist ";
	$message="Email Alert for Returnpath domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);

	//Send email alert to delivery team 
	$to="sarah.gidwani@nichelive.com";
	$subject="Central ATM API] Email Alert to Deliver for Returnpath domain Blacklist ";
	$message="Email Alert for Returnpath domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);


}
else
{
	//Send email alert to delivery team 
	$to="shripad.kulkarni@nichelive.com";
	$subject="Central ATM API] Email Alert for Returnpath domain Blacklist ";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);

}


?>
