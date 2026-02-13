<?php 

    //criando conexao no banco de dados
    define('HOST', 'localhost');
    define('USER', 'root');
    define('PASS', '');
    define('BASE', '?');

    $conn = new MySQLi(HOST, USER, PASS, BASE);

?>