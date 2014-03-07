<?php

/**
* Sample custom login command
*/
$loginCommand = new RouteCommand("POST", "users", "login", function($params = NULL) {
	
	global $resterController;
	
	$filter["login"]=$params["login"];
	$filter["password"]=md5($params["password"]);
	
	$result = $resterController->getObjectsFromRouteName("usuarios", $filter);

	$resterController->showResult($result);
	
}, array("login", "password"), "Method to login users");

$resterController->addRouteCommand($loginCommand);

?>