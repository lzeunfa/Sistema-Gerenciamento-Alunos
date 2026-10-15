<?php
session_start();
require_once(__DIR__ . '/../CONNECTION/config.php');

$userName = trim($_POST['inputUser'] ?? '');
$userPass = $_POST['inputUserPass'] ?? '';

if ($userName === '' || $userPass === '') {
    $_SESSION['login_error'] = "Preencha usuário e senha!";
    header('Location: ../../index.php');
    exit();
}

$pdo = Database::getConexao();
//aceita o login ou o email do administrador
$sql = "SELECT TOP 1 ADMMATRICULA, ADMNOME, ADMSENHA FROM ADMINISTRACAO WHERE ADMLOGIN = :login OR ADMEMAIL = :email";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(":login", $userName);
$stmt->bindParam(":email", $userName);
$stmt->execute();
$data = $stmt->fetch();

if ($data && password_verify($userPass, $data['ADMSENHA'])) {
    session_regenerate_id(true); //evita reaproveitar o id de sessao de antes do login
    $_SESSION['user_id'] = $data['ADMMATRICULA'];
    $_SESSION['user_name'] = $data['ADMNOME'];
    $_SESSION['last_click'] = time();
    header('Location: ../../sistema.php');
    exit();
}

//mesma mensagem para usuario inexistente e senha errada, para nao revelar quais usuarios existem
$_SESSION['login_error'] = "Usuário ou senha incorretos!";
header('Location: ../../index.php');
exit();
?>
