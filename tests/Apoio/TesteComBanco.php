<?php
use PHPUnit\Framework\TestCase;

//Base dos testes que usam o banco gktgym_teste. Cada teste comeca com as tabelas de alunos vazias.
abstract class TesteComBanco extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!bancoTesteDisponivel()) {
            self::markTestSkipped("Banco " . BANCO_TESTE . " indisponível. Rode: pwsh tests/preparar_banco_teste.ps1");
        }
    }

    protected function setUp(): void
    {
        $_POST = [];
        $_SESSION = [];
        $pdo = Database::getConexao();
        $pdo->exec("DELETE FROM ALUNOS_INSCRICAO; DELETE FROM ALUNOS; DELETE FROM LOGIN_TENTATIVAS;");
    }

    //CPFs validos e ficticios para os testes (digitos verificadores corretos)
    public const CPFS = ['52998224725', '11144477735', '12345678909', '98765432100', '39053344705', '71428793860'];

    //dados de um aluno adulto valido, no formato devolvido por lerAlunoDoPost()
    protected function alunoValido(array $mudancas = []): array
    {
        return array_merge([
            'nome' => 'ALUNO TESTE',
            'telefone' => '61999990000',
            'email' => 'aluno.teste@exemplo.invalid',
            'cpf' => self::CPFS[0],
            'dataNasc' => '1990-05-10',
            'genero' => 'M',
            'cep' => '70000000',
            'endereco' => 'Rua Ficticia 1',
            'responsavelNome' => '',
            'responsavelCpf' => '',
            'modalidades' => [1],
            'plano' => 1,
            'frequencia' => 1,
            'status' => 1,
        ], $mudancas);
    }

    //cadastra pelo mesmo caminho do sistema e devolve a matricula
    protected function cadastrar(array $mudancas = []): string
    {
        $a = $this->alunoValido($mudancas);
        $matricula = cadastrarAluno($a['nome'], $a['telefone'], $a['email'], $a['cpf'], $a['genero'], $a['dataNasc'], $a['cep'], $a['endereco'], $a['modalidades'], $a['plano'], $a['frequencia'], $a['status'], $a['responsavelNome'], $a['responsavelCpf']);
        $this->assertNotFalse($matricula, "cadastrarAluno() falhou");
        return (string) $matricula;
    }

    protected function codigo(string $tabela, string $colunaCodigo, string $colunaTexto, string $texto): int
    {
        $stmt = Database::getConexao()->prepare("SELECT $colunaCodigo FROM $tabela WHERE $colunaTexto = :t");
        $stmt->execute([':t' => $texto]);
        $codigo = $stmt->fetchColumn();
        $this->assertNotFalse($codigo, "$texto não existe em $tabela");
        return (int) $codigo;
    }

    /** @return array<string, mixed> linha da tabela ALUNOS (vazia se nao existir) */
    protected function linhaAluno(string $matricula): array
    {
        $stmt = Database::getConexao()->prepare("SELECT * FROM ALUNOS WHERE ALUMATRICULA = :m");
        $stmt->execute([':m' => $matricula]);
        return $stmt->fetch() ?: [];
    }
}
