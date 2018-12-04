<?php
	
	require_once('params.php');

	$data_url = 'https://overpass-api.de/api/interpreter?data=%5Bout%3Ajson%5D%5Btimeout%3A180%5D%3B%0A%2F%2F%20gather%20results%0A%28%0A%20%20%2F%2F%20query%20part%20for%3A%20%E2%80%9Cemergency%3Ddefibrillator%E2%80%9D%0A%20%20node%5B%22emergency%22%3D%22defibrillator%22%5D%2848.7%2C2%2C51%2C4.5%29%3B%0A%20%20way%5B%22emergency%22%3D%22defibrillator%22%5D%2848.7%2C2%2C51%2C4.5%29%3B%0A%20%20relation%5B%22emergency%22%3D%22defibrillator%22%5D%2848.7%2C2%2C51%2C4.5%29%3B%0A%29%3B%0A%2F%2F%20print%20results%0Aout%20body%3B%0A%3E%3B%0Aout%20skel%20qt%3B';
	// whole europe would be (35,-13,71,29)

	$bb = [50.76, 50.92, 4.23, 4.50]; 	// Brussels only

	$ret_raw		= file_get_contents($data_url);
	$ret_array 		= json_decode($ret_raw, true);

	echo $ret_raw; 

	$ret_array['areas'] 	= [];
	$data   		= $ret_array['elements'];

	foreach ($data as $val)
	{
		if ( ( $bb[0]  <= $val['lat'] ) & ( $val['lat'] <= $bb[1]) & ( $bb[2] <= $val['lon'] ) & ( $val['lon'] <= $bb[3]) )		// sub area (as isoline computing is costly)
		{
			$ret_array['areas'][] = json_decode(file_get_contents(isoline_url($val['lat'], $val['lon'])), true);
			sleep(1); // throttling
		}
	}

	$ret_raw = json_encode($ret_array);

	file_put_contents(CACHE_FILE, $ret_raw);

	function isoline_url($lat, $lng)
	{
		if (mapboxAccessToken != null)
		{
			return "https://api.mapbox.com/isochrone/v1/mapbox/walking/" . $lng . "," . $lat . "?contours_minutes=3&contours_colors=6706ce,04e813,4286f4&polygons=true&access_token=" . mapboxAccessToken;
		}
		else
		{
			return 'https://www.iso4app.net/rest/1.3/isoline.geojson?licKey=87B7FB96-83DA-4FBD-A312-7822B96BB143'
			.'&type=isochrone'
			.'&value=600'	// seconds
			.'&lat=' . $lat .'&lng=' . $lng
			.'&approx=50&mobility=pedestrian&speedType=normal&reduceQueue=false&avoidTolls=true&restrictedAreas=false&fastestRouting=true&concavity=6&buffering=3&reqId=A57X';
		}
	}

?>


