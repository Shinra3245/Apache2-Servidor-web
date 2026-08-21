<?php

require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID no válido.');
}

if ((int) $id === (int) $_SESSION['usuario_id']) {
    http_response_code(403);
    exit('No puedes eliminar el usuario de la sesión actual.');
}

$stmt = $pdo->prepare(
    'DELETE FROM usuarios
     WHERE id = :id'
);

$stmt->execute([':id' => $id]);

header('Location: index.php?ok=eliminado');
exit;
