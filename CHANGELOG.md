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
- Testes automatizados com PHPUnit (216 testes): unidade, integração com banco de teste, funcionais e configuração do Apache.
  O CI roda os de unidade. Como rodar: `tests/README.md`.
- Ambiente Docker (`docker/`): PHP 8.2 + Apache + SQL Server 2022 com `docker compose`, testes dentro do container
  e varredura de segurança com OWASP ZAP. Passo a passo para Debian em `docker/README.md`. O CI roda todos os testes
  nos containers.
- `config.php` aceita a configuração do banco por variáveis de ambiente quando não há `.env`.
- Varredura OWASP ZAP logada como administrador (`docker/varredura-autenticada.sh`).
- `robots.txt` pedindo aos buscadores para não indexar o sistema.
- Botões **Editar**, **Exportar dados** e **Excluir** nas listas de Alunos e Turmas (direitos do titular, LGPD art. 18).
  Exportar baixa um JSON com todos os dados e inscrições do aluno; Excluir anonimiza os dados pessoais e mantém
  matrícula e inscrições para estatística.
- Situação `INATIVO` para alunos que encerraram a matrícula.
- `DB/anonimizar_antigos.php`: anonimiza alunos inativos há mais de 5 anos (rodar pelo terminal com `--executar`).

### Corrigido
- Aluno cadastrado já como INATIVO ficava sem data de inativação e nunca entrava na retenção de 5 anos.
- Erro de modalidade inexistente na edição aparecia como "CPF ou e-mail já pertence a outro aluno".
- Matrícula com quebra de linha no final passava pela validação.

### Segurança
- Achados do OWASP ZAP corrigidos: CSP também nas páginas de erro do Apache; cabeçalhos
  `Cross-Origin-Resource-Policy`, `Cross-Origin-Embedder-Policy` e `Permissions-Policy` em todas as respostas.
- Fontes da tela de login hospedadas no próprio sistema: sem Google Fonts, a página não carrega nada de outros
  sites e o IP do visitante deixa de ser enviado ao Google (LGPD). A CSP ficou mais restrita.
- No container, a versão do Apache não aparece mais nos cabeçalhos nem nas páginas de erro.

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
