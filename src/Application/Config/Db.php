<?php

// Database configuration, See PDO documentation for connection string: http://php.net/manual/en/pdo.construct.php for more information

// PostgreSQL: Default
$config['db']['pdo']['default'] = [
  'string'              => "pgsql:host={$_ENV['DB_DEFAULT_HOSTNAME']};port={$_ENV['DB_DEFAULT_PORT']};dbname={$_ENV['DB_DEFAULT_DATABASE']}",
  'username'            => $_ENV['DB_DEFAULT_USERNAME'],
  'password'            => $_ENV['DB_DEFAULT_PASSWORD'],
  'charset'             => 'UTF8',
  'persistent'          => false,
  'wrap_column'         => '"',
  'fetch_mode_objects'  => false,
  'debug'               => $config['debug']
];
