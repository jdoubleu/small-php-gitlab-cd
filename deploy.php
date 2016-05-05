<?php
/**
 * Small PHP GitLab CD
 *
 * A small and simple php script to deploy artifacts generated with and by the GitLab CI (https://about.gitlab.com/gitlab-ci/).
 * Have a look at the README file for more information.
 *
 * @version 0.2
 * @license MIT
 * @link https://github.com/jdoubleu/small-php-gitlab-cd
 */

/**
 * Checks if this script is called via web server or via CLI
 */
define("MODE", (!empty($argc) && isset($argc[0]) ? "CLI" : "REQUEST"));

/**
 * Looks for a config file named after this file.
 * The config file must start with the name of this file without an extension
 * (e.g. deploy-config.php for deploy.php, test-config.php for test.php, ...).
 *
 * It is recommended to use an external config file rather than changing default
 * configuration in this file.
 */
if(file_exists($config_file = basename(__FILE__,'-php') . '-config.php')) {
	define("CONFIG_FILE", $config_file);
	require_once $config_file;
} else {
	define("CONFIG_FILE", __FILE__);
}

/**
 * Collects all settings made in config file and fills them with default
 * configs if they are not set.
 *
 * First checks if config array is defined.
 *
 * $CONFIG is an array storing all configuration made by the user or default value.
 * See config file for more information of it's items.
 */
if(!isset($USER_CONFIG) || !is_array($USER_CONFIG))
	$USER_CONFIG = array();

// Merge settings
$CONFIG = array(
	"secret_token" => "AverySecretTokenOnlyYouShouldKnow",
	"tmp_dir" => "/tmp/spgcd-artifacts",
	"gitlab_api_uri" => "https://gitlab.com/api/v3",
	"gitlab_api_token" => "",
	"project_id" => -1,
	"branches" => array("master"),
	"jobs" => array("default"),
	"target_dir" => "/tmp/spgcd-target",
	"delete_files" => false,
	"post_commands" => array(),
	"email_error" => false,
	"logging" => false,
	"logging_file" => 'logs/' . time() . '.log'
);
array_walk($CONFIG, function(&$item, $key) {
	if(isset($USER_CONFIG[$key]) && gettype($item) === gettype($USER_CONFIG[$key]))
		$item = $USER_CONFIG[$key];
});

/**
 * Log handler.
 * Either a file handler or html head
 *
 * @var boolean|string|resource
 */
$loghandle = false;
/**
 * Logging
 *
 * Manages logging into a file or direct output.
 *
 * @param string $msg Log Message
 */
function log($msg) {
	global $loghandle, $CONFIG;

	if(!$CONFIG['logging'])
		return;

	if($CONFIG['logging'] == "FILE") {
		if(!$loghandle)
			$loghandle = fopen($CONFIG['logging_file'], 'a');
		fwrite($loghandle, '[' . time() . '] ' . $msg . "\n");
	} elseif($CONFIG['logging'] == "OUTPUT") {
		if(!$loghandle && MODE == "REQUEST")
			echo $loghandle = '<!DOCUMENT html>\n' .
				'<html><head><title>small-php-gitlab-cd</title><meta charset="utf-8"/></head>' .
				'<body>';
		echo '[' . time() . '] ' . htmlspecialchars($msg) . "\n";
	}
}

/*
 * ==================== deployment ====================
 */

/*
 * Handle incoming request and log it.
 * Check if request contains a payload
 */
log("small-php-gitlab-cd started");
log("MODE is " . MODE);
if(isset($_SERVER['HTTP_REFERER']))
	log("Referer: " . $_SERVER['HTTP_REFERER']);
if(isset($_SERVER['QUERY_STRING']))
	log("QUERY STRING: " . $_SERVER['QUERY_STRING']);

// Check if secret token is need and given
if(MODE == "REQUEST") {
	if(!isset($_REQUEST['secret_token']) || $_REQUEST['secret_token'] != $CONFIG['secret_token'])
		log("Invalid secret token! Aborting") && exit(100);
}

// Reads incoming payload or argument
if(MODE == "REQUEST") {
	if(($requestPayload = file_get_contents('php://input')) == false)
		log("Unknown Request Payload") && exit(111);
	elseif(!$requestPayload = json_decode($requestPayload, true))
		log("Request Payload couldn't be analyzed. Failed with json decode error: " . json_last_error_msg()) && exit(112);
	else
		log("Got a request payload!");
} elseif(MODE == "CLI") {
	if(!$bkey = array_search('-b', $argv) || !isset($argv[$bkey+1]))
		log("A build id is not given! Use -b parameter (See help for more information).") && exit(113);
}

// Check if correct object kind in payload if request
if(MODE == "REQUEST" && $requestPayload['object_kind'] != "build")
	log("Invalid object_kind in request payload. Expected \"build\" got \"" . $requestPayload['object_kind'] . "\"!") && exit(122);

// Check build status
if(MODE == "REQUEST" && $requestPayload['build_status'] != "success")
	log("The build failed! Need a successful build!") && exit(123);

/*
 * Check if needed tools are available.
 *
 * Tools: curl, unzip, rsync
 */
$neededBinaries = array('curl', 'unzip', 'rsync');

foreach($neededBinaries as $bin) {
	$path = trim(shell_exec('which ' . $bin));
	if(!$path)
		log("Binaries for " . $bin . " not found! Aborting") && exit(200);
}

/*
 * ==================== END deployment ====================
 */

/*
 * Close logging
 *
 * Either close file handle or end html document
 *
 * Is called when the script aborts or finishes
 */
register_shutdown_function(function() {
	global $CONFIG, $loghandle;

	if(!$CONFIG['logging']) {
		if($CONFIG['logging'] == "FILE" && $loghandle)
			fclose($loghandle);
		elseif($CONFIG['logging'] == "OUTPUT" && $loghandle)
			echo '</body></html>';
	}
});
