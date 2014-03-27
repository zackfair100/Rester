<?php

class Route {
	/** The main route name */
	var $routeName;
	/** All the fields contained in this route */
	var $routeFields = array();
	/** Route command processors */
	var $routeCommands = array();
	/** Route primary key field */
	var $primaryKey = NULL;
	/** All the file processors of this route */
	var $fileProcessors = array();
	
	/**
	 * Returns the relation fields with this route
	 * @return array of RouteField
	 */
	function getRelationFields() {
		$r = array();
		foreach($this->routeFields as $rf) {
			if($rf->isRelation)
				$r[]=$rf;
		}
		return $r;
	}
	
	/**
	 * Gets all the field names
	 * @param boolean $includeRelations all the relations are included with sql like reference
	 * @param boolean $routePrefix append route prefix
	 * @return array(string) array containing route names as string
	 */
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
				ResterUtils::Log($this->routeName." > ".$field->fieldName." IS FILE TYPE");
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
	

	/**
	 * PDO does not treat types of objects by default, this method casts the object values to appropiate type
	 * @param object $object the source object
	 * @return object the result object
	 */
	function mapObjectTypes($object) {
		foreach($this->routeFields as $rf) {
			if($rf->fieldType == "integer") {
				if(isset($object[$rf->fieldName]))
					$object[$rf->fieldName]=intval($object[$rf->fieldName]);
			}
		}
		return $object;
	}

}

?>