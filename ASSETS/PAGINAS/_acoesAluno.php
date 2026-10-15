<?php
//botoes Editar / Exportar / Excluir de uma linha da tabela; usado por ALUNOS.php e TURMAS.php
//espera $aluno (linha de listarAlunosCompletos) e $paginaVolta ('ALUNOS' ou 'TURMAS')
$linkMatricula = 'matricula=' . urlencode($aluno['ALUMATRICULA']) . '&voltar=' . $paginaVolta;
?>
<div class="acoes">
    <?php if (!empty($aluno['ALUANONIMIZADO'])): ?>
        <small class="anonimizado">anonimizado</small>
    <?php else: ?>
        <a class="btn-acao btn-edit" href="sistema.php?pagina=EDITAR&amp;<?= e($linkMatricula) ?>" title="Editar" aria-label="Editar">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        </a>

        <form class="form-acao" method="POST" action="BACKEND/CONTROLLER/exportarAluno.php">
            <?= csrfCampo() ?>
            <input type="hidden" name="matricula" value="<?= e($aluno['ALUMATRICULA']) ?>">
            <input type="hidden" name="voltar" value="<?= e($paginaVolta) ?>">
            <button type="submit" class="btn-acao btn-export" title="Exportar dados (JSON)" aria-label="Exportar dados">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                </svg>
            </button>
        </form>

        <a class="btn-acao btn-delete" href="sistema.php?pagina=EXCLUIR&amp;<?= e($linkMatricula) ?>" title="Excluir" aria-label="Excluir">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </a>
    <?php endif; ?>
</div>
