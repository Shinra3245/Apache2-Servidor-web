<?php

class Database
{
    public $conn;

    public function getConnection()
    {
        $this->conn = null;

        // Configuración local Fedora
        $localEnv = '/etc/practica/db.env';

        // Configuración del VPS ubicada en la raíz del proyecto
        $serverEnv = dirname(__DIR__, 2) . '/.env';

        if (is_readable($localEnv)) {
            $config = parse_ini_file(
                $localEnv,
                false,
                INI_SCANNER_RAW
            );
        } elseif (is_readable($serverEnv)) {
            $config = parse_ini_file(
                $serverEnv,
                false,
                INI_SCANNER_RAW
            );
        } else {
            throw new RuntimeException(
                'No se encontró la configuración de la base de datos.'
            );
        }

        try {
            $this->conn = new PDO(
                "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
                $config['DB_USER'],
                $config['DB_PASSWORD']
            );

            $this->conn->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $this->conn->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

        } catch (PDOException $e) {
            throw new RuntimeException(
                'Error de conexión a la base de datos.'
            );
        }

        return $this->conn;
    }
}