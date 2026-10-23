<?php
//Base dos testes funcionais: sobe o sistema no servidor de teste e cria um administrador conhecido.
abstract class TesteFuncional extends TesteComBanco
{
    protected const LOGIN = 'teste_func';
    protected const EMAIL = 'teste_func@exemplo.invalid';
    protected const SENHA = 'cavalo-bateria-grampo-7';

    protected Navegador $nav;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ServidorDeTeste::iniciar();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = Database::getConexao();
        $pdo->exec("DELETE FROM ADMINISTRACAO WHERE ADMLOGIN LIKE 'teste_%'");
        $stmt = $pdo->prepare("INSERT INTO ADMINISTRACAO (ADMMATRICULA, ADMNOME, ADMTELEFONE, ADMEMAIL, ADMCPF, ADMDATANASC, ADMSEX, ADMCEP, ADMENDERECO, ADMLOGIN, ADMSENHA)
            VALUES ('ADM-TESTE', 'ADMIN TESTE', '0000000000', :email, '00000000000', '2000-01-01', 'M', '00000000', '-', :login, :senha)");
        $stmt->execute([':email' => self::EMAIL, ':login' => self::LOGIN, ':senha' => gerarHashSenha(self::SENHA)]);
        $this->nav = new Navegador();
    }

    //caminho do redirecionamento, sem host (ex.: /sistema.php)
    protected function destino(array $resposta): string
    {
        $url = parse_url($resposta['destino']);
        return ($url['path'] ?? '') . (isset($url['query']) ? '?' . $url['query'] : '');
    }

    protected function tentarLogin(string $usuario, string $senha, ?Navegador $nav = null): array
    {
        $nav ??= $this->nav;
        $token = Navegador::token($nav->get('index.php')['corpo']);
        return $nav->post('BACKEND/CONTROLLER/actions.php', ['csrf_token' => $token, 'inputUser' => $usuario, 'inputUserPass' => $senha]);
    }

    protected function entrar(): void
    {
        $resposta = $this->tentarLogin(self::LOGIN, self::SENHA);
        $this->assertSame('/sistema.php', $this->destino($resposta), 'login do administrador de teste');
    }

    //token CSRF da sessao atual (qualquer pagina interna tem o formulario de Sair)
    protected function tokenDaSessao(): string
    {
        return Navegador::token($this->nav->get('sistema.php?pagina=CADASTRAR')['corpo']);
    }

    //campos de um cadastro valido, como o formulario envia
    protected function formularioAluno(array $mudancas = []): array
    {
        return array_merge([
            'nome' => 'aluno funcional',
            'telefone' => '(61) 99999-0000',
            'email' => 'funcional@exemplo.invalid',
            'cpf' => '529.982.247-25',
            'data_nascimento' => '1990-05-10',
            'genero' => 'masculino',
            'cep' => '70000-000',
            'endereco' => 'Rua Ficticia 1',
            'modalidades' => ['1'],
            'plano-pagamento' => '1',
            'frequencia-dias' => '1',
            'status' => '1',
            'ciencia_privacidade' => '1',
        ], $mudancas);
    }

    //altera um valor guardado na sessao do servidor (ex.: simular 11 minutos parado)
    protected function alterarSessao(string $chave, int $valor): void
    {
        $id = $this->nav->cookie('GKTSESSID');
        $this->assertNotNull($id, 'cookie de sessão');
        $arquivo = rtrim(ini_get('session.save_path') ?: sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . "sess_$id";
        $conteudo = file_get_contents($arquivo);
        $novo = preg_replace('/' . preg_quote($chave, '/') . '\|i:\d+;/', "$chave|i:$valor;", $conteudo, 1, $trocas);
        $this->assertSame(1, $trocas, "a sessão precisa ter $chave");
        file_put_contents($arquivo, $novo);
    }

    protected function ultimaAcaoAuditada(): string
    {
        return (string) Database::getConexao()->query("SELECT TOP 1 ACAO + ' ' + ISNULL(DETALHE, '') FROM AUDITORIA ORDER BY AUDCOD DESC")->fetchColumn();
    }
}
