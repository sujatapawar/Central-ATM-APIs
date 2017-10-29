<?php
/*
1.  Put the listed domain into freezer.
2.  Put IPs belongs to the listed domain into new pool called available_assets.
3.  Replace the same IPs in all child pools with new IP from warmup. 
5.  Send email to service, delivery and client.
*/
include("commonFunctions.php");
///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":59,"domain":"mail.yesbank.in","ip_wise_counts":{"342":3000,"352":2000}}';
////////////////////////////////////////////////////////////////////////////////////////////////////
if(isset($jsonString) and $jsonString!="")
{
	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/Sending_Domain_Blacklisted/'.$today_date.'.csv';
	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
    $obj = new commonFunctions($jsonString);
     $blacklistedDomainIdArr = $obj->getDomainId($obj->inputJsonArray['domain']);
   
    $logsArray["Action1"]="Domain Removed";
  ////////////////////////////////////////   Coding Needed //////////////////
  
  
  ///////////////////////////////////////////////////////////////////////////
  
    //Insert bad domain id into frezzer
    $obj->putDomainInFreezer($blacklistedDomainId,"childPool_LinkDomains");
    $logsArray["Action3"]="Domain put into Freezer";
	
	//Releasing IP
	$obj->releaseIP();
	$logsArray["Action4"]=$json = "IPs are released";
	    
	//update IP wise count
	$obj->UpdateIPWiseCounts();
	$logsArray["Action5"]="IP wise counts are updated";
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



?>
