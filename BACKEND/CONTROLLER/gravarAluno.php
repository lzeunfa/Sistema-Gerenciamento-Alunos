<?php
require_once(__DIR__ . '/../../trava.php');
require_once(__DIR__ . '/funcoes.php');
exigirPostComCsrf();

function voltarAoCadastro(string $tipo, string $texto): void
{
    definirMensagem($tipo, $texto);
    header('Location: ../../sistema.php?pagina=CADASTRAR');
    exit();
}

[$aluno, $erros] = lerAlunoDoPost();
if (campo('ciencia_privacidade') !== '1') $erros[] = "ciência da Política de Privacidade";

if ($erros) {
    voltarAoCadastro('erro', "Verifique os campos: " . implode(', ', $erros) . ".");
}

$matricula_gerada = cadastrarAluno($aluno['nome'], $aluno['telefone'], $aluno['email'], $aluno['cpf'], $aluno['genero'], $aluno['dataNasc'], $aluno['cep'], $aluno['endereco'], $aluno['modalidades'], $aluno['plano'], $aluno['frequencia'], $aluno['status'], $aluno['responsavelNome'], $aluno['responsavelCpf']);

if ($matricula_gerada) {
    registrarAuditoria('cadastro_aluno', "matricula $matricula_gerada");
    voltarAoCadastro('sucesso', "Aluno cadastrado com sucesso! Matrícula $matricula_gerada.");
}
voltarAoCadastro('erro', "Erro ao cadastrar aluno. O CPF ou e-mail pode já estar cadastrado.");
?>
