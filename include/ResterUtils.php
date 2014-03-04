<?php

class ResterUtils {
	
	/**
	* Function to check if array is number indexed 0,1,2,3...
	*/
	static function isIndexed($arr) {
		return array_values($arr) === $arr;
	}
	
	static function cleanArray($arr, $keys) {
		$ret = array();
		foreach($keys as $k) {
			if(isset($arr[$k]))
				$ret[$k]=$arr[$k];
		}
		
		return $ret;
	}

	static function Log($message) {
		if(defined('LOG_VERBOSE')) {
			error_log($message);
		}
	}
	
}

?>