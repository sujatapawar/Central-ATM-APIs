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
//$jsonString = '{"req1":417,"ip_id":342,"ip_wise_counts":{"342":0, "861":0},"log":"b,2017-09-22 16:31:06+0530,2017-09-22 16:30:42+0530,heather_morrison@edwards.com,failed,5.3.2 (system not accepting network messages),Smtp;550 5.7.1 spam URL , Barracuda, www.niche.com,spam-related,10.136.27.30,ml93patrafinnet,722#1282788#74793#2017-09-22 16:49:14#192276#1218#o#P209#15991#APP64235581#189#229,,15991","agency_id":1}';
$jsonString = file_get_contents('php://input');

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
	if($obj->isTestIP($blacklistedIPId))
	{
	  $flagTestIP = TRUE;
	}
	else
	{
	  $flagTestIP = FALSE;
	}
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
	 $Env_ID->execute(array($blacklistedIPId));
	 $Env_Name = $Env_ID->fetch();
	 $obj->connection_disconnect();
	 $AccountBlockStatus = 0;
     // update Req1
     $obj->updateReq1Status("Stopped",'1');		

    //Retain 'childPool_id' of all pools with given IP_Id in an array 
    $childPoolIdsArray = $obj->getAllChildPoolIds($blacklistedIPId);
    if($flagTestIP)
	  $testChildPoolIdsArray = $obj->getAllTestChildPoolIds($blacklistedIPId);    
	//print_r($childPoolIdsArray); //die;

    //delete all entries of the IP_Id  
    $obj->removeIP($blacklistedIPId);
    $logsArray["Action1"]="IP Removed";
	
   $obj->connection_atm();
   $array = array($obj->req1);
   $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail,assigned_priority", " where req1_id=?", $array);
   $AssignIP = $obj->_dbHandlepdo->sql_Select("IP_master", "IP", " where IP_id=?", array($blacklistedIPId));
   $Env_ID = $obj->_dbHandlepdo->sql_Select("pool_master", "pool_name", " where pool_id=?", array($Req1_Details[0]['assigned_priority']));
   $obj->connection_disconnect();	
	
    $obj->putAssetLog($blacklistedIPId,1,"IP Blacklisted","Req1=$obj->req1,IP=".$AssignIP[0]['IP'].",Agency id=".$jsonData['agency_id'].",Captured by=ATM2");
    $obj->logBlacklistingTransactions($blacklistedIPId,1,$jsonData['agency_id']);
    $obj->putAssetLog($blacklistedIPId,1,"IP removed from pool","Req1=$obj->req1,Reason=IP ".$AssignIP[0]['IP']." got blacklisted,Pool Id=".$Req1_Details[0]['assigned_priority'].",Done by=ATM2");	

    // get new IP from warm up
	$warmedUpIP = $obj->getIPFromWarmUp($blacklistedIPId);
	$warmedUpIPAddr = $obj->getIPAddrWarmUp($warmedUpIP);
	$warmedUpIPAddr = ($warmedUpIPAddr['IP']!='')?$warmedUpIPAddr['IP']:"None (no appropriate IP available in warm-up pool)"; 
	if($warmedUpIP !='')
       {
         //replanish all the pools with new warmed-up IP 
	 if(count($childPoolIdsArray)>0)
	  {	
		  foreach($childPoolIdsArray as $childPoolId)
		  {
			$obj->replanishIP($warmedUpIP,$childPoolId[0]);
			 echo "\n $childPoolId[0] Replanied with Warmedup IP- $warmedUpIP";
			 $obj->putAssetLog($warmedUpIP,1,"IP assigned to child-pool ","Req1=$obj->req1,child-pool Id=$childPoolId[0],Reason=IP ".$AssignIP[0]['IP']." got blacklisted,Done by=ATM2");  
		  } 
	 }	
	  if(count($testChildPoolIdsArray)>0)
	  {
	     foreach($testChildPoolIdsArray as $testChildPoolId)
    	 	 {
    	  		$obj->replanishTestIP($warmedUpIP,$testChildPoolId[0]);
			echo "\n $testChildPoolId[0] Replanied with Warmedup IP- $warmedUpIP";
		        $obj->putAssetLog($warmedUpIP,1,"IP assigned to test child-pool","Req1=$obj->req1,child-pool Id=$testChildPoolId[0],Reason=IP ."$AssignIP[0]['IP']." got blacklisted,Done by=ATM2");  
    	  	}
	  
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
    	
     $obj->putAssetLog($blacklistedIPId,1,"IP put into freezer","Req1=$obj->req1,Reason=IP ".$AssignIP[0]['IP']." got blacklisted,Done by=ATM2");  
	
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
	
 if($obj->get_request_type()=="PostORPrep" and !in_array($jsonData[agency_id],array(3,4,5))) 
    {	
		
    /////////////// Blocking Sending functions //////////////////////////////////////////
	   /*$obj->connection_atm();
	   $array = array($obj->req1);
           $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail,assigned_priority", " where req1_id=?", $array);
		   $AssignIP = $obj->_dbHandlepdo->sql_Select("IP_master", "IP", " where IP_id=?", array($blacklistedIPId));
		   $Env_ID = $obj->_dbHandlepdo->sql_Select("pool_master", "pool_name", " where pool_id=?", array($Req1_Details[0]['assigned_priority']));
	   $obj->connection_disconnect();*/
    
	    $obj->connection_db_mail_master();
		$array = array($Req1_Details[0]['cl_id']);
		$Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company,cl_email", " where cl_id=?", $array);

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
			$AccountBlockStatus =1;
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
	
	
	//$obj->connection_atm();
	// Total Sent Count
	//$SentCount = $obj->getSentCount($obj->req1);
	//$obj->connection_disconnect();
	//$BlacklistLog = $obj->get_log($obj->req1."_soft_bounces.txt","BlacklistIP");


	//Send email alert to client
	//$to = array("shripad.kulkarni@nichelive.com","mahesh.jagdale@nichelive.com");
	if($obj->get_request_type()=="PostORPrep" and !in_array($jsonData[agency_id],array(3,4,5))) 
    {	
	$subject="Your mailing ".$obj->req1." has been discontinued";
	$message  = "Dear ".$Client_Details[0]['cl_name'].",";
	$message .= "<p>Your mailing (details below) has caused our sending IP to be blacklisted. In order to protect further degradation of our infrastructure, your mailing has been stopped.</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
	$message .= "<tr><td><b>Assigned IP: </b></td><td>".$AssignIP[0]['IP']."</td></tr>";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
	$message .= "<tr><td><b>Total Sent:</b></td><td>".$jsonData['TotalSentCount']." (approx.)</td></tr></table>";
	$message .= "<p>Please see the log(s) below which clearly shows that the IP-blacklisting has occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails.</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$jsonData['log']."</p>";
	$message .= "<p>Your mailing has degraded our infrastructure which will cause delivery problems for other clients using our software. As per Juvlon Terms of Use, credits will not be refunded for emails that were not sent.</p>";
	$message .= "Sincerely<br/>";
	$message .= "Juvlon Support";
	/*foreach($to as $t)
	{
		$obj->sendEmailAlert($t,$subject,$message);
	}*/
	$obj->sendEmailAlert("shripad.kulkarni@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("mahesh.jagdale@nichelive.com",$subject,$message);
	$obj->sendEmailAlert("support@juvlon.com",$subject,$message);
	$obj->sendEmailAlert($Client_Details[0]['cl_email'],$subject,$message);
     }
	
	//Send email alert to delivery team 
	//$to=array("shripad.kulkarni@nichelive.com","mahesh.jagdale@nichelive.com");
	$subject="IP ".$AssignIP[0]['IP']." blacklisted while sending out ".$obj->req1." for ".$Client_Details[0]['cl_name']." (".$Req1_Details[0]['cl_id'].")";
	$AccountBlockStatus = ($AccountBlockStatus==1)?"Yes":"No";
	$message  = "Hi,<br/>";
	$message .= "<p>The Juvlon delivery system has detected an IP blacklisting during the sending activity of a client. As a result, the client's sending has been stopped and some changes have been made in certain pools to ensure that the blacklisted IP does not get used for another sending.</p>";
	$message .= "<p>Please find below the details of the blacklisted IP and the sending that caused the blacklisting:</p>";
	$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
	$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
	$message .= "<tr><td><b>Req1_id: </b></td><td>".$obj->req1."</td></tr> ";
	$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
	$message .= "<tr><td><b>Total Sent:</b> </td><td>".$jsonData['TotalSentCount']." (approx.)</td></tr>";
	$message .= "<tr><td><b>Environment:</b></td><td>".$Env_Name['env_name']."</td></tr>";
	$message .= "<tr><td><b>List of PMTAs where this job ID was killed :</b></td><td>".implode(',',array_unique($PMTAList))."</td></tr>";
	$message .= "<tr><td><b>IPs released:</b></td><td>".implode(",",array_unique($IPRelease[0]))."</td></tr>";
	$message .= "<tr><td><b>Client's sending functions blocked?:</b></td><td>".$AccountBlockStatus."</td></tr></table>";
	$message .= "<p>Please see the log(s) below which clearly shows that the IP-blacklisting has occurred during the mailing.</p>";
	$message .= "<p><b>Log:</b></p>";
	$message .= "<p>".$jsonData['log']."</p>";
	$message .= "<p>Blacklisted IP moved to: Freezer</p>";
	$message .= "<p>New IP picked from warm-up: ".$warmedUpIPAddr." (id:".$warmedUpIP.")</p>";
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
	$subject="[Central ATM API] Email Alert for IP Blacklist ";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);

}


?>
