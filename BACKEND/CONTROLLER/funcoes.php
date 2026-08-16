<?php
require_once(__DIR__ . '/../CONNECTION/config.php');
function cadastrarAluno($nome, $telefone, $email, $cpf, $genero, $dataNasc, $cep, $endereco, $modalidade, $plano_pagamento, $frequencia_dias, $status)
{
     try {
          $pdo = Database::getConexao();
          $pdo->beginTransaction();
          $sqlCadastroAluno = "INSERT INTO ALUNOS
            (ALUMATRICULA,ALUNOME,ALUTELEFONE,ALUEMAIL,ALUCPF,ALUSEX,ALUDATANASC,ALUCEP,ALUENDERECO,ALUSTATUS)
            VALUES(:ALUMATRICULA, :ALUNOME, :ALUTELEFONE, :ALUEMAIL, :ALUCPF, :ALUSEX, :ALUDATANASC, :ALUCEP, :ALUENDERECO,:ALUSTATUS)";
          $stmt = $pdo->prepare($sqlCadastroAluno);
          $matricula_gerada = gerarProximaMatricula($pdo);
          $stmt->bindParam(':ALUMATRICULA', $matricula_gerada);
          $stmt->bindParam(':ALUNOME', $nome);
          $stmt->bindParam(':ALUTELEFONE', $telefone);
          $stmt->bindParam(':ALUEMAIL', $email);
          $stmt->bindParam(':ALUCPF', $cpf);
          $stmt->bindParam(':ALUSEX', $genero);
          $stmt->bindParam(':ALUDATANASC', $dataNasc);
          $stmt->bindParam(':ALUCEP', $cep);
          $stmt->bindParam(':ALUENDERECO', $endereco);
          $stmt->bindParam(':ALUSTATUS', $status);
          $resultado = $stmt->execute();
          
          if ($resultado) {
               $sql = "INSERT INTO ALUNOS_INSCRICAO(ALUNOMAT,ALUNOMOD,ALUNOPLANO,ALUNOOPCAO)
                    VALUES(:ALUMAT,:ALUMOD,:ALUPLANO,:ALUNOOPCAO)";
               $stmt = $pdo->prepare($sql);
               foreach ($modalidade as $mod) {
                    $stmt->bindParam(':ALUMAT', $matricula_gerada);
                    $stmt->bindParam(':ALUMOD', $mod);
                    $stmt->bindParam(':ALUPLANO', $plano_pagamento);
                    $stmt->bindParam(':ALUNOOPCAO', $frequencia_dias);
                    $stmt->execute();
               }

               $pdo->commit();
               return $matricula_gerada;
          } else {
               $pdo->rollBack();
               return false;
          }

     } catch (PDOException $error) {
          if (isset($pdo) && $pdo->inTransaction()) {
               $pdo->rollBack();
          }
          return false;
     }
}
function gerarProximaMatricula($pdo)
{
     $sqlMat = "SELECT MAX(CAST(ALUMATRICULA AS INT)) AS maior_matricula FROM ALUNOS";
     $stmt = $pdo->query($sqlMat);
     $fetch = $stmt->fetch();
     $ultima_matricula = $fetch['maior_matricula'];

     if ($ultima_matricula == null) {
          return 14001;
     } else {
          return $ultima_matricula + 1;
     }

}

function listarModalidades()
{
     $pdo = Database::getConexao();
     $sql = "SELECT MODALIDADECOD,NOMEMOD FROM MODALIDADES";
     $stmt = $pdo->prepare($sql);
     $stmt->execute();
     $modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $modalidades;
}
function listarPlano()
{
     $pdo = Database::getConexao();
     $sql = "SELECT PLANOCOD,PLANO FROM PLANOS";
     $stmt = $pdo->prepare($sql);
     $stmt->execute();
     $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $planos;
}
function listarFrequencia()
{
     $pdo = Database::getConexao();
     $sql = "SELECT OPCAOCOD,OPCAO FROM OPCAOSEMANA";
     $stmt = $pdo->prepare($sql);
     $stmt->execute();
     $frequencia = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $frequencia;
}

function listarAluno()
{
     $pdo = Database::getConexao();
     $sql = "SELECT * FROM ALUNOS";
     $stmt = $pdo->prepare($sql);
     $stmt->execute();
     $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $alunos;
}
function listarStatus()
{
     $pdo = Database::getConexao();
     $sql = "SELECT STATUSCOD,STATUSALU FROM ALUNOS_STATUS";
     $stmt = $pdo->prepare($sql);
     $stmt->execute();
     $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $status;
}
function listarAlunosCompletos($filtro = "", $limite = null, $offset = null)
{
     $pdo = Database::getConexao();

     // 1. Iniciamos a variável vazia
     $clausulaWhere = "";

     // 2. Preenchemos a regra de acordo com o que veio da tela
     if (is_numeric($filtro)) {
          // Filtro por ID da modalidade
          $clausulaWhere = " WHERE I.ALUNOMOD = " . intval($filtro) . " ";
     } else {
          switch ($filtro) {
               case 'alunos_pendentes':
                    $clausulaWhere = " WHERE S.STATUSALU != 'PAGO' ";
                    break;
               case 'alunos_pagos':
                    $clausulaWhere = " WHERE S.STATUSALU = 'PAGO' ";
                    break;
               case 'alunos_homens':
                    $clausulaWhere = " WHERE A.ALUSEX = 'M' ";
                    break;
               case 'alunos_mulheres':
                    $clausulaWhere = " WHERE A.ALUSEX = 'F' ";
                    break;
               case 'plano_2':
                    $clausulaWhere = " WHERE O.OPCAO LIKE '%2X%' ";
                    break;
               case 'plano_3':
                    $clausulaWhere = " WHERE O.OPCAO LIKE '%3X%' ";
                    break;
               case 'plano_4':
                    $clausulaWhere = " WHERE O.OPCAO LIKE '%4X%' ";
                    break;
          }
     }

     // 3. Colocamos o $clausulaWhere ANTES do GROUP BY no SQL
     $sql = "SELECT 
                 A.ALUMATRICULA, 
                 A.DATACADASTRO,
                 A.ALUNOME, 
                 A.ALUCPF, 
                 STRING_AGG(M.NOMEMOD, ', ') AS ARTES, 
                 P.PLANO, 
                 O.OPCAO AS FREQUENCIA,
                 S.STATUSALU AS SITUACAO
                          FROM ALUNOS A
             INNER JOIN ALUNOS_INSCRICAO I ON A.ALUMATRICULA = I.ALUNOMAT
             INNER JOIN MODALIDADES M ON I.ALUNOMOD = M.MODALIDADECOD
             INNER JOIN PLANOS P ON I.ALUNOPLANO = P.PLANOCOD
             INNER JOIN ALUNOS_STATUS S ON A.ALUSTATUS = S.STATUSCOD
             INNER JOIN OPCAOSEMANA O ON I.ALUNOOPCAO = O.OPCAOCOD
             
             $clausulaWhere 
             
             GROUP BY 
                 A.ALUMATRICULA, A.DATACADASTRO, A.ALUNOME, A.ALUCPF, P.PLANO, O.OPCAO, S.STATUSALU
             ORDER BY A.ALUMATRICULA DESC";

     if ($limite !== null && $offset !== null) {
          $sql .= " OFFSET :offset ROWS FETCH NEXT :limite ROWS ONLY";
     }

     $stmt = $pdo->prepare($sql);

     if ($limite !== null && $offset !== null) {
          $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
          $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
     }

     $stmt->execute();

     // O FETCH_ASSOC transforma os dados em um array associativo
     $alunos = $stmt->fetchAll(PDO::FETCH_ASSOC);
     return $alunos;
}

function contarAlunosCompletos($filtro = "")
{
     $pdo = Database::getConexao();
     $clausulaWhere = "";

     if (is_numeric($filtro)) {
          $clausulaWhere = " WHERE I.ALUNOMOD = " . intval($filtro) . " ";
     } else {
          switch ($filtro) {
               case 'alunos_pendentes':
                    $clausulaWhere = " WHERE S.STATUSALU != 'PAGO' ";
                    break;
               case 'alunos_pagos':
                    $clausulaWhere = " WHERE S.STATUSALU = 'PAGO' ";
                    break;
               case 'alunos_homens':
                    $clausulaWhere = " WHERE A.ALUSEX = 'M' ";
                    break;
               case 'alunos_mulheres':
                    $clausulaWhere = " WHERE A.ALUSEX = 'F' ";
                    break;
               case 'plano_2':
                    $clausulaWhere = " WHERE O.OPCAO LIKE '%2X%' ";
                    break;
               case 'plano_3':
                    $clausulaWhere = " WHERE O.OPCAO LIKE '%3X%' ";
                    break;
               case 'plano_4':
                    $clausulaWhere = " WHERE O.OPCAO LIKE '%4X%' ";
                    break;
          }
     }

     $sql = "SELECT COUNT(DISTINCT A.ALUMATRICULA) AS total
             FROM ALUNOS A
             INNER JOIN ALUNOS_INSCRICAO I ON A.ALUMATRICULA = I.ALUNOMAT
             INNER JOIN MODALIDADES M ON I.ALUNOMOD = M.MODALIDADECOD
             INNER JOIN PLANOS P ON I.ALUNOPLANO = P.PLANOCOD
             INNER JOIN ALUNOS_STATUS S ON A.ALUSTATUS = S.STATUSCOD
             INNER JOIN OPCAOSEMANA O ON I.ALUNOOPCAO = O.OPCAOCOD
             $clausulaWhere";

     $stmt = $pdo->prepare($sql);
     $stmt->execute();
     $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
     
     return $resultado ? (int)$resultado['total'] : 0;
}
?>