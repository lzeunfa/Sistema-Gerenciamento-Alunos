<!--
COMO MANTER ESTE ARQUIVO
- Passos copiáveis, na ordem, para uma máquina Debian limpa. Mudou algo no Dockerfile ou no compose? Atualize aqui.
- Exemplo de passo bom:  "docker compose -f docker/docker-compose.yml up -d --wait app" com o que deve aparecer depois.
- Exemplo de passo ruim: "suba os containers".
-->

# Sistema GKT em Docker (Debian)

Roda o sistema igual em qualquer computador, sem instalar XAMPP, drivers nem SQL Server:

| Container | O que é |
|---|---|
| `banco` | SQL Server 2022 Express (imagem oficial da Microsoft). Os dados ficam no volume `dados-sqlserver`. |
| `banco-init` | Roda a cada subida (`docker/iniciar-banco.php`): cria o banco (se não existir), aplica `DB/` e acerta o usuário `gkt_app`. |
| `app` | PHP 8.2 + Apache + drivers, com as mesmas proteções do XAMPP, **só em HTTPS**: https://127.0.0.1:8443 |
| `testes` | Só quando pedido: roda os testes automatizados num banco separado (`gktgym_teste`). |
| `zap` | Só quando pedido: OWASP ZAP, varredura de segurança. |
| `carga` | Só quando pedido: k6, teste de carga (muitas pessoas usando ao mesmo tempo). |
| `gkt-app-teste` | Só durante ZAP, carga e volume: cópia do `app` ligada ao banco `gktgym_teste`, sem porta aberta (`docker/copia-teste.sh`). |

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

**Se este computador vai ser o servidor**, impeça que ele entre em suspensão sozinho (o Debian com interface
gráfica suspende depois de um tempo parado, e o sistema some da rede):
```bash
sudo systemctl mask sleep.target suspend.target hibernate.target hybrid-sleep.target
```
Para desfazer: `sudo systemctl unmask sleep.target suspend.target hibernate.target hybrid-sleep.target`.

## 2. Baixar o projeto e definir as senhas

```bash
git clone https://github.com/lzeunfa/Sistema-Gerenciamento-Alunos.git
cd Sistema-Gerenciamento-Alunos
cp docker/.env.example docker/.env
chmod 600 docker/.env
```
Edite `docker/.env` e preencha as duas senhas (elas vêm em branco e o Docker não sobe sem elas). Para gerar senhas fortes:
```bash
echo "SA: $(openssl rand -base64 18)Aa1-"; echo "APP: $(openssl rand -base64 18)Bb2-"
```
No mesmo arquivo ficam a porta (`PORTA_APP`, padrão 8443) e quem pode abrir o sistema (`ENDERECO_APP`, ver seção 8).

## 3. Subir o sistema

Construa as imagens e suba (a primeira vez demora: baixa ~2 GB):
```bash
bash docker/construir.sh
```
```bash
docker compose -f docker/docker-compose.yml up -d --wait app
```
O `construir.sh` usa a rede do próprio computador: em alguns provedores os servidores da Microsoft só respondem
por IPv6, e a rede padrão dos containers só tem IPv4 (foi o caso no Debian da academia). Em redes sem esse
problema, `docker compose -f docker/docker-compose.yml up -d --build` também funciona.

Acompanhe a criação do banco com:
```bash
docker compose -f docker/docker-compose.yml logs -f banco-init
```
Quando aparecer `Banco gktgym pronto.`, crie o seu administrador (a senha é pedida, mínimo 12 caracteres):
```bash
docker compose -f docker/docker-compose.yml exec app php DB/criar_admin.php seu_login
```
Abra **https://127.0.0.1:8443** e entre.

**Aviso de certificado no navegador:** na primeira subida o container cria um certificado HTTPS só desta instalação
(autoassinado). A conexão é criptografada, mas o navegador não conhece quem assinou e mostra "Sua conexão não é
particular". Clique em **Avançado → Continuar para 127.0.0.1**. Para o aviso sumir, use um certificado de verdade
(seção 8). Quem abrir por `http://` é mandado para `https://`.

## 4. Rodar os testes automatizados

```bash
docker compose -f docker/docker-compose.yml --profile testes run --rm testes
```
Resultado esperado no final: `OK (... tests, ... assertions)`. Os testes usam o banco `gktgym_teste`, recriado a cada
execução; o banco `gktgym` não é tocado. Para rodar só um grupo: acrescente `--testsuite funcional` no fim.

## 5. Teste de segurança (OWASP ZAP)

O ZAP age como um atacante automático: navega pelo sistema e tenta injeção de SQL, XSS, falhas de cabeçalho,
vazamento de informação, arquivos expostos etc.

**O ZAP nunca ataca o sistema de verdade.** Os scripts recriam o banco `gktgym_teste` do zero e sobem uma cópia
do sistema ligada a ele, sem porta aberta, só dentro da rede do Docker. Assim dá para rodar no servidor da academia
sem tocar nos dados reais, sem encher a auditoria de tentativas de login e sem bloquear administradores.

Sem login (como um atacante de fora):
```bash
bash docker/varredura.sh
```
Logado como administrador (telas internas: cadastro, edição, exportação, exclusão). Cria um administrador
temporário com senha aleatória e alunos fictícios no banco de teste, entra e passa a sessão para o ZAP:
```bash
bash docker/varredura-autenticada.sh
```
Cada uma leva de 10 a 40 minutos. Os relatórios ficam em `relatorios/` (abra o `.html` no navegador). A pasta
`relatorios/` nunca vai para o Git.

**Como ler:** cada alerta tem um nível. `High` e `Medium` precisam de correção; `Low` e `Informational` são
recomendações. Mande o JSON (ou cole os alertas High/Medium) para revisão.

## 6. Escalabilidade: carga e volume

**Carga** (muitas pessoas usando ao mesmo tempo): o k6 simula até 30 pessoas na recepção usando o sistema e um
pico de logins. Cadastra alunos durante o teste, por isso também usa a cópia ligada ao banco de teste:
```bash
bash docker/teste-carga.sh
```
Resumo em `relatorios/carga-resumo.json` e uso de CPU/memória em `relatorios/carga-recursos.csv`.

**Volume** (muitos alunos no banco): enche o banco de **teste** com alunos fictícios e mede cada tela na cópia;
o `gktgym` não é tocado:
```bash
bash docker/teste-volume.sh 20000
```
Saída: uma linha por tela com o tempo médio. Abaixo de 500 ms é confortável para uso na recepção.

## 7. Backup e restauração

Backup verificado (o SQL Server confere o arquivo depois de gravar) em `~/backups-gkt`, fora da pasta do projeto,
legível só pelo seu usuário. Backups com mais de 30 dias são apagados:
```bash
bash docker/backup.sh
```
Uma vez por mês, prove que o backup volta (restaura numa cópia temporária, compara as contagens e apaga a cópia):
```bash
bash docker/backup.sh --testar-restauracao
```
Para agendar todo dia às 23h, rode o backup uma vez à mão (cria a pasta), depois `crontab -e` e acrescente a linha
(ajuste a pasta do projeto):
```
0 23 * * * cd ~/projetos/Sistema-Gerenciamento-Alunos && bash docker/backup.sh >> ~/backups-gkt/backup.log 2>&1
```
**O backup contém dados pessoais**: guarde uma cópia fora deste computador (HD externo ou nuvem), de preferência
criptografada. Backup que fica só no mesmo disco some junto com ele.

**Restaurar** (desastre, ou trazer o banco que estava no XAMPP, feito com `DB/backup.ps1`). Substitui **todos** os
dados do `gktgym` do container; antes, faz um backup do estado atual:
```bash
bash docker/restaurar.sh ~/backups-gkt/gktgym_AAAAMMDD_HHMMSS.bak --confirmo-substituir
```
Depois de restaurar, a subida aplica as migrações que faltarem no backup e religa o usuário `gkt_app`.

## 8. Acesso pela rede da academia

Por padrão o sistema só abre neste computador (`ENDERECO_APP=127.0.0.1`). Para os computadores da recepção:

1. Em `docker/.env`, coloque o IP deste servidor na rede nas duas linhas (veja o IP com `hostname -I`):
   ```
   ENDERECO_APP=192.168.1.23
   GKT_CERT_IP=192.168.1.23
   ```
   Use o IP da rede local, não `0.0.0.0`: assim o sistema não fica disponível em outras interfaces (VPN, Wi-Fi de
   visitantes). **Atenção:** portas publicadas pelo Docker passam por cima do firewall `ufw`; quem decide quem
   enxerga o sistema é este `ENDERECO_APP`.
2. Fixe esse IP no roteador (reserva de DHCP), senão ele pode mudar e os computadores perdem o sistema.
3. Gere o certificado de novo com o IP novo e suba:
   ```bash
   docker compose -f docker/docker-compose.yml down && docker volume rm gkt_certificados
   ```
   ```bash
   docker compose -f docker/docker-compose.yml up -d --wait app
   ```
4. Nos computadores da recepção, abra `https://192.168.1.23:8443` e aceite o aviso do certificado uma vez.

**Certificado de verdade** (sem aviso): se a academia tiver um domínio, gere um certificado (por exemplo, Let's
Encrypt) e copie para o container com os nomes `gkt.crt` e `gkt.key`:
```bash
docker compose -f docker/docker-compose.yml cp seu-certificado.crt app:/etc/gkt-certificados/gkt.crt
docker compose -f docker/docker-compose.yml cp sua-chave.key app:/etc/gkt-certificados/gkt.key
docker compose -f docker/docker-compose.yml restart app
```
O certificado fica no volume `certificados` e continua valendo depois de `down`/`up`.

## Dia a dia

| Para | Comando (sempre com `-f docker/docker-compose.yml`) |
|---|---|
| Parar tudo | `docker compose -f docker/docker-compose.yml down` |
| Atualizar depois de um `git pull` | `bash docker/construir.sh` e depois `docker compose -f docker/docker-compose.yml up -d --wait app` |
| Ver erros do PHP/Apache | `docker compose -f docker/docker-compose.yml logs -f app` |
| Ver se está tudo de pé | `docker compose -f docker/docker-compose.yml ps` (o `app` deve estar `healthy`) |
| Backup do banco | `bash docker/backup.sh` |
| **Apagar tudo, inclusive o banco** | `docker compose -f docker/docker-compose.yml down -v` (irreversível: faça backup antes) |

O comando `down` sem `-v` **mantém** os dados; só `down -v` apaga o volume do banco. Se o computador reiniciar, os
containers sobem sozinhos (`restart: unless-stopped`), desde que o Docker esteja ativo (`sudo systemctl enable docker`).
