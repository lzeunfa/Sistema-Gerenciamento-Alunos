<?php
//Scripts de manutencao rodados pelo terminal: retencao de 5 anos e criacao de administrador.
final class ScriptsDeTerminalTest extends TesteComBanco
{
    protected function setUp(): void
    {
        parent::setUp();
        Database::getConexao()->exec("DELETE FROM ADMINISTRACAO WHERE ADMLOGIN LIKE 'teste_%'");
    }

    //roda um script do projeto com o PHP do XAMPP apontando para o banco de teste
    private function rodar(string $script, array $argumentos = [], array $env = []): array
    {
        $comando = array_merge([PHP_BINARY, RAIZ_PROJETO . '/' . $script], $argumentos);
        $ambiente = array_merge(getenv(), ['GKT_DB_DATABASE' => BANCO_TESTE], $env);
        $proc = proc_open($comando, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, RAIZ_PROJETO, $ambiente, ['bypass_shell' => true]);
        fclose($pipes[0]);
        $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $saida];
    }

    // ---------- DB/anonimizar_antigos.php ----------

    private function alunosParaRetencao(): array
    {
        $inativo = $this->codigo('ALUNOS_STATUS', 'STATUSCOD', 'STATUSALU', 'INATIVO');
        $antigo = $this->cadastrar(['status' => $inativo, 'cpf' => self::CPFS[0], 'email' => 'a@exemplo.invalid']);
        $recente = $this->cadastrar(['status' => $inativo, 'cpf' => self::CPFS[1], 'email' => 'b@exemplo.invalid']);
        $ativoAntigo = $this->cadastrar(['status' => 2, 'cpf' => self::CPFS[2], 'email' => 'c@exemplo.invalid']);
        $pdo = Database::getConexao();
        $pdo->exec("UPDATE ALUNOS SET ALUDATAINATIVACAO = DATEADD(YEAR, -6, GETDATE()) WHERE ALUMATRICULA = '$antigo'");
        $pdo->exec("UPDATE ALUNOS SET ALUDATAINATIVACAO = DATEADD(YEAR, -4, GETDATE()) WHERE ALUMATRICULA = '$recente'");
        $pdo->exec("UPDATE ALUNOS SET DATACADASTRO = DATEADD(YEAR, -10, GETDATE()) WHERE ALUMATRICULA = '$ativoAntigo'");
        return [$antigo, $recente, $ativoAntigo];
    }

    public function testSimulacaoDaRetencaoSoListaENaoAltera(): void
    {
        [$antigo, $recente, $ativo] = $this->alunosParaRetencao();
        [$codigo, $saida] = $this->rodar('DB/anonimizar_antigos.php');

        $this->assertSame(0, $codigo, $saida);
        $this->assertStringContainsString('1 aluno(s)', $saida);
        $this->assertStringContainsString("matricula $antigo", $saida);
        $this->assertStringNotContainsString("matricula $recente", $saida);
        $this->assertSame('ALUNO TESTE', $this->linhaAluno($antigo)['ALUNOME'], 'simulação não altera nada');
    }

    public function testRetencaoAnonimizaSoInativosHaMaisDe5Anos(): void
    {
        [$antigo, $recente, $ativo] = $this->alunosParaRetencao();
        [$codigo, $saida] = $this->rodar('DB/anonimizar_antigos.php', ['--executar']);

        $this->assertSame(0, $codigo, $saida);
        $this->assertSame('ANONIMIZADO', $this->linhaAluno($antigo)['ALUNOME']);
        $this->assertSame('ALUNO TESTE', $this->linhaAluno($recente)['ALUNOME'], 'inativo há 4 anos fica');
        $this->assertSame('ALUNO TESTE', $this->linhaAluno($ativo)['ALUNOME'], 'aluno ativo nunca é anonimizado');

        $acao = Database::getConexao()->query("SELECT TOP 1 ACAO + '|' + USUARIO + '|' + DETALHE FROM AUDITORIA ORDER BY AUDCOD DESC")->fetchColumn();
        $this->assertSame("anonimizacao_retencao|retencao-cli|matricula $antigo", $acao);
    }

    public function testRetencaoRodadaDeNovoNaoRepete(): void
    {
        $this->alunosParaRetencao();
        $this->rodar('DB/anonimizar_antigos.php', ['--executar']);
        [$codigo, $saida] = $this->rodar('DB/anonimizar_antigos.php', ['--executar']);
        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('0 de 0', $saida);
    }

    // ---------- DB/criar_admin.php ----------

    private function admin(string $login): array
    {
        $stmt = Database::getConexao()->prepare("SELECT * FROM ADMINISTRACAO WHERE ADMLOGIN = :l");
        $stmt->execute([':l' => $login]);
        return $stmt->fetch() ?: [];
    }

    public function testCriaAdministradorComSenhaArgon2id(): void
    {
        [$codigo, $saida] = $this->rodar('DB/criar_admin.php', ['teste_admin', 'ADMIN TESTE', 'teste_admin@exemplo.invalid'], ['GKT_NOVA_SENHA' => 'cavalo-bateria-grampo-7']);

        $this->assertSame(0, $codigo, $saida);
        $admin = $this->admin('teste_admin');
        $this->assertSame('ADMIN TESTE', $admin['ADMNOME']);
        $this->assertStringStartsWith('$argon2id$', $admin['ADMSENHA']);
        $this->assertTrue(password_verify('cavalo-bateria-grampo-7', $admin['ADMSENHA']));
    }

    public function testSegundaVezSoTrocaASenha(): void
    {
        $this->rodar('DB/criar_admin.php', ['teste_admin'], ['GKT_NOVA_SENHA' => 'cavalo-bateria-grampo-7']);
        $matricula = $this->admin('teste_admin')['ADMMATRICULA'];
        [$codigo, $saida] = $this->rodar('DB/criar_admin.php', ['teste_admin'], ['GKT_NOVA_SENHA' => 'outra-senha-bem-longa-9']);

        $this->assertSame(0, $codigo, $saida);
        $this->assertStringContainsString('atualizada', $saida);
        $admin = $this->admin('teste_admin');
        $this->assertSame($matricula, $admin['ADMMATRICULA']);
        $this->assertTrue(password_verify('outra-senha-bem-longa-9', $admin['ADMSENHA']));
    }

    public function testSenhaFracaNaoCriaAdministrador(): void
    {
        [, $saida] = $this->rodar('DB/criar_admin.php', ['teste_admin'], ['GKT_NOVA_SENHA' => 'curta']);
        $this->assertStringContainsString('12 caracteres', $saida);
        $this->assertSame([], $this->admin('teste_admin'));
    }

    public function testLoginComCaracteresInvalidosEhRecusado(): void
    {
        [, $saida] = $this->rodar('DB/criar_admin.php', ["teste_'; DROP--"], ['GKT_NOVA_SENHA' => 'cavalo-bateria-grampo-7']);
        $this->assertStringContainsString('Uso:', $saida);
    }
}
