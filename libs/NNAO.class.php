<?php
/**
 * NicoNico Access Object
 */
class NNAO {
	private $log = null;
	private $last_accsess_at;
	private $interval = 0.2;
    
	public function __construct(){
		$this->last_accsess_at = $this->microstamp();

		$this->log = Log::singleton('file', LOG_DIR . DS . 'niconico_'.date('z').'.log' , 'niconico');

		if($this->log === false){
			throw new Exception('Failed to create object of PEAR::Log');
		}
		
		$this->log->info("__construct: {$this->last_accsess_at} @ ".$this->microstamp());
	}
    
	public function setInterval($interval) {
		if ($interval >= 0) {
			$this->interval = $interval;
			$this->log->info("setInterval: {$this->interval} @ ".$this->microstamp());
		}
		
		return $this->interval;
	}

	private function waitInterval() {
		while ( ( $this->microstamp() - $this->last_accsess_at ) <  $this->interval ) {
			usleep($this->interval * 500000);
			$this->log->info("waitInterval: {$this->last_accsess_at} @ ".$this->microstamp());
		}

		return $this->microstamp();
	}
	
	public function loadXML( $url ) {
		$xml = false;
		$ii = 10;

		while ( ( $xml === false ) && ( $ii > 0 ) ) {
			$ii--;
			$this->waitInterval();
			$xml = simplexml_load_file( $url );
			$this->last_accsess_at = $this->microstamp();
		}
		
		$this->log->info("loadXML: {$url} [{$ii}] @ ".$this->microstamp());
		
		return $xml;
	}

	public function getFile( $url ) {
		$file = false;
		$ii = 10;

		while ( ( $file === false ) && ( $ii > 0 ) ) {
			$ii--;
			$this->waitInterval();
			$file = file_get_contents( $url );
			$this->last_accsess_at = $this->microstamp();
		} 
		
		$this->log->info("getFile: {$url} [{$ii}] @ ".$this->microstamp());
		
		return $file;
	}

	public function microstamp() {
		list($micro, $second) = explode(" ", microtime());
		
		return $second + $micro;
	}
}


