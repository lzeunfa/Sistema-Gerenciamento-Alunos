<?php
//Permissoes minimas do usuario do sistema e trilha de auditoria (LGPD art. 37 e 46).
final class BancoEAuditoriaTest extends TesteComBanco
{
    private function ultimaAuditoria(): array
    {
        return Database::getConexao()->query("SELECT TOP 1 * FROM AUDITORIA ORDER BY AUDCOD DESC")->fetch();
    }

    public function testSistemaConectaNoBancoDeTesteENaoNoReal(): void
    {
        $this->assertSame(BANCO_TESTE, Database::getConexao()->query("SELECT DB_NAME()")->fetchColumn());
    }

    public function testAuditoriaGravaAcaoUsuarioEIp(): void
    {
        $_SESSION['user_id'] = 'ADM-TESTE';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        registrarAuditoria('teste_acao', 'matricula 123');
        $linha = $this->ultimaAuditoria();
        $this->assertSame('ADM-TESTE', $linha['USUARIO']);
        $this->assertSame('teste_acao', $linha['ACAO']);
        $this->assertSame('matricula 123', $linha['DETALHE']);
        $this->assertSame('127.0.0.1', $linha['IP']);
        $this->assertNotNull($linha['DATAHORA']);
    }

    public function testAuditoriaSemLoginRegistraTraco(): void
    {
        unset($_SESSION['user_id']);
        registrarAuditoria('teste_anonimo');
        $this->assertSame('-', $this->ultimaAuditoria()['USUARIO']);
    }

    public function testAuditoriaCortaTextosLongosEmVezDeFalhar(): void
    {
        registrarAuditoria(str_repeat('a', 100), str_repeat('d', 500), str_repeat('u', 100));
        $linha = $this->ultimaAuditoria();
        $this->assertSame(40, strlen($linha['ACAO']));
        $this->assertSame(255, strlen($linha['DETALHE']));
        $this->assertSame(50, strlen($linha['USUARIO']));
    }

    public function testSistemaNaoConsegueApagarNemAlterarAAuditoria(): void
    {
        registrarAuditoria('nao_pode_sumir');
        $pdo = Database::getConexao();
        foreach (["DELETE FROM AUDITORIA", "UPDATE AUDITORIA SET ACAO = 'x'"] as $sql) {
            try {
                $pdo->exec($sql);
                $this->fail("O usuário do sistema conseguiu executar: $sql");
            } catch (PDOException $esperado) {
                $this->assertStringContainsString('229', (string) ($esperado->errorInfo[1] ?? ''), 'erro 229 = permissão negada');
            }
        }
        $this->assertSame('nao_pode_sumir', $this->ultimaAuditoria()['ACAO']);
    }

    public function testSistemaNaoConsegueMudarAEstruturaDoBanco(): void
    {
        $pdo = Database::getConexao();
        foreach (["ALTER TABLE ALUNOS ADD COLUNA_INVASORA INT", "DROP TABLE LOGIN_TENTATIVAS", "CREATE TABLE INVASORA (ID INT)"] as $sql) {
            try {
                $pdo->exec($sql);
                $this->fail("O usuário do sistema conseguiu executar: $sql");
            } catch (PDOException $esperado) {
                $this->assertNotEmpty($esperado->getMessage());
            }
        }
        $this->assertNotFalse($pdo->query("SELECT TOP 1 * FROM LOGIN_TENTATIVAS"), 'a tabela continua lá');
    }

    public function testSistemaPodeLerEGravarAlunos(): void
    {
        $matricula = $this->cadastrar();
        $this->assertNotNull(buscarAluno($matricula));
    }
}
