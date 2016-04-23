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

	}
}