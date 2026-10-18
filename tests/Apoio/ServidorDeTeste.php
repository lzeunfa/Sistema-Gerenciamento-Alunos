<?php
//Servidor web embutido do PHP (php -S) usado pelos testes funcionais.
//Roda o sistema de verdade, mas apontando para o banco de teste (GKT_DB_DATABASE).
//Nao le o .htaccess: as regras do Apache sao testadas em tests/Apache.
final class ServidorDeTeste
{
    private static $processo = null;
    public const PORTA = 8099;

    public static function url(string $caminho = ''): string
    {
        return 'http://127.0.0.1:' . self::PORTA . '/' . ltrim($caminho, '/');
    }

    public static function iniciar(): void
    {
        if (self::$processo !== null) {
            return;
        }
        $env = array_merge(getenv(), ['GKT_DB_DATABASE' => BANCO_TESTE]);
        $comando = [PHP_BINARY, '-S', '127.0.0.1:' . self::PORTA, '-t', RAIZ_PROJETO];
        $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$processo = proc_open($comando, [['pipe', 'r'], ['file', $nulo, 'w'], ['file', $nulo, 'w']], $pipes, RAIZ_PROJETO, $env, ['bypass_shell' => true]);
        register_shutdown_function([self::class, 'parar']);

        for ($i = 0; $i < 50; $i++) { //espera ate 5 s o servidor aceitar conexoes
            $socket = @fsockopen('127.0.0.1', self::PORTA, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException("Servidor de teste não subiu na porta " . self::PORTA);
    }

    public static function parar(): void
    {
        if (self::$processo !== null) {
            proc_terminate(self::$processo);
            proc_close(self::$processo);
            self::$processo = null;
        }
    }
}

//Cliente HTTP que se comporta como um navegador: guarda cookies e NAO segue redirecionamentos,
//para os testes conferirem para onde cada acao manda o usuario.
final class Navegador
{
    private string $cookies;

    public function __construct()
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'gkt_cookies_');
    }

    public function __destruct()
    {
        @unlink($this->cookies);
    }

    public function get(string $caminho, string $base = ''): array
    {
        return $this->requisitar('GET', $caminho, null, $base);
    }

    public function post(string $caminho, array $campos, string $base = ''): array
    {
        return $this->requisitar('POST', $caminho, $campos, $base);
    }

    //devolve ['status' => 200, 'cabecalhos' => [nome => valor], 'corpo' => '...', 'destino' => URL do Location]
    private function requisitar(string $metodo, string $caminho, ?array $campos, string $base): array
    {
        $url = str_starts_with($caminho, 'http') ? $caminho : ($base ?: ServidorDeTeste::url()) . ltrim($caminho, '/');
        $cabecalhos = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => function ($c, $linha) use (&$cabecalhos) {
                if (str_contains($linha, ':')) {
                    [$nome, $valor] = explode(':', $linha, 2);
                    $cabecalhos[strtolower(trim($nome))][] = trim($valor);
                }
                return strlen($linha);
            },
        ]);
        if ($metodo === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($campos ?? []));
        }
        $corpo = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $destino = curl_getinfo($curl, CURLINFO_REDIRECT_URL) ?: '';
        curl_close($curl);
        return ['status' => $status, 'cabecalhos' => $cabecalhos, 'corpo' => (string) $corpo, 'destino' => $destino];
    }

    //valor de um cookie guardado (formato Netscape do curl)
    public function cookie(string $nome): ?string
    {
        foreach (@file($this->cookies) ?: [] as $linha) {
            $partes = explode("\t", trim($linha));
            if (count($partes) === 7 && $partes[5] === $nome) {
                return $partes[6];
            }
        }
        return null;
    }

    public static function token(string $html): string
    {
        preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
        return $m[1] ?? '';
    }

    public static function mensagem(string $html): string
    {
        preg_match('/role="alert">([^<]+)</', $html, $m);
        return html_entity_decode(trim($m[1] ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
