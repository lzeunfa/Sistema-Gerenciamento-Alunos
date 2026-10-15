<?php
//botao Excluir: elimina os dados pessoais por anonimizacao (LGPD art. 16 e 18, IV e VI)
//matricula e inscricoes continuam no banco, sem identificar ninguem, para as estatisticas da academia
require_once(__DIR__ . '/../../trava.php');
require_once(__DIR__ . '/funcoes.php');
exigirPostComCsrf();

$matricula = matriculaValida($_POST['matricula'] ?? null);
$voltar = in_array($_POST['voltar'] ?? '', ['ALUNOS', 'TURMAS'], true) ? $_POST['voltar'] : 'ALUNOS';

if ($matricula && campo('confirmar') !== '1') {
    definirMensagem('erro', "Marque a confirmação para excluir os dados.");
    header('Location: ../../sistema.php?pagina=EXCLUIR&matricula=' . urlencode($matricula) . "&voltar=$voltar");
    exit();
}

if ($matricula && anonimizarAluno($matricula)) {
    registrarAuditoria('anonimizacao_aluno', "matricula $matricula");
    definirMensagem('sucesso', "Dados pessoais da matrícula $matricula excluídos (anonimizados).");
} else {
    definirMensagem('erro', "Aluno não encontrado ou já anonimizado.");
}
header("Location: ../../sistema.php?pagina=$voltar");
exit();
?>
