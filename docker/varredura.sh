#!/usr/bin/env bash
# Varredura OWASP ZAP sem login (como um atacante de fora), contra a copia do sistema ligada ao banco de TESTE
# (docker/copia-teste.sh): as tentativas de login do ZAP nao vao para a auditoria do gktgym nem bloqueiam
# administradores de verdade. Relatorio em relatorios/zap-relatorio.html.
#   bash docker/varredura.sh
set -euo pipefail
cd "$(dirname "$0")/.."
source docker/copia-teste.sh

trap parar_copia_teste EXIT
subir_copia_teste

mkdir -p relatorios && chmod 777 relatorios
"${COMPOSE[@]}" --profile seguranca run --rm --no-deps zap
echo ">>> Relatorio: relatorios/zap-relatorio.html"
