<?php
/*
1. Put Domain into freezer
2. Replace with a new domain (with same grade and environment, and last stage) from warm-up
3. Release all IPs, and update counts
4. Notify client, client servicing and delivery team
*/

include("commonFunctions.php");

///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
//$jsonString = '{"req1":38443,"domain":"sendm.net","ip_wise_counts":{"342":0,"352":0},"log":"b,2017-09-22 16:31:06+0530,2017-09-22 16:30:42+0530,heather_morrison@edwards.com,failed,5.3.2 (system not accepting network messages),Smtp;550 5.7.1 spam URL , Barracuda, www.niche.com,spam-related,10.136.27.30,ml93patrafinnet,722#1282788#74793#2017-09-22 16:49:14#192276#1218#o#P209#15991#APP64235581#189#229,,15991"}';
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
	$jsonData = json_decode($jsonString,true);
	$AccountBlockStatus =0;
	
	$IP_IDs = array_keys($jsonData['ip_wise_counts']);
	$PMTAList = array();
	$obj->connection_atm();
	foreach($IP_IDs as $IP_ID)
	{
		$Domain = $obj->_dbHandlepdo->sql_Select("domain_master", "domain_id", " where IP_id=?", array($IP_ID));
		$PMTAName = $obj->_dbHandlepdo->sql_Select("Domain_MTA_mapping", "mta_name", " where domain_id=?", array($Domain[0]['domain_id']));
		$PMTAList[] = $PMTAName[0]['mta_name'];
	}
	$obj->connection_disconnect();
	
      $logsArray["Request Type"]=$obj->get_request_type();
      // update Req1
     $obj->updateReq1Status("Stopped");		     

     $blacklistedDomainIdArr = $obj->getDomainId($obj->inputJsonArray['domain'],"return_path");
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
	    
	$DomainName = $obj->_dbHandlepdo->sql_Select("domain_master", "domain_name", " where domain_id=?", array($warmedUpDomain));    
    }
    else
    {
    	$logsArray["Action2"]="Warmedup Domain not available";
 
    }

    //Insert bad domain id into frezzer
    $obj->putDomainInFreezer($blacklistedDomainId,"childPool_RPDomains");
    $logsArray["Action3"]="Domain put into Freezer";
	
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
	
	
	 if($obj->get_request_type()=="PostORPrep") 
    {
	/////////////// Blocking Sending functions //////////////////////////////////////////
	   $obj->connection_atm();	 
	   $array = array($obj->req1);
           $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail", " where req1_id=?", $array);

	   $obj->connection_disconnect();
    
	    $obj->connection_db_mail_master();
		$array = array($Req1_Details[0]['cl_id']);
		$Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company,pool_id,cl_email", " where cl_id=?", $array);

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
			$AccountBlockStatus =1;
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
	$obj->connection_atm();
	// Total Sent Count
	$SentCount = $obj->getSentCount($obj->req1);
	$obj->connection_disconnect();

	//$BlacklistDomainLog = $obj->get_log($obj->req1."_soft_bounces.txt","BlacklistDomain");
	//Send email alert to client
	$to = array("mahesh.jagdale@nichelive.com","shripad.kulkarni@nichelive.com");
	$subject="Your mailing ".$obj->req1." has been discontinued";
	$message  = "Dear ".$Client_Details[0]['cl_name'].",";
	$message .= "<p>Your mailing (details below) has caused our return path domain to be blacklisted. In order to protect further degradation of our infrastructure, your mailing has been stopped.</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
	$message .= "<tr><td><b>Return Path Domain: </b></td><td>".$obj->inputJsonArray['domain']."</td></tr>";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr></table>";
	$message .= "<tr><td><b>Total Sent:</b></td><td>".$SentCount."</td></tr></table>";
	$message .= "<p>Please see the below log which clearly shows the return path domain blacklisted that have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$jsonData['log']."</p>";
	$message .= "<p>Your mailing has degraded our infrastructure which will cause delivery problems for other clients using our software. As per Juvlon Terms of Use, credits will not be refunded for emails that were not sent.</p>";
	$message .= "Sincerely<br/>";
	$message .= "Juvlon Support";
	$obj->sendEmailAlert("shripad.kulkarni@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("mahesh.jagdale@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("support@juvlon.com",$subject,$message);
	$obj->sendEmailAlert($Client_Details[0]['cl_email'],$subject,$message);

	$to = array("mahesh.jagdale@nichelive.com","shripad.kulkarni@nichelive.com");
	$subject="Return Path domain ".$obj->inputJsonArray['domain']." blacklisted while sending out ".$obj->req1." for ".$Client_Details[0]['cl_name']." (".$Req1_Details[0]['cl_id'].")";
	$AccountBlockStatus = ($AccountBlockStatus==1)?"Yes":"No";
	$message  = "Hi,";
	$message .= "<p>The Juvlon delivery system has detected a return path domain blacklisting during the sending activity of a client. As a result, the client's sending has been stopped and some changes have been made in certain pools to ensure that the return path domain does not get used for another sending.</p>";
	$message .= "<p>Please find below the details of the blacklisted return path domain and the sending that caused the blacklisting:</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
	$message .= "<tr><td><b>Client's Pool ID: </b></td><td>".$Client_Details[0]['pool_id']."</td></tr>";
	$message .= "<tr><td><b>Blacklisted return path domain: : </b></td><td>".$obj->inputJsonArray['domain']."</td></tr>";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
	$message .= "<tr><td><b>Total Sent:</b></td><td>".$SentCount."</td></tr>";
	$message .= "<tr><td><b>Environment: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
	$message .= "<tr><td><b>List of PMTAs where this job ID was killed: </b></td><td>".implode(',',array_unique($PMTAList))."</td></tr>";
	$message .= "<tr><td><b>IPs released: </b></td><td>".implode(",",array_unique($IPRelease[0]))."</td></tr>";
	$message .= "<tr><td><b>Client's sending functions blocked? </b></td><td>".$AccountBlockStatus."</td></tr></table>";
	$message .= "<p>Please see the below log which clearly shows the return path domain blacklisted that have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$jsonData['log']."</p>";
	$message .= "<p>Please find below the changes made to replace the blacklisted return path domain:</p>";
	$message .= "<p>Blacklisted return path domain moved to: Freezer</p>";
	//$message .= "<p>Pool IDs from where the return path domain was removed: <list of all pool ids where the blacklisted return path domain belonged></p>";
	if($DomainName[0]['domain_name']!='')
	  $message .= "<p>New return path domain picked from warm-up: ".$DomainName[0]['domain_name']." (id: ".$warmedUpDomain.") ";
	else 
	 $message .= "<p>New return path domain picked from warm-up: None (no appropriate return path domains available in warm-up pool)</p>";
	//$message .= "<p>Pool IDs where the new return path domain is added: <list of all pool ids> / None (if no return path domain was found from the warm-up pool)</p>";
	$message .= "Sincerely<br/>";
	$message .= "Juvlon Support";
	$obj->sendEmailAlert("shripad.kulkarni@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("mahesh.jagdale@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("techsupport@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("delivery@nichelive.com",$subject,$message);

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
