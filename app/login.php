<?php

session_start();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, nombre, email, password
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];

            header('Location: index.php');
            exit;
        }

        $error = 'Correo electrónico o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="contenedor login-contenedor">
        <section class="tarjeta">
            <h1>Iniciar sesión</h1>
            <p class="subtitulo">Aplicación de práctica Apache, PHP y MariaDB</p>

            <?php if ($error !== ''): ?>
                <div class="mensaje error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <label for="email">Correo electrónico</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    required
                    autocomplete="username"
                >

                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >

                <button type="submit">Ingresar</button>
            </form>
        </section>
    </main>
</body>
</html>
