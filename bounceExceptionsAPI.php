<?php
/*
1. Create exception in client account.
2. Release all IPs 
3. Update IP counts
4. Notify client, client servicing, delivery.
*/
include("commonFunctions.php");
///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
//$jsonString = '{"req1":209,"bounce_count":10,"ip_wise_counts":{"342":10,"352":10}}';
$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////

$obj = new commonFunctions($jsonString);
if(isset($jsonString) and $jsonString!=""){
	
//Give our CSV file a name.
$today_date = date("Y-m-d");
$csvFileName = 'logs/bounce_exceptions/'.$today_date.'.csv';

$logsArray["Date/Time"]=date("Y-m-d H:i:s");
$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
$AccountBlockStatus = 0;	
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
   
  // update Req1
   $obj->updateReq1Status("Stopped");		
	
   $logsArray["Request Type"]=$obj->get_request_type();	
    
    $json = $obj->inputJsonArray;
   if($obj->get_request_type()=="PostORPrep") 
 {	
    // Create Exception
    $obj->connection_atm();
        $array = array($obj->req1);
        $Req1_Details = $obj->_dbHandlepdo->sql_Select("Req1", "cl_id,mailer_id,created_time,total_unique_mail,assigned_priority", " where req1_id=?", $array);
    $obj->connection_disconnect();
    
    $obj->connection_db_mail_master();
        $array = array($Req1_Details[0]['cl_id']);
        $Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company,cl_email", " where cl_id=?", $array);
        
        $Client_Data = "ClientName: ".$Client_Details[0]['cl_name']."\n Company Name:".$Client_Details[0]['cl_company']."\n Mailer-ID:".$Req1_Details[0]['mailer_id']."\n Sent Date:".$Req1_Details[0]['created_time']."\n Total Sent:".$Req1_Details[0]['total_unique_mail']."\n Bounce Count:".$json['bounce_count'];
        $array=array(2,$Req1_Details[0]['cl_id'],$Req1_Details[0]['mailer_id'],date('Y-m-d H:i:s'),$Req1_Details[0]['created_time'],$Client_Data,'open');
        $Exception_ID = $obj->_dbHandlepdo->sql_insert("client_exceptions", " exception_type_id,exception_client_id,exception_object_id,exception_open_date_time,exception_closed_date_time,exception_data,exception_status", $array);
        $obj->_dbHandlepdo->sql_insert("exception_process_log", " exception_id,datetime,initiated_by,account_exception_id", array($Exception_ID,date('Y-m-d H:i:s'),'Client',$Exception_ID));
	$Exception_Details = $obj->_dbHandlepdo->sql_Select("client_exceptions", "count(exception_id) as cnt", " where exception_type_id=? and exception_client_id=? and exception_object_id=? and exception_status=?", array($array[0],$array[1],$array[2],$array[6])); 
       if($Exception_Details[0]['cnt']>2) // check if more than 2 exceptions already exist
        {	
            $AccountBlockStatus=1;
	    $array = array(32,$Exception_ID,$Req1_Details[0]['cl_id']);
            $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
            $array = array(33,$Exception_ID,$Req1_Details[0]['cl_id']);
            $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
         
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

//Send email alert to client
$to = array("shripad.kulkarni@nichelive.com","mahesh.jagdale@nichelive.com");
$subject="Your mailing ".$obj->req1." has been discontinued";
$message  = "Dear ".$Client_Details[0]['cl_name'].",<br/>";
$message .= "<p>Your mailing (details below) has resulted in more than 10% hard bounces. In order to protect any degradation of our infrastructure, your mailing has been stopped.</p>";
$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
$message .= "<tr><td><b>Sending Request ID: </b></td><td>".$obj->req1."</td></tr>";
$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
$message .= "<tr><td><b>Total Sent:</b></td><td>-</td></tr>";
$message .= "<tr><td><b>Total hard bounces:</b></td><td>".$json['bounce_count']."</td></tr></table>";
$message .= "<p>Please see the log(s) attached that clearly show the hard bounces that have occurred during the mailing. This shows that your list has people that may not have subscribed to receive your emails.</p>";
$message .= "<p>Your mailing may have degraded our infrastructure which will cause delivery problems for other clients using our software. As per Juvlon Terms of Use, credits will not be refunded for emails that were not sent.<p/>";
$message .= "Sincerely<br/>";
$message .= "Juvlon Support";
foreach($to as $t)
{
  $obj->sendEmailAlert($t,$subject,$message);
}

//Send email alert to delivery team 
$to=array("shripad.kulkarni@nichelive.com","mahesh.jagdale@nichelive.com");
$subject="Hard bounce exception occurred for ".$obj->req1." of ".$Client_Details[0]['cl_name']." (".$Req1_Details[0]['cl_id'].")";
$AccountBlockStatus = ($AccountBlockStatus==1)?"Yes":"No";
$message  = "Hi,<br/>";
$message .= "<p>The Juvlon delivery system has detected a hard bounce exception during the sending activity of a client. As a result, the client's sending has been stopped.</p>";
$message .= "<p>Please find below the details of the sending that caused the hard bounce exception:</p>";
$message .= "<table><tr><td><b>Client: </b></td><td>".$Client_Details[0]['cl_name']." (ID: ".$Req1_Details[0]['cl_id'].")</td></tr>";
$message .= "<tr><td><b>Email: </b></td><td>(ID: ".$Req1_Details[0]['mailer_id'].")</td></tr>";
$message .= "<tr><td><b>Req1_id: </b></td><td>".$obj->req1."</td></tr> ";
$message .= "<tr><td><b>Total Recipients: </b></td><td>".$Req1_Details[0]['total_unique_mail']."</td></tr>";
$message .= "<tr><td><b>Total Sent:</b> </td><td>- </td></tr>";
$message .= "<tr><td><b>Total hard bounces: </b></td><td>".$json['bounce_count']." </td></tr>";
$message .= "<tr><td><b>Environment:</b></td><td>-</td></tr>";
$message .= "<tr><td><b>List of PMTAs where this job ID was killed :</b></td><td>".implode(',',array_unique($PMTAList))."</td></tr>";
$message .= "<tr><td><b>IPs released:</b></td><td>".implode(",",$IPRelease[0])."</td></tr>";
$message .= "<tr><td><b>Client's sending functions blocked?:</b></td><td>".$AccountBlockStatus."</td></tr></table>";
$message .= "<p>Please see the log(s) attached that clearly show the hard bounces that have occurred during the mailing.
</p>";
$message .= "Regards<br/>";
$message .= "Juvlon Delivery System";
foreach($to as $t)
{
  $obj->sendEmailAlert($t,$subject,$message);
}
}
else
{
	//Send email alert to delivery team 
	$to="mahesh.jagdale@nichelive.com";
	$subject="Central ATM API] Email Alert for Bounce Exception";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);
}


?>
