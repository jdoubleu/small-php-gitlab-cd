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
		$this->config = json_decode(file_get_contents(dirname(__FILE__) . "/../Config/config.json"), true);

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
		} elseif(!$requestPayload = json_decode($requestPayload, true)) {
			$this->logger->log("Failed to analyze request payload.");
			return;
		}
		// Log payload
		$this->logger->log("Received request payload:");
		$this->logger->log("  " . json_encode($requestPayload));

		// Check payload for type
		if($requestPayload['object_kind'] != "build") {
			$this->logger->log("Invalid object_kind in request payload. Expected \"build\" got \"" . $requestPayload['object_kind'] . "\"!");
			return;
		}

		// Check for build status
		if($requestPayload['build_status'] != "success") {
			$this->logger->log("Build failed! Need successfull builds!");
			return;
		}

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

		// Check project branch config against ref
		if(!$projectRef = preg_replace('/refs\/header\//', '', $requestPayload['ref'])) {
			$this->logger->log("Error while getting refs from request payload");
			return;
		} else {
			if(!in_array($projectRef, $projectConfig['branches'])) {
				$this->logger->log("Updated branch is not in config so this request will be ignored.");
				return;
			}
		}

		// Get file
		if(!$dpath = $this->handleDownloadArtifact($requestPayload['project_id'], $requestPayload['build_id'], $requestPayload['build_finished_at']))
			return;

		if((!isset($projectConfig['finish']['updateOnCache']) || !$projectConfig['finish']['updateOnCache']) && $dpath['mode'] === 'cached')
			return;

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
					if(array_key_exists('project_id', $project) && $project['project_id'] == $projectId)
						return $project;
				}
				return false;
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
		$ts = strtotime("2016-04-24 18:20:01 +0200");
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