<?php
//entrega ao titular todos os dados guardados sobre ele, em JSON (LGPD art. 18, II e V)
//e POST com CSRF para que outro site nao consiga disparar o download em nome do administrador
require_once(__DIR__ . '/../../trava.php');
require_once(__DIR__ . '/funcoes.php');
exigirPostComCsrf();

$matricula = matriculaValida($_POST['matricula'] ?? null);
$voltar = in_array($_POST['voltar'] ?? '', ['ALUNOS', 'TURMAS', 'EDITAR'], true) ? $_POST['voltar'] : 'ALUNOS';
$aluno = $matricula ? buscarAluno($matricula) : null;

if (!$aluno || $aluno['ALUANONIMIZADO'] !== null) {
    definirMensagem('erro', "Aluno não encontrado ou já anonimizado.");
    header("Location: ../../sistema.php?pagina=" . ($voltar === 'EDITAR' ? 'ALUNOS' : $voltar));
    exit();
}

$json = json_encode(dadosExportacaoAluno($matricula), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
registrarAuditoria('exportacao_aluno', "matricula $matricula");

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="dados-aluno-' . $matricula . '-' . date('Y-m-d') . '.json"');
header('Content-Length: ' . strlen($json));
echo $json;
exit();
?>
