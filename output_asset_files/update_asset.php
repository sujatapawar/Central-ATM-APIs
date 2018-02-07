<?php
include "../commonFunctions.php";
$obj = new commonFunctions("Demo");
echo "****";
print_r($_FILES);
exit;
if(!empty($_FILES))
{
    $FileName = $_FILES["file_contents"]["name"];
    if($FileName == "IPAsset.csv")
    {
        if(($handle = fopen($_FILES['file_contents']['tmp_name'], 'r')) !== FALSE) 
        {
            while(($data = fgetcsv($handle)) !== FALSE) 
            {
                $status = explode(",",$data[3]);
                if(in_array(1,$status))
                {
                   echo "Blacklist";
                }
                else
                {
                    $IP_ID = $data[0];
                    if($obj->checkIPAssignInDomainMaster($IP_ID))
                    {
                        $obj->removeIPFromFreezer($IP_ID);
                        $obj->putIPInWarmup($IP_ID);
                    }
                    else
                    {
                        $obj->removeIPFromFreezer($IP_ID);
                        $obj->putAssetIntoAvailablePool($IP_ID,'IP');
                    }
                }
            }
        } 
    }
    
}
?>
