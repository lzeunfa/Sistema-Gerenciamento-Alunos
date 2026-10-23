<?php
//Prepara o banco no SQL Server do container usando o proprio PHP (pdo_sqlsrv), sem sqlcmd.
//Seguro para rodar a cada subida: cria o banco so se nao existir, aplica dados iniciais e migracoes
//(idempotentes) e acerta o usuario do sistema com privilegio minimo.
//   php docker/iniciar-banco.php                          banco gktgym (servico banco-init)
//   php docker/iniciar-banco.php gktgym_teste --recriar   apaga e recria do zero (testes)
//Precisa de: SA_PASSWORD, DB_PASSWORD; opcionais DB_HOST (banco), DB_PORT (1433), DB_USERNAME (gkt_app)
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function falhar(string $mensagem): never
{
    fwrite(STDERR, $mensagem . "\n");
    exit(1);
}

$banco = $argv[1] ?? 'gktgym';
$recriar = ($argv[2] ?? '') === '--recriar';
$usuarioApp = getenv('DB_USERNAME') ?: 'gkt_app';
$senhaSa = getenv('SA_PASSWORD') ?: falhar('defina SA_PASSWORD');
$senhaApp = getenv('DB_PASSWORD') ?: falhar('defina DB_PASSWORD');
$servidor = (getenv('DB_HOST') ?: 'banco') . ',' . (getenv('DB_PORT') ?: '1433');

//nomes entram no SQL como identificadores: so letras, numeros e sublinhado
foreach ([$banco, $usuarioApp] as $nome) {
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $nome)) {
        falhar("nome invalido: $nome");
    }
}
if ($recriar && !str_ends_with($banco, '_teste')) {
    falhar('Por seguranca, --recriar so funciona em banco terminado em _teste.');
}

echo "Aguardando o SQL Server...\n";
$pdo = null;
for ($tentativa = 1; $tentativa <= 60; $tentativa++) {
    try {
        $pdo = new PDO("sqlsrv:Server=$servidor;TrustServerCertificate=1;LoginTimeout=5", 'sa', $senhaSa, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $erro) {
        sleep(2);
    }
}
$pdo ?? falhar('SQL Server nao respondeu em 2 minutos.');

//executa um arquivo .sql dividido nos "GO", como o sqlcmd faz, trocando o nome do banco
function rodar(PDO $pdo, string $arquivo, string $banco): void
{
    $sql = str_replace('[gktgym]', "[$banco]", file_get_contents(__DIR__ . "/../DB/$arquivo.sql"));
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql); //BOM do UTF-8
    foreach (preg_split('/^\s*GO\s*$/mi', $sql) as $lote) {
        if (trim($lote) !== '') {
            $pdo->exec($lote);
        }
    }
    echo "  ok: DB/$arquivo.sql\n";
}

try {
    if ($recriar) {
        $pdo->exec("IF DB_ID('$banco') IS NOT NULL BEGIN ALTER DATABASE [$banco] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [$banco]; END");
    }
    $pdo->exec('USE master');
    if ($pdo->query("SELECT DB_ID('$banco')")->fetchColumn() === null) {
        echo "Criando o banco $banco...\n";
        $pdo->exec("CREATE DATABASE [$banco]");
        rodar($pdo, 'estrutura', $banco);
    }
    foreach (['dados_iniciais', 'migracao_login', 'migracao_seguranca', 'migracao_direitos_titular', 'migracao_desempenho'] as $script) {
        rodar($pdo, $script, $banco);
    }

    //usuario do sistema com privilegio minimo (igual a producao, ver DB/migracao_seguranca.sql)
    $senha = $pdo->quote($senhaApp);
    $pdo->exec("USE [$banco]");
    $pdo->exec("IF SUSER_ID('$usuarioApp') IS NULL
                    CREATE LOGIN [$usuarioApp] WITH PASSWORD = $senha, CHECK_POLICY = ON;
                ELSE
                    ALTER LOGIN [$usuarioApp] WITH PASSWORD = $senha;");
    $pdo->exec("IF USER_ID('$usuarioApp') IS NULL CREATE USER [$usuarioApp] FOR LOGIN [$usuarioApp];");
    //banco restaurado de outro servidor (docker/restaurar.sh) traz o usuario ligado a um login que nao existe aqui
    $pdo->exec("ALTER USER [$usuarioApp] WITH LOGIN = [$usuarioApp];");
    $pdo->exec("ALTER ROLE db_datareader ADD MEMBER [$usuarioApp];
                ALTER ROLE db_datawriter ADD MEMBER [$usuarioApp];
                DENY ALTER, REFERENCES ON SCHEMA::dbo TO [$usuarioApp];
                DENY UPDATE, DELETE ON dbo.AUDITORIA TO [$usuarioApp];");
} catch (PDOException $erro) {
    falhar('Erro ao preparar o banco: ' . $erro->getMessage());
}

echo "Banco $banco pronto.\n";
