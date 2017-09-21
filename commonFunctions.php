<?php
include("config.php");
include("library/phpmailer/class.phpmailer.php");
include("dbConnectionClass.php");

/* Base Class */
class commonFunctions {

	//protected $_dbHandle;
    public $_dbHandlepdo;
    public $inputJsonArray;
    public $mail;
    public $req1;

	function __construct($jsonString) {
		
		//1. Accept Json file and assign file conetents in array format to a class variable
		$this->inputJsonArray=json_decode($jsonString, true);

		$this->req1=$this->inputJsonArray['req1'];
		
		//2. Instantiate DB class
				
        //3. Instantiate common DB class using pdo
        $this->_dbHandlepdo = new DBConnection(DB_HOST,DB_NAME,DB_USER,DB_PASSWORD);

		//mysql_select_db(DB_NAME, $this->_dbHandle);

		//4. Instantiate PHPMailer
	   $this->mail = new PHPMailer();
		

	} //end of construct

	
	
	   
	
    
	function sendEmailAlert($to,$subject,$message)
	{
                    $this->mail->SetLanguage("en", "/var/www/html/atm2.0/library/phpmailer/language/");			 		
                    $this->mail->IsSMTP();
                    $this->mail->Host =MAIL_HOST;
                    $this->mail->Username =MAIL_USERNAME;    
                    $this->mail->Password =MAIL_PASSWORD; 	
                    $this->mail->SMTPAuth =true;
                    $this->mail->Port=465;
                    $this->mail->SMTPSecure = 'tls'; 
                    $this->mail->From = MAIL_SENDER_EMAIL;
                    $this->mail->FromName = MAIL_SENDER_NAME;
                    $this->mail->Sender =MAIL_SENDER_EMAIL;   
                    $this->mail->AddAddress($to);
                    $this->mail->WordWrap = 50;    
                    $this->mail->IsHTML(true);   
                    $this->mail->Subject  = $subject;
                    $this->mail->Body = $message;
                    $this->mail->Send();         


	} // end of sendEmailAlert

	

	function postDataUsingCURL($url,$stringToPost)
    {
        

        $curl_handle=curl_init();
        curl_setopt($curl_handle,CURLOPT_URL,$url);
        curl_setopt($curl_handle, CURLOPT_POST, true);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $stringToPost);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
        $res = curl_exec($curl_handle);
        curl_close($curl_handle);
        if ($res) {
                //echo $res;
                //echo "Data Posted successfully";
        }
	}// end of postDataUsingCURL

    function UpdateIPWiseCounts()
    {
        // Update IP wise sending counts (which table client_ip_detail and ipwise_count)
	    echo "UpdateIPWiseCounts";
    }// end of UpdateIPWiseCounts

    function putAssetIntoFreezer($assetType, $asset)
    {
	     echo "putAssetIntoFreezer";
    
    }// end of putAssetIntoFreezer	
	
     function putAssetIntoWarmup($assetType, $asset)
    {
	     echo "putAssetIntoWormup";
    
    }// end of putAssetIntoWormup
    
    function releaseIP()
    {
	    echo "releaseIP";
    
    }// end of releaseIP
	
	
   
	
    	
    

}// end of class


?>
