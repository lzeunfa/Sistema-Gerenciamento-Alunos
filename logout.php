<?php
require_once(__DIR__ . '/BACKEND/SEGURANCA/seguranca.php');
//so aceita POST com token: um link ou imagem em outro site nao consegue deslogar o usuario
exigirPostComCsrf();
registrarAuditoria('logout');

// Passo 2: Tomar o crachá
session_unset();
// Passo 3: Rasgar o crachá
session_destroy();
// Passo 4: Expulsar do prédio
header("Location: index.php");
exit();
