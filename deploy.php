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
		fwrite($loghandle, '[' . time() . '] ' . $msg);
	} elseif($CONFIG['logging'] == "OUTPUT") {
		if(!$loghandle)
			echo $loghandle = '<!DOCUMENT html>\n' .
				'<html><head><title>small-php-gitlab-cd</title><meta charset="utf-8"/></head>' .
				'<body>';
		echo '[' . time() . '] ' . htmlspecialchars($msg);
	}
}

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
