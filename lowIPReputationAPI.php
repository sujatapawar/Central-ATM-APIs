<?php
/*
1. Remove IP from Pool (childPool_IPs) and put into warm-up (pool id 1)
2. Replace with warm-up IP (pool id 1) with same environment and same grade, with the last stage.
3. Release IP, and update counts
4. Add new entry into client_IP_detail with new IP assignment
5. Notify delivery team
6. Return (IP, PMTA, Domain)

*/

include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":56,"ip_id":530,"ip_wise_counts":{"530":10}}';
////////////////////////////////////////////////////////////////////////////////////////////////////
if(isset($jsonString) and $jsonString!="")
{

	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/Low_Reputation/'.$today_date.'.csv';

	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
	
    $obj = new commonFunctions($jsonString);

    $lowRepIPId = $obj->inputJsonArray['ip_id'];

    //Retain 'childPool_id' of all pools with given IP_Id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIds($lowRepIPId);

    //delete all entries of the IP_Id 
    $obj->removeIP($lowRepIPId);

    $logsArray["Action1"]="IP Removed";

    // get new IP from warm up
    $warmedUpIP = $obj->getIPFromWarmUp($lowRepIPId);
    if($warmedUpIP !='')
    {
         //replanish all the pools with new warmed-up IP 
    	  foreach($childPoolIdsArray as $childPoolId)
    	  {
    	  	$obj->replanishIP($warmedUpIP,$childPoolId[0]);
    	  }
        $logsArray["Action2"]="IP Replanied with Warmedup IP- $warmedUpIP";
		
		/* insert IP in client_ip_detail */
		$obj->insert_IP_in_ClientIP_Detail($warmedUpIP);
		
		/* return from lowIPReputationAPI */
		$obj->connection_atm();
		$Result = $obj->_dbHandlepdo->sql_Select("domain_master as dm, Domain_MTA_mapping as dmm", 
						 "dm.IP_id as 'IP ID', dm.domain_name as 'Sending Domain', dmm.mta as 'MTA Server ID', dmm.mta_name as 'MTA Server ID IP Address'", 
						 " where dm.domain_id=dmm.domain_id and dm.IP_id =?", 
						 array($warmedUpIP));

		$ReturnArray = array('ip_id'=>$warmedUpIP,
							 'sending_domain'=>$Result[0]['Sending Domain'],
							 'mta_ip_address'=>$Result[0]['MTA Server ID IP Address'],
							 'mta_id'=>$Result[0]['MTA Server ID']
							); 
		$obj->connection_disconnect();
		header('Content-Type: application/json');
		echo json_encode($ReturnArray);
		
    }
    else
    {
    	$logsArray["Action2"]="Warmedup IP not available";
 
    }

    //Insert bad ip id into warmup
    $obj->putIPInWarmup($lowRepIPId);
    $logsArray["Action3"]="IP put into Warmup";
	
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
	$to="shripad.kulkarni@nichelive.com";
	$subject="[Central ATM API] Email Alert to client for Low IP Reputation ";
	$message="Email Alert for Low IP Reputation from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);

	//Send email alert to delivery team 
	$to="shripad.kulkarni@nichelive.com";
	$subject="Central ATM API] Email Alert to Deliver for Low IP Reputation ";
	$message="Email Alert for Low IP Reputation from Central ATM API";
	$obj->sendEmailAlert($to,$subject,$message);

	
}



?>
