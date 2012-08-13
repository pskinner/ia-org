<?php
/*
 Plugin Name: wpDeploy
 Plugin URI: http://www.conditionalcomment.co.uk/wpdeploy
 Description: wpDeploy allows you to work with wordpress seamlessly in a multiple developer/multiple server release enviroment.
 Version: 0.6
 Author: nedsbeds
 Author URI: http://www.conditionalcomment.co.uk/plugins

 Copyright 2010 Nick Downton  (email : nick@conditionalcomment.co.uk)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License, version 2, as
 published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once 'wpdeploy.class.php';

define('EDIT_WPDEPLOY',1);
define('VIEW_WPDEPLOY',2);
define('DELETE_WPDEPLOY',3);
define('DUPLICATE_WPDEPLOY',4);

add_action('init', 'update_user_capabilities');
add_action('admin_menu', 'wpDeploy_menu');


register_deactivation_hook(__FILE__, 'wpdeploy_uninstall');
register_activation_hook(__FILE__,'wpdeploy_install');
add_filter('upgrader_pre_install', 'wpdeploy_backup', 10, 2);
add_filter('upgrader_post_install', 'wpdeploy_recover', 10, 2);




function wpDeploy_menu(){add_options_page('wpDeploy Settings', 'wpDeploy', 'manage_options', 'wpDeploySettings', 'wpDeploy_settingsPage');}


function wpDeploy_settingsPage() {

	if( isset( $_GET['action']) ){
		if( $_GET['action'] == "invert_status" ){
			if( !defined('WPDEPLOY_ACTIVATED') ){
				wpdeploy_activate();
				show_wpdeploy_default_page(array("updated fade","Plugin Activated"));
			} else if( defined('WPDEPLOY_ACTIVATED') ){
				wpdeploy_deactivate();
				show_wpdeploy_default_page(array("updated fade","Plugin Deactivated"));
			}
		} else if ( $_GET['action'] == "new" || $_GET['action'] == "edit" || $_GET['action'] == "duplicate" ){
			//Logic for adding new instances
			if ( isset( $_POST['add']) || isset( $_POST['edit']) ){
				$nonce = $_POST['wpdeploy-nonce'];
				if (!wp_verify_nonce($nonce, 'wpdeploy-nonce')) die ( 'Security Check - If you receive this in error, log out and back in to WordPress');

				$wpdeploy_instance = new WpDeployInstance(esc_html($_POST['serverName']));
				$wpdeploy_instance->DB_HOST = esc_html($_POST['DB_HOST']);
				$wpdeploy_instance->DB_NAME = esc_html($_POST['DB_NAME']);
				$wpdeploy_instance->DB_PASSWORD = esc_html($_POST['DB_PASSWORD']);
				$wpdeploy_instance->DB_USER = esc_html($_POST['DB_USER']);
				$wpdeploy_instance->WP_HOME = esc_html($_POST['WP_HOME']);
				$wpdeploy_instance->WP_SITEURL = esc_html($_POST['WP_SITEURL']);

				if( isset($_POST['allowUpgrades']) && $_POST['allowUpgrades']=="true"){$wpdeploy_instance->allowUpgrades = true;} else {$wpdeploy_instance->allowUpgrades = false;}
				if( isset($_POST['allowPluginInstall']) && $_POST['allowPluginInstall']=="true"){$wpdeploy_instance->allowPluginInstall = true;} else {$wpdeploy_instance->allowPluginInstall = false;}
				if( isset($_POST['allowUploads']) && $_POST['allowUploads']=="true"){$wpdeploy_instance->allowUploads = true;} else {$wpdeploy_instance->allowUploads = false;}

				if($wpdeploy_instance->check_details_complete()){
					//details are ok so save to the filesystem and return main page
					$wpdeploy_instance->serializeInstance();
					show_wpdeploy_default_page();
				} else {
					//details are incomplete so show edit page again
					show_wpdeploy_edit_page("",array("error","Please ensure all mandatory fields are completed"));
				}
			} else {
				if ( $_GET['action'] == "edit" ){
					show_wpdeploy_edit_page($_GET['serverName']);
				} else if ( $_GET['action'] == "duplicate" ){
					show_wpdeploy_edit_page($_GET['serverName'],"",TRUE);
				} else {
					show_wpdeploy_edit_page();
				}

			}


		} else if ( $_GET['action'] == "delete" ){
			$nonce = $_GET['wpdeploy-delete-nonce'];
			if (!wp_verify_nonce($nonce, 'wpdeploy-delete-nonce-'.$_GET['serverName'])) die ( 'Security Check - If you receive this in error, log out and back in to WordPress');

			if( $_SERVER['SERVER_NAME'] == $_GET['serverName']){
				show_wpdeploy_default_page(array("error","You cannot delete the definition for this server!"));
			} else {
				$wpdeploy_instance = new WpDeployInstance($_GET['serverName']);
				$wpdeploy_instance->deleteInstance();

				show_wpdeploy_default_page(array("updated fade","Instance Deleted"));
			}
		}

	} else {
		show_wpdeploy_default_page();
	}

	update_user_capabilities();

}

/**
 *
 * Creates the definition editor. If the servername is passed, then we are editing an already defined instance, although we are creating a new duplicate of serverName is duplicateServer is true ...
 * @param string $serverName
 * @param string $updateMessage Allows an error message to be passed to the page
 * @param boolean $duplicateServerName set to true if creating a duplicate of $serverName
 */
function show_wpdeploy_edit_page($serverName="",$updateMessage="",$duplicateServer=FALSE){


	if($serverName!=""){
		//we already know a server name so we must be editing an existing instance. Lock the field
		$wpdeploy_instance = new WpDeployInstance($serverName);
		//lock the servername if we are editing a created instance
		if (!$duplicateServer){
			$servername_locked = TRUE;
		} else {
			$servername_locked = FALSE;
		}
	} else {
		//there is no servername passed so we are creating one. This may be an error handling pass though so check to see if there is anything in the http request.
		if( isset($_POST['serverName'])){
			$wpdeploy_instance = new WpDeployInstance(esc_attr($_POST['serverName']));
		} else {
			$wpdeploy_instance = new WpDeployInstance();
		}
		$servername_locked = FALSE;
		if( isset($_POST['serverName'])){$wpdeploy_instance->serverName = esc_attr($_POST['serverName']);}
	}
	//repopulate updated variables in to the instance object if a user error occured and the instance wasn't updated.
	if( isset($_POST['DB_HOST']) && $_POST['DB_HOST'] != "" ){$wpdeploy_instance->DB_HOST = esc_attr($_POST['DB_HOST']);}
	if( isset($_POST['DB_USER']) && $_POST['DB_USER'] != "" ){$wpdeploy_instance->DB_USER = esc_attr($_POST['DB_USER']);}
	if( isset($_POST['DB_PASSWORD']) && $_POST['DB_PASSWORD'] != "" ){$wpdeploy_instance->DB_PASSWORD = esc_attr($_POST['DB_PASSWORD']);}
	if( isset($_POST['DB_NAME']) && $_POST['DB_NAME'] != "" ){$wpdeploy_instance->DB_NAME = esc_attr($_POST['DB_NAME']);}
	if( isset($_POST['WP_SITEURL']) && $_POST['WP_SITEURL'] != "" ){$wpdeploy_instance->WP_SITEURL = esc_attr($_POST['WP_SITEURL']);}
	if( isset($_POST['WP_HOME']) && $_POST['WP_HOME'] != "" ){$wpdeploy_instance->WP_HOME = esc_attr($_POST['WP_HOME']);}
	if( isset($_POST['allowUpgrades']) && $_POST['allowUpgrades'] == "true" ){$wpdeploy_instance->allowUpgrades = true;}
	if( isset($_POST['allowUploads']) && $_POST['allowUploads'] != "" ){$wpdeploy_instance->allowUploads = esc_attr($_POST['allowUploads']);}
	if( isset($_POST['allowPluginInstall']) && $_POST['allowPluginInstall'] != "" ){$wpdeploy_instance->allowPluginInstall = esc_attr($_POST['allowPluginInstall']);}
	?>
<div class='wrap'>
<div id="icon-edit" class="icon32"></div>
	<?php if(isset($wpdeploy_instance->serverName) && !$duplicateServer){?>
<h2>Edit Instance</h2>
	<?php
	} else {?>
<h2>Create New Instance</h2>
	<?php }
	if( is_array($updateMessage)){
		echo '<div id="message" class="'.$updateMessage[0].'"><p>'.$updateMessage[1].'</p></div>';
	}
	?>

<form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>"><input
	type="hidden"
	name="<?php if($servername_locked){ echo "edit"; } else { echo "add"; } ?>"
	value="1" /> <input type="hidden" name="wpdeploy-nonce"
	value="<?php echo wp_create_nonce('wpdeploy-nonce'); ?>" />

<table class="form-table">
	<tr>
		<th><label for="serverName">Server Name *</label></th>
		<td><input name="serverName" id="serverName" type="text"
		<?php echo ( $servername_locked )? "readonly": ""; ?>
			value="<?php if (!$duplicateServer){ echo $wpdeploy_instance->serverName;}?>"
			class="regular-text code" /></td>
	</tr>
</table>
<p>Tip: To save creating multiple records for similar domains, only
specify your parent domain (e.g. google.com) rather than the fully
qualified domain name (www.google.com). <br />
wpDeploy will then work with both the www and non www version of your
domains.</p>
<h3>Database Settings</h3>
<table class="form-table">
	<tr>
		<th><label for="DB_HOST">DB_HOST *</label></th>
		<td><input name="DB_HOST" id="DB_HOST" type="text"
			value="<?php echo $wpdeploy_instance->DB_HOST;?>"
			class="regular-text code" /></td>
	</tr>
	<tr>
		<th><label for="DB_USER">DB_USER *</label></th>
		<td><input name="DB_USER" id="DB_USER" type="text"
			value="<?php echo $wpdeploy_instance->DB_USER;?>"
			class="regular-text code" /></td>
	</tr>
	<tr>
		<th><label for="DB_PASSWORD">DB_PASSWORD *</label></th>
		<td><input name="DB_PASSWORD" id="DB_PASSWORD" type="text"
			value="<?php echo $wpdeploy_instance->DB_PASSWORD;?>"
			class="regular-text code" /></td>
	</tr>
	<tr>
		<th><label for="DB_NAME">DB_NAME *</label></th>
		<td><input name="DB_NAME" id="DB_NAME" type="text"
			value="<?php echo $wpdeploy_instance->DB_NAME;?>"
			class="regular-text code" /></td>
	</tr>

</table>
<h3>URL Settings</h3>
<table class="form-table">


	<tr>
		<th><label for="WP_SITEURL">WP_SITEURL</label></th>
		<td><input name="WP_SITEURL" id="WP_SITEURL" type="text"
			value="<?php echo $wpdeploy_instance->WP_SITEURL;?>"
			class="regular-text code" /></td>
	</tr>
	<tr>
		<th><label for="WP_HOME">WP_HOME</label></th>
		<td><input name="WP_HOME" id="WP_HOME" type="text"
			value="<?php echo $wpdeploy_instance->WP_HOME;?>"
			class="regular-text code" /></td>
	</tr>
</table>
<h3>File System Settings</h3>
<table class="form-table">


	<tr>
		<th><label for="allowUpgrades">Allow Wordpress updates to be performed</label></th>
		<td><input name="allowUpgrades" id="allowUpgrades" type="checkbox"
			value="true" <?php if($wpdeploy_instance->allowUpgrades){echo "checked='checked' ";}?>"/></td>
	</tr>
	<tr>
		<th><label for="allowUploads">Allow users to upload</label></th>
		<td><input name="allowUploads" id="allowUploads" type="checkbox"
			value="true" <?php if($wpdeploy_instance->allowUploads){echo "checked='checked' ";}?>"/></td>
	</tr>
	<tr>
		<th><label for="allowPluginInstall">Allow Plugins to be installed</label></th>
		<td><input name="allowPluginInstall" id="allowPluginInstall"
			type="checkbox" value="true" <?php if($wpdeploy_instance->allowPluginInstall){echo "checked='checked' ";}?>"/></td>
	</tr>


</table>
<br />
<input class='button-primary' type='submit'
	value='<?php _e('Save Details'); ?>' id='submitbutton' /> <a
	class="button-secondary"
	href="options-general.php?page=wpDeploySettings" title="Cancel">Cancel</a>
</form>
</div>
		<?php
}

function show_wpdeploy_default_page($updateMessage=""){

	$instancesArray = WpDeployInstance::deserializeInstancesArray();
	?>
<div class='wrap'>

<div id="icon-plugins" class="icon32"></div>
<h2>wpDeploy Plugin Status</h2>
	<?php
	if( is_array($updateMessage)){
		echo '<div id="message" class="'.$updateMessage[0].'"><p>'.$updateMessage[1].'</p></div>';
	}
	?>
<p>Once you have your definitions defined, you may turn the plugin on
and off</p>

	<?php if ( defined('WPDEPLOY_ACTIVATED') && !defined('WPDEPLOY_ACTIVATE_STATUS_CHANGED') || ( !defined('WPDEPLOY_ACTIVATED') && defined('WPDEPLOY_ACTIVATE_STATUS_CHANGED') ) ){ ?>
<ul class="subsubsub">
	<li>Plugin Currently <span style="color: green;">Enabled</span></li>
</ul>

<br clear="all" />
<form action="options-general.php" method="get"><input type="hidden"
	name="action" value="invert_status"> <input type="hidden" name="page"
	value="wpDeploySettings"> <input class='button-primary' type='submit'
	value='<?php _e('Disable Plugin Operation'); ?>' id='submitbutton' /></form>

<br clear="all" />

	<?php } else { ?>
<ul class="subsubsub">
	<li>Plugin Currently <span style="color: red;">Disabled</span></li>
</ul>

<br clear="all" />
<form action="options-general.php" method="get"><input type="hidden"
	name="action" value="invert_status"> <input type="hidden" name="page"
	value="wpDeploySettings"> <input class='button-primary' type='submit'
	value='<?php _e('Enable Plugin Operation'); ?>' id='submitbutton' /></form>

<br clear="all" />

	<?php } ?>



<div id="icon-options-general" class="icon32"></div>
<h2>Defined Site Installs <a class="button add-new-h2"
	href="options-general.php?page=wpDeploySettings&action=new">Add New</a></h2>
<ul class="subsubsub">
	<li>Currently active instance: <?php echo $_SERVER['SERVER_NAME'];?></li>
</ul>
<table class="widefat">
	<thead>
		<tr>
			<th>Server Name</th>
			<th>Can Upload</th>
			<th>Can Upgrade</th>
			<th>Can Install</th>
			<th>Actions</th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th>Server Name</th>
			<th>Can Upload</th>
			<th>Can Upgrade</th>
			<th>Can Install</th>
			<th>Actions</th>
		</tr>
	</tfoot>
	<tbody>
	<?php
	foreach ($instancesArray as $instance) {
		$wpDeployInstance = new WpDeployInstance($instance['serverName']);
		?>
		<tr>
			<td><?php echo $wpDeployInstance->serverName ?></td>
			<td><?php echo ($wpDeployInstance->allowUploads)? "YES" : "NO"; ?></td>
			<td><?php echo ($wpDeployInstance->allowUpgrades)? "YES" : "NO"; ?></td>
			<td><?php echo ($wpDeployInstance->allowPluginInstall)? "YES" : "NO"; ?></td>
			<td><span class="edit"><a title="Edit this instance"
				href="options-general.php?page=wpDeploySettings&action=edit&serverName=<?php echo $wpDeployInstance->serverName?>">Edit</A>
			</span> | <span class="duplicate"><a title="Duplicate this instance"
				href="options-general.php?page=wpDeploySettings&action=duplicate&serverName=<?php echo $wpDeployInstance->serverName?>">Duplicate</A>
			</span> | <span class="delete"><a class="submitdelete"
				title="Delete this instance"
				href="options-general.php?page=wpDeploySettings&action=delete&serverName=<?php echo $wpDeployInstance->serverName . '&wpdeploy-delete-nonce=' . wp_create_nonce('wpdeploy-delete-nonce-'.$wpDeployInstance->serverName);?>"
				onclick="if ( confirm('CAUTION You are about to delete this configuration.\nThis could leave your installation unoperable.\n\'Cancel\' to stop, \'OK\' to delete.') ) { return true;}return false;">Delete</a></span>

			</td>
		</tr>
		<?php
	}
	?>
	</tbody>
</table>


</div>
	<?php
}


/**
 * Adds code to wp-config to allow for dynamic config values
 *
 * @return string
 */
function wpDeploy_install() {
	$configFile = ABSPATH . 'wp-config.php';

	$defaultConfigSettings = new WpDeployInstance($_SERVER['SERVER_NAME']);
	$defaultConfigSettings->useCurrentSettings();
	$defaultConfigSettings->serializeInstance();

	copy(dirname(__FILE__) . '/wpdeploy-settings.php.file', ABSPATH . 'wpdeploy-settings.php');
	copy($configFile, ABSPATH . 'wpconfig-BAK.php');
	wpdeploy_activate();
}

/**
 * updates wordpress config file to use WpDeploy
 * @return void
 */
function wpdeploy_activate(){
	$configSettings = new WpDeployInstance($_SERVER['SERVER_NAME']);
	if($configSettings->check_details_complete()){
		$configFile = ABSPATH . 'wp-config.php';
		$file = fopen($configFile, "r") or error_log("Unable to open file!");
		//Output a line of the file until the end is reached
		$newFileContent = "";
		while(!feof($file))
		{
			$line = fgets($file);
			if (
			strpos($line,"define('DB_NAME',")!==false ||
			strpos($line,"define('DB_USER',")!==false ||
			strpos($line,"define('DB_PASSWORD',")!==false ||
			strpos($line,"define('DB_HOST',")!==false ||
			strpos($line,"define('WP_SITEURL',")!==false ||
			strpos($line,"define('WP_HOME',")!==false
			){$line = "//".$line;}

			if (strpos($line,"wp-settings.php")!==false){$line = "if(file_exists(ABSPATH . 'wpdeploy-settings.php')){require_once(ABSPATH . 'wpdeploy-settings.php');}\r\n".$line;}

			$newFileContent .= $line;
		}
		fclose($file);

		if (is_writable($configFile)) {

			if (!$handle = fopen($configFile, 'w')) {return;}
			if (fwrite($handle, $newFileContent) === FALSE) {return;}
			fclose($handle);
			define('WPDEPLOY_ACTIVATE_STATUS_CHANGED',TRUE);

		} else {
			exit;
		}
	}
}

/**
 * updates wordpress config file to use default config values
 * @return void
 */
function wpdeploy_deactivate(){
	$configFile = ABSPATH . 'wp-config.php';

	$file = fopen($configFile, "r") or exit("Unable to open file!");
	//Output a line of the file until the end is reached
	$newFileContent = "";
	while(!feof($file))
	{
		$line = fgets($file);

		if (
		strpos($line,"define('DB_NAME',")!==false ||
		strpos($line,"define('DB_USER',")!==false ||
		strpos($line,"define('DB_PASSWORD',")!==false ||
		strpos($line,"define('DB_HOST',")!==false ||
		strpos($line,"define('WP_SITEURL',")!==false ||
		strpos($line,"define('WP_HOME',")!==false
		){ $line = str_replace("//","",$line);}

		if (strpos($line,"wpdeploy-settings.php")!==false){	$line = "";	}

		$newFileContent .= $line;
	}
	fclose($file);

	if (is_writable($configFile)) {

		if (!$handle = fopen($configFile, 'w')) {return;}
		if (fwrite($handle, $newFileContent) === FALSE) {return;}
		fclose($handle);
		define('WPDEPLOY_ACTIVATE_STATUS_CHANGED',TRUE);
	} else {
		return;
	}
}


function wpdeploy_backup()
{
    $to = dirname(__FILE__)."/../instancesarray.file";
    $from = dirname(__FILE__)."/instancesarray.file";
    copy( $from , $to);
}
function wpdeploy_recover()
{
    $from = dirname(__FILE__)."/../instancesarray.file";
    $to = dirname(__FILE__)."/instancesarray.file";
    copy($from, $to);
    unlink($from);
}



/**
 * Removes wpdploy file changes from the system and goes back to default wp-config settings
 *
 * @return string
 */
function wpdeploy_uninstall() {
	wpdeploy_deactivate();

	if (file_exists(ABSPATH . 'wpdeploy-settings.php')) unlink(ABSPATH . 'wpdeploy-settings.php');
	if (file_exists(ABSPATH . 'wpconfig-BAK.php')) unlink(ABSPATH . 'wpconfig-BAK.php');
}

function update_user_capabilities(){
	$current_user = wp_get_current_user();
	$roles = new WP_Roles();
	$role = $roles->get_role($current_user->roles[0]);

	if( defined('WPDEPLOY_ACTIVATED') ){
		global $wpDeployInstance;
		if($role!=""){
			$current_user->add_cap('upload_files',$role->has_cap('upload_files') && $wpDeployInstance->allowUploads);
			$current_user->add_cap('update_core',$role->has_cap('update_core') && $wpDeployInstance->allowUpgrades);
			$current_user->add_cap('install_plugins',$role->has_cap('install_plugins') && $wpDeployInstance->allowPluginInstall);
			$current_user->add_cap('update_plugins',$role->has_cap('update_plugins') && $wpDeployInstance->allowPluginInstall);
			$current_user->get_role_caps();
		}
	} else {

		$current_user->add_cap('upload_files',$role->has_cap('upload_files'));
		$current_user->add_cap('update_core',$role->has_cap('update_core'));
		$current_user->add_cap('install_plugins',$role->has_cap('install_plugins'));
		$current_user->add_cap('update_plugins',$role->has_cap('update_plugins'));
		$current_user->get_role_caps();
	}
}