<?php /* html da pagina dos alunos */ ?>

<?php /* form que realiza filtro */ ?>
<?php /* colocar action pro arquivo que faz o necessario para filtrar */ ?>
<?php
require_once "BACKEND/CONTROLLER/funcoes.php";
$filtroEscolhido = isset($_GET['filtrando']) && is_string($_GET['filtrando']) ? $_GET['filtrando'] : '';

$listagem = dadosListagem($filtroEscolhido, $_GET['p'] ?? null);
$listaAlunosDashboard = $listagem['alunos'];
$pagina_atual = $listagem['pagina'];
$total_paginas = $listagem['totalPaginas'];
?>

<form id="form_filtrosAlunos" action="sistema.php" class="filter-form">
    <input type="hidden" name="pagina" value="ALUNOS">

    <label for="filtrando">Escolha um filtro :</label>

    <select name="filtrando" id="filtrando">

        <option value="">=ESCOLHER=</option>

        <option value="alunos_pendentes" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'alunos_pendentes' ? 'selected' : '' ?>>PENDENTES</option>

        <option value="alunos_pagos" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'alunos_pagos' ? 'selected' : '' ?>>PAGOS</option>

        <option value="plano_2" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'plano_2' ? 'selected' : '' ?>>PLANO 2X</option>

        <option value="plano_3" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'plano_3' ? 'selected' : '' ?>>PLANO 3X</option>

        <option value="plano_4" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'plano_4' ? 'selected' : '' ?>>PLANO 4X</option>

        <option value="alunos_homens" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'alunos_homens' ? 'selected' : '' ?>>Homens</option>

        <option value="alunos_mulheres" <?php echo isset($_GET['filtrando']) && $_GET['filtrando'] == 'alunos_mulheres' ? 'selected' : '' ?>>Mulheres</option>

    </select>

    <button type="submit">FILTRAR</button>

</form>

<?php $mensagem = pegarMensagem(); if ($mensagem): ?>
    <div class="form-mensagem form-mensagem-<?= e($mensagem['tipo']) ?> mt-3" role="alert"><?= e($mensagem['texto']) ?></div>
<?php endif; ?>

<?php if (empty($listaAlunosDashboard)): ?>
    <?php /* caso a pesquisa nao retorne nada */ ?>
    <h1 class="text-center mt-4 mb-4">Nenhum resultado foi encontrado!</h1>
<?php else: ?>
    <div class="container mb-3 p-1">

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
                        <?php /* Como não puxamos a data do BD na query, coloquei a matrícula aqui provisoriamente. 
                     Você pode adicionar A.ALUDATANASC na query depois se quiser */ ?>
                        <td>
                            <?php
                            if (!empty($aluno['DATACADASTRO'])) {
                                echo date('d/m/Y', strtotime($aluno['DATACADASTRO']));
                            }
                            ?>
                        </td>
                        <td>
                            <?= e($aluno['ALUMATRICULA']) ?>
                        </td>

                        <td>
                            <?= e($aluno['ALUNOME']) ?>
                        </td>
                        <td>
                            <?= e(mascararCpf($aluno['ALUCPF'])) ?>
                        </td>

                        <td><span class="badge-arte">
                                <?= e($aluno['ARTES']) ?>
                            </span></td>

                        <td>
                            <?= e($aluno['PLANO']) ?>
                            <br>
                            <small><?= e($aluno['FREQUENCIA']) ?></small>
                        </td>

                        <td>
                            <div class="status">
                                <?php if (strtoupper($aluno['SITUACAO']) == 'PAGO'): ?>
                                    <span class="status-icon">✓</span>
                                <?php else: ?>
                                    <?php /* O inactive deixa o ícone cinza ou vermelho, dependendo do seu CSS */ ?>
                                    <span class="status-icon inactive">✗</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td>
                            <?php $paginaVolta = 'ALUNOS'; include 'ASSETS/PAGINAS/_acoesAluno.php'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>


        </table>

        <?php $paginaVolta = 'ALUNOS'; include 'ASSETS/PAGINAS/_paginacao.php'; ?>

    </div>
<?php endif; ?>

<?php /* section com o divisor com a logo da gkt */ ?>
<section id="divisor" class="d-flex justify-content-center align-items-center mt-3 mb-3">
    <img src="ASSETS/IMGS/LOGOS/logo_gktSF.png" alt="logo da academia gkt" width="100px">
</section>

<?php /* section container para as opcoes de gerenciamento */ ?>
<section id="opcoesGerenciamento" class="d-flex flex-column justify-content-center align-items-center">

    <h1>Opções de gerenciamento</h1>

    <?php /* div centralizadora das opcoes */ ?>
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

    <form action="logout.php" method="POST">
        <?= csrfCampo() ?>
        <button id="btSair" type="submit" class="mb-5 mt-5 d-flex justify-content-center align-items-center">
            <img class="me-3" src="ASSETS/IMGS/ICONES/iconeSair.png" alt="bonequinho correndo simbolizando saida"
                width="30px">
            Sair
        </button>
    </form>

</section>