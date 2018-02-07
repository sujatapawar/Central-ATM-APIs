<?php

/* For teting */

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
$files = glob(getcwd()."/*.csv");
foreach($files as $f):
    $FileName = basename($f);
    $TargetURL = "http://52.44.195.201/Central-ATM-APIs/output_assetFiles/update_asset.php";
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
    echo $result=curl_exec($ch);
    curl_close ($ch);
endforeach;
?>
