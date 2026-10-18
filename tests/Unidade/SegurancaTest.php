<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

//Funcoes do nucleo de seguranca (BACKEND/SEGURANCA/seguranca.php) que nao dependem do banco.
final class SegurancaTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    // ---------- e() : escape contra XSS ----------

    public function testEscapaCaracteresQueMontamHtml(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
        $this->assertSame('&quot; onmouseover=&quot;x', e('" onmouseover="x'));
        $this->assertSame('&#039;x&#039;', e("'x'"));
        $this->assertSame('a &amp; b', e('a & b'));
    }

    public function testEscapeAceitaNumerosENulo(): void
    {
        $this->assertSame('123', e(123));
        $this->assertSame('', e(null));
    }

    public function testEscapeNaoQuebraTextoComAcento(): void
    {
        $this->assertSame('KARATÊ', e('KARATÊ'));
    }

    // ---------- mascararCpf : minimizacao nas listagens ----------

    public function testMascaraMostraSoOsDigitosDoMeio(): void
    {
        $this->assertSame('***.982.***-**', mascararCpf('52998224725'));
        $this->assertSame('***.982.***-**', mascararCpf('529.982.247-25'));
    }

    public function testMascaraDeCpfMalFormadoNaoVazaNada(): void
    {
        $this->assertSame('***', mascararCpf('123'));
        $this->assertSame('***', mascararCpf(''));
        $this->assertSame('***', mascararCpf('X0000000001')); //CPF de aluno anonimizado
    }

    // ---------- politica de senha ----------

    public static function senhasRecusadas(): array
    {
        return [
            'curta' => ['curta123', ''],
            'onze caracteres' => ['abcdefghijk', ''],
            'longa demais' => [str_repeat('a1', 65), ''],
            'contem o login' => ['minhasenhafelipe9', 'felipe'],
            'contem o login em maiusculas' => ['minhasenhaFELIPE9', 'felipe'],
            'comum' => ['123456789012', ''],
            'comum maiuscula' => ['ADMIN1234567', ''],
            'caractere repetido' => ['aaaaaaaaaaaaaaaa', ''],
        ];
    }

    #[DataProvider('senhasRecusadas')]
    public function testSenhaFracaEhRecusadaComMotivo(string $senha, string $login): void
    {
        $this->assertNotNull(problemaNaSenha($senha, $login));
    }

    public function testSenhaForteEhAceita(): void
    {
        $this->assertNull(problemaNaSenha('cavalo-bateria-grampo-7', 'felipe'));
        $this->assertNull(problemaNaSenha('ação-coração1', '')); //12 caracteres contando acentos
    }

    // ---------- hash de senha ----------

    public function testHashUsaArgon2idEConfere(): void
    {
        $hash = gerarHashSenha('cavalo-bateria-grampo-7');
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(password_verify('cavalo-bateria-grampo-7', $hash));
        $this->assertFalse(password_verify('outra-senha-qualquer', $hash));
    }

    public function testMesmaSenhaGeraHashesDiferentes(): void
    {
        $this->assertNotSame(gerarHashSenha('cavalo-bateria-grampo-7'), gerarHashSenha('cavalo-bateria-grampo-7'));
    }

    public function testHashFalsoDoLoginEhUmArgon2Valido(): void
    {
        //actions.php confere usuario inexistente contra este hash, para o tempo de resposta ser igual
        $info = password_get_info('$argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHRzYWx0c2FsdA$Zm9vYmFyZm9vYmFyZm9vYmFyZm9vYmFyZm9vYmFy');
        $this->assertSame('argon2id', $info['algoName']);
    }

    // ---------- CSRF ----------

    public function testTokenCsrfTem256BitsEFicaFixoNaSessao(): void
    {
        $token = csrfToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, csrfToken());
    }

    public function testSessoesDiferentesTemTokensDiferentes(): void
    {
        $primeiro = csrfToken();
        $_SESSION = [];
        $this->assertNotSame($primeiro, csrfToken());
    }

    public function testCampoCsrfEhUmInputEscondidoComOToken(): void
    {
        $this->assertSame('<input type="hidden" name="csrf_token" value="' . csrfToken() . '">', csrfCampo());
    }

    // ---------- mensagens entre paginas ----------

    public function testMensagemEhEntregueUmaVezSo(): void
    {
        definirMensagem('sucesso', 'Aluno cadastrado');
        $this->assertSame(['tipo' => 'sucesso', 'texto' => 'Aluno cadastrado'], pegarMensagem());
        $this->assertNull(pegarMensagem());
    }

    // ---------- endereco e HTTPS ----------

    public function testRaizDoProjetoNoApache(): void
    {
        //bootstrap.php simula o Apache: DOCUMENT_ROOT dois niveis acima (C:\xampp\htdocs -> /Gkt/Sistema-Gerenciamento-Alunos)
        $esperado = '/' . basename(dirname(RAIZ_PROJETO)) . '/' . basename(RAIZ_PROJETO);
        $this->assertSame($esperado, urlRaizProjeto());
    }

    public function testDetectaHttps(): void
    {
        $original = $_SERVER;
        try {
            unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
            $this->assertFalse(conexaoSegura());
            $_SERVER['HTTPS'] = 'on';
            $this->assertTrue(conexaoSegura());
            $_SERVER['HTTPS'] = 'off';
            $this->assertFalse(conexaoSegura());
            unset($_SERVER['HTTPS']);
            $_SERVER['SERVER_PORT'] = 443;
            $this->assertTrue(conexaoSegura());
        } finally {
            $_SERVER = $original;
        }
    }

    public function testAlgoritmoDeSenhaEhArgon2idNoPhpDoXampp(): void
    {
        $this->assertSame(PASSWORD_ARGON2ID, algoritmoSenha());
    }
}
