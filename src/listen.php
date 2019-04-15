<?
	header('Cache-Control: no-cache, must-revalidate');
	header('Content-type: application/json');

	require_once 'ugr.class.php';
	$UGR = new UGR();
	
	
	
	/* START - UPDATE STATS */
	#SESSION - STATS
	if(is_array($_SESSION["stats"])) {
		$stats=$_SESSION["stats"];
		
	#POST - 14:30#Istanbul=14|Bosk=12|~15:30#Server1=15|Server2=12|Server3=
	} else if(strstr($_POST['stats'],"~")) {
		$line=explode("~",$_POST['stats']);
		if(COUNT($line)) {
			foreach($line as $l) {
				$exp=explode("#",$l);
				foreach(explode("|",$exp[1]) as $parse)
					if($parse[0] && $parse[1])
						$stats[$exp[0]][explode("=",$parse)[0]]=explode("=",$parse)[1];
			}
		}
	#Start new stats
	} else {
		$stats=array();
	}
	/* END - UPDATE STATS */
	
	
	
	## Find Last Table for insert DB
	$last_table=$UGR->getLastTable();#name,date,order
	
	
	/******
			COLLECT LOGS FROM OTHER SERVERS 
												*****/
												
	##TEST - SIMPLE LOG CREATOR - START##
	$fp = fopen('logfile.json', 'w');
	fwrite($fp, json_encode($UGR->simpleLogCreator(),JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	fclose($fp);
	##TEST - SIMPLE LOG CREATOR - END##
	
	
	$records = json_decode(file_get_contents('logfile.json'), true);
	$insert_limit=50;#Insert all records for break - checker limit
		
		
	## Records update for every server connection
	if(is_array($records) && COUNT($records)) {
		UNSET($i,$sql_inserts);
		foreach($records as $record) {
			$sql_inserts[floor(++$i/$insert_limit)][]="('{$record[0]}','{$record[1]}','{$record[2]}','{$record[3]}')";
			$min_date=!$min_date || $min_date>substr($record[0],0,10) ? substr($record[0],0,10) : $min_date;
			
			##Chart data stats
			$stats[substr($record[0],11,3).(substr($record[0],14,1) >=3 ? '30' : '00')][$record[2]]++;
		}
		
		
		## INSERT DB for every limit
		if(COUNT($sql_inserts)) {
			foreach($sql_inserts as $sql_insert) {
				
				## Crete New Table : No last table or max limit MB last table
				if($last_table===NULL || $UGR->getTableSizes($last_table['name'])[$last_table['name']]['size']>$UGR->table['max_load']) {
					$last_table=$UGR->createNewTable($last_table,$min_date);
				}
				
				try {
					$UGR->db->exec("INSERT INTO {$last_table['name']} (timestamp,log_level,server_name,log_detail)
												VALUES ".implode(",",$sql_insert));
				} catch(PDOException $ex) {
					$UGR->PDOException($ex);
				}
			}
		}
	}

	
	
	/**** CHART - Online stats with no DB ***/
	$thead=$UGR->logs['servers'];
	array_unshift($thead, "Time");
	$chart_data[]=$thead;
	
	
	## No stats yet
	if(!COUNT($stats)) {
	
		$chart_data[]=array(date("H:").(date('i')>=30 ? '30':'00'),0, 0, 0, 0, 0);
		die(json_encode(array("data"=>$chart_data,"stats"=>"")));
	
	## Stats update
	} else {
	
		#php session exist, use it for update stats
		if(session_id())
			$_SESSION["stats"]=$stats;
			
		## Stats for chart_data
		foreach($stats as $time=>$s) {
			UNSET($temp);
			$temp[]=$time;
			$chart_stats.="{$time}#";
			foreach($UGR->logs['servers'] as $server_name) {
				$counter=intval($stats[$time][$server_name]);
				$temp[]=$counter;
				$chart_stats.="{$server_name}={$counter}|";
			}
			$chart_stats.="~";
			$chart_data[]=$temp;
		}
		
		die(json_encode(array("data"=>$chart_data,"stats"=>$chart_stats)));

	}

?>