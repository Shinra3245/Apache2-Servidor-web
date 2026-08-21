<?php

require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';

$stmt = $pdo->prepare(
    'SELECT id, nombre, email, fecha_registro
     FROM usuarios
     ORDER BY id'
);
$stmt->execute();

$usuarios = $stmt->fetchAll();

$mensajes = [
    'creado' => 'Usuario creado correctamente.',
    'editado' => 'Usuario actualizado correctamente.',
    'eliminado' => 'Usuario eliminado correctamente.'
];

$mensaje = $mensajes[$_GET['ok'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración de usuarios</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main class="contenedor">

    <div class="encabezado">
        <div>
            <h1>Administración de usuarios</h1>
            <p>
                Sesión iniciada como
                <strong><?= htmlspecialchars($_SESSION['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>

        <a class="boton-secundario" href="logout.php">Cerrar sesión</a>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje exito">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <a class="boton" href="crear.php">Nuevo usuario</a>

    <div class="tabla-contenedor">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo electrónico</th>
                <th>Fecha de registro</th>
                <th>Acciones</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= (int) $usuario['id'] ?></td>

                    <td>
                        <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($usuario['fecha_registro'], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <td class="acciones">
                        <a href="editar.php?id=<?= (int) $usuario['id'] ?>">
                            Editar
                        </a>

                        <?php if ((int) $usuario['id'] !== (int) $_SESSION['usuario_id']): ?>
                            <form method="post"
                                  action="eliminar.php"
                                  onsubmit="return confirm('¿Eliminar este usuario?');">

                                <input type="hidden"
                                       name="id"
                                       value="<?= (int) $usuario['id'] ?>">

                                <button class="boton-enlace peligro" type="submit">
                                    Eliminar
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="usuario-actual">Sesión actual</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</main>
</body>
</html>
