<?php
class WpDeployInstance{
	
	public $wpDeployID;
	public $name;
	public $serverName;

	public $DB_HOST;
	public $DB_PASSWORD;
	public $DB_USER;
	public $DB_NAME;
	public $WP_SITEURL;
	public $WP_HOME;

	public $allowUploads = true;
	public $allowUpgrades = true;
	public $allowPluginInstall = true;

	/**
	 * 
	 * Class for manipulating instance data
	 * @param string $serverName
	 * @param Boolean $search if set to true, will try and find the closest servername (solves issues with www. on domains)
	 */
	function __construct($serverName="",$search=FALSE){
		if($serverName!=""){
			$this->serverName = $serverName;
				
			$importArray= WpDeployInstance::deserializeInstancesArray($this->serverName,$search);
			if(is_array($importArray)){
				foreach ($importArray as $importArrayKey => $importArrayItem) {
					$this->{$importArrayKey} = $importArrayItem;
				}
			}
		}
	}

	/**
	 * sets the variables in this instantiated object to the same as the current Wordpress settings
	 *
	 */
	function useCurrentSettings(){
		$this->DB_HOST = DB_HOST;
		$this->DB_PASSWORD = DB_PASSWORD;
		$this->DB_USER = DB_USER;
		$this->DB_NAME = DB_NAME;
		if(defined('WP_SITEURL'))$this->WP_SITEURL = WP_SITEURL;
		if(defined('WP_HOME'))$this->WP_HOME = WP_HOME;
	}

	/**
	 *
	 * Ensure that all required details are set
	 * @return boolean
	 */
	function check_details_complete(){
		if(
		$this->serverName != "" &&
		$this->DB_HOST != "" &&
		$this->DB_PASSWORD != "" &&
		$this->DB_USER != "" &&
		$this->DB_NAME != ""
		){
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Serialises the current instance to the main array
	 *
	 */
	function serializeInstance(){
		if($this->check_details_complete()){
			$instancesArray = WpDeployInstance::deserializeInstancesArray();
			$instancesArray[$this->serverName]['serverName'] = $this->serverName;
			$instancesArray[$this->serverName]['DB_HOST'] = $this->DB_HOST;
			$instancesArray[$this->serverName]['DB_PASSWORD'] = $this->DB_PASSWORD;
			$instancesArray[$this->serverName]['DB_USER'] = $this->DB_USER;
			$instancesArray[$this->serverName]['DB_NAME'] = $this->DB_NAME;
			$instancesArray[$this->serverName]['WP_SITEURL'] = $this->WP_SITEURL;
			$instancesArray[$this->serverName]['WP_HOME'] = $this->WP_HOME;
			$instancesArray[$this->serverName]['allowUpgrades'] = $this->allowUpgrades;
			$instancesArray[$this->serverName]['allowUploads'] = $this->allowUploads;
			$instancesArray[$this->serverName]['allowPluginInstall'] = $this->allowPluginInstall;

			if(!file_exists(dirname(__FILE__) . "/instancesarray.file")) touch(dirname(__FILE__) . "/instancesarray.file");
			file_put_contents(dirname(__FILE__) . "/instancesarray.file",serialize($instancesArray));
		}
	}

	/**
	 * returns the entire settings array or the individual details for the requested server.
	 * Handles aliased servers
	 *
	 * @param string $serverName
	 * @return array
	 */
	static function deserializeInstancesArray($serverName="",$search=FALSE){
		if(file_exists(dirname(__FILE__) . "/instancesarray.file")){
			$instancesArray = unserialize(file_get_contents(dirname(__FILE__) . "/instancesarray.file"));
			if($serverName!="" && isset($instancesArray[$serverName]) ){
				// found an exact match
				return $instancesArray[$serverName];
			} else if( $serverName!="" && $search == TRUE ){
				//servername hasn't been matched. try and match item to the the servername
				foreach ($instancesArray as $instance) {
					if ( strpos( $serverName,$instance['serverName']) !== FALSE){
						return $instance;
					}
				}
				//didn't find an instance so return everything.
				return $instancesArray; 
			} else {
				return $instancesArray;
			}  
			
		} else {
			return array();
		}
	}

	function deleteInstance(){
		$instancesArray = WpDeployInstance::deserializeInstancesArray();
		unset($instancesArray[$this->serverName]);
		file_put_contents(dirname(__FILE__) . "/instancesarray.file",serialize($instancesArray));
	}

	function updateInstance(){
		$this->serializeInstance();
	}
}