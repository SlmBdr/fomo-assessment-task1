<?php

namespace App;

use PDO;
use Exception;

class Database {
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO connection
     * @return PDO
     * @throws Exception
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Load environment variables if helper function exists, or rely on getenv
            $dbUrl = getenv('DATABASE_URL') ?: $_ENV['DATABASE_URL'] ?? null;

            if (!$dbUrl) {
                // If .env isn't loaded yet or variable is empty
                self::loadEnv();
                $dbUrl = getenv('DATABASE_URL') ?: $_ENV['DATABASE_URL'] ?? null;
            }

            if (!$dbUrl) {
                throw new Exception("DATABASE_URL is not defined in the environment or .env file. Please check your config.");
            }

            $parsed = parse_url($dbUrl);
            if ($parsed === false || !isset($parsed['host']) || !isset($parsed['path'])) {
                throw new Exception("Invalid DATABASE_URL format. Expected: postgresql://username:password@hostname:port/database_name");
            }

            $host = $parsed['host'];
            $port = $parsed['port'] ?? 5432;
            $dbName = ltrim($parsed['path'], '/');
            $user = $parsed['user'] ?? '';
            $pass = $parsed['pass'] ?? '';

            $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
            
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (\PDOException $e) {
                throw new Exception("Database Connection Error: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    /**
     * Helper method to parse .env file if it exists, without needing external libraries
     */
    public static function loadEnv(): void {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (preg_match('/^"(.*)"$/', $value, $matches)) {
                        $value = $matches[1];
                    } elseif (preg_match('/^\'(.*)\'$/', $value, $matches)) {
                        $value = $matches[1];
                    }
                    
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}
