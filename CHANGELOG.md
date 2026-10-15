<!--
COMO MANTER ESTE ARQUIVO (formato Keep a Changelog: https://keepachangelog.com/pt-BR/1.1.0/)

- Escreva para quem USA ou INSTALA o sistema, não para quem lê o código.
- Toda mudança nova entra em "## [Não publicado]", no grupo certo:
    Adicionado   → funcionalidade nova
    Alterado     → mudança em algo que já existia
    Corrigido    → bug resolvido
    Removido     → algo que deixou de existir
    Segurança    → correção de vulnerabilidade
    Banco        → migração que precisa ser rodada (diga QUAL arquivo)
- Ao publicar uma versão: troque "[Não publicado]" por "[1.2.0] - AAAA-MM-DD", crie um novo
  "[Não publicado]" vazio em cima e crie a tag no Git: git tag v1.2.0 && git push origin v1.2.0
  (o GitHub Actions cria a Release usando a seção desta versão).
- Versão (SemVer): 1.2.3 → 1 = quebra compatibilidade/exige ação manual, 2 = funcionalidade nova, 3 = correção.

Exemplo de entrada boa:
  ### Corrigido
  - Cadastro aceitava CPF com pontuação, que não cabia no banco e gerava erro genérico.
Exemplo de entrada ruim:
  - ajustes no gravarAluno.php
-->

# Changelog

Todas as mudanças relevantes deste projeto são registradas aqui.

## [Não publicado]

### Adicionado
- `CLAUDE.md` com as regras do projeto para IA e equipe, `.env.example`, `DOC/DECISOES.md`, modelo de PR e skill `/revisar-pr`.
- CI no GitHub Actions: checagem de sintaxe PHP, análise estática (PHPStan), busca de senhas vazadas e auditoria de dependências.
- Botões **Editar**, **Exportar dados** e **Excluir** nas listas de Alunos e Turmas (direitos do titular, LGPD art. 18).
  Exportar baixa um JSON com todos os dados e inscrições do aluno; Excluir anonimiza os dados pessoais e mantém
  matrícula e inscrições para estatística.
- Situação `INATIVO` para alunos que encerraram a matrícula.
- `DB/anonimizar_antigos.php`: anonimiza alunos inativos há mais de 5 anos (rodar pelo terminal com `--executar`).

### Alterado
- Frequências de treino de 1x a 5x por semana, listadas em ordem no cadastro; modalidades e planos da academia nos dados iniciais.
- Filtro "Pendentes" mostra só a situação `PENDENTE` (antes incluía qualquer situação diferente de `PAGO`).

### Banco
- Rodar de novo `DB/dados_iniciais.sql` (não duplica dados).
- Rodar `DB/migracao_direitos_titular.sql` (situação `INATIVO` e colunas `ALUDATAINATIVACAO` e `ALUANONIMIZADO`).

## [1.1.0] - 2026-10-14

### Segurança
- Arquivos internos (`.env`, `.git`, `DB/`, `DOC/`, páginas internas) deixaram de ser acessíveis pelo navegador.
- Proteção contra XSS, CSRF e força bruta no login (bloqueio de 15 minutos após 5 erros).
- Senhas com Argon2id; senhas antigas são convertidas no próximo login.
- Sessão com cookie `HttpOnly`/`SameSite`, expiração por inatividade (10 min) e limite de 8 horas.
- Cabeçalhos de segurança (CSP, X-Frame-Options, nosniff, Referrer-Policy).
- Usuário do banco do sistema passou a ter só permissão de leitura e escrita.

### Adicionado
- Política de Privacidade (`privacidade.php`) e confirmação de ciência no cadastro (LGPD art. 9).
- Campos de responsável legal, obrigatórios para alunos menores de 18 anos (LGPD art. 14).
- Registro de auditoria de logins, cadastros e tentativas inválidas.
- Script de backup com verificação (`DB/backup.ps1`).
- Relatório `DOC/SEGURANCA_LGPD.md`.

### Alterado
- CPF aparece mascarado nas listagens de alunos e turmas.
- Cadastro valida todos os campos no servidor (CPF com dígito verificador, e-mail, datas, tamanhos).
- Botão Sair usa formulário com token em vez de link.

### Removido
- `test.php` (arquivo de depuração com caminho fixo).

### Banco
- Rodar `DB/migracao_seguranca.sql` e os comandos de permissão no final dele.

## [1.0.0] - 2026-10-14

### Corrigido
- Login e bloqueio de páginas restaurados (haviam sido apagados) e adaptados ao SQL Server, com senha criptografada.
- Botão de mostrar/ocultar senha do login.

### Adicionado
- Script `DB/criar_admin.php` para criar administradores.

### Banco
- Rodar `DB/migracao_login.sql` (amplia `ADMSENHA` para 255 caracteres e remove a restrição de senha única).

[Não publicado]: https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos/releases/tag/v1.0.0
