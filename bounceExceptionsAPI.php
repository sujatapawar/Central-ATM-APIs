<?php
/*
1. Create exception in client account.
2. Release all IPs 
3. Update IP counts
4. Notify client, client servicing, delivery.
*/
include("commonFunctions.php");
//$jsonString = '{"req1":12345,"sending_ip_id":1,"IP":"50.17.178.225"}';//$_POST['jsonForBlacklistedIP'];

//extract($_POST);
//$jsonString = '{"req1":2547,"Domain":"nichelive.com","ip_wise_counts":{"342":0,"343":"0"}}';//file_get_contents('php://input');
$v = array('req1'=>2545,'bounce_count'=>1,'ip_wise_counts'=>array('342'=>500,'343'=>1000));
$jsonString = json_encode($v);

if(isset($jsonString) and $jsonString!=""){
    $obj = new commonFunctions($jsonString);

    $json = $obj->inputJsonArray;
    /* Create Exception */
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
        $array = array(32,$Exception_ID,$Req1_Details[0]['cl_id']);
        $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);
        $array = array(33,$Exception_ID,$Req1_Details[0]['cl_id']);
        $obj->_dbHandlepdo->sql_insert("client_blocked_functions", " blocked_function_id,exception_id,client_id", $array);

    $obj->connection_disconnect();

//Give our CSV file a name.
$today_date = date("Y-m-d");
$csvFileName = 'logs/bounce_exceptions/'.$today_date.'.csv';

$logsArray["Date/Time"]=date("Y-m-d H:i:s");
$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
//Releasing IP
$obj->releaseIP();
$logsArray["Action1"] = "IPs are released";
    
//update IP wise count
$obj->UpdateIPWiseCounts();
$logsArray["Action2"]="IP wise counts are updated";
    
$logsArray["Action3"]="Execption generated and sending functions are blocked";    


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
$subject="[Central ATM API] Email Alert to client for Sender Domain Blacklist ";
$message="Email Alert for Sender Domain Blacklist from Central ATM API";
$obj->sendEmailAlert($to,$subject,$message);

//Send email alert to delivery team 
$to="shripad.kulkarni@nichelive.com";
$subject="Central ATM API] Email Alert to Deliver for Sender Domain Blacklist ";
$message="Email Alert for Sender Domain Blacklist from Central ATM API";
$obj->sendEmailAlert($to,$subject,$message);


}



?>
