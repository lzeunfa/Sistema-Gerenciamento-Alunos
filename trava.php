<?php
//incluir no topo de toda pagina que exige login: require_once(caminho ate trava.php)
session_start();

//endereco do index.php a partir da raiz do site, funciona de qualquer pasta que incluir este arquivo
$raizProjeto = str_replace('\\', '/', substr(__DIR__, strlen(realpath($_SERVER['DOCUMENT_ROOT']))));
$paginaLogin = $raizProjeto . '/index.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: $paginaLogin");
    exit();
}


$time_limit = 600; //10 minutos sem clicar derruba a sessao

if (isset($_SESSION['last_click'])) {
    $inative_sec = time() - $_SESSION['last_click'];

    if ($inative_sec > $time_limit) {
        session_unset();
        session_destroy();
        header("Location: $paginaLogin?erro=sessao_expirada");
        exit();
    }
}

$_SESSION['last_click'] = time();
?>
