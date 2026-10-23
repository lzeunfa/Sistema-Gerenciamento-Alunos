#!/usr/bin/env bash
# Backup do banco gktgym do container (LGPD art. 46: disponibilidade). Equivalente Docker do DB/backup.ps1.
#   bash docker/backup.sh                       backup verificado em ~/backups-gkt (fora da pasta do projeto)
#   bash docker/backup.sh --testar-restauracao  tambem restaura numa copia temporaria e confere as contagens
# Opcionais: GKT_PASTA_BACKUP (padrao ~/backups-gkt), GKT_MANTER_DIAS (padrao 30).
# O backup contem dados pessoais: guarde uma copia fora deste computador, de preferencia criptografada.
# Agendar todo dia as 23h (crontab -e):  0 23 * * * cd ~/projetos/Sistema-Gerenciamento-Alunos && bash docker/backup.sh >> ~/backups-gkt/backup.log 2>&1
set -euo pipefail
cd "$(dirname "$0")/.."
source docker/funcoes-banco.sh

DESTINO="${GKT_PASTA_BACKUP:-$HOME/backups-gkt}"
MANTER_DIAS="${GKT_MANTER_DIAS:-30}"
TESTAR=0
[ "${1:-}" = "--testar-restauracao" ] && TESTAR=1

ARQUIVO="gktgym_$(date +%Y%m%d_%H%M%S).bak"
NO_CONTAINER="/var/opt/mssql/backup/$ARQUIVO"
COPIA=gktgym_restauracao_teste

limpar() {
    sql "IF DB_ID('$COPIA') IS NOT NULL DROP DATABASE [$COPIA];" > /dev/null 2>&1 || true
    "${COMPOSE[@]}" exec -T banco rm -f "$NO_CONTAINER" || true
}
trap limpar EXIT

echo ">>> Backup de gktgym (com CHECKSUM) e verificacao do arquivo"
"${COMPOSE[@]}" exec -T banco mkdir -p /var/opt/mssql/backup
sql "BACKUP DATABASE [gktgym] TO DISK = N'$NO_CONTAINER' WITH INIT, CHECKSUM;
     RESTORE VERIFYONLY FROM DISK = N'$NO_CONTAINER' WITH CHECKSUM;"

if [ "$TESTAR" = 1 ]; then
    echo ">>> Restaurando numa copia temporaria ($COPIA) para provar que o backup volta"
    sql "RESTORE DATABASE [$COPIA] FROM DISK = N'$NO_CONTAINER' WITH $(clausulas_move "$NO_CONTAINER" "$COPIA"), CHECKSUM;" > /dev/null
    contar="SELECT (SELECT COUNT(*) FROM BANCO.dbo.ALUNOS), (SELECT COUNT(*) FROM BANCO.dbo.ALUNOS_INSCRICAO), (SELECT COUNT(*) FROM BANCO.dbo.ADMINISTRACAO), (SELECT COUNT(*) FROM BANCO.dbo.AUDITORIA);"
    original="$(sql "${contar//BANCO/gktgym}")"
    restaurado="$(sql "${contar//BANCO/$COPIA}")"
    echo "    alunos, inscricoes, administradores, auditoria: original [$original] | restaurado [$restaurado]"
    # alguem usando o sistema durante o backup pode mudar a contagem do original; o restaurado nunca pode estar vazio
    [ "$original" = "$restaurado" ] || echo "    AVISO: contagens diferentes (o sistema estava em uso?). Rode de novo fora do horario."
fi

echo ">>> Copiando para $DESTINO"
mkdir -p "$DESTINO"
chmod 700 "$DESTINO"
"${COMPOSE[@]}" cp "banco:$NO_CONTAINER" "$DESTINO/$ARQUIVO"
chmod 600 "$DESTINO/$ARQUIVO"

# minimizacao (LGPD): nao guardar dados pessoais alem do necessario
find "$DESTINO" -maxdepth 1 -name 'gktgym_*.bak' -mtime +"$MANTER_DIAS" -print -delete

echo "Backup criado e verificado: $DESTINO/$ARQUIVO ($(du -h "$DESTINO/$ARQUIVO" | cut -f1))"
