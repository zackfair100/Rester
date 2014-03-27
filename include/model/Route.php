<?php

class Route {
	var $routeName;
	var $routeFields = array();
	var $routeCommands = array();
	var $primaryKey = NULL;
	var $fileProcessors = array();
	
	function getRelationFields() {
		$r = array();
		foreach($this->routeFields as $rf) {
			if($rf->isRelation)
				$r[]=$rf;
		}
		return $r;
	}
	
	function getFieldNames($includeRelations = TRUE, $routePrefix = FALSE) {
		$fieldNames = array();
		foreach($this->routeFields as $rf) {
			if($includeRelations || !$rf->isRelation) {
				if($routePrefix)
					$fieldNames[] = $this->routeName.".".$rf->fieldName;
				else
					$fieldNames[] = $rf->fieldName;
			}
		}
		return $fieldNames;
	}

	function getRelationFieldNames($relation) {
		foreach($this->getFieldNames(FALSE) as $fn) {
			$relationFieldNames[$fn] = $relation->relationName."_".$fn;
		}
		foreach($this->getRelationFields() as $rf) {
			$relationFieldNames[$rf->relation->field] = $rf->relation->relationName;
		}
		return $relationFieldNames;
	}

	function addFileProcessor($fieldName) {
		foreach($this->routeFields as $field) {
			if($field->fieldName == $fieldName) {
				error_log($this->routeName." > ".$field->fieldName." IS FILE TYPE");
				$field->isFile = true;
				$this->fileProcessors[] = new RouteFileProcessor($field);
			}
		}
	}
	
	function getFileProcessor($name) {
		foreach($this->fileProcessors as $processor) {
			if($name == $processor->field->fieldName)
				return $processor;
		}
		return NULL;
	}
	
	function mapObjectTypes($object) {
		foreach($this->routeFields as $rf) {
			if($rf->fieldType == "integer") {
				$object[$rf->fieldName]=intval($object[$rf->fieldName]);
			}
		}
		return $object;
	}
}

?>