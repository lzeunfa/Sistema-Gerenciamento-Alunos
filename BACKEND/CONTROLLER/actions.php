<?php
require_once(__DIR__ . '/../SEGURANCA/seguranca.php');
exigirPostComCsrf('../../index.php');

//campo enviado como lista (inputUser[]=x) vira vazio; trim() de array derrubava a pagina com erro 500
$userName = is_string($_POST['inputUser'] ?? null) ? trim($_POST['inputUser']) : '';
$userPass = is_string($_POST['inputUserPass'] ?? null) ? $_POST['inputUserPass'] : '';

function voltarAoLogin(string $mensagem): void
{
    $_SESSION['login_error'] = $mensagem;
    header('Location: ../../index.php');
    exit();
}

if ($userName === '' || $userPass === '' || strlen($userName) > 100 || strlen($userPass) > 128) {
    voltarAoLogin("Preencha usuário e senha!");
}

$pdo = Database::getConexao();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

//protecao contra forca bruta: 5 erros no mesmo login ou 20 erros no mesmo IP em 15 minutos bloqueiam por 15 minutos
$stmt = $pdo->prepare("SELECT
        SUM(CASE WHEN CHAVE = :login THEN 1 ELSE 0 END) AS erros_login,
        SUM(CASE WHEN CHAVE = :ip THEN 1 ELSE 0 END) AS erros_ip
    FROM LOGIN_TENTATIVAS WHERE DATAHORA > DATEADD(MINUTE, -15, GETDATE())");
$stmt->execute([':login' => 'login:' . strtolower($userName), ':ip' => 'ip:' . $ip]);
$erros = $stmt->fetch();

if ((int) $erros['erros_login'] >= 5 || (int) $erros['erros_ip'] >= 20) {
    registrarAuditoria('login_bloqueado', 'muitas tentativas', $userName);
    voltarAoLogin("Muitas tentativas erradas. Aguarde 15 minutos e tente novamente.");
}

//aceita o login ou o email do administrador
$sql = "SELECT TOP 1 ADMMATRICULA, ADMNOME, ADMSENHA FROM ADMINISTRACAO WHERE ADMLOGIN = :login OR ADMEMAIL = :email";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(":login", $userName);
$stmt->bindParam(":email", $userName);
$stmt->execute();
$data = $stmt->fetch();

//se o usuario nao existe, confere contra um hash falso para a resposta demorar o mesmo tempo
//(senao daria para descobrir quais usuarios existem medindo o tempo de resposta)
$hashParaConferir = $data['ADMSENHA'] ?? '$argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHRzYWx0c2FsdA$Zm9vYmFyZm9vYmFyZm9vYmFyZm9vYmFyZm9vYmFy';
$senhaConfere = password_verify($userPass, $hashParaConferir);

if ($data && $senhaConfere) {
    //senhas antigas em bcrypt sao convertidas para Argon2id no primeiro login
    if (password_needs_rehash($data['ADMSENHA'], algoritmoSenha())) {
        $stmt = $pdo->prepare("UPDATE ADMINISTRACAO SET ADMSENHA = :senha WHERE ADMMATRICULA = :matricula");
        $stmt->execute([':senha' => gerarHashSenha($userPass), ':matricula' => $data['ADMMATRICULA']]);
    }

    $stmt = $pdo->prepare("DELETE FROM LOGIN_TENTATIVAS WHERE CHAVE = :login");
    $stmt->execute([':login' => 'login:' . strtolower($userName)]);

    session_regenerate_id(true); //evita reaproveitar o id de sessao de antes do login
    unset($_SESSION['csrf_token']);
    $_SESSION['user_id'] = $data['ADMMATRICULA'];
    $_SESSION['user_name'] = $data['ADMNOME'];
    $_SESSION['last_click'] = time();
    $_SESSION['login_time'] = time();
    registrarAuditoria('login_ok');
    header('Location: ../../sistema.php');
    exit();
}

$stmt = $pdo->prepare("INSERT INTO LOGIN_TENTATIVAS (CHAVE) VALUES (:login), (:ip)");
$stmt->execute([':login' => 'login:' . strtolower($userName), ':ip' => 'ip:' . $ip]);
//so os ultimos 15 minutos importam para o bloqueio; sem esta limpeza a tabela cresceria para sempre sob ataque
$pdo->exec("DELETE FROM LOGIN_TENTATIVAS WHERE DATAHORA < DATEADD(DAY, -1, GETDATE())");
registrarAuditoria('login_falha', '', $userName);

//mesma mensagem para usuario inexistente e senha errada, para nao revelar quais usuarios existem
voltarAoLogin("Usuário ou senha incorretos!");
?>
