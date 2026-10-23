<?php /* html da pagina para casdstros */ ?>
<?php
require_once "BACKEND/CONTROLLER/funcoes.php";
$listarModalidades = listarModalidades();
$listarPLanos = listarPlano();
$listarFrequencia = listarFrequencia();
$listarStatus = listarStatus();
$mensagem = pegarMensagem();
?>
<div class="container_form">
    <?php /* Cabeçalho */ ?>
    <div class="page-header">
        <h1>Cadastrar Novo Aluno</h1>
        <p>Preencha os dados abaixo para cadastrar um novo aluno na academia</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="form-mensagem form-mensagem-<?= e($mensagem['tipo']) ?>" role="alert"><?= e($mensagem['texto']) ?></div>
    <?php endif; ?>

    <?php /* Formulário */ ?>
    <div class="form-container">
        <form id="cadastroForm" method="POST" action="BACKEND/CONTROLLER/gravarAluno.php">
            <?= csrfCampo() ?>
            <?php /* Nome */ ?>
            <div class="form-group">
                <label class="form-label">
                    Nome Completo <span class="required">*</span>
                </label>
                <input type="text" class="form-input" name="nome" placeholder="Digite o nome completo do aluno"
                    minlength="3" maxlength="100" required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Telefone <span class="required">*</span>
                </label>
                <input type="tel" id="telefone" class="form-input" name="telefone" placeholder="(99)99898-9898"
                    maxlength="20" required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    E-mail <span class="required">*</span>
                </label>
                <input type="email" class="form-input" name="email" placeholder="Digite o email do aluno" maxlength="100" required>
            </div>


            <?php /* CPF e Data de Nascimento */ ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        CPF <span class="required">*</span>
                    </label>
                    <input type="text" class="form-input" name="cpf" placeholder="000.000.000-00" maxlength="14"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Data de Nascimento <span class="required">*</span>
                    </label>
                    <input type="date" class="form-input" name="data_nascimento" required>
                </div>
            </div>

            <?php /* Gênero */ ?>
            <div class="form-group">
                <label class="form-label">
                    Gênero <span class="required">*</span>
                </label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="masculino" name="genero" value="masculino" required>
                        <label for="masculino">Masculino</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="feminino" name="genero" value="feminino" required>
                        <label for="feminino">Feminino</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    CEP <span class="required">*</span>
                </label>
                <input type="text" class="form-input" name="cep" placeholder="Digite o cep do aluno" maxlength="9" required>
            </div>

            <?php /* Endereço */ ?>
            <div class="form-group">
                <label class="form-label">
                    Endereço <span class="required">*</span>
                </label>
                <input type="text" class="form-input" name="endereco" placeholder="Digite o endereço do aluno" maxlength="150" required>
            </div>

            <?php /* Responsável legal (LGPD art. 14: dados de criança e adolescente) */ ?>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        Nome do responsável <small>(obrigatório para menores de 18 anos)</small>
                    </label>
                    <input type="text" class="form-input" name="responsavel_nome" placeholder="Nome do pai, mãe ou responsável"
                        maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        CPF do responsável <small>(obrigatório para menores de 18 anos)</small>
                    </label>
                    <input type="text" class="form-input" name="responsavel_cpf" placeholder="000.000.000-00" maxlength="14">
                </div>
            </div>

            <?php /* Modalidades */ ?>
            <div class="form-group">
                <label class="form-label" required>
                    Modalidade(s) <span class="required">*</span>
                </label>
                <?php
                echo "<div class='checkbox-group'>";

                foreach ($listarModalidades as $linha) {
                    $idBanco = e('mod' . $linha['MODALIDADECOD']);

                    echo "<div class='checkbox-option'>";

                    // O id do input recebe o ID do banco. O name continua com []
                    echo "<input type='checkbox' id='" . $idBanco . "' name='modalidades[]' value='" . e($linha['MODALIDADECOD']) . "'>";

                    // O 'for' da label recebe o mesmo ID para o clique funcionar
                    echo "<label for='" . $idBanco . "'>" . e($linha['NOMEMOD']) . "</label>";

                    echo "</div>";
                }

                echo "</div>";
                ?>

            </div>

            <?php /* Plano */ ?>
            <div class="form-group">
                <label class="form-label">
                    Plano <span class="required">*</span>
                </label>
                <select class="form-select" name="plano-pagamento" required>
                    <option value="">Selecione o plano</option>
                    <?php
                    //O programador que fez este código é burro, erra o nome da variável que ele mesmo criou.
                    foreach ($listarPLanos as $plano) {
                        $codPLano = $plano['PLANOCOD'];
                        echo ("<option value='" . e($codPLano) . "'>" . e($plano['PLANO']) . "</option>");
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Frequência <span class="required">*</span>
                </label>
                <select class="form-select" name="frequencia-dias" required>
                    <option value="">Selecione</option>
                    <?php
                    foreach ($listarFrequencia as $frequencia) {
                        $codFrequencia = $frequencia['OPCAOCOD'];
                        echo ("<option value='" . e($codFrequencia) . "'>" . e($frequencia['OPCAO']) . "</option>");
                    }
                    ?>

                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Status <span class="required">*</span>
                </label>
                <select class="form-select" name="status" required>
                    <option value="">Selecione</option>
                    <?php
                    foreach ($listarStatus as $status) {
                        $codStatus = $status['STATUSCOD'];
                        echo ("<option value='" . e($codStatus) . "'>" . e($status['STATUSALU']) . "</option>");
                    }
                    ?>

                </select>
            </div>

            <?php /* Transparência (LGPD art. 9) */ ?>
            <div class="form-group">
                <div class="checkbox-option">
                    <input type="checkbox" id="ciencia_privacidade" name="ciencia_privacidade" value="1" required>
                    <label for="ciencia_privacidade">
                        O aluno (ou o responsável, se for menor de idade) foi informado sobre a
                        <a href="privacidade.php" target="_blank" rel="noopener">Política de Privacidade</a>
                        e, no caso de menor, o responsável autorizou o tratamento dos dados. <span class="required">*</span>
                    </label>
                </div>
            </div>


            <?php /* Botões de Ação */ ?>
            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    Cadastrar Aluno
                </button>
            </div>
        </form>
    </div>
</div>

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