<?php

include_once("config.php");
require_once(__DIR__.'/model/Route.php');
require_once(__DIR__.'/model/RouteField.php');
require_once(__DIR__.'/model/RouteRelation.php');

class ArrestDB
{
	
	static $db = null;
	
	function ArrestDB() {
	
		$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
					\PDO::ATTR_PERSISTENT => true
				);
	
		$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
	
		$this::$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', "mysql", DBHOST, 3306, DBNAME), DBUSER, DBPASSWORD, $options);
	}

	public function Query($query = null)
	{
		static $result = array();

		try
		{
			if (isset($this::$db, $query) === true)
			{
				if (strncasecmp($this::$db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'mysql', 5) === 0)
				{
					$query = strtr($query, '"', '`');
				}

				if (empty($result[$hash = crc32($query)]) === true)
				{
					$result[$hash] = $this::$db->prepare($query);
				}

				$data = array_slice(func_get_args(), 1);
				
				if (count($data, COUNT_RECURSIVE) > count($data))
				{
					$data = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data)), false);
				}
						
				if ($result[$hash]->execute($data) === true)
				{
					$sequence = null;

					if ((strncmp($this::$db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'pgsql', 5) === 0) && (sscanf($query, 'INSERT INTO %s', $sequence) > 0))
					{
						$sequence = sprintf('%s_id_seq', trim($sequence, '"'));
					}
					
					switch (strtoupper(strstr($query, ' ', true)))
					{
						case 'INSERT':
						case 'REPLACE':
							return $this::$db->lastInsertId($sequence);

						case 'UPDATE':
						case 'DELETE':
							return $result[$hash]->rowCount();

						case 'SELECT':
						case 'EXPLAIN':
						case 'PRAGMA':
						case 'SHOW':
						case 'DESCRIBE':
							return $result[$hash]->fetchAll();
						/*case 'DESCRIBE':
							return $result[$hash]->fetchAll(PDO::FETCH_COLUMN);*/
					}
					return true;
				}
				echo $query. "KO";

				return false;
			}

			/*else if (isset($query) === true)
			{
				$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
				);
				
				$dsn = DSN;

				if (preg_match('~^sqlite://([[:print:]]++)$~i', $query, $dsn) > 0)
				{
					$options += array
					(
						\PDO::ATTR_TIMEOUT => 3,
					);
					
					$this::$db = new \PDO(sprintf('sqlite:%s', $dsn[1]), null, null, $options);
					$pragmas = array
					(
						'automatic_index' => 'ON',
						'cache_size' => '8192',
						'foreign_keys' => 'ON',
						'journal_size_limit' => '67110000',
						'locking_mode' => 'NORMAL',
						'page_size' => '4096',
						'recursive_triggers' => 'ON',
						'secure_delete' => 'ON',
						'synchronous' => 'NORMAL',
						'temp_store' => 'MEMORY',
						'journal_mode' => 'WAL',
						'wal_autocheckpoint' => '4096',
					);

					if (strncasecmp(PHP_OS, 'WIN', 3) !== 0)
					{
						$memory = 131072;

						if (($page = intval(shell_exec('getconf PAGESIZE'))) > 0)
						{
							$pragmas['page_size'] = $page;
						}

						if (is_readable('/proc/meminfo') === true)
						{
							if (is_resource($handle = fopen('/proc/meminfo', 'rb')) === true)
							{
								while (($line = fgets($handle, 1024)) !== false)
								{
									if (sscanf($line, 'MemTotal: %d kB', $memory) == 1)
									{
										$memory = round($memory / 131072) * 131072; break;
									}
								}

								fclose($handle);
							}
						}

						$pragmas['cache_size'] = intval($memory * 0.25 / ($pragmas['page_size'] / 1024));
						$pragmas['wal_autocheckpoint'] = $pragmas['cache_size'] / 2;
					}

					foreach ($pragmas as $key => $value)
					{
						$this::$db->exec(sprintf('PRAGMA %s=%s;', $key, $value));
					}
				}

				else if (preg_match('~^(mysql|pgsql)://(?:(.+?)(?::(.+?))?@)?([^/:@]++)(?::(\d++))?/(\w++)/?$~i', $query, $dsn) > 0)
				{
					if (strncasecmp($query, 'mysql', 5) === 0)
					{
						$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
					}

					$this::$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', "mysql", $dsn[4], $dsn[5], $dsn[6]), $dsn[2], $dsn[3], $options);
				}
			}*/
		}

		catch (\Exception $e)
		{
			exit(ArrestDB::Reply(ApiResponse::errorResponseWithMessage(503, $e->getMessage()." ".$query)));
			return false;
		}

		return (isset($this::$db) === true) ? $this::$db : false;
	}

	public static function Reply($data)
	{
		$bitmask = 0;
		$options = array('UNESCAPED_SLASHES', 'UNESCAPED_UNICODE');

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}

		return $result;
	}

	public static function Serve($on = null, $callback = null)
	{


		/*if (isset($_SERVER['REQUEST_METHOD']) !== true)
		{
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}*/

		parse_str($_SERVER['QUERY_STRING'], $parameters);

		$root = preg_replace('~/++~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])) . '/');
		
		//No parameters, we are requesting the root
		if($root === "/") {
			
		}
		
		$requestQuery = explode("?", $root);
		
		error_log($_SERVER["PHP_SELF"]." - ".$_SERVER['SCRIPT_NAME']." - ".$_SERVER['QUERY_STRING']);
		
			
		if($route === "/" && $root === "/") {
			error_log("PROCESSING ROOT");
			return exit(call_user_func($callback));
		} else if($route === "/") {
			return false;
		} else {
			error_log("PROCESSING ".$route." - ".$root);
		}
		
		$replace = str_replace(array('#any', '#num'), array('[^/]++', '[0-9]++'), $route) . '~i';
	
		//echo '~^' . str_replace(array('#any', '#num'), array('[^/]++', '[0-9]++'), $route) . '~i';
			
		if ($num_match = preg_match('~^' . str_replace(array('#any', '#num'), array('[^/]++', '[0-9]++'), $route) . '~i', $root, $parts) > 0) {	
			error_log("PROCESSING CALLBACK ".$root." - ".$route);
			return (empty($callback) === true) ? true : exit(call_user_func_array($callback, array_slice($parts, 1)));
		} else {
			error_log("NO MATCH ".$root." - ".$route);
		}
		
		return false;
	}
	
	public function getRoot() {
		$root = preg_replace('~/++~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])) . '/');
		return $root;
	}
	
	function getRelations() {
		$relations = $this->Query("select * from information_schema.key_column_usage where table_schema = '".DBNAME."' and referenced_table_name is not null");
		return RouteRelation::parseRelationsFromMySQL($relations);
	}
	
	function getRouteFields($route, $currentRelations = null) {
		$result = ArrestDB::Query("DESCRIBE ".$route->routeName);
		$routeFields = array();
		
		foreach($result as $f) {
			$routeField = new RouteField();
			
			$routeField->fieldName = $f["Field"];
			$routeField->fieldType = RouteField::getTypeFromMySQL($f["Type"]);
			$routeField->isRequired = ($f["Null"] == "NO") ? true : false;
			$routeField->defaultValue = $f["Default"];
			$routeField->description = $routeField->fieldName." field ".$routeField->fieldType;
			
			if(isset($currentRelations)) {
				foreach($currentRelations as $r) {
					if($r->field == $routeField->fieldName) {
						$routeField->setRelation($r);
					}
				}
			}
		
			$routeFields[]=$routeField;
		}
		
		return $routeFields;
	}
	
	function getRoutes() {
		$routes = array();
		
		$relations = $this->getRelations();
		
		$result = ArrestDB::Query("SHOW TABLES");
	
		if ($result === false) {
			exit(ApiResponse::errorResponse(404));
		} else if (empty($result) === true) {
			exit(ApiResponse::errorResponse(204));
		} else {
			foreach($result as $k => $v) {
				$route = reset($v);
				
				$route = new Route();
				$route->routeName = reset($v);
				if(isset($relations[$route->routeName]))
					$route->routeFields = $this->getRouteFields($route, $relations[$route->routeName]);
				else
					$route->routeFields = $this->getRouteFields($route);
				
				$routes[$route->routeName]=$route;
			}
		}

		return $routes;
	}
	
}

?>
