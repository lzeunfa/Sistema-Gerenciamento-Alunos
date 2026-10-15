<?php
//Cria (ou troca a senha de) um administrador com senha criptografada (Argon2id).
//Uso no terminal: C:\xampp\php\php.exe DB/criar_admin.php <login> [nome] [email]
//A senha e pedida depois, para nao ficar gravada no historico do terminal.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once(__DIR__ . '/../BACKEND/SEGURANCA/seguranca.php');

$login = $argv[1] ?? '';
$nome = mb_strtoupper($argv[2] ?? $login);
$email = mb_strtolower($argv[3] ?? "$login@gkt.local");

if ($login === '' || !preg_match('/^[A-Za-z0-9._-]{3,30}$/', $login)) {
    die("Uso: php DB/criar_admin.php <login> [nome] [email]\nO login deve ter de 3 a 30 letras, numeros, ponto, hifen ou sublinhado.\n");
}

//senha pode vir por variavel de ambiente (automacao) ou digitada no terminal
$senha = getenv('GKT_NOVA_SENHA') ?: '';
if ($senha === '') {
    echo "Digite a nova senha (minimo 12 caracteres): ";
    $senha = rtrim((string) fgets(STDIN), "\r\n");
}
$problema = problemaNaSenha($senha, $login);
if ($problema) {
    die($problema . "\n");
}

$pdo = Database::getConexao();
$hash = gerarHashSenha($senha);

$stmt = $pdo->prepare("SELECT ADMMATRICULA FROM ADMINISTRACAO WHERE ADMLOGIN = :login");
$stmt->execute([':login' => $login]);

if ($stmt->fetch()) {
    $stmt = $pdo->prepare("UPDATE ADMINISTRACAO SET ADMSENHA = :senha WHERE ADMLOGIN = :login");
    $stmt->execute([':senha' => $hash, ':login' => $login]);
    registrarAuditoria('admin_senha_trocada', "login $login", 'terminal');
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
    ':nome' => mb_substr($nome, 0, 20),
    ':email' => $email,
    ':cpf' => str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT),
    ':login' => $login,
    ':senha' => $hash,
]);
registrarAuditoria('admin_criado', "login $login", 'terminal');
echo "Administrador '$login' criado (matricula $matricula).\n";
?>
