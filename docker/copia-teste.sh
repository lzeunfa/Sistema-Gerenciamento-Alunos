# Copia do sistema ligada ao banco de TESTE (gktgym_teste), para os testes que atacam ou escrevem no banco:
# varreduras ZAP, carga e volume; carregue com "source" (nao rode sozinho).
# Motivo: o container "app" usa o gktgym, que no servidor da academia tem os dados reais. A copia nao tem porta
# aberta; os outros containers a acham pelo nome (https://gkt-app-teste).

COMPOSE=(docker compose -f docker/docker-compose.yml)
TESTES=("${COMPOSE[@]}" --profile testes run --rm -T)
COPIA=gkt-app-teste

parar_copia_teste() { docker rm -f "$COPIA" > /dev/null 2>&1 || true; }

# recria o gktgym_teste do zero e sobe a copia apontando para ele; espera ficar saudavel
subir_copia_teste() {
    echo ">>> Recriando o banco de teste e subindo uma copia do sistema ligada a ele ($COPIA)"
    "${TESTES[@]}" testes php docker/iniciar-banco.php gktgym_teste --recriar
    parar_copia_teste
    "${COMPOSE[@]}" run -d --no-deps --name "$COPIA" -e GKT_DB_DATABASE=gktgym_teste app > /dev/null
    for _ in $(seq 1 45); do
        [ "$(docker inspect -f '{{.State.Health.Status}}' "$COPIA")" = healthy ] && return 0
        sleep 2
    done
    echo "A copia do sistema nao ficou saudavel em 90 s" >&2
    return 1
}

# na_copia [-e VAR]... comando...: roda dentro da copia (sempre no banco de teste).
# "-e VAR" sem valor repassa a variavel do script, sem a senha aparecer na linha de comando (ps).
na_copia() {
    local opcoes=()
    while [ "${1:-}" = "-e" ]; do opcoes+=(-e "$2"); shift 2; done
    docker exec -i "${opcoes[@]}" "$COPIA" "$@"
}

# cria_admin_teste <login> <senha>: administrador temporario no banco de teste
cria_admin_teste() {
    GKT_NOVA_SENHA="$2" na_copia -e GKT_NOVA_SENHA php DB/criar_admin.php "$1" "TESTE AUTOMATICO" "$1@exemplo.invalid"
}
