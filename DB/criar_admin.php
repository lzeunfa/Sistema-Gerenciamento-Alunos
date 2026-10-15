<?php
//Cria (ou troca a senha de) um administrador com senha criptografada.
//Uso no terminal: C:\xampp\php\php.exe DB/criar_admin.php <login> <senha> [nome] [email]
require_once(__DIR__ . '/../BACKEND/CONNECTION/config.php');

if (PHP_SAPI !== 'cli') {
    die("Rode este arquivo pelo terminal.");
}

$login = $argv[1] ?? '';
$senha = $argv[2] ?? '';
$nome = strtoupper($argv[3] ?? $login);
$email = strtolower($argv[4] ?? "$login@gkt.local");

if ($login === '' || $senha === '') {
    die("Uso: php DB/criar_admin.php <login> <senha> [nome] [email]\n");
}

$pdo = Database::getConexao();
$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT ADMMATRICULA FROM ADMINISTRACAO WHERE ADMLOGIN = :login");
$stmt->execute([':login' => $login]);

if ($stmt->fetch()) {
    $stmt = $pdo->prepare("UPDATE ADMINISTRACAO SET ADMSENHA = :senha WHERE ADMLOGIN = :login");
    $stmt->execute([':senha' => $hash, ':login' => $login]);
    echo "Senha do administrador '$login' atualizada.\n";
    exit;
}

//campos obrigatorios da tabela recebem valores ficticios, podem ser editados depois no banco
$matricula = 'ADM' . date('ymdHis');
$sql = "INSERT INTO ADMINISTRACAO
        (ADMMATRICULA, ADMNOME, ADMTELEFONE, ADMEMAIL, ADMCPF, ADMDATANASC, ADMSEX, ADMCEP, ADMENDERECO, ADMLOGIN, ADMSENHA)
        VALUES (:matricula, :nome, '0000000000', :email, :cpf, '2000-01-01', 'M', '00000000', '-', :login, :senha)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':matricula' => $matricula,
    ':nome' => substr($nome, 0, 20),
    ':email' => $email,
    ':cpf' => str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT),
    ':login' => $login,
    ':senha' => $hash,
]);
echo "Administrador '$login' criado (matricula $matricula).\n";
?>
