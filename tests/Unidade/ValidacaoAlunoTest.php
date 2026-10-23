<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

//Regras de validacao do formulario de aluno (BACKEND/CONTROLLER/funcoes.php). Nao usa banco.
final class ValidacaoAlunoTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
    }

    // ---------- cpfValido ----------

    public static function cpfsValidos(): array
    {
        return [['52998224725'], ['11144477735'], ['12345678909'], ['98765432100'], ['39053344705'], ['71428793860']];
    }

    #[DataProvider('cpfsValidos')]
    public function testCpfComDigitosVerificadoresCorretosEValido(string $cpf): void
    {
        $this->assertTrue(cpfValido($cpf));
    }

    public static function cpfsInvalidos(): array
    {
        return [
            'digito errado' => ['52998224724'],
            'segundo digito errado' => ['52998224735'],
            'todos iguais' => ['11111111111'],
            'zeros' => ['00000000000'],
            'curto' => ['5299822472'],
            'longo' => ['529982247250'],
            'com pontuacao' => ['529.982.247-25'],
            'vazio' => [''],
            'letras' => ['5299822472a'],
        ];
    }

    #[DataProvider('cpfsInvalidos')]
    public function testCpfInvalidoERecusado(string $cpf): void
    {
        $this->assertFalse(cpfValido($cpf));
    }

    // ---------- soDigitos / idsInteiros / matriculaValida / campo ----------

    public function testSoDigitosRemoveTudoQueNaoENumero(): void
    {
        $this->assertSame('52998224725', soDigitos('529.982.247-25'));
        $this->assertSame('61999990000', soDigitos('(61) 99999-0000'));
        $this->assertSame('', soDigitos('abc'));
    }

    public function testIdsInteirosAceitaSoNumerosSemRepetir(): void
    {
        $this->assertSame([1, 3], idsInteiros(['1', '3', '1']));
        $this->assertSame([2], idsInteiros(['2', '1; DROP TABLE ALUNOS', '-1', '2.5', ['3'], 4]));
        $this->assertSame([], idsInteiros('1'));
        $this->assertSame([], idsInteiros(null));
    }

    public function testMatriculaValidaAceitaLetrasNumerosEHifen(): void
    {
        $this->assertSame('26141312', matriculaValida('26141312'));
        $this->assertSame('ADM-01', matriculaValida('ADM-01'));
    }

    public function testMatriculaValidaRecusaEntradaPerigosaOuMalFormada(): void
    {
        foreach (["1' OR '1'='1", '../etc', '', str_repeat('1', 21), '12 34', "1\n"] as $ruim) {
            $this->assertNull(matriculaValida($ruim), "deveria recusar: " . json_encode($ruim));
        }
        $this->assertNull(matriculaValida(['123']));
        $this->assertNull(matriculaValida(123));
        $this->assertNull(matriculaValida(null));
    }

    public function testCampoTiraEspacosEIgnoraArrays(): void
    {
        $_POST = ['nome' => '  Maria  ', 'lista' => ['x'], 'numero' => 5];
        $this->assertSame('Maria', campo('nome'));
        $this->assertSame('', campo('lista'));
        $this->assertSame('', campo('numero'));
        $this->assertSame('', campo('inexistente'));
    }

    // ---------- filtroAlunos (telas Alunos e Turmas) ----------

    public function testFiltrosConhecidosViramClausulaFixa(): void
    {
        $this->assertSame([" WHERE S.STATUSALU = 'PAGO' ", []], filtroAlunos('alunos_pagos'));
        $this->assertSame([" WHERE O.OPCAO LIKE '%3X%' ", []], filtroAlunos('plano_3'));
    }

    public function testModalidadeVaiComoParametroENaoNoTextoDoSql(): void
    {
        [$where, $parametros] = filtroAlunos('4');
        $this->assertStringNotContainsString('4', $where);
        $this->assertSame([':modalidade' => 4], $parametros);
    }

    public function testFiltroDesconhecidoOuMaliciosoNaoFiltraNada(): void
    {
        foreach (['', 'qualquer', "1 OR 1=1", "1; DROP TABLE ALUNOS", '1.5', '-1', ' 1', ['1'], null] as $filtro) {
            $this->assertSame(['', []], filtroAlunos($filtro), json_encode($filtro));
        }
    }

    // ---------- lerAlunoDoPost ----------

    private function postValido(array $mudancas = []): array
    {
        return array_merge([
            'nome' => 'maria da silva',
            'telefone' => '(61) 99999-0000',
            'email' => 'Maria.Silva@Exemplo.COM',
            'cpf' => '529.982.247-25',
            'data_nascimento' => '1990-05-10',
            'genero' => 'feminino',
            'cep' => '70000-000',
            'endereco' => 'Rua Ficticia 1',
            'modalidades' => ['1', '3'],
            'plano-pagamento' => '1',
            'frequencia-dias' => '2',
            'status' => '1',
        ], $mudancas);
    }

    private function errosPara(array $mudancas): array
    {
        $_POST = $this->postValido($mudancas);
        return lerAlunoDoPost()[1];
    }

    public function testAlunoAdultoValidoNaoTemErrosEEhNormalizado(): void
    {
        $_POST = $this->postValido();
        [$aluno, $erros] = lerAlunoDoPost();

        $this->assertSame([], $erros);
        $this->assertSame('MARIA DA SILVA', $aluno['nome']);
        $this->assertSame('61999990000', $aluno['telefone']);
        $this->assertSame('maria.silva@exemplo.com', $aluno['email']);
        $this->assertSame('52998224725', $aluno['cpf']);
        $this->assertSame('F', $aluno['genero']);
        $this->assertSame('70000000', $aluno['cep']);
        $this->assertSame([1, 3], $aluno['modalidades']);
        $this->assertSame(1, $aluno['plano']);
        $this->assertSame(2, $aluno['frequencia']);
        $this->assertSame(1, $aluno['status']);
    }

    public function testNomeComAcentoViraMaiusculoSemPerderAcento(): void
    {
        $_POST = $this->postValido(['nome' => 'joão gonçalves']);
        $this->assertSame('JOÃO GONÇALVES', lerAlunoDoPost()[0]['nome']);
    }

    public static function camposInvalidos(): array
    {
        return [
            'nome curto' => [['nome' => 'ab'], 'nome'],
            'nome longo' => [['nome' => str_repeat('a', 101)], 'nome'],
            'nome como lista' => [['nome' => ['x']], 'nome'],
            'telefone curto' => [['telefone' => '123456789'], 'telefone'],
            'telefone longo' => [['telefone' => '619999900001'], 'telefone'],
            'email invalido' => [['email' => 'nao-e-email'], 'e-mail'],
            'email longo' => [['email' => str_repeat('a', 95) . '@x.com'], 'e-mail'],
            'cpf invalido' => [['cpf' => '111.111.111-11'], 'CPF'],
            'nascimento no futuro' => [['data_nascimento' => date('Y-m-d', strtotime('+1 day'))], 'data de nascimento'],
            'data inexistente' => [['data_nascimento' => '2001-02-30'], 'data de nascimento'],
            'data em outro formato' => [['data_nascimento' => '10/05/1990'], 'data de nascimento'],
            'mais de 120 anos' => [['data_nascimento' => date('Y-m-d', strtotime('-121 years'))], 'data de nascimento'],
            'genero desconhecido' => [['genero' => 'outro'], 'gênero'],
            'cep curto' => [['cep' => '7000-000'], 'CEP'],
            'endereco vazio' => [['endereco' => '   '], 'endereço'],
            'endereco longo' => [['endereco' => str_repeat('a', 151)], 'endereço'],
            'sem modalidade' => [['modalidades' => []], 'modalidade'],
            'modalidade so com lixo' => [['modalidades' => ['x', '-1']], 'modalidade'],
            'plano com injecao' => [['plano-pagamento' => '1; DROP TABLE ALUNOS'], 'plano'],
            'plano zero' => [['plano-pagamento' => '0'], 'plano'],
            'frequencia vazia' => [['frequencia-dias' => ''], 'frequência'],
            'status negativo' => [['status' => '-1'], 'status'],
        ];
    }

    #[DataProvider('camposInvalidos')]
    public function testCampoInvalidoApareceNaListaDeErros(array $mudanca, string $erroEsperado): void
    {
        $this->assertContains($erroEsperado, $this->errosPara($mudanca));
    }

    public function testMenorSemResponsavelEhRecusado(): void
    {
        $erros = $this->errosPara(['data_nascimento' => date('Y-m-d', strtotime('-10 years'))]);
        $this->assertContains('nome do responsável', $erros);
        $this->assertContains('CPF do responsável', $erros);
    }

    public function testMenorComResponsavelValidoEhAceitoEGuardaOResponsavel(): void
    {
        $_POST = $this->postValido([
            'data_nascimento' => date('Y-m-d', strtotime('-10 years')),
            'responsavel_nome' => 'ana da silva',
            'responsavel_cpf' => '111.444.777-35',
        ]);
        [$aluno, $erros] = lerAlunoDoPost();
        $this->assertSame([], $erros);
        $this->assertSame('ANA DA SILVA', $aluno['responsavelNome']);
        $this->assertSame('11144477735', $aluno['responsavelCpf']);
    }

    public function testMenorComCpfDoResponsavelInvalidoEhRecusado(): void
    {
        $erros = $this->errosPara([
            'data_nascimento' => date('Y-m-d', strtotime('-10 years')),
            'responsavel_nome' => 'ana da silva',
            'responsavel_cpf' => '123',
        ]);
        $this->assertSame(['CPF do responsável'], $erros);
    }

    public function testQuemFaz18AnosHojeEhAdulto(): void
    {
        $erros = $this->errosPara(['data_nascimento' => date('Y-m-d', strtotime('-18 years'))]);
        $this->assertSame([], $erros);
    }

    public function testQuemFaz18AnosAmanhaAindaEhMenor(): void
    {
        $erros = $this->errosPara(['data_nascimento' => date('Y-m-d', strtotime('-18 years +1 day'))]);
        $this->assertContains('nome do responsável', $erros);
    }

    public function testAdultoNaoGuardaDadosDeResponsavel(): void
    {
        //minimizacao (LGPD art. 6, III): responsavel so e necessario para menores
        $_POST = $this->postValido(['responsavel_nome' => 'ana', 'responsavel_cpf' => '11144477735']);
        [$aluno, $erros] = lerAlunoDoPost();
        $this->assertSame([], $erros);
        $this->assertSame('', $aluno['responsavelNome']);
        $this->assertSame('', $aluno['responsavelCpf']);
    }

    public function testFormularioVazioAcusaTodosOsCamposObrigatorios(): void
    {
        $_POST = [];
        $erros = lerAlunoDoPost()[1];
        foreach (['nome', 'telefone', 'e-mail', 'CPF', 'data de nascimento', 'gênero', 'CEP', 'endereço', 'modalidade', 'plano', 'frequência', 'status'] as $campo) {
            $this->assertContains($campo, $erros);
        }
    }
}
