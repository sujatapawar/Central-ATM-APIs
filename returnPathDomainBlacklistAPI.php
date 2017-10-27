<?php
/*
1. Put Domain into freezer
2. Replace with a new domain (with same grade and environment, and last stage) from warm-up
3. Release all IPs, and update counts
4. Notify client, client servicing and delivery team
*/

include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":59,"domain":"mail.yesbank.in","ip_wise_counts":{"342":3000,"352":2000}}';
////////////////////////////////////////////////////////////////////////////////////////////////////
if(isset($jsonString) and $jsonString!="")
{

	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/ReturnPath_Blacklisted/'.$today_date.'.csv';

	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);

    $obj = new commonFunctions($jsonString);

     $blacklistedDomainIdArr = $obj->getDomainId($obj->inputJsonArray['domain']);
	echo $blacklistedDomainId=$blacklistedDomainIdArr[0]; die;

    //Retain 'childPool_id' of all pools with given domain id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIdsOfRP($blacklistedDomainId);
	//print_r($childPoolIdsArray); //die;

    //delete all entries of the domain id 
    $obj->removeRPDomain($blacklistedDomainId);

    $logsArray["Action1"]="Domain Removed";

    // get new domain from warm up
    $warmedUpDomain = $obj->getRPDomainFromWarmUp($blacklistedDomainId);
    if($warmedUpDomain !='')
    {
         //replanish all the pools with new warmed-up domain 
    	  foreach($childPoolIdsArray as $childPoolId)
    	  {
    	  	$obj->replanishRPDomain($warmedUpDomain,$childPoolId[0]);
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
    $obj->putRPDomainInFreezer($blacklistedDomainId);
    $logsArray["Action3"]="IP put into Freezer";
	
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
	$subject="[Central ATM API] Email Alert to client for Returnpath domain Blacklist ";
	$message="Email Alert for Returnpath domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);

	//Send email alert to delivery team 
	$to="sarah.gidwani@nichelive.com";
	$subject="Central ATM API] Email Alert to Deliver for Returnpath domain Blacklist ";
	$message="Email Alert for Returnpath domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);


}


?>
