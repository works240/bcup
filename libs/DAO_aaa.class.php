<?php
/**
 * Description of DAO_aaa Class
 *
 * @author K240
 */
class DAO_aaa {
    
    private $log = null;
    private $pdoObject = null;
    private $sql = '';
    
    public function __construct(){
        $config = parse_ini_file(CONFIG_DIR . DS . 'dbconfig.ini' , false);
        if($config === false){
            throw new Exception('Failed to load dbconfig.ini');
        }
        
        $this->log = Log::singleton('file', LOG_DIR . DS . 'aaa_dao_'.date('z').'.log' , 'aaa_dao');
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
	
	public function beginTransaction() {
		$this->pdoObject->beginTransaction();
	}
	
	public function commit() {
		$this->pdoObject->commit();
	}
	
	public function setInfo($uri, $ua, $ip = '127.0.0.1', $user = null ) {
		$this->execSQL("SET @URI='{$uri}';");
		$this->execSQL("SET @UA='{$ua}';");
		$this->execSQL("SET @IP='{$ip}';");
		if ( isset($user) ) { $this->execSQL("SET @USER=CONCAT('{$user}/', USER());"); }
	}

	public function dbquote($value) {
		return $this->pdoObject->quote($value);
	}
	
	public function execSQL($sql) {
		$result = $this->pdoObject->exec($sql);
		if ($result === false) {
			print_r($result);
		}
		return $result;
	}

//	getEvent($eventcode)
//	  $eventcode イベントコード
//	  指定されたイベント(1件)を取得する。
//	
	public function getEvent($eventcode) {
		$this->sql = 'SELECT eventcode, event_name, event_master, event_masterurl, event_url, event_mylists, y_eventcode, reg_time_min, reg_time_max, reg_post_start, reg_post_end, reg_vote_start, reg_vote_end, reg_event_start, reg_event_end, reg_theme_must, reg_title, reg_tag, reg_team, reg_point_type, event_close, get_rss_start, get_rss_end, get_rss_sch '
			   .   'FROM event '
			   .  "WHERE eventcode = '".$eventcode."' ";
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false) {
			throw new Exception('Failed to execute select query:'.$this->sql);
		}

		$event = array();
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$event = $row;
		}
		
		return $event;
	}
	
//	getEventList($eventcode)
//	  $eventcode イベントコード(LIKEのワイルドカード)
//	  指定されたイベント(複数件)を取得する。
//	
	public function getEventList($eventcode) {
		$this->sql = 'SELECT eventcode, event_name, event_master, event_masterurl, event_url, event_mylists, y_eventcode, reg_time_min, reg_time_max, reg_post_start, reg_post_end, reg_vote_start, reg_vote_end, reg_event_start, reg_event_end, reg_theme_must, reg_title, reg_tag, reg_team, reg_point_type, event_close, get_rss_start, get_rss_end, get_rss_sch '
			   .   'FROM event '
			   .  "WHERE eventcode LIKE '{$eventcode}' "
			   .  'ORDER BY reg_vote_end DESC ';
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false) {
			throw new Exception('Failed to execute select query:'.$this->sql);
		}

		$eventlist = $result->fetchAll(PDO::FETCH_ASSOC);
		
		return $eventlist;
	}
	
//	getTheme($eventcode)
//	  $eventcode イベントコード
//	  指定されたイベントのテーマを取得する。
//	
	public function getTheme($eventcode) {
		$this->sql = 'SELECT eventcode, theme, theme_name, theme_regexp '
			   .   'FROM theme '
			   .  "WHERE eventcode = '".$eventcode."' "
			   .  'ORDER BY theme_order ';
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false) {
			throw new Exception('Failed to execute select query:'.$this->sql);
		}

		$theme = array();
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$theme[] = $row;
		}
		
		return $theme;
	}
	
//	getVideoList($limit ,$offset ,$eventCode, $option, $order, $search)
//	  $limit 件数
//	  $offset 開始位置
//	  $eventCode イベントコード
//	  $option 抽出条件(省略時：all)
//	  $order 並び順(省略時：登録が新しい順)
//	  $search 追加検索条件(省略可)
//	  指定されたイベントのテーマを取得する。
//	
	public function getVideoList($limit ,$offset ,$eventCode, $option = 'all', $order = 'ev_seq desc', $search = null) {
		$videoList = array();
	
/*
 * $option (省略時：all) 
 * 	=>	all ... 削除済を含む全ての動画データ
 * 		nd ... 削除済みを含まない
 * 		ok ... 削除済み、違反、を含まない
 * 		pf ... フライング
 * 		pl ... 遅刻
 * 		ts ... 時間不足
 * 		tl ... 時間超過
 * 		ti ... タイトルNG
 * 		th ... テーマNG
 * 		tg ... タグNG
 * 		ot ... その他NG
 * 		ye ... 予選動画指定NG
 * 		ng ... 違反確定(ngcode IS NOT NULL)
 * 		del ... マイリスから削除された動画のみ
 * 		nd ... マイリスから削除されていない
 * 		vd ... マイリスから削除された動画のみ
 * $order (省略時：登録が新しい順) 
 * 	=>	ORDER句に指定する形式で、項目名と方向を指定、常に最下位の並び順として'ev_seq desc'が指定される。
 * $search 
 * 	=>	WEHRE句に指定する形式で検索条件を文字列で指定する。
 *		常にeventcode=の条件が指定され、その後に、AND条件で追加される。
 */

		$where   =  "WHERE eventcode = '{$eventCode}' ";
		if ( $option == 'ok' ) {
			$where .= 'AND delete_at IS NULL AND ngcode IS NULL ';
		} elseif ( $option == 'del' ) {
			$where .= 'AND delete_at IS NOT NULL ';
		} elseif ( in_array($option, array('pf', 'pl', 'ts', 'tl', 'ti', 'th', 'tg', 'ot')) ) {
			$where .= "AND ( ngmemo LIKE '%$option%' OR ngcode LIKE '%$option%' ) ";
		} elseif ( $option == 'ye' ) {
			$where .= "AND ngmemo LIKE '%y_%' ";
		} elseif ( $option == 'ng' ) {
			$where .= 'AND ngcode IS NOT NULL ';
		} elseif ( $option == 'nd' ) {
			$where .= 'AND delete_at IS NULL ';
		} elseif ( $option == 'vd' ) {
			$where .= 'AND video_delete_at IS NOT NULL ';
		} elseif ( $option == 'all' ) {
			$where .= '';
		}
		if ( $search ) {
			$where .= "AND {$search} ";
		}
		
		$this->sql = 'SELECT COUNT(*) as count_all FROM videolist '.$where;
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query:'.$this->sql);
		}
		$count = $result->fetch(PDO::FETCH_ASSOC);
		$videoList['count_all'] = (int) $count['count_all'];
		
		$this->sql = 'SELECT eventcode,nicono,title,watch_url,postdate,time,length,userid,niconame,username,theme,descript,thumbnail,tag, point,memo,ngmemo,ngcode,delete_at,team,view_e,comme_e,mylist_e,yosenp,y_eventcode,y_nicono,rec_time,view,comme,mylist,video_delete_at '
		           .   'FROM videolist '
				   .  $where;
		if ( $order ) {
			$this->sql .= "ORDER BY {$order}, ev_seq DESC ";
		}
		if ($limit > 0 || $offset > 0) {
			$this->sql .= "LIMIT {$offset}, {$limit}";
		} else {
			$this->sql .= '';
		}
		$this->log->debug($this->sql);

		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query:'.$this->sql);
		}
		$videoList['videolist'] = $result->fetchAll(PDO::FETCH_ASSOC);
		if($videoList === false){
			throw new Exception('Failed to get videolist data');
		}
		
		return $videoList;
    }
    
//	importVideo($video)
//	  videoテーブルに、1レコード分の連想配列$videoの値で、レコードを追加する。
//	  もし、主キーが同じレコードが既にある場合は、$set_listの指定で、更新する。(ON DUPLICATE KEY UPDATE)
//	  値に'NULL'を指定すると、NULL値となる。
//	  追加あるいは更新したレコードの件数を返す。(0 あるいは 1)
//
	public function importVideo($video, $set_list) {
		if (!isset($video['nicono']) || is_null($video['nicono'])) {
			throw new Exception('nicono is not set');
		}

		$count = 0;

		$item_list = array();
		$value_list = array();

		foreach ($video as $key => $value) {
			$item_list[] = "`$key`";
			
			if ($value == 'NULL') {
				$value_list[] = 'NULL';
			} elseif (in_array($key, array('time','view','comme','mylist'))) {
				$value_list[] = $value;
			} else {
				$value_list[] = $this->pdoObject->quote($value);
			}
		}

		if (!empty($item_list)) { 
			$this->sql = 'INSERT INTO `video` ('.implode(',', $item_list).') VALUES('.implode(',', $value_list).')';
			if (!empty($set_list)) {
				$this->sql .= ' ON DUPLICATE KEY UPDATE '.implode(',', $set_list);
			}
			$this->sql .= ";";
			$this->log->debug($this->sql);
			$count += $this->execSQL($this->sql);
		}

		return $count;
	}

//	importEventVideo($eventvideo)
//	  eventvideoテーブルに、1レコード分の連想配列$eventvideoの値で、レコードを追加する。
//	  もし、主キーが同じレコードが既にある場合は、$set_listの指定で、更新する。(ON DUPLICATE KEY UPDATE)
//	  値に'NULL'を指定すると、NULL値となる。
//	  追加あるいは更新したレコードの件数を返す。(0 あるいは 1)
//
	public function importEventVideo($eventvideo, $set_list) {
		if (!isset($eventvideo['eventcode']) || is_null($eventvideo['eventcode'])) {
			throw new Exception('eventcode is not set');
		}

		if (!isset($eventvideo['nicono']) || is_null($eventvideo['nicono'])) {
			throw new Exception('nicono is not set');
		}

		$count = 0;

		$item_list = array();
		$value_list = array();

		foreach ($eventvideo as $key => $value) {
			$item_list[] = "`$key`";
			
			if ($value == 'NULL') {
				$value_list[] = 'NULL';
			} elseif (in_array($key, array('team','point','view_e','comme_e','mylist_e','yosen_p'))) {
				$value_list[] = $value;
			} else {
				$value_list[] = $this->pdoObject->quote($value);
			}
		}

		if (!empty($item_list)) { 
			$this->sql = 'INSERT INTO `eventvideo` ('.implode(',', $item_list).') VALUES('.implode(',', $value_list).')';
			if (!empty($set_list)) {
				$this->sql .= ' ON DUPLICATE KEY UPDATE '.implode(',', $set_list);
			}
			$this->sql .= ";";
			$this->log->debug($this->sql);
			$count += $this->execSQL($this->sql);
		}

		return $count;
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
			throw new Exception('Failed to execute select query:'.$this->sql);
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
    

//	getVideoInfo($nicono ,$eventCode)
//	  $nicono で指定した動画の情報をvideolist形式で取得する。
//	  $eventCode を指定すると、特定のイベントだけを対象とする。
//	
	public function getVideoInfo($nicono ,$eventCode = null) {
		$this->sql = 'SELECT * FROM videolist '
		           .  "WHERE nicono = '{$nicono}' ";
		if ($eventCode) {
			$this->sql .= "AND eventcode = '{$eventCode}' ";
		}
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query:'.$this->sql);
		}

		$videoInfo = $result->fetchAll(PDO::FETCH_ASSOC);
		if($videoInfo === false){
			throw new Exception('Failed to get videolist data');
		}
		
		return $videoInfo;
	}

//	updateEventVideo($eventvideo)
//	  eventvideoテーブルを、1レコード分の連想配列$eventvideoの値で更新する。
//	  次の項目は更新しない(主キー、あるいは、代替キー)
//	    eventcode,nicono,,ev_seq
//	  値に'NULL'を指定すると、NULL値に変更する。
//	  次の項目は、DB上の項目がNULLの場合のみ更新する。(値がセットされていれば上書きしない)
//	    delete_at
//	  DB上の値と、指定した値が同一である項目は更新しない。
//	  更新したレコードの件数を返す。(0 あるいは 1)
//
	public function updateEventVideo( $eventvideo, $ng = null, $ngset = null ) {
		$this->sql = "SELECT * FROM eventvideo WHERE eventcode = '{$eventvideo['eventcode']}' AND nicono = '{$eventvideo['nicono']}' LIMIT 1 FOR UPDATE";
				$this->log->debug($this->sql);
		
		$result = $this->pdoObject->query($this->sql);
		if($result === false){
			throw new Exception('Failed to execute select query:'.$this->sql);
		}
		
		$count = 0;
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$set_list = array();
			foreach ($eventvideo as $key => $value) {
				if (!in_array($key, array('eventcode', 'nicono', 'ev_seq', 'ngcode', 'ngmemo')) && $value != $row[$key]) {
					if ($value == 'NULL') {
						if (!is_null($row[$key])) {
							$set_list[] = "{$key} = NULL";
						}
					} elseif (in_array($key, array('delete_at'))) {
						if (is_null($row[$key])) {
							$set_list[] = "{$key} = ".$this->pdoObject->quote($value);
						}
					} elseif (in_array($key, array('team','point','view_e','comme_e','mylist_e','yosen_p'))) {
						$set_list[] = "{$key} = ".$value;
					} else {
						if ( $value ) {
							$set_list[] = "{$key} = ".$this->pdoObject->quote($value);
						}
					}
				}
			}
			if ( isset($ng) && in_array($ng, array('pf', 'pl', 'ts', 'tl', 'ti', 'th', 'tg', 'ot', 'yy')) ) {
				$ngmemo = array();
				$add_ng = $ng;
				foreach ( explode(',', $row['ngmemo']) as $ngc ) {
					if ( $ngc == $ng ) {
						if ( $ngc == 'pf' || $ngc == 'pl' ) {
							$add_ng = 'pp';
						} elseif ( $ngc == 'ts' || $ngc == 'tl' ) {
							$add_ng = 'tt';
						} elseif ( $ngc == 'ti' ) {
							$add_ng = 'it';
						} elseif ( $ngc == 'th' ) {
							$add_ng = 'ht';
						} elseif ( $ngc == 'tg' ) {
							$add_ng = 'gt';
						} else {
							$add_ng = '';
						}
					} elseif ( $ngc == 'pp' && ( $ng == 'pf' || $ng == 'pl' ) ) {
						$add_ng = '';
					} elseif ( $ngc == 'tt' && ( $ng == 'ts' || $ng == 'tl' ) ) {
						$add_ng = '';
					} elseif ( $ngc == 'it' && $ng == 'ti' ) {
						$add_ng = '';
					} elseif ( $ngc == 'ht' && $ng == 'th' ) {
						$add_ng = '';
					} elseif ( $ngc == 'gt' && $ng == 'tg' ) {
						$add_ng = '';
					} elseif ( ($ngc == 'pf' && $ng == 'pl') || ($ngc == 'pl' && $ng == 'pf') ) {
						$add_ng = $ng;
					} elseif ( ($ngc == 'ts' && $ng == 'tl') || ($ngc == 'tl' && $ng == 'ts') ) {
						$add_ng = $ng;
					} elseif ( $ngc ) {
						$ngmemo[] = $ngc;
					}
				}
				if ( $add_ng ) {
					$ngmemo[] = $add_ng;
				}
				if ( empty($ngmemo) ) {
					$set_list[] = "ngmemo = null";
				} else {
					sort($ngmemo);
					$set_list[] = "ngmemo = '".implode(',', $ngmemo)."'";
				}
			}
			
			if ( $ngset ) {
				$ngcode = array();
				foreach ( explode(',', $row['ngmemo']) as $ngc ) {
					if ( in_array($ngc, array('pf', 'pl', 'ts', 'tl', 'ti', 'th', 'tg', 'ot')) ) {
						$ngcode[] = $ngc;
					}
				}
				
				if ( empty($ngcode) || $row['ngcode'] == implode(',', $ngcode) ) {
					$set_list[] = "ngcode = null";
				} else {
					sort($ngcode);
					$set_list[] = "ngcode = '".implode(',', $ngcode)."'";
				}
			}
			
			if (!empty($set_list)) {
				$this->sql = 'UPDATE eventvideo SET '.implode(",", $set_list)." WHERE eventcode = '{$eventvideo['eventcode']}' AND nicono = '{$eventvideo['nicono']}'";
				$this->log->debug($this->sql);
				$count += $this->execSQL($this->sql);
			}
		}
		
		return $count;
	}
}


