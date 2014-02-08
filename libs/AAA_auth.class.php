<?php
/**
 * Description of AAA_auth Class
 *
 * @author K240
 */
class AAA_auth {
    
    private $log = null;
    private $auth_xml = null;
    private $auth_info = null;
    
    public function __construct(){
		$this->auth_xml = simplexml_load_file( CONFIG_DIR . DS . 'aaa_auth.xml' );
		if($this->auth_xml === false){
			throw new Exception('Failed to load dbconfig.ini');
		}
        
		$this->log = Log::singleton('file', LOG_DIR . DS . 'aaa_auth_'.date('z').'.log' , 'aaa_auth');
		if($this->log === false){
			throw new Exception('Failed to create object of PEAR::Log');
		}
	}
	
	public function getAuthInfo( $db ) {
		if ( isset($_SERVER['REMOTE_USER']) && !is_null($_SERVER['REMOTE_USER']) ) {
			$userid = $_SERVER['REMOTE_USER'];
		} else {
			$userid = 'admin';
		}
		
		$event_auth = array();
		
		foreach ($this->auth_xml->auth as $auth) {
			if ( $auth->userid == $userid ) {
				foreach ( $auth->event as $auth_event ) {
					foreach ( $db->getEventList( $auth_event->eventcode ) as $event ) {
						$event_auth[$event['eventcode']] = (object) array(
							'eventcode' => $event['eventcode'],
							'event_name' => $event['event_name'],
							'reg_post_start' => $event['reg_post_start'],
							'reg_post_end' => $event['reg_post_end'],
							'reg_vote_start' => $event['reg_vote_start'],
							'reg_vote_end' => $event['reg_vote_end'],
							'reg_event_start' => $event['reg_event_start'],
							'reg_event_end' => $event['reg_event_end'],
							'event_close' => $event['event_close'],
							'privilege' => (string) $auth_event->privilege
						);
					}
				}
			}
		}
	
		$this->auth_info = $event_auth;
		return $this->auth_info;
	}
	
	public function isAdmin( $eventcode ) {
		if ( isset($this->auth_info[$eventcode]) ) {
			$event_auth = $this->auth_info[$eventcode];
			
			return ($event_auth->privilege == 'admin');
		} elseif ( isset($this->auth_info) ) {
			return 'undefined';
		} else {
			return null;
		}
	}

	public function getPrivilege( $eventcode ) {
		if ( isset($this->auth_info[$eventcode]) ) {
			$event_auth = $this->auth_info[$eventcode];
			
			return $event_auth->privilege;
		} elseif ( isset($this->auth_info) ) {
			return 'undefined';
		} else {
			return null;
		}
	}

	public function isReader( $eventcode ) {
		if ( isset($this->auth_info[$eventcode]) ) {
			$event_auth = $this->auth_info[$eventcode];
			
			return ($event_auth->privilege == 'reader');
		} elseif ( isset($this->auth_info) ) {
			return 'undefined';
		} else {
			return null;
		}
	}
}


