<?php
/*
1. Create exception in client account.
2. Release all IPs 
3. Update IP counts
4. Notify client, client servicing, delivery.
*/

include("commonFunctions.php");


///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
//$jsonString = '{"req1":38443,"spam_count":10,"ip_wise_counts":{"342":0,"352":"0"}}';
$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////
$obj = new commonFunctions($jsonString);

if(isset($jsonString) and $jsonString!="")
{
	
  //Give our CSV file a name.
    $today_date = date("Y-m-d");
    $csvFileName = 'logs/spam_exceptions/'.$today_date.'.csv';

    $logsArray["Date/Time"]=date("Y-m-d H:i:s");
    $logsArray["Input JSON "]=str_replace(","," ",$jsonString);
    
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

    $AccountBlockStatus=0;
     $logsArray["Request Type"]=$obj->get_request_type();		
    // update Req1
     $obj->updateReq1Status("Stopped",'8');	
    $json = $obj->inputJsonArray;
    if($obj->get_request_type()=="PostORPrep") 
   {	
    // Create Exception 
    $obj->connection_atm();
        $array = array($obj->req1);
        $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail", " where req1_id=?", $array);
    $obj->connection_disconnect();
    
    $obj->connection_db_mail_master();
        $array = array($Req1_Details[0]['cl_id']);
        $Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company,cl_email", " where cl_id=?", $array);
        
        $Client_Data = "ClientName: ".$Client_Details[0]['cl_name']."\n Company Name:".$Client_Details[0]['cl_company']."\n Mailer-ID:".$Req1_Details[0]['mailer_id']."\n Sent Date:".$Req1_Details[0]['created_time']."\n Total Sent:".$Req1_Details[0]['total_unique_mail']."\n Spam Count:".$json['spam_count'];
        $array=array(3,$Req1_Details[0]['cl_id'],$Req1_Details[0]['mailer_id'],date('Y-m-d H:i:s'),$Req1_Details[0]['created_time'],$Client_Data,'open');
        
        $Exception_ID = $obj->_dbHandlepdo->sql_insert("client_exceptions", " exception_type_id,exception_client_id,exception_object_id,exception_open_date_time,exception_closed_date_time,exception_data,exception_status", $array);
       $obj->_dbHandlepdo->sql_insert("exception_process_log", " exception_id,datetime,initiated_by,account_exception_id", array($Exception_ID,date('Y-m-d H:i:s'),'Client',$Exception_ID));
	$Exception_Details = $obj->_dbHandlepdo->sql_Select("client_exceptions", "count(exception_id) as cnt", " where exception_type_id=? and exception_client_id=? and exception_status=?", array($array[0],$array[1],$array[6])); 
       if($Exception_Details[0]['cnt']>2) // check if more than 2 exceptions already exist
        {	
	    $array = array(32,$Exception_ID,$Req1_Details[0]['cl_id']);
            $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
            $array = array(33,$Exception_ID,$Req1_Details[0]['cl_id']);
            $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
            $AccountBlockStatus=1;
	}
    $obj->connection_disconnect();
   
    $logsArray["Action1"]="Execption generated and sending functions are blocked";
 } //close if of get_request_type
  else
  {
    $logsArray["Action1"]="";
  }
    
    //Releasing IP
    $IPID = $obj->releaseIP();
    $IPRelease = array();
    $obj->connection_atm();
    foreach($IPID as $I)
    {
      $IPRelease = $obj->_dbHandlepdo->sql_Select("IP_master", "IP", " where IP_id=?", array($I['IP_id']));
    }
    $obj->connection_disconnect();
    $logsArray["Action2"] = "IPs are released";
        
    //update IP wise count
    $obj->UpdateIPWiseCounts();
    $logsArray["Action3"]="IP wise counts are updated";  
  
	

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
    sleep(90);
    $SpamComplaintsLog = $obj->get_log($obj->req1."_spam_complaints.txt","Spam");
    //Send email alert to client
   // $to = array("mahesh.jagdale@nichelive.com");
    $subject="Your mailing ".$obj->req1." has been discontinued";
    $message  = "Dear ".$Client_Details[0]['cl_name'].",<br/>";
    $message .= "<p>Your mailing (details below) has resulted in more than 3% spam complaints. In order to protect any degradation of our infrastructure, your mailing has been stopped.</p>";
    $message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
    $message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
    $message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
    $message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
    $message .= "<tr><td><b>Total Sent:</b></td><td>".$json['TotalSentCount']."</td></tr>";
    $message .= "<tr><td><b>Total Spam Complaints:</b></td><td>".$json['spam_count']."</td></tr></table>";
    $message .= "<p>Please see the URL below which clearly shows that the spam complaints have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails</p>";
    $message .= "<p><b>URL:</b><a href='http://".BOUNCE_SERVER."/juvlon_bounce_process/bounce_processor/imported/".$obj->req1."_spam_complaints.txt'> Spam Logs</a></p>";
    $message .= "<p>Your mailing may have degraded our infrastructure which will cause delivery problems for other clients using our software. As per Juvlon Terms of Use, credits will not be refunded for emails that were not sent.</p>";
    $message .= "Sincerely<br/>";
    $message .= "Juvlon Support";
    $obj->sendEmailAlert("shripad.kulkarni@nichelive.com",$subject,$message);
    $obj->sendEmailAlert("mahesh.jagdale@nichelive.com",$subject,$message);
    $obj->sendEmailAlert("support@juvlon.com",$subject,$message);
    $obj->sendEmailAlert($Client_Details[0]['cl_email'],$subject,$message);

    //Send email alert to delivery team 
    //$to=array("mahesh.jagdale@nichelive.com");
    $subject="Spam complaints exception occurred for ".$obj->req1." of ".$Client_Details[0]['cl_name']." (".$Req1_Details[0]['cl_id'].")";
    $AccountBlockStatus = ($AccountBlockStatus==1)?"Yes":"No";
    $message  = "Hi,<br/>";
    $message .= "<p>The Juvlon delivery system has detected a spam complaints exception during the sending activity of a client. As a result, the client's sending has been stopped.</p>";
    $message .= "<p>Please find below the details of the sending that caused the spam complaints exception:</p>";
    $message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
    $message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
    $message .= "<tr><td><b>Req1_id: </b></td><td>".$obj->req1."</td></tr> ";
    $message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
    $message .= "<tr><td><b>Total Sent:</b> </td><td>".$json['TotalSentCount']."</td></tr>";
    $message .= "<tr><td><b>Total spam complaints: </b></td><td>".$json['spam_count']." </td></tr>";
    $message .= "<tr><td><b>Environment:</b></td><td>-</td></tr>";
    $message .= "<tr><td><b>List of PMTAs where this job ID was killed :</b></td><td>".implode(',',array_unique($PMTAList))."</td></tr>";
    $message .= "<tr><td><b>IPs released:</b></td><td>".implode(",",array_unique($IPRelease[0]))."</td></tr>";
    $message .= "<tr><td><b>Client's sending functions blocked?:</b></td><td>".$AccountBlockStatus."</td></tr></table>";
    $message .= "<p>Please see the URL below which clearly shows that the spam complaints have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails</p>";
    $message .= "<p><b>URL:</b><a href='http://".BOUNCE_SERVER."/juvlon_bounce_process/bounce_processor/imported/".$obj->req1."_spam_complaints.txt'>Spam Logs</a></p>";
 +  $message .= "Regards<br/>";
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
	$subject="Central ATM API] Email Alert for Spam Exception ";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);
}


?>
