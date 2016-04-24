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
		if(isset($this->config['secret_token']) && $this->config['secret_token'] == $requestData['secret_token'])
			$this->logger->log('Got correct secret token');
		else {
			$this->logger->log('Incorrect secret token! Got ' . $requestData['secret_token'] . ' expected ' . $this->config['secret_token']);
			return;
		}
		
		// Checks if the request has a payload
		if(($requestPayload = file_get_contents('php://input')) === false) {
			$this->logger->log("Couldn't read php input stream. Failed to analyze request payload.");
			return;
		} elseif(!$requestPayload = json_decode($requestPayload)) {
			$this->logger->log("Failed to analyze request payload.");
			return;
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
			return;
		}
		if(!extension_loaded('zip')) {
			$this->logger->log('Cannot continue. Needed php extension zip is not loaded. See https://secure.php.net/manual/de/book.zip.php');
			return;
		}

		// Checks if project_id is defined in request payload
		if(!isset($requestPayload['project_id'])) {
			$this->logger->log("No project id set in request payload.");
			return;
		}

		// Check if project is defined in config.json
		if(!$projectConfig = $this->getProjectConfigById($requestPayload['project_id']))
			return;

		if(!isset($projectConfig['finish']) && !is_array($projectConfig['finish']) && !isset($projectConfig['finish']['target'])) {
			$this->logger->log("A finish is not set for project " . $projectConfig['id'] . " ! Exiting.");
			return;
		}

		// Check if branches are set in request payload
		if(!isset($requestPayload['ref'])) {
			$this->logger->log("No ref set in request payload so no reference to check");
			return;
		}

		// Check and get commit sha
		if(!isset($requestPayload['checkout_sha'])) {
			$this->logger->log("No checkout commit found!");
			return;
		}

		// Check project branch config against ref
		if(!$projectRef = preg_replace('/refs\/header\//', '', $requestPayload['ref'])) {
			$this->logger->log("Error while getting refs from request payload");
			return;
		} else {
			if(!in_array($projectRef, $projectConfig['branches'])) {
				$this->logger->log("Updated branch is not in config so this request will be ignored.");
				return;
			} else {
				$projectBranch = $projectConfig['branches'];
			}
		}

		// Create an API Client
		$this->gitlabClient = new \Gitlab\Client($this->config['gitlab_api_uri']);
		$this->gitlabClient->authenticate($this->config['gitlab_api_key'], \Gitlab\Client::AUTH_URL_TOKEN);
		// Updating GitLab PHP API user agent
		$this->gitlabClient->setOption('user_agent', 'small-php-gitlab-cd using php-gitlab-api');

		// Get Builds by last commit
		if(!$builds = $this->analyzeApiResponse(
			$this->gitlabClient->api('builds')->show(array(
				'id' => $projectConfig['project_id'],
				'sha' => $requestPayload['checkout_sha'],
				'scope' => 'success'
			))
		) || empty($builds)) {
			$this->logger->log("There are no builds for the last commit.");
			return;
		}
		if(!is_array($builds)) {
			$this->logger->log("Cannot analyze api response.");
			return;
		}

		// Go through builds
		$build_ids = array();	// All builds with the correct scope will be collected
		foreach($builds as $build) {
			if(!isset($build['id'])) {
				$this->logger->log("Invalid build. Build has no id!");
				continue;
			}

			if(!isset($build['name'])) {
				$this->logger->log("Build " . $build['id'] . " has no nme!");
				continue;
			}

			if(!in_array($build['name'], $projectConfig['jobs'])) {
				$this->logger->log("Build " . $build['id'] . " has incorrect name. Skipping.");
				continue;
			}
			
			if(!isset($build['artifacts_files'])) {
				$this->logger->log("Build " . $build['id'] . " has no artifacts. Skipping.");
				continue;
			}

			array_push($build_ids, $build);
		}

		if(empty($build_ids)) {
			$this->logger->log("No fitting builds. Exiting.");
		}

		// Get files
		foreach($build_ids as $build) {
			if(!$dpath = $this->handleDownloadArtifact($projectConfig['project_id'], $build['id'], $build['created_at']))
				return;

			if((!isset($projectConfig['finish']['updateOnCache']) || !$projectConfig['finish']['updateOnCache']) && $dpath['mode'] === 'cached')
				continue;

			$output = array();
			$rsync = 'rsync -rltgoDzvO ' . $dpath['path'] . ' ' . $projectConfig['finish']['target'];
			exec($rsync . ' 2>&1', $tmp, $status);

			if(!$status) {
				$this->logger->log("Failed to run rsync command! Errors:");
				$this->logger->log("  " . trim(implode("\n", $output)));
				return;
			} else {
				$this->logger->log("Successfully moved " . $dpath['path'] . " to " . $projectConfig['finish']['target'] . " . rsync output:");
				$this->logger->log("  " . trim(implode("\n", $output)));
			}
		}
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
			return false;
		} else {
			$this->logger->log('GitLab API Request was successful. Returned with:');
			$this->logger->log('  ' . $response);
			return $response;
		}
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

	/**
	 * Handles a download of artifacts.
	 * Downloads them into cache dir and unzips them.
	 * Cache control
	 *
	 * @param int $project_id GitLab Project Id
	 * @param int $build_id Id of the Build
	 * @param string $date Creation of the artifact
	 * @return array|boolean Returns false if download failed and an array with information if it was successful or from cache
	 */
	private function handleDownloadArtifact($project_id, $build_id, $date) {
		$ts = date('U', $date);
		$path = $this->config['cache_dir'] . '/' . $ts . '_' . $project_id . '_' . $build_id;
		if(!file_exists($path) && !is_dir($path)) {
			// Cache doesn't exist!
			$resource = $this->config['gitlab_api_uri'] . '/projects/' . $project_id . '/builds' . $build_id . '/artifacts';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $resource);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"PRIVATE-TOKEN" => $this->config['gitlab_api_key']
			));

			$artifact = curl_exec($ch);
			$errors = curl_error($ch);
			curl_close($ch);

			if($errors) {
				$this->logger->log("Error downloading artifact of build " . $build_id . ". cURL error:");
				$this->logger->log("  " . $errors);
				return false;
			}

			// Creating cache dir and saving file
			if(!mkdir($path)) {
				$this->logger->log("Could not create cache dir " . $path);
				return false;
			}

			// Write downloaded data to file
			$filePath = $path . '/artifacts.zip';
			if($file = fopen($filePath, 'w')) {
				$this->logger->log("Could not create artifacts file in cache. Path: " . $filePath);
				return false;
			}
			fwrite($file, $artifact);
			fclose($file);

			// Unzip Archive
			$archive = new ZipArchive();
			$res = $archive->open($filePath);

			if(!$res) {
				$this->logger->log("Could not open artifacts archive. Exited with Zip Error:");
				$this->logger->log("  " . $res);
				return false;
			}

			$archive->extractTo($path . '/artifacts');
			$archive->close();
			
			if(!file_exists($path . '/artifacts') && !is_dir($path . '/artifacts')) {
				$this->logger->log("Could not extract artifacts archive.");
				return false;
			}

			return array(
				'mode' => 'downloaded',
				'path' => $path . '/artifacts'
			);
		} elseif(file_exists($path . '/artifacts') && is_dir($path . '/artifacts')) {
			return array(
				'mode' => 'cached',
				'path' => $path . '/artifacts'
			);
		}
	}
}