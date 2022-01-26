<?php

// Database configuration, See PDO documentation for connection string: http://php.net/manual/en/pdo.construct.php for more information

// PostgreSQL: Default
$config['services']['twitch'] = [
  'client_id' => $_ENV['TWITCH_CLIENT_ID'],
  'secret' => $_ENV['TWITCH_SECRET'],
  'scopes' => $_ENV['TWITCH_SCOPES']
];
