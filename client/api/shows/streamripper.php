<?php

/**
 * @file shows/streamripper.php
 *
 * This page is used by streamripper, which runs on "john" (130.127.17.39).
 * Streamripper is configured to pull this metadata with the script 
 * streamripper_start_on_boot.sh, which requests this every 10 seconds.
 * 
 * Streamripper uses the result from this to split and name the tracks.
 * The default behavior for streamripper is to name the track as <TITLE> - <ARTIST>.mp3.
 * We use the -D flag to set the name to only the title (-D %T). However, it appears that
 * streamripper uses the default configuration (name,artist).mp3 in the incomplete directory,
 * and then renames it as specified by the argument once the track is changed (i.e. new show).
 * 
 * [dcohen @ 2015-02-05] 
 * 
 * jagarwa @ 2024-04-30 added show_name functionality
 */    
require_once("../connect.php");
require_once("functions.php");

if ( $_SERVER["REQUEST_METHOD"] == "GET" ) {
	$mysqli = construct_connection();

	$showID = get_current_show_id($mysqli);
	$scheduleID = get_current_schedule_id($mysqli);

	# If scheduleID is 0, then our automation is playing, and we should not include the metadata to make it
	# simpler for our syncer to parse. Otherwise, we pass the metadata as expected by streamripper.
	if ( $scheduleID == 0 ) {
		$title = 0;
		$artist = 0;
	} else {
		$show_name = get_current_show_name($mysqli);

		$title = $showID;
		$artist = $show_name;
	}

	exit("TITLE=$title\n"
		. "ARTIST=$artist\n"
		. ".\n");
}
?>
