<?php
class Database
{
    private static $conn = null;
    private function __construct()
    {
    }

    //Le a configuracao do banco: do arquivo .env na raiz (XAMPP) ou, se ele nao existir,
    //das variaveis de ambiente (container Docker, ver docker/README.md).
    public static function configuracao(): array
    {
        $chaves = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_TRUST_CERT'];
        $caminhoEnv = dirname(__DIR__, 2) . '/.env';

        if (file_exists($caminhoEnv)) {
            $arquivoEnv = parse_ini_file($caminhoEnv) ?: [];
        } else {
            $arquivoEnv = [];
            foreach ($chaves as $chave) {
                if (getenv($chave) !== false) {
                    $arquivoEnv[$chave] = getenv($chave);
                }
            }
        }

        foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $obrigatoria) {
            if (!isset($arquivoEnv[$obrigatoria])) {
                error_log("Configuracao do banco incompleta: falta $obrigatoria (.env ou variavel de ambiente)");
                die("Configuração do banco incompleta.");
            }
        }

        //os testes automatizados apontam para o banco gktgym_teste por esta variavel (ver tests/README.md)
        $arquivoEnv['DB_DATABASE'] = getenv('GKT_DB_DATABASE') ?: $arquivoEnv['DB_DATABASE'];
        return $arquivoEnv;
    }

    public static function getConexao()
    {
        if (self::$conn === null) { //testa se e nulo e do mesmo tipo.
            $c = self::configuracao();
            //host,porta e nome do banco.
            $dsn = "{$c['DB_CONNECTION']}:Server={$c['DB_HOST']},{$c['DB_PORT']};Database={$c['DB_DATABASE']}";
            //o driver ODBC 18 (container) exige certificado confiavel; dentro da rede do Docker o do SQL Server e autoassinado
            if (!empty($c['DB_TRUST_CERT'])) {
                $dsn .= ';TrustServerCertificate=1';
            }
            $opcoes = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            try {
                self::$conn = new PDO($dsn, $c['DB_USERNAME'], $c['DB_PASSWORD'], $opcoes);
            } catch (PDOException $error) {
                self::tratarErroConexao($error);
            }
        }
        return self::$conn;
    }

    private static function tratarErroConexao($error)
    {
        error_log("Erro crítico de banco de dados: " . $error->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            "erro" => true,
            "mensagem" => "Falha interna no servidor."
        ]);
        exit;
    }
}
?>