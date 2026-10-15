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

//le um campo de texto do POST; arrays e outros tipos viram texto vazio
function campo(string $nome): string
{
    $valor = $_POST[$nome] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

function soDigitos(string $valor): string
{
    return preg_replace('/\D/', '', $valor);
}

function cpfValido(string $cpf): bool
{
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        if ((int) $cpf[$t] !== ((10 * $soma) % 11) % 10) {
            return false;
        }
    }
    return true;
}

function idsInteiros($valores): array
{
    if (!is_array($valores)) {
        return [];
    }
    $ids = [];
    foreach ($valores as $valor) {
        if (is_string($valor) && ctype_digit($valor)) {
            $ids[] = (int) $valor;
        }
    }
    return array_values(array_unique($ids));
}

$nome = mb_strtoupper(campo('nome'));
$telefone = soDigitos(campo('telefone'));
$email = mb_strtolower(campo('email'));
$cpf = soDigitos(campo('cpf'));
$dataNasc = campo('data_nascimento');
$genero = substr(strtoupper(campo('genero')), 0, 1);
$cep = soDigitos(campo('cep'));
$endereco = campo('endereco');
$responsavelNome = mb_strtoupper(campo('responsavel_nome'));
$responsavelCpf = soDigitos(campo('responsavel_cpf'));
$modalidade = idsInteiros($_POST['modalidades'] ?? []);
$plano_pagamento = ctype_digit(campo('plano-pagamento')) ? (int) campo('plano-pagamento') : 0;
$frequencia_dias = ctype_digit(campo('frequencia-dias')) ? (int) campo('frequencia-dias') : 0;
$status = ctype_digit(campo('status')) ? (int) campo('status') : 0;

$nascimento = DateTime::createFromFormat('!Y-m-d', $dataNasc);
$idade = $nascimento ? $nascimento->diff(new DateTime('today'))->y : -1;

$erros = [];
if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) $erros[] = "nome";
if (strlen($telefone) < 10 || strlen($telefone) > 11) $erros[] = "telefone";
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $erros[] = "e-mail";
if (!cpfValido($cpf)) $erros[] = "CPF";
if (!$nascimento || $nascimento->format('Y-m-d') !== $dataNasc || $nascimento > new DateTime('today') || $idade > 120) $erros[] = "data de nascimento";
if (!in_array($genero, ['M', 'F'], true)) $erros[] = "gênero";
if (strlen($cep) !== 8) $erros[] = "CEP";
if ($endereco === '' || mb_strlen($endereco) > 150) $erros[] = "endereço";
if (empty($modalidade)) $erros[] = "modalidade";
if ($plano_pagamento <= 0) $erros[] = "plano";
if ($frequencia_dias <= 0) $erros[] = "frequência";
if ($status <= 0) $erros[] = "status";
if (campo('ciencia_privacidade') !== '1') $erros[] = "ciência da Política de Privacidade";

//LGPD art. 14: menor de idade precisa de responsavel identificado
if ($idade >= 0 && $idade < 18) {
    if (mb_strlen($responsavelNome) < 3 || mb_strlen($responsavelNome) > 100) $erros[] = "nome do responsável";
    if (!cpfValido($responsavelCpf)) $erros[] = "CPF do responsável";
} else {
    //adulto: nao guardar dados de responsavel sem necessidade (minimizacao)
    $responsavelNome = '';
    $responsavelCpf = '';
}

if ($erros) {
    voltarAoCadastro('erro', "Verifique os campos: " . implode(', ', $erros) . ".");
}

$matricula_gerada = cadastrarAluno($nome, $telefone, $email, $cpf, $genero, $dataNasc, $cep, $endereco, $modalidade, $plano_pagamento, $frequencia_dias, $status, $responsavelNome, $responsavelCpf);

if ($matricula_gerada) {
    registrarAuditoria('cadastro_aluno', "matricula $matricula_gerada");
    voltarAoCadastro('sucesso', "Aluno cadastrado com sucesso! Matrícula $matricula_gerada.");
}
voltarAoCadastro('erro', "Erro ao cadastrar aluno. O CPF ou e-mail pode já estar cadastrado.");
?>
