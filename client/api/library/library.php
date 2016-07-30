<?php

/**
 * @file library/library.php
 * @author Ben Shealy
 *
 * Get albums in the music library, or update
 * the music library.
 */
require_once("../auth/auth.php");
require_once("../connect.php");

/**
 * Get a section of the album library.
 *
 * @param mysqli           MySQL connection
 * @param rotationID       rotation ID
 * @param general_genreID  general genre ID
 * @param page             page offset
 * @return array of albums
 */
function get_library($mysqli, $rotationID, $general_genreID, $page)
{
	$page_size = 200;
	$keys = array(
		"al.albumID",
		"al.album_code",
		"al.album_name",
		"al.rotationID",
		"ar.artist_name",
		"UNIX_TIMESTAMP(r.review_date) * 1000 AS review_date",
		"u.preferred_name AS reviewer"
	);

	$q = "SELECT " . implode(",", $keys) . " FROM `libalbum` AS al "
		. "INNER JOIN `libartist` AS ar ON al.artistID=ar.artistID "
		. "LEFT OUTER JOIN `libreview` AS r ON r.albumID=al.albumID "
		. "LEFT OUTER JOIN `users` AS u ON r.username=u.username "
		. "WHERE al.rotationID='$rotationID' "
		. (isset($general_genreID)
			? "AND al.general_genreID = '$general_genreID' "
			: "")
		. "ORDER BY al.albumID DESC "
		. "LIMIT "  . ($page * $page_size) . ", $page_size;";
	$result = $mysqli->query($q);

	$albums = array();
	while ( ($a = $result->fetch_assoc()) ) {
		$albums[] = $a;
	}

	return $albums;
}

/**
 * Search the library by DJ name.
 *
 * TODO: finding a username should be handled by the client.
 *
 * @param mysqli      MySQL connection
 * @param rotationID  rotation ID
 * @param name        DJ name
 * @return array of albums
 */
function search_library($mysqli, $rotationID, $name)
{
	/* get username */
	$q = "SELECT username FROM `users` WHERE preferred_name='$name';";
	$result = $mysqli->query($q);

	if ( $result->num_rows == 0 ) {
		return array();
	}

	$user = $result->fetch_assoc();
	$username = $user["username"];

	/* get albums */
	$keys = array(
		"al.albumID",
		"al.album_code",
		"al.album_name",
		"al.rotationID",
		"ar.artist_name",
		"UNIX_TIMESTAMP(r.review_date) * 1000 AS review_date",
		"u.preferred_name AS reviewer"
	);

	$q = "SELECT " . implode(",", $keys) . " FROM `libalbum` AS al "
		. "INNER JOIN `libartist` AS ar ON al.artistID=ar.artistID "
		. "LEFT OUTER JOIN `libreview` AS r ON r.albumID=al.albumID "
		. "LEFT OUTER JOIN `users` AS u ON r.username=u.username "
		. "WHERE al.rotationID = '$rotationID' "
		. "AND r.username = '$username' "
		. "ORDER BY al.albumID DESC;";
	$result = $mysqli->query($q);

	$albums = array();
	while ( ($a = $result->fetch_assoc()) ) {
		$albums[] = $a;
	}

	return $albums;
}

/**
 * Move albums through rotation.
 *
 * @param mysqli  MySQL connection
 * @param albums  array of album IDs and rotation IDs
 */
function move_rotation($mysqli, $albums)
{
	foreach ( $albums as $a ) {
		$q = "UPDATE `libalbum` SET rotationID = '$a[rotationID]' "
			. " WHERE albumID = '$a[albumID]';";
		$mysqli->query($q);
	}
}

authenticate();

if ( $_SERVER["REQUEST_METHOD"] == "GET" ) {
	$mysqli = construct_connection();

	if ( !check_reviewer($mysqli) ) {
		header("HTTP/1.1 401 Unauthorized");
		exit("Current user is not allowed to view the music library.");
	}

	$rotationID = $_GET["rotationID"];
	$general_genreID = array_key_exists("general_genreID", $_GET)
		? $_GET["general_genreID"]
		: null;
	$page = array_key_exists("page", $_GET)
		? $_GET["page"]
		: null;
	$term = array_key_exists("term", $_GET)
		? $mysqli->escape_string($_GET["term"])
		: null;

	if ( is_numeric($rotationID)
	  && (!isset($general_genreID) || is_numeric($general_genreID))
	  && is_numeric($page) ) {
		$albums = get_library($mysqli, $rotationID, $general_genreID, $page);
	}
	else if ( is_numeric($rotationID) && strlen($term) >= 3 ) {
		$albums = search_library($mysqli, $rotationID, $term);
	}
	else {
		header("HTTP/1.1 404 Not Found");
		exit("Invalid input.");
	}

	$mysqli->close();

	header("Content-Type: application/json");
	exit(json_encode($albums));
}
else if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
	$mysqli = construct_connection();

	if ( !check_music_director($mysqli) ) {
		header("HTTP/1.1 401 Unauthorized");
		exit("Current user is not allowed to update the music library.");
	}

	$albums = json_decode(file_get_contents("php://input"), true);
	$albums = escape_json($mysqli, $albums);

	// TODO: validate albums

	move_rotation($mysqli, $albums);
	$mysqli->close();

	exit;
}
?>