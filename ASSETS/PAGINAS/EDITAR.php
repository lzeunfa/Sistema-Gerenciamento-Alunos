<!--html da pagina de edicao de aluno (LGPD art. 18, III: correcao de dados)-->
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
<?php else:
    $inscricoes = buscarInscricoes($matricula);
    $modsMarcadas = array_map('intval', array_column($inscricoes, 'ALUNOMOD'));
    $planoAtual = (int) ($inscricoes[0]['ALUNOPLANO'] ?? 0);
    $frequenciaAtual = (int) ($inscricoes[0]['ALUNOOPCAO'] ?? 0);
    $listarModalidades = listarModalidades();
    $listarPLanos = listarPlano();
    $listarFrequencia = listarFrequencia();
    $listarStatus = listarStatus();
?>
    <div class="page-header">
        <h1>Editar Aluno — matrícula <?= e($aluno['ALUMATRICULA']) ?></h1>
        <p>Corrija os dados do aluno. As mesmas regras do cadastro são aplicadas.</p>
    </div>

    <?php if ($mensagem): ?>
        <div class="form-mensagem form-mensagem-<?= e($mensagem['tipo']) ?>" role="alert"><?= e($mensagem['texto']) ?></div>
    <?php endif; ?>

    <div class="form-container">
        <form id="edicaoForm" method="POST" action="BACKEND/CONTROLLER/editarAluno.php">
            <?= csrfCampo() ?>
            <input type="hidden" name="matricula" value="<?= e($aluno['ALUMATRICULA']) ?>">
            <input type="hidden" name="voltar" value="<?= e($voltar) ?>">

            <div class="form-group">
                <label class="form-label" for="nome">Nome Completo <span class="required">*</span></label>
                <input type="text" class="form-input" id="nome" name="nome" value="<?= e($aluno['ALUNOME']) ?>"
                    minlength="3" maxlength="100" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="telefone">Telefone <span class="required">*</span></label>
                <input type="tel" id="telefone" class="form-input" name="telefone" value="<?= e($aluno['ALUTELEFONE']) ?>"
                    maxlength="20" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail <span class="required">*</span></label>
                <input type="email" class="form-input" id="email" name="email" value="<?= e($aluno['ALUEMAIL']) ?>" maxlength="100" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="cpf">CPF <span class="required">*</span></label>
                    <input type="text" class="form-input" id="cpf" name="cpf" value="<?= e($aluno['ALUCPF']) ?>" maxlength="14" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="data_nascimento">Data de Nascimento <span class="required">*</span></label>
                    <input type="date" class="form-input" id="data_nascimento" name="data_nascimento"
                        value="<?= e(substr((string) $aluno['ALUDATANASC'], 0, 10)) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Gênero <span class="required">*</span></label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" id="masculino" name="genero" value="masculino" <?= $aluno['ALUSEX'] === 'M' ? 'checked' : '' ?> required>
                        <label for="masculino">Masculino</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="feminino" name="genero" value="feminino" <?= $aluno['ALUSEX'] === 'F' ? 'checked' : '' ?> required>
                        <label for="feminino">Feminino</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="cep">CEP <span class="required">*</span></label>
                <input type="text" class="form-input" id="cep" name="cep" value="<?= e($aluno['ALUCEP']) ?>" maxlength="9" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="endereco">Endereço <span class="required">*</span></label>
                <input type="text" class="form-input" id="endereco" name="endereco" value="<?= e($aluno['ALUENDERECO']) ?>" maxlength="150" required>
            </div>

            <!-- Responsável legal (LGPD art. 14); é descartado se o aluno for maior de idade -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="responsavel_nome">
                        Nome do responsável <small>(obrigatório para menores de 18 anos)</small>
                    </label>
                    <input type="text" class="form-input" id="responsavel_nome" name="responsavel_nome"
                        value="<?= e($aluno['ALURESPNOME']) ?>" maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label" for="responsavel_cpf">
                        CPF do responsável <small>(obrigatório para menores de 18 anos)</small>
                    </label>
                    <input type="text" class="form-input" id="responsavel_cpf" name="responsavel_cpf"
                        value="<?= e($aluno['ALURESPCPF']) ?>" maxlength="14">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Modalidade(s) <span class="required">*</span></label>
                <div class="checkbox-group">
                    <?php foreach ($listarModalidades as $linha): $idBanco = 'mod' . $linha['MODALIDADECOD']; ?>
                        <div class="checkbox-option">
                            <input type="checkbox" id="<?= e($idBanco) ?>" name="modalidades[]" value="<?= e($linha['MODALIDADECOD']) ?>"
                                <?= in_array((int) $linha['MODALIDADECOD'], $modsMarcadas, true) ? 'checked' : '' ?>>
                            <label for="<?= e($idBanco) ?>"><?= e($linha['NOMEMOD']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="plano-pagamento">Plano <span class="required">*</span></label>
                <select class="form-select" id="plano-pagamento" name="plano-pagamento" required>
                    <option value="">Selecione o plano</option>
                    <?php foreach ($listarPLanos as $plano): ?>
                        <option value="<?= e($plano['PLANOCOD']) ?>" <?= (int) $plano['PLANOCOD'] === $planoAtual ? 'selected' : '' ?>><?= e($plano['PLANO']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="frequencia-dias">Frequência <span class="required">*</span></label>
                <select class="form-select" id="frequencia-dias" name="frequencia-dias" required>
                    <option value="">Selecione</option>
                    <?php foreach ($listarFrequencia as $frequencia): ?>
                        <option value="<?= e($frequencia['OPCAOCOD']) ?>" <?= (int) $frequencia['OPCAOCOD'] === $frequenciaAtual ? 'selected' : '' ?>><?= e($frequencia['OPCAO']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status <span class="required">*</span></label>
                <select class="form-select" id="status" name="status" required>
                    <option value="">Selecione</option>
                    <?php foreach ($listarStatus as $status): ?>
                        <option value="<?= e($status['STATUSCOD']) ?>" <?= (int) $status['STATUSCOD'] === (int) $aluno['ALUSTATUS'] ? 'selected' : '' ?>><?= e($status['STATUSALU']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>INATIVO encerra a matrícula: os dados pessoais são anonimizados 5 anos depois.</small>
            </div>

            <?php if ($aluno['ALUCIENCIAPRIVACIDADE'] === null): ?>
                <!-- cadastros antigos, feitos antes da Política de Privacidade (LGPD art. 9) -->
                <div class="form-group">
                    <div class="checkbox-option">
                        <input type="checkbox" id="ciencia_privacidade" name="ciencia_privacidade" value="1">
                        <label for="ciencia_privacidade">
                            O aluno (ou o responsável) foi informado agora sobre a
                            <a href="privacidade.php" target="_blank" rel="noopener">Política de Privacidade</a>.
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <a class="btn btn-secondary" href="sistema.php?pagina=<?= e($voltar) ?>">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </div>
        </form>
    </div>

    <!-- Direitos do titular: acesso/portabilidade e eliminação -->
    <div class="form-actions acoes-titular">
        <form method="POST" action="BACKEND/CONTROLLER/exportarAluno.php">
            <?= csrfCampo() ?>
            <input type="hidden" name="matricula" value="<?= e($aluno['ALUMATRICULA']) ?>">
            <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
            <button type="submit" class="btn btn-secondary">Exportar dados (JSON)</button>
        </form>
        <a class="btn btn-perigo" href="sistema.php?pagina=EXCLUIR&amp;matricula=<?= e(urlencode($aluno['ALUMATRICULA'])) ?>&amp;voltar=<?= e($voltar) ?>">Excluir dados pessoais</a>
    </div>
<?php endif; ?>
</div>
