<?php

class RouteRelation {
	var $relationName;
	
	static function parseRelationsFromMySQL($relationsArray) {
		$relations = array();
		
		foreach($relationsArray as $r) {
			$relation = new RouteRelation();
			$relation->relationName = $r["CONSTRAINT_NAME"];
			$relation->route = $r["TABLE_NAME"];
			$relation->field = $r["COLUMN_NAME"];
			$relation->destinationRoute = $r["REFERENCED_TABLE_NAME"];
			$relation->destinationField = $r["REFERENCED_COLUMN_NAME"];
			$relations[$relation->route][]=$relation;
		}
		
		return $relations;
	}
	
}

?>