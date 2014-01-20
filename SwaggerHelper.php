<?php

require_once("config.php");
require_once("ArrestDB.php");

class SwaggerHelper {
	
	
	public static function getDocFromRoute($route, $routes = null) {
	
		$apiID["path"] = "/".$route."/{".$route."Id}";
		
		$paramID = array('name' => $route."Id", 
						'paramType' => 'path',
						'type' => 'string', 
						'required' => true,
						"description" => "ID of ".$route);
		
		$apiID["operations"][]=SwaggerHelper::createOperation("GET", $route, array($paramID), $route);
		$apiID["operations"][]=SwaggerHelper::createOperation("PUT", $route, $paramID, "void");
		$apiID["operations"][]=SwaggerHelper::createOperation("POST", $route, $paramID, "void");
		$apiID["operations"][]=SwaggerHelper::createOperation("DELETE", $route, $paramID, "void");
				
		$apiLIST["path"] = "/".$route."/list";
		$apiLIST["operations"][]=SwaggerHelper::createOperation("GET", $route, NULL, "array");
		
		$apis = array($apiID, $apiLIST);
	
		$result = array
		(
			'apiVersion' => API_VERSION,
			'apis' => $apis,
			'resourcePath' => "/".$route,
			//'basePath' => 'http://'.$_SERVER['HTTP_HOST'].ArrestDB::getRoot()
			'swaggerVersion' => "1.2",
			'produces' => array('application/json')
		);
		
		if(isset($routes)) {
			$result['models']=SwaggerHelper::getModelsFromRoutes($routes);
		}
		
		return $result;

	}
	
	public static function createOperation($method, $route, $parameters = null, $operationType) {
		$operation["method"]=$method;
		$operation["nickname"]=strtolower($method).strtolower($route);
		if(isset($parameters))
			$operation["parameters"]=$parameters;
			
		$operation['produces'] = array('application/json');
		$operation['notes'] = $route." ".$method." operation";
		$operation['authorizations']=array();
		$operation['type']=$operationType;
		if($operationType == "array") {
			$operation["items"]["\$ref"]=$route;
		}
		return $operation;
	}
	
	
	public static function getParametersFromRoutes() {
		
	}
	
	public static function getModelsFromRoutes($routes) {
		
		$models = array();
		
		foreach($routes as $route => $fields) {
			$models[$route]["id"]=$route;
			
			$properties = array();
			
			$required = array();
			
			foreach($fields as $f) {
				$fieldName = $f["Field"];
				$fieldType = $f["Type"];
				$fieldIsNull = $f["Null"];
				$fieldIsKey = $f["Key"];
				$fieldDefaultValue = $f["Default"];
			
				$type = "string";
				if(strpos($fieldType, "int") !== false) {
					$type = "integer";
				}
				
				if($fieldIsNull == "NO") {
					$required[]=$fieldName;
				}
				
				$properties[$fieldName]["description"]=$fieldName." field ".$fieldType;
				$properties[$fieldName]["type"]=$type;
			}
			
			$models[$route]["properties"]=$properties;
			if(count($required)>0)
				$models[$route]["required"]=$required;
		}
		
		//var_dump($models);
		
		return $models;
	}
	
	public static function routeResume($routes) {
	
		foreach($routes as $route => $fields) {

			$operation["description"]="Operations about ".$route;
			$operation["path"]="/".$route;
		
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