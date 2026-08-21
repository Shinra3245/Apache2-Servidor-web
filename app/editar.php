<?php

require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID de usuario no válido.');
}

$stmt = $pdo->prepare(
    'SELECT id, nombre, email
     FROM usuarios
     WHERE id = :id'
);

$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$errores = [];
$nombre = $usuario['nombre'];
$email = $usuario['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre debe tener entre 2 y 100 caracteres.';
    }

    if ($email === '') {
        $errores[] = 'El correo electrónico es obligatorio.';
    } elseif (mb_strlen($email) > 150) {
        $errores[] = 'El correo electrónico no puede superar 150 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }

    if ($password !== '' && mb_strlen($password) < 8) {
        $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    }

    if (!$errores) {
        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = :nombre,
                         email = :email,
                         password = :password
                     WHERE id = :id'
                );

                $stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':password' => $hash,
                    ':id' => $id
                ]);

            } else {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = :nombre,
                         email = :email
                     WHERE id = :id'
                );

                $stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':id' => $id
                ]);
            }

            header('Location: index.php?ok=editado');
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errores[] = 'El correo electrónico ya se encuentra registrado.';
            } else {
                throw $e;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar usuario</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main class="contenedor formulario-contenedor">
    <section class="tarjeta">

        <h1>Editar usuario</h1>

        <?php if ($errores): ?>
            <div class="mensaje error">
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">

            <label for="nombre">Nombre</label>
            <input
                type="text"
                id="nombre"
                name="nombre"
                maxlength="100"
                required
                value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="email">Correo electrónico</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                required
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="password">Nueva contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
            >

            <p class="ayuda">
                Deja la contraseña vacía para conservar la actual.
            </p>

            <button type="submit">Guardar cambios</button>
            <a class="boton-cancelar" href="index.php">Cancelar</a>

        </form>

    </section>
</main>
</body>
</html>
