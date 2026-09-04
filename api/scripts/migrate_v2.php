<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$sqlFile = __DIR__ . '/../database_v2.sql';
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    fwrite(STDERR, "No fue posible leer database_v2.sql.\n");
    exit(1);
}

$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
$db = (new Database())->getConnection();

foreach ($statements as $statement) {
    $statement = trim($statement);

    if ($statement !== '') {
        $db->exec($statement);
    }
}

echo "Migración V2 completada.\n";
echo "Tablas verificadas: api_users, api_tokens.\n";
