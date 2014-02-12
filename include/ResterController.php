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
			
			if(isset($routeName) && $routeName == "api-doc" && isset($routePath)) {
				$this->doResponse(SwaggerHelper::getDocFromRoute($this->getAvailableRoutes()[$routePath[0]], $this->getAvailableRoutes()));
			}
		
			if(count($routePath) >= 1) {
				$command = $routePath[0];
				
				if(isset($this->customRoutes["GET"][$routeName][$command])) {
					$callback = $this->customRoutes["GET"][$routeName][$command];
					call_user_func($callback, $parameters);
				} else {
					$result = array_shift($this->getObjectByID($routeName, $command));
					$this->showResult($result);
				}								
			} else {
				if(isset($parameters))
					$result = $this->getObjectsFromRoute($routeName, $parameters);
				else
					$result = $this->getObjectsFromRoute($routeName);
				//show result forcing array result
				$this->showResult($result, true);
			}
		});
		
		$this->addRequestProcessor("POST", function($routeName = NULL, $routePath = NULL, $parameters = NULL) {
			
			if(!isset($routeName)) {
				$this->showError(400);
			}
		
			//Check for command
			if(count($routePath) == 1) {
				$command = $routePath[0];
				if(isset($this->customRoutes["POST"][$routeName][$command])) {
					$callback = $this->customRoutes["POST"][$routeName][$command];
					call_user_func($callback, $parameters);
					return;
				}
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
		
		$this->addRequestProcessor("PUT", function($routeName, $routePath) {
		
			if(!isset($routeName)) {
				$this->showError(400);
			}
			
			
			
			$input = file_get_contents('php://input');
			
			parse_str($input, $putData);
			
			
			
			
			if(empty($putData)) {
				error_log("Empty PUT request");
				$this->showError(400);
			}
			
			if(!isset($routePath) || count($routePath) < 1) {
			
				$route = $this->getAvailableRoutes()[$routeName];
				
				$pk = $route->primaryKey;
				
				if(!isset($putData[$route->primaryKey->fieldName])) {
					error_log("No PRIMARY KEY FIELD");
					var_dump($putData);
					echo $route->primaryKey->fieldName;
					$this->showError(400);
				} 
				$this->updateObjectFromRoute($routeName, $putData[$route->primaryKey->fieldName], $putData);
			} else {
				$this->updateObjectFromRoute($routeName, $routePath[0], $putData);
			}
			
			$result = $this->deleteObjectFromRoute($routeName, $routePath[0]);
			
			if($result > 0) {
				$this->showResult(ApiResponse::successResponse());
			} else {
				$this->showResult($result);
			}
		
		});
	}
	
	function addRouteCommand($routeCommand) {
		
		if($routeCommand->method == "DELETE" || $routeCommand->method == "PUT") {
			exit($routeCommand->method." is not supported on custom commands. Use GET or POST instead");
		}
	
		$routes = $this->getAvailableRoutes();
		if(isset($routes[$routeCommand->routeName])) {
			$this->customRoutes[$routeCommand->method][$routeCommand->routeName][$routeCommand->routeCommand]=$routeCommand->callback;
			$route = $routes[$routeCommand->routeName];
			$route->routeCommands[$routeCommand->routeCommand]=$routeCommand;
		}
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
					
					if($requestMethod == "GET")
						parse_str($_SERVER['QUERY_STRING'], $requestParameters);
					else if($requestMethod == "POST")
						$requestParameters = $_POST;
						
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
		if(isset($filters)) {
			foreach($filters as $filterField => $filterValue) {
				if($i == 0)
					$query[] = sprintf("WHERE %s = '%s'",  $filterField, $filterValue);
				else
					$query[] = sprintf("AND %s = '%s'",  $filterField, $filterValue);
				$i++;
			}
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
	
	function updateObjectFromRoute($routeName, $objectID, $newData) {
		if (empty($newData) === true) {
			$this->showError(204);
		}
		
		$currentRoute = $this->getAvailableRoutes()[$routeName];
		
		if(is_array($newData) === true) {
			$data = array();

			foreach ($newData as $key => $value) {
				$data[$key] = sprintf('"%s" = ?', $key);
			}

			$query = array
			(
				sprintf('UPDATE `%s` SET %s WHERE `%s` = '.$newData[$currentRoute->primaryKey->fieldName], $routeName, implode(',', $data), $currentRoute->primaryKey->fieldName),
			);

			$query = sprintf('%s;', implode(' ', $query));
			
			$result = $this->dbController->Query($query, $newData);
			
			$this->showResult($result);
		}
	}
	
	/*************************************/
	/* RETURN DATA FUNCTIONS
	/*************************************/
	
	function showError($errorNumber) {
		$result = ApiResponse::errorResponse($errorNumber);
		exit(ArrestDB::Reply($result));
	}
	
	function showResult($result, $forceArray = false) {
		if ($result === false || count($result) == 0) {
			$this->showError(404);
		} else if (empty($result) === true) {
			$this->showError(204);
		} else if($result === true || (is_int($result) && $result >= 1) ) {
			$this->doResponse(ApiResponse::successResponse());
		} else {
			if(is_array($result) && count($result) == 1 && !$forceArray)
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
		if(count($routePath) > 0)
			return array_values($routePath)[0];
		else
			return false;
	}
	
	public function getCurrentPath() {
		return array_values(array_filter(array_slice(explode("/", $this->getRoot()), 2)));
	}
	
	/**
	* Search the tables of the DB and configures the routes
	*/
	function getAvailableRoutes() {
		if(!isset($this->routes) || count($this->routes) === 0)
			$this->routes = $this->dbController->getRoutes();
		return $this->routes;
	}
	
	function getColumnsFromTable($table) {
		$result = $this->dbController->Query("DESCRIBE ".$table);
		return $result;
	}
}

?>