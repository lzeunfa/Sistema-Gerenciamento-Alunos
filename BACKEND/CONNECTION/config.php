<?php
class Database
{
    private static $conn = null;
    private function __construct()
    {
    }
    public static function getConexao()
    {
        if (self::$conn === null) { //testa se e nulo e do mesmo tipo.
            $caminhoEnv = dirname(__DIR__, 2) . '/.env';

            if (!file_exists($caminhoEnv)) { //Se ele for false(diferente de verdadeiro) morre na hora.
                die("Nao encontrei o  .env " . $caminhoEnv);
            }

            $arquivoEnv = parse_ini_file($caminhoEnv); //Ler  meu arquivo .env
            //Ao ler, pegar os dados do Banco.
            $db_conn = $arquivoEnv["DB_CONNECTION"];
            $db_host = $arquivoEnv["DB_HOST"];
            $db_port = $arquivoEnv["DB_PORT"];
            $db_name = $arquivoEnv["DB_DATABASE"];
            $db_user = $arquivoEnv["DB_USERNAME"];
            $db_pass = $arquivoEnv["DB_PASSWORD"];
            //host,porta e nome do banco.
            $dsn = "$db_conn:Server=$db_host,$db_port;Database=$db_name";
            $opcoes = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            try {
                self::$conn = new PDO($dsn, $db_user, $db_pass, $opcoes);
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