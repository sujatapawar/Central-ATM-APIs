<?php
define("ATMHost","172.16.8.115");
define("ATMDB","ATM");
define("ATMUsername","juvlonui");
define("ATMPassword","#u2dwfbeZlJO");
define("FilePath",getcwd());
class asset_file
{
    protected $host = ATMHost;                  
    protected $dbname = ATMDB;               
    protected $dbuser = ATMUsername;
    protected $dbpass = ATMPassword;
    public $ATMLink;
    function __construct()
    {
        try
        {
            /* Connection to Bounce Server */
            $this->ATMLink = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->dbuser, $this->dbpass);
            $this->ATMLink->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }
        catch(PDOException $e)
        {   
            echo "Connection failed: " . $e->getMessage();
        }
        /* Asset Type 
        1 = IP 
        2 = Domain
        */
        $this->getAgencyName($this->getFreezerIP(),1);
        $this->getAgencyName($this->getFreezerDomain(),2);
        $this->sendFile();
    }
    
    function sendFile()
    {
        $files = glob(FilePath."/*.csv");
        foreach($files as $f):
            $FileName = basename($f);
            $FilesArr = explode('_', $FileName, 2);
            $Env = $FilesArr[0];
            $TargetURL = ($Env=="Pune")?"http://103.13.110.7/file_listener.php":"http://150.129.25.10/file_listener.php";
            if (function_exists('curl_file_create')) 
            { 
                $cFile = curl_file_create($f);
            }
            else 
            { 
                $cFile = '@' . realpath($f);
            }
            $post = array('file_contents'=> $cFile);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL,$TargetURL);
            curl_setopt($ch, CURLOPT_POST,1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $result=curl_exec($ch);
            print_r($result);
            curl_close ($ch);
            if($result==1):
                unlink($f);
            endif;
        endforeach;
    }
    
    function getAgencyName($AssetIDs,$AssetType)
    {
        $PuneIP_Asset = array();
        $MumbaiIP_Asset = array();
        $PuneDomain_Asset = array();
        $MumbaiDomain_Asset = array();
        
        foreach($AssetIDs as $A)
        {
            if($AssetType == 1)
            {
                $Query = $this->ATMLink->prepare("
                                            SELECT  BAM.agency_name
                                            FROM blacklisting_transactions BT
                                            JOIN blacklistingAgencies_master BAM ON BAM.agency_id = BT.agency_id
                                            where BT.asset_id = ".$A['IP_id']." and BT.asset_type_id=".$AssetType." and BT.transaction_id=(select max(transaction_id) from blacklisting_transactions where status='listed' and asset_id=".$A['IP_id'].")"
                                            );
                $Query->execute();
                $AgencyName = $Query->fetch(PDO::FETCH_ASSOC);
                $AgencyName = ($AgencyName['agency_name']!='') ? $AgencyName['agency_name']:'';
                if($A['env_id']==1)
                {
                    $PuneIP_Asset[] = array($A['IP_id'],$A['IP'],$AgencyName);
                }
                else
                {
                    $MumbaiIP_Asset[] = array($A['IP_id'],$A['IP'],$AgencyName);
                }
            }
            else
            {
                $Query = $this->ATMLink->prepare("
                                            SELECT  BAM.agency_name
                                            FROM blacklisting_transactions BT
                                            JOIN blacklistingAgencies_master BAM ON BAM.agency_id = BT.agency_id
                                            where BT.asset_id = ".$A['domain_id']." and BT.asset_type_id=".$AssetType." and BT.transaction_id=(select max(transaction_id) from blacklisting_transactions where status='listed' and asset_id=".$A['domain_id'].")"
                                            );
                $Query->execute();
                $AgencyName = $Query->fetch(PDO::FETCH_ASSOC);
                $AgencyName = ($AgencyName['agency_name']!='') ? $AgencyName['agency_name']:'';
                if($A['env_id']==1)
                {
                    $PuneDomain_Asset[] = array($A['domain_id'],$A['domain_name'],$AgencyName);
                }
                else
                {
                    $MumbaiDomain_Asset[] = array($A['domain_id'],$A['domain_name'],$AgencyName);
                }
            }
        }

        $this->createCSV($PuneIP_Asset,'Pune_IPAsset');
        $this->createCSV($MumbaiIP_Asset,'Mumbai_IPAsset');
        $this->createCSV($PuneDomain_Asset,'Pune_DomainAsset');
        $this->createCSV($MumbaiDomain_Asset,'Mumbai_DomainAsset');
    }
    
    function createCSV($FileData,$FileName)
    {
        if(!empty($FileData))
        {
            $file = fopen($FileName.".csv","w");
            foreach ($FileData as $line)
            {
                fputcsv($file,$line);
            }
            fclose($file);
        }
    }

    function getFreezerIP()
    {
        $Query = $this->ATMLink->prepare("
                                        SELECT CPIP.IP_id,IPM.IP,IPM.IP,IPM.env_id 
                                        FROM childPool_IPs CPIP
                                        join IP_master IPM  on IPM.IP_id = CPIP.IP_id
                                        where CPIP.childPool_id = 10344
                                        ");
        $Query->execute();
        return $Query->fetchAll(PDO::FETCH_ASSOC);
    }
    
    function getFreezerDomain()
    {
        $Query = $this->ATMLink->prepare("
                                        SELECT DM.domain_id,DM.domain_name,DM.env_id
                                        FROM childPool_master CPM
                                        JOIN childPool_SendingDomains CPSD ON CPSD.childPool_id = CPM.childPool_id
                                        JOIN domain_master DM ON DM.domain_id = CPSD.domain_id
                                        where CPM.pool_id=2 and CPM.childPool_type_id=8
                                        ");
        $Query->execute();
        return $Query->fetchAll(PDO::FETCH_ASSOC);
    }
}

$obj = new asset_file();
?>
