<?php

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/ArrestDB.php');

class SwaggerHelper {
	
	
	public static function getDocFromRoute($route, $allRoutes) {
	
		
		//Without parameter
		$apiCREATE["path"]="/".$route->routeName;
		$apiCREATE["operations"][]=SwaggerHelper::createOperation("POST", $route, SwaggerHelper::getParametersFromRoute($route, "POST"), $route->routeName);
		$apiCREATE["operations"][]=SwaggerHelper::createOperation("PUT", $route, SwaggerHelper::getParametersFromRoute($route, "PUT"), $route->routeName);
		
		$apiID["path"] = "/".$route->routeName."/{".$route->routeName."Id}";
		$apiID["operations"][]=SwaggerHelper::createOperation("GET", $route, SwaggerHelper::getParametersFromRoute($route, "GET", "id"), $route->routeName);
		$apiID["operations"][]=SwaggerHelper::createOperation("PUT", $route, SwaggerHelper::getParametersFromRoute($route, "PUT", "id"), "void");	
		$apiID["operations"][]=SwaggerHelper::createOperation("DELETE", $route, SwaggerHelper::getParametersFromRoute($route, "DELETE", "id"), "void");
				
		$apiLIST["path"] = "/".$route->routeName."/list";
		$apiLIST["operations"][]=SwaggerHelper::createOperation("GET", $route, SwaggerHelper::getParametersFromRoute($route, "GET", "list"), "array[".$route->routeName."]");
		
		$apis = array($apiCREATE, $apiID, $apiLIST);
		
		$result = array
		(
			'apiVersion' => API_VERSION,
			'apis' => $apis,
			'resourcePath' => "/".$route->routeName,
			//'basePath' => 'http://'.$_SERVER['HTTP_HOST'].ArrestDB::getRoot()
			'swaggerVersion' => "1.2",
			'produces' => array('application/json')
		);
		
		if(isset($allRoutes)) {
			$result['models']=SwaggerHelper::getModelsFromRoutes($allRoutes);
		}
		
		return $result;

	}
	
	public static function getPathFromOperation($path, $operations, $returnType) {
		$api["path"] = $path;
		$api["operations"] = $operations;
	}
	
	public static function getParametersFromRoute($route, $routeMethod, $routeAction = NULL) {
	
		//Disable of show required fields
		$noRequired = true;
		//Get parameters from model
		$parametersFromModel = false;
		//Set the ID as parameters
		$idAsParameter = false;
		
		$parameters = array();
		
		
		
		//Logic
		switch($routeMethod) {
			case "GET":
				if($routeAction == "id") {
					$parameters[] = SwaggerHelper::getIdParameter($route, true);
				}
			break;
			case "PUT":
			
				if($routeAction == "id") {
					$parameters[] = SwaggerHelper::getIdParameter($route, true);
				}

				$parameters[] = SwaggerHelper::getBodyParameterFromModel($route);
			break;
			case "POST":
				
				//$parameters[] = SwaggerHelper::getBodyParameterFromModel($route);
				$parameters = array_merge($parameters, SwaggerHelper::getParametersFromModel($route, false));
			break;
			case "DELETE":
				if($routeAction == "id") {
					$parameters[] = SwaggerHelper::getIdParameter($route, true);
				}
			break;
		}
		
		
		return $parameters;
	}
	
	public static function getIdParameter($route, $required) {
		return array('name' => $route->routeName."Id", 
						'paramType' => 'path',
						'type' => 'string',
						'required' => $required,
						"description" => "ID of ".$route->routeName);
	}
	
	public static function getParametersFromModel($route, $noRequired = false) {

		foreach($route->routeFields as $field) {
			
	
			if($field->fieldName != "id") {
			
				$parameters[] = array('name' => (!$field->isRelation) ? $field->fieldName : $field->relation->field,
									'type' => $field->fieldType,
									'paramType' => 'form',
									'required' => ($noRequired) ? false : $field->isRequired,
									'description' => $field->description);
			}
			
		}
				
		return $parameters;
	}
	
	public static function getBodyParameterFromModel($route) {
		return array('name' => "body",
					'paramType' => 'body',
					'required' => true,
					'type' => $route->routeName,
					'description' => $route->routeName." created object");
	}
	
	public static function createOperation($method, $route, $parameters = null, $operationType) {
	
		switch($method) {
			case "GET":
				$notes = "Retrieve ".$route->routeName." objects";
			break;
			case "PUT":
				if($operationType == "void") {
					$notes = "Update ".$route->routeName." object";
				} else {
					$notes = "Create or update ".$route->routeName." object";
				}
			break;
			case "POST":
				$notes = "Create ".$route->routeName." object";
			break;
			case "DELETE":
				$notes = "Deletes ".$route->routeName." object";
			break;
			default:
				$notes = $route->routeName." ".$method." operation";
			break;
		}
	
		$operation["method"]=$method;
		$operation["nickname"]=strtolower($method).strtolower($route->routeName);
		if(isset($parameters))
			$operation["parameters"]=$parameters;

		$operation['produces'] = array('application/json');
		$operation['notes'] = $notes;
		$operation['authorizations']=array();
		$operation['type']=$operationType;
		if($operationType == "array") {
			$operation["items"]["\$ref"]=$route;
		}
		return $operation;
	}
	
	
	public static function getModelsFromRoutes($routes) {
		
		$models = array();
		
		foreach($routes as $route) {
			$models[$route->routeName]["id"]=$route->routeName;
			
			$properties = array();

			foreach($route->routeFields as $f) {
				
				$properties[$f->fieldName]["description"]=$f->fieldName." field ".$f->fieldType;
				
				$properties[$f->fieldName]["type"]=$f->fieldType;
				
				if($f->isRequired) {
					$models[$route->routeName]["required"][]=$f->fieldName;
				}
			}
			
			$models[$route->routeName]["properties"]=$properties;
			
		}
		
		//var_dump($models);
		
		return $models;
	}
	
	public static function routeResume($routes) {
	
		foreach($routes as $routeName => $routeObject) {

			$operation["description"]="Operations about ".$routeName;
			$operation["path"]="/".$routeName;
		
			$r[] = $operation;
		}
		
		$result = array
		(
			'apiVersion' => API_VERSION,
			'apis' => $r
		);
		
		return $result;
	}
	
	
	
}

?>