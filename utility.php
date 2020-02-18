<?php

class WC_IzoneUtility {

	static function post_to_url($url, $data = false) {
		if($data)
			$json = json_encode($data);
			var_dump("Printing post data");
			var_dump($json);

		$response = wp_remote_post($url, array(
			'method' => 'POST',
			'headers' => array(
				"Cache-Control" => "no-cache",
				"Content-Type" => "application/json"
			),
			'body' => $json
			)
		);

		// var_dump("Response from manilla");
		// var_dump($response);

		if (!is_wp_error($response)) {
			$r = wp_remote_retrieve_body($response);
			return $r;
		}
		
		return false;
	}

	static function get_to_url($url) {
		
		$response = wp_remote_get($url, array());
		// var_dump("Response from gis server");
		// var_dump($response);

		if (!is_wp_error($response)) {
			$r = wp_remote_retrieve_body($response);
			return $r;
		}
		return false;
	}

}

?>