<?php

class ResterUtils {
	
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
	
}

?>