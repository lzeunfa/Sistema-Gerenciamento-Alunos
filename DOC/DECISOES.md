<!--
COMO MANTER ESTE ARQUIVO (registro de decisões de arquitetura, no estilo ADR)

Registre aqui toda decisão que alguém poderia questionar depois ("por que não usamos X?").
Cada decisão é curta e tem sempre os mesmos 4 itens. Nunca apague uma decisão antiga: se ela mudar,
marque como "Substituída por D-00X" e crie uma nova.

Modelo (copie para o fim do arquivo):

## D-000 — Título curto da decisão
**Data:** AAAA-MM-DD · **Situação:** Aceita | Substituída por D-00X
**Contexto:** qual problema ou restrição levou à decisão.
**Decisão:** o que foi escolhido, em uma ou duas frases.
**Consequências:** o que fica melhor, o que fica pior e o que passa a ser obrigatório.
-->

# Decisões de arquitetura

## D-001 — SQL Server em vez de MySQL
**Data:** 2026-08-16 · **Situação:** Aceita
**Contexto:** as primeiras versões usavam MySQL (`LIMIT`, tabela `users`); o banco da academia foi migrado para SQL Server.
**Decisão:** SQL Server 2022 via PDO com o driver `sqlsrv`.
**Consequências:** sintaxe T-SQL (`TOP`, `OFFSET ... FETCH`, `STRING_AGG`). O PHP precisa ser 8.2 (XAMPP 8.2), pois os
drivers da Microsoft ainda não suportam versões mais novas.

## D-002 — Configuração do banco em `.env`
**Data:** 2026-08-16 · **Situação:** Aceita
**Contexto:** credenciais no código vazariam no repositório público.
**Decisão:** `config.php` lê `.env` (formato INI) da raiz; o modelo versionado é `.env.example`.
**Consequências:** o `.env` fica na pasta do site, então o `.htaccess` precisa bloqueá-lo (ver D-003). Em produção, o
ideal é mover o `.env` para fora do `htdocs`.

## D-003 — Bloqueio de arquivos internos pelo `.htaccess`
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** o projeto inteiro fica dentro do `htdocs`, e `.env`, `.git` e scripts de `DB/` eram baixáveis pelo navegador.
**Decisão:** `.htaccess` responde 404 para arquivos ocultos e pastas internas, com `(?i)` porque o Windows não
diferencia maiúsculas.
**Consequências:** arquivo novo que não deve ser público precisa ficar numa pasta bloqueada ou ganhar regra própria.
Depende de `AllowOverride All` no Apache.

## D-004 — Núcleo único de segurança (`BACKEND/SEGURANCA/seguranca.php`)
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** cada página iniciava a sessão do seu jeito e nenhuma tinha CSRF, escape de saída ou cabeçalhos.
**Decisão:** um arquivo incluído por todas as páginas configura sessão, cabeçalhos, CSRF, escape (`e()`) e auditoria.
**Consequências:** nenhuma página chama `session_start()` direto; mudar uma regra de segurança é mudar um só lugar.

## D-005 — Argon2id para senhas, com migração automática
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** a OWASP recomenda Argon2id; já existiam senhas em bcrypt.
**Decisão:** `password_hash` com `PASSWORD_ARGON2ID`; no login, `password_needs_rehash` converte senhas antigas.
**Consequências:** cada login leva ~0,4 s de propósito (dificulta força bruta). Usuário inexistente é conferido
contra um hash falso para o tempo de resposta não revelar quem existe.

## D-006 — Cookie de sessão `SameSite=Lax`, não `Strict`
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** com `Strict`, quem abria o sistema por um link (WhatsApp, e-mail) chegava deslogado.
**Decisão:** `Lax`, como recomendam OWASP e Mozilla; a proteção contra CSRF vem dos tokens nos formulários.
**Consequências:** todo formulário que altera dados precisa de `csrfCampo()` + `exigirPostComCsrf()`.

## D-007 — CSP sem JavaScript inline
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** a Content-Security-Policy é a segunda barreira contra XSS, mas só funciona sem `'unsafe-inline'`.
**Decisão:** scripts só de arquivos do próprio site ou do jsDelivr (Bootstrap com SRI).
**Consequências:** proibido `onclick=` e `<script>` sem `src`; mensagens de sucesso/erro vão pela sessão
(`definirMensagem()`), não por `alert()`.

## D-008 — Usuário do banco com privilégio mínimo
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** o sistema conectava como `db_owner`; uma injeção de SQL poderia apagar tabelas.
**Decisão:** `gkt_app` só tem `db_datareader`/`db_datawriter`, com `DENY ALTER` e sem `UPDATE`/`DELETE` em `AUDITORIA`.
**Consequências:** migrações rodam com outro usuário (autenticação do Windows). Ao criar tabela nova, as permissões
vêm automaticamente das roles.

## D-009 — "Excluir" aluno anonimiza em vez de apagar
**Data:** 2026-10-14 · **Situação:** Aceita
**Contexto:** a LGPD (art. 16, IV e 18, VI) pede a eliminação dos dados pessoais, mas a academia quer manter
estatísticas de matrículas e modalidades, e `ALUNOS_INSCRICAO` depende da matrícula.
**Decisão:** o botão Excluir e a rotina `DB/anonimizar_antigos.php` sobrescrevem os dados pessoais (`anonimizarAluno()`):
nome `ANONIMIZADO`, CPF `X` + código interno, e-mail/telefone/endereço com um código aleatório, CEP e responsável
apagados, nascimento reduzido a 1º de janeiro do mesmo ano. Matrícula, sexo e inscrições ficam. A data entra em
`ALUANONIMIZADO`; a contagem dos 5 anos de guarda usa `ALUDATAINATIVACAO`, gravada quando a situação vira `INATIVO`.
**Consequências:** não dá para desfazer (exporte antes, se o aluno pedir cópia). Aluno anonimizado não pode ser
editado nem exportado. A `AUDITORIA` guarda só a matrícula, então não reidentifica ninguém. Os valores novos são
únicos porque CPF e e-mail têm `UNIQUE` no banco.

## D-010 — Docker para rodar igual em qualquer máquina
**Data:** 2026-10-22 · **Situação:** Aceita
**Contexto:** o ambiente dependia de XAMPP 8.2, drivers do SQL Server e configurações feitas à mão no Windows
("na minha máquina funciona"); o WSL não funciona na máquina de desenvolvimento.
**Decisão:** `docker/` com a imagem `php:8.2-apache-bookworm` + drivers da Microsoft e o SQL Server 2022 oficial,
orquestrados pelo `docker compose`. Sem `.env`, o `config.php` lê as mesmas chaves das variáveis de ambiente.
**Consequências:** senhas ficam em `docker/.env` (fora do Git e da imagem). O banco não é exposto na rede. Scripts
`.sh` precisam de quebra de linha LF (`.gitattributes`). O CI sobe os containers e roda todos os testes no Linux.
O XAMPP continua funcionando como antes.

## D-011 — HTTPS no container, com certificado por instalação
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** o OWASP ZAP apontou "HTTP Only Site". Sem HTTPS, senha e cookie de sessão trafegam abertos na rede
da academia.
**Decisão:** o Apache do container serve só HTTPS (TLS 1.2/1.3, cifras do perfil "intermediate" da Mozilla); a
porta 80 redireciona. Na primeira subida, `docker/entrypoint.sh` gera um certificado autoassinado **só daquela
instalação** (volume `certificados`), que pode ser trocado por um certificado real. HSTS em todas as respostas.
**Consequências:** o navegador avisa sobre o certificado autoassinado até a academia instalar um real (ou aceitar
o aviso uma vez por computador). Com HTTPS o cookie de sessão fica `Secure` sozinho (`conexaoSegura()`).

## D-012 — Fontes hospedadas no próprio sistema
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** a tela de login carregava fontes do Google Fonts: sem verificação de integridade (SRI) possível e
com o IP de cada visitante enviado ao Google.
**Decisão:** Rajdhani e Saira Condensed (licença OFL) em `ASSETS/FONTES`, só os alfabetos latinos.
**Consequências:** a CSP não libera mais nenhum domínio do Google; a tela de login não carrega nada de outro site.
Fonte nova? Coloque o arquivo em `ASSETS/FONTES` e declare em `fontes.css`.

## D-013 — Só os métodos HTTP GET, POST e HEAD
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** o ZAP identificava o servidor usando OPTIONS/TRACE/TRACK; o sistema não usa nenhum outro método.
**Decisão:** `<LimitExcept GET POST HEAD>` no `.htaccess` recusa os demais (403/405).
**Consequências:** uma API futura que precise de PUT/DELETE terá de liberar o método explicitamente.

## D-014 — Toda mudança passa por testes no container, varredura ZAP e teste de carga
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** o container é a simulação mais próxima da produção; vários problemas (versão do Apache exposta,
cabeçalhos ausentes, entradas em lista derrubando páginas) só apareceram assim.
**Decisão:** antes de cada entrega: testes automatizados no container, varredura ZAP com e sem login e teste de
carga (k6), conforme `docker/README.md`. O CI do GitHub roda os testes no container a cada push.
**Consequências:** uma entrega leva cerca de 40 minutos de verificação automática; achado novo vira teste.

## D-015 — Backup do container pelo sqlcmd do próprio SQL Server, restauração por script
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** no Docker não havia backup automatizado nem caminho testado de volta; o usuário `gkt_app` não pode
fazer backup (privilégio mínimo, D-008) e a imagem do sistema não tem `sqlcmd`.
**Decisão:** `docker/backup.sh` roda `BACKUP ... WITH CHECKSUM` e `RESTORE VERIFYONLY` com o `sqlcmd` que já vem na
imagem oficial do SQL Server, como `sa`; a senha vai por variável de ambiente (`SQLCMDPASSWORD`), nunca pela
linha de comando. O arquivo sai do container para `~/backups-gkt` (fora do projeto, permissão 600), com 30 dias de
retenção. `docker/restaurar.sh` exige `--confirmo-substituir`, faz backup do estado atual antes e re-roda o
`banco-init`, que aplica migrações e religa o `gkt_app` (`ALTER USER ... WITH LOGIN`) a um banco vindo de outro
servidor, inclusive do XAMPP.
**Consequências:** um backup do Windows (`DB/backup.ps1`) pode ser levado para o Docker. Guardar a cópia fora do
computador continua sendo tarefa da academia.

## D-016 — Sistema do container só em 127.0.0.1 por padrão
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** portas publicadas pelo Docker passam por cima do firewall do Debian (`ufw`); publicar em `0.0.0.0`
deixaria o sistema aberto em qualquer interface (VPN, Wi-Fi de visitantes).
**Decisão:** `ENDERECO_APP` em `docker/.env`, padrão `127.0.0.1`. Para a recepção, o IP do servidor na rede local
(`docker/README.md`, seção 8).
**Consequências:** quem sobe o sistema pela primeira vez só acessa pelo próprio computador até decidir abrir.

## D-017 — Varreduras, carga e volume atacam uma cópia ligada ao banco de teste
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** a revisão de segurança da branch apontou que a varredura ZAP logada e o teste de carga cadastravam e
alteravam alunos no banco `gktgym` do container. No servidor da academia esse banco tem os dados reais; mesmo a
varredura sem login enche a auditoria (que o sistema não pode apagar) de tentativas e pode bloquear administradores.
**Decisão:** `docker/copia-teste.sh` recria o `gktgym_teste` e sobe `gkt-app-teste`, uma cópia do `app` ligada a
ele, sem porta aberta; `varredura.sh`, `varredura-autenticada.sh`, `teste-carga.sh` e `teste-volume.sh` atacam só a
cópia e a apagam no fim. O serviço `zap` do compose já aponta para ela.
**Consequências:** D-014 (ZAP e carga a cada entrega) pode ser cumprida no próprio servidor de produção. Cada rodada
recria o banco de teste do zero.

## D-018 — Próxima matrícula gerada com trava de aplicação do SQL Server
**Data:** 2026-10-23 · **Situação:** Aceita
**Contexto:** o teste de carga mostrou que dois cadastros simultâneos liam o mesmo `MAX(ALUMATRICULA)` e o segundo
falhava com chave duplicada. A matrícula é `varchar` com números sequenciais e já existe na base real, então trocar
por `IDENTITY` ou `SEQUENCE` exigiria migrar a chave e as tabelas ligadas a ela.
**Decisão:** `gerarProximaMatricula()` pega `sp_getapplock` exclusivo (dono: a transação do cadastro, espera até 10 s)
antes de ler o `MAX`. Só os cadastros entram em fila; listagens e edições não esperam. Não precisa de permissão nova
para o `gkt_app`. O `MAX` usa `TRY_CAST`, para matrículas com letras não travarem os cadastros.
**Consequências:** `gerarProximaMatricula()` só pode ser chamada dentro de uma transação. Com milhares de cadastros
por segundo a fila viraria gargalo, longe da realidade de uma academia.
