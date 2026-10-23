<?php
//Carregado pelo PHPUnit antes de qualquer teste.
//Garante que NENHUM teste use o banco real: tudo vai para um banco cujo nome termina em _teste.

define('RAIZ_PROJETO', dirname(__DIR__));
define('BANCO_TESTE', getenv('GKT_DB_DATABASE') ?: 'gktgym_teste');

if (!preg_match('/_teste$/', BANCO_TESTE)) {
    fwrite(STDERR, "GKT_DB_DATABASE precisa terminar em _teste (recebido: " . BANCO_TESTE . ").\n");
    exit(1);
}
putenv('GKT_DB_DATABASE=' . BANCO_TESTE);

require_once RAIZ_PROJETO . '/vendor/autoload.php';

//erros esperados dos testes (CPF repetido etc.) vao para um arquivo proprio, nao para a tela nem para o log do XAMPP
ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gkt_testes_php_error.log');

//na linha de comando nao ha navegador: sessao sem cookie e sem cabecalhos
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.cache_limiter', '');
$_SERVER['DOCUMENT_ROOT'] = dirname(RAIZ_PROJETO, 2); //C:\xampp\htdocs, como no Apache
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once RAIZ_PROJETO . '/BACKEND/SEGURANCA/seguranca.php';
require_once RAIZ_PROJETO . '/BACKEND/CONTROLLER/funcoes.php';
restore_exception_handler(); //o PHPUnit trata as excecoes dos testes

//true se o banco de teste existe e o usuario do sistema consegue entrar nele.
//Testa com PDO direto porque Database::getConexao() encerra o processo quando falha.
function bancoTesteDisponivel(): bool
{
    static $disponivel = null;
    if ($disponivel !== null) {
        return $disponivel;
    }
    $temConfig = file_exists(RAIZ_PROJETO . '/.env') || getenv('DB_HOST') !== false;
    if (!$temConfig || !extension_loaded('pdo_sqlsrv')) {
        return $disponivel = false;
    }
    $env = Database::configuracao();
    $confiar = !empty($env['DB_TRUST_CERT']) ? ';TrustServerCertificate=1' : '';
    try {
        new PDO("sqlsrv:Server={$env['DB_HOST']},{$env['DB_PORT']};Database=" . BANCO_TESTE . ";LoginTimeout=3$confiar", $env['DB_USERNAME'], $env['DB_PASSWORD']);
        return $disponivel = true;
    } catch (PDOException $erro) {
        return $disponivel = false;
    }
}

require_once __DIR__ . '/Apoio/TesteComBanco.php';
require_once __DIR__ . '/Apoio/ServidorDeTeste.php';
require_once __DIR__ . '/Apoio/TesteFuncional.php';
