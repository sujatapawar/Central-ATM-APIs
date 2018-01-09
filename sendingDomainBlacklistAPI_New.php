<?php
/*
1.  Put the listed domain into freezer.
2.  Put IPs belongs to the listed domain into new pool called available_assets.
3.  Replace the same IPs in all child pools with new IP from warmup. 
5.  Send email to service, delivery and client.
*/
include("commonFunctions.php");
///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":347,"domain":"nl1.sendm.net","ip_wise_counts":{"342":1}}';
//$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////
$AccountBlockStatus = 0;
$obj = new commonFunctions($jsonString);
// $obj->updateReq1Status("Stopped",'4'); die;
if(isset($jsonString) and $jsonString!="")
{
	//log file a name.
	$today_date = date("Y-m-d");
	$csvFileName = 'logs/Sending_Domain_Blacklisted/'.$today_date.'.csv';
	$logsArray["Date/Time"]=date("Y-m-d H:i:s");
	$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
	$logsArray["Request Type"]=$obj->get_request_type();
	
	$jsonData = json_decode($jsonString,true);
	 $IP_IDs = array_keys($jsonData['ip_wise_counts']);
	 $PMTAList = array();
	 $obj->connection_atm(); 
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
	 $Env_ID->execute(array($IP_IDs[0]));
	 $Env_Name = $Env_ID->fetch();
	
	// update Req1
          $obj->updateReq1Status("Stopped",'4');	
	
	// get main domain of listed domain
	 $main_domain = preg_replace("/^(.*\.)?([^.]*\..*)$/", "$2", $obj->inputJsonArray['domain']);
	
	// get all hosts (varients of main domain)
	 $sqlDomain = $Conn->prepare("select domain_id,domain_name from domain_master where domain_name like ? and type=? and domain_name!=? ");
	 
	 $sqlDomain->execute(array("%".$main_domain,"sending",$main_domain));
	 $domainsArray = $sqlDomain->fetchAll();
	//print_r($domainsArray);
	foreach($domainsArray as $domain){ // start of loop for all hosts
	//echo $domain['domain_name'];
        //fetch IP belongs to domain	
	 $ipIds = $obj->getDomainIpId($domain['domain_name']);
		
	$freezerIPArray= $obj->_dbHandlepdo->sql_Select("childPool_IPs", "IP_id", "where childPool_id=? and IP_id=?", array(10344,($ipIds[0]['IP_id']));	
	if(!isset($freezerIPArray[0]['IP_id'])) // check if IP is in freezer
	{
		//delete all entries of the IP_Id from all pools 
		$obj->removeIP($ipIds[0]['IP_id']);	  

		//put Ip in available_assets pool
		$obj->putAssetIntoAvailablePool($ipIds[0]['IP_id']);	
	}	
	
	// remove domain from domain_master and domain_mta_mapping table
	//$obj->removeDomain($domain['domain_id']);		
	 $obj->connection_atm(); 
         $obj->_dbHandlepdo->sql_update("server_master"," reload=1", " where server_id=(select mta from Domain_MTA_mapping where domain_id=?)", array($domain['domain_id']));		
        $obj->_dbHandlepdo->sql_delete("domain_master", " where domain_id=?", array($domain['domain_id']));
	
	$obj->_dbHandlepdo->sql_delete("Domain_MTA_mapping", " where domain_id=?", array($domain['domain_id']));
       
		
	
    }// end of loop for all hosts
	
	$mainDomainId=$obj->getDomainId($main_domain,"sending");

      // Deactivate the mail domain
	$obj->deactivateDomain($mainDomainId[0]['domain_id']);     
        $logsArray["Action1"]="Domain $main_domain is deactivated";
  
	//Insert main domain id into frezzer
	$obj->putDomainInFreezer($mainDomainId[0]['domain_id'],"childPool_SendingDomains");
	$logsArray["Action2"]="Domain $main_domain put into Freezer";
	
	//Releasing IP
	$IPID = $obj->releaseIP();
	$IPRelease = array();
	$obj->connection_atm();
	foreach($IPID as $I)
	{
	$IPRelease = $obj->_dbHandlepdo->sql_Select("IP_master", "IP", " where IP_id=?", array($I['IP_id']));
	}
	$obj->connection_disconnect();
	$logsArray["Action3"]=$json = "IPs are released";
	    
	//update IP wise count
	$obj->UpdateIPWiseCounts();
	$logsArray["Action4"]="IP wise counts are updated";
	
	if($obj->get_request_type()=="PostORPrep") 
       {
	/////////////// Blocking Sending functions //////////////////////////////////////////
	  $obj->connection_atm();	
	   $array = array($obj->req1);
           $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail", " where req1_id=?", $array);

	   $obj->connection_disconnect();
    
	    $obj->connection_db_mail_master();
		$array = array($Req1_Details[0]['cl_id']);
		$Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company,cl_email,pool_id", " where cl_id=?", $array);

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
			$AccountBlockStatus = 1;
		}
	    $obj->connection_disconnect();	
	
	 $logsArray["Action5"]="Sending functions are blocked";
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
	sleep(30);
	//Send email alert to client
	//$to = array("shripad.kulkarni@nichelive.com","mahesh.jagdale@nichelive.com");
	$subject="Your mailing ".$obj->req1." has been discontinued";
	$message  = "Dear ".$Client_Details[0]['cl_name'].",";
	$message .= "<p>Your mailing (details below) has caused our sending domain to be blacklisted. In order to protect further degradation of our infrastructure, your mailing has been stopped.</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
	$message .= "<tr><td><b>Sending Domain:  </b></td><td>".$obj->inputJsonArray['domain']."</td></tr>";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr></table>";
	$message .= "<tr><td><b>Total Sent:</b></td><td>".$jsonData['TotalSentCount']."</td></tr></table>";
	$message .= "<p>Please see the below log which clearly shows the Sending Domain Blacklisted that have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$jsonData['log']."</p>";
	$message .= "<p>Your mailing has degraded our infrastructure which will cause delivery problems for other clients using our software. As per Juvlon Terms of Use, credits will not be refunded for emails that were not sent.</p>";
	$message .= "Sincerely<br/>";
	$message .= "Juvlon Support";
	$obj->sendEmailAlert("shripad.kulkarni@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("mahesh.jagdale@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("support@juvlon.com",$subject,$message);
	$obj->sendEmailAlert($Client_Details[0]['cl_email'],$subject,$message);

	//Send email alert to delivery team 
	//$to=array("shripad.kulkarni@nichelive.com","mahesh.jagdale@nichelive.com");
	$obj->connection_atm();
	$poolName = $obj->_dbHandlepdo->sql_Select("pool_master", "pool_name", " where pool_id=?", array($Client_Details[0]['pool_id']));
	$subject="The entire Pool ".$poolName[0]['pool_name']." (id: ".$Client_Details[0]['pool_id'].") inactivated while sending out ".$obj->req1." for ".$Client_Details[0]['cl_name']." (".$Req1_Details[0]['cl_id'].")";
	$AccountBlockStatus = ($AccountBlockStatus==1)?"Yes":"No";
	$message  = "Hi,<br/>";
	$message .= "<p>The Juvlon delivery system has detected a Sending domain blacklisting during the sending activity of a client. As a result, the client's sending has been stopped and, some changes have been made in certain pools to ensure that the Sending domain does not get used for another sending.</p>";
	$message .= "<p>Please find below the details of the blacklisted Sending domain and the sending that caused the blacklisting:</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Req1_id: </b></td><td>".$obj->req1."</td></tr> ";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
	$message .= "<tr><td><b>Total Sent:</b> </td><td>".$jsonData['TotalSentCount']."</td></tr>";
	$message .= "<tr><td><b>Environment:</b></td><td>".$Env_Name['env_name']."</td></tr>";
	$message .= "<tr><td><b>List of PMTAs where this job ID was killed :</b></td><td>".implode(',',array_unique($PMTAList))."</td></tr>";
	$message .= "<tr><td><b>IPs released:</b></td><td>".implode(",",array_unique($IPRelease[0]))."</td></tr>";
	$message .= "<tr><td><b>Client's sending functions blocked?:</b></td><td>".$AccountBlockStatus."</td></tr></table>";
	$message .= "<p>Please see the below log which clearly shows the Sending Domain Blacklisted that have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails.</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$jsonData['log']."</p>";
	$message .= "<p>Please find below the changes made to replace the blacklisted Sending domain</p>";
	$message .= "<p>Inactivated Pools: <list of pool names and ids which do not have any IPs left as a result of this blacklisting></p>";
	$message .= "<p>Blacklisted sending domain moved to: Freezer</p>";
	$message .= "<p>All host names deleted: Yes</p>";
	$message .= "<p>Associated IPs moved to: Available Assets</p>";
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
	$subject="Central ATM API] Email Alert for Sending domain Blacklist ";
	$message="Blank Json Input";
	$obj->sendEmailAlert($to,$subject,$message);

}



?>
