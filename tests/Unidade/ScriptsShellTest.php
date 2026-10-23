<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

//Scripts .sh do Docker e dos testes: rodam no Debian, mas sao editados no Windows.
final class ScriptsShellTest extends TestCase
{
    public static function scripts(): array
    {
        $arquivos = array_merge(glob(RAIZ_PROJETO . '/docker/*.sh'), glob(RAIZ_PROJETO . '/tests/*.sh'), glob(RAIZ_PROJETO . '/tests/carga/*.sh'));
        return array_map(fn($a) => [substr($a, strlen(RAIZ_PROJETO) + 1)], $arquivos);
    }

    //Git Bash no Windows (o "bash" do PATH pode ser o do WSL, que nao funciona nesta maquina)
    private static function bash(): ?string
    {
        $caminho = PHP_OS_FAMILY === 'Windows' ? 'C:\Program Files\Git\bin\bash.exe' : '/bin/bash';
        return is_file($caminho) ? $caminho : null;
    }

    private function rodarBash(array $argumentos): array
    {
        $bash = self::bash() ?? $this->markTestSkipped('bash não encontrado');
        $proc = proc_open(array_merge([$bash], $argumentos), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, RAIZ_PROJETO, null, ['bypass_shell' => true]);
        fclose($pipes[0]);
        $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $saida];
    }

    #[DataProvider('scripts')]
    public function testScriptTemQuebraDeLinhaUnix(string $script): void
    {
        //com CRLF o bash do Debian falha com "$'\r': command not found" (.gitattributes forca LF)
        $this->assertStringNotContainsString("\r", file_get_contents(RAIZ_PROJETO . "/$script"), $script);
    }

    #[DataProvider('scripts')]
    public function testScriptParaNoPrimeiroErro(string $script): void
    {
        //sem "set -e" um backup que falhou seguiria em frente e diria "Backup criado"
        $conteudo = file_get_contents(RAIZ_PROJETO . "/$script");
        if (!str_starts_with($conteudo, '#!')) {
            $this->assertStringContainsString('carregue com "source"', $conteudo, "$script: sem #! precisa dizer que e carregado por outro");
            return;
        }
        $this->assertStringContainsString('set -euo pipefail', $conteudo, $script);
    }

    #[DataProvider('scripts')]
    public function testScriptNaoTemErroDeSintaxe(string $script): void
    {
        [$codigo, $saida] = $this->rodarBash(['-n', $script]);
        $this->assertSame(0, $codigo, "$script: $saida");
    }

    public function testRestaurarRecusaSemConfirmacao(): void
    {
        //restaurar substitui o banco inteiro: sem a confirmacao explicita, para antes de tocar no Docker
        $arquivo = tempnam(sys_get_temp_dir(), 'gkt_bak_');
        try {
            foreach ([[], [$arquivo], [$arquivo, '--sim'], ['/nao/existe.bak', '--confirmo-substituir']] as $argumentos) {
                [$codigo, $saida] = $this->rodarBash(array_merge(['docker/restaurar.sh'], $argumentos));
                $this->assertSame(1, $codigo, implode(' ', $argumentos));
                $this->assertStringContainsString('--confirmo-substituir', $saida);
                $this->assertStringNotContainsString('Backup de seguranca', $saida, 'não pode ter começado');
            }
        } finally {
            unlink($arquivo);
        }
    }
}
