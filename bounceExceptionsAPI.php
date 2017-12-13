<?php
/*
1. Create exception in client account.
2. Release all IPs 
3. Update IP counts
4. Notify client, client servicing, delivery.
*/
include("commonFunctions.php");
///////////////////////////////////PROGRAM INPUT//////////////////////////////////////////////////
$jsonString = '{"req1":209,"bounce_count":10,"ip_wise_counts":{"32":10,"23":10}}';
//$jsonString = file_get_contents('php://input');
////////////////////////////////////////////////////////////////////////////////////////////////////

$obj = new commonFunctions($jsonString);
if(isset($jsonString) and $jsonString!=""){
/*	
//Give our CSV file a name.
$today_date = date("Y-m-d");
$csvFileName = 'logs/bounce_exceptions/'.$today_date.'.csv';

$logsArray["Date/Time"]=date("Y-m-d H:i:s");
$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
	

  // update Req1
   $obj->updateReq1Status("Stopped");		
	
   $logsArray["Request Type"]=$obj->get_request_type();	
    
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
        $Client_Details = $obj->_dbHandlepdo->sql_Select("client_master", "cl_name,cl_company", " where cl_id=?", $array);
        
        $Client_Data = "ClientName: ".$Client_Details[0]['cl_name']."\n Company Name:".$Client_Details[0]['cl_company']."\n Mailer-ID:".$Req1_Details[0]['mailer_id']."\n Sent Date:".$Req1_Details[0]['created_time']."\n Total Sent:".$Req1_Details[0]['total_unique_mail']."\n Bounce Count:".$json['bounce_count'];
        $array=array(2,$Req1_Details[0]['cl_id'],$Req1_Details[0]['mailer_id'],date('Y-m-d H:i:s'),$Req1_Details[0]['created_time'],$Client_Data,'open');
        $Exception_ID = $obj->_dbHandlepdo->sql_insert("client_exceptions", " exception_type_id,exception_client_id,exception_object_id,exception_open_date_time,exception_closed_date_time,exception_data,exception_status", $array);
        $obj->_dbHandlepdo->sql_insert("exception_process_log", " exception_id,datetime,initiated_by,account_exception_id", array($Exception_ID,date('Y-m-d H:i:s'),'Client',$Exception_ID));
	$Exception_Details = $obj->_dbHandlepdo->sql_Select("client_exceptions", "count(exception_id) as cnt", " where exception_type_id=? and exception_client_id=? and exception_object_id=? and exception_status=?", array($array[0],$array[1],$array[2],$array[6])); 
       if($Exception_Details[0]['cnt']>2) // check if more than 2 exceptions already exist
        {	
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
$obj->releaseIP();
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
*/

//Send email alert to client
$to="pramod.suryawanshi@nichelive.com";
$subject="[Central ATM API] Email Alert to client for Bounce Exception ";
$message="Email Alert for Bounce Exception from Central ATM API";
$obj->sendEmailAlert($to,$subject,$message);

//Send email alert to delivery team 
$to="pramod.suryawanshi@nichelive.com";
$subject="Central ATM API] Email Alert to Deliver for Bounce Exception ";
$message="Email Alert for Bounce Exception from Central ATM API";
$obj->sendEmailAlert($to,$subject,$message);
echo "mail send";

}
else
{
	//Send email alert to delivery team 
	$to="pramod.suryawanshi@nichelive.com";
	$subject="Central ATM API] Email Alert for Bounce Exception";
	$message="Blank JSON Input";
	$obj->sendEmailAlert($to,$subject,$message);
	echo "blank json";
}


?>
