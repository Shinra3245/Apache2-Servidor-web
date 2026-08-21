<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
