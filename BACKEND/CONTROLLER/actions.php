<?php 
    session_start();
    require("../CONECTION/config.php");
    
    $userName = $_POST['inputUser'] ?? '';
    $userPass = $_POST['inputUserPass'] ?? '';
    
    $sql = "SELECT id_user, password_user FROM users WHERE email_user = :userName LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":userName", $userName);
    $stmt->execute();

    if($stmt->rowCount() > 0){
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if($userPass == $data['password_user']){
            $_SESSION['user_id'] =  $data['id_user'];
            $_SESSION['user_name'] = $userName;
            header('Location: ../../index.php');
            exit();
        }else{
            $_SESSION['login_error'] = "Senha incorreta!";
            header('Location: ../../index.php');
            exit();    
        }
    }else{
        $_SESSION['login_error'] = "Usuario nao encontrardo!";
        header('Location: ../../index.php');
        exit();
    }

?>