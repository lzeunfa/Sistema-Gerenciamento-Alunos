#!/usr/bin/env bash
# Restaura um backup no banco gktgym do container, SUBSTITUINDO todos os dados atuais.
# Serve para recuperar de um desastre ou para trazer para o Docker um backup feito no XAMPP (DB/backup.ps1).
#   bash docker/restaurar.sh ~/backups-gkt/gktgym_20261023_230000.bak --confirmo-substituir
# Antes de substituir, faz um backup do estado atual (docker/backup.sh), para dar para voltar atras.
set -euo pipefail
cd "$(dirname "$0")/.."
ARQUIVO="${1:-}"
if [ ! -f "$ARQUIVO" ] || [ "${2:-}" != "--confirmo-substituir" ]; then
    echo "Uso: bash docker/restaurar.sh <arquivo.bak> --confirmo-substituir" >&2
    echo "ATENCAO: substitui TODOS os dados do banco gktgym do container pelos do arquivo." >&2
    exit 1
fi
source docker/funcoes-banco.sh

NO_CONTAINER="/var/opt/mssql/backup/restaurar_$(date +%Y%m%d_%H%M%S).bak"
limpar() {
    "${COMPOSE[@]}" exec -T banco rm -f "$NO_CONTAINER" || true
    # se o restore falhou no meio, o banco nao pode ficar preso em modo de um usuario so
    sql "IF DATABASEPROPERTYEX('gktgym', 'UserAccess') = 'SINGLE_USER' ALTER DATABASE [gktgym] SET MULTI_USER;" > /dev/null 2>&1 || true
}
trap limpar EXIT

echo ">>> Backup de seguranca do estado atual"
bash docker/backup.sh

echo ">>> Parando o sistema e enviando o arquivo para o container do banco"
"${COMPOSE[@]}" stop app
"${COMPOSE[@]}" exec -T banco mkdir -p /var/opt/mssql/backup
"${COMPOSE[@]}" cp "$ARQUIVO" "banco:$NO_CONTAINER"
# o docker cp grava como root; o SQL Server roda como o usuario mssql (10001) e precisa ler o arquivo
"${COMPOSE[@]}" exec -T -u root banco chown 10001 "$NO_CONTAINER"

echo ">>> Conferindo o arquivo"
sql "RESTORE VERIFYONLY FROM DISK = N'$NO_CONTAINER';"
MOVE="$(clausulas_move "$NO_CONTAINER" gktgym)"

echo ">>> Restaurando"
sql "IF DB_ID('gktgym') IS NOT NULL ALTER DATABASE [gktgym] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
     RESTORE DATABASE [gktgym] FROM DISK = N'$NO_CONTAINER' WITH $MOVE, REPLACE;
     ALTER DATABASE [gktgym] SET MULTI_USER;"

# a subida roda o banco-init de novo: aplica as migracoes que faltarem no backup e religa o usuario gkt_app
echo ">>> Subindo o sistema (migracoes e usuario do banco)"
"${COMPOSE[@]}" up -d --wait app

echo "Restaurado. Alunos, inscricoes, administradores: $(sql 'SELECT (SELECT COUNT(*) FROM gktgym.dbo.ALUNOS), (SELECT COUNT(*) FROM gktgym.dbo.ALUNOS_INSCRICAO), (SELECT COUNT(*) FROM gktgym.dbo.ADMINISTRACAO);')"
