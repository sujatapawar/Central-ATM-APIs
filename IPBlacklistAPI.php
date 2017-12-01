<?php
/*
1. Remove IP from Pool (childPool_IPs) and put into freezer (pool 2)
2. Replace with warm-up IP (pool id 1) with same environment and same grade, with the last stage.
3. Release all IPs, and update counts
4. Notify client and client servicing
5. Notify delivery team
*/


include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":230,"ip_id":342,"ip_wise_counts":{"342":0, "352":0}}';
//$jsonString = file_get_contents('php://input');

////////////////////////////////////////////////////////////////////////////////////////////////////
$obj = new commonFunctions($jsonString);
if(isset($jsonString) and $jsonString!="")
{
  
	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/IP_Blacklisted/'.$today_date.'.csv';

	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
	
       
      $logsArray["Request Type"]="PostORPrep";

     $blacklistedIPId = $obj->inputJsonArray['ip_id'];
     // update Req1
     $obj->updateReq1Status("Stopped");		

    //Retain 'childPool_id' of all pools with given IP_Id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIds($blacklistedIPId);
	//print_r($childPoolIdsArray); //die;

    //delete all entries of the IP_Id 
    $obj->removeIP($blacklistedIPId);

    $logsArray["Action1"]="IP Removed";

    // get new IP from warm up
    $warmedUpIP = $obj->getIPFromWarmUp($blacklistedIPId);
    if($warmedUpIP !='')
    {
         //replanish all the pools with new warmed-up IP 
    	  foreach($childPoolIdsArray as $childPoolId)
    	  {
    	  	$obj->replanishIP($warmedUpIP,$childPoolId[0]);
		 echo "\n $childPoolId[0] Replanied with Warmedup IP- $warmedUpIP";
    	  }
	//die;    
        $logsArray["Action2"]="IP Replanied with Warmedup IP- $warmedUpIP";
    }
    else
    {
    	$logsArray["Action2"]="Warmedup IP not available";
 
    }

    //Insert bad ip id into frezzer
    $obj->putIPInFreezer($blacklistedIPId);
    $logsArray["Action3"]="IP put into Freezer";
	
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
		$array=array(21,$Req1_Details[0]['cl_id'],$Req1_Details[0]['mailer_id'],date('Y-m-d H:i:s'),$Req1_Details[0]['created_time'],$Client_Data,'open');

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
	       $logsArray["Request Type"]=$obj->get_request_type();
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
	$subject="[Central ATM API] Email Alert to client for IP Blacklist ";
	$message="Email Alert for IP Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);

	//Send email alert to delivery team 
	$to="sarah.gidwani@nichelive.com";
	$subject="Central ATM API] Email Alert to Deliver for IP Blacklist ";
	$message="Email Alert for IP Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);


}
else
{
	//Send email alert to delivery team 
	$to="shripad.kulkarni@nichelive.com";
	$subject="Central ATM API] Email Alert for IP Blacklist ";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);

}


?>
