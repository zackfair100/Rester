<?php

class RouteField {
	
	var $fieldName;
	var $fieldType;
	var $isRequired;
	var $isKey;
	var $defaultValue;
	var $description;
	var $isRelation = false;
	var $relation;
	
	static function getTypeFromMySQL($mysqlType) {
		$type = "string";
		if(strpos($mysqlType, "int") !== false) {
			$type = "integer";
		}
		
		return $type;
	}
		
	function setRelation($routeRelation) {
		$this->isRelation = true;
		$this->fieldName = $routeRelation->relationName;
		$this->fieldType = $routeRelation->destinationRoute;
		$this->relation = $routeRelation;
		$this->isRequired = false;
		//error_log("SET RELATION ".$this->fieldName." ".$routeRelation->relationName." - type: ".$routeRelation->route);
	}
	
}

?>