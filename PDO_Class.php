<?php
 
class MyPDO{
	private $pdo;
	function __construct(){
		$c = new Config();
		$this->pdo = new PDO("mysql:dbname=".$c->db_name.";host=".$c->db_server."", $c->db_user, $c->db_password,   array( PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
    $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	function escape($string){
		return $this->pdo->quote($string);		 
	}
	
	function update($tab, $data, $con){

		foreach($data as $key=>$val) $keys[] = "`".$key."` = :".$key;
		foreach($con as $key=>$val) $cons[] = "`".$key."` = :".$key;
		
		//bulid the exec array
		foreach($data as $key=>$val){
			$exec_arr[$key] = trim($val);
		}
		foreach($con as $key=>$val){
			$exec_arr[$key] = trim($val);
		}
		$sql = "UPDATE `".$tab."` SET ".implode(", ", $keys)." WHERE ".implode(", ",$cons);
		$prep_insert = $this->pdo->prepare($sql);
		try{
			$prep_insert->execute($exec_arr);
			return true;
		}
		catch(Exception $e){
			print_r($e);
		}
	}
	function inter($tmp){
		if(is_array($tmp)){
			foreach($tmp as $key=>$val) $tmp[$key] = (int)$val;
		} 
		else $tmp = (int)$tmp;
		return $tmp;
	
	}
	function insert($tab, $data){
	#	print_r($data);
		foreach($data as $key=>$val){
			$keys[] = $key;
			$vals[] = ":".$key;
		}
		$keys = "`".implode("`,`", $keys)."`";
		$vals = implode(",", $vals);
		$sql = "INSERT INTO `".$tab."`(".$keys.") VALUES (".$vals.")";
		$prep_insert = $this->pdo->prepare($sql);
		try{
			$prep_insert->execute($data);
		}
		catch(Exception $e){
			print_r($e->errorInfo);
		}
		
  
		
		return $this->pdo->lastInsertId(); 
		
	}
	function raw_prep_select($sql, $val = null){
		$fprep = $this->pdo->prepare($sql);		
		try{
		isset($val) > 0 ? $fprep->execute($val): $fprep->execute();
		return $fprep->fetchALL(PDO::FETCH_ASSOC);
		}
		catch(Exception $e){
			print_r($e->errorInfo);
		}
		
	}
	function raw_select($sql){
			try{
				return  $this->pdo->query($sql)->fetchALL(PDO::FETCH_ASSOC);
			}
		catch(Exception $e){
			print_r($e->errorInfo);
		}
	}
	
	function raw_exec($sql){
		try{
		return $this->pdo->query($sql);
		}
		catch(Exception $e){
			print_r($e->errorInfo);
		}
		
	}
	function bulid_where_statment($cond, $cond2){
		
		#print_r($cond2);
		//pro und con
	
		if($cond){
		foreach($cond as $key=>$val){
		
			if($val['t'] == "BETWEEN"){
				$val_tmp = explode("#", $val['v']);
				$prepare[] = " `$key` BETWEEN ".$this->pdo->quote($val_tmp[0])." AND ".$this->pdo->quote($val_tmp[1]);
				unset($val_tmp);
			}
			else if(strstr($key, "json_")){				
				$prepare[] = " REPLACE(REPLACE(`".$val['f']."` , ']' , ','), '[' , ',') ".str_replace("SLIKE", "LIKE", $val['t'])." :".$val['f'];
				$vals[$val['f']] = $val['t'] == "SLIKE" ? "%".$val['v']."%" : $val['v'];
			}
			else if(strstr($key, "touid") || strstr($key, "userid")){			
			
				$prepare[] = "`".$val['f']."`  IN(".$val[v].")";
			}
			else{
			$prepare[] = "`".$val['f']."` ".str_replace("SLIKE", "LIKE", $val['t'])." :".$val['f'];
			$vals[$val['f']] = $val['t'] == "SLIKE" ? "%".$val['v']."%" : $val['v'];
			}
		}
			$prepare = implode(" AND ", $prepare);
		}
		else $prepare = "";
		if($cond2){
		foreach($cond2 as $key=>$val){
			if($val['t'] == "BETWEEN"){
				$val_tmp = explode("#", $val['v']);
				$prepare2[] = " `$key`  NOT BETWEEN ".$this->pdo->quote($val[0])." AND ".$this->pdo->quote($val[1]);
				unset($val_tmp);
			}
			else if(strstr($key, "json_")){				
				$prepare2[] = " REPLACE(REPLACE(`".substr($val['f'], 0, strlen($val['f'])-2)."` , ']' , ','), '[' , ',') ".str_replace("SLIKE", "LIKE", $val['t'])." :".$val['f'];
				$vals[$val['f']] = $val['t'] == "SLIKE" ? "%".$val['v']."%" : $val['v'];
			}
			else if($key == "touid_n" || $key == "userid_n"){				
				$prepare2[] = "`".substr($val['f'], 0, strlen($val['f'])-2)."` NOT IN(".$val[v].")";
			}
			else{
			$prepare2[] = "`".substr($val['f'], 0, strlen($val['f'])-2)."` ".str_replace("SLIKE", "LIKE", $val['t'])." :".$val['f'];
			$vals[$val['f']] = $val['t'] == "SLIKE" ? "%".$val['v']."%" : $val['v'];
			}
		}
			$prepare2 = implode(" AND ", $prepare2);
		}
		else $prepare2 = "";
		
		
	if(strlen($prepare) > 5)	$sf['p'] = $prepare;
	if(strlen($prepare2) > 5)	$sf['n'] = $prepare2;
	$sf['v']= isset($vals)? $vals:[];
	return $sf;
	}
	function select($tab,$fields, $cond = null, $orderby = null){
		if($cond){
		foreach($cond as $key=>$val){
			$prepare[] = "`".$val['f']."` ".str_replace("SLIKE", "LIKE", $val['t'])." :".$val['f'];
			$vals[$val['f']] = $val['t'] == "SLIKE" ? "%".$val['v']."%" : $val['v'];
		}
			$prepare = implode(" AND ", $prepare);
		}
		else $prepare = "1";
	
		$sql = "SELECT `".implode("`,`", $fields)."` FROM `".$tab."` WHERE ".$prepare;
		if($orderby) $prepare." ORDER BY ".$orderby;
		$fprep = $this->pdo->prepare($sql);		
		try{
		$cond ? $fprep->execute($vals): $fprep->execute();
		}
		catch(Exception $e){
			print_r($e->errorInfo);
		}
		return $fprep->fetchALL(PDO::FETCH_ASSOC);
	}
	
	
} 




?>

