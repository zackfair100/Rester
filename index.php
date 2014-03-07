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


$loginCommand = new RouteCommand("POST", "usuarios", "login", function($params = NULL) {
	error_log("Processing login");
	global $resterController;
	
	$filter["login"]=$params["login"];
	$filter["password"]=md5($params["password"]);
	
	$result = $resterController->getObjectsFromRouteName("usuarios", $filter);

	$resterController->showResult($result);
}, array("login", "password"), "Method to login users");

$resterController->addRouteCommand($loginCommand);

$poisRouteCommand = new RouteCommand("GET", "ruta", "getRutaWithPois", function($params = NULL) {
	error_log("Processing ruta pois");
	
	global $resterController;
	
	$distanceMapping = NULL;
	
	if(isset($params["lat"]) && isset($params["lon"])) {
		$params["distancia"]=1000000;
		$distanceMapping = getDistanciaMapping($resterController, $params);
		unset($params["lat"]);
		unset($params["lon"]);
		unset($params["distancia"]);
	}
	
	$result = $resterController->getObjectsFromRouteName("ruta", $params);
	
	$resultWithChilds = array();
	
	foreach($result as $row) {
		$filter = array("ruta" => $row["id"]);
	
		$childs = $resterController->getObjectsFromRouteName("poi_ruta", $filter);
		
		$pois = array();
		
		foreach($childs as $c) {
			$pois[] = $c["poi"];
		}
		
		$row["pois"]=$pois;
		if(isset($distanceMapping[$row["id"]])) {
			$row["distancia"]=$distanceMapping[$row["id"]];
		}
		$resultWithChilds[]=$row;
	}

	$resterController->showResult($resultWithChilds, true);
	
});

$resterController->addRouteCommand($poisRouteCommand);
 

$routePoisCommand = new RouteCommand("GET", "poi", "getPoiWithRutas", function($params = NULL) {
	error_log("Processing getPoiWithRutas");
	
	global $resterController;
	
	$distanceMapping = NULL;
	
	if(isset($params["lat"]) && isset($params["lon"])) {
		$params["distancia"]=1000000;
		$distanceMapping = getDistanciaMapping($resterController, $params);
		unset($params["lat"]);
		unset($params["lon"]);
		unset($params["distancia"]);
	}
	
	$result = $resterController->getObjectsFromRouteName("poi", $params);
	
	$resultWithChilds = array();
	
	foreach($result as $row) {
		$filter = array("poi" => $row["id"]);
	
		$childs = $resterController->getObjectsFromRouteName("poi_ruta", $filter);
		
		$rutas = array();
		if(isset($childs) && count($childs) > 0) {		
			foreach($childs as $c) {
				if(isset($distanceMapping[$c["ruta"]["id"]])) {
					$c["ruta"]["distancia"]=$distanceMapping[$c["ruta"]["id"]];
				}
			
				$rutas[] = $c["ruta"];
			}
		}
		
		$row["rutas"]=$rutas;
		$resultWithChilds[]=$row;
	}

	$resterController->showResult($resultWithChilds, true);
	
});

$resterController->addRouteCommand($routePoisCommand);

$mailCommand = new RouteCommand("POST", "usuarios", "contacto", function($params = NULL) {

	global $resterController;

	//$destino = "rutas@addin.es";
	$destino = "omanta@moddity.net";
	
	$correo = $_POST["correo"];
	$mensaje = $_POST["mensaje"];
	
	$subject = "Correo desde rutasculturales App";
	if(isset($_POST["usuario"])) {
		$subject.=" De: ".$_POST["usuario"];
	}
	
	
	mail($destino, $subject, $mensaje, "From: ".$correo."\r\n");
	$resterController->showResult(ApiResponse::successResponse());
});

$resterController->addRouteCommand($mailCommand);

$buscarCommand = new RouteCommand("GET", "ruta","buscar", function($params = NULL) {
	
	error_log("PROCESANDO BUSQUEDA");
	
	global $resterController;
	
	//Miramos si han pasado ciudad
	
	if(isset($params["ciudad"])) {
		$ciudades = $resterController->getObjectsFromRouteName("ciudades", array("ciudad" => $params["ciudad"]));
			
		if(count($ciudades) > 0) {
			$cid = array();
			foreach($ciudades as $c) {
				$cid[] = $c["id"];
			}
			$params["ciudad"]=implode(",", $cid);
		}
	}
	
	if(isset($params["tipologia"])) {
		$tipologias = $resterController->getObjectsFromRouteName("tipologia", array("tipo" => $params["tipologia"]));
			
		if(count($tipologias) > 0) {
			$tid = array();
			foreach($tipologias as $t) {
				$tid[] = $t["id"];
			}
			$params["tipologia"]=implode(",", $tid);
		}
	}

	$result = $resterController->getObjectsFromRouteName("ruta", $params, true);
	
	$resultWithChilds = array();
	
	foreach($result as $row) {
		$filter = array("ruta" => $row["id"]);
	
		$childs = $resterController->getObjectsFromRouteName("poi_ruta", $filter);
		
		$pois = array();
		
		foreach($childs as $c) {
			$pois[] = $c["poi"];
		}
		
		$row["pois"]=$pois;
		$resultWithChilds[]=$row;
	}

	$resterController->showResult($resultWithChilds, true);
	
});

$resterController->addRouteCommand($buscarCommand);

$rutasCercaCommand = new RouteCommand("GET", "ruta", "rutasCercanas", function($params = NULL) {

	global $resterController;

	if(!isset($params["lat"])) {
		$resterController->showError(500);
	}
	if(!isset($params["lon"])) {
		$resterController->showError(500);
	}
	if(!isset($params["distancia"])) {
		$resterController->showError(500);
	}
	
	$query = "SELECT id, ( 6371 * acos( cos( radians(".$params["lat"].") ) * cos( radians( `latitud` ) ) 
* cos( radians( `longitud` ) - radians(".$params["lon"].") ) + sin( radians(".$params["lat"].") ) * sin(radians(latitud)) ) ) AS distance,ruta 
FROM ruta_path 
GROUP BY ruta 
HAVING distance < ".$params["distancia"]."
ORDER BY distance
LIMIT 0,1000";

	$res = $resterController->query($query);
	
	foreach($res as $r) {
		$distance[$r["ruta"]]=$r["distance"];
	}

	
	$result = $resterController->getObjectByID("ruta",implode(",",array_keys($distance)));
	
	
	$resultWithChilds = array();
	
	foreach($result as $row) {
		$filter = array("ruta" => $row["id"]);
	
		$childs = $resterController->getObjectsFromRouteName("poi_ruta", $filter);
		
		$pois = array();
		
		foreach($childs as $c) {
			$pois[] = $c["poi"];
		}
		
		$row["pois"]=$pois;
		$row["distancia"]=$distance[$row["id"]];
		$resultWithChilds[]=$row;
	}

	$resterController->showResult($resultWithChilds, true);


});

$resterController->addRouteCommand($rutasCercaCommand);

function getDistanciaMapping($resterController, $params) {
	$query = "SELECT id, ( 6371 * acos( cos( radians(".$params["lat"].") ) * cos( radians( `latitud` ) ) 
* cos( radians( `longitud` ) - radians(".$params["lon"].") ) + sin( radians(".$params["lat"].") ) * sin(radians(latitud)) ) ) AS distance,ruta 
FROM ruta_path 
GROUP BY ruta 
HAVING distance < ".$params["distancia"]."
ORDER BY distance
LIMIT 0,1000";

	$res = $resterController->query($query);
	
	foreach($res as $r) {
		$distance[$r["ruta"]]=$r["distance"];
	}
	
	return $distance;

}

//Do the work
$resterController->processRequest($requestMethod);

$result = ApiResponse::errorResponse(405);

exit(ArrestDB::Reply($result));

 