<?php
/**
 * Class GitLabCD
 *
 * @author Joshua Westerheide
 */
class GitLabCD {
	
	/** @var array $config Local config array. */
	private $config = array();

	/** @var Logger $logger instance of a logger */
	private $logger = null;

	/**
	 * GitLabCD constructor.
	 */
	public function __construct() {
		// Get configuration
		$this->config = json_decode(file_get_contents(dirname(__FILE__) . "/../Config/config.json"));

		// Get a logger
		$this->logger = Logger::getInstance();
	}

	/**
	 * Entry function.
	 * Analyzes the requests and handles it.
	 */
	public function initialize() {
		// Start Log
		$this->logger->log('Handle incoming request:');
		if(isset($_SERVER['HTTP_REFERER']))
			$this->logger->log('  Referer: ' . $_SERVER['HTTP_REFERER']);
		$this->logger->log('  Query String: ' . $_SERVER['QUERY_STRING']);

		// Read and analyze data
		$requestData = $_REQUEST;

		// Check security token
		if($this->config['secret_token'] == $requestData['secret_token'])
			$this->logger->log('Got correct secret token');
		else {
			$this->logger->log('Incorrect secret token! Got ' . $requestData['secret_token'] . ' expected ' . $this->config['secret_token']);
			die();
		}

		/*
		 * Check for needed PHP extensions:
		 * 1. php-curl
		 * 2. zip
		 */
		if(!extension_loaded('curl')) {
			$this->logger->log('Cannot continue. Needed php extension curl is not loaded. See https://secure.php.net/manual/de/book.curl.php');
			die();
		}
		if(!extension_loaded('zip')) {
			$this->logger->log('Cannot continue. Needed php extension zip is not loaded. See https://secure.php.net/manual/de/book.zip.php');
			die();
		}
	}
}