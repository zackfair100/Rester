<?php

require_once('config.php');
require_once('ArrestDB.php');
require_once('ApiResponse.php');
require_once('SwaggerHelper.php');
require_once('ArrestDBController.php');

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* ArrestDB 1.7.1 (github.com/alixaxel/ArrestDB/)
* Copyright (c) 2014 Alix Axel <alix.axel@gmail.com>
**/

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('ArrestDB should not be run from CLI.' . PHP_EOL);
}

$restController = new ArrestDBController();

//Check the client restrictions
$restController->checkClientRestriction();

//Test the sql server
$restController->checkConnectionStatus();


if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

else if (array_key_exists('HTTP_X_HTTP_METHOD_OVERRIDE', $_SERVER) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
}

ArrestDB::Serve('GET', '/(#any)/(#any)/(#any)', function ($table, $id, $data)
{

	//echo $table." - ".$id." - ".$data;
	$query = array
	(
		sprintf('SELECT * FROM "%s"', $table),
		sprintf('WHERE "%s" %s ?', $id, (ctype_digit($data) === true) ? '=' : 'LIKE'),
	);

	if (isset($_GET['by']) === true)
	{
		if (isset($_GET['order']) !== true)
		{
			$_GET['order'] = 'ASC';
		}

		$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
	}

	if (isset($_GET['limit']) === true)
	{
		$query[] = sprintf('LIMIT %u', $_GET['limit']);

		if (isset($_GET['offset']) === true)
		{
			$query[] = sprintf('OFFSET %u', $_GET['offset']);
		}
	}

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $data);

	if ($result === false)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
			),
		);
	}

	else if (empty($result) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('GET', '/(#any)/(#any)?', function ($table, $id = null)
{
		if (isset($id) === true)
		{
			$query = array
			(
				sprintf('SELECT * FROM "%s"', $table),
				);
	
				if($id != "list")
					$query[] = sprintf('WHERE "%s" = ? LIMIT 1', 'id');
	
	/*else
	{*/
		if (isset($_GET['by']) === true)
		{
			if (isset($_GET['order']) !== true)
			{
				$_GET['order'] = 'ASC';
			}

			$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
		}

		if (isset($_GET['limit']) === true)
		{
			$query[] = sprintf('LIMIT %u', $_GET['limit']);

			if (isset($_GET['offset']) === true)
			{
				$query[] = sprintf('OFFSET %u', $_GET['offset']);
			}
		}
	//}

	$query = sprintf('%s;', implode(' ', $query));

	$result = (isset($id) === true && $id != "list") ? ArrestDB::Query($query, $id) : ArrestDB::Query($query);

	if ($result === false)
	{
		$result = ApiResponse::errorResponse(404);
	}

	else if (empty($result) === true)
	{
		$result = ApiResponse::errorResponse(204);
	}

	else if (isset($id) === true)
	{
		$result = array_shift($result);
	}

	return ArrestDB::Reply($result);
	} else {
		return ArrestDB::Reply(SwaggerHelper::getDocFromRoute($table, ArrestDBController::getAvailableRoutes()));
	}
});

ArrestDB::Serve('DELETE', '/(#any)/(#num)', function ($table, $id)
{
	$query = array
	(
		sprintf('DELETE FROM "%s" WHERE "%s" = ?', $table, 'id'),
	);

	$query = sprintf('%s;', implode(' ', $query));
	$result = ArrestDB::Query($query, $id);

	if ($result === false)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
			),
		);
	}

	else if (empty($result) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	else
	{
		$result = array
		(
			'success' => array
			(
				'code' => 200,
				'status' => 'OK',
			),
		);
	}

	return ArrestDB::Reply($result);
});

if (in_array($http = strtoupper($_SERVER['REQUEST_METHOD']), array('POST', 'PUT')) === true)
{
	if (preg_match('~^\x78[\x01\x5E\x9C\xDA]~', $data = file_get_contents('php://input')) > 0)
	{
		$data = gzuncompress($data);
	}

	if ((array_key_exists('CONTENT_TYPE', $_SERVER) === true) && (empty($data) !== true))
	{
		if (strcasecmp($_SERVER['CONTENT_TYPE'], 'application/json') === 0)
		{
			$GLOBALS['_' . $http] = json_decode($data, true);
		}

		else if ((strcasecmp($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === 0) && (strcasecmp($_SERVER['REQUEST_METHOD'], 'PUT') === 0))
		{
			parse_str($data, $GLOBALS['_' . $http]);
		}
	}

	if ((isset($GLOBALS['_' . $http]) !== true) || (is_array($GLOBALS['_' . $http]) !== true))
	{
		$GLOBALS['_' . $http] = array();
	}

	unset($data);
}

ArrestDB::Serve('POST', '/(#any)', function ($table)
{
	if (empty($_POST) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	else if (is_array($_POST) === true)
	{
		$queries = array();

		if (count($_POST) == count($_POST, COUNT_RECURSIVE))
		{
			$_POST = array($_POST);
		}

		foreach ($_POST as $row)
		{
			$data = array();

			foreach ($row as $key => $value)
			{
				$data[sprintf('"%s"', $key)] = '?';
			}

			$query = array
			(
				sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table, implode(', ', array_keys($data)), implode(', ', $data)),
			);

			$queries[] = array
			(
				sprintf('%s;', implode(' ', $query)),
				$data,
			);
		}

		if (count($queries) > 1)
		{
			ArrestDB::Query()->beginTransaction();

			while (is_null($query = array_shift($queries)) !== true)
			{
				if (($result = ArrestDB::Query($query[0], $query[1])) === false)
				{
					ArrestDB::Query()->rollBack(); break;
				}
			}

			if (($result !== false) && (ArrestDB::Query()->inTransaction() === true))
			{
				$result = ArrestDB::Query()->commit();
			}
		}

		else if (is_null($query = array_shift($queries)) !== true)
		{
			$result = ArrestDB::Query($query[0], $query[1]);
		}

		if ($result === false)
		{
			$result = array
			(
				'error' => array
				(
					'code' => 409,
					'status' => 'Conflict',
				),
			);
		}

		else
		{
			$result = array
			(
				'success' => array
				(
					'code' => 201,
					'status' => 'Created',
				),
			);
		}
	}

	return ArrestDB::Reply($result);
});

ArrestDB::Serve('PUT', '/(#any)/(#num)', function ($table, $id)
{
	if (empty($GLOBALS['_PUT']) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	else if (is_array($GLOBALS['_PUT']) === true)
	{
		$data = array();

		foreach ($GLOBALS['_PUT'] as $key => $value)
		{
			$data[$key] = sprintf('"%s" = ?', $key);
		}

		$query = array
		(
			sprintf('UPDATE "%s" SET %s WHERE "%s" = ?', $table, implode(', ', $data), 'id'),
		);

		$query = sprintf('%s;', implode(' ', $query));
		$result = ArrestDB::Query($query, $GLOBALS['_PUT']);

		if ($result === false)
		{
			$result = array
			(
				'error' => array
				(
					'code' => 409,
					'status' => 'Conflict',
				),
			);
		}

		else
		{
			$result = array
			(
				'success' => array
				(
					'code' => 200,
					'status' => 'OK',
				),
			);
		}
	}

	return ArrestDB::Reply($result);
});

//Get the api root
ArrestDB::Serve('GET', NULL, function () {
	
	/*$result = ArrestDB::Query("SHOW TABLES");
	
	if ($result === false)
	{
		$result = ApiResponse::errorResponse(404);
	}

	else if (empty($result) === true)
	{
		$result = ApiResponse::errorResponse(204);
	}

	else 
	{
		$routes = array();
	
		foreach($result as $k => $v) {
			$routes[] = reset($v);	
		}
		
		$result = SwaggerHelper::routeResume($routes);
	}*/

	$routes = ArrestDBController::getAvailableRoutes();
	$result = SwaggerHelper::routeResume($routes);
	return ArrestDB::Reply($result);
});

$result = ApiResponse::errorResponse(400);

exit(ArrestDB::Reply($result));

