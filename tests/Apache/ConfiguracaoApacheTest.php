<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

//Regras do .htaccess e configuracao do Apache do XAMPP. So faz leituras (GET), nao altera dados.
final class ConfiguracaoApacheTest extends TestCase
{
    //XAMPP: http://localhost/Gkt/Sistema-Gerenciamento-Alunos/  |  Docker: GKT_URL_APACHE=http://app/
    private static function base(): string
    {
        return rtrim(getenv('GKT_URL_APACHE') ?: 'http://localhost/Gkt/Sistema-Gerenciamento-Alunos/', '/') . '/';
    }

    public static function setUpBeforeClass(): void
    {
        $url = parse_url(self::base());
        $socket = @fsockopen($url['host'], $url['port'] ?? 80, $errno, $errstr, 1);
        if (!$socket) {
            self::markTestSkipped('Apache não está respondendo em ' . self::base());
        }
        fclose($socket);
    }

    private function get(string $caminho): array
    {
        return (new Navegador())->get($caminho, self::base());
    }

    public static function arquivosInternos(): array
    {
        return array_map(fn($c) => [$c], [
            '.env', '.env.example', '.git/config', '.GIT/config', '.gitignore', '.htaccess', '.github/workflows/ci.yml',
            'DB/estrutura.sql', 'db/ESTRUTURA.SQL', 'DB/criar_admin.php', 'DB/anonimizar_antigos.php', 'DB/backup.ps1',
            'DOC/SEGURANCA_LGPD.md', 'CLAUDE.md', 'README.md', 'CHANGELOG.md',
            'BACKEND/CONNECTION/config.php', 'BACKEND/SEGURANCA/seguranca.php', 'BACKEND/CONTROLLER/funcoes.php',
            'ASSETS/PAGINAS/ALUNOS.php', 'ASSETS/PAGINAS/_acoesAluno.php', 'trava.php',
            'vendor/autoload.php', 'composer.json', 'composer.lock', 'phpstan.neon', 'phpunit.xml',
            'tests/bootstrap.php', 'tests/preparar_banco_teste.ps1',
            'docker/Dockerfile', 'docker/docker-compose.yml', 'docker/iniciar-banco.sh', 'docker/.env.example', '.dockerignore',
        ]);
    }

    #[DataProvider('arquivosInternos')]
    public function testArquivoInternoNaoEhAcessivelPeloNavegador(string $caminho): void
    {
        //404 vem do nosso .htaccess; 403 do proprio Apache (bloqueia .ht* antes). Os dois impedem o download.
        $resposta = $this->get($caminho);
        $this->assertContains($resposta['status'], [403, 404], $caminho);
        $this->assertStringNotContainsString('DB_PASSWORD', $resposta['corpo']);
    }

    public static function paginasPublicas(): array
    {
        return array_map(fn($c) => [$c], ['index.php', 'privacidade.php', 'ASSETS/CSS/loginStyle.css', 'ASSETS/JS/login.js']);
    }

    #[DataProvider('paginasPublicas')]
    public function testPaginaPublicaAbre(string $caminho): void
    {
        $this->assertSame(200, $this->get($caminho)['status'], $caminho);
    }

    public function testPastasNaoListamArquivos(): void
    {
        $resposta = $this->get('ASSETS/IMGS/');
        $this->assertSame(403, $resposta['status']);
        $this->assertStringNotContainsString('Index of', $resposta['corpo']);
    }

    public function testServidorNaoRevelaVersoes(): void
    {
        $c = $this->get('index.php')['cabecalhos'];
        $this->assertSame('Apache', $c['server'][0] ?? null);
        $this->assertArrayNotHasKey('x-powered-by', $c);
    }

    public function testCabecalhosDeSegurancaSemDuplicar(): void
    {
        $c = $this->get('index.php')['cabecalhos'];
        foreach (['x-frame-options' => 'DENY', 'x-content-type-options' => 'nosniff', 'referrer-policy' => 'same-origin'] as $nome => $valor) {
            $this->assertSame([$valor], $c[$nome] ?? [], $nome);
        }
        $this->assertCount(1, $c['content-security-policy'] ?? []);
        $this->assertNotEmpty($c['permissions-policy'] ?? []);
    }

    public function testArquivosEstaticosTambemTemCabecalhos(): void
    {
        $c = $this->get('ASSETS/CSS/loginStyle.css')['cabecalhos'];
        $this->assertSame(['nosniff'], $c['x-content-type-options'] ?? []);
        $this->assertSame(['same-origin'], $c['cross-origin-resource-policy'] ?? [], 'outros sites não podem embutir nossos arquivos');
    }

    public function testPaginaDeErroTemPermissionsPolicy(): void
    {
        $this->assertNotEmpty($this->get('sitemap.xml')['cabecalhos']['permissions-policy'] ?? []);
    }

    public function testCabecalhoXOriginalUrlNaoBurlaOBloqueio(): void
    {
        //"Bypassing 403" do ZAP: truque que funciona em IIS/Nginx; no Apache deve ser ignorado
        foreach (['X-Original-URL: /BACKEND/CONNECTION/config.php', 'X-Rewrite-URL: /.env'] as $cabecalho) {
            $curl = curl_init(self::base());
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [$cabecalho]]);
            $corpo = (string) curl_exec($curl);
            curl_close($curl);
            $this->assertStringNotContainsString('DB_PASSWORD', $corpo, $cabecalho);
            $this->assertStringNotContainsString('class Database', $corpo, $cabecalho);
        }
    }

    public function testPaginaDeErroTemCspQueNaoPermiteNada(): void
    {
        //achado do OWASP ZAP: 404 do Apache (robots.txt, sitemap.xml) vinha sem CSP
        $c = $this->get('sitemap.xml')['cabecalhos'];
        $this->assertSame(["default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"], $c['content-security-policy'] ?? []);
        $this->assertSame(['nosniff'], $c['x-content-type-options'] ?? []);
    }

    public function testRobotsPedeParaNaoIndexarOSistema(): void
    {
        $resposta = $this->get('robots.txt');
        $this->assertSame(200, $resposta['status']);
        $this->assertMatchesRegularExpression('/^Disallow: \/\s*$/m', $resposta['corpo']);
    }

    public function testPaginaDeErroNaoMostraDetalhesDoServidor(): void
    {
        $corpo = $this->get('nao-existe-' . uniqid() . '.php')['corpo'];
        $this->assertStringNotContainsStringIgnoringCase('Apache/2', $corpo);
        $this->assertStringNotContainsString('C:\\xampp', $corpo);
    }

    public function testApacheSoAtendeEsteComputador(): void
    {
        $httpd = @file_get_contents('C:/xampp/apache/conf/httpd.conf');
        if ($httpd === false) {
            $this->markTestSkipped('httpd.conf não encontrado');
        }
        $this->assertMatchesRegularExpression('/^Listen 127\.0\.0\.1:80\r?$/m', $httpd, 'o httpd.conf usa quebra de linha do Windows');
    }
}
