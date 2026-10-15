<?php
//Politica de retencao (LGPD art. 15 e 16): anonimiza os alunos INATIVOS ha mais de 5 anos.
//So roda pelo terminal. Sem parametro apenas mostra quantos seriam anonimizados; com --executar anonimiza.
//  C:\xampp\php\php.exe DB/anonimizar_antigos.php             (simulacao)
//  C:\xampp\php\php.exe DB/anonimizar_antigos.php --executar  (para o Agendador de Tarefas, 1x por mes)
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once(__DIR__ . '/../BACKEND/SEGURANCA/seguranca.php');
require_once(__DIR__ . '/../BACKEND/CONTROLLER/funcoes.php');

const ANOS_DE_GUARDA = 5;
$executar = in_array('--executar', $argv, true);

$inativo = codigoStatusInativo();
if ($inativo === null) {
    fwrite(STDERR, "Situacao INATIVO nao existe no banco. Rode DB/migracao_direitos_titular.sql antes.\n");
    exit(1);
}

$pdo = Database::getConexao();
$stmt = $pdo->prepare("SELECT ALUMATRICULA FROM ALUNOS
    WHERE ALUANONIMIZADO IS NULL AND ALUSTATUS = :inativo
      AND ALUDATAINATIVACAO < DATEADD(YEAR, -" . ANOS_DE_GUARDA . ", GETDATE())
    ORDER BY ALUMATRICULA");
$stmt->execute([':inativo' => $inativo]);
$matriculas = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!$executar) {
    echo "Simulacao: " . count($matriculas) . " aluno(s) inativo(s) ha mais de " . ANOS_DE_GUARDA . " anos.\n";
    foreach ($matriculas as $matricula) {
        echo "  matricula $matricula\n";
    }
    echo "Use --executar para anonimizar.\n";
    exit(0);
}

$feitos = 0;
foreach ($matriculas as $matricula) {
    try {
        if (anonimizarAluno($matricula)) {
            registrarAuditoria('anonimizacao_retencao', "matricula $matricula", 'retencao-cli');
            $feitos++;
            echo "anonimizado: matricula $matricula\n";
        }
    } catch (PDOException $erro) {
        error_log("GKT erro ao anonimizar matricula $matricula: " . $erro->getMessage());
        fwrite(STDERR, "falhou: matricula $matricula (detalhes no log do PHP)\n");
    }
}
echo "$feitos de " . count($matriculas) . " aluno(s) anonimizado(s).\n";
exit($feitos === count($matriculas) ? 0 : 1);
?>
