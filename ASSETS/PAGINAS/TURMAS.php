<!--html da pagina turmas-->
<?php
require_once "BACKEND/CONTROLLER/funcoes.php";
$filtroEscolhido = isset($_GET['filtrando']) ? $_GET['filtrando'] : '';

$limite = 40;
$pagina_atual = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($pagina_atual - 1) * $limite;

$total_registros = contarAlunosCompletos($filtroEscolhido);
$total_paginas = ceil($total_registros / $limite);

$listaAlunosDashboard = listarAlunosCompletos($filtroEscolhido, $limite, $offset);
?>
<!--form que realiza filtro-->
<!--colocar action pro arquivo que faz o necessario para filtrar-->
<form id="form_filtrosTurmas" action="sistema.php" class="filter-form">
    <input type="hidden" name="pagina" value="TURMAS">
    <label for="filtrando">Escolha um filtro:</label>
    <select name="filtrando" id="filtrando">
        <option value="">=ESCOLHER=</option>
        <?php 
        $todasModalidades = listarModalidades();
        foreach ($todasModalidades as $mod): 
        ?>
            <option value="<?= $mod['MODALIDADECOD'] ?>" <?= ($filtroEscolhido == $mod['MODALIDADECOD']) ? 'selected' : '' ?>>
                <?= $mod['NOMEMOD'] ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">FILTRAR</button>
</form>

<?php if (empty($listaAlunosDashboard)): ?>
    <!--caso a pesquisa nao retorne nada-->
    <h1 class="text-center mt-4 mb-4">Nenhum resultado foi encontrado!</h1>
<?php else: ?>
    <div class="container p-2 mb-3">
        <table>
            <thead>
                <tr>
                    <th>DATA CAD.</th>
                    <th>MATRÍCULA</th>
                    <th>NOME</th>
                    <th>CPF</th>
                    <th>ARTE MARCIAL</th>
                    <th>PLANO</th>
                    <th>SITUAÇÃO</th>
                    <th>AÇÕES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaAlunosDashboard as $aluno): ?>
                    <tr>
                        <td>
                            <?php 
                                if (!empty($aluno['DATACADASTRO'])) {
                                    echo date('d/m/Y', strtotime($aluno['DATACADASTRO']));
                                }
                            ?>
                        </td>
                        <td>
                            <?= $aluno['ALUMATRICULA'] ?>
                        </td>
                        <td><?= $aluno['ALUNOME'] ?></td>
                        <td><?= $aluno['ALUCPF'] ?></td>
                        <td><span class="badge-arte"><?= $aluno['ARTES'] ?></span></td>
                        <td>
                            <?= $aluno['PLANO'] ?>
                            <br>
                            <small><?= $aluno['FREQUENCIA'] ?></small>
                        </td>
                        <td>
                            <div class="status">
                                <?php if (strtoupper($aluno['SITUACAO']) == 'PAGO'): ?>
                                    <span class="status-icon">✓</span>
                                <?php else: ?>
                                    <span class="status-icon inactive">✗</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="acoes">
                                <button class="btn-acao btn-edit" title="Editar">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button class="btn-acao btn-delete" title="Excluir">
                                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="paginacao d-flex justify-content-center mt-4">
            <?php if (isset($total_paginas) && $total_paginas > 1): ?>
                <nav aria-label="Navegação de página">
                    <ul class="pagination">
                        <li class="page-item <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="sistema.php?pagina=TURMAS&filtrando=<?= urlencode($filtroEscolhido) ?>&p=<?= $pagina_atual - 1 ?>">Anterior</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= ($pagina_atual == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="sistema.php?pagina=TURMAS&filtrando=<?= urlencode($filtroEscolhido) ?>&p=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
                            <a class="page-link" href="sistema.php?pagina=TURMAS&filtrando=<?= urlencode($filtroEscolhido) ?>&p=<?= $pagina_atual + 1 ?>">Próxima</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!--section com o divisor com a logo da gkt-->
<section id="divisor" class="d-flex justify-content-center align-items-center mt-3 mb-3">
    <img src="ASSETS/IMGS/LOGOS/logo_gktSF.png" alt="logo da academia gkt" width="100px">
</section>

<!--section container para as opcoes de gerenciamento-->
<section id="opcoesGerenciamento" class="d-flex flex-column justify-content-center align-items-center">

    <h1>Opções de gerenciamento</h1>

    <!--div centralizadora das opcoes-->
    <div class="d-flex flex-wrap justify-content-center">

        <div class="containerOpcoesGerenciamento d-flex flex-column justify-content-center align-items-center">

            <div id="gerenciarAlunosOp" class="iconeOpcoes">
                <img src="ASSETS/IMGS/ICONES/iconeAlunos.png" alt="icone bonequinhos">
            </div>

            <p class="textoEscuro">Gerenciar Alunos</p>
            <p>Visualizar todos alunos</p>

        </div>

        <div class="containerOpcoesGerenciamento d-flex flex-column justify-content-center align-items-center">

            <div id="cadastrarAlunosOp" class="iconeOpcoes">
                <img src="ASSETS/IMGS/ICONES/iconeCadastrar.png" alt="icone bonequinhos e simbolo de mais">
            </div>

            <p class="textoEscuro">Cadastrar</p>
            <p>Cadastro alunos/professores</p>

        </div>

        <div class="containerOpcoesGerenciamento d-flex flex-column justify-content-center align-items-center">

            <div id="verTurmasOp" class="iconeOpcoes">
                <img src="ASSETS/IMGS/ICONES/iconeTurmas.png" alt="icone bonequinhos">
            </div>

            <p class="textoEscuro">Turmas</p>
            <p>Visualizar turmas</p>

        </div>

    </div>

    <button id="btSair" 
    onclick="window.location.href='logout.php'"
    class="mb-5 mt-5 d-flex justify-content-center align-items-center">
        <img class="me-3" src="ASSETS/IMGS/ICONES/iconeSair.png" alt="bonequinho correndo simbolizando saida" width="30px">
        Sair
    </button>

</section>