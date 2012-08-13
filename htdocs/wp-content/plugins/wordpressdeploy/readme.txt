=== WP-Deploy ===
Contributors: nedsbeds
Tags: config, deployment, migration, subversion, git, svn, development teams
Requires at least: 2.0.2
Tested up to: 3.1.1
Stable tag: 0.6

WP Deploy is designed to solve some of the difficulties of developers working in teams using version control software and multi-server enviroments

== Description ==

WP Deploy is a plugin designed to solve some of the difficulties experienced by developers working in teams using version control software and multiple stages of deployment (local, development, staging, production etc.)

More specifically it allows multiple config files to be added through the admin system and then dynamically switched depending on the URL a user is accessing the site from.

In this way, a wordpress installation can be added to an svn repository (or any version control system) and checked out in multiple places that might have needs for differing config files.

== Installation ==

1. Upload the folder `wp-deploy` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate to settings -> `wp deploy settings` and start adding your server instances. Your current config will automatically be loaded in.

== Frequently Asked Questions ==

= I have enabled WpDeploy and can no longer access my site =

WpDeploy manually manipulates your wp-config.php file. If you enable WpDeploy and find you can no longer access your wordpress site, load the wp-config file and uncomment the following lines
`//define('DB_NAME',
//define('DB_USER',
//define('DB_PASSWORD',
//define('DB_HOST',`

by removing the forward slashes

`define('DB_NAME',
define('DB_USER',
define('DB_PASSWORD',
define('DB_HOST',`


You will then need to remove the following line

`if(file_exists(ABSPATH . 'wpdeploy-settings.php')){require_once(ABSPATH . 'wpdeploy-settings.php');}`

= I can no longer upload files to the media library =

If you can no longer see the media library tab, your WpDeploy config has probably blocked access. Edit the config and ensure that the correct permissions are checked.

= I have ticked "Allow users to upload" but I still cannot upload media files =

WpDeploy will only remove privileges. It will not add them to users who couldn't already perform operations. Ensure the user's role is high enough.

== Changelog ==
= 0.6 =
htaccess file added to deny access to config file. 

= 0.5 =
Config files saved when upgraded

= 0.4 =
Fixed bug when logged out

= 0.3 =
Install Directory Corrected

= 0.2 =
Readme file added

= 0.1 =
This is the first version of wp-deploy available.

== Upgrade Notice ==

= 0.1 =
Users of the beta version should upgrade to this fully functional version.

== Advanced Options and Use Cases ==

WPDeploy also allows you to limit certain activities for particular instances.
These activities are as follows:

* Allow a user to use the filemanager to upload content
* Allow a user to perform plugin installations
* Allow a user to run the automatic Wordpress upgrade

Below is an example of when one might use these capabilities.
A team of developers are working on a wordpress site together and all develop code locally, before committing to an SVN repository.
Once commited, any unit test scripts are run and then the latest version is checked out automatically to a client viewable server.
This client viewable server is where clients can produce content and will also be using the filemanager to upload images. 
Since this copy of the site is automatically created, there is no developer responsible for checking this back in to the repository, any changes made to the codebase by upgrading wordpress or installing plugins won't make it back in to the repository.
It is also desirable to keep any user generated content out of the repository 
With WPDeploy, we would forbid content uploads to all machines except the version clients are creating content on, and not allow code changes on any servers that won't get checked back in to the repository.