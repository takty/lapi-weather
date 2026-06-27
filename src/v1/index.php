<?php
/**
 *
 * Weather API
 *
 * @author Takuto Yanagida
 * @version 2026-06-28
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
if ($api_key === false || $api_key === '') {
	http_response_code(500);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(null);
	return;
}

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);

if (
	$lat === false || $lat === null || $lon === false || $lon === null ||
	$lat < -90 || 90 < $lat || $lon < -180 || 180 < $lon
) {
	http_response_code(403);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(null);
	return;
}

$lat_c = (int)round($lat * 10);
$lon_c = (int)round($lon * 10);

clean_cache();
$w = read_cache($lat_c, $lon_c);
if ($w === null) {
	$w = get_weather($api_key, $lat, $lon);
	if ($w !== null) {
		write_cache($lat_c, $lon_c, $w);
	}
}

if ($w === null) {
	http_response_code(502);
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($w);


// -----------------------------------------------------------------------------


function clean_cache(): void {
	$base = __DIR__ . '/cache/';
	if (!is_dir($base)) return;

	$ps  = scandir($base);
	if ($ps === false) return;

	$now = time();

	foreach ($ps as $p) {
		if ($p[0] === '.') continue;

		$path = $base . $p;
		if (!is_file($path)) continue;

		$time = filemtime($path);
		if ($time === false) continue;

		if (30 * 60 < $now - $time) {
			unlink($path);
		}
	}
}

function read_cache(int $lat, int $lon): ?array {
	$path = __DIR__ . "/cache/$lat,$lon";
	if (!file_exists($path)) return null;

	$time = filemtime($path);
	if ($time === false) return null;
	if (30 * 60 < time() - $time) return null;

	$c = file_get_contents($path);
	if ($c === false) return null;

	return json_decode($c, true);
}

function write_cache(int $lat, int $lon, array $w): void {
	$base = __DIR__ . '/cache/';

	if (!is_dir($base)) {
		$s = mkdir($base, 0775, true);
		if ($s) {
			chmod($base, 0775);
			chown($base, OWNER);
		}
	}
	if (!file_exists($base)) return;

	$fn   = "$lat,$lon";
	$path = $base . $fn;
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

	if (
		!isset($raw['main'], $raw['wind'], $raw['clouds'], $raw['sys']) ||
		!isset(
			$raw['main']['temp'],
			$raw['main']['pressure'],
			$raw['main']['humidity'],
			$raw['wind']['speed'],
			$raw['wind']['deg'],
			$raw['clouds']['all'],
			$raw['sys']['sunrise'],
			$raw['sys']['sunset'],
			$raw['timezone']
		)
	) {
		return null;
	}
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
