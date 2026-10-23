<!--
COMO MANTER ESTE ARQUIVO
Relatório de uma execução (22 e 23/10/2026). Não edite resultados antigos: uma nova rodada de testes ganha uma seção
nova com a data. Comandos aqui devem ser copiáveis; segredos (senhas, chaves) nunca aparecem.
-->

# Relatório: sistema em Docker no Debian, segurança, escalabilidade e manutenibilidade

**Ambiente:** Debian 13.7 (trixie), x86_64, 16 GB RAM, 12 núcleos, Wi-Fi, IP 192.168.1.23 (usuário `felipedoc`),
operado do Windows por SSH com chave. Branch `feats/felipe-docker` (PR #22). Medições de volume no Windows (XAMPP +
SQL Server Express), no banco de teste.

## 1. Resumo

| Área | Resultado |
|---|---|
| Containers | Sistema, banco e preparação do banco sobem com um comando no Debian. **Só HTTPS** (TLS 1.2/1.3). |
| Testes automatizados | **251 passando dentro do container** no Debian (commit `1819013`); **293 passando no Windows** no commit atual. |
| OWASP ZAP sem login | 3 varreduras completas: **0 falhas** (110, 139 e 140 verificações aprovadas). Avisos corrigidos a cada rodada. |
| OWASP ZAP logado | ~10.500 requisições com sessão de administrador válida: **nenhum alerta High**; único Medium era "site sem HTTPS", já resolvido. |
| Bugs achados | 4 corrigidos, cada um com teste: 2 entradas que derrubavam páginas (erro 500), paginação pesada e tabela de tentativas de login sem limite. |
| Escalabilidade (volume) | Com **100 mil alunos** fictícios todas as telas abaixo de 0,7 s; com 20 mil, abaixo de 0,2 s. |
| Manutenibilidade | Complexidade máxima de função **30 → 13**, média 3,1; análise estática (PHPStan) no nível 5 sem erros. |
| Backup | Scripts de backup verificado e restauração criados; parte SQL ensaiada no Windows. |

**O que não deu para rodar:** o Debian saiu da rede por volta das 02h30 (provavelmente suspendeu; não responde a
ping nem SSH). Ficaram para quando ele voltar: teste de carga (k6), nova varredura ZAP para confirmar as duas últimas
correções, backup/restauração e volume dentro dos containers e o teste de reinício do banco. A lista exata de
comandos está na seção 9.

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
| `git checkout feats/felipe-docker` e depois `git pull` | Usa a branch com os arquivos do Docker (até o PR #22 ser mergeado) e atualiza. |
| `umask 077; echo "SA_PASSWORD=Sa-$(openssl rand -hex 16)-X1" > docker/.env` (e `DB_PASSWORD`, `PORTA_APP`, `GKT_CERT_IP`) | Gera senhas aleatórias **no próprio Debian**, num arquivo que só o seu usuário lê (`-rw-------`). Elas não passaram pela conversa nem pelo Git. |
| `bash docker/construir.sh` | Constrói as imagens `gkt-app:producao` e `gkt-app:testes` usando a rede do Debian (motivo na seção 3). |
| `docker compose -f docker/docker-compose.yml up -d --wait app` | Sobe o SQL Server, roda a preparação do banco (`banco-init`) e sobe o sistema; `--wait` espera ficar saudável. Na primeira vez gera o certificado HTTPS da instalação. |
| `docker compose -f docker/docker-compose.yml logs banco-init` | Mostra a criação do banco: estrutura, dados iniciais, migrações e usuário `gkt_app`. |
| `curl -k https://127.0.0.1:8443/index.php` | Confere que o sistema responde (200) e que `/.env` está bloqueado (404). `-k` aceita o certificado autoassinado. |

### 2.4 Testes

| Comando | O que faz |
|---|---|
| `docker compose -f docker/docker-compose.yml --profile testes run --rm testes` | Roda todos os testes num banco separado (`gktgym_teste`), recriado do zero. Inclui os testes do Apache e do HTTPS contra o container `app`. |
| `docker compose -f docker/docker-compose.yml --profile seguranca run --rm zap` | Varredura OWASP ZAP **sem login** (como um atacante de fora). Relatório em `relatorios/`. |
| `bash docker/varredura-autenticada.sh` | Varredura ZAP **logada**: limpa bloqueios de varreduras anteriores, cria um administrador temporário com senha aleatória, cadastra 2 alunos fictícios, faz login, passa o cookie de sessão para o ZAP e apaga o administrador no fim (mesmo se falhar). |
| `bash docker/teste-carga.sh` | Teste de carga com k6 (até 30 pessoas simultâneas + pico de logins), com CPU/memória dos containers a cada 5 s. *Ainda não rodado.* |
| `bash docker/teste-volume.sh 100000` | Enche o banco de **teste** com alunos fictícios e mede cada tela numa cópia do sistema sem porta aberta. *Ainda não rodado no container; medido no Windows (seção 6).* |
| `bash docker/backup.sh --testar-restauracao` | Backup verificado + restauração numa cópia temporária, comparando contagens. *Ainda não rodado no container.* |

No Windows, os mesmos testes rodam com `pwsh tests/preparar_banco_teste.ps1` e `C:\xampp\php\php.exe vendor\bin\phpunit`.

## 3. Problemas encontrados no caminho e como foram resolvidos

| Problema | Causa | Solução (já no código) |
|---|---|---|
| `pecl install sqlsrv` dizia "No releases available" | A consulta de versões do pecl falhava nessa rede; o download direto funcionava | O Dockerfile baixa o pacote pelo link direto e instala do arquivo |
| Downloads da Microsoft falhando (`No route to host`) | O provedor não tem rota IPv4 para os servidores da Microsoft; por IPv6 funciona, mas a rede dos containers só tem IPv4 | `docker/construir.sh` constrói com a rede do Debian (`--network host`), que tem IPv6 |
| Pacote `mssql-tools18` (sqlcmd) inacessível | Servidor da Microsoft só com IPv4 | Removido da imagem do sistema: o banco é preparado por `docker/iniciar-banco.php`. Imagem menor |
| `composer install` falhando de vez em quando | Wi-Fi instável com o GitHub | Até 5 tentativas automáticas |
| Imagem do ZAP no `ghcr.io` inacessível | Mesmo problema de IPv4 | Imagem oficial no Docker Hub (`zaproxy/zap-stable`) |
| Apache revelava `Apache/2.4.68 (Debian)` | O `security.conf` do Debian sobrescrevia a configuração | Arquivo renomeado para `zz-gkt.conf` (carrega por último). Achado pelos testes automatizados |
| ZAP logado não conseguia entrar | A varredura sem login anterior tinha disparado o bloqueio por IP (20 erros), ou seja, a proteção funcionou contra a própria ferramenta | O script limpa as tentativas antes de entrar |
| Login do ZAP pelo navegador interno não guardava a sessão | Limitação do modo automático do ZAP | Login feito com `curl` e cookie injetado em toda requisição do ZAP |
| Debian sumiu da rede às 02h30 | Suspensão automática do Debian com interface gráfica | Recomendação na seção 8 |

## 4. Testes de segurança

### 4.1 Testes automatizados
Simulam, entre outros: força bruta no login (bloqueio após 5 erros por login ou 20 por IP), fixação de sessão,
CSRF em todos os formulários, XSS no cadastro, injeção de SQL nos filtros e na matrícula, entradas inesperadas
(listas no lugar de texto, textos gigantes, caracteres de controle), acesso sem login a todas as páginas e ações,
download de arquivos internos (`.env`, `.git`, scripts do banco e do Docker), métodos HTTP proibidos, HTTPS
(redirecionamento, HSTS, cookie `Secure`, TLS 1.0/1.1 recusados) e permissões mínimas do banco (o sistema não
consegue apagar a auditoria nem alterar tabelas).

### 4.2 OWASP ZAP — 1ª varredura completa (sem login, HTTP)
**0 falhas, 110 verificações aprovadas, 3 avisos.** Testou injeção de SQL, XSS, injeção de comandos, path traversal,
inclusão de arquivos, XXE, SSTI, vazamento de `.env`/`.htaccess`, arquivos ocultos, Log4Shell, Spring4Shell e outros.

| Aviso | Correção |
|---|---|
| CSP ausente nas páginas de erro | `.htaccess` manda CSP restritiva em toda resposta de erro; `robots.txt` criado |
| SRI ausente na fonte do Google Fonts | Fontes hospedadas no sistema; a tela de login não carrega nada de fora e o IP do visitante não vai mais ao Google (LGPD) |
| Site só em HTTP | Resolvido na 3ª rodada (HTTPS) |

### 4.3 OWASP ZAP — 2ª varredura completa (sem login, HTTP)
**0 falhas, 139 verificações aprovadas, 4 avisos:**

| Aviso | Resultado |
|---|---|
| Cross-Origin-Resource-Policy ausente | Corrigido: `same-origin` em todas as respostas |
| Cross-Origin-Embedder-Policy ausente | Corrigido: `require-corp` nas páginas |
| Permissions-Policy ausente na página 404 | Corrigido |
| "Bypassing 403" com cabeçalho `X-Original-URL` | **Falso positivo**: o truque funciona em IIS/Nginx; o Apache ignora o cabeçalho. Confirmado e transformado em teste |

Durante essa varredura o ZAP tentou login repetidamente e **foi bloqueado pela proteção contra força bruta**
(registros `login_bloqueado` na auditoria).

### 4.4 OWASP ZAP — varredura logada como administrador (HTTP)
O ZAP navegou e atacou as telas internas (dashboard, alunos, turmas, cadastro, edição, exportação e exclusão) com
sessão válida: das ~10.500 requisições a `sistema.php`, 9.979 responderam 200 (tela interna, logado) e 493 foram
redirecionadas; a sessão continuou válida até o fim. **Nenhum alerta High.**

| Alerta | Nível | Resultado |
|---|---|---|
| Site só em HTTP | Medium | Resolvido com HTTPS (4.5) |
| Comentários suspeitos no HTML | Informativo | Corrigido: comentários de desenvolvimento viraram comentários PHP e não chegam ao navegador (com teste) |
| "GET para POST" em gravar/editar/anonimizar | Informativo | Sem risco: essas ações recusam GET e exigem token CSRF (`exigirPostComCsrf`), coberto por testes |
| Parâmetro refletido em atributo HTML (possível XSS) | Informativo | Sem risco: todo valor passa por `e()`; o ZAP não conseguiu injetar nada |
| User Agent Fuzzer | Informativo | Só registra que o servidor responde igual a navegadores antigos |

### 4.5 OWASP ZAP — 3ª varredura completa (sem login, **HTTPS**)
**0 falhas, 140 verificações aprovadas, 3 avisos:**

| Aviso | Resultado |
|---|---|
| HSTS ausente em arquivos estáticos e páginas de erro | Corrigido: HSTS em toda resposta HTTPS, inclusive erros (com teste). *Falta confirmar em nova varredura.* |
| Proxy Disclosure | Corrigido: `TRACE` desligado (`TraceEnable Off`) e `OPTIONS` recusado (só GET, POST e HEAD). *Falta confirmar em nova varredura.* |
| "Bypassing 403" | Mesmo falso positivo da 2ª rodada |

## 5. Funcionalidade e robustez

Além dos testes de cada função e tela, foram feitos testes de **entrada inesperada** (o que uma ferramenta de
ataque ou um navegador com defeito mandaria). Bugs achados e corrigidos, cada um com um teste que falharia sem a
correção:

| Bug | Efeito antes | Correção |
|---|---|---|
| Login com usuário/senha enviados como lista (`inputUser[]=x`) | Erro 500 | Tratado como campo vazio |
| `sistema.php?pagina[]=x` | Erro 500 | Página inválida volta ao dashboard |
| Paginação desenhava um botão por página | Com 20 mil alunos: 500 botões e 251 KB por tela | No máximo 9 botões; página além da última cai na última |
| Tabela de tentativas de login sem limite | Sob ataque prolongado o banco crescia para sempre | Tentativas com mais de 1 dia são apagadas a cada erro de login |

Em rodadas anteriores os testes já tinham achado: aluno cadastrado como INATIVO sem data de inativação, erro de
modalidade aparecendo como "CPF duplicado" e matrícula com quebra de linha passando pela validação.

## 6. Escalabilidade

### 6.1 Volume (muitos alunos)
Alunos fictícios no banco de teste, tempo médio de 5 acessos por tela (Windows, servidor embutido do PHP, mais lento
que o Apache do container):

| Tela | 20 mil, antes | 20 mil, depois | 100 mil, antes | 100 mil, depois |
|---|---|---|---|---|
| Dashboard (contagens) | 90 ms | 134 ms | 480 ms | 495–660 ms |
| Alunos, página 1 | 156 ms | 165 ms | 567 ms | 370 ms |
| Alunos, página 250 | 185 ms | 168 ms | 727 ms | 462 ms |
| Alunos, filtro pendentes | 97 ms | 78 ms | 573 ms | **126 ms** |
| Alunos, filtro plano 2x | 142 ms | 112 ms | 497 ms | 166 ms |
| Turmas, modalidade 1 | 68 ms | 51 ms | 208 ms | **95 ms** |
| Tamanho da tela de alunos | 251 KB | **127 KB** | 127 KB | 127 KB |

"Depois" = paginação corrigida + índices (`DB/migracao_desempenho.sql`) + lista que escolhe as 40 matrículas da
página antes de montar as colunas. O SQL Server não sugeriu mais nenhum índice.

**Conclusão:** uma academia tem de centenas a poucos milhares de alunos; nessa faixa todas as telas ficam abaixo de
0,2 s. Com 100 mil (50 vezes mais que o realista) o sistema continua usável. O dashboard é o mais lento nessa escala
porque conta a base inteira a cada abertura; não vale otimizar antes de chegar perto disso.

### 6.2 Carga (muitas pessoas ao mesmo tempo)
Preparado (`tests/carga/carga.js` e `docker/teste-carga.sh`), **não rodado**: o Debian saiu da rede antes. O
cenário simula até 30 pessoas usando o sistema ao mesmo tempo (a recepção real tem 1 a 3) e um pico de 5 logins
por segundo, com limites: 95% das telas abaixo de 0,8 s, 95% dos logins abaixo de 3 s e menos de 1% de erros. O login é lento de propósito
(Argon2id usa ~64 MB e ~0,4 s por tentativa), o que dificulta força bruta; o teste mostra quantos logins simultâneos
o servidor aguenta.

## 7. Manutenibilidade

| Medida | Antes | Agora |
|---|---|---|
| Complexidade máxima de uma função | 30 (`lerAlunoDoPost`) | 13 (`atualizarAluno`) |
| Complexidade média | — | 3,1 |
| Funções acima de 10 caminhos | várias | 1 |
| Análise estática (PHPStan) | nível 0 | **nível 5, sem erros** |
| Código duplicado | filtros e paginação copiados em Alunos e Turmas | uma função cada (`filtroAlunos`, `dadosListagem`, `_paginacao.php`) |

Medido com `C:\xampp\php\php.exe tests/metricas.php`. A validação do aluno virou uma lista de regras, compartilhada por
cadastro e edição; o script antigo `DB/update_db.php`, incompatível com o banco atual, foi removido.

## 8. O que ainda depende de você ou da infraestrutura

1. **Impedir o Debian de suspender**, se ele vai ser o servidor (foi o que interrompeu os testes desta noite):
   `sudo systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target`. Cabo de rede em vez de
   Wi-Fi também ajuda (o Wi-Fi atual derruba downloads do GitHub de vez em quando).
2. **Merge dos PRs na ordem #20 → #21 → #22.**
3. **Certificado HTTPS**: o autoassinado protege a conexão, mas o navegador avisa. Para a recepção, aceitar o aviso
   uma vez por computador resolve; com domínio, um certificado real (`docker/README.md`, seção 8).
4. **Política de Privacidade**: preencher os campos entre colchetes (razão social, CNPJ, canal de contato).
5. **Backup fora do computador**: agendar `docker/backup.sh` (ou `DB/backup.ps1` no XAMPP) e guardar cópia em outro
   lugar, criptografada.
6. **Regras de negócio para confirmar:** aluno sem inscrição não aparece nas listas (correto?); regra de numeração
   da matrícula; situação "Atrasado" depois do dia 10; planos fora do Notion (Mensal?); frequências 1x e 5x no
   filtro de Alunos.
7. **`DB/script.sql`** tem dados pessoais de exemplo num repositório público: confirmar se são fictícios.

## 9. Quando o Debian voltar: comandos que faltam (na ordem)

```bash
cd ~/projetos/Sistema-Gerenciamento-Alunos && git pull
bash docker/construir.sh && docker compose -f docker/docker-compose.yml up -d --wait app
docker compose -f docker/docker-compose.yml --profile testes run --rm testes
docker compose -f docker/docker-compose.yml --profile seguranca run --rm zap
bash docker/varredura-autenticada.sh
bash docker/teste-carga.sh
bash docker/teste-volume.sh 100000
bash docker/backup.sh --testar-restauracao
docker compose -f docker/docker-compose.yml restart banco   # o sistema deve voltar sozinho em segundos
```
Depois das varreduras e da carga, o banco do container fica com alunos fictícios. Para limpar (apaga só o banco do
Docker): `docker compose -f docker/docker-compose.yml down -v` e suba de novo.
