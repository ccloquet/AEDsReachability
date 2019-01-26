<?php
	
	require_once('params.php');

	$ret_array = [];

	$bb = [50.76, 50.92, 4.23, 4.50]; 	// Brussels only
						// whole europe would be (35,-13,71,29)
	// 1. get data from OSM	(defibrillators)
	echo 'OSM DEFIBRILLATORS<br>';
	$data_url = 'https://overpass-api.de/api/interpreter?data=%5Bout%3Ajson%5D%5Btimeout%3A180%5D%3B%0A%2F%2F%20gather%20results%0A%28%0A%20%20%2F%2F%20query%20part%20for%3A%20%E2%80%9Cemergency%3Ddefibrillator%E2%80%9D%0A%20%20node%5B%22emergency%22%3D%22defibrillator%22%5D%2848.7%2C2%2C51%2C4.5%29%3B%0A%20%20way%5B%22emergency%22%3D%22defibrillator%22%5D%2848.7%2C2%2C51%2C4.5%29%3B%0A%20%20relation%5B%22emergency%22%3D%22defibrillator%22%5D%2848.7%2C2%2C51%2C4.5%29%3B%0A%29%3B%0A%2F%2F%20print%20results%0Aout%20body%3B%0A%3E%3B%0Aout%20skel%20qt%3B';
	
	$ret_raw		= file_get_contents($data_url);
	$ret_array 		= json_decode($ret_raw, true);

	echo strlen($ret_raw) .'<br>';

	// 2. get data from OSM	: AXA in Brussels (partial)
	echo 'OSM AXA & ING<br>';
	$data_url = 'https://overpass-api.de/api/interpreter?data=%0A%5Bout%3Ajson%5D%5Btimeout%3A50%5D%3B%0A%2F%2F%20gather%20results%0A%28%0A%20%20node%5B%22amenity%22%3D%22bank%22%5D%5B%22name%22%3D%22AXA%22%5D%2850.73471682490245%2C4.198493957519531%2C50.935281831886016%2C4.560699462890625%29%3B%0A%29%3B%0A%2F%2F%20print%20results%0Aout%20body%3B%0A%3E%3B%0Aout%20skel%20qt%3B';
	
	$ret_raw		= file_get_contents($data_url);
	$ret_tmp 		= json_decode($ret_raw, true);

	foreach ($ret_tmp['elements'] as $elem)
	{
		if (strtolower($elem['tags']['name']) == 'axa') $elem['tags']['operator'] = 'AXA';
		if (strtolower($elem['tags']['name']) == 'ing') $elem['tags']['operator'] = 'ING';
		$ret_array['elements'][]= $elem;
	}

	echo strlen($ret_raw) .'<br>';
	//echo $ret_raw; 
	
	// 3. get data from Interparking
	// https://www.interparking.be/fr-Be/find-parking/search-results/?Keyword=Bruxelles
	echo 'INTERPARKING<br>';
	$headers = ["Content-Type: application/x-www-form-urlencoded; charset=UTF-8","Accept: application/json, text/javascript, */*; q=0.01", "X-Requested-With: XMLHttpRequest", "Cache-Control: max-age=0"];
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 			'https://www.interparking.be/fr-Be/find-parking/search-results/?Keyword=Bruxelles');
	curl_setopt($ch, CURLOPT_POST, 			TRUE);
 	curl_setopt($ch, CURLOPT_HTTPHEADER, 		$headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,  	1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,     	'urlHash=%7B%0A++%22Services%22%3A+%5B%0A++++%22D%C3%A9fibrillateur+(AED)%22%0A++%5D%0A%7D&requestType=FilterParkings');
	$ret = curl_exec($ch);
	curl_close($ch);
 
	$ret = json_decode($ret, true);
	foreach ($ret['MapItems'] as $mapitem)
	{
		echo json_encode ($mapitem['Point']).'<br>';

		$a 	= strpos($mapitem['Html'], '<strong>');
		$b 	= strpos($mapitem['Html'], '</strong>');
		$name 	= substr($mapitem['Html'], $a+8, $b-$a-8) ;

		$a 	= strpos($mapitem['Html'], '<p>');
		$b 	= strpos($mapitem['Html'], '</p>');
		$adress = substr($mapitem['Html'], $a+3, $b-$a-3);

		$ret_array['elements'][] = ['type'=>'node', 'lat'=>floatval($mapitem['Point'][0]), 'lon'=>floatval($mapitem['Point'][1]), 'tags'=>['name'=>$name, 'adress'=>$adress, 'operator'=>'Interparking', 'source'=>'Interparking']];
	}
	
	//4. get data from BASIC FIT
	echo 'BASIC-FIT<br>';
	$ret = file_get_contents('https://www.basic-fit.com/ClubModule/Search/GetAllClubs?siteName=BasicFit~FR-BE&countryCode=BE');

	$ret = json_decode($ret, true);

	foreach ($ret as $mapitem)
	{
		$ret_array['elements'][] = [	'type'=>'node', 
						'lat' =>floatval($mapitem['lat']), 'lon'=>floatval($mapitem['lng']), 
						'tags'=>['name'=>$mapitem['title'], 'adress'=>$mapitem['street'] . ' ' . $mapitem['number'] . ' ' . $mapitem['zipcode'], 'isOpen24Hours' => $mapitem['isOpen24Hours'], 'operator'=> 'Basic-Fit', 'source'=>'Basic-Fit'] ];
	}

	//5. data from commune d'Uccle
	//http://www.uccle.be/administration/travaux/la-commune-compte-33-defibrillateurs-externes-automatiques-dea-repartis-dans-divers-lieux
	//http://www.uccle.be/administration/travaux/docs/Defibrilateurs%20Uccle.pdf

	$uccle=[
		["Centre Culturel d’Uccle","",50.80113,4.34242],
		["Maison communale","",50.80356,4.33323],
		["Piscine Longchamp","",50.805,4.34805],
		["Bibliothèque du « Homborch »","Homborchveld, 30 1180 Uccle",50.77638,4.34206],
		["Bibliothèque du « Centre »"," Rue du Doyenné, 64 1180 Uccle",50.8054,4.33908],
		["Bibliothèque médiathèque « La Phare »","Chaussée de Waterloo, 935 1180 Uccle",50.80717,4.37062],
		["Bibliothèque Néerlandophone","Rue de Broyer, 27 1180 Uccle",50.80192,4.33536],
		["Salle omnisports « Neerstalle »","Rue Zwartebeek, 23 1180 Uccle",50.79767,4.31731],
		["Complexe sportif « Jacques Van Offelen »","Avenue Brugmann, 524 1180 Uccle",50.80084,4.33836],
		["Complexe sportif « St Job »","Place de Saint Job, 20 1180 Uccle",50.79457,4.3673],
		["Dojo « Sauvagère »","Avenue de la Chênaie, 83 1180 Uccle",50.78824,4.35033],
		["Tir de la « Sauvagère »","Avenue de la Chênaie, 83 1180 Uccle",50.78824,4.35033],
		["Complexe sportif « André Deridder »","Rue des Griottes, 26 1180 Uccle",50.78458,4.33523],
		["Etang de pêche","Rue de Linkebeek, 71 1180 Uccle",50.77929,4.32871],
		["Complexe de football « Neerstalle »","Chaussée de Neerstalle, 431 1180 Uccle",50.79802,4.31799],
		["Ecole communale de « Calevoet » "," Rue François Vervloet, 10 1180 Uccle",50.79116,4.3311],
		["Ecole communale du « Centre » "," Rue du Doyenné, 60 1180 Uccle",50.80591,4.33828],
		["Ecole communale des « Eglantiers » + salle d'escrime"," Avenue des Eglantiers, 21 1180 Uccle",50.7832,4.37281],
		["Ecole communale des « Ecureuils » "," Avenue d’Hougoumont 1180 Uccle",50.77398,4.38125],
		["Ecole communale du « Homborch » "," Homborchveld, 34 1180 Uccle",50.77694,4.34174],
		["Ecole communale de « Longchamp » "," Rue Edith Cavell, 29 1180 Uccle",50.81259,4.35655],
		["Ecole communale de « Messidor » "," Avenue de Messidor, 161 1180 Uccle",50.81002,4.34372],
		["Ecole communale de « St Job » "," Rue Jean Benaets, 74 1180 Uccle",50.79219,4.36243],
		["Ecole communale du « Val Fleuri » "," Rue Gatti de Gamond, 140 1180 Uccle",50.80188,4.32777],
		["Ecole communale de « Verrewinkel » "," Avenue Dolez, 544 1180 Uccle",50.77547,4.35471],
		["Ecole communale du « Merlo » "," Rue du Merlo, 16 1180 Uccle",50.79897,4.32216],
		["Ecole communale de l’ « I.C.P.P. » "," Rue des Polders, 51 1180 Uccle",50.79651,4.31915],
		["Ecole des « Arts d’Uccle » "," Rue Rouge, 2 1180 Uccle",50.8023,4.34274],
		["Centre de retraité de « Vandenkindere » "," Rue Vanderkindere, 383 1180 Uccle",50.81429,4.36052],
		["Centre de retraité de « Stroobant » "," Avenue Paul Stroobant, 43 1180 Uccle",50.7984,4.34762],
		["Centre de retraité de « Neerstalle » "," Chaussée de Neerstalle, 489 1180 Uccle",50.79689,4.32078],
		["Centre de retraité du « Homborch » "," Rue Kriekenput, 14 1180 Uccle",50.77982,4.33801],
	];

	foreach ($uccle as $item)
	{
		$ret_array['elements'][] = [	'type'=>'node', 
						'lat' =>floatval($item[2]), 'lon'=>floatval($item[3]), 
						'tags'=>['name'=>$item[0], 'adress'=>$item[1]] ];
	} 

	echo 'POST-PROCESSING<br>';
	$ret_array['areas'] 	= [];
	$data   		= $ret_array['elements'];
 
	foreach ($data as $val)
	{
 
		if ($val['type'] != 'node') continue;

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
			return "https://api.mapbox.com/isochrone/v1/mapbox/walking/" . $lng . "," . $lat . "?contours_minutes=2&contours_colors=6706ce,04e813,4286f4&polygons=true&access_token=" . mapboxAccessToken;
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

/*
[OK] Interparkings
------------
[partially OK] agences Axa 
[OK] les salles de sport Basic Fit, 
[TODO]les magasins Carrefour
[TODO]les hôtels du groupe Accor ainsi que 
[TODO]les centres commerciaux, comme le City 2 ont tous choisi d’investir dans un appareil.
sce: https://www.dhnet.be/regions/bruxelles/les-defibrillateurs-gagnent-du-terrain-a-bruxelles-le-point-commune-par-commune-carte-582645bccd70fb896a687176
*/

?>


