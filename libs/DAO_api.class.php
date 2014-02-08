<?php
/**
 * Description of PDOClass
 *
 * @author seri
 */
class DAOClass {
    
    private $log = null;
    private $pdoObject = null;
    private $sql = '';
    
    public function __construct(){
        $config = parse_ini_file(CONFIG_DIR . DS . 'dbconfig.ini' , false);
        if($config === false){
            throw new Exception('Failed to load dbconfig.ini');
        }
        
        $this->log = Log::singleton('file', LOG_DIR . DS . 'api_dao_'.date('z').'.log' , 'api_dao');
        if($this->log === false){
            throw new Exception('Failed to create object of PEAR::Log');
        }
        
        $dsn = 
            'mysql:dbname='.$config['dbname'].
            ';charset=' . $config['charset'] .
            ';host='.$config['host'].
            ';port='.$config['port'];
        
        $this->pdoObject = new PDO($dsn , $config['user'] , $config['password']);
        if($this->pdoObject === false){
            throw new Exception('Failed to create object of PDO');
        }
		$this->log->info("Connect: {$dsn};user={$config['user']};remote_addr={$_SERVER['REMOTE_ADDR']}");
	}
    
	public function getVideoList($limit , $offset ,$eventCode ,$option = 'normal'){
		$this->sql = "SELECT eventcode,nicono,title,watch_url,postdate,time,length,userid,niconame,username,IFNULL(theme, '') as theme,descript,thumbnail,tag, point,memo,ngmemo,ngcode,delete_at,team,view_e,comme_e,mylist_e,yosenp,y_eventcode,y_nicono,rec_time,view,comme,mylist,video_delete_at "
		           .   'FROM videolist '
		           .  "WHERE eventcode = '{$eventCode}' AND postdate IS NOT NULL ";
		if ( $option == 'normal' ) {
			$this->sql .= 'AND delete_at IS NULL ';
		} elseif ( $option == 'deleted' ) {
			$this->sql .= 'AND delete_at IS NOT NULL ';
		} elseif ( $option == 'all' ) {
			$this->sql .= '';
		}
		$this->sql .= "AND userid NOT IN( SELECT userid FROM blacklist WHERE delete_at IS NULL ) ";
		$this->sql .= "LIMIT {$offset}, {$limit}";
		$this->log->debug($this->sql);

		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query');
		}

		$videoList = $result->fetchAll(PDO::FETCH_ASSOC);
		if($videoList === false){
			throw new Exception('Failed to get videolist data');
		}
		
		return $videoList;
    }
    
	public function getLastNums($nicono, $rec_time) {
		$this->sql = 'SELECT nicono,rec_time_h,view_h,comme_h,mylist_h '
		           .   'FROM tblnums '
		           .  "WHERE nicono = '{$nicono}' "
		           .    "AND rec_time_h = (SELECT MAX(rec_time_h) FROM tblnums WHERE nicono = '{$nicono}' AND rec_time_h <= '{$rec_time}') "
		           .  'LIMIT 1';
//		$this->log->debug($this->sql);

		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query');
		}

		$tblnums = $result->fetch(PDO::FETCH_ASSOC);
		if($tblnums === false){
			$tblnums = array('nicono'=>$nicono, 'rec_time_h'=>'2000-01-01 00:00;00', 'view_h'=>'0', 'comme_h'=>'0', 'mylist_h'=>'0');
		}

		return $tblnums;
	}

	private function doQuery(){
        if($this->sql == ''){
            return false;
        }
        
        $result = $this->pdoObject->query($this->sql);

		return $result;
    }
    
    private function initSQL(){
        $this->sql = '';
    }
    

}


