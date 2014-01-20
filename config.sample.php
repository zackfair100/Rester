<?php

/** The API Version */
define('API_VERSION', "1.0.0");

/** 
	Route to DB 
	DSN FORMAT:
		SQLite: $dsn = 'sqlite://./path/to/database.sqlite';
		MySQL: $dsn = 'mysql://[user[:pass]@]host[:port]/db/;
		PostgreSQL: $dsn = 'pgsql://[user[:pass]@]host[:port]/db/;
*/
define('DSN','mysql://root:root@localhost/mydb');

/** IP FILTER */
$clients = array
(
);
?>