<?php
/**
 *
 * Weather API
 *
 * @author Takuto Yanagida
 * @version 2020-04-28
 *
 */


$lat = 0;
$lot = 0;

if (
	isset( $_GET['lat'] ) && ! preg_match( '/[^0-9]/', $_GET['lat'] ) &&
	isset( $_GET['lon'] ) && ! preg_match( '/[^0-9]/', $_GET['lon'] )
) {
	$lat = round( floatval( $_GET['lat'] ) );
	$lon = round( floatval( $_GET['lon'] ) );
} else {
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo json_encode( [ 'status' => 'no' ] );
	return;
}

clean_cache();
$w = read_cache( $lat, $lon );
if ( $w === null ) {
	$w = get_weather( $lat, $lon );
	if ( $w ) write_cache( $lat, $lon, $w );
}
if ( ! $w ) $w = [ 'status' => 'no' ];

header( 'Content-Type: text/html; charset=UTF-8' );
echo json_encode( $w );


// -----------------------------------------------------------------------------


function clean_cache() {
	$dir = __DIR__ . '/cache/';
	if ( ! file_exists( $dir ) ) return;

	$now = new DateTime( 'now' );
	$ps = scandir( $dir );
	foreach ( $ps as $p ) {
		if ( $p[0] === '.' ) continue;
		$ps = explode( ',', $p );
		if ( count( $ps ) === 3 ) {
			$d = preg_replace( '/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})/', '${1}-${2}-${3} ${4}:${5}:00', $ps[2] );
			$date = new DateTime( $d );
			$diff = $date->diff( $now );
			if ( $diff->days === 0 && $diff->h === 0 && $diff->i < 30 ) continue;
		}
		unlink( $dir . $p );
	}
}

function read_cache( $lat, $lon ) {
	$dir = __DIR__ . '/cache/';
	if ( ! file_exists( $dir ) ) return null;

	$ps = scandir( $dir, SCANDIR_SORT_DESCENDING );
	foreach ( $ps as $p ) {
		if ( $p[0] === '.' ) continue;
		$ps = explode( ',', $p );
		if ( count( $ps ) === 3 && $ps[0] == $lat && $ps[1] == $lon ) {
			$c = file_get_contents( $dir . $p );
			return json_decode( $c );
		}
	}
	return null;
}

function write_cache( $lat, $lon, $w ) {
	$dir = __DIR__ . '/cache/';
	if ( ! file_exists( $dir ) ) {
		$s = mkdir( $dir, 0775, true );
		if ( $s ) {
			chmod( $dir, 0775 );
			chown( $dir, 'laccolla' );
		}
	}
	if ( ! file_exists( $dir ) ) return false;

	$now = new DateTime( 'now' );
	$ns = $now->format( 'YmdHi' );
	$fn = "$lat,$lon,$ns";
	$path = $dir . '/' . $fn;
	file_put_contents( $path, json_encode( $w ), LOCK_EX );
	chown( $path, 'laccolla' );
}


// -----------------------------------------------------------------------------


function get_weather( $lat, $lon ) {
	$unit = 'metric';
	$key  = '20085c5c99ae270b88e30de6720c97b7';
	$url  = "http://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&units=$unit&appid=$key";

	$cont = file_get_contents( $url );
	if ( $cont === false ) return null;
	$raw = json_decode( $cont, true );
	if ( $raw === null ) return null;

	$res = [];
	$res['temp']       = $raw['main']['temp'];      // [C deg]
	$res['pressure']   = $raw['main']['pressure'];  // [hPa]
	$res['humidity']   = $raw['main']['humidity'];  // [%]
	$res['wind_speed'] = $raw['wind']['speed'];     // [m/sec]
	$res['wind_deg']   = $raw['wind']['deg'];       // [deg (meteorological)]
	$res['cloud']      = $raw['clouds']['all'];     // [%]
	$res['sunrise']    = $raw['sys']['sunrise'];    // [unix, UTC]
	$res['sunset']     = $raw['sys']['sunset'];     // [unix, UTC]
	$res['timezone']   = $raw['timezone'];          // [sec (shift from UTC)]
	return $res;
}
