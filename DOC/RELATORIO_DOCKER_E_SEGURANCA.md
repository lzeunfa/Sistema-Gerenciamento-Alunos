<!--
COMO MANTER ESTE ARQUIVO
Relatório de uma execução (22 e 23/10/2026). Não edite resultados antigos: uma nova rodada de testes ganha uma seção
nova com a data. Comandos aqui devem ser copiáveis; segredos (senhas, chaves) nunca aparecem.
-->

# Relatório: sistema em Docker no Debian e testes de segurança

**Ambiente:** Debian 13.7 (trixie), x86_64, 16 GB RAM, 12 núcleos, Wi-Fi, IP 192.168.1.23 (usuário `felipedoc`).
Operado a partir do Windows por SSH com chave. Branch: `feats/felipe-docker` (PR #22).

## 1. Resultado em uma frase

O sistema roda no Debian em containers, passa em **226 testes automatizados** dentro do container e passou por
**duas varreduras do OWASP ZAP sem nenhuma falha** (110 e depois 139 verificações aprovadas). Todos os avisos
que dependiam do código foram corrigidos; sobra só o HTTPS, que depende do servidor de produção.

## 2. Passo a passo, com cada comando e o que ele faz

### 2.1 No Windows: chave SSH para entrar no Debian sem senha

| Comando | O que faz |
|---|---|
| `ssh-keygen -t ed25519 -f %USERPROFILE%\.ssh\id_ed25519` | Cria um par de chaves (pública e privada) no Windows. A privada nunca sai do Windows. |
| `type id_ed25519.pub \| ssh felipedoc@192.168.1.23 "cat >> ~/.ssh/authorized_keys"` | Copia a chave **pública** para o Debian. Pediu a senha do Debian uma única vez. |
| `ssh -o BatchMode=yes felipedoc@192.168.1.23 "echo ok"` | Testa se o acesso por chave funciona (BatchMode = falha em vez de pedir senha). |

### 2.2 No Debian: servidor SSH e Docker (feito uma vez, com a sua senha no `sudo`)

| Comando | O que faz |
|---|---|
| `sudo apt-get install -y openssh-server` e `sudo systemctl enable --now ssh` | Instala e liga o servidor SSH. |
| `sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc` | Baixa a chave que assina os pacotes oficiais do Docker. |
| `echo "deb [...] https://download.docker.com/linux/debian trixie stable" \| sudo tee /etc/apt/sources.list.d/docker.list` | Adiciona o repositório oficial do Docker ao `apt`. |
| `sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin` | Instala o Docker e o Docker Compose. |
| `sudo usermod -aG docker felipedoc` | Permite usar o Docker sem `sudo` (efeito na próxima sessão). |
| `sudo docker run --rm hello-world` | Confirma que o Docker funciona. |

### 2.3 No Debian: projeto, senhas, imagens e containers

| Comando | O que faz |
|---|---|
| `git clone https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos.git ~/projetos/...` | Baixa o projeto. |
| `git checkout feats/felipe-docker` | Usa a branch com os arquivos do Docker (até o PR #22 ser mergeado). |
| `umask 077; echo "SA_PASSWORD=Sa-$(openssl rand -hex 16)-X1" > docker/.env` (e `DB_PASSWORD`) | Gera senhas aleatórias **no próprio Debian**, num arquivo que só o seu usuário lê (`-rw-------`). Elas não passaram pela conversa nem pelo Git. |
| `bash docker/construir.sh` | Constrói as duas imagens (`gkt-app:producao` e `gkt-app:testes`) usando a rede do Debian (motivo na seção 3). |
| `docker compose -f docker/docker-compose.yml up -d --wait app` | Sobe o SQL Server, roda a preparação do banco (`banco-init`) e sobe o sistema; `--wait` espera o sistema ficar saudável. |
| `docker compose -f docker/docker-compose.yml logs banco-init` | Mostra a criação do banco: estrutura, dados iniciais, migrações e usuário `gkt_app`. |
| `curl http://127.0.0.1:8080/index.php` | Confere que o sistema responde (200) e que `/.env` está bloqueado (404). |
| `docker compose -f docker/docker-compose.yml --profile testes run --rm testes` | Roda os 226 testes num banco separado (`gktgym_teste`), recriado do zero. |
| `docker compose -f docker/docker-compose.yml --profile seguranca run --rm zap` | Varredura OWASP ZAP **sem login** (como um atacante de fora). Relatório em `relatorios/`. |
| `bash docker/varredura-autenticada.sh` | Varredura OWASP ZAP **logada como administrador** temporário (criado com senha aleatória e apagado no fim). |

Para usar no dia a dia, o resumo está em `docker/README.md`.

## 3. Problemas encontrados no caminho e como foram resolvidos

| Problema | Causa | Solução (já no código) |
|---|---|---|
| `pecl install sqlsrv` dizia "No releases available" | A consulta de versões do pecl falhava nessa rede; o download direto funcionava | O Dockerfile baixa o pacote pelo link direto e instala do arquivo |
| Downloads da Microsoft falhando (`No route to host`) | O provedor de internet não tem rota IPv4 para os servidores da Microsoft; por IPv6 funciona, mas a rede dos containers só tem IPv4 | `docker/construir.sh` constrói com a rede do Debian (`--network host`), que tem IPv6 |
| Pacote `mssql-tools18` (sqlcmd) inacessível | Servidor da Microsoft só com IPv4 | Removido da imagem: o banco é preparado por `docker/iniciar-banco.php`, usando o driver do PHP. Imagem menor, menos coisas instaladas |
| `composer install` falhando de vez em quando | Wi-Fi instável com o GitHub | Até 5 tentativas automáticas |
| Imagem do ZAP no `ghcr.io` inacessível | Mesmo problema de IPv4 | Uso da imagem oficial no Docker Hub (`zaproxy/zap-stable`) |
| Apache revelava `Apache/2.4.68 (Debian)` | O `security.conf` do Debian sobrescrevia a configuração | Arquivo renomeado para `zz-gkt.conf` (carrega por último). Achado pelos testes automatizados |

## 4. Testes de segurança

### 4.1 Testes automatizados (226, todos passando no Debian e no XAMPP)
Simulam, entre outros: força bruta no login (bloqueio após 5 erros), roubo/fixação de sessão, CSRF em todos os
formulários, XSS no cadastro, injeção de SQL nos filtros e na matrícula, acesso sem login a todas as páginas e
ações, download de arquivos internos (`.env`, `.git`, scripts do banco), expiração de sessão e permissões mínimas
do banco (o sistema não consegue apagar a auditoria nem alterar tabelas).

### 4.2 OWASP ZAP — 1ª varredura completa (sem login)
**0 falhas, 110 verificações aprovadas, 3 avisos.** Testou injeção de SQL (SQL Server, Oracle, PostgreSQL), XSS,
injeção de comandos, path traversal, inclusão de arquivos, XXE, SSTI, vazamento de `.env`/`.htaccess`, arquivos
ocultos, Log4Shell, Spring4Shell e outros.

| Aviso | Correção |
|---|---|
| CSP ausente nas páginas de erro (`robots.txt`, `sitemap.xml`) | `.htaccess` manda CSP restritiva em toda resposta de erro; `robots.txt` criado |
| SRI ausente na fonte do Google Fonts | Fontes hospedadas no sistema (`ASSETS/FONTES`); a tela de login não carrega nada de fora e o IP do visitante não vai mais ao Google (LGPD) |
| Site só em HTTP | Recomendação (seção 5) |

### 4.3 OWASP ZAP — 2ª varredura completa (sem login, depois das correções)
**0 falhas, 139 verificações aprovadas** (o ZAP rodou mais regras), 4 avisos:

| Aviso | Resultado |
|---|---|
| Cross-Origin-Resource-Policy ausente | Corrigido: `same-origin` em todas as respostas |
| Cross-Origin-Embedder-Policy ausente | Corrigido: `require-corp` nas páginas (Bootstrap continua carregando) |
| Permissions-Policy ausente na página 404 | Corrigido |
| "Bypassing 403" com cabeçalho `X-Original-URL` | **Falso positivo**: o truque funciona em IIS/Nginx; o Apache ignora o cabeçalho e devolve só a tela de login. Confirmado manualmente e transformado em teste |
| Site só em HTTP | Recomendação (seção 5) |

Durante essa varredura o ZAP tentou login repetidamente e **foi bloqueado pela proteção contra força bruta**
(registros `login_bloqueado` na auditoria) — evidência de que a proteção funciona contra uma ferramenta real.

### 4.4 OWASP ZAP — varredura logada como administrador
RESULTADO_VARREDURA_AUTENTICADA

## 5. O que ainda depende de decisão ou infraestrutura

1. **HTTPS** (único aviso restante do ZAP). Quando o sistema sair do `127.0.0.1` e for acessado pela rede, ele
   precisa de certificado. Com HTTPS o cookie de sessão passa a `Secure` e o HSTS liga sozinho (já está no código).
2. **Merge dos PRs na ordem #20 → #21 → #22.**
3. **Política de Privacidade**: preencher os campos entre colchetes (razão social, CNPJ, canal de contato).
4. **Backup**: agendar o `docker/README.md` → "Backup do banco" e guardar fora do computador.
5. **Rede do Debian**: está no Wi-Fi e o provedor não tem rota IPv4 para a Microsoft/GitHub. Para um servidor da
   academia, cabo de rede é mais estável.
