# Funcoes usadas por docker/backup.sh e docker/restaurar.sh (carregue com "source", nao rode sozinho).
# Comandos SQL vao para o sqlcmd que ja vem na imagem oficial do SQL Server, como administrador (sa).

COMPOSE=(docker compose -f docker/docker-compose.yml)

# senha do sa lida do docker/.env sem executar o arquivo; vai ao sqlcmd por variavel de ambiente,
# nunca pela linha de comando (que qualquer usuario do computador ve com "ps")
SQLCMDPASSWORD="$(grep -m1 '^SA_PASSWORD=' docker/.env | cut -d= -f2- | tr -d '\r')"
[ -n "$SQLCMDPASSWORD" ] || { echo "SA_PASSWORD vazio em docker/.env" >&2; exit 1; }
export SQLCMDPASSWORD

"${COMPOSE[@]}" ps --status running --services | grep -qx banco || { echo "O container do banco nao esta rodando (docker compose ... up -d --wait app)" >&2; exit 1; }

SQLCMD="$("${COMPOSE[@]}" exec -T banco sh -c 'for c in /opt/mssql-tools18/bin/sqlcmd /opt/mssql-tools/bin/sqlcmd; do if [ -x "$c" ]; then echo "$c"; break; fi; done')"
[ -n "$SQLCMD" ] || { echo "sqlcmd nao encontrado no container do banco" >&2; exit 1; }

# sql "comando": roda e devolve o resultado sem cabecalho, colunas separadas por |
sql() {
    "${COMPOSE[@]}" exec -T -e SQLCMDPASSWORD banco "$SQLCMD" -S localhost -U sa -C -b -h -1 -W -s '|' -Q "SET NOCOUNT ON; $1"
}

# clausulas_move <backup no container> <banco de destino>: "MOVE ... TO ..." de cada arquivo do backup para a pasta
# de dados do container, com o nome do destino. Backups do Windows (DB/backup.ps1) trazem caminhos C:\...
clausulas_move() {
    local move
    move="$(sql "RESTORE FILELISTONLY FROM DISK = N'$1';" | awk -F'|' -v d="$2" -v q="'" '
        $3 == "D" || $3 == "L" {
            nome = $1; gsub(q, q q, nome)
            if ($3 == "D") { n++; arq = d (n > 1 ? "_" n : "") ".mdf" } else { m++; arq = d "_log" (m > 1 ? "_" m : "") ".ldf" }
            printf "%sMOVE N%s%s%s TO N%s/var/opt/mssql/data/%s%s", sep, q, nome, q, q, arq, q; sep = ", "
        }')"
    [ -n "$move" ] || { echo "Nao consegui ler os arquivos de dentro do backup $1" >&2; return 1; }
    echo "$move"
}
