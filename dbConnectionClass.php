<?php
class DBConnection
{
     protected $dbHandle;
     public function __construct($host,$dbname,$user,$pass = NULL)
    {
        $this->dbHandle = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        $this->dbHandle->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // always disable emulated prepared statement when using the MySQL driver
        $this->dbHandle->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
     }

    function sql_Select($Table, $Fields, $Conditional = NULL, $array = NULL){
          $sql = "SELECT $Fields FROM $Table";
          if(!is_null($Conditional)){
                $sql .= $Conditional;
                $pre=$this->dbHandle->prepare($sql);
                $pre->execute($array);
                return $pre->fetchAll();
            }
                $pre=$this->dbHandle->prepare($sql);
                $pre->execute();
                return $pre->fetchAll();
    }

    function sql_Update($Table, $Fields, $Conditional = NULL,$array = NULL){

        $sql = "UPDATE $Table SET";

             /*foreach($Fields as $field => $value){
                $sql .= is_numeric($value) ? " $field = $value , " : " $field = '$value' , " ;
                }
                $sql = preg_replace('/,$/', '', trim($sql)); //Removes the extra ',' 
                */
                $sql .= $Fields;

         if(!is_null($Conditional)){
            $sql .= $Conditional;
               $pre = $this->dbHandle->prepare($sql);
                $pre->execute($array);
                 return $updateCount = $pre->rowCount();
            }   
                $pre = $this->dbHandle->prepare($sql);
                $pre->execute();
                return $updateCount = $pre->rowCount();
                    
    }

    function sql_delete($Table, $Conditional = NULL,$array = NULL){

        $sql = "DELETE FROM $Table";

          if(!is_null($Conditional)){
            $sql .= $Conditional;
            $pre = $this->dbHandle->prepare($sql);
            $pre->execute($array);
            return $deleteCount = $pre->rowCount();
            }
            $pre = $this->dbHandle->prepare($sql);
            $pre->execute();
            return $deleteCount = $pre->rowCount();
    }




}
?>
