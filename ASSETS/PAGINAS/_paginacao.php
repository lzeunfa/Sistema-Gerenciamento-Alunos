<?php
//botoes de pagina das telas Alunos e Turmas (um so lugar para as duas).
//Espera: $paginaVolta ('ALUNOS' ou 'TURMAS'), $filtroEscolhido, $pagina_atual e $total_paginas.
//Mostra so a primeira, a ultima e as vizinhas da atual: com 20 mil alunos seriam 500 botoes.
/** @var string $paginaVolta */
/** @var string $filtroEscolhido */
/** @var int $pagina_atual */
/** @var int $total_paginas */
$linkPagina = fn(int $p) => 'sistema.php?pagina=' . $paginaVolta . '&filtrando=' . urlencode($filtroEscolhido) . '&p=' . $p;
?>
<div class="paginacao d-flex justify-content-center mt-4">
    <?php if ($total_paginas > 1): ?>
        <nav aria-label="Navegação de página">
            <ul class="pagination">
                <li class="page-item <?= $pagina_atual <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($linkPagina(max(1, $pagina_atual - 1))) ?>">Anterior</a>
                </li>
                <?php foreach (paginasVisiveis($pagina_atual, $total_paginas) as $p): ?>
                    <?php if ($p === null): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php else: ?>
                        <li class="page-item <?= $p === $pagina_atual ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e($linkPagina($p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item <?= $pagina_atual >= $total_paginas ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e($linkPagina(min($total_paginas, $pagina_atual + 1))) ?>">Próxima</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
