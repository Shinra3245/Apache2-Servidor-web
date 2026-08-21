<?php

$config = parse_ini_file('/etc/practica/db.env', false, INI_SCANNER_RAW);

if ($config === false) {
    throw new RuntimeException('No fue posible cargar la configuración de la base de datos.');
}

$pdo = new PDO(
    "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
    $config['DB_USER'],
    $config['DB_PASSWORD'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]
);
