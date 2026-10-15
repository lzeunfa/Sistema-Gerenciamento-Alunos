<?php
//incluir no topo de toda pagina que exige login: require_once(caminho ate trava.php)
require_once(__DIR__ . '/BACKEND/SEGURANCA/seguranca.php');

//endereco do index.php a partir da raiz do site, funciona de qualquer pasta que incluir este arquivo
$raizProjeto = str_replace('\\', '/', substr(__DIR__, strlen(realpath($_SERVER['DOCUMENT_ROOT']))));
$paginaLogin = $raizProjeto . '/index.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: $paginaLogin");
    exit();
}


$time_limit = 600; //10 minutos sem clicar derruba a sessao
$limite_absoluto = 8 * 3600; //mesmo usando sem parar, a sessao dura no maximo 8 horas

$inativa = isset($_SESSION['last_click']) && time() - $_SESSION['last_click'] > $time_limit;
$vencida = isset($_SESSION['login_time']) && time() - $_SESSION['login_time'] > $limite_absoluto;

if ($inativa || $vencida) {
    registrarAuditoria('sessao_expirada', $inativa ? 'inatividade' : 'limite de 8h');
    session_unset();
    session_destroy();
    header("Location: $paginaLogin?erro=sessao_expirada");
    exit();
}

$_SESSION['last_click'] = time();
?>
