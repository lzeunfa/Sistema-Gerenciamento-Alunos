<?php
//Login (actions.php), bloqueio de paginas (trava.php), expiracao de sessao e logout.
final class LoginESessaoTest extends TesteFuncional
{
    // ---------- login ----------

    public function testLoginCorretoLevaAoSistema(): void
    {
        $this->entrar();
        $pagina = $this->nav->get('sistema.php');
        $this->assertSame(200, $pagina['status']);
        $this->assertStringContainsString('Total de alunos', $pagina['corpo']);
        $this->assertSame('login_ok ', $this->ultimaAcaoAuditada());
    }

    public function testLoginPeloEmailTambemFunciona(): void
    {
        $this->assertSame('/sistema.php', $this->destino($this->tentarLogin(self::EMAIL, self::SENHA)));
    }

    public function testCookieDeSessaoEhProtegido(): void
    {
        $cookie = implode("\n", $this->nav->get('index.php')['cabecalhos']['set-cookie'] ?? []);
        $this->assertStringContainsString('GKTSESSID=', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
    }

    public function testIdDaSessaoMudaNoLogin(): void
    {
        //protege contra fixacao de sessao: o id de antes do login nao vale depois
        $this->nav->get('index.php');
        $antes = $this->nav->cookie('GKTSESSID');
        $this->entrar();
        $this->assertNotSame($antes, $this->nav->cookie('GKTSESSID'));
    }

    public function testIdDeSessaoInventadoEhTrocado(): void
    {
        $resposta = (new Navegador())->get('index.php?x=1');
        $this->assertNotEmpty($resposta['cabecalhos']['set-cookie'] ?? []);
        $curl = curl_init(ServidorDeTeste::url('index.php'));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => 'GKTSESSID=idinventadopeloatacante123']);
        $cabecalhos = curl_exec($curl);
        curl_close($curl);
        $this->assertMatchesRegularExpression('/Set-Cookie: GKTSESSID=(?!idinventadopeloatacante123)/i', $cabecalhos);
    }

    public function testSenhaErradaEUsuarioInexistenteTemAMesmaMensagem(): void
    {
        $errada = $this->tentarLogin(self::LOGIN, 'senha-errada-qualquer');
        $this->assertSame('/index.php', $this->destino($errada));
        $msgErrada = Navegador::mensagem($this->nav->get('index.php')['corpo']);

        $this->tentarLogin('usuario_que_nao_existe', 'senha-errada-qualquer');
        $msgInexistente = Navegador::mensagem($this->nav->get('index.php')['corpo']);

        $this->assertSame('Usuário ou senha incorretos!', $msgErrada);
        $this->assertSame($msgErrada, $msgInexistente, 'não pode revelar quais usuários existem');
    }

    public function testLoginSemTokenVoltaAoLoginComExplicacao(): void
    {
        $resposta = $this->nav->post('BACKEND/CONTROLLER/actions.php', ['inputUser' => self::LOGIN, 'inputUserPass' => self::SENHA]);
        $this->assertSame('/index.php', $this->destino($resposta));
        $this->assertStringContainsString('aberta por muito tempo', Navegador::mensagem($this->nav->get('index.php')['corpo']));
        $this->assertSame(302, $this->nav->get('sistema.php')['status'], 'continua sem login');
    }

    public function testCamposDoLoginEnviadosComoListaSaoTratadosComoVazios(): void
    {
        //ferramentas de ataque mandam inputUser[]=x; antes isso causava TypeError no trim() (erro 500)
        $token = Navegador::token($this->nav->get('index.php')['corpo']);
        $resposta = $this->nav->post('BACKEND/CONTROLLER/actions.php', ['csrf_token' => $token, 'inputUser' => ['admin'], 'inputUserPass' => ['x']]);
        $this->assertSame(302, $resposta['status']);
        $this->assertSame('/index.php', $this->destino($resposta));
        $this->assertSame('Preencha usuário e senha!', Navegador::mensagem($this->nav->get('index.php')['corpo']));
    }

    public function testAbrirOActionsPeloNavegadorNaoFazLogin(): void
    {
        $this->assertSame('/index.php', $this->destino($this->nav->get('BACKEND/CONTROLLER/actions.php')));
    }

    public function testCincoErrosBloqueiamOLoginMesmoComASenhaCerta(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->tentarLogin(self::LOGIN, "errada-$i");
        }
        $resposta = $this->tentarLogin(self::LOGIN, self::SENHA);
        $this->assertSame('/index.php', $this->destino($resposta));
        $this->assertStringContainsString('Muitas tentativas', Navegador::mensagem($this->nav->get('index.php')['corpo']));
        $this->assertSame('login_bloqueado muitas tentativas', $this->ultimaAcaoAuditada());
    }

    public function testBloqueioDeUmLoginNaoAtrapalhaOutro(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->tentarLogin('outro_usuario', "errada-$i");
        }
        $this->assertSame('/sistema.php', $this->destino($this->tentarLogin(self::LOGIN, self::SENHA)));
    }

    public function testBloqueioPassaDepoisDe15Minutos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->tentarLogin(self::LOGIN, "errada-$i");
        }
        Database::getConexao()->exec("UPDATE LOGIN_TENTATIVAS SET DATAHORA = DATEADD(MINUTE, -16, DATAHORA)");
        $this->assertSame('/sistema.php', $this->destino($this->tentarLogin(self::LOGIN, self::SENHA)));
    }

    public function testVinteErrosNoMesmoIpBloqueiamTodosOsLogins(): void
    {
        for ($i = 0; $i < 20; $i++) {
            Database::getConexao()->exec("INSERT INTO LOGIN_TENTATIVAS (CHAVE) VALUES ('ip:127.0.0.1')");
        }
        $this->tentarLogin(self::LOGIN, self::SENHA);
        $this->assertStringContainsString('Muitas tentativas', Navegador::mensagem($this->nav->get('index.php')['corpo']));
    }

    public function testLoginCertoZeraAsTentativasErradas(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->tentarLogin(self::LOGIN, "errada-$i");
        }
        $this->entrar();
        $restantes = Database::getConexao()->query("SELECT COUNT(*) FROM LOGIN_TENTATIVAS WHERE CHAVE = 'login:" . self::LOGIN . "'")->fetchColumn();
        $this->assertSame(0, (int) $restantes);
    }

    public function testTentativasAntigasSaoApagadasParaATabelaNaoCrescerSemLimite(): void
    {
        //sob ataque de forca bruta prolongado a tabela cresceria para sempre
        $pdo = Database::getConexao();
        $pdo->exec("INSERT INTO LOGIN_TENTATIVAS (CHAVE, DATAHORA) SELECT TOP 500 'ip:10.0.0.1', DATEADD(DAY, -2, GETDATE()) FROM sys.all_objects");
        $this->tentarLogin(self::LOGIN, 'senha-errada-qualquer');
        $antigas = $pdo->query("SELECT COUNT(*) FROM LOGIN_TENTATIVAS WHERE DATAHORA < DATEADD(DAY, -1, GETDATE())")->fetchColumn();
        $this->assertSame(0, (int) $antigas);
        $recentes = $pdo->query("SELECT COUNT(*) FROM LOGIN_TENTATIVAS")->fetchColumn();
        $this->assertSame(2, (int) $recentes, 'a tentativa errada de agora continua contando (login e IP)');
    }

    public function testSenhaAntigaEmBcryptViraArgon2idNoLogin(): void
    {
        Database::getConexao()->prepare("UPDATE ADMINISTRACAO SET ADMSENHA = :h WHERE ADMLOGIN = :l")
            ->execute([':h' => password_hash(self::SENHA, PASSWORD_BCRYPT), ':l' => self::LOGIN]);
        $this->entrar();
        $hash = Database::getConexao()->query("SELECT ADMSENHA FROM ADMINISTRACAO WHERE ADMLOGIN = '" . self::LOGIN . "'")->fetchColumn();
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testQuemJaEstaLogadoVaiDiretoParaOSistema(): void
    {
        $this->entrar();
        $this->assertSame('/sistema.php', $this->destino($this->nav->get('index.php')));
    }

    // ---------- trava.php ----------

    public function testPaginasInternasExigemLogin(): void
    {
        foreach (['sistema.php', 'sistema.php?pagina=ALUNOS', 'sistema.php?pagina=CADASTRAR', 'sistema.php?pagina=TURMAS', 'sistema.php?pagina=EDITAR&matricula=1', 'sistema.php?pagina=EXCLUIR&matricula=1'] as $pagina) {
            $resposta = $this->nav->get($pagina);
            $this->assertSame(302, $resposta['status'], $pagina);
            $this->assertSame('/index.php', $this->destino($resposta), $pagina);
        }
    }

    public function testAcoesExigemLogin(): void
    {
        foreach (['gravarAluno.php', 'editarAluno.php', 'exportarAluno.php', 'anonimizarAluno.php'] as $acao) {
            $resposta = $this->nav->post("BACKEND/CONTROLLER/$acao", ['matricula' => '1', 'confirmar' => '1']);
            $this->assertSame('/index.php', $this->destino($resposta), $acao);
        }
    }

    public function testDezMinutosParadoDerrubaASessao(): void
    {
        $this->entrar();
        $this->alterarSessao('last_click', time() - 601);
        $resposta = $this->nav->get('sistema.php');
        $this->assertSame('/index.php?erro=sessao_expirada', $this->destino($resposta));
        $this->assertStringContainsString('expirou', Navegador::mensagem($this->nav->get('index.php?erro=sessao_expirada')['corpo']));
        $this->assertSame(302, $this->nav->get('sistema.php')['status'], 'precisa entrar de novo');
        $this->assertSame('sessao_expirada inatividade', Database::getConexao()->query("SELECT TOP 1 ACAO + ' ' + DETALHE FROM AUDITORIA WHERE ACAO = 'sessao_expirada' ORDER BY AUDCOD DESC")->fetchColumn());
    }

    public function testNoveMinutosParadoAindaEstaLogado(): void
    {
        $this->entrar();
        $this->alterarSessao('last_click', time() - 540);
        $this->assertSame(200, $this->nav->get('sistema.php')['status']);
    }

    public function testSessaoTemLimiteDe8HorasMesmoEmUso(): void
    {
        $this->entrar();
        $this->alterarSessao('login_time', time() - 8 * 3600 - 1);
        $this->assertSame('/index.php?erro=sessao_expirada', $this->destino($this->nav->get('sistema.php')));
    }

    // ---------- logout ----------

    public function testSairPeloBotaoEncerraASessao(): void
    {
        $this->entrar();
        $resposta = $this->nav->post('logout.php', ['csrf_token' => $this->tokenDaSessao()]);
        $this->assertSame('/index.php', $this->destino($resposta));
        $this->assertSame(302, $this->nav->get('sistema.php')['status']);
        $this->assertSame('logout ', Database::getConexao()->query("SELECT TOP 1 ACAO + ' ' FROM AUDITORIA WHERE ACAO = 'logout' ORDER BY AUDCOD DESC")->fetchColumn());
    }

    public function testLinkDeOutroSiteNaoConsegueDeslogar(): void
    {
        //um <img src=".../logout.php"> em outro site faria um GET sem token
        $this->entrar();
        $this->nav->get('logout.php');
        $this->assertSame(200, $this->nav->get('sistema.php')['status'], 'continua logado');
    }
}
