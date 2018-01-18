<?php
$ch = curl_init(); 
$option = json_encode( array( "IP"=> "150.129.26.127","Domain"=>"n1.abcdef.com") );
curl_setopt( $ch, CURLOPT_POSTFIELDS, $option );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_URL, "http://localhost:3000/AddPTR"); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
$output = curl_exec($ch); 
echo $output;
curl_close($ch); 

/* $ch = curl_init(); 
$option = json_encode(array( "IP"=> "150.129.26.127"));
curl_setopt( $ch, CURLOPT_POSTFIELDS, $option );
curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_URL, "http://localhost:3000/DeletePTR"); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
$output = curl_exec($ch); 
echo $output;
curl_close($ch); */
?>