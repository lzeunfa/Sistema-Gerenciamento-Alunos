<?php
// Passo 1: Acessar a portaria
// Dica: Use a função do PHP que inicia/retoma uma sessão.
session_start();

// Passo 2: Tomar o crachá
// Dica: Use a função do PHP que limpa as variáveis globais da sessão.
session_unset();
// Passo 3: Rasgar o crachá
// Dica: Use a função do PHP que destrói a sessão atual.
session_destroy();
// Passo 4: Expulsar do prédio
// Dica: Use a função do PHP que altera o "header" (cabeçalho) enviando um Location para 'index.php', e não esqueça de colocar um exit() logo abaixo para parar a execução!
header("Location: index.php");
exit();
