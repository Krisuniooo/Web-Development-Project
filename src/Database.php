<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/** The PostgreSQL connection, with its settings from the environment (.env through docker-compose.yaml). */
final class Database
{
    private string $username;
    private string $password;
    private string $host;
    private int $port;
    private string $database;

    public function __construct()
    {
        $this->username = $this->env('DB_USERNAME');
        $this->password = $this->env('DB_PASSWORD');
        $this->host = $this->env('DB_HOST');
        $this->port = (int) $this->env('DB_PORT');
        $this->database = $this->env('DB_NAME');
    }

    public function connect(): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=prefer',
            $this->host,
            $this->port,
            $this->database,
        );

        try {
            return new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            // PostgreSQL's reason, such as a wrong password or a stopped server, for the log; it never holds the password
            throw new RuntimeException('Cannot connect to the database: ' . strtok($exception->getMessage(), "\n"), 0, $exception);
        }
    }

    private function env(string $name): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            throw new RuntimeException("$name is missing in .env");
        }

        return $value;
    }
}
