<?php
/**
 * Plugin Name: Logout on Close
 * Plugin URI: https://www.shiny9web.com/
 * Description: This plugin logs users off if the close the browser tab or window without logging off.  The script runs every five minutes by default.
 * Version:     1.0.0
 * Author:      Robert Gillmer
 * Author URI:  http://www.robertgillmer.com
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2, as published by the
 * Free Software Foundation.  You may NOT assume that you can use any other
 * version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.
 *
 */

// Create the option and the cron schedule on plugin activation

function rfg_loc_activation_functions() {
	add_option( 'rfg_online_users', array(), '', FALSE );
	if( ! wp_next_scheduled( 'rfg_loc_cron_job' ) ) {
		wp_schedule_event( time(), 'every_five_minutes', 'rfg_loc_cron_job' );
	}
}

register_activation_hook( __FILE__, 'rfg_loc_activation_functions' );

// Destroy the option and the cron schedule on plugin deactivation

function rfg_loc_deactivation_functions() {
	delete_option( 'rfg_online_users', array() );
	wp_clear_scheduled_hook( 'rfg_loc_cron_job' );
}

register_deactivation_hook( __FILE__, 'rfg_loc_deactivation_functions' );

// When a user logs in, add the user_id and time() to the option array.

function rfg_loc_add_userid( $user_login, $user ) {
	$user_id = $user->ID;
	$current_online_users = get_option( 'rfg_online_users' );
	
	$current_online_users[ $user_id ] = time();

	update_option( 'rfg_online_users', $current_online_users );
}

add_action( 'wp_login', 'rfg_loc_add_userid', 10, 3 );

// When a user manually logs out, remove the user_id from the option array.

function rfg_loc_remove_userid() {
	$user_id = get_current_user_id();
	$current_online_users = get_option( 'rfg_online_users' );
	
	unset( $current_online_users[ $user_id ] );

	update_option( 'rfg_online_users', $current_online_users );
}

add_action( 'wp_logout', 'rfg_loc_remove_userid' );

/*
 * Enqueue our online-user-heartbeat JS, and make sure Heartbeat is
 * running.  We don't need these scripts for visitors who are not
 * logged in, which is why we're not calling the 'nopriv' versions of
 * the action hooks.
 */

function rfg_loc_online_user_scripts() {
	wp_enqueue_script( 'heartbeat' );
	wp_enqueue_script( 'online-user-heartbeat', plugins_url( 'online-user-heartbeat.js', __FILE__ ), array( 'jquery' ), '', TRUE );
}

add_action( 'wp_enqueue_scripts', 'rfg_loc_online_user_scripts' );
add_action( 'admin_enqueue_scripts', 'rfg_loc_online_user_scripts' );

// Make sure the heartbeat is beating normal speed.

function rfg_loc_heartbeat_settings( $settings ) {
    $settings[ 'interval' ] = 15; //Anything between 15-60
    return $settings;
}

add_filter( 'heartbeat_settings', 'rfg_loc_heartbeat_settings' );

// When we receive a heartbeat tick, update the options array with a new time().

function rfg_loc_update_userid( $response, $data ) {
	// Make sure we're getting the data we expect 
	if( $data[ 'locOnline' ] == 'true' ) {
		$user_id = get_current_user_id();
		$current_online_users = get_option( 'rfg_online_users' );
		$current_online_users[ $user_id ] = time();
		update_option( 'rfg_online_users', $current_online_users );
	}

	return $response;
}

add_filter( 'heartbeat_received', 'rfg_loc_update_userid', 10, 2);

// Cron functions

// Add our function to the cron function name we specified on plugin activation
add_action( 'rfg_loc_cron_job', 'rfg_loc_look_for_expired_userids' );

/*
 * Add a schedule for every five minutes.
 *
 * @TODO: Add an options page so that an admin can update this interval.
 */


function rfg_loc_five_minute_schedule( $schedules ) {
	$schedules[ 'every_five_minutes' ] = array(
		'display' => __( 'Every Five Minutes', 'textdomain' ),
		'interval' => 300,
	);
	return $schedules;
}

add_filter( 'cron_schedules', 'rfg_loc_five_minute_schedule' );

/*
 * The cron job which logs out users who closed the tab.  Those user id's
 * will still be in the rfg_online_users array because the logout
 * function didn't fire.  So look through $key => $value, find any $value
 * that is from greater than two minutes ago - i.e., time() - (2 * 60) - 
 * and log out the $key for those 'expired' $values.
 *
 * Note: Using a two minute threshold combined with the cron running
 * every five minutes means that a user could stay logged in for up to
 * six minutes, 59 seconds after closing the tab. If this is an
 * unacceptable threshold, run the cron more often, or reduce the two-
 * minute threshold.
 *
 * @TODO: Add an options page for the two minute threshold.
 */

function rfg_loc_look_for_expired_userids() {
	$current_online_users = get_option( 'rfg_online_users' );

	// There's no reason this should not be an array, but just in case...
	if( is_array( $current_online_users ) ) {
		$i = 0;
		foreach( $current_online_users as $key => $last_heartbeat_tick ) {

			/*
			 * If the tick came through more than five minutes ago, this
			 * user is either idle (so the heartbeat isn't firing) or has
			 * closed the tab.  Either way, remove the id from the array
			 * and run some logoff scripts.
			 */

			if( $last_heartbeat_tick < time() - ( 2 * 60 ) ) {

				// Get all sessions for user with ID $key
				$sessions = WP_Session_Tokens::get_instance( $key );

				// We have got all the sessions, so destroy them all!
				$sessions->destroy_all();

				// And remove the user from the array.
				unset( $current_online_users[ $key ] );
				update_option( 'rfg_online_users', $current_online_users );
			}
		}
	}
}