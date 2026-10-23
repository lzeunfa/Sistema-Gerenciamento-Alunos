<?php
//Teste de volume: cadastra N alunos ficticios (nome "VOLUME ...") para medir o sistema com muitos dados.
//So para o ambiente Docker de teste. Uso (dentro do container app):
//   php tests/carga/popular_volume.php 20000 --confirmo-banco-de-teste
//   php tests/carga/popular_volume.php --limpar --confirmo-banco-de-teste
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../../BACKEND/CONTROLLER/funcoes.php';

if (!in_array('--confirmo-banco-de-teste', $argv, true)) {
    fwrite(STDERR, "Este script enche o banco de alunos ficticios. Confirme com --confirmo-banco-de-teste\n");
    exit(1);
}
$pdo = Database::getConexao();
echo "Banco: " . $pdo->query("SELECT DB_NAME()")->fetchColumn() . "\n";

if (in_array('--limpar', $argv, true)) {
    $pdo->exec("DELETE I FROM ALUNOS_INSCRICAO I JOIN ALUNOS A ON A.ALUMATRICULA = I.ALUNOMAT WHERE A.ALUNOME LIKE 'VOLUME %'");
    $apagados = $pdo->exec("DELETE FROM ALUNOS WHERE ALUNOME LIKE 'VOLUME %'");
    echo "$apagados aluno(s) de volume apagados\n";
    exit(0);
}

$quantidade = (int) ($argv[1] ?? 0);
if ($quantidade < 1 || $quantidade > 200000) {
    fwrite(STDERR, "Informe a quantidade (1 a 200000)\n");
    exit(1);
}

//CPF valido e unico derivado da matricula (aleatorio repetia a partir de dezenas de milhares de alunos)
function cpfDaMatricula(int $matricula): string
{
    $d = array_map('intval', str_split(str_pad((string) ($matricula % 1000000000), 9, '0', STR_PAD_LEFT)));
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $d[$i] * ($t + 1 - $i);
        }
        $d[] = ((10 * $soma) % 11) % 10;
    }
    return implode('', $d); //matriculas sao sequenciais e nunca repetem 9 digitos iguais na faixa usada
}

$modalidades = array_map('intval', array_column(listarModalidades(), 'MODALIDADECOD'));
$planos = array_map('intval', array_column(listarPlano(), 'PLANOCOD'));
$frequencias = array_map('intval', array_column(listarFrequencia(), 'OPCAOCOD'));
$status = array_map('intval', array_column(listarStatus(), 'STATUSCOD'));
$proxima = (int) $pdo->query("SELECT ISNULL(MAX(CAST(ALUMATRICULA AS INT)), 14000) FROM ALUNOS WHERE ALUMATRICULA NOT LIKE '%[^0-9]%'")->fetchColumn() + 1;

$inicio = microtime(true);
$lote = 200; //alunos por transacao
for ($feitos = 0; $feitos < $quantidade; $feitos += $lote) {
    $pdo->beginTransaction();
    $aluno = $pdo->prepare("INSERT INTO ALUNOS (ALUMATRICULA, ALUNOME, ALUTELEFONE, ALUEMAIL, ALUCPF, ALUSEX, ALUDATANASC, ALUCEP, ALUENDERECO, ALUSTATUS, DATACADASTRO, ALUCIENCIAPRIVACIDADE)
        VALUES (?, ?, '61999990000', ?, ?, ?, ?, '70000000', 'Rua Ficticia 1', ?, DATEADD(DAY, -CAST(? AS INT), GETDATE()), GETDATE())");
    $inscricao = $pdo->prepare("INSERT INTO ALUNOS_INSCRICAO (ALUNOMAT, ALUNOMOD, ALUNOPLANO, ALUNOOPCAO) VALUES (?, ?, ?, ?)");
    for ($i = 0; $i < $lote && $feitos + $i < $quantidade; $i++) {
        $matricula = (string) $proxima++;
        $aluno->execute([$matricula, "VOLUME $matricula", "volume.$matricula@exemplo.invalid", cpfDaMatricula((int) $matricula),
            random_int(0, 1) ? 'M' : 'F', date('Y-m-d', strtotime('-' . random_int(6, 70) . ' years')),
            $status[array_rand($status)], random_int(0, 1800)]);
        $plano = $planos[array_rand($planos)];
        $frequencia = $frequencias[array_rand($frequencias)];
        foreach ((array) array_rand(array_flip($modalidades), random_int(1, 2)) as $modalidade) {
            $inscricao->execute([$matricula, $modalidade, $plano, $frequencia]);
        }
    }
    $pdo->commit();
}
printf("%d aluno(s) cadastrados em %.1f s. Total no banco: %d\n", $quantidade, microtime(true) - $inicio,
    $pdo->query("SELECT COUNT(*) FROM ALUNOS")->fetchColumn());
