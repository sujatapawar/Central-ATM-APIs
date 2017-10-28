<?php
/*
1. Put Domain into freezer
2. Replace with a new domain (with same grade and environment, and last stage) from warm-up
3. Check the number of times that any domain has been blacklisted during this client's sendings 
(irrespective of the type of domain that was blacklisted before)
4. "If any domain is blacklisted for the first time for the client, then:
- Notify the delivery team of the changes carried out in the pool(s)
- Return the domain blacklist count of the client and the new domain for the ATM2.0 to resume compilation.

Or

If the number of times that the domain is blacklisted for this client exceeds 1, then:
- Release all IPs, and update counts
- Notify client, client servicing and delivery team
- Return the domain blacklist count of the client"
5. 

*/

include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":59,"domain":"mail.yesbank.in","ip_wise_counts":{"342":3000,"352":2000}}';
////////////////////////////////////////////////////////////////////////////////////////////////////
if(isset($jsonString) and $jsonString!="")
{

	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/Link_Domain_Blacklisted/'.$today_date.'.csv';

	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);

    $obj = new commonFunctions($jsonString);

     $blacklistedDomainIdArr = $obj->getDomainId($obj->inputJsonArray['domain']);
    $blacklistedDomainId=$blacklistedDomainIdArr[0]['domain_id']; //die;

    //Retain 'childPool_id' of all pools with given domain id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIdsOfDomains($blacklistedDomainId,"childPool_LinkDomains");
	//print_r($childPoolIdsArray); //die;

    //delete all entries of the domain id 
    $obj->removeDomain($blacklistedDomainId,"childPool_LinkDomains");

    $logsArray["Action1"]="Domain Removed";

    // get new domain from warm up
    $warmedUpDomain = $obj->getDomainFromWarmUp($blacklistedDomainId,"childPool_LinkDomains");
    if($warmedUpDomain !='')
    {
         //replanish all the pools with new warmed-up domain 
    	  foreach($childPoolIdsArray as $childPoolId)
    	  {
    	  	$obj->replanishDomain($warmedUpDomain,$childPoolId[0],"childPool_LinkDomains");
		 echo "\n $childPoolId[0] Replanied with Warmedup Domain- $warmedUpDomain";
    	  }
	//die;    
        $logsArray["Action2"]="Domain Replanied with Warmedup Domain - $warmedUpDomain";
      
      // returning clean domain with blacklist count
      $warmedUpDomainName = $obj->getDomainName($warmedUpDomain);
      $ReturnArray = array( "count_blacklisted" => "1","clean_domain" => $warmedUpDomainName[0]['domain_name']);
      header('Content-Type: application/json');
		  echo json_encode($ReturnArray);
    }
    else
    {
    	$logsArray["Action2"]="Warmedup Domain not available";
      $ReturnArray = array( "count_blacklisted" => "1","clean_domain" => "NOT AVAILABLE");
      header('Content-Type: application/json');
		  echo json_encode($ReturnArray);
 
    }

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
	$subject="[Central ATM API] Email Alert to client for Link/Image domain Blacklist ";
	$message="Email Alert for Returnpath domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);

	//Send email alert to delivery team 
	$to="sarah.gidwani@nichelive.com";
	$subject="Central ATM API] Email Alert to Deliver for Link/Image domain Blacklist ";
	$message="Email Alert for Returnpath domain Blacklist from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);


}


?>



?>
