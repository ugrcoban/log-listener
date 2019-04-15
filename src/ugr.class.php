<?PHP
/**
 * PHP - COLLECT LOG FROM SERVERS
 *
 * Copyright - UGUR COBAN
 */
	
	class UGR {
		
		
		public $logs,$table,$db;
		private $sql;
		
		public function __construct() {
			
			## Default Settings ##
			date_default_timezone_set("Europe/Istanbul");
			header('Content-Type: text/html; charset=utf-8');
	
			
			/***** CONFIG - START *****/
			$this->sql=array("servername"=>'log-listener-db',"dbname"=>"sys_db","username"=>getenv('MYSQL_USER'),"password"=>getenv('MYSQL_PASSWORD'));
			
			$this->logs['levels']=array("INFO","WARN","FATAL","DEBUG","ERROR");
			$this->logs['servers']=array("Istanbul","Tokyo","Moskow","Beijing","London");
			$this->table['prefix']="logs_";
			$this->table['max_load']=2;#MB
			/***** CONFIG - END *****/
			
			
			## Connection to MYSQL ##
			$this->db=$this->connMysql();
			
			## PHP SESSION #Real time monitor data 
			if ((function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) || !session_id()) {
				session_start();
			}
		}
		
		
		
		/*****************
		* Function : Database - Connection
		* Return(object)  
										****************/
		private function connMysql() { 
			return new PDO("mysql:host={$this->sql['servername']};dbname={$this->sql['dbname']};charset=utf8mb4", $this->sql['username'], $this->sql['password'], array(PDO::ATTR_EMULATE_PREPARES => false,PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		
			/*try {
				$conn = new PDO("mysql:host={$this->sql['servername']};charset=utf8mb4", $this->sql['username'], $this->sql['password'], array(PDO::ATTR_EMULATE_PREPARES => false,PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
				$sql = "CREATE DATABASE IF NOT EXISTS {$this->sql['dbname']}";
				$conn->exec($sql);
				$sql = "use {$this->sql['dbname']}";
				$conn->exec($sql);
				return $conn;
				
			}	catch(PDOException $ex) {
					$this->PDOException($ex);
			}*/
		
		}
		
		
		
		
		/*****************
		* Function : Validation - Datetime checker
		* Input : date,format
		* Return(string/false) : date || false
												****************/
		public function validateDate($date, $format = 'Y-m-d') {
			$date=$this->validateText($date,19);
			$d = DateTime::createFromFormat($format, $date);
			return $d && $d->format($format) == $date ? $date : 0;
		}
		
		
		/*****************
		* Function : Validation - Input text checker
		* Input : text,long,case
		* Return(string) : text
												****************/
		public function validateText($text,$long=50,$case="case") {
			return $case=="case" ? ucwords(mb_substr(trim(str_replace(array("<",">","'",'"'),array("&lt;","&gt;","&#39;","&quot;"),$text)),0,$long,"UTF-8")) : mb_substr(trim(str_replace(array("<",">","'",'"'),array("&lt;","&gt;","&#39;","&quot;"),$text)),0,$long,"UTF-8");
		}
		
		
		
		/*****************
		* Function : Database - calculate all/selected table's rows & sizes(MB)
		* Input(string) : table || NULL
		* Return(Multi Array) : {rows,size}
												****************/
		public function getTableSizes($_table=NULL) {
			
			try {
				
				$stmt=$this->db->query("SELECT TABLE_NAME, TABLE_ROWS, round(((data_length + index_length)/1024/1024),2) TABLE_SIZE FROM information_schema.TABLES WHERE table_schema='{$this->getDbName()}' ".($_table===NULL ? "" : "AND table_name='{$_table}'")." ORDER BY(DATA_LENGTH + INDEX_LENGTH) DESC");
				While($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
					$return[$row['TABLE_NAME']]=array("rows"=>$row['TABLE_ROWS'],"size"=>$row['TABLE_SIZE']);
				}
				
			} catch(PDOException $ex) {
				$this->PDOException($ex);
			}
			
			return $return ? $return : NULL; 
		}
		
		
		
		
		/*****************
		* Function : Database - Get database last table details to continue insert log records
		* Return(Array) : {name,date,order}
												****************/
		public function getLastTable() {
			
			try {
				
				
				
				$last_table=NULL;
				$stmt=$this->db->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = '{$this->getDbName()}' AND table_name like '{$this->table['prefix']}%'");
				While($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
					$record=str_replace(array("{$this->table['prefix']}","_"),array("","-"),$row['TABLE_NAME']);
					$record_date=substr($record,0,10);
					$record_order=intval(substr($record,11));
					if($this->validateDate($record_date)) {
						if($last_table===NULL) {
							$last_table=array("date"=>$record_date,"order"=>$record_order);
						} else if($last_table['date']==$record_date) {
							$last_table['date']=$record_date;
							$last_table['order']=max($last_table['order'],$record_order);
						} else if($last_table['date']<$record_date) {
							$last_table['date']=$record_date;
							$last_table['order']=$record_order;
						}
					}
				}
				
				if($last_table!==NULL) {
					$last_table['name']="{$this->table['prefix']}".str_replace("-","_",$last_table['date']).($last_table['order'] ? "_".$last_table['order'] : '');
					##(innodb_stats_auto_update = off) use ANALYZE TABLE
					$this->db->query("ANALYZE TABLE ".$last_table['name']);
					return $last_table;
				}
				
			} catch(PDOException $ex) {
				$this->PDOException($ex);
			}
			
			return NULL;
		}
		
		
		
		/*****************
		* Function : Database - Create new table for log's
		* Input(Array) : {name,date,order} || NULL
		* Return(Array) : {name,date,order}
												****************/
		public function createNewTable($_last=NULL,$_day=NULL) {
			
			## Find new table name & other details
			$_day=$this->validateDate($_day) ? $_day : date("Y-m-d");
			if($_last['date']==$_day) {
				$new_table=array("name"=>"{$this->table['prefix']}".str_replace("-","_",$_day)."_".($_last['order']==0 ? 2 : $_last['order']+1),"date"=>$_day,"order"=>$_last['order']==0 ? 2 : $_last['order']+1);
			} else {
				$new_table=array("name"=>"{$this->table['prefix']}".str_replace("-","_",$_day),"date"=>$_day,"order"=>0);
			}
			
			
			## ENUM for log levels & server names
			$log_levels=is_array($this->logs['levels']) ? "ENUM('".implode("','",$this->logs['levels'])."')" : "VARCHAR(50)";
			$server_names=is_array($this->logs['servers']) ? "ENUM('".implode("','",$this->logs['servers'])."')" : "VARCHAR(50)";
	
			try {
				$this->db->exec("CREATE TABLE {$new_table['name']} (
					id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY, 
					timestamp DATETIME(3) NOT NULL,
					log_level {$log_levels} NOT NULL,
					server_name {$server_names} NOT NULL,
					log_detail VARCHAR(255) NOT NULL,
					INDEX timestamp_IND (timestamp),
					INDEX log_level_IND (log_level),
					INDEX server_name_IND (server_name),
					INDEX log_detail_IND (log_detail),
					CONSTRAINT uc_row_unique UNIQUE(timestamp,log_level,server_name,log_detail)
				)ENGINE=InnoDB");
			
			} catch(PDOException $ex) {
				$this->PDOException($ex);
			}
			
			return $new_table; 
		}
		
		
		
		
		
		
		/*****************
		* Function : Database - Query Search of all log's tables
		* Input(Array) : {start,end,log_level,server_name,log_detail}
		* Return(Multi Array) : {timestamp,log_level,server_name,log_detail}
												****************/
		public function searchTable($_opts=NULL) {
			
			$_opts['start']=$_opts['start'] ? $_opts['start'] : date("Y-m-")."01";
			$_opts['end']=$_opts['end'] ? $_opts['end'] : date("Y-m-d");
			
			##1- Which tables are required ? Ordered table no problem.
			$prev_date=NULL;#temp for prev table
			$search_tables=array();
			
			try {
				
				$stmt=$this->db->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = '{$this->getDbName()}' AND table_name like '{$this->table['prefix']}%'");
				While($row=$stmt->fetch(PDO::FETCH_ASSOC)) {
					
					$record_date=substr(str_replace(array("{$this->table['prefix']}","_"),array("","-"),$row['TABLE_NAME']),0,10);
					if($_opts['start']<=$record_date) {
						
						##i- Starting with previous dates? Maybe, prev_table's are required ?
						if($prev_date && !COUNT($search_tables) && $_opts['start']<=$record_date)
							$search_tables=$tables[$prev_date];#array, no problem
						
						##ii- That's enough - finish
						if($_opts['end']<$record_date) break;
						
						##iii- it is required table!
						$search_tables[]=$row['TABLE_NAME'];
					}
					
					$prev_date=$record_date;#for prev table
					$tables[$record_date][]=$row['TABLE_NAME'];#recording..
				}
				
			} catch(PDOException $ex) {
				$this->PDOException($ex);
			}
			
			
			##2- Connect to tables
			if(COUNT($search_tables)) {
				## SQL WHERE Options
				$sql_where=$_opts['log_level'] ? " AND log_level='{$this->validateText($_opts['log_level'],50,'nocase')}' " : '';
				$sql_where.=$_opts['server_name'] ? " AND server_name='{$this->validateText($_opts['server_name'],50,'nocase')}' " : '';
				$sql_where.=$_opts['log_detail'] ? " AND log_detail like '%{$this->validateText($_opts['log_detail'],100,'nocase')}%'" : '';
				
				## Mysql connection to tables
				foreach($search_tables as $search_table) {
					try{
						UNSET($sql,$r);
						$sql=$this->db->query("Select timestamp,log_level,server_name,log_detail FROM {$search_table} WHERE DATE(timestamp)>='{$_opts['start']}' AND DATE(timestamp)<='{$_opts['end']}' {$sql_where} ORDER BY timestamp ASC");
						While($r=$sql->fetch(PDO::FETCH_ASSOC)) {
							$rows[$r['timestamp']]=array("timestamp"=>$r['timestamp'],"log_level"=>$r['log_level'],"server_name"=>$r['server_name'],"log_detail"=>$r['log_detail']);
						}
					} catch(PDOException $ex) {
						$this->PDOException($ex);
					}
				}
			}
			
			// Ordered by timestamp again
			if($rows) {
				ksort($rows);
				return $rows;
			} else
				return NULL;
		}
		
		
		
		/*****************
		* Function : Database - Get my database name ( Private -> Public )
		* Output : String
										****************/
		public function getDbName() {
			return $this->sql['dbname'];
		}
		
		
		
		
		/*****************
		* Function : Database Tables name
		* Output : Array
										****************/
		public function getDbTables() {
			
			$arr=array();
			$statement = $this->db->query('SHOW TABLES');
			 
			//Loop through our table names.
			foreach($statement->fetchAll(PDO::FETCH_NUM) as $table){
				$arr[]=$table[0];
			}	
			
			#print_r($arr);
			return $arr;
		}
		
		
		
		/*****************
		* Function : Database : Catch error from sql query.
		* Output : Void ( maybe terminate function )
										****************/
		public function PDOException($_ex,$_opt=array("terminate"=>true)): void {
			
			## GET DETAILS FROM PDOException
			$err['message']=$_ex->getMessage();
			$err['code']=$_ex->getCode();
			$err['file']=$_ex->getFile();
			$err['line']=$_ex->getLine();
			foreach($_ex->getTrace() as $t) {
				if($t['function']=="query" && is_string($t['args'][0])) 
					$err['query']=$t['args'][0];
			}
			
			## START - CREATE ERROR LOG
				/* ....... */
			## END - CREATE ERROR LOG
			
			
			if($_opt['terminate']) {
				die("ERROR - {$_ex->getMessage()}!");
			}
			return;
		}
		
		
		
		
		/*****************
		* Function : TEST/DEBUG - Simple Log Creator
		* Output(Multi Array) : {timestamp,log_level,server_name,log_detail} || NULL 
										****************/
		public function simpleLogCreator() {
			
			while(++$i<100) {
				if(rand()%$i==5) break;
				
				$test['timestamp']=substr((new DateTime( date('Y-m-d H:i:s.'.sprintf("%03d",(microtime(true) - floor(microtime(true))) * 1000), microtime(true)) ))->format("Y-m-d H:i:s.u"),0,-3);
				$test['timestamp']=substr($test['timestamp'],0,20).rand(100,999);
				$test['level']=is_array($this->logs['levels']) ? $this->logs['levels'][rand()%COUNT($this->logs['levels'])] : 'INFO';
				$test['server']=is_array($this->logs['levels']) ? $this->logs['servers'][(rand(100,time())*$i)%COUNT($this->logs['servers'])] : 'Undefined';
				
				if(!$check_timestamp[$test['timestamp']]) {
					$records[]=array($test['timestamp'],$test['level'],$test['server'],"Hello-from-".$test['server']);
				}
				
				$check_timestamp[$test['timestamp']]=$test['timestamp'];
			}
			
			## EMPTY array
			if(rand(1,100)%10==5)
				UNSET($records);
			
			return $records ? $records : NULL;
		}
		
	}//endClass
	
?>