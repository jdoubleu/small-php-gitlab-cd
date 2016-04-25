<?php

/**
 * Class Logger
 * Creates log files and writes log-output into it.
 *
 * @author: Joshua Westerheide
 */
class Logger {

	/** @var Logger $instance Variable which stores an instance of this class */
	private static $instance = null;

	/** @var resource $handle A file handle  */
	private $handle = null;
	
	/** @var int $logStart stores the starttime of this log as a timestamp. */
	private $logStart = null;

	/** @var int $logErrorCounter counts failed writes to log file */
	private $logErrorCounter = 0;

	/**
	 * Returns an instance of this class.
	 * Singleton pattern.
	 *
	 * @return Logger Instance
	 */
	public static function getInstance() {
		if(self::$instance === null)
			self::$instance = new self();
		return self::$instance;
	}

	/**
	 * Logger constructor.
	 */
	private function __construct() {
		$this->logStart = $this->getTimestamp();

		// Create logfile/handle
		$this->handle = fopen(realpath(dirname(__FILE__) . "/../Logs") . '/' . $this->logStart . ".log", 'a');
	}

	/**
	 * Writes a final message to the logfile
	 */
	function __destruct() {
		// Log end
		$end = $this->getTimestamp();
		$diff = $end - $this->logStart;

		$this->log("-- finished with " . $this->logErrorCounter . " write errors in " . $diff . " seconds.");
	}

	/**
	 * Writes a message into the logfile.
	 * If writing message to file fails the error counter will be increased.
	 *
	 * @param string $message A message to write into log
	 */
	public function log($message) {
		if(!fwrite($this->handle, $this->getTimestamp() . ": " . $message . "\n"))
			$this->logErrorCounter++;
	}

	/**
	 * Returns the current timestamp
	 *
	 * @return int timestamp
	 */
	private function getTimestamp() {
		return time();
	}
}