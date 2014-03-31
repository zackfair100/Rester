<?php

require_once('config.php');
require_once(__DIR__.'/ResterUtils.php');
require_once(__DIR__.'/UUID.php');
require_once(__DIR__.'/model/RouteFileProcessor.php');
require_once('OAuthServer.php');
require_once('OAuthStore.php');


class ResterController {

	var $routes = array();
	
	var $customRoutes = array();
	
	var $dbController;
	
	var $requestProcessors = array();
	
	/**
	* Indexed array containing the methods don't checked by OAuth
	*/
	var $publicMethods;
	
	function ResterController() {
		$this->dbController = new ArrestDB();
		$this->checkConnectionStatus();
		
		//Internal processors
		
		$this->addRequestProcessor("OPTIONS", function($routeName = NULL, $routePath = NULL, $parameters = NULL) {
			if(isset($routeName) && isset($routePath)) {
				$this->doResponse(SwaggerHelper::getDocFromRoute($this->getAvailableRoutes()[$routePath[0]], $this->getAvailableRoutes()));
			}
			$this->doResponse(NULL);
		});
		
		$this->addRequestProcessor("HEAD", function($routeName = NULL, $routePath = NULL, $parameters = NULL) {
			$this->showResult("");
		});
		
		/**
		* This is the main GET processor
		* If the request does not have a route, shows the doc for swagger
		* If we have a route and no operation or parameters, show the doc for swagger (route specific)
		* Else we process the parameters, checks if we have a command or an ID to return the results 
		*/
		$this->addRequestProcessor("GET", function($routeName = NULL, $routePath = NULL, $parameters = NULL) {
			//If we ask for the root, give the docs
			if($routeName == NULL) {
				$this->showRoutes();
			}
			
			if(isset($routeName) && $routeName == "api-doc" && isset($routePath)) {
				ResterUtils::Log("Returning apidoc");
				$this->doResponse(SwaggerHelper::getDocFromRoute($this->getAvailableRoutes()[$routePath[0]], $this->getAvailableRoutes()));
			}
			
			$this->checkRouteExists($routeName);
			
		
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
				if(isset($parameters)){
					$result = $this->getObjectsFromRoute($this->routes[$routeName], $parameters);
				} else
					$result = $this->getObjectsFromRoute($this->routes[$routeName]);
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
					ResterUtils::Log(">> Executing custom command <".$command.">");
					$callback = $this->customRoutes["POST"][$routeName][$command];
					call_user_func($callback, $parameters);
					return;
				} else { //tenemos un id; hacemos un update
					$result = $this->updateObjectFromRoute($routeName, $routePath[0], $_POST);
					$this->processFiles($this->getAvailableRoutes()[$routeName], $_POST);
					$this->showResult($result);
				}
			}
		
			if (empty($_POST) === true) { //if empty, create a barebone object
			
				$body = $this->getRequestBody();			
				
				if($body == NULL) {
					//not postbody and no post data... we create something...
					ResterUtils::Log(">> CREATING BAREBONE 8======8");
					$barebone = array();
					$result = $this->insertObject($routeName, $barebone); //give all the post data	
				} else {
					//Create object from postbody
					ResterUtils::Log(">> CREATING OBJECT FROM POSTBODY: *CREATE*");
					$result = $this->insertObject($routeName, $body);
				}
				$this->showResult($result, 201);
				
			} else if (is_array($_POST) === true) { 
				$result = $this->insertObject($routeName, $_POST); //give all the post data	
				$this->showResult($result, 201);
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
		
			ResterUtils::Log("PROCESSING PUT");
			if(!isset($routeName)) {
				$this->showError(400);
			}
			
			$input = file_get_contents('php://input');
			
			if(empty($input)) {
				ResterUtils::Log("Empty PUT request");
				$this->showError(400);
			}
			
			if(!isset($routePath) || count($routePath) < 1) { //no id in URL, we expect json body
			
				$putData = json_decode($input, true);
				$route = $this->getAvailableRoutes()[$routeName];
				if(is_array($putData) && ResterUtils::isIndexed($putData) && count($putData) > 0) { //iterate on elements and try to update
					ResterUtils::Log("UPDATING MULTIPLE OBJECTS");
					foreach($putData as $updateObject) {
						ResterUtils::Log("UPDATING OBJECT ".$routeName." ID: ".$updateObject["id"]);
						if(isset($updateObject[$route->primaryKey->fieldName])) {
							$result = $this->updateObjectFromRoute($routeName, $updateObject[$route->primaryKey->fieldName], $updateObject);
						}
					}
					ResterUtils::Log("SUCCESS");
					$this->doResponse(ApiResponse::successResponse());
				} else {
					ResterUtils::Log("UPDATING SINGLE OBJECT");
					if(!isset($putData[$route->primaryKey->fieldName])) {
						ResterUtils::Log("No PRIMARY KEY FIELD ".$input);
						echo $route->primaryKey->fieldName;
						$this->showError(400);
					}	 
					$result = $this->updateObjectFromRoute($routeName, $putData[$route->primaryKey->fieldName], $putData);
					$this->showResult($result);
				}
			} else { //id from URL
		
				parse_str($input, $putData);
			
					ResterUtils::Log("IS INDEXED");
					$result = $this->updateObjectFromRoute($routeName, $routePath[0], $putData);
					$this->showResult($result);
		
			}
					
			if($result > 0) {
				$this->doResponse(ApiResponse::successResponse());
			} else {
				$this->showResult($result);
			}
		
		});
	}

	function checkOAuth() {
		
		
		global $validOrigins;
		
		if(isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $validOrigins)) {
			return;
		}
		
		
		
		//Command to generate the Request Tokens
		$this->addRouteCommand(new RouteCommand("POST", "auth", "requestToken", function($params = NULL) {
		
			if(empty($_POST["userId"])) {
				$this->showError(400);
			}
			
			$store = OAuthStore::instance('PDO', array('conn' => ArrestDB::$db));
		
			$key = $store->updateConsumer($_POST, $_POST["userId"], true);
			$c = $store->getConsumer($key, $_POST["userId"]);
			
			$result["key"]=$c["consumer_key"];
			$result["secret"]=$c["consumer_secret"];
			
			$this->showResult($result);
			
		}, array("userId"), "Request a new token"));
		
		
		// Create a new instance of OAuthStore and OAuthServer
		$store = OAuthStore::instance('PDO', array('conn' => ArrestDB::$db));
		$server = new OAuthServer();
		
		ResterUtils::Log(">> CHECKING OAUTH ".$_SERVER['REQUEST_METHOD']);
		
		if (OAuthRequestVerifier::requestIsSigned()) {
		
			//If the request is signed, allow from any source
			header('Access-Control-Allow-Origin: *');
		
			try {
				$req = new OAuthRequestVerifier();
				$id = $req->verify(false);
				ResterUtils::Log("*** API USER ".$id." ***");
			}  catch (OAuthException2 $e)  {
				// The request was signed, but failed verification
				header('HTTP/1.1 401 Unauthorized');
				header('WWW-Authenticate: OAuth realm=""');
				header('Content-Type: text/plain; charset=utf8');
				ResterUtils::Log(">> OAUTH ERROR >> ".$e->getMessage());
				exit();
			}	
		} else {
				
				ResterUtils::Log(">> OAUTH: Unsigned request");
				if(isset($validOrigins)) {
					foreach($validOrigins as $origin) {
						ResterUtils::Log(">> ADD ORIGIN: ".$origin);
						header('Access-Control-Allow-Origin: '.$origin);
					}
				} else {
					//TODO; CHECK ORIGIN
					header('HTTP/1.1 401 Unauthorized');
					header('WWW-Authenticate: OAuth realm=""');
					header('Content-Type: text/plain; charset=utf8');
					echo "Authentication error";
					ResterUtils::Log(">> OAUTH ERROR >> Request not signed");
					ResterUtils::Log("*** AUTH ERROR *** ===>");
					
					exit();
				}
			//$this->showError(401);
		}
    }
	
	function getRequestBody() {
		$requestBody = @file_get_contents('php://input');
			
		if(empty($requestBody)) {
			return NULL;
		} 
			
		return json_decode($requestBody, true);
	}
	
	function checkRouteExists($routeName) {
		if(!isset($this->getAvailableRoutes()[$routeName])) {
				$this->showError(404);
				return false;
		}
		return true;
	}
	
	function addFileProcessor($routeName, $fieldName, $acceptedTypes = NULL) {
		if(isset($this->getAvailableRoutes()[$routeName])) {
			$this->getAvailableRoutes()[$routeName]->addFileProcessor($fieldName);	
		} else
			die("Can't add file processor ".$fieldName." to route ".$routeName);
		
	}
	
	function addPublicMethod($requestMethod, $routeName) {
		$this->publicMethods[$requestMethod][]=$routeName;
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
	
		ResterUtils::Log("*** BEGIN PROCESSING REQUEST ".$requestMethod." *** ==> ".$this->getRoutePath());
		
		global $validOrigins;
		
		if(isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $validOrigins)) {
			ResterUtils::Log(">> ADD ORIGIN: ".$_SERVER['HTTP_ORIGIN']);
			header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
		}
	
		if(!isset($this->publicMethods[$requestMethod])) {
			if($requestMethod !== "OPTIONS")
				$this->checkOAuth();
		} else {
			$publicRoutes = $this->publicMethods[$requestMethod];
			if(!in_array($this->getRoutePath(), $publicRoutes) && !in_array($this->getCurrentRoute(), $publicRoutes))
				$this->checkOAuth();
			else
				ResterUtils::Log("*** PUBLIC ROUTE ==> ".$this->getRoutePath());
		}
	
		
		
		if(isset($this->requestProcessors[$requestMethod])) {
			foreach($this->requestProcessors[$requestMethod] as $callback) {
			
				ResterUtils::Log(">> Found processor callback");
			
				$callbackParameters = array();
				
				if($this->getCurrentRoute() && $this->getCurrentRoute() != "/") {
					$callbackParameters[0] = $this->getCurrentRoute();
					ResterUtils::Log(">> Processing route /".$this->getCurrentRoute());
					if(count($this->getCurrentPath()) > 0) {
						$callbackParameters[1]=$this->getCurrentPath();
						ResterUtils::Log(">> Processing command ".implode("/",$this->getCurrentPath()));
					} else {
						$callbackParameters[1] = NULL;
					}
					
					if($requestMethod == "GET")
						parse_str($_SERVER['QUERY_STRING'], $requestParameters);
					else if($requestMethod == "POST")
						$requestParameters = $_POST;
						
					if(isset($requestParameters)) {
						$callbackParameters[2] = $requestParameters;
						ResterUtils::Log(">> PARAMETERS: ".http_build_query($requestParameters));
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
				ResterUtils::Log("SERVING: ".$requestMethod." ".$pattern);
				$result = ArrestDB::Serve($requestMethod, $callback);
				if($result) {
					$this->doResponse($result);	
				}*/
			}
		} else {
			ResterUtils::Log("*** ERROR *** Request processor not set ".$requestMethod);
			$this->showError(405);
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
	
		$route = $this->getAvailableRoutes()[$routeName];
	
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
				$data[sprintf('%s', $key)] = '?';
				$values[$key] = $value;
			}
			//No autoincrement support
			$insertID = NULL;
			if($route->primaryKey != NULL) {
				//if(!isset($data[$route->primaryKey->fieldName])) { //we have not passed an id by parameters
				if(!array_key_exists($route->primaryKey->fieldName, $data)) {
					ResterUtils::Log(">> NO KEY SET ON CREATE *".$route->primaryKey->fieldName."*");
					if($route->primaryKey->isAutoIncrement) { //put a dummy value to auto_increment do the job
						$data[$route->primaryKey->fieldName] = '?';
						$values[$route->primaryKey->fieldName] = '0';
					} else {
						$insertID = UUID::v4();
						ResterUtils::Log(">> GENERATING NEW UUID ".$insertID);;
						$data[$route->primaryKey->fieldName] = "?";
						$values[$route->primaryKey->fieldName] = $insertID; //generate a UUID
					}
				} else {
					//ResterUtils::Dump($values);
					$insertID = $values[$route->primaryKey->fieldName];
				}
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
		
	
		if($result == 0) { //No insert id
			$result = $insertID;
		}
	
		ResterUtils::Log("RESULT: **** ".$result);
	
		//Process files
		if(count($_FILES) > 0) { //we got files... process them
			foreach($_FILES as $fileField => $f) {
				if($route->getFileProcessor($fileField) != NULL) { //We have to process
					$processor = $route->getFileProcessor($fileField);
					$upload = $processor->saveUploadFile($result, $route->routeName, $f);
					$newData = array ($route->primaryKey->fieldName => $result, $fileField => $upload["destination"]);
					$this->updateObjectFromRoute($route->routeName, $result, $newData);
				}
			}
		}
		
		$object = $this->getObjectByID($routeName,$result);
		
		return array_shift($object);
	}
	
	function getObjectsFromRouteName($routeName, $filters = NULL, $orFilter = false) {
		return $this->getObjectsFromRoute($this->getAvailableRoutes()[$routeName], $filters, $orFilter);
	}
	
	function getObjectsFromRoute($route, $filters = NULL, $orFilter = false) {
	
		$fieldFilters = $filters;
		$fieldFilters = $this->cleanReservedFields($fieldFilters);
		
		
		if(isset($filters['order'])) {
			$order['by']=$filters['order'];
			unset($filters['order']);
		}
		if(isset($filters['orderType'])) {
			$order['order']=$filters['orderType'];
			unset($filters['orderType']);
		}	
		
		if(count($route->getRelationFields()) > 0) {
		
			foreach($route->getRelationFields() as $rf) {
				$destinationRoute = $this->getAvailableRoutes()[$rf->relation->destinationRoute];
				
				foreach($destinationRoute->getRelationFieldNames($rf->relation) as $fieldKey => $rName) {
					$relationFieldNames[] = $rf->relation->relationName.".".$fieldKey." as ".$rName;
				}
			}
		
		}
		
		$query[] = "SELECT ";
		
		$query[] = implode(",", $route->getFieldNames(FALSE, TRUE));
		
		if(isset($relationFieldNames)) {
			$query[] = ",";
			$query[] = implode(",",$relationFieldNames);
		}
		
		$query[] = " FROM `".$route->routeName."` as ".$route->routeName;
		
		//$query = array(sprintf('SELECT * FROM "%s"', $route->routeName));
		
		if(count($route->getRelationFields()) > 0) {
			foreach($route->getRelationFields() as $relationField) {
				$query[] = ",".$relationField->relation->destinationRoute." as ".$relationField->relation->relationName;
			}
		}

		$i = 0;
		
		if(isset($fieldFilters)) {
			
			$closeBracket = false;
			
			foreach($fieldFilters as $filterField => $filterValue) {
			
				if($i == 0) {
					$q = "WHERE ("; 
					$closeBracket = true;
				} else {
					if($orFilter) {
						$q = "OR";
					} else {
						$q = "AND";
					}
				}
				
				$q .= " (".$route->routeName.".".$filterField." ";
						
				if(is_array($filterValue)) {
					$queryType = array_keys($filterValue)[0];
					$queryValue =$filterValue[$queryType];	
					
					$val = explode(",", $queryValue);
					
					
					
					switch($queryType) {
						case "in":
							$q.="LIKE '%".$queryValue."%'";
						break;
						case "gt":
							$q.="> ".$queryValue;
						break;
						case "lt":
							$q.="< ".$queryValue;
						break;
						case "ge":
							$q.=">= ".$queryValue;
						break;
						case "le":
							$q.="<= ".$queryValue;
						break;
						default:
							$q.="= '".$queryValue."'";
						break;
					}
					
					
				} else {
	
					$val = explode(",", $filterValue);
	
					//search mode
					$q.="= '".$val[0]."'";
					
					
					for($i = 1; $i<count($val); $i++) {
						$q.=" OR ".$route->routeName.".".$filterField." = '".$val[$i]."'";	
					}

				}
				
				$q.= ")";				
				
				$query[] = $q;
				
				$i++;
			}
		}
		
		if($closeBracket)
			$query[] = ")";
		
		//JOINS
		if(count($route->getRelationFields()) > 0) {
			if(!isset($fieldFilters) || count($fieldFilters) == 0) {
				$query[] = " WHERE ";
			} else {
				$query[] = " AND ";
			}
			$query [] = "(";
			$i = 0;
			foreach($route->getRelationFields() as $relationField) {
				if($i > 0)
					$query[] = "AND";
				$query[] = " ".$route->routeName.".".$relationField->relation->field." = ".$relationField->relation->relationName.".".$relationField->relation->destinationField." ";
				$i++;
			}
			$query [] = ")";
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
			$query[] = "LIMIT 1000";
		}
		
		$query = sprintf('%s;', implode(' ', $query));
		
		$result = $this->dbController->Query($query);
		
		foreach($result as $row) {
			$mainObject = ResterUtils::cleanArray($row, $route->getFieldNames(FALSE, FALSE));
			
			$mainObject = $route->mapObjectTypes($mainObject);
			
			if(count($route->getRelationFields()) > 0) {
				foreach($route->getRelationFields() as $rf) {
					
					$destinationRoute = $this->getAvailableRoutes()[$rf->relation->destinationRoute];
									
					$relationObject = array();
					foreach($destinationRoute->getRelationFieldNames($rf->relation) as $fieldKey => $rName) {
						$relationObject[$fieldKey]=$row[$rName];
					}
					
					$relationObject = $destinationRoute->mapObjectTypes($relationObject);
					
					$mainObject[$rf->relation->destinationRoute]=$relationObject;
				}
			}
			
			$response[]=$mainObject;
		}
		if(!isset($response)) {
			return NULL;
		}
		return $response;
	}
	
	function query($query) {
		return $this->dbController->Query($query);
	}
		
	function getObjectByID($routeName, $ID) {

		$route = $this->getAvailableRoutes()[$routeName];
		
		if(is_array($ID)) {
			$ID=implode(",", $ID);
		}
		
		$filter = array($route->primaryKey->fieldName => $ID);
			
		$result = $this->getObjectsFromRoute($route, $filter);
		
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
		ResterUtils::Log("UPDATING OBJECT");
		if (empty($newData) === true) {
			$this->showError(204);
		}
		
		$currentRoute = $this->getAvailableRoutes()[$routeName];
		
		if(is_array($newData) === true) {
			$data = array();

			foreach ($newData as $key => $value) {
				$data[$key] = sprintf('`%s` = ?', $key);
			}

			$query = array
			(
				sprintf('UPDATE `%s` SET %s WHERE `%s` = '.$objectID, $routeName, implode(',', $data), $currentRoute->primaryKey->fieldName),
			);

			$query = sprintf('%s;', implode(' ', $query));
			
			$result = $this->dbController->Query($query, $newData);
			
			return $result;
		}
	}
	
	function processFiles($route, $baseObject) {
			//Process files
		if(count($_FILES) > 0) { //we got files... process them
			foreach($_FILES as $fileField => $f) {
				if($route->getFileProcessor($fileField) != NULL) { //We have to process
					$processor = $route->getFileProcessor($fileField);
					$upload = $processor->saveUploadFile($baseObject[$route->primaryKey->fieldName], $route->routeName, $f);
					$newData = array ($route->primaryKey->fieldName => $baseObject[$route->primaryKey->fieldName], $fileField => $upload["destination"]);
					$this->updateObjectFromRoute($route->routeName, $baseObject[$route->primaryKey->fieldName], $newData);
				}
			}
		}
	}
	
	/*************************************/
	/* RETURN DATA FUNCTIONS
	/*************************************/
	
	function showError($errorNumber) {
		$result = ApiResponse::errorResponse($errorNumber);
		exit($this->doResponse($result));
	}
	
	function showResult($result, $forceArray = false) {
	
		ResterUtils::Log("*** DISPLAY RESULT TO API ***");
		
		//ResterUtils::Dump($result);
	
		if ($result === false || count($result) == 0) {
			$this->showError(404);
		} else if (empty($result) === true) {
			$this->showError(204);
		} else if($result === true || (is_int($result) && $result >= 1) ) {
			$this->doResponse(ApiResponse::successResponse());
		} else {
			if(is_array($result) && count($result) == 1 && !$forceArray && ResterUtils::isIndexed($result)) {
				$this->doResponse($result[0]);
			} else
				$this->doResponse($result);
		}
	}
	
	private function doResponse($data, $responseCode = 200) {
	
		if(isset($data["error"])) {
			ResterUtils::Log(">> Error Response: ".$data["error"]["code"]." ".$data["error"]["status"]);
			header("HTTP/1.1 ".$data["error"]["code"]." ".$data["error"]["status"], true, $data["error"]["code"]);
		}
	
		if($responseCode != 200) {
			switch($responseCode) {
				case 201:
					header("HTTP/1.1 ".$responseCode." Created");
				break;
			}
		}
	
		$bitmask = 0;
		$options = array('UNESCAPED_SLASHES', 'UNESCAPED_UNICODE');

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}

		exit($result);
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
	
	public function getRoutePath() {
		if(count($this->getCurrentPath()) > 0)
			return $this->getCurrentRoute()."/".implode("/",$this->getCurrentPath());
		else
			return $this->getCurrentRoute();
	}
	
	
	
	/**
	* Search the tables of the DB and configures the routes
	*/
	function getAvailableRoutes() {
		if($this->routes == NULL) {
			$this->routes = $this->dbController->getRoutes();
		}
		return $this->routes;
	}
	
	function getRoute($routeName) {
		$routes = $this->getAvailableRoutes();
		if(isset($routes[$routeName]))
			return $routes[$routeName];
			
		return NULL;
	}
	
	function getColumnsFromTable($table) {
		$result = $this->dbController->Query("DESCRIBE ".$table);
		return $result;
	}
	
	function getReservedFields() {
		return array("order", "limit", "orderType");
	}
	
	function cleanReservedFields($fieldsMap) {
		
		foreach($this->getReservedFields() as $reserved) {
			if(array_key_exists($reserved, $fieldsMap)) {
				unset($fieldsMap[$reserved]);
			}
		}
		
		return $fieldsMap;
	}
	
	/**
 * Parse raw HTTP request data
 *
 * Pass in $a_data as an array. This is done by reference to avoid copying
 * the data around too much.
 *
 * Any files found in the request will be added by their field name to the
 * $data['files'] array.
 *
 * @param   array  Empty array to fill with data
 * @return  array  Associative array of request data
 */
function parse_raw_http_request($input, array &$a_data)
{
   
  // grab multipart boundary from content type header
  preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
   
  // content type is probably regular form-encoded
  if (!count($matches))
  {
    // we expect regular puts to containt a query string containing data
    parse_str(urldecode($input), $a_data);
    return $a_data;
  }
   
  $boundary = $matches[1];
 
  // split content by boundary and get rid of last -- element
  $a_blocks = preg_split("/-+$boundary/", $input);
  array_pop($a_blocks);
  
  // loop data blocks
  foreach ($a_blocks as $id => $block)
  {
    if (empty($block))
      continue;
     
    // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char
     
    // parse uploaded files
    if (strpos($block, 'application/octet-stream') !== FALSE)
    {
      // match "name", then everything after "stream" (optional) except for prepending newlines
      preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
      $a_data['files'][$matches[1]] = $matches[2];
    }
    // parse all other fields
    else
    {
      // match "name" and optional value in between newline sequences
      preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
      $a_data[$matches[1]] = $matches[2];
    }
  }
}

	
} //END

?>