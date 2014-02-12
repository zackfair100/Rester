<?php

//Constants

define('ROUTE_CACHE_KEY', 'routesKey');


class ApiCacheManager {
		
	static function getValueFromCache($valueName) {
		if(extension_loaded('apc') && ini_get('apc.enabled')) {
			$data = apc_fetch($valueName, $success);
			if(!$success)
				return NULL;
				
			return $data;
		} else {
			return NULL;
		}
	}
	
	static function saveValueToCache($valueKey, $valueData) {
		if(extension_loaded('apc') && ini_get('apc.enabled')) {
			return apc_add($valueKey, $valueData);
		} else {
			return false;
		}

	}
	
}

?>