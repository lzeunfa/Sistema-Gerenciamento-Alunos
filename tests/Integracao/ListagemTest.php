<?php
//Listas de apoio, lista de alunos com filtros, contagem e paginacao (telas Alunos, Turmas e Dashboard).
final class ListagemTest extends TesteComBanco
{
    public function testModalidadesDaAcademia(): void
    {
        $nomes = array_column(listarModalidades(), 'NOMEMOD');
        sort($nomes);
        $this->assertSame(['KARATÊ', 'KICK FEM', 'KICKBOXING', 'MUAY THAI', 'PERSONAL FIGHT'], $nomes);
    }

    public function testPlanos(): void
    {
        $this->assertSame(['Trimestral', 'Semestral', 'Anual'], array_column(listarPlano(), 'PLANO'));
    }

    public function testFrequenciasEmOrdemDe1a5(): void
    {
        $this->assertSame(['1x por semana', '2x por semana', '3x por semana', '4x por semana', '5x por semana'], array_column(listarFrequencia(), 'OPCAO'));
    }

    public function testSituacoes(): void
    {
        $this->assertSame(['PENDENTE', 'PAGO', 'INATIVO'], array_column(listarStatus(), 'STATUSALU'));
    }

    //cria um conjunto variado de alunos e devolve as matriculas por apelido
    private function popular(): array
    {
        $pago = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'PAGO');
        $pendente = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'PENDENTE');
        $inativo = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'INATIVO');
        $f2 = $this->codigo('OPCAOSEMANA', 'OPCAOCOD', 'OPCAO', '2x por semana');
        $f3 = $this->codigo('OPCAOSEMANA', 'OPCAOCOD', 'OPCAO', '3x por semana');
        $f4 = $this->codigo('OPCAOSEMANA', 'OPCAOCOD', 'OPCAO', '4x por semana');
        $muay = $this->codigo('MODALIDADES', 'MODALIDADECOD', 'NOMEMOD', 'MUAY THAI');
        $karate = $this->codigo('MODALIDADES', 'MODALIDADECOD', 'NOMEMOD', 'KARATÊ');

        return [
            'homemPago2x' => $this->cadastrar(['nome' => 'HOMEM PAGO', 'genero' => 'M', 'status' => $pago, 'frequencia' => $f2, 'modalidades' => [$muay, $karate], 'cpf' => self::CPFS[0], 'email' => 'a@exemplo.invalid']),
            'mulherPendente3x' => $this->cadastrar(['nome' => 'MULHER PENDENTE', 'genero' => 'F', 'status' => $pendente, 'frequencia' => $f3, 'modalidades' => [$muay], 'cpf' => self::CPFS[1], 'email' => 'b@exemplo.invalid']),
            'mulherInativa4x' => $this->cadastrar(['nome' => 'MULHER INATIVA', 'genero' => 'F', 'status' => $inativo, 'frequencia' => $f4, 'modalidades' => [$karate], 'cpf' => self::CPFS[2], 'email' => 'c@exemplo.invalid']),
            'muay' => (string) $muay,
            'karate' => (string) $karate,
        ];
    }

    private function nomes(string $filtro): array
    {
        $nomes = array_column(listarAlunosCompletos($filtro), 'ALUNOME');
        sort($nomes);
        return $nomes;
    }

    public function testSemFiltroMostraTodosUmaVezSoMesmoComVariasModalidades(): void
    {
        $this->popular();
        $lista = listarAlunosCompletos();
        $this->assertCount(3, $lista);
        $homem = array_values(array_filter($lista, fn($a) => $a['ALUNOME'] === 'HOMEM PAGO'))[0];
        $artes = explode(', ', $homem['ARTES']);
        sort($artes);
        $this->assertSame(['KARATÊ', 'MUAY THAI'], $artes);
        $this->assertSame(3, contarAlunosCompletos());
    }

    public function testListaVemDaMatriculaMaisNovaParaAMaisAntiga(): void
    {
        $m = $this->popular();
        $this->assertSame([$m['mulherInativa4x'], $m['mulherPendente3x'], $m['homemPago2x']], array_column(listarAlunosCompletos(), 'ALUMATRICULA'));
    }

    public function testFiltros(): void
    {
        $m = $this->popular();
        $this->assertSame(['HOMEM PAGO'], $this->nomes('alunos_pagos'));
        $this->assertSame(['MULHER PENDENTE'], $this->nomes('alunos_pendentes'), 'pendentes não inclui inativos');
        $this->assertSame(['HOMEM PAGO'], $this->nomes('alunos_homens'));
        $this->assertSame(['MULHER INATIVA', 'MULHER PENDENTE'], $this->nomes('alunos_mulheres'));
        $this->assertSame(['HOMEM PAGO'], $this->nomes('plano_2'));
        $this->assertSame(['MULHER PENDENTE'], $this->nomes('plano_3'));
        $this->assertSame(['MULHER INATIVA'], $this->nomes('plano_4'));
        $this->assertSame(['HOMEM PAGO', 'MULHER PENDENTE'], $this->nomes($m['muay']), 'filtro de turma por modalidade');
        $this->assertSame(['HOMEM PAGO', 'MULHER INATIVA'], $this->nomes($m['karate']));
    }

    public function testContagemBateComALista(): void
    {
        $m = $this->popular();
        foreach (['', 'alunos_pagos', 'alunos_pendentes', 'alunos_homens', 'alunos_mulheres', 'plano_2', 'plano_3', 'plano_4', $m['muay'], $m['karate']] as $filtro) {
            $this->assertSame(count(listarAlunosCompletos($filtro)), contarAlunosCompletos($filtro), "filtro '$filtro'");
        }
    }

    public function testFiltroDesconhecidoOuMaliciosoMostraTodosSemQuebrar(): void
    {
        $this->popular();
        foreach (['qualquer', "' OR 1=1 --", 'alunos_pagos; DROP TABLE ALUNOS'] as $filtro) {
            $this->assertCount(3, listarAlunosCompletos($filtro), "filtro '$filtro'");
        }
        $this->assertCount(0, listarAlunosCompletos('999'), 'modalidade inexistente');
        $this->assertCount(3, listarAluno(), 'a tabela continua inteira');
    }

    public function testPaginacaoDe40EmQuarenta(): void
    {
        for ($i = 0; $i < 45; $i++) {
            Database::getConexao()->exec("INSERT INTO ALUNOS (ALUMATRICULA, ALUNOME, ALUTELEFONE, ALUEMAIL, ALUCPF, ALUDATANASC, ALUSTATUS)
                VALUES ('" . (20000 + $i) . "', 'ALUNO $i', '6199999$i', 'a$i@exemplo.invalid', '" . str_pad((string) $i, 11, '9', STR_PAD_LEFT) . "', '1990-01-01', 1);
                INSERT INTO ALUNOS_INSCRICAO (ALUNOMAT, ALUNOMOD, ALUNOPLANO, ALUNOOPCAO) VALUES ('" . (20000 + $i) . "', 1, 1, 1)");
        }
        $this->assertSame(45, contarAlunosCompletos());
        $pagina1 = listarAlunosCompletos('', 40, 0);
        $pagina2 = listarAlunosCompletos('', 40, 40);
        $this->assertCount(40, $pagina1);
        $this->assertCount(5, $pagina2);
        $this->assertSame([], array_intersect(array_column($pagina1, 'ALUMATRICULA'), array_column($pagina2, 'ALUMATRICULA')), 'nenhum aluno repetido entre páginas');
        $this->assertCount(0, listarAlunosCompletos('', 40, 80));
    }

    public function testDadosListagemCalculaPaginasELevaPaginaAlemDoFimParaAUltima(): void
    {
        for ($i = 0; $i < 45; $i++) {
            Database::getConexao()->exec("INSERT INTO ALUNOS (ALUMATRICULA, ALUNOME, ALUTELEFONE, ALUEMAIL, ALUCPF, ALUDATANASC, ALUSTATUS)
                VALUES ('" . (20000 + $i) . "', 'ALUNO $i', '6199999$i', 'a$i@exemplo.invalid', '" . str_pad((string) $i, 11, '9', STR_PAD_LEFT) . "', '1990-01-01', 1);
                INSERT INTO ALUNOS_INSCRICAO (ALUNOMAT, ALUNOMOD, ALUNOPLANO, ALUNOOPCAO) VALUES ('" . (20000 + $i) . "', 1, 1, 1)");
        }
        $primeira = dadosListagem('', null);
        $this->assertSame(1, $primeira['pagina']);
        $this->assertSame(2, $primeira['totalPaginas']);
        $this->assertSame(45, $primeira['total']);
        $this->assertCount(40, $primeira['alunos']);

        $alem = dadosListagem('', '999');
        $this->assertSame(2, $alem['pagina'], 'pagina 999 cai na ultima');
        $this->assertCount(5, $alem['alunos']);
    }

    public function testDadosListagemSemAlunosTemUmaPaginaVazia(): void
    {
        $vazia = dadosListagem('', '3');
        $this->assertSame(1, $vazia['pagina']);
        $this->assertSame(1, $vazia['totalPaginas']);
        $this->assertSame([], $vazia['alunos']);
    }

    public function testAlunoSemInscricaoNaoApareceNaLista(): void
    {
        //comportamento atual: a lista junta com ALUNOS_INSCRICAO (INNER JOIN)
        Database::getConexao()->exec("INSERT INTO ALUNOS (ALUMATRICULA, ALUNOME, ALUTELEFONE, ALUEMAIL, ALUCPF, ALUDATANASC, ALUSTATUS)
            VALUES ('30000', 'SEM INSCRICAO', '61999990000', 'sem@exemplo.invalid', '" . self::CPFS[3] . "', '1990-01-01', 1)");
        $this->assertSame(0, contarAlunosCompletos());
        $this->assertCount(1, listarAluno());
    }

    public function testListaDevolveDadosParaAsColunasDaTela(): void
    {
        $this->popular();
        $aluno = listarAlunosCompletos('alunos_pagos')[0];
        foreach (['ALUMATRICULA', 'DATACADASTRO', 'ALUNOME', 'ALUCPF', 'ARTES', 'PLANO', 'FREQUENCIA', 'SITUACAO', 'ALUANONIMIZADO'] as $coluna) {
            $this->assertArrayHasKey($coluna, $aluno);
        }
        $this->assertSame('PAGO', $aluno['SITUACAO']);
        $this->assertSame('2x por semana', $aluno['FREQUENCIA']);
    }
}
