<?php
// Release all IPs
// Update IP wise sending count (which table client_ip_detail and ipwise_count - Ans: both tables ?)
// Send Email to Client (content?)
// Send Email to Client Servicing and Delivery Team (content?)
// logs for the action

include("commonFunctions.php");
//$jsonString = '{"req1":12345,"sending_ip_id":1,"IP":"50.17.178.225"}';//$_POST['jsonForBlacklistedIP'];


//extract($_POST);
$jsonString = '{"req1":2545,"Domain":"nichelive.com","ip_wise_counts":{"342":500,"343":"1000"}}';//file_get_contents('php://input');

if(isset($jsonString) and $jsonString!=""){
    $obj = new commonFunctions($jsonString);

//Give our CSV file a name.
$today_date = date("Y-m-d");
$csvFileName = 'logs/sender_domain_listed/'.$today_date.'.csv';

$logsArray["Date/Time"]=date("Y-m-d H:i:s");
$logsArray["Input JSON "]=str_replace(","," ",$jsonString);
//Releasing IP
$obj->releaseIP();
$logsArray["Action1"]=$json = "IPs are released";
    
//update IP wise count
$obj->UpdateIPWiseCounts();
$logsArray["Action2"]="IP wise counts are updated";


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


