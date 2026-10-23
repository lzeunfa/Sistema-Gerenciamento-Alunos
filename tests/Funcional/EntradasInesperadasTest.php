<?php
//Robustez: nenhuma pagina ou acao pode quebrar (erro 500) com entrada em formato inesperado.
//Ferramentas de ataque e de varredura (como o OWASP ZAP) mandam parametros como lista (campo[]=x),
//textos enormes e caracteres de controle; o sistema deve recusar com calma, nunca estourar.
use PHPUnit\Framework\Attributes\DataProvider;

final class EntradasInesperadasTest extends TesteFuncional
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->entrar();
        $this->matricula = $this->criarAlunoPeloSistema();
    }

    private string $matricula;

    private function criarAlunoPeloSistema(): string
    {
        $campos = $this->formularioAluno() + ['csrf_token' => $this->tokenDaSessao()];
        $this->nav->post('BACKEND/CONTROLLER/gravarAluno.php', $campos);
        return (string) listarAluno()[0]['ALUMATRICULA'];
    }

    private function semErroInterno(array $resposta, string $descricao): void
    {
        $this->assertNotSame(500, $resposta['status'], "$descricao respondeu 500");
        $this->assertStringNotContainsString('Ocorreu um erro interno', $resposta['corpo'], $descricao);
        $this->assertStringNotContainsString('Fatal error', $resposta['corpo'], $descricao);
        $this->assertStringNotContainsString('Stack trace', $resposta['corpo'], $descricao);
    }

    public static function paginasGet(): array
    {
        return [
            'pagina como lista' => ['sistema.php?pagina[]=ALUNOS'],
            'filtro como lista' => ['sistema.php?pagina=ALUNOS&filtrando[]=alunos_pagos'],
            'pagina numerica como lista' => ['sistema.php?pagina=ALUNOS&p[]=2'],
            'turmas com filtro em lista' => ['sistema.php?pagina=TURMAS&filtrando[]=1&p[]=1'],
            'editar com matricula em lista' => ['sistema.php?pagina=EDITAR&matricula[]=1&voltar[]=ALUNOS'],
            'excluir com matricula em lista' => ['sistema.php?pagina=EXCLUIR&matricula[]=1'],
            'pagina negativa' => ['sistema.php?pagina=ALUNOS&p=-5'],
            'pagina gigante' => ['sistema.php?pagina=ALUNOS&p=99999999999999999999'],
            'filtro enorme' => ['sistema.php?pagina=ALUNOS&filtrando=' . str_repeat('A', 5000)],
            'caractere nulo' => ['sistema.php?pagina=ALUNOS%00&filtrando=%00'],
            'erro do login em lista' => ['index.php?erro[]=sessao_expirada'],
        ];
    }

    #[DataProvider('paginasGet')]
    public function testPaginaNaoQuebraComEntradaInesperada(string $caminho): void
    {
        $this->semErroInterno($this->nav->get($caminho), $caminho);
    }

    public function testAcoesNaoQuebramComCamposEmLista(): void
    {
        $token = $this->tokenDaSessao();
        $acoes = [
            'gravarAluno.php' => array_map(fn($v) => [$v], $this->formularioAluno()),
            'editarAluno.php' => ['matricula' => [$this->matricula], 'voltar' => ['ALUNOS'], 'nome' => ['x']],
            'exportarAluno.php' => ['matricula' => [$this->matricula], 'voltar' => ['ALUNOS']],
            'anonimizarAluno.php' => ['matricula' => [$this->matricula], 'confirmar' => ['1'], 'voltar' => ['ALUNOS']],
        ];
        foreach ($acoes as $acao => $campos) {
            $this->semErroInterno($this->nav->post("BACKEND/CONTROLLER/$acao", $campos + ['csrf_token' => $token]), $acao);
        }
        $this->assertSame('ALUNO FUNCIONAL', buscarAluno($this->matricula)['ALUNOME'], 'nada foi alterado');
    }

    public function testTokenCsrfEmListaEhRecusadoSemQuebrar(): void
    {
        $resposta = $this->nav->post('BACKEND/CONTROLLER/gravarAluno.php', $this->formularioAluno() + ['csrf_token' => [$this->tokenDaSessao()]]);
        $this->semErroInterno($resposta, 'csrf como lista');
        $this->assertCount(1, listarAluno());
    }

    public function testCamposEnormesSaoRecusadosSemQuebrar(): void
    {
        $enorme = str_repeat('Á', 100000);
        $campos = $this->formularioAluno(['nome' => $enorme, 'endereco' => $enorme, 'email' => $enorme . '@x.com']) + ['csrf_token' => $this->tokenDaSessao()];
        $this->semErroInterno($this->nav->post('BACKEND/CONTROLLER/gravarAluno.php', $campos), 'campos enormes');
        $this->assertCount(1, listarAluno());
    }

    public function testCaracteresDeControleNoNomeSaoRecusadosOuGuardadosComSeguranca(): void
    {
        $campos = $this->formularioAluno(['nome' => "nome\x00com\x1Bcontrole", 'cpf' => '111.444.777-35', 'email' => 'controle@exemplo.invalid']) + ['csrf_token' => $this->tokenDaSessao()];
        $this->semErroInterno($this->nav->post('BACKEND/CONTROLLER/gravarAluno.php', $campos), 'caracteres de controle');
        $this->semErroInterno($this->nav->get('sistema.php?pagina=ALUNOS'), 'lista depois do cadastro');
    }
}
