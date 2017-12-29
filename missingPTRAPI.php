<?php
/*
1. Remove IP from Pool (childPool_IPs) and put into Available Assets Pool
2. Replace with warm-up IP (pool id 1) with same environment and same grade, with the last stage.
3. Release IP, and update counts
4. Add new entry into client_IP_detail with new IP assignment
5. Notify delivery team
6. Return (IP, PMTA, Domain)
*/
include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
//$jsonString = '{"req1":38443,"ip_id":342,"ip_wise_counts":{"342":0,"352":0}}';
$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////
$obj = new commonFunctions($jsonString);
if(isset($jsonString) and $jsonString!="")
{

	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/Missing_PTR/'.$today_date.'.csv';

	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
	
   	$jsonData = json_decode($jsonString,true);
	

    $missedPTRIP = $obj->inputJsonArray['ip_id'];
	$obj->connection_atm();
	$AssignIP = $obj->_dbHandlepdo->sql_Select("IP_master", "IP", " where IP_id=?", array($missedPTRIP));
	$Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail,assigned_priority", " where req1_id=?", array($obj->req1));
	$IP_IDs = array_keys($jsonData['ip_wise_counts']);
	$PMTAList = array();
	foreach($IP_IDs as $IP_ID)
	{
		$Domain = $obj->_dbHandlepdo->sql_Select("domain_master", "domain_id", " where IP_id=?", array($IP_ID));
		$PMTAName = $obj->_dbHandlepdo->sql_Select("Domain_MTA_mapping", "mta_name", " where domain_id=?", array($Domain[0]['domain_id']));
		$PMTAList[] = $PMTAName[0]['mta_name'];
	}

	$Conn = $obj->_dbHandlepdo->get_connection_variable();
	$Env_ID = $Conn->prepare(
								"select env_name 
								from enviornment_master
								join IP_master on enviornment_master.env_id = IP_master.env_id
								where IP_master.IP_id = ?
								"
							);
	$Env_ID->execute(array($missedPTRIP));
	$Env_Name = $Env_ID->fetch();
	$obj->connection_disconnect();
	
	$obj->connection_db_mail_master();
	$Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company,pool_id", " where cl_id=?", array($Req1_Details[0]['cl_id']));
	
	$obj->connection_disconnect();
    //Retain 'childPool_id' of all pools with given IP_Id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIds($missedPTRIP);

    //delete all entries of the IP_Id 
    $obj->removeIP($missedPTRIP);

    $logsArray["Action1"]="IP Removed";

    // get new IP from warm up
    $warmedUpIP = $obj->getIPFromWarmUp($missedPTRIP);
	if($warmedUpIP !='')
    {
		//replanish all the pools with new warmed-up IP 
		foreach($childPoolIdsArray as $childPoolId)
		{
		$obj->replanishIP($warmedUpIP,$childPoolId[0]);
		}
		$logsArray["Action2"]="IP Replanied with Warmedup IP- $warmedUpIP";

		
		
		// return from missingPTRAPI 
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

    //Insert bad ip id into Available pool
    $obj->putAssetIntoAvailablePool($missedPTRIP);
    $logsArray["Action3"]="IP put into Warmup";
	
	//Releasing IP
	$IPID = $obj->releaseIP();
	$IPRelease = array();
	$obj->connection_atm();
	foreach($IPID as $I)
	{
	$IPRelease = $obj->_dbHandlepdo->sql_Select("IP_master", "IP", " where IP_id=?", array($I['IP_id']));
	}
	$obj->connection_disconnect();
	$logsArray["Action4"]=$json = "IPs are released";
	    
	//update IP wise count
	$obj->UpdateIPWiseCounts();
	$logsArray["Action5"]="IP wise counts are updated";
	
	// insert IP in client_ip_detail 
	if($warmedUpIP!='')
		$obj->insert_IP_in_ClientIP_Detail($warmedUpIP,$missedPTRIP);
   

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

	$obj->connection_atm();
	// Total Sent Count
	$SentCount = $obj->getSentCount($obj->req1);
	$obj->connection_disconnect();
	sleep(30);
	$MissingPTR = $obj->get_log($obj->req1."_soft_bounces.txt","MissingPTR");

	//Send email alert to client
	$warmedUpIP = ($warmedUpIP!="")?"<IP address> (id: ".$warmedUpIP.")":"None";
	//$to = array("mahesh.jagdale@nichelive.com","shripad.kulkarni@nichelive.com");
	$subject="IP ".$AssignIP[0]['IP']." with missing PTRs while sending out ".$obj->req1." for ".$Client_Details[0]['cl_name']." (".$Req1_Details[0]['cl_id'].")";
	$message  = "Hi,<br/>";
	$message .= "<p>The Juvlon delivery system has detected an IP with missing PTRs during the sending activity of a client. As a result, the client's sending was paused and resumed using another IP, and some changes were made in certain pools to ensure that the IP with missing PTRs does not get used for another sending.</p>";
	$message .= "<p>Please find below the details of the IP with missing PTRs, and the sending that was paused and resumed:</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
	$message .= "<tr><td><b>Client's Pool ID: </b></td><td>".$Client_Details[0]['pool_id']."</td></tr>";
	$message .= "<tr><td><b>IP with missing PTR: </b></td><td>".$AssignIP[0]['IP']." (id: ".$missedPTRIP.")</td></tr>";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
	$message .= "<tr><td><b>Total Sent: </b></td><td>".$SentCount."</td></tr>";
	$message .= "<tr><td><b>Environment: </b></td><td>".$Env_Name['env_name']."</td></tr>";
	$message .= "<tr><td><b>List of PMTAs where this job ID was killed: </b></td><td>".implode(',',array_unique($PMTAList))."</td></tr>";
	$message .= "<tr><td><b>IPs released: </b></td><td>".implode(",",array_unique($IPRelease[0]))."</td></tr></table>";
	$message .= "<p>Please see the below log which clearly shows the Missing PTR that have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$MissingPTR."</p>";
	$message .= "<p>Please find below the changes made to replace the IP with missing PTRs:</p>";
	$message .= "<p>IP with missng PTR moved to:Available Assets</p>";
	//$message .= "<p>Pool IDs from where the IP was removed: <list of all pool ids where the IP with missing PTRs belonged></p>";
	$message .= "<p>New IP picked from warm-up: ".$warmedUpIP."</p>";
	//$message .= "<p>Pool IDs where the new IP is added: <list of all pool ids> / None (if no IP was found from the warm-up pool)</p>";
	$message .= "Regards<br/>";
	$message .= "Juvlon Delivery System";
	$obj->sendEmailAlert("shripad.kulkarni@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("mahesh.jagdale@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("techsupport@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("delivery@nichelive.com",$subject,$message);

	
}
else
{
	//Send email alert to delivery team 
	$to="shripad.kulkarni@nichelive.com";
	$subject="Central ATM API] Email Alert for Missing PTR ";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);
}
?>
