<?php

/**
 * Plugin Name: ML Slack -- A Slackbot that Emojifies
 * Description: An example of using the WordPress REST API as an API for a Slackbot that uploads emoji to Slack.
 * Author: Maura Teal
 * Author URI: http://mlteal.com
 * Version: 0.1
 * Plugin URI:
 * License: GPL2+
 *
 * GitHub Plugin URI:
 * GitHub Branch: master
 *
 * It should be noted that this doesn't currently work with Slack teams
 * that have 2 factor authentication enabled.
 *
 * I also think it will fail if you have a service that immediately uploads
 * wp images to a service like Amazon S3, because we need ultimately to send
 * a filepath, not URL, to Slack. We do this by uploading and saving the image
 * to our WP site.
 *
 * References: https://github.com/abrudtkuhl/wp-slack-slash-command by Andy Brudtkuhl
 *             https://brudtkuhl.com/building-slack-bot-nodejs-wordpress-rest-api/
 */

require_once( 'class-ml-slack.php' );
require_once( 'class-ml-slack-admin.php' );

add_action( 'rest_api_init', array( 'ML_Slack', 'register_rest_route' ) );
if ( is_admin() ) {
	$ml_slack_admin = new ML_Slack_Admin();
}
