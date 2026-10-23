#!/usr/bin/env bash
# Teste de carga (escalabilidade) com k6 contra o container "app". Ver tests/carga/carga.js.
# ATENCAO: cadastra alunos ficticios no banco do container. No fim, recrie o banco se quiser limpar:
#   docker compose -f docker/docker-compose.yml down -v && docker compose -f docker/docker-compose.yml up -d --wait app
#   bash docker/teste-carga.sh
set -euo pipefail
cd "$(dirname "$0")/.."
COMPOSE=(docker compose -f docker/docker-compose.yml)

export K6_USUARIO="k6_carga"
K6_SENHA="K6-$(openssl rand -hex 16)-5"
export K6_SENHA

remover_admin() {
    echo ">>> Apagando o administrador temporario"
    "${COMPOSE[@]}" exec -T app php -r 'require "BACKEND/CONNECTION/config.php"; Database::getConexao()->prepare("DELETE FROM ADMINISTRACAO WHERE ADMLOGIN = ?")->execute(["k6_carga"]); echo "ok\n";' || true
}
trap remover_admin EXIT

echo ">>> Limpando tentativas de login e criando administrador temporario"
"${COMPOSE[@]}" exec -T app php -r 'require "BACKEND/CONNECTION/config.php"; Database::getConexao()->exec("DELETE FROM LOGIN_TENTATIVAS");'
"${COMPOSE[@]}" exec -T -e GKT_NOVA_SENHA="$K6_SENHA" app php DB/criar_admin.php "$K6_USUARIO" "K6 CARGA" k6@exemplo.invalid

mkdir -p relatorios && chmod 777 relatorios
# coleta uso de CPU e memoria dos containers durante o teste
( while sleep 5; do docker stats --no-stream --format '{{.Name}};{{.CPUPerc}};{{.MemUsage}}' gkt-app-1 gkt-banco-1; done ) > relatorios/carga-recursos.csv &
MONITOR=$!
trap 'kill $MONITOR 2>/dev/null; remover_admin' EXIT

"${COMPOSE[@]}" --profile carga run --rm -e K6_USUARIO -e K6_SENHA carga \
    run --summary-export /relatorios/carga-resumo.json /scripts/carga.js | tee relatorios/carga-saida.txt
echo ">>> Resumo: relatorios/carga-resumo.json | recursos: relatorios/carga-recursos.csv"
