<?php
/**
 * Configuration file
 *
 * This file contains all options needed for deployment. This will be included in the deploy
 * script. Copy this file and rename it to `deploy-config.php`.
 *
 * @version 0.2
 */

/**
 * Declare and init the config array.
 * This array stores all settings for the deployment.
 *
 * DO NOT RENAME THIS VARIABLE!
 */
$USER_CONFIG = array();

/**
 * Secret Token Option
 *
 * A secret token acting like an authentication token for web requests.
 * If MODE is CLI this will be ignored.
 *
 * You should change this from default!
 *
 * @var string
 */
$USER_CONFIG["secret_token"] = "AverySecretTokenOnlyYouShouldKnow";

/**
 * Temporary Saving Directory
 * 
 * The full path to a temporary directory where the artifact will be downloaded to.
 * Please make sure this dir is writable by php.
 *
 * @default "/tmp/spgcd-artifacts"
 * @var string
 */
$USER_CONFIG["tmp_dir"] = "/tmp/spgcd-artifacts";

/**
 * GitLab API URI
 *
 * Full URI to the GitLab API.
 *
 * @default "https://gitlab.com/api/v3"
 * @var string
 */
$USER_CONFIG["gitlab_api_uri"] = "https://gitlab.com/api/v3";

/**
 * GitLab API Token
 *
 * A private token to authenticate api acces.
 * You can find this token in your GitLab profile settings under accounts.
 *
 * @var string
 */
$USER_CONFIG["gitlab_api_token"] = "";

/**
 * GitLab Project Id
 *
 * The id of your GitLab project. When a request is incoming its project id will be checked against this.
 * If MODE is CLI or value is -1 the checked will be skipped.
 *
 * To find out your project's id make an api call like this:
 * https://git.example.org/api/v3/projects/search/My%20Project?private_token=YOUR_PRIVATE_TOKEN
 * This will search for "My Project". Emit the id from the request you get.
 *
 * @var integer
 */
$USER_CONFIG["project_id"] = -1;

/**
 * Project Branches
 *
 * Select branches from which builds will be accepted. Incoming request payloads will be checked against this.
 * Leave this empty (empty array!) to skip this check.
 *
 * @default array("master")
 * @var array
 */
$USER_CONFIG["branches"] = array("master");

/**
 * Build Jobs
 *
 * In GitLab CI config you can define jobs. Each job will trigger the webhook so you can give a list of job (-names) here
 * which should be accepted.
 * Leave it empty (empty array!) to accept for all jobs
 *
 * Examples: "default", "main", "deploy:production"
 *
 * @var array
 */
$USER_CONFIG["jobs"] = array("default");

/**
 * Deploy Directory
 *
 * Target dir where the extracted artifacts should be deployed to.
 * For the deployment rsync is used so you can also give a remote path.
 *
 * @var string
 */
$USER_CONFIG["target_dir"] = "/tmp/spgcd-target";

/**
 * Delete Files
 *
 * Whether to delete files in deploy dir before deploying.
 * This will unrecoverably delete all files in deploy target dir.
 *
 * @default true
 * @var boolean
 */
$USER_CONFIG["delete_files"] = true;

/**
 * Post Script Commands
 *
 * List of commands which will be executed if deployment was successful.
 * So far the deployment has finished and errors executing these commands will not abort deploying.
 *
 * You can fill this list with as many commands as you need.
 * Leave it empty to execute nothing.
 *
 * @var array
 */
$USER_CONFIG["post_commands"] = array();