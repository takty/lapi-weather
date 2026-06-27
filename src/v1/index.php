<?php
/**
 *
 * Weather API
 *
 * @author Takuto Yanagida
 * @version 2026-06-27
 *
 */

require "access-control.php";

$allowed_hosts = [
	'takty.net',
];

$expected_uas = [
	'Croqujs/',
	'Electron/',
];

const OWNER = 'takty';

$is_allowed_hosts = is_request_allowed($allowed_hosts);

if (!$is_allowed_hosts && !is_user_agent_expected($expected_uas)) {
	http_response_code(404);
	return;
}
if ($is_allowed_hosts) {
	send_cors_headers();
}


// -----------------------------------------------------------------------------


$api_key = getenv('API_KEY');

$lat = 0;
$lot = 0;

if (
	isset($_GET['lat']) && !preg_match('/[^0-9\.]/', $_GET['lat']) &&
	isset($_GET['lon']) && !preg_match('/[^0-9\.]/', $_GET['lon'])
) {
	$lat = round(floatval($_GET['lat']));
	$lon = round(floatval($_GET['lon']));
} else {
	http_response_code(403);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(['status' => 'no']);
	return;
}

clean_cache();
$w = read_cache($lat, $lon);
if ($w === null) {
	$w = get_weather($api_key, $lat, $lon);
	if ($w !== null) {
		write_cache($lat, $lon, $w);
	}
}

if ($w === null) {
	http_response_code(502);
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($w);


// -----------------------------------------------------------------------------


function clean_cache() {
	$dir = __DIR__ . '/cache/';
	if (!file_exists($dir)) return;

	$now = new DateTime('now');
	$ps  = scandir($dir);

	foreach ($ps as $p) {
		if ($p[0] === '.') continue;
		$ps = explode(',', $p);

		if (count($ps) === 3) {
			$d    = preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})/', '${1}-${2}-${3} ${4}:${5}:00', $ps[2]);
			$date = new DateTime($d);
			$diff = $date->diff($now);
			if ($diff->days === 0 && $diff->h === 0 && $diff->i < 30) continue;
		}
		unlink( $dir . $p );
	}
}

function read_cache(float $lat, float $lon): ?array {
	$dir = __DIR__ . '/cache/';
	if (!file_exists($dir)) return null;

	$ps = scandir($dir, SCANDIR_SORT_DESCENDING);
	foreach ($ps as $p) {
		if ($p[0] === '.') continue;
		$ps = explode(',', $p);

		if (count($ps) === 3 && $ps[0] == $lat && $ps[1] == $lon) {
			$c = file_get_contents($dir . $p);
			return json_decode($c, true);
		}
	}
	return null;
}

function write_cache(float $lat, float $lon, array $w) {
	$dir = __DIR__ . '/cache/';

	if (!file_exists($dir)) {
		$s = mkdir($dir, 0775, true);
		if ($s) {
			chmod($dir, 0775);
			chown($dir, OWNER);
		}
	}
	if (!file_exists($dir)) return false;

	$now  = new DateTime('now');
	$ns   = $now->format('YmdHi');
	$fn   = "$lat,$lon,$ns";
	$path = $dir . '/' . $fn;
	file_put_contents($path, json_encode($w), LOCK_EX);
	chown($path, OWNER);
}


// -----------------------------------------------------------------------------


function get_weather(string $api_key, float $lat, float $lon): ?array {
	$unit = 'metric';
	$url  = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&units=$unit&appid=$api_key";
	$cont = file_get_contents($url);
	if ($cont === false) return null;

	$raw = json_decode($cont, true);
	if ($raw === null) return null;

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
