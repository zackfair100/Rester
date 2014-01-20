<?php

require_once('config.php');

class ArrestDBController {

	var $routes = array();

	
	function ArrestDBController() {
		
	}
	
	function checkClientRestriction() {
		if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
		{
			exit(ArrestDB::Reply(ApiResponse::errorResponse(403)));
		}
	}
	
	function checkConnectionStatus() {
		if (ArrestDB::Query(DSN) === false) {
			exit(ArrestDB::Reply(ApiResponse::errorResponse(503)));	
		}
	}
	
	static function getColumnsFromTable($table) {
		$result = ArrestDB::Query("DESCRIBE ".$table);
		return $result;
	}
	
	/**
	* Search the tables of the DB and configures the routes
	*/
	static function getAvailableRoutes() {
		$routes = array();
		$result = ArrestDB::Query("SHOW TABLES");
	
		if ($result === false) {
			exit(ApiResponse::errorResponse(404));
		} else if (empty($result) === true) {
			exit(ApiResponse::errorResponse(204));
		} else {
			foreach($result as $k => $v) {
				$route = reset($v);
				$routes[$route]=ArrestDBController::getColumnsFromTable($route);
			}
		}

		return $routes;
	}
	
}

?>