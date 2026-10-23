<!--
COMO MANTER ESTE ARQUIVO

O README responde, nesta ordem: QUE PROBLEMA isso resolve, PARA QUEM, COMO RODAR e ONDE ESTÁ o resto.
- Comece pelo problema e pelo uso, não pela tecnologia.
- Passos de instalação devem funcionar copiando e colando, numa máquina limpa. Se mudou algo
  na instalação (driver, migração, variável do .env), atualize aqui no mesmo commit.
- Detalhes longos vão para DOC/ e são só linkados aqui.
- Nunca coloque senha, IP real ou dado de aluno.

Exemplo de abertura boa:  "Controla matrículas, turmas e pagamentos dos alunos da GKT, substituindo a planilha."
Exemplo de abertura ruim: "Projeto em PHP com SQL Server e Bootstrap."
-->

# Sistema de Gerenciamento de Alunos — GKT Artes Marciais

Controla as matrículas, turmas e a situação de pagamento dos alunos da academia GKT. A equipe da recepção
cadastra alunos, vê quem está com mensalidade pendente e filtra por modalidade, plano ou frequência, sem
depender de planilhas.

Como o sistema guarda dados pessoais (inclusive de menores de idade), ele foi feito para atender à **LGPD**:
acesso só com login, registro de auditoria, Política de Privacidade e proteção contra os ataques mais comuns.

## O que o sistema faz

- **Login de administradores** com bloqueio após tentativas erradas e sessão que expira sozinha.
- **Dashboard** com total de alunos, turmas e alunos com pendência.
- **Alunos**: lista com filtros (pagos, pendentes, plano 2x/3x/4x, sexo) e paginação.
- **Turmas**: alunos por modalidade.
- **Cadastro** de alunos com validação completa e dados do responsável para menores.

## Como rodar localmente (Windows)

Pré-requisitos:

| Programa | Versão | Observação |
|---|---|---|
| XAMPP | 8.2.x | PHP 8.2 (versões mais novas ainda não têm driver do SQL Server) |
| SQL Server | 2022 Express ou Developer | com TCP habilitado na porta 1433 |
| Microsoft ODBC Driver for SQL Server | 17 ou 18 | |
| Drivers PHP para SQL Server | 5.12 | `php_sqlsrv_82_ts_x64.dll` e `php_pdo_sqlsrv_82_ts_x64.dll` |

1. Clone o projeto dentro do `htdocs` do XAMPP:
   ```bash
   git clone https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos C:\xampp\htdocs\Gkt\Sistema-Gerenciamento-Alunos
   ```
2. Copie os drivers PHP para `C:\xampp\php\ext\` e adicione ao `C:\xampp\php\php.ini`:
   ```ini
   extension=sqlsrv_82_ts_x64
   extension=pdo_sqlsrv_82_ts_x64
   ```
3. Crie o banco `gktgym`, a estrutura e os dados básicos, e rode as migrações, nesta ordem:
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -Q "CREATE DATABASE gktgym"
   ```
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\estrutura.sql
   ```
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\dados_iniciais.sql
   ```
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_login.sql
   ```
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_seguranca.sql
   ```
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_direitos_titular.sql
   ```
   ```bash
   sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_desempenho.sql
   ```
   Depois crie o usuário do sistema no banco e rode os comandos de **PERMISSÕES** do final de `DB/migracao_seguranca.sql`.
4. Copie `.env.example` para `.env` e preencha usuário e senha do banco.
5. Crie seu administrador (a senha é pedida no terminal, mínimo de 12 caracteres):
   ```bash
   C:\xampp\php\php.exe DB/criar_admin.php seu_login
   ```
6. Inicie o Apache pelo XAMPP Control Panel e acesse http://localhost/Gkt/Sistema-Gerenciamento-Alunos/

## Estrutura

```
index.php, sistema.php     login e "casca" das páginas internas
trava.php                  exige login (incluído no topo das páginas internas)
privacidade.php            Política de Privacidade (pública)
ASSETS/PAGINAS/            páginas internas (DASHBOARD, ALUNOS, CADASTRAR, TURMAS)
BACKEND/CONTROLLER/        ações: login, cadastro e consultas ao banco
BACKEND/SEGURANCA/         núcleo de segurança (sessão, CSRF, XSS, auditoria)
DB/                        estrutura, migrações, backup e scripts de terminal
DOC/                       documentação: segurança/LGPD e decisões de arquitetura
```

## Documentação

- [CLAUDE.md](CLAUDE.md) — regras do projeto (para a equipe e para assistentes de IA).
- [DOC/SEGURANCA_LGPD.md](DOC/SEGURANCA_LGPD.md) — proteções, situação da LGPD e o que fazer em incidente.
- [DOC/DECISOES.md](DOC/DECISOES.md) — por que o sistema é do jeito que é.
- [CHANGELOG.md](CHANGELOG.md) — o que mudou em cada versão e quais migrações rodar.

## Como contribuir

1. Crie uma branch a partir da `main` (`feats/seu-nome-assunto`).
2. Siga as regras do [CLAUDE.md](CLAUDE.md) e registre a mudança no [CHANGELOG.md](CHANGELOG.md).
3. Abra o Pull Request preenchendo o modelo. O CI precisa passar antes do merge.

Encontrou uma falha de segurança? **Não abra issue pública**: avise os mantenedores diretamente.
