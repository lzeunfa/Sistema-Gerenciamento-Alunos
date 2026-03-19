<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}


$time_limit = 600; 

if (isset($_SESSION['last_click'])) {
    $inative_sec = time() - $_SESSION['last_click'];

    if ($inative_sec > $time_limit) {
        session_unset();
        session_destroy();
        header("Location: index.php?erro=sessao_expirada");
        exit();
    }
}

$_SESSION['last_click'] = time();
?>