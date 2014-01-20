<?php

class ApiResponse {
	
	public static function errorResponse($errorCode) {
		
		$status = "Bad Request";
		
		switch($errorCode) {
			case 404:
			$status = "Not found";
			break;
			case 400:
			$status = "Bad Request";
			break;
			case 204:
			$status = "No Content";
			break;
			case 403:
			$status = "Forbidden";
			break;
			case 503:
			$status = "Service Unavailable";
			break;
		}
		
		$result = array
		(
			'error' => array
				(
					'code' => $errorCode,
					'status' => $status,
				),
		);

		return $result;
		
	}
	
}

?>