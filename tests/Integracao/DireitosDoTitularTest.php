<?php
//Edicao (art. 18, III), exportacao (art. 18, II e V) e anonimizacao (art. 16 e 18, IV/VI).
final class DireitosDoTitularTest extends TesteComBanco
{
    // ---------- buscar ----------

    public function testBuscarAlunoExistenteEInexistente(): void
    {
        $matricula = $this->cadastrar();
        $this->assertSame('ALUNO TESTE', buscarAluno($matricula)['ALUNOME']);
        $this->assertSame('PENDENTE', buscarAluno($matricula)['STATUSALU']);
        $this->assertNull(buscarAluno('99999999'));
    }

    // ---------- atualizarAluno ----------

    public function testEdicaoAtualizaOsDados(): void
    {
        $matricula = $this->cadastrar();
        $novo = $this->alunoValido(['nome' => 'NOME CORRIGIDO', 'telefone' => '61988887777', 'email' => 'novo@exemplo.invalid', 'cep' => '71000000', 'genero' => 'F']);

        $this->assertSame('ok', atualizarAluno($matricula, $novo, false));
        $linha = $this->linhaAluno($matricula);
        $this->assertSame('NOME CORRIGIDO', $linha['ALUNOME']);
        $this->assertSame('61988887777', $linha['ALUTELEFONE']);
        $this->assertSame('novo@exemplo.invalid', $linha['ALUEMAIL']);
        $this->assertSame('71000000', $linha['ALUCEP']);
        $this->assertSame('F', $linha['ALUSEX']);
    }

    public function testEdicaoTrocaAsModalidadesEAplicaPlanoEFrequenciaATodas(): void
    {
        $matricula = $this->cadastrar(['modalidades' => [1, 3]]);
        $this->assertSame('ok', atualizarAluno($matricula, $this->alunoValido(['modalidades' => [3, 4], 'plano' => 3, 'frequencia' => 5]), false));

        $inscricoes = buscarInscricoes($matricula);
        $modalidades = array_map('intval', array_column($inscricoes, 'ALUNOMOD'));
        sort($modalidades);
        $this->assertSame([3, 4], $modalidades, 'saiu a 1, ficou a 3, entrou a 4');
        foreach ($inscricoes as $inscricao) {
            $this->assertSame(3, (int) $inscricao['ALUNOPLANO']);
            $this->assertSame(5, (int) $inscricao['ALUNOOPCAO']);
        }
    }

    public function testInscricaoMantidaConservaADataOriginal(): void
    {
        $matricula = $this->cadastrar(['modalidades' => [1]]);
        Database::getConexao()->exec("UPDATE ALUNOS_INSCRICAO SET DATACADASTRO = '2025-01-01' WHERE ALUNOMAT = '$matricula'");
        atualizarAluno($matricula, $this->alunoValido(['modalidades' => [1, 2]]), false);
        $datas = array_column(buscarInscricoes($matricula), 'DATACADASTRO', 'ALUNOMOD');
        $this->assertStringStartsWith('2025-01-01', $datas[1]);
    }

    public function testPassarParaInativoMarcaADataEVoltarLimpa(): void
    {
        $inativo = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'INATIVO');
        $pago = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'PAGO');
        $matricula = $this->cadastrar();

        atualizarAluno($matricula, $this->alunoValido(['status' => $inativo]), false);
        $data = $this->linhaAluno($matricula)['ALUDATAINATIVACAO'];
        $this->assertNotNull($data);

        atualizarAluno($matricula, $this->alunoValido(['status' => $inativo, 'nome' => 'OUTRO NOME']), false);
        $this->assertSame($data, $this->linhaAluno($matricula)['ALUDATAINATIVACAO'], 'editar um inativo não reinicia o prazo');

        atualizarAluno($matricula, $this->alunoValido(['status' => $pago]), false);
        $this->assertNull($this->linhaAluno($matricula)['ALUDATAINATIVACAO'], 'voltou a treinar');
    }

    public function testCienciaDaPrivacidadeSoEhGravadaUmaVezENuncaApagada(): void
    {
        $matricula = $this->cadastrar();
        Database::getConexao()->exec("UPDATE ALUNOS SET ALUCIENCIAPRIVACIDADE = NULL WHERE ALUMATRICULA = '$matricula'");

        atualizarAluno($matricula, $this->alunoValido(), false);
        $semCiencia = $this->linhaAluno($matricula)['ALUCIENCIAPRIVACIDADE'];
        $this->assertNull($semCiencia);

        atualizarAluno($matricula, $this->alunoValido(), true);
        $primeira = $this->linhaAluno($matricula)['ALUCIENCIAPRIVACIDADE'];
        $this->assertNotNull($primeira);

        atualizarAluno($matricula, $this->alunoValido(), false);
        $this->assertSame($primeira, $this->linhaAluno($matricula)['ALUCIENCIAPRIVACIDADE']);
    }

    public function testEdicaoGuardaOuLimpaOResponsavel(): void
    {
        $matricula = $this->cadastrar();
        atualizarAluno($matricula, $this->alunoValido(['responsavelNome' => 'ANA', 'responsavelCpf' => self::CPFS[1]]), false);
        $this->assertSame('ANA', $this->linhaAluno($matricula)['ALURESPNOME']);
        atualizarAluno($matricula, $this->alunoValido(), false);
        $this->assertNull($this->linhaAluno($matricula)['ALURESPNOME']);
        $this->assertNull($this->linhaAluno($matricula)['ALURESPCPF']);
    }

    public function testCpfDeOutroAlunoEhRecusadoSemAlterarNada(): void
    {
        $this->cadastrar();
        $segundo = $this->cadastrar(['cpf' => self::CPFS[1], 'email' => 'b@exemplo.invalid', 'nome' => 'SEGUNDO']);

        $this->assertSame('duplicado', atualizarAluno($segundo, $this->alunoValido(['nome' => 'MUDOU', 'email' => 'b@exemplo.invalid']), false));
        $this->assertSame('SEGUNDO', $this->linhaAluno($segundo)['ALUNOME'], 'a transação foi desfeita');
    }

    public function testEmailDeOutroAlunoEhRecusado(): void
    {
        $this->cadastrar();
        $segundo = $this->cadastrar(['cpf' => self::CPFS[1], 'email' => 'b@exemplo.invalid']);
        $this->assertSame('duplicado', atualizarAluno($segundo, $this->alunoValido(['cpf' => self::CPFS[1]]), false));
    }

    public function testModalidadeInexistenteEhErroENaoDuplicado(): void
    {
        //a chave estrangeira tambem devolve SQLSTATE 23000; a tela nao pode dizer "CPF ou e-mail ja pertence a outro aluno"
        $matricula = $this->cadastrar();
        $this->assertSame('erro', atualizarAluno($matricula, $this->alunoValido(['modalidades' => [999]]), false));
        $this->assertCount(1, buscarInscricoes($matricula), 'as inscrições antigas continuam');
    }

    public function testEditarAlunoInexistenteOuAnonimizadoEhErro(): void
    {
        $this->assertSame('erro', atualizarAluno('99999999', $this->alunoValido(), false));
        $matricula = $this->cadastrar();
        anonimizarAluno($matricula);
        $this->assertSame('erro', atualizarAluno($matricula, $this->alunoValido(['cpf' => self::CPFS[2], 'email' => 'x@exemplo.invalid']), false));
        $this->assertSame('ANONIMIZADO', $this->linhaAluno($matricula)['ALUNOME']);
    }

    // ---------- exportacao ----------

    public function testExportacaoTrazTodosOsDadosEInscricoes(): void
    {
        $matricula = $this->cadastrar(['modalidades' => [1, 3]]);
        $dados = dadosExportacaoAluno($matricula);

        $this->assertSame($matricula, $dados['aluno']['matricula']);
        $this->assertSame('ALUNO TESTE', $dados['aluno']['nome']);
        $this->assertSame(self::CPFS[0], $dados['aluno']['cpf']);
        $this->assertSame('aluno.teste@exemplo.invalid', $dados['aluno']['email']);
        $this->assertSame('PENDENTE', $dados['aluno']['situacao']);
        $this->assertNull($dados['aluno']['responsavel_legal']);
        $this->assertNotNull($dados['aluno']['ciencia_politica_privacidade']);
        $this->assertCount(2, $dados['inscricoes']);
        $this->assertSame(['modalidade', 'plano', 'frequencia', 'data_inscricao'], array_keys($dados['inscricoes'][0]));
        $this->assertStringContainsString('art. 18', $dados['base_legal']);
        $this->assertNotFalse(json_encode($dados, JSON_UNESCAPED_UNICODE), 'precisa virar JSON');
    }

    public function testExportacaoDeMenorIncluiOResponsavel(): void
    {
        $matricula = $this->cadastrar(['dataNasc' => date('Y-m-d', strtotime('-10 years')), 'responsavelNome' => 'ANA', 'responsavelCpf' => self::CPFS[1]]);
        $this->assertSame(['nome' => 'ANA', 'cpf' => self::CPFS[1]], dadosExportacaoAluno($matricula)['aluno']['responsavel_legal']);
    }

    public function testExportacaoDeAlunoInexistente(): void
    {
        $this->assertNull(dadosExportacaoAluno('99999999'));
    }

    // ---------- anonimizacao ----------

    public function testAnonimizacaoApagaDadosPessoaisEMantemEstatistica(): void
    {
        $matricula = $this->cadastrar(['modalidades' => [1, 3], 'dataNasc' => '1990-05-10', 'responsavelNome' => 'ANA', 'responsavelCpf' => self::CPFS[1]]);
        $this->assertTrue(anonimizarAluno($matricula));

        $linha = $this->linhaAluno($matricula);
        $this->assertSame('ANONIMIZADO', $linha['ALUNOME']);
        $this->assertStringNotContainsString(self::CPFS[0], $linha['ALUCPF']);
        $this->assertStringStartsWith('X', $linha['ALUCPF']);
        $this->assertStringNotContainsString('aluno.teste', $linha['ALUEMAIL']);
        $this->assertStringNotContainsString('61999990000', $linha['ALUTELEFONE']);
        $this->assertStringNotContainsString('Ficticia', $linha['ALUENDERECO']);
        $this->assertNull($linha['ALUCEP']);
        $this->assertNull($linha['ALURESPNOME']);
        $this->assertNull($linha['ALURESPCPF']);
        $this->assertSame('1990-01-01', substr($linha['ALUDATANASC'], 0, 10), 'só o ano fica');
        $this->assertSame('M', $linha['ALUSEX'], 'sexo fica para estatística');
        $this->assertNotNull($linha['ALUANONIMIZADO']);
        $this->assertNotNull($linha['ALUDATAINATIVACAO']);
        $this->assertSame('INATIVO', buscarAluno($matricula)['STATUSALU']);
        $this->assertCount(2, buscarInscricoes($matricula), 'inscrições ficam');
    }

    public function testAnonimizarDuasVezesNaoFazNada(): void
    {
        $matricula = $this->cadastrar();
        $this->assertTrue(anonimizarAluno($matricula));
        $email = $this->linhaAluno($matricula)['ALUEMAIL'];
        $this->assertFalse(anonimizarAluno($matricula));
        $this->assertSame($email, $this->linhaAluno($matricula)['ALUEMAIL']);
    }

    public function testVariosAnonimizadosNaoViolamCpfEEmailUnicos(): void
    {
        $matriculas = [];
        foreach (array_slice(self::CPFS, 0, 4) as $i => $cpf) {
            $matriculas[] = $this->cadastrar(['cpf' => $cpf, 'email' => "a$i@exemplo.invalid"]);
        }
        foreach ($matriculas as $matricula) {
            $this->assertTrue(anonimizarAluno($matricula));
        }
        $this->assertCount(4, array_unique(array_column(listarAluno(), 'ALUCPF')));
    }

    public function testCpfAnonimizadoCabeNaColunaENaoEhUmCpfValido(): void
    {
        $matricula = $this->cadastrar();
        anonimizarAluno($matricula);
        $cpf = $this->linhaAluno($matricula)['ALUCPF'];
        $this->assertSame(11, strlen($cpf));
        $this->assertFalse(cpfValido($cpf));
        $this->assertSame('***', mascararCpf($cpf));
    }

    public function testAnonimizarInexistenteDevolveFalso(): void
    {
        $this->assertFalse(anonimizarAluno('99999999'));
    }

    public function testDepoisDeAnonimizarONovoCadastroComOMesmoCpfFunciona(): void
    {
        //o titular pode voltar a ser aluno depois de pedir a eliminacao
        $matricula = $this->cadastrar();
        anonimizarAluno($matricula);
        $this->assertNotSame($matricula, $this->cadastrar());
    }
}
