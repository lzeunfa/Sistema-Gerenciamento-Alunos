#!/usr/bin/env bash
# Teste de volume (escalabilidade com muitos dados): enche o banco de TESTE de alunos ficticios e mede as telas
# na copia do sistema ligada a ele (docker/copia-teste.sh); o gktgym nunca e tocado.
#   bash docker/teste-volume.sh            20 mil alunos
#   bash docker/teste-volume.sh 100000     100 mil alunos
set -euo pipefail
cd "$(dirname "$0")/.."
source docker/copia-teste.sh
QUANTIDADE="${1:-20000}"

U=vol_medicao
S="Vol-$(openssl rand -hex 16)-7"
export U S

trap parar_copia_teste EXIT
subir_copia_teste
cria_admin_teste "$U" "$S"

echo ">>> Cadastrando $QUANTIDADE alunos ficticios no banco de teste"
"${TESTES[@]}" testes php tests/carga/popular_volume.php "$QUANTIDADE" --confirmo-banco-de-teste

echo ">>> Medindo (tempo medio de 5 acessos por tela)"
"${TESTES[@]}" -e U -e S -e BASE="https://$COPIA" testes bash tests/carga/medir_paginas.sh
docker stats --no-stream --format '{{.Name}}: CPU {{.CPUPerc}}, memoria {{.MemUsage}}' "$COPIA" gkt-banco-1
