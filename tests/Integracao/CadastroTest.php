<?php
//cadastrarAluno() e gerarProximaMatricula() contra o banco de teste.
final class CadastroTest extends TesteComBanco
{
    public function testCadastroGravaOAlunoEAsInscricoes(): void
    {
        $matricula = $this->cadastrar(['modalidades' => [1, 3], 'plano' => 2, 'frequencia' => 3]);
        $linha = $this->linhaAluno($matricula);

        $this->assertSame('ALUNO TESTE', $linha['ALUNOME']);
        $this->assertSame('52998224725', $linha['ALUCPF']);
        $this->assertSame('61999990000', $linha['ALUTELEFONE']);
        $this->assertSame('aluno.teste@exemplo.invalid', $linha['ALUEMAIL']);
        $this->assertSame('M', $linha['ALUSEX']);
        $this->assertSame('1990-05-10', substr($linha['ALUDATANASC'], 0, 10));
        $this->assertNotNull($linha['DATACADASTRO'], 'data de cadastro preenchida pelo banco');
        $this->assertNotNull($linha['ALUCIENCIAPRIVACIDADE'], 'ciência da Política de Privacidade (LGPD art. 9)');
        $this->assertNull($linha['ALURESPNOME'], 'adulto não guarda responsável');

        $inscricoes = buscarInscricoes($matricula);
        $this->assertSame([1, 3], array_map('intval', array_column($inscricoes, 'ALUNOMOD')));
        foreach ($inscricoes as $inscricao) {
            $this->assertSame(2, (int) $inscricao['ALUNOPLANO']);
            $this->assertSame(3, (int) $inscricao['ALUNOOPCAO']);
        }
    }

    public function testPrimeiraMatriculaEh14001EAsSeguintesSaoSequenciais(): void
    {
        $this->assertSame('14001', $this->cadastrar());
        $this->assertSame('14002', $this->cadastrar(['cpf' => self::CPFS[1], 'email' => 'b@exemplo.invalid']));
    }

    public function testMatriculaContinuaDoMaiorNumeroExistente(): void
    {
        //como a base atual, que ja tem a matricula 26141312
        Database::getConexao()->exec("INSERT INTO ALUNOS (ALUMATRICULA, ALUNOME, ALUTELEFONE, ALUEMAIL, ALUCPF, ALUDATANASC, ALUSTATUS)
            VALUES ('26141312', 'PEDRO', '61999990009', 'pedro@exemplo.invalid', '" . self::CPFS[5] . "', '2003-02-14', 2)");
        $this->assertSame('26141313', $this->cadastrar());
    }

    public function testMenorGuardaOResponsavelLegal(): void
    {
        $matricula = $this->cadastrar([
            'dataNasc' => date('Y-m-d', strtotime('-10 years')),
            'responsavelNome' => 'ANA DA SILVA',
            'responsavelCpf' => self::CPFS[1],
        ]);
        $linha = $this->linhaAluno($matricula);
        $this->assertSame('ANA DA SILVA', $linha['ALURESPNOME']);
        $this->assertSame(self::CPFS[1], $linha['ALURESPCPF']);
    }

    public function testCpfRepetidoEhRecusado(): void
    {
        $this->cadastrar();
        $resultado = @cadastrarAluno('OUTRO', '61999990001', 'outro@exemplo.invalid', self::CPFS[0], 'F', '1991-01-01', '70000000', 'Rua 2', [1], 1, 1, 1);
        $this->assertFalse($resultado);
        $this->assertCount(1, listarAluno());
    }

    public function testEmailRepetidoEhRecusado(): void
    {
        $this->cadastrar();
        $resultado = @cadastrarAluno('OUTRO', '61999990001', 'aluno.teste@exemplo.invalid', self::CPFS[1], 'F', '1991-01-01', '70000000', 'Rua 2', [1], 1, 1, 1);
        $this->assertFalse($resultado);
    }

    public function testFalhaNaInscricaoDesfazOCadastroInteiro(): void
    {
        //modalidade 999 nao existe: a chave estrangeira falha depois que o aluno ja foi inserido
        $a = $this->alunoValido(['modalidades' => [1, 999]]);
        $resultado = @cadastrarAluno($a['nome'], $a['telefone'], $a['email'], $a['cpf'], $a['genero'], $a['dataNasc'], $a['cep'], $a['endereco'], $a['modalidades'], $a['plano'], $a['frequencia'], $a['status']);

        $this->assertFalse($resultado);
        $this->assertSame([], listarAluno(), 'nada pode sobrar do cadastro que falhou');
        $total = Database::getConexao()->query("SELECT COUNT(*) FROM ALUNOS_INSCRICAO")->fetchColumn();
        $this->assertSame(0, (int) $total);
    }

    public function testStatusInexistenteEhRecusado(): void
    {
        $a = $this->alunoValido(['status' => 999]);
        $this->assertFalse(@cadastrarAluno($a['nome'], $a['telefone'], $a['email'], $a['cpf'], $a['genero'], $a['dataNasc'], $a['cep'], $a['endereco'], $a['modalidades'], $a['plano'], $a['frequencia'], $a['status']));
    }

    public function testAlunoCadastradoJaInativoComecaAContarOPrazoDeGuarda(): void
    {
        //a retencao de 5 anos (DB/anonimizar_antigos.php) conta a partir de ALUDATAINATIVACAO
        $inativo = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'INATIVO');
        $matricula = $this->cadastrar(['status' => $inativo]);
        $this->assertNotNull($this->linhaAluno($matricula)['ALUDATAINATIVACAO']);
    }

    public function testNomeComAcentoEhGravadoSemCorromper(): void
    {
        $matricula = $this->cadastrar(['nome' => 'JOÃO GONÇALVES']);
        $this->assertSame('JOÃO GONÇALVES', buscarAluno($matricula)['ALUNOME']);
    }
}
