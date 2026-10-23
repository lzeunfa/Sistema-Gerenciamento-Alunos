<?php
//Telas e acoes do sistema como o administrador usa: navegar, cadastrar, editar, exportar e excluir.
final class TelasEAcoesTest extends TesteFuncional
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->entrar();
    }

    private function cadastrarPeloFormulario(array $mudancas = []): array
    {
        $campos = $this->formularioAluno($mudancas) + ['csrf_token' => $this->tokenDaSessao()];
        $resposta = $this->nav->post('BACKEND/CONTROLLER/gravarAluno.php', $campos);
        $this->assertSame('/sistema.php?pagina=CADASTRAR', $this->destino($resposta));
        return [$resposta, Navegador::mensagem($this->nav->get('sistema.php?pagina=CADASTRAR')['corpo'])];
    }

    private function matriculaCadastrada(): string
    {
        [, $mensagem] = $this->cadastrarPeloFormulario();
        $this->assertMatchesRegularExpression('/Matrícula (\d+)/', $mensagem);
        preg_match('/Matrícula (\d+)/', $mensagem, $m);
        return $m[1];
    }

    // ---------- navegacao ----------

    public function testTodasAsTelasAbrem(): void
    {
        $esperado = ['DASHBOARD' => 'Total de alunos', 'ALUNOS' => 'ESCOLHA UM FILTRO', 'CADASTRAR' => 'Cadastrar Novo Aluno', 'TURMAS' => 'Escolha um filtro'];
        foreach ($esperado as $pagina => $texto) {
            $resposta = $this->nav->get("sistema.php?pagina=$pagina");
            $this->assertSame(200, $resposta['status'], $pagina);
            $this->assertStringContainsStringIgnoringCase($texto, $resposta['corpo'], $pagina);
        }
    }

    public function testPaginasNaoMandamComentariosDeDesenvolvimentoAoNavegador(): void
    {
        //achado do OWASP ZAP ("Suspicious Comments"): comentarios ficam no PHP, nao no HTML
        $matricula = $this->matriculaCadastrada();
        $paginas = ['index.php', 'privacidade.php', 'sistema.php?pagina=DASHBOARD', 'sistema.php?pagina=ALUNOS',
            'sistema.php?pagina=CADASTRAR', 'sistema.php?pagina=TURMAS',
            "sistema.php?pagina=EDITAR&matricula=$matricula", "sistema.php?pagina=EXCLUIR&matricula=$matricula"];
        foreach ($paginas as $pagina) {
            $html = str_starts_with($pagina, 'sistema') ? $this->nav->get($pagina)['corpo'] : (new Navegador())->get($pagina)['corpo'];
            $this->assertStringNotContainsString('<!--', $html, $pagina);
        }
    }

    public function testPaginaDesconhecidaOuMaliciosaAbreODashboard(): void
    {
        foreach (['NAOEXISTE', '../../.env', '../trava', 'dashboard'] as $pagina) {
            $resposta = $this->nav->get('sistema.php?pagina=' . urlencode($pagina));
            $this->assertSame(200, $resposta['status'], $pagina);
            $this->assertStringContainsString('Total de alunos', $resposta['corpo'], $pagina);
            $this->assertStringNotContainsString('DB_PASSWORD', $resposta['corpo']);
        }
    }

    public function testDashboardMostraOsTotais(): void
    {
        $this->matriculaCadastrada();
        $html = $this->nav->get('sistema.php')['corpo'];
        $this->assertMatchesRegularExpression('/id="numTotalAlunos"[^>]*>1</', $html);
        $this->assertMatchesRegularExpression('/id="numTurmas"[^>]*>5</', $html);
        $this->assertMatchesRegularExpression('/id="numPendentes"[^>]*>1</', $html);
    }

    public function testTelasTemCabecalhosDeSeguranca(): void
    {
        $c = $this->nav->get('sistema.php')['cabecalhos'];
        $this->assertStringContainsString("default-src 'self'", $c['content-security-policy'][0] ?? '');
        $this->assertStringContainsString("frame-ancestors 'none'", $c['content-security-policy'][0] ?? '');
        $this->assertSame('DENY', $c['x-frame-options'][0] ?? null);
        $this->assertSame('nosniff', $c['x-content-type-options'][0] ?? null);
        $this->assertSame('no-store', $c['cache-control'][0] ?? null);
        $this->assertSame('require-corp', $c['cross-origin-embedder-policy'][0] ?? null);
        $this->assertArrayNotHasKey('x-powered-by', $c);
    }

    public function testLoginNaoCarregaNadaDeOutrosSites(): void
    {
        //fontes locais: sem Google Fonts (SRI impossivel e IP do visitante enviado ao Google)
        $resposta = (new Navegador())->get('index.php');
        $this->assertDoesNotMatchRegularExpression('/(href|src)="https?:\/\//', $resposta['corpo']);
        $this->assertStringNotContainsString('googleapis', $resposta['cabecalhos']['content-security-policy'][0] ?? '');
        $this->assertStringNotContainsString('gstatic', $resposta['cabecalhos']['content-security-policy'][0] ?? '');
        $this->assertSame(200, (new Navegador())->get('ASSETS/FONTES/fontes.css')['status']);
    }

    public function testPoliticaDePrivacidadeEhPublica(): void
    {
        $resposta = (new Navegador())->get('privacidade.php');
        $this->assertSame(200, $resposta['status']);
        $this->assertStringContainsString('Política de Privacidade', $resposta['corpo']);
        $this->assertStringContainsString('art. 18', $resposta['corpo']);
    }

    public function testFormularioDeCadastroListaAsOpcoesDoBanco(): void
    {
        $html = $this->nav->get('sistema.php?pagina=CADASTRAR')['corpo'];
        foreach (['KARATÊ', 'MUAY THAI', 'PERSONAL FIGHT', 'KICK FEM', 'KICKBOXING', 'Trimestral', 'Anual', '1x por semana', '5x por semana', 'PENDENTE', 'PAGO'] as $opcao) {
            $this->assertStringContainsString($opcao, $html);
        }
        $this->assertStringContainsString('name="csrf_token"', $html);
    }

    // ---------- cadastro ----------

    public function testCadastroValidoGravaEAvisa(): void
    {
        [, $mensagem] = $this->cadastrarPeloFormulario();
        $this->assertStringStartsWith('Aluno cadastrado com sucesso!', $mensagem);
        $alunos = listarAluno();
        $this->assertCount(1, $alunos);
        $this->assertSame('ALUNO FUNCIONAL', $alunos[0]['ALUNOME']);
        $this->assertSame('cadastro_aluno matricula ' . $alunos[0]['ALUMATRICULA'], $this->ultimaAcaoAuditada());
    }

    public function testCadastroInvalidoListaOsCamposENaoGrava(): void
    {
        [, $mensagem] = $this->cadastrarPeloFormulario(['cpf' => '111.111.111-11', 'email' => 'x', 'ciencia_privacidade' => '']);
        $this->assertStringContainsString('CPF', $mensagem);
        $this->assertStringContainsString('e-mail', $mensagem);
        $this->assertStringContainsString('Política de Privacidade', $mensagem);
        $this->assertSame([], listarAluno());
    }

    public function testCadastroDeMenorSemResponsavelEhRecusado(): void
    {
        [, $mensagem] = $this->cadastrarPeloFormulario(['data_nascimento' => date('Y-m-d', strtotime('-10 years'))]);
        $this->assertStringContainsString('responsável', $mensagem);
        $this->assertSame([], listarAluno());
    }

    public function testCpfRepetidoDaMensagemClara(): void
    {
        $this->cadastrarPeloFormulario();
        [, $mensagem] = $this->cadastrarPeloFormulario(['email' => 'outro@exemplo.invalid']);
        $this->assertStringContainsString('CPF ou e-mail pode já estar cadastrado', $mensagem);
        $this->assertCount(1, listarAluno());
    }

    public function testCadastroSemTokenNaoGrava(): void
    {
        //simula um formulario escondido em outro site enviando para o sistema (CSRF)
        $this->nav->post('BACKEND/CONTROLLER/gravarAluno.php', $this->formularioAluno());
        $this->assertSame([], listarAluno());
        $this->assertSame('csrf_invalido /BACKEND/CONTROLLER/gravarAluno.php', $this->ultimaAcaoAuditada());
    }

    public function testCodigoMaliciosoNoNomeApareceComoTexto(): void
    {
        $this->cadastrarPeloFormulario(['nome' => '<script>alert(1)</script>']);
        $html = $this->nav->get('sistema.php?pagina=ALUNOS')['corpo'];
        $this->assertStringNotContainsString('<SCRIPT>ALERT(1)</SCRIPT>', $html);
        $this->assertStringContainsString('&lt;SCRIPT&gt;ALERT(1)&lt;/SCRIPT&gt;', $html);
    }

    public function testListaMostraCpfMascarado(): void
    {
        $this->cadastrarPeloFormulario();
        foreach (['ALUNOS', 'TURMAS'] as $pagina) {
            $html = $this->nav->get("sistema.php?pagina=$pagina")['corpo'];
            $this->assertStringContainsString('***.982.***-**', $html, $pagina);
            $this->assertStringNotContainsString('52998224725', $html, $pagina);
        }
    }

    public function testPaginacaoNaoDesenhaUmBotaoPorPagina(): void
    {
        //achado do teste de volume: com 20 mil alunos a tela desenhava 500 botoes (251 KB por pagina)
        $pdo = Database::getConexao();
        $pdo->exec("WITH n AS (SELECT TOP 2000 ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS i FROM sys.all_objects a CROSS JOIN sys.all_objects b)
            INSERT INTO ALUNOS (ALUMATRICULA, ALUNOME, ALUTELEFONE, ALUEMAIL, ALUCPF, ALUDATANASC, ALUSTATUS)
            SELECT CAST(30000 + i AS varchar), 'VOLUME ' + CAST(i AS varchar), '61999990000', 'v' + CAST(i AS varchar) + '@exemplo.invalid',
                   RIGHT('00000000000' + CAST(i AS varchar), 11), '1990-01-01', 1 FROM n;
            INSERT INTO ALUNOS_INSCRICAO (ALUNOMAT, ALUNOMOD, ALUNOPLANO, ALUNOOPCAO) SELECT ALUMATRICULA, 1, 1, 1 FROM ALUNOS");

        foreach (['ALUNOS', 'TURMAS&filtrando=1'] as $tela) {
            $html = $this->nav->get("sistema.php?pagina=$tela&p=25")['corpo'];
            $this->assertLessThanOrEqual(11, substr_count($html, 'class="page-link"'), "$tela: no maximo 9 paginas + Anterior/Proxima");
            $this->assertStringContainsString('>50</a>', $html, "$tela: ultima pagina (2000 alunos / 40) aparece");
            $this->assertStringContainsString('…', $html);
            $this->assertLessThan(150 * 1024, strlen($html), "$tela: pagina leve");
        }
        $ultima = $this->nav->get('sistema.php?pagina=ALUNOS&p=999')['corpo'];
        $this->assertMatchesRegularExpression('/>\s*VOLUME 1\s*</', $ultima, 'pagina alem do fim mostra a ultima (alunos mais antigos)');
    }

    public function testFiltroDaTelaDeAlunos(): void
    {
        $this->cadastrarPeloFormulario(['nome' => 'homem', 'genero' => 'masculino']);
        $this->cadastrarPeloFormulario(['nome' => 'mulher', 'genero' => 'feminino', 'cpf' => '111.444.777-35', 'email' => 'm@exemplo.invalid']);
        $html = $this->nav->get('sistema.php?pagina=ALUNOS&filtrando=alunos_mulheres')['corpo'];
        $this->assertStringContainsString('MULHER', $html);
        $this->assertStringNotContainsString('>HOMEM<', preg_replace('/\s+/', '', $html));
    }

    // ---------- editar ----------

    public function testTelaDeEdicaoMostraOsDadosCompletos(): void
    {
        $matricula = $this->matriculaCadastrada();
        $html = $this->nav->get("sistema.php?pagina=EDITAR&matricula=$matricula")['corpo'];
        $this->assertStringContainsString('value="ALUNO FUNCIONAL"', $html);
        $this->assertStringContainsString('value="52998224725"', $html, 'na edição o CPF aparece inteiro');
    }

    public function testEdicaoSalvaERegistraAuditoria(): void
    {
        $matricula = $this->matriculaCadastrada();
        $campos = $this->formularioAluno(['nome' => 'nome corrigido', 'matricula' => $matricula, 'voltar' => 'TURMAS', 'csrf_token' => $this->tokenDaSessao()]);
        $resposta = $this->nav->post('BACKEND/CONTROLLER/editarAluno.php', $campos);

        $this->assertSame('/sistema.php?pagina=TURMAS', $this->destino($resposta));
        $this->assertSame('NOME CORRIGIDO', buscarAluno($matricula)['ALUNOME']);
        $this->assertSame("edicao_aluno matricula $matricula", $this->ultimaAcaoAuditada());
    }

    public function testEdicaoInvalidaVoltaParaAEdicao(): void
    {
        $matricula = $this->matriculaCadastrada();
        $campos = $this->formularioAluno(['telefone' => '1', 'matricula' => $matricula, 'csrf_token' => $this->tokenDaSessao()]);
        $resposta = $this->nav->post('BACKEND/CONTROLLER/editarAluno.php', $campos);
        $this->assertStringContainsString('pagina=EDITAR', $this->destino($resposta));
        $this->assertSame('61999990000', buscarAluno($matricula)['ALUTELEFONE']);
    }

    public function testEditarMatriculaInexistenteOuMaliciosa(): void
    {
        foreach (['99999999', "1' OR '1'='1"] as $matricula) {
            $campos = $this->formularioAluno(['matricula' => $matricula, 'csrf_token' => $this->tokenDaSessao()]);
            $resposta = $this->nav->post('BACKEND/CONTROLLER/editarAluno.php', $campos);
            $this->assertSame('/sistema.php?pagina=ALUNOS', $this->destino($resposta), $matricula);
        }
        $this->assertSame([], listarAluno());
    }

    public function testParametroVoltarNaoAceitaOutroDestino(): void
    {
        //evita redirecionar para pagina arbitraria (open redirect)
        $matricula = $this->matriculaCadastrada();
        $campos = $this->formularioAluno(['matricula' => $matricula, 'voltar' => 'https://site-malicioso.example', 'csrf_token' => $this->tokenDaSessao()]);
        $this->assertSame('/sistema.php?pagina=ALUNOS', $this->destino($this->nav->post('BACKEND/CONTROLLER/editarAluno.php', $campos)));
    }

    // ---------- exportar ----------

    public function testExportarBaixaUmJsonComOsDados(): void
    {
        $matricula = $this->matriculaCadastrada();
        $resposta = $this->nav->post('BACKEND/CONTROLLER/exportarAluno.php', ['matricula' => $matricula, 'csrf_token' => $this->tokenDaSessao()]);

        $this->assertSame(200, $resposta['status']);
        $this->assertStringStartsWith('application/json', $resposta['cabecalhos']['content-type'][0] ?? '');
        $this->assertStringContainsString("attachment; filename=\"dados-aluno-$matricula-", $resposta['cabecalhos']['content-disposition'][0] ?? '');
        $dados = json_decode($resposta['corpo'], true);
        $this->assertSame('ALUNO FUNCIONAL', $dados['aluno']['nome']);
        $this->assertSame("exportacao_aluno matricula $matricula", $this->ultimaAcaoAuditada());
    }

    public function testExportarPorLinkSemTokenNaoBaixa(): void
    {
        $matricula = $this->matriculaCadastrada();
        $resposta = $this->nav->post('BACKEND/CONTROLLER/exportarAluno.php', ['matricula' => $matricula]);
        $this->assertSame(302, $resposta['status']);
        $this->assertStringNotContainsString('52998224725', $resposta['corpo']);
    }

    // ---------- excluir (anonimizar) ----------

    public function testExcluirSemConfirmarNaoApaga(): void
    {
        $matricula = $this->matriculaCadastrada();
        $resposta = $this->nav->post('BACKEND/CONTROLLER/anonimizarAluno.php', ['matricula' => $matricula, 'csrf_token' => $this->tokenDaSessao()]);
        $this->assertStringContainsString('pagina=EXCLUIR', $this->destino($resposta));
        $this->assertSame('ALUNO FUNCIONAL', buscarAluno($matricula)['ALUNOME']);
    }

    public function testExcluirConfirmadoAnonimizaEBloqueiaEdicaoEExportacao(): void
    {
        $matricula = $this->matriculaCadastrada();
        $token = $this->tokenDaSessao();
        $this->nav->post('BACKEND/CONTROLLER/anonimizarAluno.php', ['matricula' => $matricula, 'confirmar' => '1', 'csrf_token' => $token]);

        $this->assertSame('ANONIMIZADO', buscarAluno($matricula)['ALUNOME']);
        $this->assertSame("anonimizacao_aluno matricula $matricula", $this->ultimaAcaoAuditada());

        $exportar = $this->nav->post('BACKEND/CONTROLLER/exportarAluno.php', ['matricula' => $matricula, 'csrf_token' => $token]);
        $this->assertSame(302, $exportar['status'], 'anonimizado não pode ser exportado');
        $editar = $this->nav->post('BACKEND/CONTROLLER/editarAluno.php', $this->formularioAluno(['matricula' => $matricula, 'csrf_token' => $token]));
        $this->assertSame('/sistema.php?pagina=ALUNOS', $this->destino($editar));
        $this->assertSame('ANONIMIZADO', buscarAluno($matricula)['ALUNOME']);
    }

    public function testListaMostraOsBotoesDeAcao(): void
    {
        $this->matriculaCadastrada();
        $html = $this->nav->get('sistema.php?pagina=ALUNOS')['corpo'];
        $this->assertStringContainsString('btn-edit', $html);
        $this->assertStringContainsString('exportarAluno.php', $html);
        $this->assertStringContainsString('btn-delete', $html);
    }
}
