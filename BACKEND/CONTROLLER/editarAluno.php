<?php
//correcao de dados do aluno (LGPD art. 18, III)
require_once(__DIR__ . '/../../trava.php');
require_once(__DIR__ . '/funcoes.php');
exigirPostComCsrf();

$matricula = matriculaValida($_POST['matricula'] ?? null);
$voltar = in_array($_POST['voltar'] ?? '', ['ALUNOS', 'TURMAS'], true) ? $_POST['voltar'] : 'ALUNOS';

function voltarParaEdicao(string $matricula, string $voltar, string $tipo, string $texto): void
{
    definirMensagem($tipo, $texto);
    header('Location: ../../sistema.php?pagina=EDITAR&matricula=' . urlencode($matricula) . '&voltar=' . $voltar);
    exit();
}

$atual = $matricula ? buscarAluno($matricula) : null;
if (!$atual || $atual['ALUANONIMIZADO'] !== null) {
    definirMensagem('erro', "Aluno não encontrado ou já anonimizado.");
    header("Location: ../../sistema.php?pagina=$voltar");
    exit();
}

[$aluno, $erros] = lerAlunoDoPost();
if ($erros) {
    voltarParaEdicao($matricula, $voltar, 'erro', "Verifique os campos: " . implode(', ', $erros) . ".");
}

$resultado = atualizarAluno($matricula, $aluno, campo('ciencia_privacidade') === '1');

if ($resultado === 'ok') {
    registrarAuditoria('edicao_aluno', "matricula $matricula");
    definirMensagem('sucesso', "Dados da matrícula $matricula atualizados.");
    header("Location: ../../sistema.php?pagina=$voltar");
    exit();
}
voltarParaEdicao($matricula, $voltar, 'erro', $resultado === 'duplicado'
    ? "O CPF ou e-mail informado já pertence a outro aluno."
    : "Erro ao salvar as alterações. Tente novamente.");
?>
