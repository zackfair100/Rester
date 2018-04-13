#Rester 

Rester is a fork of ArrestDB as codebase with many improvements and new features.
	
	ArrestDB: https://github.com/alixaxel/ArrestDB
	
##Requeriments

- PHP 5.4+ & PDO
- MySQL

##Features

- Create an API in 5 minutes
- Auto-generation of swagger-ui documentation on http://api.example.com/doc/
- MySQL Relation support
- File upload support
- Custom API functions
- Filters


##Installation

Edit `config.php`, here are some examples:

```php
/** The API Version */
define('API_VERSION', "1.0.0");

/** Database credentials */
define('DBHOST', 'localhost');
define('DBNAME', 'mydb');
define('DBUSER', 'dbuser');
define('DBPASSWORD', 'dbpassword');

/** Enable logging on error.log */
//define('LOG_VERBOSE', true);

/** Path where uploads */
define('FILE_UPLOAD_PATH', 'uploads');

```

##API Design

The actual API design is very straightforward and follows the design patterns of the majority of APIs.

	(C)reate > POST   /table
	(R)ead   > GET    /table[/id]
	(U)pdate > PUT    /table/id
	(U)pdate > POST   /table/id
	(D)elete > DELETE /table/id

To put this into practice below are some example of how you would use the Rester API:

	# Get all rows from the "customers" table
	GET http://api.example.com/customers/

	# Get a single row from the "customers" table (where "123" is the ID)
	GET http://api.example.com/customers/123

	# Get 50 rows from the "customers" table
	GET http://api.example.com/customers/?limit=50

	# Get 50 rows from the "customers" table ordered by the "date" field
	GET http://api.example.com/customers/?limit=50&by=date&order=desc
	
	# Get all the customers named LIKE Tom; (Tom, Tomato, Tommy...)
	GET http://api.example.com/customers/?name[in]=Tom

	# Create a new row in the "customers" table where the POST data corresponds to the database fields
	POST http://api.example.com/customers

	# Update customer "123" in the "customers" table where the PUT data corresponds to the database fields
	PUT http://api.example.com/customers/123
	POST http://api.example.com/customers/123

	# Delete customer "123" from the "customers" table
	DELETE http://api.example.com/customers/123

Please note that `GET` calls accept the following query string variables:

- `by` (column to order by)
  - `order` (order direction: `ASC` or `DESC`)
- `limit` (`LIMIT x` SQL clause)
  - `offset` (`OFFSET x` SQL clause)
- `parameter[in]` (LIKE search)
- `parameter[gt]` (greater than search)
- `parameter[lt]` (less than search)
- `parameter[ge]` (greater or equals search)
- `parameter[le]` (less or equals search)

##Changelog

- **beta** ~~Reaching beta stage~~

##Credits
Rester is a nearly complete rewrite of [ArrestDB](ArrestDB: https://github.com/alixaxel/ArrestDB) with many additional features.
ArrestDB is a complete rewrite of [Arrest-MySQL](https://github.com/gilbitron/Arrest-MySQL) with several optimizations and additional features.

##License (MIT)

Copyright (c) 2014 mOddity Mobile S.L. (http://www.moddity.net)
