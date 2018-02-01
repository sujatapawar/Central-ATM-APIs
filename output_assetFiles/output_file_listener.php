<?php
if(!empty($_FILES))
{
    $name = $_FILES["file_contents"]["name"];
    $UploadFilePath = "output_assetFiles/";
    
    $Filename = pathinfo($name,PATHINFO_FILENAME);
    $OriginalName = $Filename;
    $Extension = pathinfo($name, PATHINFO_EXTENSION);
    
    $i = 1;
    while(file_exists($UploadFilePath.$Filename.".".$Extension))
    {           
        $Filename = (string)$OriginalName.'#'.$i;
        $name = $Filename.".".$Extension;
        $i++;
    }
    if (move_uploaded_file($_FILES["file_contents"]["tmp_name"], $UploadFilePath.$name)):
        echo 1;
    endif;
}
?>
