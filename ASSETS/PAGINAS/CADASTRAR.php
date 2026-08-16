<!--html da pagina para casdstros-->
<?php
require_once "BACKEND/CONTROLLER/funcoes.php";
$listarModalidades = listarModalidades();
$listarPLanos = listarPlano();
$listarFrequencia = listarFrequencia();
$listarStatus = listarStatus();
?>
<div class="container_form">
    <!-- Cabeçalho -->
    <div class="page-header">
        <h1>Cadastrar Novo Aluno</h1>
        <p>Preencha os dados abaixo para cadastrar um novo aluno na academia</p>
    </div>

    <!-- Formulário -->
    <div class="form-container">
        <form id="cadastroForm" method="POST" action="BACKEND/CONTROLLER/gravarAluno.php">
            <!-- Nome -->
            <div class="form-group">
                <label class="form-label">
                    Nome Completo <span class="required">*</span>
                </label>
                <input type="text" class="form-input" name="nome" placeholder="Digite o nome completo do aluno"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Telefone <span class="required">*</span>
                </label>
                <input type="tel" id="telefone" class="form-input" name="telefone" placeholder="(99)99898-9898"
                    required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    E-mail <span class="required">*</span>
                </label>
                <input type="email" class="form-input" name="email" placeholder="Digite o email do aluno" required>
            </div>


            <!-- CPF e Data de Nascimento -->
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

            <!-- Gênero -->
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
                <input type="text" class="form-input" name="cep" placeholder="Digite o cep do aluno" required>
            </div>

            <!-- Endereço -->
            <div class="form-group">
                <label class="form-label">
                    Endereço <span class="required">*</span>
                </label>
                <input type="text" class="form-input" name="endereco" placeholder="Digite o endereço do aluno" required>
            </div>

            <!-- Modalidades -->
            <div class="form-group">
                <label class="form-label" required>
                    Modalidade(s) <span class="required">*</span>
                </label>
                <?php
                echo "<div class='checkbox-group'>";

                foreach ($listarModalidades as $linha) {
                    $idBanco = $linha['MODALIDADECOD'];

                    echo "<div class='checkbox-option'>";

                    // O id do input recebe o ID do banco. O name continua com []
                    echo "<input type='checkbox' id='" . $idBanco . "' name='modalidades[]' value='" . $idBanco . "'>";

                    // O 'for' da label recebe o mesmo ID para o clique funcionar
                    echo "<label for='" . $idBanco . "'>" . $linha['NOMEMOD'] . "</label>";

                    echo "</div>";
                }

                echo "</div>";
                ?>

            </div>

            <!-- Plano -->
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
                        echo ("<option value=" . $codPLano . ">" . $plano['PLANO'] . "</option>");
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <lab el class="form-label">
                    Frequência <span class="required">*</span>
                    </label>
                    <select class="form-select" name="frequencia-dias" required>
                        <option value="">Selecione</option>
                        <?php
                        foreach ($listarFrequencia as $frequencia) {
                            $codFrequencia = $frequencia['OPCAOCOD'];
                            echo ("<option value=" . $codFrequencia . "> " . $frequencia['OPCAO'] . "</option>");
                        }
                        ?>

                    </select>
            </div>

            <div class="form-group">
                <lab el class="form-label">
                    Status <span class="required">*</span>
                    </label>
                    <select class="form-select" name="status" required>
                        <option value="">Selecione</option>
                        <?php
                        foreach ($listarStatus as $status) {
                            $codStatus = $status['STATUSCOD'];
                            echo ("<option value=" . $codStatus . "> " . $status['STATUSALU'] . "</option>");
                        }
                        ?>

                    </select>
            </div>


            <!-- Botões de Ação -->
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

    <button id="btSair" onclick="window.location.href='logout.php'"
        class="mb-5 mt-5 d-flex justify-content-center align-items-center">
        <img class="me-3" src="ASSETS/IMGS/ICONES/iconeSair.png" alt="bonequinho correndo simbolizando saida"
            width="30px">
        Sair
    </button>

</section>