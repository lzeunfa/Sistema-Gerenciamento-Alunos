<?php
//So para o PHPStan: declara as constantes que tests/bootstrap.php define ao rodar os testes.
//(O PHPStan nao executa o bootstrap, que conecta no banco e inicia sessao.)
define('RAIZ_PROJETO', dirname(__DIR__));
define('BANCO_TESTE', 'gktgym_teste');
