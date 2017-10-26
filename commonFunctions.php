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
        //$this->_dbHandlepdo = new DBConnection(DB_HOST,DB_NAME,DB_USER,DB_PASSWORD);
        
        //mysql_select_db(DB_NAME, $this->_dbHandle);

		//4. Instantiate PHPMailer
	   $this->mail = new PHPMailer();
		

	} //end of construct

	function connection_db_mail_master()
    {
        $this->_dbHandlepdo = new DBConnection(DBMAIL_MASTER_DB_HOST,DBMAIL_MASTER_DB_NAME,DBMAIL_MASTER_DB_USER,DBMAIL_MASTER_DB_PASSWORD);
    }
    function connection_atm()
    {
        $this->_dbHandlepdo = new DBConnection(ATM_DB_HOST,ATM_DB_NAME,ATM_DB_USER,ATM_DB_PASSWORD);
    }
	function connection_disconnect()
    {
        $this->_dbHandlepdo->connection_disconnect();
    }
	   
	
    
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
        $this->connection_atm();
            $json = $this->inputJsonArray;
            foreach($json['ip_wise_counts'] as $IP=>$Count):
                $array = array($IP,date('Y-m-d'));
                $RecordExist = $this->_dbHandlepdo->sql_Select("ipwise_count", "id", " where IP_id=? and date=?", $array);
                $array = array($Count,$IP,date('Y-m-d'));
                
                /* check ipwise_count for ip exist */
                if(!empty($RecordExist)):
                    $this->_dbHandlepdo->sql_Update("ipwise_count"," count=count-?", " where IP_id=? and date=? ",$array);
                else:
                    $this->_dbHandlepdo->sql_insert("ipwise_count", "count,IP_id,date", $array);
                endif;
                
                $array = array($Count,$IP,$json['req1']);
                $this->_dbHandlepdo->sql_Update("client_ip_detail"," sent=sent-?", " where IP_id=? and req1_id=?",$array);  
            endforeach;
        $this->connection_disconnect();
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
        $this->connection_atm();
            $json = $this->inputJsonArray;
            $array = array(0,$json['req1']);
            $this->_dbHandlepdo->sql_Update("client_ip_detail"," in_use=? ", " where req1_id=?",$array);
        $this->connection_disconnect();        
        
    }// end of releaseIP
	
	/* Get IP From Warm-up */
    function getIPFromWarmUp($IP_ID)
    {
        $this->connection_atm();
            $Conn = $this->_dbHandlepdo->get_connection_variable();
            $SQL_WarmUpIP = $Conn->prepare(
                                            "select chip.IP_id from childPool_IPs  as chip, IP_master as ipm
                                            where chip.IP_id=ipm.IP_id
                                            and childPool_id = (select childPool_ID from childPool_master where pool_id = 1 and childPool_type_id=1)
                                            and ipm.env_id = (select env_id from IP_master where IP_id=?)
                                            and ipm.grade = (select grade from IP_master where IP_id=?)
                                            and chip.IP_id!=? order by chip.childStage_id DESC LIMIT 1"
                                          );
            $SQL_WarmUpIP->execute(array($IP_ID,$IP_ID,$IP_ID));
            $WarmUpIP = $SQL_WarmUpIP->fetchAll();
            $WarmUpIP = $WarmUpIP[0]['IP_id'];
            
            $SQL_DeleteIP = $Conn->prepare(
                                            "delete from childPool_IPs 
                                            where IP_id=? 
                                            and childPool_id = (select childPool_ID from childPool_master where pool_id = 1 and childPool_type_id=1)"
                                          );
            $SQL_DeleteIP->execute(array($WarmUpIP));

        $this->connection_disconnect();
        return $WarmUpIP;
    }
    /* End Get IP From warm-up */
	
	/* Insert IP in client_ip_detail */
    function insert_IP_in_ClientIP_Detail($WarmUp_IP_ID)
    {
        $this->connection_atm();
        $json = $this->inputJsonArray;
        
        $ClientID = $this->_dbHandlepdo->sql_Select("client_ip_detail", "cl_id,sent", " where req1_id=?", array($json['req1']));
        $ClientID = $ClientID[0]['cl_id'];
	$Sent = $ClientID[0]['sent'];
	//print_r($ClientID);    
        
        $array = array($json['req1'],$ClientID,$WarmUp_IP_ID,$Sent,1,date('Y-m-d')); 
        $this->_dbHandlepdo->sql_insert("client_ip_detail", "req1_id,cl_id,IP_id,sent,in_use,date", $array);
        $this->connection_disconnect();
    }
    /* End Insert IP in client_ip_detail */

   function getAllChildPoolIds($badIpId)
   {
     $this->connection_atm();
     $arrayOfChildPoolIds = $this->_dbHandlepdo->sql_Select("childPool_IPs", "childPool_id", " where IP_id=?", array($badIpId));
     $this->connection_disconnect();
     return $arrayOfChildPoolIds;

   }// end of getAllChildPoolIds
	
   function removeIP($badIPId)
   {
     $this->connection_atm();
     $this->_dbHandlepdo->sql_delete("childPool_IPs", " where IP_id=?", array($badIPId));
     $this->connection_disconnect();
   }// end of removeIP
	
   function replanishIP($warmedUpIP,$childPoolId)
   {
    $this->connection_atm();
    $this->_dbHandlepdo->sql_insert("childPool_IPs", "childPool_id,IP_id,web,childStage_id", array($childPoolId,$warmedUpIP,'1',1));
    $this->connection_disconnect();
   } // end of replanishIP	

   function putIPInFreezer($badIPId)
   {
    $this->connection_atm();
    $this->_dbHandlepdo->sql_insert("childPool_IPs", "childPool_id,IP_id,web,childStage_id", array(10344,$badIPId,'1',1));
    $this->connection_disconnect();
   }// end of putIPInFreezer

   function putIPInWarmup($badIPId)
   {
    $this->connection_atm();
    $this->_dbHandlepdo->sql_insert("childPool_IPs", "childPool_id,IP_id,web,childStage_id", array(97,$badIPId,'1',1));
    $this->connection_disconnect();
   }// end of putIPInWarmup
   	
  
	
    	
    

}// end of class


?>
