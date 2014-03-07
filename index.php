<?php

require_once(__DIR__.'/config.php');
require_once(__DIR__.'/include/ArrestDB.php');
require_once(__DIR__.'/include/ApiResponse.php');
require_once(__DIR__.'/include/SwaggerHelper.php');
require_once(__DIR__.'/include/ResterController.php');
require_once(__DIR__.'/include/ApiCacheManager.php');
require_once(__DIR__.'/include/model/RouteCommand.php');

//TODO; Make this smarter
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

$resterController = new ResterController();

if(isset($_GET["cacheClear"])) {
	ApiCacheManager::clear();
	exit(ArrestDB::Reply("Cache Clear!"));
}

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('Rester should not be run from CLI.' . PHP_EOL);
}

$resterController = new ResterController();

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

if(file_exists("fileProcessors.php")) {
	include("fileProcessors.php");
}

if(defined('API_VERSION') && file_exists(__DIR__."/".API_VERSION.".php")) {
	include(__DIR__."/".API_VERSION.".php");
}

//Do the work
$resterController->processRequest($requestMethod);

$result = ApiResponse::errorResponse(405);

exit(ArrestDB::Reply($result));

 