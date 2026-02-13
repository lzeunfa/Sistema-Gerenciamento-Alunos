<?php
    //criando conexao no banco de dados
    use Dom\Mysql;
    
    $host_name = "localhost";
    $user_name = "root";
    $password = "";
    $db_name = "Gkt";

    try{
        $conn= new PDO("mysql:hostname=$host_name;dbname=$db_name" , $user_name,$password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }catch(PDOException $e){
        echo("Failed! " . $e->getMessage());
    }
?>