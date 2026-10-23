<?php
//Nucleo de seguranca: TODA pagina ou acao do sistema deve incluir este arquivo antes de qualquer saida.
//Configura erros, sessao, cabecalhos HTTP, CSRF e auditoria.
require_once(__DIR__ . '/../CONNECTION/config.php');

// ---------- Erros: nunca mostrar detalhes tecnicos para o usuario ----------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $erro) {
    error_log("GKT erro nao tratado: " . $erro->getMessage() . " em " . $erro->getFile() . ":" . $erro->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo "Ocorreu um erro interno. Tente novamente ou avise o administrador.";
    exit;
});

// ---------- Sessao ----------
function conexaoSegura(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   //recusa ids de sessao inventados (session fixation)
    ini_set('session.use_only_cookies', '1');  //id nunca vai na URL
    ini_set('session.use_trans_sid', '0');
    session_name('GKTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,                 //cookie some ao fechar o navegador
        'path' => '/',
        'secure' => conexaoSegura(),     //em producao com HTTPS o cookie so trafega criptografado
        'httponly' => true,              //JavaScript nao consegue ler o cookie (protege contra roubo via XSS)
        'samesite' => 'Lax',             //cookie nao vai em POSTs vindos de outros sites (CSRF); Strict deslogaria quem abre por link
    ]);
    session_start();
}

// ---------- Cabecalhos de seguranca ----------
header_remove('X-Powered-By');
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' https://cdn.jsdelivr.net; "
    . "style-src 'self' https://cdn.jsdelivr.net; "
    . "font-src 'self'; "
    . "img-src 'self' data:; "
    . "connect-src 'self'; "
    . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store'); //paginas com dados pessoais nao ficam no cache do navegador
if (conexaoSegura()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ---------- Escape de saida (XSS) ----------
//use e($valor) sempre que imprimir no HTML qualquer dado vindo do banco ou do usuario
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

//exibe so os 3 digitos do meio do CPF (necessidade/minimizacao da LGPD)
function mascararCpf($cpf): string
{
    $cpf = preg_replace('/\D/', '', (string) $cpf);
    return strlen($cpf) === 11 ? '***.' . substr($cpf, 3, 3) . '.***-**' : '***';
}

// ---------- Senhas (OWASP Password Storage) ----------
//Argon2id e o algoritmo recomendado pela OWASP; bcrypt fica como alternativa se o PHP nao tiver Argon2
function algoritmoSenha(): string
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function gerarHashSenha(string $senha): string
{
    return password_hash($senha, algoritmoSenha());
}

//devolve null se a senha for aceitavel, ou o motivo da recusa
function problemaNaSenha(string $senha, string $login = ''): ?string
{
    if (mb_strlen($senha) < 12) {
        return "A senha precisa ter pelo menos 12 caracteres.";
    }
    if (mb_strlen($senha) > 128) {
        return "A senha pode ter no máximo 128 caracteres.";
    }
    if ($login !== '' && stripos($senha, $login) !== false) {
        return "A senha não pode conter o login.";
    }
    $comuns = ['123456789012', 'senha1234567', 'password1234', 'qwertyuiop12', 'gkt123456789', 'academiagkt1', 'admin1234567'];
    if (in_array(strtolower($senha), $comuns, true) || preg_match('/^(.)\1+$/', $senha)) {
        return "Essa senha é fácil demais de adivinhar.";
    }
    return null;
}

// ---------- CSRF ----------
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

//encerra a requisicao se nao for POST com token valido
//endereco da raiz do sistema no site (ex.: /Gkt/Sistema-Gerenciamento-Alunos), funciona de qualquer pasta
function urlRaizProjeto(): string
{
    $raiz = realpath(__DIR__ . '/../..');
    return str_replace('\\', '/', substr($raiz, strlen(realpath($_SERVER['DOCUMENT_ROOT']))));
}

//encerra a requisicao se nao for POST com token valido.
//Token invalido quase sempre e pagina aberta ha muito tempo (a sessao expirou): por isso, em vez de parar
//numa pagina de erro, volta para $voltarPara (ou para o inicio) com uma mensagem explicando.
function exigirPostComCsrf(string $voltarPara = ''): void
{
    $token = $_POST['csrf_token'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($token) || !hash_equals(csrfToken(), $token)) {
        registrarAuditoria('csrf_invalido', $_SERVER['REQUEST_URI'] ?? '');
        definirMensagem('erro', "A página ficou aberta por muito tempo ou foi aberta em outra aba. Tente novamente.");
        header('Location: ' . ($voltarPara !== '' ? $voltarPara : urlRaizProjeto() . '/index.php'));
        exit;
    }
}

// ---------- Mensagens entre paginas ----------
function definirMensagem(string $tipo, string $texto): void
{
    $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
}

function pegarMensagem(): ?array
{
    $mensagem = $_SESSION['mensagem'] ?? null;
    unset($_SESSION['mensagem']);
    return $mensagem;
}

// ---------- Auditoria (LGPD art. 37 e 46 / OWASP A09) ----------
//nunca gravar dados pessoais completos no detalhe, apenas identificadores
function registrarAuditoria(string $acao, string $detalhe = '', ?string $usuario = null): void
{
    try {
        $pdo = Database::getConexao();
        $stmt = $pdo->prepare("INSERT INTO AUDITORIA (USUARIO, ACAO, DETALHE, IP) VALUES (:usuario, :acao, :detalhe, :ip)");
        $stmt->execute([
            ':usuario' => substr($usuario ?? ($_SESSION['user_id'] ?? '-'), 0, 50),
            ':acao' => substr($acao, 0, 40),
            ':detalhe' => substr($detalhe, 0, 255),
            ':ip' => substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45),
        ]);
    } catch (Throwable $erro) {
        error_log("GKT falha ao gravar auditoria: " . $erro->getMessage());
    }
}
?>
