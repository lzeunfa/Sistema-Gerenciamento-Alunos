<?php
require_once("../CONNECTION/config.php");
require_once("../CONTROLLER/funcoes.php");
$pdo = Database::getConexao();
$nome = strtoupper($_POST['nome']);
$telefone = $_POST['telefone'];
$email = strtolower($_POST['email']);
$cpf = $_POST['cpf'];
$dataNasc = $_POST['data_nascimento'];
$genero = substr(strtoupper($_POST['genero']), 0, 1);
$cep = $_POST['cep'];
$endereco = $_POST['endereco'];
$modalidade = isset($_POST['modalidades']) ? $_POST['modalidades'] : [];
$plano_pagamento = $_POST['plano-pagamento'];
$frequencia_dias = $_POST['frequencia-dias'];
$status = $_POST['status'] ?? 1;
$matricula_gerada = cadastrarAluno($nome, $telefone, $email, $cpf, $genero, $dataNasc, $cep, $endereco, $modalidade, $plano_pagamento, $frequencia_dias, $status);

if ($matricula_gerada) {
    echo ("<script>
            alert('Aluno cadastrado com sucesso!');
            window.location.href = '../../sistema.php?pagina=CADASTRAR';
          </script>");
} else {
    echo ("<script>
            alert('Erro ao cadastrar aluno. Verifique os dados e tente novamente.');
            window.history.back();
          </script>");
}
?>