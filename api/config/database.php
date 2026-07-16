<?php
/**
 * DUHN FRAGRANCES — Database Connection (PDO Singleton)
 * Configure credentials via environment variables or edit directly.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host   = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'duhn_db';
            $user   = $_ENV['DB_USER'] ?? 'duhn_user';
            $pass   = $_ENV['DB_PASS'] ?? '';
            $port   = $_ENV['DB_PORT'] ?? '3306';

            try {
                self::$instance = new PDO(
                    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    ]
                );
            } catch (PDOException $e) {
                // Never expose DB credentials in error messages
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'DB_CONNECTION_FAILED', 'message' => 'Database connection failed.']);
                exit;
            }
        }

        return self::$instance;
    }
}
