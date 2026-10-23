#!/usr/bin/env bash
# Teste de carga (escalabilidade) com k6, ver tests/carga/carga.js. Cadastra alunos durante o teste: por isso roda
# contra a copia do sistema ligada ao banco de TESTE (docker/copia-teste.sh), nunca o gktgym.
#   bash docker/teste-carga.sh
set -euo pipefail
cd "$(dirname "$0")/.."
source docker/copia-teste.sh

K6_USUARIO="k6_carga"
K6_SENHA="K6-$(openssl rand -hex 16)-5"
export K6_USUARIO K6_SENHA

MONITOR=""
trap 'if [ -n "$MONITOR" ]; then kill "$MONITOR" 2>/dev/null || true; fi; parar_copia_teste' EXIT
subir_copia_teste
cria_admin_teste "$K6_USUARIO" "$K6_SENHA"

mkdir -p relatorios && chmod 777 relatorios
# uso de CPU e memoria da copia do sistema e do banco a cada 5 s durante o teste
( while sleep 5; do docker stats --no-stream --format '{{.Name}};{{.CPUPerc}};{{.MemUsage}}' "$COPIA" gkt-banco-1; done ) > relatorios/carga-recursos.csv &
MONITOR=$!

"${COMPOSE[@]}" --profile carga run --rm --no-deps -e K6_USUARIO -e K6_SENHA -e BASE_URL="https://$COPIA" carga \
    run --summary-export /relatorios/carga-resumo.json /scripts/carga.js | tee relatorios/carga-saida.txt
echo ">>> Resumo: relatorios/carga-resumo.json | recursos: relatorios/carga-recursos.csv"
