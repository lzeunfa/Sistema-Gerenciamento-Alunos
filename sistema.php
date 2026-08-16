<?php
$pagina = isset($_GET['pagina']) ? strtoupper($_GET['pagina']) : 'DASHBOARD';
$paginas_permitidas = ['DASHBOARD', 'ALUNOS', 'CADASTRAR', 'TURMAS'];

if (!in_array($pagina, $paginas_permitidas)) {
    $pagina = 'DASHBOARD';
}

$nomePagina = strtolower($pagina);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Página do dashboard da GKT">
    <title>Dashboard GKT</title>

    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">

    <!--css geral-->
    <link rel="stylesheet" href="ASSETS/CSS/index.css">

    <!--css do header esta neste css dashboard-->
    <link rel="stylesheet" href="ASSETS/CSS/dashboard.css">

    <!--css alunos-->
    <link rel="stylesheet" href="ASSETS/CSS/alunos.css">
    
    <!--css alunos-->
    <link rel="stylesheet" href="ASSETS/CSS/cadastrar.css">

    <script></script>

    <!--media querie css-->
    <link rel="stylesheet" href="ASSETS/CSS/mq.css">

    <!--bootstrap css-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>

<body>
    <!--section para organizacao de conteudos menu e header-->
    <section id="menuHeader" class="d-flex">

        <aside id="menuLateral">

            <!--banner com nome da academia-->
            <div id="bannerMenu">

                <h1>GKT</h1>
                <p>Artes Marciais</p>

            </div>

            <div id="opcoesMenu" class="d-flex flex-column">

                <a href="sistema.php?pagina=DASHBOARD" id="dashboardPage" class="linkOpcoes <?= $pagina == 'DASHBOARD' ? 'linkSelecionado' : '' ?>">
                    <img src="ASSETS/IMGS/ICONES/iconeDashboard.png" alt="icone simbolizando area de dashboard" width="35px">
                    DASHBOARD
                </a>

                <a href="sistema.php?pagina=ALUNOS" id="studentsPage" class="linkOpcoes <?= $pagina == 'ALUNOS' ? 'linkSelecionado' : '' ?>">
                    <img src="ASSETS/IMGS/ICONES/iconeAlunos.png" alt="icone simbolizando area de dashboard" width="35px">
                    ALUNOS
                </a>
                
                <a href="sistema.php?pagina=CADASTRAR" id="registerPage" class="linkOpcoes <?= $pagina == 'CADASTRAR' ? 'linkSelecionado' : '' ?>">
                    <img src="ASSETS/IMGS/ICONES/iconeCadastrar.png" alt="icone simbolizando area de dashboard" width="35px">
                    CADASTRAR
                </a>
                
                <a href="sistema.php?pagina=TURMAS" id="classesPage" class="linkOpcoes <?= $pagina == 'TURMAS' ? 'linkSelecionado' : '' ?>">
                    <img src="ASSETS/IMGS/ICONES/iconeTurmas.png" alt="icone simbolizando area de dashboard" width="35px">
                    TURMAS
                </a>

            </div>

        </aside>

        <section id="sectionCentralizadora" class="d-flex flex-column w-100">

            <!--cabecalho-->
            <header>
                <!--section para conteúdo informativo da pagina no header-->
                <section class="d-flex flex-column">

                    <div class="d-flex align-items-center justify-content-center">

                        <!--icone da pagina-->
                        <img src="ASSETS/IMGS/ICONES/house-regular-full.svg" alt="icone de casa simbolizando pagina de dashboard" width="50px">

                        <!--este texto muda de acordo com a página acessada-->
                        <h1 class="m-0" id="nomePA"><?= $nomePagina ?></h1>

                    </div>

                    <!--texto dentro do span deve mudar de acordo com a página-->
                    <p class="textoAzul ps-2">home / <span id="caminhoPA"><?= $nomePagina ?></span></p>

                </section>
                <!--logo da gkt-->
                <img class="logoGKT me-2" src="ASSETS/IMGS/LOGOS/logo_gktCF.png" alt="logo da academia gkt">
            </header>

            <!--tag que recebera troca de html via interação-->
            <main id="mainConteudoPagina" class="mt-2 d-flex align-items-center justify-content-center flex-column">
                
                <?php include "ASSETS/PAGINAS/{$pagina}.php"; ?>

            </main>

        </section>

    </section>

    <!--js das interações (se necessário futuramente)-->
    <!-- script controle_paginas removido -- >

    <!--bootstrap js-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>