<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

if ($argc !== 4) {
    fwrite(
        STDERR,
        "Uso: php create_api_user.php <username> <email> <password>\n"
    );
    exit(1);
}

[, $username, $email, $password] = $argv;
$username = trim($username);
$email = trim($email);

if ($username === '' || mb_strlen($username) > 100) {
    fwrite(STDERR, "El username no es válido.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El email no es válido.\n");
    exit(1);
}

if (mb_strlen($password) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}

$db = (new Database())->getConnection();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare(
    'INSERT INTO api_users (username, email, password_hash, status)
     VALUES (:username, :email, :password_hash, \'ACTIVE\')
     ON DUPLICATE KEY UPDATE
        email = VALUES(email),
        password_hash = VALUES(password_hash),
        status = \'ACTIVE\''
);
$stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password_hash' => $hash
]);

echo "Usuario API listo: {$username}\n";
echo "Estado: ACTIVE\n";
echo "La contraseña se almacenó mediante password_hash().\n";
