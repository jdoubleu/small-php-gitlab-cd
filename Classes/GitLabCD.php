<?php
/**
 * Class GitLabCD
 *
 * @author Joshua Westerheide
 */
class GitLabCD {
	
	/** @var array $config Local config array. */
	private $config = array();

	/**
	 * GitLabCD constructor.
	 */
	public function __construct() {
		// Get configuration
		$this->config = json_decode(file_get_contents(dirname(__FILE__) . "/../Config/config.json"));
	}
}