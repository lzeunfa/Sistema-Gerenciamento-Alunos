<!--confirmacao do botao Excluir: anonimiza o aluno (LGPD art. 16 e 18, VI)-->
<?php
require_once "BACKEND/CONTROLLER/funcoes.php";
$matricula = matriculaValida($_GET['matricula'] ?? null);
$voltar = in_array($_GET['voltar'] ?? '', ['ALUNOS', 'TURMAS'], true) ? $_GET['voltar'] : 'ALUNOS';
$aluno = $matricula ? buscarAluno($matricula) : null;
$mensagem = pegarMensagem();
?>
<div class="container_form">
<?php if (!$aluno || $aluno['ALUANONIMIZADO'] !== null): ?>
    <div class="page-header">
        <h1>Aluno não encontrado</h1>
        <p>A matrícula não existe ou os dados já foram anonimizados.</p>
    </div>
    <a class="btn btn-secondary" href="sistema.php?pagina=<?= e($voltar) ?>">Voltar</a>
<?php else: ?>
    <div class="page-header">
        <h1>Excluir dados do aluno</h1>
        <p>Matrícula <?= e($aluno['ALUMATRICULA']) ?> — <?= e($aluno['ALUNOME']) ?> (CPF <?= e(mascararCpf($aluno['ALUCPF'])) ?>)</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="form-mensagem form-mensagem-<?= e($mensagem['tipo']) ?>" role="alert"><?= e($mensagem['texto']) ?></div>
    <?php endif; ?>

    <div class="form-container">
        <p>Nome, CPF, e-mail, telefone, endereço, CEP e dados do responsável serão <strong>apagados definitivamente</strong>
            e substituídos por valores sem significado. A data de nascimento fica só com o ano.</p>
        <p>A matrícula, o sexo e as inscrições (modalidade, plano e frequência) continuam no sistema, sem identificar a pessoa,
            para as estatísticas da academia. <strong>Esta ação não pode ser desfeita.</strong></p>
        <p>Se o aluno pediu uma cópia dos dados, use <em>Exportar dados</em> antes.</p>

        <form method="POST" action="BACKEND/CONTROLLER/anonimizarAluno.php">
            <?= csrfCampo() ?>
            <input type="hidden" name="matricula" value="<?= e($aluno['ALUMATRICULA']) ?>">
            <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
            <div class="form-group">
                <div class="checkbox-option">
                    <input type="checkbox" id="confirmar" name="confirmar" value="1" required>
                    <label for="confirmar">Confirmo que quero excluir os dados pessoais desta matrícula.</label>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn btn-secondary" href="sistema.php?pagina=<?= e($voltar) ?>">Cancelar</a>
                <button type="submit" class="btn btn-perigo">Excluir dados pessoais</button>
            </div>
        </form>
    </div>
<?php endif; ?>
</div>
