<?php
//Metricas de manutenibilidade do codigo do sistema (sem tests/ e vendor/):
//linhas por arquivo, e por funcao o tamanho e a complexidade ciclomatica (quantos caminhos o codigo tem).
//Uso: C:\xampp\php\php.exe tests/metricas.php   (ou php tests/metricas.php no container de testes)
//Referencia: complexidade ate 10 = simples de testar; 11 a 20 = atencao; acima de 20 = dividir a funcao.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$raiz = dirname(__DIR__);
$arquivos = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)), '/\.php$/');
$decisoes = [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE, T_CATCH, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR, T_COALESCE];

$funcoes = [];
$totalLinhas = 0;
$porArquivo = [];
foreach ($arquivos as $arquivo) {
    $caminho = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raiz) + 1));
    if (preg_match('#^(vendor|tests)/#', $caminho)) {
        continue;
    }
    $codigo = file_get_contents($arquivo->getPathname());
    $linhas = substr_count($codigo, "\n") + 1;
    $totalLinhas += $linhas;
    $porArquivo[$caminho] = $linhas;

    $tokens = token_get_all($codigo);
    $atual = null;
    $profundidade = 0;
    foreach ($tokens as $i => $t) {
        if (is_array($t) && $t[0] === T_FUNCTION) {
            for ($j = $i + 1; isset($tokens[$j]); $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $atual = ['nome' => "$caminho::{$tokens[$j][1]}()", 'inicio' => $t[2], 'complexidade' => 1, 'abertura' => null];
                    break;
                }
                if ($tokens[$j] === '(') {
                    break; //funcao anonima: entra na complexidade de quem a contem
                }
            }
            continue;
        }
        if ($atual === null) {
            continue;
        }
        if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $profundidade++;
            $atual['abertura'] ??= $profundidade;
        } elseif ($t === '}') {
            if ($profundidade === $atual['abertura']) {
                $linhaFim = 0;
                for ($k = $i; $k >= 0 && !$linhaFim; $k--) {
                    if (is_array($tokens[$k])) {
                        $linhaFim = $tokens[$k][2] + substr_count($tokens[$k][1], "\n");
                    }
                }
                $atual['linhas'] = $linhaFim - $atual['inicio'] + 1;
                $funcoes[] = $atual;
                $atual = null;
            }
            $profundidade--;
        } elseif ($t === '?' || (is_array($t) && in_array($t[0], $decisoes, true))) {
            $atual['complexidade']++;
        }
    }
}

usort($funcoes, fn($a, $b) => $b['complexidade'] <=> $a['complexidade']);
arsort($porArquivo);
$complexidades = array_column($funcoes, 'complexidade');
printf("Arquivos PHP: %d | linhas: %d | funcoes: %d\n", count($porArquivo), $totalLinhas, count($funcoes));
printf("Complexidade media: %.1f | maxima: %d | funcoes acima de 10: %d | acima de 20: %d\n\n",
    array_sum($complexidades) / max(1, count($complexidades)), max($complexidades ?: [0]),
    count(array_filter($complexidades, fn($c) => $c > 10)), count(array_filter($complexidades, fn($c) => $c > 20)));
echo "Funcoes mais complexas:\n";
foreach (array_slice($funcoes, 0, 12) as $f) {
    printf("  %3d caminhos  %4d linhas  %s\n", $f['complexidade'], $f['linhas'], $f['nome']);
}
echo "\nMaiores arquivos:\n";
foreach (array_slice($porArquivo, 0, 8, true) as $caminho => $linhas) {
    printf("  %5d linhas  %s\n", $linhas, $caminho);
}
