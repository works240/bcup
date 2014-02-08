<?php
class DAO_import {
    
	private $log = null;
	private $pdoObject = null;
	private $sql = '';
    
	public function __construct(){
		$config = parse_ini_file(CONFIG_DIR . DS . 'dbconfig.ini' , false);
		if($config === false){
			throw new Exception('Failed to load dbconfig.ini');
		}
        
		$this->log = Log::singleton('file', LOG_DIR . DS . 'import_dao_'.date('z').'.log' , 'import_dao');
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
	
	public function setInfo($uri, $ua, $ip = '127.0.0.1') {
		$this->execSQL("SET @URI='{$uri}';");
		$this->execSQL("SET @UA='{$ua}';");
		$this->execSQL("SET @IP='{$ip}';");
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
//	  指定されたイベントを取得する。
//	
	public function getEvent($eventcode) {
		$this->sql = 'SELECT eventcode, event_name, event_master, event_masterurl, event_url, event_mylists, reg_time_min, reg_time_max, reg_post_start, reg_post_end, reg_vote_start, reg_vote_end, reg_event_start, reg_event_end, reg_theme_must, reg_title, reg_tag, reg_team, get_rss_start, get_rss_end, get_rss_sch FROM event '
			   .  "WHERE eventcode = '".$eventcode."'";
		
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false) {
			throw new Exception('Failed to execute select query');
		}

		$event = array();
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$event = $row;
		}
		
		return $event;
	}
	
//	getActiveEvent($now)
//	  $now 現在日時
//	  指定された時刻時点で有効(RSS取得期間内)のイベントを取得する。
//	
	public function getActiveEvent($now = null, $sch = null) {
		if ( is_null($now) ) {
			$now = time();
		}

		$this->sql = 'SELECT eventcode, event_mylists, reg_time_min, reg_time_max, reg_post_start, reg_post_end, reg_vote_start, reg_vote_end, reg_event_start, reg_event_end, reg_theme_must, reg_title, reg_tag, reg_team, reg_point_type, y_eventcode, get_rss_sch FROM event '
				   .  "WHERE get_rss_start <= '".date(DATEFORMAT, $now)."'"
				   .  "  AND get_rss_end   >= '".date(DATEFORMAT, $now)."'";
		
		if (is_array($sch)) {
			$sch_str = "'".implode("','", $sch)."'";
			$this->sql .= " AND get_rss_sch IN({$sch_str})";
		}
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false) {
			throw new Exception('Failed to execute select query');
		}

		$active_events = array();
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$active_events[] = $row;
		}
		
		return $active_events;
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
	

//	getVideoIDs($eventCode ,$option)
//	  $eventCode で指定したイベントに登録されている動画ID(nicono)の一覧を、配列で取得する。
//	  $option で対象範囲をしていする。省略時は'update'。
//	    update: videoあるいはeventvideoに新規登録され、まだ更新されていない動画、および、ngcodeやadmin_msgが設定されている動画
//	    new: videoあるいはeventvideoに新規登録され、まだ更新されていない動画
//	    all: 登録されている全ての動画(削除済みを含む)
//	    normal: 登録されている有効な動画(削除済みを含まない)
//	    deleted: 削除済みの動画
//	
	public function getVideoIDs($eventCode ,$option = 'update') {
		$this->sql = 'SELECT v.nicono as nicono FROM video v JOIN eventvideo ev ON v.nicono = ev.nicono '
		           .  "WHERE eventcode = '{$eventCode}' ";
		if ( $option == 'normal' ) {
			$this->sql .= 'AND ev.delete_at IS NULL ';
		} elseif ( $option == 'deleted' ) {
			$this->sql .= 'AND ev.delete_at IS NOT NULL ';
		} elseif ( $option == 'all' ) {
			$this->sql .= '';
		} elseif ( $option == 'new' ) {
			$this->sql .= 'AND ( v.update_at IS NULL OR ev.update_at IS NULL )';
		} elseif ( $option == 'update' ) {
			$this->sql .= 'AND ( v.update_at IS NULL OR ev.update_at IS NULL OR ev.ngmemo IS NOT NULL OR ev.ngcode IS NOT NULL OR ev.admin_msg IS NOT NULL)';
		}
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query');
		}

		$eventvideos = $result->fetchAll(PDO::FETCH_ASSOC);
		if($eventvideos === false){
			throw new Exception('Failed to get videolist data');
		}
		
		$nicono_list = array();
		foreach ( $eventvideos as $row ) {
			$nicono_list[] = $row['nicono'];
		}
		
		return $nicono_list;
	}
	
//	getEventVideo($eventCode, $nicono)
//	  $eventCode, $nicono で指定したeventvideoのレコードを取得する。
//	
	public function getEventVideo($eventCode, $nicono) {
		$this->sql = 'SELECT * FROM eventvideo '
		           .  "WHERE eventcode = '{$eventCode}' AND nicono = '{$nicono}' "
		           .  'LIMIT 1 ';

		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query');
		}

		$eventvideo = $result->fetch(PDO::FETCH_ASSOC);
		if($eventcode === false){
			throw new Exception('Failed to get eventcode data');
		}
		
		return $eventvideo;
	}
	
//	getVideolist($nicono ,$eventCode)
//	  $nicono で指定した動画の情報をvideolist形式で取得する。
//	  $eventCode を指定すると、特定のイベントだけを対象とする。
//	
	public function getVideolist($nicono ,$eventCode = null) {
		$this->sql = 'SELECT * FROM videolist '
		           .  "WHERE nicono = '{$nicono}' ";
		if ($eventCode) {
			$this->sql .= "AND eventcode = '{$eventCode}' ";
		}
		$this->log->debug($this->sql);
		
		$result = $this->doQuery();
		if($result === false){
			throw new Exception('Failed to execute select query');
		}

		$videolists = $result->fetchAll(PDO::FETCH_ASSOC);
		if($videolists === false){
			throw new Exception('Failed to get videolist data');
		}
		
		return $videolists;
	}
	
//	updateVideo($video)
//	  videoテーブルを、1レコード分の連想配列$videoの値で更新する。
//	  次の項目は更新しない(主キー、あるいは、代替キー)
//	    nicono
//	  値に'NULL'を指定すると、NULL値に変更する。
//	  次の項目は、DB上の項目がNULLの場合のみ更新する。(値がセットされていれば上書きしない)
//	    delete_at
//	  DB上の値と、指定した値が同一である項目は更新しない。
//	  更新したレコードの件数を返す。(0 あるいは 1)
//
	public function updateVideo($video) {
		$this->sql = "SELECT * FROM video WHERE nicono = '{$video['nicono']}' LIMIT 1 FOR UPDATE";
		$result = $this->pdoObject->query($this->sql);
		if($result === false){
			throw new Exception('Failed to execute select query');
		}
		
		$count = 0;
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$set_list = array();
			foreach ($video as $key => $value) {
				if (!in_array($key, array('nicono')) && $value != html_entity_decode($row[$key])) {
					if ($value == 'NULL') {
						if (!is_null($row[$key])) {
							$set_list[] = "{$key} = NULL";
						}
					} elseif (in_array($key, array('delete_at'))) {
						if (is_null($row[$key])) {
							$set_list[] = "{$key} = ".$this->pdoObject->quote($value);
						}
					} elseif (in_array($key, array('time','view','comme','mylist'))) {
						$set_list[] = "{$key} = ".$value;
					} else {
						if ( $value ) {
							$set_list[] = "{$key} = ".$this->pdoObject->quote($value);
						}
					}
				}
			}
			
//var_dump($set_list);
			if (!empty($set_list)) {
				$this->sql = 'UPDATE video SET '.implode(",", $set_list)." WHERE nicono = '{$video['nicono']}'";
				$this->log->debug($this->sql);
				$count += $this->execSQL($this->sql);
			}
		}
		
		return $count;
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
	public function updateEventVideo($eventvideo) {
		$this->sql = "SELECT * FROM eventvideo WHERE eventcode = '{$eventvideo['eventcode']}' AND nicono = '{$eventvideo['nicono']}' LIMIT 1 FOR UPDATE";
		
		$result = $this->pdoObject->query($this->sql);
		if($result === false){
			throw new Exception('Failed to execute select query');
		}
		
		$count = 0;
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			$set_list = array();
			foreach ($eventvideo as $key => $value) {
				if (!in_array($key, array('eventcode', 'nicono', 'ev_seq', 'ngcode')) && $value != $row[$key]) {
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
					} elseif ( $key == 'auto_ng' ) {
						list($ngmemo, $ngcode) = $this->setNG( $row['ngmemo'], $row['ngcode'], $value );
						if ( $ngmemo != $row['ngmemo']) {
							$set_list[] = "ngmemo = ".($ngmemo ? "'{$ngmemo}'" : 'null');
						}
						if ( $ngcode != $row['ngcode']) {
							$set_list[] = "ngcode = ".($ngcode ? "'{$ngcode}'" : 'null');
						}
						$this->log->debug("{$row['nicono']} ngmemo={$row['ngmemo']} ngcode={$row['ngcode']} auto_ng={$value}");
					} else {
						if ( $value ) {
							$set_list[] = "{$key} = ".$this->pdoObject->quote($value);
						}
					}
				}
			}
			
//var_dump($set_list);
			if (!empty($set_list)) {
				$this->sql = 'UPDATE eventvideo SET '.implode(",", $set_list)." WHERE eventcode = '{$eventvideo['eventcode']}' AND nicono = '{$eventvideo['nicono']}'";
				$this->log->debug($this->sql);
				$count += $this->execSQL($this->sql);
			}
		}
		
		return $count;
	}

//	setDelEventVideo( $event, $nicono, $reset )
//	  eventvideoテーブルを論理削除する。
//	  $resetがTRUEの場合、論理削除から復活する。
//	  更新したレコードの件数を返す。(0 あるいは 1)
//
	public function setDelEventVideo( $event, $nicono, $reset = FALSE ) {
		$this->sql = "SELECT * FROM eventvideo WHERE eventcode = '{$event['eventcode']}' AND nicono = '{$nicono}' LIMIT 1 FOR UPDATE";
		
		$result = $this->pdoObject->query($this->sql);
		if($result === false){
			throw new Exception('Failed to execute select query');
		}
		
		$count = 0;
		while ( $row = $result->fetch(PDO::FETCH_ASSOC) ) {
			if ( $reset ) {
				if ( $row['delete_at'] ) {
					$this->sql = 'UPDATE eventvideo SET delete_at = NULL '
						       . " WHERE eventcode = '{$event['eventcode']}' AND nicono = '{$nicono}'";
					$this->log->debug($this->sql);
					$count += $this->execSQL($this->sql);
				}
			} else {
				$this->sql = 'UPDATE eventvideo SET delete_at = now() '
					       . " WHERE eventcode = '{$event['eventcode']}' AND nicono = '{$nicono}'";
				$this->log->debug($this->sql);
				$count += $this->execSQL($this->sql);
			}
		}
		
		return $count;
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


	public function setNG($old_ngmemo, $old_ngcode, $new_ng) {
		$ngmemo = array();
		$ngcode = array();
		$new_ng_array = explode(',', $new_ng);
		$old_ngmemo_array = explode(',', $old_ngmemo);
		$old_ngcode_array = explode(',', $old_ngcode);

		foreach ( $new_ng_array as $ngc ) {
			if ( $ngc == 'pl' && !in_array('pp', $old_ngmemo_array) ) {
				$ngmemo[] = $ngc;
				$ngcode[] = $ngc;
			} elseif ( in_array($ngc, array('ts', 'tl')) && !in_array('tt', $old_ngmemo_array) ) {
				$ngmemo[] = $ngc;
				$ngcode[] = $ngc;
			} elseif ( $ngc == 'ti' && !in_array('it', $old_ngmemo_array) ) {
				$ngmemo[] = $ngc;
				$ngcode[] = $ngc;
			} elseif ( $ngc == 'th' && !in_array('ht', $old_ngmemo_array) ) {
				$ngmemo[] = $ngc;
				$ngcode[] = $ngc;
			} elseif ( $ngc == 'tg' && !in_array('gt', $old_ngmemo_array) ) {
				$ngmemo[] = $ngc;
				$ngcode[] = $ngc;
			} elseif ( in_array($ngc, array('y0', 'y8', 'y9', 'ym', 'yt', 'yh', 'yu')) ) {
				$ngmemo[] = $ngc;
			} 
		}
 
		foreach ( $old_ngmemo_array as $ngc ) {
			if ( !in_array($ngc, $ngmemo) ) {
				if ( in_array($ngc, array('pp', 'tt', 'it', 'ht', 'gt', 'yy')) ) {
					$ngmemo[] = $ngc;
				} elseif ( in_array($ngc, array('pf', 'ot')) ) {
					$ngmemo[] = $ngc;
				} elseif ( $ngc == 'pl' && !in_array('pp', $new_ng_array) ) {
					$ngmemo[] = $ngc;
				} elseif ( in_array($ngc, array('ts', 'tl')) && !in_array('tt', $new_ng_array) ) {
					$ngmemo[] = $ngc;
				} elseif ( $ngc == 'ti' && !in_array('it', $new_ng_array) ) {
					$ngmemo[] = $ngc;
				} elseif ( $ngc == 'th' && !in_array('ht', $new_ng_array) ) {
					$ngmemo[] = $ngc;
				} elseif ( $ngc == 'tg' && !in_array('gt', $new_ng_array) ) {
					$ngmemo[] = $ngc;
				} elseif ( in_array($ngc, array('y0', 'y8', 'y9', 'ym', 'yt', 'yh', 'yu')) && in_array($ngc, $new_ng_array) && !in_array('yy', $new_ng_array) ) {
					$ngmemo[] = $ngc;
				}
			} 
		}

		foreach ( $old_ngcode_array as $ngc ) {
			if ( !in_array($ngc, $ngcode) ) {
				if ( in_array($ngc, array('pf', 'ot')) ) {
					$ngcode[] = $ngc;
				} elseif ( $ngc == 'pl' && !in_array('pp', $new_ng_array) ) {
					$ngcode[] = $ngc;
				} elseif ( in_array($ngc, array('ts', 'tl')) && !in_array('tt', $new_ng_array) ) {
					$ngcode[] = $ngc;
				} elseif ( $ngc == 'ti' && !in_array('it', $new_ng_array) ) {
					$ngcode[] = $ngc;
				} elseif ( $ngc == 'th' && !in_array('ht', $new_ng_array) ) {
					$ngcode[] = $ngc;
				} elseif ( $ngc == 'tg' && !in_array('gt', $new_ng_array) ) {
					$ngcode[] = $ngc;
				}
			} 
		}
		
		sort($ngmemo);
		sort($ngcode);
		
		return array(implode(',', $ngmemo), implode(',', $ngcode));
	}

}

