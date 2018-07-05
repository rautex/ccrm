<?php

/*
  ######################################################
  # Datenbankklasse des CRM                            #
  #	wird benötigt da wir bsp über die Tabellen Regexen #
  # und funktionen benötigen die schwer mit einem      #
  # Framework umzusetzen sind                          #
  # Die Klasse kümmert sich um alle Datenbank Anfragen #
  ######################################################
 */
 
class MyPDO
{
    private $pdo;
    function __construct()
    {
        $c = new Config();
        $this->pdo = new PDO("mysql:dbname=" . $c->db_name . ";host=" . $c->db_server . "", $c->db_user, $c->db_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    function escape($string)
    {
        return $this->pdo->quote($string);
    }
    
    function Update_Tab($Tabelle, $Data_Array, $Bedienung)
    {
        /* Funktion um Datensätze upzudaten */
        
        foreach ($Data_Array as $key => $val)$keys[] = "`" . $key . "` = :" . $key;
        foreach ($Bedienung as $key => $val)$Bedienungs_Array[] = "`" . $key . "` = :" . $key;
        
        //bulid the exec array
        foreach ($Data_Array as $key => $val) $Daten_Array[$key] = trim($val);
        foreach ($Bedienung as $key => $val)$Daten_Array[$key] = trim($val);

        $Prepared_Insert = $this->pdo->prepare("UPDATE `" . $Tabelle . "` SET " . implode(", ", $keys) . " WHERE " . implode(", ", $Bedienungs_Array));
        try {
            $Prepared_Insert->execute($Daten_Array);
            return true;
        }
        catch (Exception $e) {
            print_r($e);
        }
    }
    function inter($Daten)
    {
    	  /* Funktion um Array oder Datensätze zur Zahl zu Konvertieren */
        if (is_array($Daten)) {
            foreach ($Daten as $key => $val)
                $Daten[$key] = (int) $val;
        } else $Daten = (int) $Daten;
        return $Daten;        
    }
    function Insert($Tabelle, $Data_Array)
    {
        /* Funktion um ein Array in die Datenbank einzupflegen  */
        foreach ($Data_Array as $key => $val) {
            $keys[] = $key;
            $vals[] = ":" . $key;
        }
        $Prepared_Insert = $this->pdo->prepare("INSERT INTO `" . $Tabelle . "`(`" . implode("`,`", $keys) . "`) VALUES (" . implode(",", $vals) . ")");
        try {
            $Prepared_Insert->execute($Data_Array);
        }
        catch (Exception $e) {
            print_r($e->errorInfo);
        }
        return $this->pdo->lastInsertId();        
    }
    function raw_prep_select($sql, $Array = null)
    {
    	  /* Funktion um ein fertiges Statment auszuführen  */
        $Prepared_Select = $this->pdo->prepare($sql);
        try {
            isset($Array) ? $Prepared_Select->execute($Array) : $Prepared_Select->execute();
            return $Prepared_Select->fetchALL(PDO::FETCH_ASSOC);
        }
        catch (Exception $e) {
            print_r($e->errorInfo);
        }
        
    }
    function Raw_Select($sql)
    {
    	  /* Funktion um ein Select SQL Statment direkt auszuführen */
        try {
            return $this->pdo->query($sql)->fetchALL(PDO::FETCH_ASSOC);
        }
        catch (Exception $e) {
            print_r($e->errorInfo);
        }
    }
    
    function raw_exec($sql)
    {
    	  /* Funktion um ein  SQL Statment direkt auszuführen ohne Rückgabe */
        try {
             $this->pdo->exec($sql);
             return true;
        }
        catch (Exception $e) {
            print_r($e->errorInfo);
        }
        
    }
    function Baue_Bedingungen($Bedienung1, $Bedienung2)
    {
        /* Funktion um unser Where für das Statment zu bauen, dies ist wichtig da wir von Between für Datum bis Regexp für Json alles verwenden*/

        if ($Bedienung1) {
            foreach ($Bedienung1 as $key => $val) {                
                if ($val['t'] == "BETWEEN") {
                    $val_tmp   = explode("#", $val['v']);
                    $Prepare_Array[] = " `$key` BETWEEN " . $this->pdo->quote($val_tmp[0]) . " AND " . $this->pdo->quote($val_tmp[1]);
                    unset($val_tmp);
                } else if (strstr($key, "json_")) {
                    $Prepare_Array[]       = " REPLACE(REPLACE(`" . $val['f'] . "` , ']' , ','), '[' , ',') " . str_replace("SLIKE", "LIKE", $val['t']) . " :" . $val['f'];
                    $vals[$val['f']] = $val['t'] == "SLIKE" ? "%" . $val['v'] . "%" : $val['v'];
                } else if (strstr($key, "touid") || strstr($key, "userid")) {                    
                    $Prepare_Array[] = "`" . $val['f'] . "`  IN(" . $val[v] . ")";
                } else {
                    $Prepare_Array[]       = "`" . $val['f'] . "` " . str_replace("SLIKE", "LIKE", $val['t']) . " :" . $val['f'];
                    $vals[$val['f']] = $val['t'] == "SLIKE" ? "%" . $val['v'] . "%" : $val['v'];
                }
            }
            $Prepare_String1 = implode(" AND ", $Prepare_Array);
        } else
            $Prepare_String1 = "";
        if ($Bedienung2) {
            foreach ($Bedienung2 as $key => $val) {
                if ($val['t'] == "BETWEEN") {
                    $val_tmp    = explode("#", $val['v']);
                    $Prepare_Array2[][] = " `$key`  NOT BETWEEN " . $this->pdo->quote($val[0]) . " AND " . $this->pdo->quote($val[1]);
                    unset($val_tmp);
                } else if (strstr($key, "json_")) {
                    $Prepare_Array2[][]      = " REPLACE(REPLACE(`" . substr($val['f'], 0, strlen($val['f']) - 2) . "` , ']' , ','), '[' , ',') " . str_replace("SLIKE", "LIKE", $val['t']) . " :" . $val['f'];
                    $vals[$val['f']] = $val['t'] == "SLIKE" ? "%" . $val['v'] . "%" : $val['v'];
                } else if ($key == "touid_n" || $key == "userid_n") {
                    $Prepare_Array2[][] = "`" . substr($val['f'], 0, strlen($val['f']) - 2) . "` NOT IN(" . $val[v] . ")";
                } else {
                    $Prepare_Array2[][]      = "`" . substr($val['f'], 0, strlen($val['f']) - 2) . "` " . str_replace("SLIKE", "LIKE", $val['t']) . " :" . $val['f'];
                    $vals[$val['f']] = $val['t'] == "SLIKE" ? "%" . $val['v'] . "%" : $val['v'];
                }
            }
            $Prepare_String2 = implode(" AND ", $Prepare_Array2);
        } else
            $Prepare_String2 = "";
        
        
        if (strlen($Prepare_String1) > 5)
            $sf['p'] = $Prepare_Array;
        if (strlen($Prepare_String2) > 5){
        	  $sf['n'] = $Prepare_Array2[];
            $sf['v']= isset($vals)? $vals:[];
        }        
        return $sf;
    }
    function select($Tabelle, $Felder, $Bedienung1 = null, $orderby = null)
    {
        if ($Bedienung1) {
            foreach ($Bedienung1 as $key => $val) {
                $Prepare_Array[]       = "`" . $val['f'] . "` " . str_replace("SLIKE", "LIKE", $val['t']) . " :" . $val['f'];
                $vals[$val['f']] = $val['t'] == "SLIKE" ? "%" . $val['v'] . "%" : $val['v'];
            }
            $Prepare_String = implode(" AND ", $Prepare_Array);
        } else
            $Prepare_String = "1";
        
        if ($orderby)$Prepare_String  . " ORDER BY " . $orderby;
        $Prepared_Select = $this->pdo->prepare("SELECT `" . implode("`,`", $Felder) . "` FROM `" . $Tabelle . "` WHERE " . $Prepare_String);
        try {
            $Bedienung1 ? $Prepared_Select->execute($vals) : $Prepared_Select->execute();
        }
        catch (Exception $e) {
            print_r($e->errorInfo);
        }
        return $Prepared_Select->fetchALL(PDO::FETCH_ASSOC);
    }
}




?>