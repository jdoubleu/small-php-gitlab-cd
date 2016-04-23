<?php
/**
 * Index/Initiator
 * Autoloading file.
 *
 * Will load all configuration and the main class.
 */

// Load files
require_once 'Classes/Logger.php';
require_once 'Classes/GitLabCD.php';

// Create instance of the main handler
$handler = new GitLabCD();
$handler->initialize();