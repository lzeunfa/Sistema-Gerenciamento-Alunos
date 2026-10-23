# Cria do zero o banco gktgym_teste usado pelos testes de integracao e funcionais.
# Usa os mesmos scripts de DB/ (com o nome do banco trocado), entao tambem testa a instalacao.
# Uso: pwsh tests/preparar_banco_teste.ps1 [-Servidor localhost\SQLEXPRESS] [-UsuarioApp gkt_app]
param(
    [string]$Servidor = 'localhost\SQLEXPRESS',
    [string]$Banco = 'gktgym_teste',
    [string]$UsuarioApp = 'gkt_app'
)
$ErrorActionPreference = 'Stop'
$raiz = Split-Path $PSScriptRoot -Parent
$temp = Join-Path ([IO.Path]::GetTempPath()) "gkt_banco_teste"
New-Item -ItemType Directory -Force $temp | Out-Null

function Sql([string]$comando, [string]$banco = 'master') {
    sqlcmd -S $Servidor -E -C -b -d $banco -Q $comando | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Falhou: $comando" }
}

if ($Banco -notmatch '_teste$') { throw "Por seguranca o banco de teste precisa terminar em _teste." }

Sql "IF DB_ID('$Banco') IS NOT NULL BEGIN ALTER DATABASE [$Banco] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [$Banco]; END; CREATE DATABASE [$Banco];"

foreach ($script in 'estrutura', 'dados_iniciais', 'migracao_login', 'migracao_seguranca', 'migracao_direitos_titular', 'migracao_desempenho') {
    $conteudo = (Get-Content (Join-Path $raiz "DB\$script.sql") -Raw -Encoding utf8) -replace '\[gktgym\]', "[$Banco]"
    $arquivo = Join-Path $temp "$script.sql"
    Set-Content $arquivo $conteudo -Encoding utf8
    sqlcmd -S $Servidor -E -C -b -i $arquivo | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Falhou ao rodar DB/$script.sql" }
}

# mesmas permissoes de producao (ver final de DB/migracao_seguranca.sql)
Sql @"
CREATE USER [$UsuarioApp] FOR LOGIN [$UsuarioApp];
ALTER ROLE db_datareader ADD MEMBER [$UsuarioApp];
ALTER ROLE db_datawriter ADD MEMBER [$UsuarioApp];
DENY ALTER, REFERENCES ON SCHEMA::dbo TO [$UsuarioApp];
DENY UPDATE, DELETE ON dbo.AUDITORIA TO [$UsuarioApp];
"@ $Banco

Write-Output "Banco $Banco pronto."
