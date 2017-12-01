<?php
/*
1.  Put the listed domain into freezer.
2.  Put IPs belongs to the listed domain into new pool called available_assets.
3.  Replace the same IPs in all child pools with new IP from warmup. 
5.  Send email to service, delivery and client.
*/
include("commonFunctions.php");
///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
//$jsonString = '{"req1":79,"domain":"nl1.sendm.net","ip_wise_counts":{"342":30000,"352":20000}}';
$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////
$obj = new commonFunctions($jsonString);
if(isset($jsonString) and $jsonString!="")
{
	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/Sending_Domain_Blacklisted/'.$today_date.'.csv';
	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
	$logsArray["Request Type"]=$obj->get_request_type();
	
	// update Req1
          $obj->updateReq1Status("Stopped");	
	
        $blacklistedDomainIdArr = $obj->getDomainId($obj->inputJsonArray['domain']);
	$blacklistedDomainId=$blacklistedDomainIdArr[0]['domain_id'];
	
	//fetch IP belongs to domain
	
	 $ipIds = $obj->getDomainIpId($obj->inputJsonArray['domain']);
	//echo $obj->inputJsonArray['domain']; die;
	
	// Deactivate the domain
	$obj->deactivateDomain($blacklistedDomainId);     
        $logsArray["Action1"]="Domain deactivated";
	
       
	
	//Retain 'childPool_id' of all pools with given IP_Id in an array 
        $childPoolIdsArray = $obj->getAllChildPoolIds($ipIds[0]['IP_id']);
	 
	
	
        //delete all entries of the IP_Id from all pools to setup with new
	$obj->removeIP($ipIds[0]['IP_id']);
	  
        // get new IP from warm up
        $warmedUpIP = $obj->getIPFromWarmUp($ipIds[0]['IP_id']); 
       if($warmedUpIP !='')
	{
		 //replanish all the pools with new warmed-up IP 
		  foreach($childPoolIdsArray as $childPoolId)
		  {
			$obj->replanishIP($warmedUpIP,$childPoolId[0]);
			 echo "\n $childPoolId[0] Replanied with Warmedup IP- $warmedUpIP";
		  }
		//die;    
		$logsArray["Action2"]=$ipIds[0]['IP_id']." IP Replanied with Warmedup IP- $warmedUpIP";
	 }
	else
	 {
		$logsArray["Action2"]="Warmedup IP not available";

	 }
		
	
	//put Ip in available_assets pool
	$obj->putAssetIntoAvailablePool($ipIds[0]['IP_id']);	
	
  
	//Insert bad domain id into frezzer
	$obj->putDomainInFreezer($blacklistedDomainId,"childPool_SendingDomains");
	$logsArray["Action3"]="Domain put into Freezer";
	
	//Releasing IP
	$obj->releaseIP();
	$logsArray["Action4"]=$json = "IPs are released";
	    
	//update IP wise count
	$obj->UpdateIPWiseCounts();
	$logsArray["Action5"]="IP wise counts are updated";
	
	if($obj->get_request_type()=="PostORPrep") 
       {
	/////////////// Blocking Sending functions //////////////////////////////////////////
	  $obj->connection_atm();	
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
		    $obj->_dbHandlepdo->sql_insert("exception_process_log", " exception_id,datetime,initiated_by,account_exception_id", array($Exception_ID,date('Y-m-d H:i:s'),'Client',$Exception_ID));
		    $array = array(32,$Exception_ID,$Req1_Details[0]['cl_id']);
		    $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
		    $array = array(33,$Exception_ID,$Req1_Details[0]['cl_id']);
		    $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
		  
		}
	    $obj->connection_disconnect();	
	
	 $logsArray["Action6"]="Sending functions are blocked";
	////////////////////////////////////////////////////////////////////////////////////
		
	} // if close for request type	
	else {
	
	   $logsArray["Action6"]="";
	}
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
	$subject="[Central ATM API] Email Alert to client for Sending domain Blacklist ";
	$message="Email Alert for Sending domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);
	//Send email alert to delivery team 
	$to="sarah.gidwani@nichelive.com";
	$subject="Central ATM API] Email Alert to Deliver for Sending domain Blacklist ";
	$message="Email Alert for Sending domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);
}
else
{
	//Send email alert to delivery team 
	$to="shripad.kulkarni@nichelive.com";
	$subject="Central ATM API] Email Alert for Sending domain Blacklist ";
	$message="Blank Json Input";
	$obj->sendEmailAlert($to,$subject,$message);

}



?>
