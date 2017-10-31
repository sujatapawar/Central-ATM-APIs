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
