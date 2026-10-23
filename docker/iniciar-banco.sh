#!/usr/bin/env bash
# Prepara o banco no SQL Server do container. Seguro para rodar toda vez que o ambiente sobe:
# cria o banco so se nao existir, aplica dados iniciais e migracoes (idempotentes) e acerta o usuario do sistema.
#   docker/iniciar-banco.sh                       banco gktgym (usado pelo servico banco-init)
#   docker/iniciar-banco.sh gktgym_teste --recriar   apaga e recria do zero (usado pelos testes)
# Precisa de: SA_PASSWORD, DB_PASSWORD e DB_HOST (padrao: banco)
set -euo pipefail

BANCO="${1:-gktgym}"
RECRIAR="${2:-}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
: "${SA_PASSWORD:?defina SA_PASSWORD}"
: "${DB_PASSWORD:?defina DB_PASSWORD}"
USUARIO_APP="${DB_USERNAME:-gkt_app}"

sqlcmd_sa() { sqlcmd -S "${DB_HOST:-banco},${DB_PORT:-1433}" -U sa -P "$SA_PASSWORD" -C -b -f 65001 "$@"; }
# senha dentro de uma string SQL: aspas simples viram duas
SENHA_SQL="${DB_PASSWORD//\'/\'\'}"

if [[ "$RECRIAR" == "--recriar" && ! "$BANCO" =~ _teste$ ]]; then
    echo "Por seguranca, --recriar so funciona em banco terminado em _teste." >&2
    exit 1
fi

echo "Aguardando o SQL Server..."
for tentativa in $(seq 1 60); do
    if sqlcmd_sa -Q "SELECT 1" >/dev/null 2>&1; then break; fi
    if [[ $tentativa == 60 ]]; then echo "SQL Server nao respondeu em 2 minutos." >&2; exit 1; fi
    sleep 2
done

rodar() { # roda um script de DB/ trocando o nome do banco
    sed "s/\[gktgym\]/[$BANCO]/g" "$RAIZ/DB/$1.sql" > "/tmp/$1.sql"
    sqlcmd_sa -i "/tmp/$1.sql" >/dev/null
    echo "  ok: DB/$1.sql"
}

if [[ "$RECRIAR" == "--recriar" ]]; then
    sqlcmd_sa -Q "IF DB_ID('$BANCO') IS NOT NULL BEGIN ALTER DATABASE [$BANCO] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [$BANCO]; END" >/dev/null
fi

if [[ "$(sqlcmd_sa -h -1 -W -Q "SET NOCOUNT ON; SELECT CASE WHEN DB_ID('$BANCO') IS NULL THEN 0 ELSE 1 END")" == "0" ]]; then
    echo "Criando o banco $BANCO..."
    sqlcmd_sa -Q "CREATE DATABASE [$BANCO]" >/dev/null
    rodar estrutura
fi

for script in dados_iniciais migracao_login migracao_seguranca migracao_direitos_titular; do
    rodar "$script"
done

# usuario do sistema com privilegio minimo (igual a producao, ver DB/migracao_seguranca.sql)
sqlcmd_sa -d "$BANCO" -Q "
IF SUSER_ID('$USUARIO_APP') IS NULL
    CREATE LOGIN [$USUARIO_APP] WITH PASSWORD = '$SENHA_SQL', CHECK_POLICY = ON;
ELSE
    ALTER LOGIN [$USUARIO_APP] WITH PASSWORD = '$SENHA_SQL';
IF USER_ID('$USUARIO_APP') IS NULL
    CREATE USER [$USUARIO_APP] FOR LOGIN [$USUARIO_APP];
ALTER ROLE db_datareader ADD MEMBER [$USUARIO_APP];
ALTER ROLE db_datawriter ADD MEMBER [$USUARIO_APP];
DENY ALTER, REFERENCES ON SCHEMA::dbo TO [$USUARIO_APP];
DENY UPDATE, DELETE ON dbo.AUDITORIA TO [$USUARIO_APP];" >/dev/null

echo "Banco $BANCO pronto."
