<?php

require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$errores = [];
$nombre = '';
$email = '';

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

    if ($password === '') {
        $errores[] = 'La contraseña es obligatoria.';
    } elseif (mb_strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if (!$errores) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, email, password)
                 VALUES (:nombre, :email, :password)'
            );

            $stmt->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':password' => $hash
            ]);

            header('Location: index.php?ok=creado');
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
    <title>Crear usuario</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main class="contenedor formulario-contenedor">
    <section class="tarjeta">

        <h1>Crear usuario</h1>

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

            <label for="password">Contraseña</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                required
            >

            <button type="submit">Guardar usuario</button>
            <a class="boton-cancelar" href="index.php">Cancelar</a>

        </form>
    </section>
</main>
</body>
</html>
