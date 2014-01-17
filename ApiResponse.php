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