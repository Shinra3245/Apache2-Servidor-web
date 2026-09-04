<?php

require_once __DIR__ . '/AppConfig.php';

class Database
{
    private ?PDO $conn = null;

    public function getConnection(): PDO
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $config = AppConfig::all();

        try {
            $port = (int) ($config['DB_PORT'] ?? 3306);

            $this->conn = new PDO(
                "mysql:host={$config['DB_HOST']};port={$port};dbname={$config['DB_NAME']};charset=utf8mb4",
                $config['DB_USER'],
                $config['DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Error de conexión a la base de datos.'
            );
        }

        return $this->conn;
    }
}
