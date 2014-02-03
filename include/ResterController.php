<?php

require_once('config.php');

class ResterController {

	var $routes = array();
	
	var $customRoutes = array();
	
	var $dbController;
	
	var $requestProcessors = array();
	
	function ResterController() {
		$this->dbController = new ArrestDB();
		$this->checkConnectionStatus();
		
		//Internal processors
		
		/**
		* This is the main GET processor
		* If the request does not have a route, shows the doc for swagger
		* If we have a route and no operation or parameters, show the doc for swagger (route specific)
		* Else we process the parameters, checks if we have a command or an ID to return the results 
		*/
		$this->addRequestProcessor("GET", function($routeName = NULL, $routePath = NULL, $parameters = NULL) {
			error_log("PROCESSING GET ONE ".$routeName." - ".$routePath." - ".$parameters);
			//If we ask for the root, give the docs
			if($routeName == NULL) {
				$this->showRoutes();
			}
			
			if(isset($routeName) && !isset($routePath)) {
				$routes = $this->getAvailableRoutes();
				$this->doResponse(SwaggerHelper::getDocFromRoute($routes[$routeName], $routes));
			}
		
			if(count($routePath) == 1) {
				$command = $routePath[0];
				
				if(isset($this->customRoutes["GET"][$routeName][$command])) {
					$callback = $this->customRoutes["GET"][$routeName][$command];
					call_user_func($callback, $parameters);
				} else {
					$result = array_shift($this->getObjectByID($routeName, $command));
					$this->showResult($result);
				}
				
				/*
				if(is_numeric($command)) { //if its numeric we'll treat like an ID
					
				} else {
					switch($command) {
						case "list":
						$result = $this->getObjectsFromRoute($routeName, $parameters);
						break;
					}
				}*/
				
								
			}
		});
		
		$this->addRequestProcessor("POST", function($routeName = NULL, $routePath = NULL, $parameters = NULL) {
			
			if(!isset($routeName)) {
				$this->showError(400);
			}
		
			if (empty($_POST) === true) {
				$this->showError(204);
			} else if (is_array($_POST) === true) { 
				$result = $this->insertObject($routeName, $_POST); //give all the post data	
				$this->showResult($result);
			}
		});
		
		$this->addRequestProcessor("DELETE", function($routeName, $routePath) {
		
			if(!isset($routeName)) {
				$this->showError(400);
			}
			
			if(!isset($routePath) || count($routePath) < 1) {
				$this->showError(404);
			}
			
			$result = $this->deleteObjectFromRoute($routeName, $routePath[0]);
			
			if($result > 0) {
				$this->showResult(ApiResponse::successResponse());
			} else {
				$this->showResult($result);
			}
		
		});
	}
	
	function addCustomRoute($method, $routeName, $command, $callback) {
		$this->customRoutes[$method][$routeName][$command]=$callback;
	}
	
	function checkClientRestriction() {
		if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
		{
			exit($this->dbController->Reply(ApiResponse::errorResponse(403)));
		}
	}
	
	function addRequestProcessor($requestMethod, $callback) {
		$this->requestProcessors[$requestMethod][]=$callback;
	}
		
	function processRequest($requestMethod) {
		
		if(isset($this->requestProcessors[$requestMethod])) {
			foreach($this->requestProcessors[$requestMethod] as $callback) {
			
				error_log("PROCESSING ".$requestMethod);
				$callbackParameters = array();
				
				if($this->getCurrentRoute() && $this->getCurrentRoute() != "/") {
					$callbackParameters[0] = $this->getCurrentRoute();
					if(count($this->getCurrentPath()) > 0) {
						$callbackParameters[1]=$this->getCurrentPath();
					} else {
						$callbackParameters[1] = NULL;
					}
					parse_str($_SERVER['QUERY_STRING'], $requestParameters);
					if(isset($requestParameters)) {
						$callbackParameters[2] = $requestParameters;
					}
				}
								
				try {
					if(isset($callbackParameters) && count($callbackParameters) > 0) {
						call_user_func_array($callback, $callbackParameters);
					} else {
						call_user_func($callback);
					}
				} catch(Exception $e) {
					return false;
				}
			
				/*
				error_log("SERVING: ".$requestMethod." ".$pattern);
				$result = ArrestDB::Serve($requestMethod, $callback);
				if($result) {
					$this->doResponse($result);	
				}*/
			}
		} else {
			error_log("Request processor not set ".$requestMethod);
		}
	}
	
	//TODO
	function checkConnectionStatus() {
		/*if ($this->dbController->Query(DSN) === false) {
			exit($this->dbController->Reply(ApiResponse::errorResponseWithMessage(503, "Error connecting to SQL")));	
		}*/
	}
	
		
	/*************************************/
	/* OBJECT MANAGEMENT METHODS
	/*************************************/	

	function insertObject($routeName, $objectData) {
		$queries = array();

		if (count($objectData) == count($objectData, COUNT_RECURSIVE))
		{
			$objectData = array($objectData);
		}
		
		foreach ($objectData as $row)
		{
			$data = array();

			foreach ($row as $key => $value)
			{
				$data[sprintf('"%s"', $key)] = '?';
				$values[sprintf('"%s"', $key)] = $value;
			}

			$query = array
			(
				sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $routeName, implode(', ', array_keys($data)), implode(', ', $data)),
			);

		
			$queries[] = array
			(
				sprintf('%s;', implode(' ', $query)),
				$values,
			);
			
		}

		if (count($queries) > 1)
		{
			$this->dbController->Query()->beginTransaction();

			while (is_null($query = array_shift($queries)) !== true)
			{
				if (($result = $this->dbController->Query($query[0], $query[1])) === false)
				{
					$this->dbController->Query()->rollBack(); break;
				}
			}

			if (($result !== false) && ($this->dbController->Query()->inTransaction() === true))
			{
				$result = $this->dbController->Query()->commit();
			}
		} else if (is_null($query = array_shift($queries)) !== true) {
			$result = $this->dbController->Query($query[0], $query[1]);
		}

		return array_shift($this->getObjectByID($routeName,$result));
		/*
		
		if ($result === false) {
			$this->showError(409);
		} else {
			$result = array
			(
				'success' => array
				(
					'code' => 201,
					'status' => 'Created',
				),
			);
		}
		
		return $result;*/
	}
	
	function getObjectsFromRoute($routeName, $filters = NULL, $order = NULL) {
		$query = array(sprintf('SELECT * FROM "%s"', $routeName));
		
		/*$query = array (
			sprintf('SELECT * FROM "%s"', $routeName),
			sprintf('WHERE "%s" %s ?', $id, (ctype_digit($data) === true) ? '=' : 'LIKE'),
		);*/
		$i = 0;
		foreach($filters as $filterField => $filterValue) {
			if($i == 0)
				$query[] = sprintf("WHERE %s = '%s'",  $filterField, $filterValue);
			else
				$query[] = sprintf("AND %s = '%s'",  $filterField, $filterValue);
			$i++;
		}
		
		if (isset($order['by']) === true)
		{
			if (isset($order['order']) !== true)
			{
				$order['order'] = 'ASC';
			}

			$query[] = sprintf('ORDER BY "%s" %s', $order['by'], $order['order']);
		}

		if (isset($filters['limit']) === true)
		{
			$query[] = sprintf('LIMIT %u', $filters['limit']);

			if (isset($filters['offset']) === true)
			{
				$query[] = sprintf('OFFSET %u', $filters['offset']);
			}
		} else { //Default limit
			$query[] = "LIMIT 200";
		}
		
		$query = sprintf('%s;', implode(' ', $query));
		
		$result = $this->dbController->Query($query);
		
		return $result;
	}
	
	function getObjectByID($routeName, $ID) {
		$query = array(sprintf('SELECT * FROM "%s"', $routeName));
		
		$query[] = sprintf('WHERE "%s" = ? LIMIT 1', 'id');
	
		$query = sprintf('%s;', implode(' ', $query));
		
		$result = $this->dbController->Query($query, $ID);
		
		return $result;	
	}
	
	function deleteObjectFromRoute($routeName, $ID) {
		$query = array(
			sprintf('DELETE FROM "%s" WHERE "%s" = ?', $routeName, 'id')
		);

		$query = sprintf('%s;', implode(' ', $query));
	
		$result = $this->dbController->Query($query, $ID);

		return $result;
	}
	
	/*************************************/
	/* RETURN DATA FUNCTIONS
	/*************************************/
	
	function showError($errorNumber) {
		$result = ApiResponse::errorResponse($errorNumber);
		exit(ArrestDB::Reply($result));
	}
	
	function showResult($result) {
		error_log("SHOW RESULT");
		if ($result === false || count($result) == 0) {
			$this->showError(404);
		} else if (empty($result) === true) {
			$this->showError(204);
		} else if($result === true) {
			$this->doResponse(ApiResponse::successResponse());
		} else {
			if(is_array($result) && count($result) == 1)
				$this->doResponse($result[0]);
			else
				$this->doResponse($result);
		}
	}
	
	private function doResponse($responseObject) {
		exit(ArrestDB::Reply($responseObject));
	}
	
	function showRoutes() {
		$routes = $this->getAvailableRoutes();
		$result = SwaggerHelper::routeResume($routes);
		$this->doResponse($result);
	}
	
	/*************************************/
	/* ROUTE PARSING METHODS
	/*************************************/
	
	public function getRoot() {
		$root = preg_replace('~/++~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])) . '/');
		return $root;
	}
	
	public function getCurrentRoute() {
		$routePath = array_filter(explode("/", $this->getRoot()));
		return array_values($routePath)[0];
	}
	
	public function getCurrentPath() {
		return array_values(array_filter(array_slice(explode("/", $this->getRoot()), 2)));
	}
	
	/**
	* Search the tables of the DB and configures the routes
	*/
	function getAvailableRoutes() {	
		$this->routes = $this->dbController->getRoutes();
		return $this->routes;
	}
	
	function getColumnsFromTable($table) {
		$result = $this->dbController->Query("DESCRIBE ".$table);
		return $result;
	}
}

?>