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
        if($jsonString!='')
        {
            $this->inputJsonArray=json_decode($jsonString, true);

            $this->req1=$this->inputJsonArray['req1'];
        }
        
        

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
    
    function get_request_type()
    {
    $this->connection_atm();
        $array = array($this->req1);
        $Req1_Details = $this->_dbHandlepdo->sql_Select("Req1", "cl_id,sending_type", " where req1_id=?", $array);
    if($Req1_Details[0]['sending_type']=='test')
    {
      return "Test";
    }
        
        $this->connection_disconnect(); 
    $this->connection_db_mail_master();
        $array = array($Req1_Details[0]['cl_id']);
        $Client_Details = $this->_dbHandlepdo->sql_Select("client_master", "client_type", " where cl_id=?", $array);
    if($Client_Details[0]['client_type']=='trial')
    {
       return "Trial";
    
    }
    else return "PostORPrep";    
    
    }
    function connection_disconnect()
    {
        $this->_dbHandlepdo->connection_disconnect();
    }
       
    function getSentCount($req1Id)
    {
        $this->connection_atm();
        $array = array($req1Id);
        $RecordExist = $this->_dbHandlepdo->sql_Select("client_ip_detail", "sum(sent) as SentCount", " where req1_id=?", $array);
        $this->connection_disconnect();
        return $RecordExist[0]['SentCount'];
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
                    $this->mail->ClearAddresses();


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
            $RecordExist = $this->_dbHandlepdo->sql_Select("client_ip_detail", "IP_id", " where req1_id=?", array($json['req1']));
            $array = array(0,$json['req1']);
            $this->_dbHandlepdo->sql_Update("client_ip_detail"," in_use=? ", " where req1_id=?",$array);
        $this->connection_disconnect();
        return $RecordExist;        
        
    }// end of releaseIP
    
  function updateReq1Status($status,$flag=1)
    {
        $this->connection_atm();
            $json = $this->inputJsonArray;
            $array = array("Paused",$flag,$json['req1']);
            $this->_dbHandlepdo->sql_Update("Req1"," status=? and controlled_sending=?", " where req1_id=?",$array);
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
    function insert_IP_in_ClientIP_Detail($WarmUp_IP_ID,$badIpId)
    {
        $this->connection_atm();
        $req1 = $this->req1;
        
        $Conn = $this->_dbHandlepdo->get_connection_variable();
            $SQL_ClientIP_Detail = $Conn->prepare(
                                            "select cl_id, sent from client_ip_detail where req1_id=? and IP_id=?"
                                          );
            $SQL_ClientIP_Detail->execute(array($req1,$badIpId));
            $client_ip_details_data = $SQL_ClientIP_Detail->fetchAll();
          
        foreach($client_ip_details_data as $data) {
        //print_r($data);
       /* $ClientID = $this->_dbHandlepdo->sql_Select("client_ip_detail", "cl_id,sent", " where req1_id=?", array($req1));
       */ $ClientID = $data['cl_id'];
      $Sent = $data['sent'];       
        
        
     //  print_r($array); die;   
            $arrayToCheck = array($WarmUp_IP_ID,$ClientID);
        $RecordExist = $this->_dbHandlepdo->sql_Select("client_ip_detail", "req1_id", " where IP_id=? and cl_id=?", $arrayToCheck);
               
                if(!empty($RecordExist)):
               $arrayToUpdate = array($req1,$Sent,1,date('Y-m-d'),$WarmUp_IP_ID); 
                    $this->_dbHandlepdo->sql_Update("client_ip_detail"," req1_id=?,sent=sent+?,in_use=?,date=?", " where IP_id=? ",$arrayToUpdate);
                else:    
               $arrayToInsert = array($req1,$ClientID,$WarmUp_IP_ID,$Sent,1,date('Y-m-d')); 
                $this->_dbHandlepdo->sql_insert("client_ip_detail", "req1_id,cl_id,IP_id,sent,in_use,date", $arrayToInsert);
            endif;
    } //end foreach loop    
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
    $this->_dbHandlepdo->sql_Update("IP_master"," status=? ", " where IP_id=?",array('listed',$badIPId));   
    $this->connection_disconnect();
   }// end of putIPInFreezer

   function putIPInWarmup($badIPId)
   {
    $this->connection_atm();
    $this->_dbHandlepdo->sql_insert("childPool_IPs", "childPool_id,IP_id,web,childStage_id", array(97,$badIPId,'1',1));
    $this->connection_disconnect();
   }// end of putIPInWarmup
    
    
   function getDomainId($domain,$type)
   {
     $this->connection_atm();
     $arrayOfDomainId = $this->_dbHandlepdo->sql_Select("domain_master", "domain_id", " where domain_name=? and type=?", array($domain,$type));
     $this->connection_disconnect();
     return $arrayOfDomainId;
   
   }//end of getDomainId
    
   function getAllChildPoolIdsOfDomains($blacklistedDomainId,$tableName)
   {
     $this->connection_atm();
     $arrayOfChildPoolIds = $this->_dbHandlepdo->sql_Select($tableName, "childPool_id", " where domain_id=?", array($blacklistedDomainId));
     $this->connection_disconnect();
     return $arrayOfChildPoolIds;
   
   }// end of getAllChildPoolIdsOfRP
    
   function removeDomain($blacklistedDomainId,$tableName)
   {
     $this->connection_atm();
     $this->_dbHandlepdo->sql_delete($tableName, " where domain_id=?", array($blacklistedDomainId));
     $this->connection_disconnect();       
   
   }//end of removeRPDomain
    
   function getDomainFromWarmUp($blacklistedDomainId,$tableName)
   {
        if($tableName=='childPool_RPDomains')
            $childpool_type=2;
        if($tableName=='childPool_LinkDomains')
            $childpool_type=3;
        if($tableName=='childPool_ImageDomains')
            $childpool_type=5;


        $this->connection_atm();
            $Conn = $this->_dbHandlepdo->get_connection_variable();
            $SQL_WarmUpIP = $Conn->prepare(
                                            "select chip.domain_id from ".$tableName." as chip, domain_master as dm
                                            where chip.domain_id=dm.domain_id and dm.active='1'
                                            and childPool_id = (select childPool_ID from childPool_master where pool_id = 1 and childPool_type_id=$childpool_type)
                                            and chip.domain_id!=? LIMIT 1"
                                          );
            $SQL_WarmUpIP->execute(array($blacklistedDomainId));
            $WarmUpDomain = $SQL_WarmUpIP->fetchAll();
            $WarmUpDomainId = $WarmUpDomain[0]['domain_id'];
            
            $SQL_DeleteIP = $Conn->prepare(
                                            "delete from ".$tableName." 
                                            where domain_id=? 
                                            and childPool_id = (select childPool_ID from childPool_master where pool_id = 1 and childPool_type_id=$childpool_type)"
                                          );
            $SQL_DeleteIP->execute(array($WarmUpDomainId));

        $this->connection_disconnect();
        return $WarmUpDomainId;
   
   }//end of getRPDomainFromWarmUp
    
  function replanishDomain($warmedUpDomainId,$childPoolId,$tableName)
   {
        $this->connection_atm();
        $this->_dbHandlepdo->sql_insert($tableName, "childPool_id,domain_id,web", array($childPoolId,$warmedUpDomainId,'1'));
        $this->connection_disconnect();
   }// end of replanishRPDomain
    
    
   function putDomainInFreezer($blacklistedDomainId,$tableName)
   {
        $this->connection_atm();
        if($tableName=='childPool_RPDomains')
        {
          $arrayOfResult = $this->_dbHandlepdo->sql_Select("childPool_master", "childPool_ID", " where pool_id =? and childPool_type_id=? ", array(2,2));
          $freezerId=$arrayOfResult[0]['childPool_ID'];//10366;
        }
            
        if($tableName=='childPool_LinkDomains' or $tableName=='childPool_ImageDomains')
        {
          $arrayOfResult = $this->_dbHandlepdo->sql_Select("childPool_master", "childPool_ID", " where pool_id =? and childPool_type_id=? ", array(2,3));
          $freezerId=$arrayOfResult[0]['childPool_ID'];//10367;
        }
        
        if($tableName=='childPool_SendingDomains')
        {
          $arrayOfResult = $this->_dbHandlepdo->sql_Select("childPool_master", "childPool_ID", " where pool_id =? and childPool_type_id=? ", array(2,8));
          $freezerId=$arrayOfResult[0]['childPool_ID'];//10735;
        }
              

        
        $this->_dbHandlepdo->sql_insert($tableName, "childPool_id,domain_id,web", array($freezerId,$blacklistedDomainId,'1'));
        $this->connection_disconnect();

   }//end of putRPDomainInFreezer
    
    function getDomainName($warmedUpDomainId)
    {

    $this->connection_atm();
    $arrayOfDomainName = $this->_dbHandlepdo->sql_Select("domain_master", "domain_name", " where  domain_id=? ", array($warmedUpDomainId));
    $this->connection_disconnect();
    return $arrayOfDomainName;

    } // end of getDomainName
    
    function deactivateDomain($blacklistedDomainId)
    {
     $this->connection_atm();
     $this->_dbHandlepdo->sql_Update("domain_master"," status='listed',active='0',IP_id=0 ", " where domain_id=?",array($blacklistedDomainId));
     $this->connection_disconnect();      
     } //end of deactivateDomain
    
    
    function getDomainIpId($blacklistedDomain)
    {
     $this->connection_atm();
     $arrayOfDomainName = $this->_dbHandlepdo->sql_Select("domain_master", "IP_id", " where  domain_name=? ", array($blacklistedDomain));
     $this->connection_disconnect();
     return $arrayOfDomainName;
    
    } //end of getDomainIpId
    
    function putAssetIntoAvailablePool($asset_id,$asset_type='IP')
    {
      $this->connection_atm();
     if($asset_type=='IP')
     {
         //check if IP is listed 
          $arrayOfIPResult = $this->_dbHandlepdo->sql_Select("IP_master", "status", " where  IP_id=? ", array($asset_id));
         if($arrayOfIPResult[0]['status']!='listed')
         {
            $Conn = $this->_dbHandlepdo->get_connection_variable();
            $SQL_ChildPoolId = $Conn->prepare(
                                            "select cm.childPool_id from childPool_master as cm, pool_master pm where cm.pool_id=pm.pool_id and pm.pool_name=? and cm.childPool_type_id=?"
                                          );
            $SQL_ChildPoolId->execute(array('Available assets',1));
            $arrayOfResult = $SQL_ChildPoolId->fetchAll();
            $childPool_id = $arrayOfResult[0]['childPool_id'];

            $this->_dbHandlepdo->sql_insert("childPool_IPs", "childPool_id,IP_id,web,childStage_id", array($childPool_id,$asset_id,'1',1));
         }
          $this->connection_disconnect();
     
     }
    
    }
  
    
    function get_log($filename,$handler)
    {
        $curl_handle=curl_init();
        curl_setopt($curl_handle,CURLOPT_URL,'http://'.BOUNCE_SERVER.'/juvlon_bounce_process/bounce_processor/log_api.php');
        $data = array('filename' => $filename,'handler'=>$handler);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
        $buffer = curl_exec($curl_handle);
        curl_close($curl_handle);
        if (empty($buffer)){
            return  "Nothing logs present";
        }
        else{
            return $buffer;
        }
    }    
    

}// end of class


?>
