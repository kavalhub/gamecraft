<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'mysql';
$useTestConnection = $connection === 'mysql_testing';

$database = $useTestConnection
    ? ($_ENV['DB_TEST_DATABASE'] ?? getenv('DB_TEST_DATABASE') ?: 'game_db_testing')
    : ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'game_db_testing');

if ($database === ':memory:') {
    return;
}

$host = $useTestConnection
    ? ($_ENV['DB_TEST_HOST'] ?? getenv('DB_TEST_HOST') ?: 'mysql_game_test')
    : ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
$port = $useTestConnection
    ? ($_ENV['DB_TEST_PORT'] ?? getenv('DB_TEST_PORT') ?: '3306')
    : ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
$user = $useTestConnection
    ? ($_ENV['DB_TEST_USERNAME'] ?? getenv('DB_TEST_USERNAME') ?: 'game_user')
    : ($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root');
$password = $useTestConnection
    ? ($_ENV['DB_TEST_PASSWORD'] ?? getenv('DB_TEST_PASSWORD') ?: '')
    : ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');

$dsn = sprintf('mysql:host=%s;port=%s', $host, $port);

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    str_replace('`', '``', $database),
));
