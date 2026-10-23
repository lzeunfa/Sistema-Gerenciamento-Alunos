<?php
require_once(__DIR__ . '/../CONNECTION/config.php');
function cadastrarAluno($nome, $telefone, $email, $cpf, $genero, $dataNasc, $cep, $endereco, $modalidade, $plano_pagamento, $frequencia_dias, $status, $responsavelNome = '', $responsavelCpf = '')
{
     try {
          $pdo = Database::getConexao();
          $pdo->beginTransaction();
          //aluno ja cadastrado como INATIVO comeca a contar os 5 anos de guarda hoje (DB/anonimizar_antigos.php)
          $sqlCadastroAluno = "INSERT INTO ALUNOS
            (ALUMATRICULA,ALUNOME,ALUTELEFONE,ALUEMAIL,ALUCPF,ALUSEX,ALUDATANASC,ALUCEP,ALUENDERECO,ALUSTATUS,ALURESPNOME,ALURESPCPF,ALUCIENCIAPRIVACIDADE,ALUDATAINATIVACAO)
            VALUES(:ALUMATRICULA, :ALUNOME, :ALUTELEFONE, :ALUEMAIL, :ALUCPF, :ALUSEX, :ALUDATANASC, :ALUCEP, :ALUENDERECO,:ALUSTATUS,:ALURESPNOME,:ALURESPCPF,GETDATE(),
                    CASE WHEN :STATUSNOVO = :INATIVO THEN GETDATE() ELSE NULL END)";
          $responsavelNome = $responsavelNome !== '' ? $responsavelNome : null;
          $responsavelCpf = $responsavelCpf !== '' ? $responsavelCpf : null;
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
          $stmt->bindParam(':ALURESPNOME', $responsavelNome);
          $stmt->bindParam(':ALURESPCPF', $responsavelCpf);
          $inativo = codigoStatusInativo() ?? -1;
          $stmt->bindParam(':STATUSNOVO', $status);
          $stmt->bindParam(':INATIVO', $inativo);
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
          error_log("GKT erro ao cadastrar aluno: " . $error->getMessage());
          return false;
     }
}
//precisa ser chamada dentro da transacao do cadastro
function gerarProximaMatricula($pdo)
{
     //dois cadastros ao mesmo tempo liam o mesmo MAX e o segundo falhava com matricula duplicada (achado no teste
     //de carga). A trava de aplicacao enfileira so os cadastros, ate o commit/rollback; as telas de consulta nao esperam
     $trava = $pdo->query("SET NOCOUNT ON; DECLARE @r INT;
          EXEC @r = sp_getapplock @Resource = 'gkt_proxima_matricula', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 10000;
          SELECT @r")->fetchColumn();
     if ((int) $trava < 0) {
          throw new PDOException("trava da proxima matricula nao obtida (codigo $trava)");
     }
     //TRY_CAST: uma matricula com letras (matriculaValida aceita) nao pode derrubar todos os cadastros
     $sqlMat = "SELECT MAX(TRY_CAST(ALUMATRICULA AS INT)) AS maior_matricula FROM ALUNOS";
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
     $sql = "SELECT OPCAOCOD,OPCAO FROM OPCAOSEMANA ORDER BY OPCAO";
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
//junta aluno, inscricoes e tabelas de apoio; usado pela lista e pela contagem (as duas precisam bater)
const FROM_ALUNOS_COMPLETOS = "FROM ALUNOS A
             INNER JOIN ALUNOS_INSCRICAO I ON A.ALUMATRICULA = I.ALUNOMAT
             INNER JOIN MODALIDADES M ON I.ALUNOMOD = M.MODALIDADECOD
             INNER JOIN PLANOS P ON I.ALUNOPLANO = P.PLANOCOD
             INNER JOIN ALUNOS_STATUS S ON A.ALUSTATUS = S.STATUSCOD
             INNER JOIN OPCAOSEMANA O ON I.ALUNOOPCAO = O.OPCAOCOD";

//filtros das telas Alunos e Turmas. Lista fixa (whitelist): texto desconhecido = sem filtro.
//Numero = codigo da modalidade (tela Turmas), passado como parametro.
//Devolve [clausula WHERE, parametros]. $sufixo diferencia o parametro quando o filtro aparece duas vezes no SQL.
function filtroAlunos($filtro, string $sufixo = ""): array
{
     if (is_string($filtro) && ctype_digit($filtro)) {
          return [" WHERE I.ALUNOMOD = :modalidade$sufixo ", [":modalidade$sufixo" => (int) $filtro]];
     }
     $filtros = [
          'alunos_pendentes' => "S.STATUSALU = 'PENDENTE'",
          'alunos_pagos' => "S.STATUSALU = 'PAGO'",
          'alunos_homens' => "A.ALUSEX = 'M'",
          'alunos_mulheres' => "A.ALUSEX = 'F'",
          'plano_2' => "O.OPCAO LIKE '%2X%'",
          'plano_3' => "O.OPCAO LIKE '%3X%'",
          'plano_4' => "O.OPCAO LIKE '%4X%'",
     ];
     return is_string($filtro) && isset($filtros[$filtro]) ? [" WHERE {$filtros[$filtro]} ", []] : ["", []];
}

function listarAlunosCompletos($filtro = "", $limite = null, $offset = null)
{
     $pdo = Database::getConexao();
     [$clausulaWhere, $parametros] = filtroAlunos($filtro);
     $paginar = $limite !== null && $offset !== null;

     //com paginacao, escolhe primeiro as matriculas da pagina e so depois monta as colunas (STRING_AGG etc.)
     //dessas poucas: com 100 mil alunos o tempo nao cresce com o tamanho da tabela inteira
     $pagina = "";
     if ($paginar) {
          [$wherePagina, $parametrosPagina] = filtroAlunos($filtro, '_pagina');
          $parametros += $parametrosPagina;
          $pagina = "INNER JOIN (
                    SELECT A.ALUMATRICULA " . FROM_ALUNOS_COMPLETOS . " $wherePagina
                    GROUP BY A.ALUMATRICULA
                    ORDER BY A.ALUMATRICULA DESC
                    OFFSET :offset ROWS FETCH NEXT :limite ROWS ONLY
               ) PG ON PG.ALUMATRICULA = A.ALUMATRICULA";
     }

     $sql = "SELECT
                 A.ALUMATRICULA,
                 A.DATACADASTRO,
                 A.ALUNOME,
                 A.ALUCPF,
                 STRING_AGG(M.NOMEMOD, ', ') AS ARTES,
                 P.PLANO,
                 O.OPCAO AS FREQUENCIA,
                 S.STATUSALU AS SITUACAO,
                 A.ALUANONIMIZADO
             " . FROM_ALUNOS_COMPLETOS . "
             $pagina
             $clausulaWhere
             GROUP BY
                 A.ALUMATRICULA, A.DATACADASTRO, A.ALUNOME, A.ALUCPF, P.PLANO, O.OPCAO, S.STATUSALU, A.ALUANONIMIZADO
             ORDER BY A.ALUMATRICULA DESC";

     $stmt = $pdo->prepare($sql);
     foreach ($parametros as $nome => $valor) {
          $stmt->bindValue($nome, $valor, PDO::PARAM_INT);
     }
     if ($paginar) {
          $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
          $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
     }
     $stmt->execute();
     return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function contarAlunosCompletos($filtro = "")
{
     $pdo = Database::getConexao();
     [$clausulaWhere, $parametros] = filtroAlunos($filtro);

     $stmt = $pdo->prepare("SELECT COUNT(DISTINCT A.ALUMATRICULA) AS total " . FROM_ALUNOS_COMPLETOS . " $clausulaWhere");
     foreach ($parametros as $nome => $valor) {
          $stmt->bindValue($nome, $valor, PDO::PARAM_INT);
     }
     $stmt->execute();
     $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
     return $resultado ? (int)$resultado['total'] : 0;
}

// ---------- Paginacao das telas Alunos e Turmas ----------

//numero da pagina vindo da URL (?p=): so inteiros positivos; qualquer outra coisa vira 1
function paginaDaUrl($valor): int
{
     return is_string($valor) && ctype_digit($valor) && (int) $valor > 0 ? (int) $valor : 1;
}

//numeros de pagina para os botoes: a primeira, a ultima e $raio de cada lado da atual;
//null marca um salto ("..."). Ex.: atual 50 de 500 -> [1, null, 48, 49, 50, 51, 52, null, 500]
function paginasVisiveis(int $atual, int $total, int $raio = 2): array
{
     if ($total <= 1) {
          return [];
     }
     $paginas = array_unique(array_merge([1, $total], range(max(1, $atual - $raio), min($total, $atual + $raio))));
     sort($paginas);
     $resultado = [];
     foreach ($paginas as $i => $pagina) {
          if ($i > 0 && $pagina - $paginas[$i - 1] > 1) {
               $resultado[] = null;
          }
          $resultado[] = $pagina;
     }
     return $resultado;
}

//dados de uma pagina da lista de alunos; pagina alem da ultima cai na ultima
function dadosListagem($filtro, $paginaPedida, int $porPagina = 40): array
{
     $total = contarAlunosCompletos($filtro);
     $totalPaginas = max(1, (int) ceil($total / $porPagina));
     $pagina = min(paginaDaUrl($paginaPedida), $totalPaginas);
     return [
          'alunos' => listarAlunosCompletos($filtro, $porPagina, ($pagina - 1) * $porPagina),
          'pagina' => $pagina,
          'totalPaginas' => $totalPaginas,
          'total' => $total,
     ];
}

// ---------- Validacao dos dados do aluno (cadastro e edicao usam as mesmas regras) ----------

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

//matricula vem da URL ou do POST: so letras, numeros e hifen
//(\z e nao $: no PHP o $ tambem aceita uma quebra de linha no final)
function matriculaValida($matricula): ?string
{
     return is_string($matricula) && preg_match('/^[0-9A-Za-z-]{1,20}\z/', $matricula) ? $matricula : null;
}

//le e valida os dados do formulario de aluno; devolve [dados, lista de campos com erro]
function lerAlunoDoPost(): array
{
     return validarAluno(normalizarAlunoDoPost());
}

//numero inteiro positivo vindo de um campo do formulario (0 se nao for numero)
function inteiroDoCampo(string $nome): int
{
     return ctype_digit(campo($nome)) ? (int) campo($nome) : 0;
}

//le o formulario e padroniza: maiusculas no nome, so digitos em CPF/telefone/CEP, codigos inteiros
function normalizarAlunoDoPost(): array
{
     return [
          'nome' => mb_strtoupper(campo('nome')),
          'telefone' => soDigitos(campo('telefone')),
          'email' => mb_strtolower(campo('email')),
          'cpf' => soDigitos(campo('cpf')),
          'dataNasc' => campo('data_nascimento'),
          'genero' => substr(strtoupper(campo('genero')), 0, 1),
          'cep' => soDigitos(campo('cep')),
          'endereco' => campo('endereco'),
          'responsavelNome' => mb_strtoupper(campo('responsavel_nome')),
          'responsavelCpf' => soDigitos(campo('responsavel_cpf')),
          'modalidades' => idsInteiros($_POST['modalidades'] ?? []),
          'plano' => inteiroDoCampo('plano-pagamento'),
          'frequencia' => inteiroDoCampo('frequencia-dias'),
          'status' => inteiroDoCampo('status'),
     ];
}

//idade em anos de uma data AAAA-MM-DD existente, nao futura e de no maximo 120 anos; null se invalida
function idadeValida(string $data): ?int
{
     $nascimento = DateTime::createFromFormat('!Y-m-d', $data);
     $hoje = new DateTime('today');
     if (!$nascimento || $nascimento->format('Y-m-d') !== $data || $nascimento > $hoje) {
          return null;
     }
     $idade = $nascimento->diff($hoje)->y;
     return $idade <= 120 ? $idade : null;
}

function tamanhoEntre(string $texto, int $minimo, int $maximo): bool
{
     return mb_strlen($texto) >= $minimo && mb_strlen($texto) <= $maximo;
}

//regras do aluno (cadastro e edicao). A ordem dos erros e a ordem em que aparecem na mensagem da tela.
function validarAluno(array $aluno): array
{
     $idade = idadeValida($aluno['dataNasc']);
     $regras = [
          'nome' => tamanhoEntre($aluno['nome'], 3, 100),
          'telefone' => tamanhoEntre($aluno['telefone'], 10, 11),
          'e-mail' => filter_var($aluno['email'], FILTER_VALIDATE_EMAIL) && strlen($aluno['email']) <= 100,
          'CPF' => cpfValido($aluno['cpf']),
          'data de nascimento' => $idade !== null,
          'gênero' => in_array($aluno['genero'], ['M', 'F'], true),
          'CEP' => strlen($aluno['cep']) === 8,
          'endereço' => tamanhoEntre($aluno['endereco'], 1, 150),
          'modalidade' => !empty($aluno['modalidades']),
          'plano' => $aluno['plano'] > 0,
          'frequência' => $aluno['frequencia'] > 0,
          'status' => $aluno['status'] > 0,
     ];

     //LGPD art. 14: menor de idade precisa de responsavel identificado
     if ($idade !== null && $idade < 18) {
          $regras['nome do responsável'] = tamanhoEntre($aluno['responsavelNome'], 3, 100);
          $regras['CPF do responsável'] = cpfValido($aluno['responsavelCpf']);
     } else {
          //adulto (ou data invalida): nao guardar dados de responsavel sem necessidade (minimizacao)
          $aluno['responsavelNome'] = '';
          $aluno['responsavelCpf'] = '';
     }

     return [$aluno, array_keys(array_filter($regras, fn($valido) => !$valido))];
}

// ---------- Direitos do titular (LGPD art. 18) ----------

function buscarAluno(string $matricula): ?array
{
     $pdo = Database::getConexao();
     $stmt = $pdo->prepare("SELECT A.*, S.STATUSALU FROM ALUNOS A
          LEFT JOIN ALUNOS_STATUS S ON A.ALUSTATUS = S.STATUSCOD
          WHERE A.ALUMATRICULA = :matricula");
     $stmt->execute([':matricula' => $matricula]);
     $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
     return $aluno ?: null;
}

function buscarInscricoes(string $matricula): array
{
     $pdo = Database::getConexao();
     $stmt = $pdo->prepare("SELECT I.INSCRICAOCOD, I.ALUNOMOD, M.NOMEMOD, I.ALUNOPLANO, P.PLANO, I.ALUNOOPCAO, O.OPCAO, I.DATACADASTRO
          FROM ALUNOS_INSCRICAO I
          LEFT JOIN MODALIDADES M ON I.ALUNOMOD = M.MODALIDADECOD
          LEFT JOIN PLANOS P ON I.ALUNOPLANO = P.PLANOCOD
          LEFT JOIN OPCAOSEMANA O ON I.ALUNOOPCAO = O.OPCAOCOD
          WHERE I.ALUNOMAT = :matricula
          ORDER BY I.INSCRICAOCOD");
     $stmt->execute([':matricula' => $matricula]);
     return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//codigo da situacao INATIVO (criada por DB/migracao_direitos_titular.sql)
function codigoStatusInativo(): ?int
{
     $pdo = Database::getConexao();
     $codigo = $pdo->query("SELECT TOP 1 STATUSCOD FROM ALUNOS_STATUS WHERE STATUSALU = 'INATIVO'")->fetchColumn();
     return $codigo === false ? null : (int) $codigo;
}

//devolve 'ok', 'duplicado' (CPF ou e-mail de outro aluno) ou 'erro'
function atualizarAluno(string $matricula, array $aluno, bool $registrarCiencia): string
{
     try {
          $pdo = Database::getConexao();
          $pdo->beginTransaction();

          //a data de inativacao marca o inicio dos 5 anos de guarda (DB/anonimizar_antigos.php)
          $stmt = $pdo->prepare("UPDATE ALUNOS SET
                    ALUNOME = :nome, ALUTELEFONE = :telefone, ALUEMAIL = :email, ALUCPF = :cpf, ALUSEX = :sexo,
                    ALUDATANASC = :nascimento, ALUCEP = :cep, ALUENDERECO = :endereco, ALUSTATUS = :status,
                    ALURESPNOME = :respnome, ALURESPCPF = :respcpf,
                    ALUDATAINATIVACAO = CASE WHEN :status_novo = :inativo THEN ISNULL(ALUDATAINATIVACAO, GETDATE()) ELSE NULL END,
                    ALUCIENCIAPRIVACIDADE = CASE WHEN :ciencia = 1 THEN ISNULL(ALUCIENCIAPRIVACIDADE, GETDATE()) ELSE ALUCIENCIAPRIVACIDADE END
               WHERE ALUMATRICULA = :matricula AND ALUANONIMIZADO IS NULL");
          $stmt->execute([
               ':nome' => $aluno['nome'],
               ':telefone' => $aluno['telefone'],
               ':email' => $aluno['email'],
               ':cpf' => $aluno['cpf'],
               ':sexo' => $aluno['genero'],
               ':nascimento' => $aluno['dataNasc'],
               ':cep' => $aluno['cep'],
               ':endereco' => $aluno['endereco'],
               ':status' => $aluno['status'],
               ':respnome' => $aluno['responsavelNome'] !== '' ? $aluno['responsavelNome'] : null,
               ':respcpf' => $aluno['responsavelCpf'] !== '' ? $aluno['responsavelCpf'] : null,
               ':status_novo' => $aluno['status'],
               ':inativo' => codigoStatusInativo() ?? -1,
               ':ciencia' => $registrarCiencia ? 1 : 0,
               ':matricula' => $matricula,
          ]);
          if ($stmt->rowCount() !== 1) {
               $pdo->rollBack();
               return 'erro';
          }

          //inscricoes: remove as modalidades desmarcadas, atualiza plano/frequencia e inclui as novas
          $marcadores = [];
          $parametros = [':matricula' => $matricula];
          foreach ($aluno['modalidades'] as $i => $mod) {
               $marcadores[] = ":mod$i";
               $parametros[":mod$i"] = $mod;
          }
          $stmt = $pdo->prepare("DELETE FROM ALUNOS_INSCRICAO WHERE ALUNOMAT = :matricula AND ALUNOMOD NOT IN (" . implode(',', $marcadores) . ")");
          $stmt->execute($parametros);

          $stmt = $pdo->prepare("UPDATE ALUNOS_INSCRICAO SET ALUNOPLANO = :plano, ALUNOOPCAO = :opcao WHERE ALUNOMAT = :matricula");
          $stmt->execute([':plano' => $aluno['plano'], ':opcao' => $aluno['frequencia'], ':matricula' => $matricula]);

          $existentes = array_map('intval', array_column(buscarInscricoes($matricula), 'ALUNOMOD'));
          $stmt = $pdo->prepare("INSERT INTO ALUNOS_INSCRICAO(ALUNOMAT,ALUNOMOD,ALUNOPLANO,ALUNOOPCAO) VALUES(:matricula,:mod,:plano,:opcao)");
          foreach (array_diff($aluno['modalidades'], $existentes) as $mod) {
               $stmt->execute([':matricula' => $matricula, ':mod' => $mod, ':plano' => $aluno['plano'], ':opcao' => $aluno['frequencia']]);
          }

          $pdo->commit();
          return 'ok';
     } catch (PDOException $error) {
          if (isset($pdo) && $pdo->inTransaction()) {
               $pdo->rollBack();
          }
          //23000 vale para UNIQUE e para chave estrangeira; so 2627/2601 sao CPF ou e-mail repetido
          if (in_array($error->errorInfo[1] ?? null, [2627, 2601], true)) {
               return 'duplicado';
          }
          error_log("GKT erro ao atualizar aluno: " . $error->getMessage());
          return 'erro';
     }
}

//dados completos do aluno para o proprio titular (acesso e portabilidade, art. 18, II e V)
function dadosExportacaoAluno(string $matricula): ?array
{
     $aluno = buscarAluno($matricula);
     if (!$aluno) {
          return null;
     }
     $inscricoes = array_map(fn($i) => [
          'modalidade' => $i['NOMEMOD'],
          'plano' => $i['PLANO'],
          'frequencia' => $i['OPCAO'],
          'data_inscricao' => $i['DATACADASTRO'],
     ], buscarInscricoes($matricula));

     return [
          'controlador' => 'Academia GKT Artes Marciais',
          'gerado_em' => date('c'),
          'base_legal' => 'LGPD (Lei 13.709/2018), art. 18, II (acesso) e V (portabilidade)',
          'aluno' => [
               'matricula' => $aluno['ALUMATRICULA'],
               'nome' => $aluno['ALUNOME'],
               'cpf' => $aluno['ALUCPF'],
               'data_nascimento' => $aluno['ALUDATANASC'],
               'sexo' => $aluno['ALUSEX'],
               'telefone' => $aluno['ALUTELEFONE'],
               'email' => $aluno['ALUEMAIL'],
               'cep' => $aluno['ALUCEP'],
               'endereco' => $aluno['ALUENDERECO'],
               'situacao' => $aluno['STATUSALU'],
               'data_cadastro' => $aluno['DATACADASTRO'],
               'data_inativacao' => $aluno['ALUDATAINATIVACAO'] ?? null,
               'ciencia_politica_privacidade' => $aluno['ALUCIENCIAPRIVACIDADE'],
               'responsavel_legal' => $aluno['ALURESPNOME'] === null ? null : [
                    'nome' => $aluno['ALURESPNOME'],
                    'cpf' => $aluno['ALURESPCPF'],
               ],
          ],
          'inscricoes' => $inscricoes,
     ];
}

//elimina os dados pessoais (art. 16 e 18, IV/VI) sem apagar matricula e inscricoes, que ficam para estatistica.
//Os valores novos sao unicos (CPF e e-mail tem UNIQUE no banco) mas nao dizem nada sobre a pessoa.
function anonimizarAluno(string $matricula): bool
{
     $pdo = Database::getConexao();
     $token = bin2hex(random_bytes(8));
     $stmt = $pdo->prepare("UPDATE ALUNOS SET
               ALUNOME = 'ANONIMIZADO',
               ALUCPF = 'X' + RIGHT('0000000000' + CAST(ALUCOD AS varchar(10)), 10),
               ALUEMAIL = :email,
               ALUTELEFONE = :telefone,
               ALUENDERECO = :endereco,
               ALUCEP = NULL,
               ALURESPNOME = NULL,
               ALURESPCPF = NULL,
               ALUDATANASC = DATEFROMPARTS(YEAR(ALUDATANASC), 1, 1),
               ALUSTATUS = COALESCE(:inativo, ALUSTATUS),
               ALUDATAINATIVACAO = ISNULL(ALUDATAINATIVACAO, GETDATE()),
               ALUANONIMIZADO = GETDATE()
          WHERE ALUMATRICULA = :matricula AND ALUANONIMIZADO IS NULL");
     $stmt->execute([
          ':email' => "anon-$token@anonimizado.invalid",
          ':telefone' => "X$token",
          ':endereco' => "ANONIMIZADO-$token",
          ':inativo' => codigoStatusInativo(),
          ':matricula' => $matricula,
     ]);
     return $stmt->rowCount() === 1;
}
?>