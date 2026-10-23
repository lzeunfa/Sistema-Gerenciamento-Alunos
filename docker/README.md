<!--
COMO MANTER ESTE ARQUIVO
- Passos copiáveis, na ordem, para uma máquina Debian limpa. Mudou algo no Dockerfile ou no compose? Atualize aqui.
- Exemplo de passo bom:  "docker compose -f docker/docker-compose.yml up -d --build" com o que deve aparecer depois.
- Exemplo de passo ruim: "suba os containers".
-->

# Sistema GKT em Docker (Debian)

Roda o sistema igual em qualquer computador, sem instalar XAMPP, drivers nem SQL Server:

| Container | O que é |
|---|---|
| `banco` | SQL Server 2022 Express (imagem oficial da Microsoft). Os dados ficam no volume `dados-sqlserver`. |
| `banco-init` | Roda uma vez a cada subida: cria o banco (se não existir), aplica `DB/` e acerta o usuário `gkt_app`. |
| `app` | PHP 8.2 + Apache + drivers, com as mesmas proteções do XAMPP. Em http://127.0.0.1:8080 |
| `testes` | Só quando pedido: roda os testes automatizados num banco separado (`gktgym_teste`). |
| `zap` | Só quando pedido: OWASP ZAP, varredura de segurança contra o `app`. |

O banco **não** fica exposto na rede; só os outros containers enxergam ele.

## 1. Pré-requisitos (uma vez)

O computador precisa ser **x86_64** (Intel/AMD) e ter **pelo menos 4 GB de RAM** (o SQL Server usa 2 GB):

```bash
uname -m
```
Deve mostrar `x86_64`. Em `aarch64` (ARM) a imagem do SQL Server não roda.

Instalar o Docker pelo repositório oficial:

```bash
sudo apt-get update && sudo apt-get install -y ca-certificates curl git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update && sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker $USER
```
Saia e entre de novo na sessão (para o grupo `docker` valer) e confira com `docker run --rm hello-world`.

## 2. Baixar o projeto e definir as senhas

```bash
git clone https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos.git
cd Sistema-Gerenciamento-Alunos
cp docker/.env.example docker/.env
```
Edite `docker/.env` e troque as duas senhas. Para gerar senhas fortes:
```bash
echo "SA: $(openssl rand -base64 18)Aa1-"; echo "APP: $(openssl rand -base64 18)Bb2-"
```

## 3. Subir o sistema

```bash
docker compose -f docker/docker-compose.yml up -d --build
```
A primeira vez demora (baixa ~2 GB). Acompanhe a criação do banco com:
```bash
docker compose -f docker/docker-compose.yml logs -f banco-init
```
Quando aparecer `Banco gktgym pronto.`, crie o seu administrador (a senha é pedida, mínimo 12 caracteres):
```bash
docker compose -f docker/docker-compose.yml exec app php DB/criar_admin.php seu_login
```
Abra **http://127.0.0.1:8080** e entre.

## 4. Rodar os testes automatizados

```bash
docker compose -f docker/docker-compose.yml --profile testes run --rm testes
```
Resultado esperado no final: `OK (... tests, ... assertions)`. Os testes usam o banco `gktgym_teste`, recriado a cada
execução; o banco `gktgym` não é tocado. Para rodar só um grupo: acrescente `--testsuite funcional` no fim.

## 5. Teste de segurança (OWASP ZAP)

O ZAP age como um atacante automático: navega pelo sistema e tenta injeção de SQL, XSS, falhas de cabeçalho,
vazamento de informação, arquivos expostos etc. **Ele só ataca o container `app`**, dentro da rede do Docker.

> Rode contra um banco **sem dados reais**. Depois de subir o ambiente pela primeira vez o banco `gktgym` só tem os
> dados iniciais fictícios. Nunca aponte o ZAP para um sistema que não é seu.

```bash
mkdir -p relatorios
docker compose -f docker/docker-compose.yml --profile seguranca run --rm zap
```
Leva de 5 a 20 minutos. O relatório fica em `relatorios/zap-relatorio.html` (abra no navegador) e
`relatorios/zap-relatorio.json`. A pasta `relatorios/` nunca vai para o Git.

**Como ler:** cada alerta tem um nível. `High` e `Medium` precisam de correção; `Low` e `Informational` são
recomendações. Mande o JSON (ou cole os alertas High/Medium) para revisão.

**Limitação:** esta varredura é feita sem login, como um atacante de fora. As páginas internas são cobertas pelos
testes funcionais (`tests/Funcional`), que simulam força bruta, CSRF, XSS, injeção e acesso sem permissão.

## Dia a dia

| Para | Comando (sempre com `-f docker/docker-compose.yml`) |
|---|---|
| Parar tudo | `docker compose -f docker/docker-compose.yml down` |
| Atualizar depois de um `git pull` | `docker compose -f docker/docker-compose.yml up -d --build` |
| Ver erros do PHP/Apache | `docker compose -f docker/docker-compose.yml logs -f app` |
| Backup do banco | `docker compose -f docker/docker-compose.yml exec banco /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "$SA_PASSWORD" -C -Q "BACKUP DATABASE gktgym TO DISK='/var/opt/mssql/gktgym.bak' WITH INIT"` (rode `source docker/.env` antes) |
| **Apagar tudo, inclusive o banco** | `docker compose -f docker/docker-compose.yml down -v` (irreversível) |

O comando `down` sem `-v` **mantém** os dados; só `down -v` apaga o volume do banco.
