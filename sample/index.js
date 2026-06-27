/**
 * Script for Sample
 *
 * @author Takuto Yanagida
 * @version 2026-06-27
 */

document.addEventListener('DOMContentLoaded', () => {
	const opts = {
		enableHighAccuracy: false,
		timeout           : 8000,
		maximumAge        : 2000,
	};

	const btn = document.getElementById('fetch');
	const out = document.getElementById('result');

	btn.addEventListener('click', async () => {
		try {
			const pos = await new Promise((res, rej) => {
				navigator.geolocation.getCurrentPosition(res, rej, opts);
			});
			try {
				const w = await getCurrentWeather(pos.coords.latitude, pos.coords.longitude);
				out.value = `temp: ${w.temp}, pressure: ${w.pressure}, humidity: ${w.humidity}, cloud: ${w.cloud}`;
			} catch (e) {
				throw new Error('Weather cannot be captured.');
			}
		} catch (e) {
			throw new Error('Geolocation cannot be captured.');
		}
	});
});

async function getCurrentWeather(latitude, longitude) {
	const res = await fetch(`https://takty.net/api/weather/v1/?lat=${latitude.toFixed(4)}&lon=${longitude.toFixed(4)}`, {
		mode       : 'cors',
		cache      : 'no-cache',
		credentials: 'same-origin',
		headers    : { 'Content-Type': 'application/json; charset=utf-8', },
		referrer   : 'no-referrer',
	});
	return res.json();
}
