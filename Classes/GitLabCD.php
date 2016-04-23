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

	/** @var \Gitlab\Client $gitlabClient instance of a GitLab API Client @see https://github.com/m4tthumphrey/php-gitlab-api */
	private $gitlabClient = null;

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
		
		// Checks if the request has a payload
		if(($requestPayload = file_get_contents('php://input')) === false) {
			$this->logger->log("Couldn't read php input stream. Failed to analyze request payload.");
			die();
		} elseif(!$requestPayload = json_decode($requestPayload)) {
			$this->logger->log("Failed to analyze request payload.");
			die();
		}
		// Log payload
		$this->logger->log("Received request payload:");
		$this->logger->log("  " . print_r($requestPayload));

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

		// Etablish API
		$this->gitlabClient = new \Gitlab\Client($this->config['gitlab_api_uri']);
		$this->gitlabClient->authenticate($this->config['gitlab_api_key'], \Gitlab\Client::AUTH_URL_TOKEN);
		// Updating GitLab PHP API user agent
		$this->gitlabClient->setOption('user_agent', 'small-php-gitlab-cd using php-gitlab-api');
	}

	/**
	 * Analyzes the GitLab API Response
	 * If it's a 401 Unauthorized it aborts the script
	 * Logs response
	 *
	 * @param mixed $response Received response from the API
	 * @return mixed Analyzed response.
	 * 		false if access was unauthorized or the request failed
	 *      array response if request was successful and a simple get request
	 */
	private function analyzeApiResponse($response) {
		if($response["message"] == "401 Unauthorized") {
			$this->logger->log('Cannot continue. API Request failed with code: 401 Unauthorized. Seems like the api token is invalid.');
			die();
		} else {
			$this->logger->log('GitLab API Request was successfull. Returned with:');
			$this->logger->log('  ' . $response);
		}
		return $response;
	}

	/**
	 * Returns project configuration of project defined in config.json by its id.
	 *
	 * @param int $projectId project_id (representative the gitlab project_id)
	 * @return boolean|array false if project doesn't exist in config else array of config
	 */
	private function getProjectConfigById($projectId) {
		if(isset($this->config['projects'])) {
			if(!empty($this->config['projects'])) {
				$config = array();
				foreach($this->config['projects'] as $project) {
					if(array_key_exists('id', $project) && $project['id'] == $projectId)
						return $project;
					return false;
				}
			} else {
				$this->logger->log('No projects defined in config.json!');
				return false;
			}
		} else {
			$this->logger->log('No projects defined in config.json!');
			return false;
		}
	}
}