<!--
COMO MANTER ESTE ARQUIVO (lido automaticamente pelo Claude Code em toda sessão neste projeto)

Um bom CLAUDE.md é curto, concreto e só tem o que NÃO dá para descobrir lendo o código:
  - comandos exatos para rodar, testar e validar (copiáveis, com caminho completo);
  - regras do projeto que a IA tende a quebrar ("nunca X", "sempre Y") e o porquê;
  - armadilhas conhecidas do ambiente;
  - onde ficam as coisas importantes.
Evite: explicar PHP, repetir o que está no README, listas genéricas de "boas práticas".

Exemplo de regra boa:      "Toda saída de dado do banco no HTML passa por e() — XSS (ver DOC/SEGURANCA_LGPD.md)."
Exemplo de regra ruim:     "Escreva código limpo e seguro."

Ao mudar uma regra aqui, registre o motivo em DOC/DECISOES.md.
-->

# Sistema GKT — instruções para IA e para a equipe

Sistema de gestão de alunos da academia GKT Artes Marciais. PHP 8.2 (XAMPP) + SQL Server 2022, sem framework.
Trata dados pessoais de alunos, inclusive menores de idade: **segurança e LGPD vêm antes de conveniência.**

## Comandos

```powershell
# checar sintaxe de todos os PHP (o mesmo que o CI faz)
Get-ChildItem -Recurse -Filter *.php | ? FullName -notmatch '\\vendor\\' | % { C:\xampp\php\php.exe -l $_.FullName }

# análise estática (precisa de: composer install)
composer analise

# aplicar migrações no banco local
sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_login.sql
sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_seguranca.sql
sqlcmd -S "localhost\SQLEXPRESS" -E -C -i DB\migracao_direitos_titular.sql

# retenção de 5 anos: sem parâmetro só simula; com --executar anonimiza os inativos há mais de 5 anos
C:\xampp\php\php.exe DB/anonimizar_antigos.php --executar

# criar/trocar senha de administrador (senha pedida no terminal)
C:\xampp\php\php.exe DB/criar_admin.php <login>

# backup do banco
pwsh DB/backup.ps1

# testes automatizados (detalhes em tests/README.md); o banco de teste e recriado do zero, o real nunca e tocado
pwsh tests/preparar_banco_teste.ps1
C:\xampp\php\php.exe vendor\bin\phpunit
```

Sistema local: http://localhost/Gkt/Sistema-Gerenciamento-Alunos/ (Apache do XAMPP escuta só em 127.0.0.1).

## Regras obrigatórias

### Segurança (detalhes em DOC/SEGURANCA_LGPD.md)
- Toda página ou ação começa com `require_once` de `trava.php` (exige login) ou de `BACKEND/SEGURANCA/seguranca.php` (páginas públicas). Nunca chame `session_start()` direto.
- Todo dado vindo do banco ou do usuário impresso no HTML passa por `e()`. Inclusive `value=""` de `<option>`.
- Todo formulário que altera algo: `method="POST"` + `<?= csrfCampo() ?>`; o destino chama `exigirPostComCsrf()` antes de qualquer outra coisa.
- SQL só com `prepare()` e parâmetros nomeados. Nunca concatene entrada do usuário em SQL; filtros dinâmicos usam lista fixa (whitelist), como em `listarAlunosCompletos()`.
- Sem JavaScript inline (`onclick=`, `<script>` sem `src`): a CSP bloqueia. Use arquivo em `ASSETS/JS/` com `addEventListener`.
- Validação sempre no servidor (o `required` do HTML é só conforto). Use as funções de `funcoes.php`: `campo()`, `soDigitos()`, `cpfValido()`, `idsInteiros()`, `matriculaValida()` e `lerAlunoDoPost()` (regras do aluno, compartilhadas por cadastro e edição).
- Senhas: `gerarHashSenha()` / `password_verify()`; política em `problemaNaSenha()`. Nunca guarde ou registre senha em texto.
- Erros: nunca `echo` de exceção para o usuário. Use `error_log()` e mensagem genérica.
- Arquivo novo que não deve ser acessado pelo navegador? Coloque em `DB/`, `DOC/`, `BACKEND/CONNECTION/`, `BACKEND/SEGURANCA/` ou `ASSETS/PAGINAS/` (bloqueados no `.htaccess`) ou acrescente uma regra lá. Scripts de manutenção começam com `if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }`.

### LGPD
- Não colete dado novo sem finalidade clara; se colher, atualize `privacidade.php` e a seção 4 de `DOC/SEGURANCA_LGPD.md`.
- CPF aparece mascarado em listagens (`mascararCpf()`); completo só em tela de detalhe/edição.
- Ações sobre dados pessoais (cadastro, edição, exclusão, exportação) chamam `registrarAuditoria()` com a **matrícula**, nunca com nome/CPF/e-mail.
- Dados reais de alunos nunca vão para o Git (scripts `.sql`, testes, prints, issues). Use dados fictícios.

### Banco
- Mudança de estrutura = novo arquivo `DB/migracao_<assunto>.sql`, idempotente (`IF COL_LENGTH(...) IS NULL`, `IF OBJECT_ID(...) IS NULL`), e registro no CHANGELOG.
- O usuário do app (`gkt_app`) só tem leitura/escrita. Se precisar de permissão nova, peça explicitamente e explique por quê.
- Matrícula de aluno é `varchar`; status, plano, modalidade e frequência são `int` com chave estrangeira.

### Git e documentação
- Commits em português, no imperativo ou gerúndio, com título curto e corpo listando o que mudou e por quê.
- Toda mudança visível para o usuário ou na estrutura do banco entra em `CHANGELOG.md`, seção `[Não publicado]`.
- Decisão de arquitetura ou de segurança nova → entrada em `DOC/DECISOES.md`.
- Nunca commite `.env`, `vendor/`, backups (`*.bak`) ou logs.
- Antes de considerar uma tarefa pronta: sintaxe OK, `composer analise` sem erros novos, **todos os testes passando** e o fluxo testado no navegador.
- Bug corrigido ou regra nova → teste novo que falharia sem a mudança (ver tests/README.md).

## Armadilhas conhecidas
- O `php` do PATH é o 8.5 do usuário (sem driver SQL Server). Para o sistema use sempre `C:\xampp\php\php.exe` (8.2).
- `composer install` falha com "zip extension missing" nos dois PHPs. Use:
  `C:\xampp\php\php.exe -d extension=zip C:\ProgramData\ComposerSetup\bin\composer.phar install`
- `vendor/` fica dentro do site: o `.htaccess` bloqueia; não remova essa regra.
- Windows não diferencia maiúsculas: regras do `.htaccess` usam `(?i)`.
- Cookie de sessão é `SameSite=Lax` de propósito: `Strict` desloga quem abre o sistema por um link (ver DOC/DECISOES.md).
- `DB/update_db.php` é antigo e conflita com o esquema atual (`ALUSTATUS` é `int`). Não rode.
- Revisão de PR: use a skill `/revisar-pr`.
