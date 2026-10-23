#!/usr/bin/env bash
# Teste de volume (escalabilidade com muitos dados): enche o banco de TESTE de alunos ficticios e mede as telas.
# Nunca toca no banco gktgym: sobe uma copia temporaria do sistema, sem porta aberta, ligada ao gktgym_teste
# (recriado do zero; a proxima rodada dos testes automatizados recria de novo).
#   bash docker/teste-volume.sh            20 mil alunos
#   bash docker/teste-volume.sh 100000     100 mil alunos
set -euo pipefail
cd "$(dirname "$0")/.."
COMPOSE=(docker compose -f docker/docker-compose.yml)
TESTES=("${COMPOSE[@]}" --profile testes run --rm -T)
QUANTIDADE="${1:-20000}"
COPIA=gkt-app-volume

export U=vol_medicao
S="Vol-$(openssl rand -hex 16)-7"
export S

parar() { docker rm -f "$COPIA" > /dev/null 2>&1 || true; }
trap parar EXIT

echo ">>> Recriando o banco de teste e cadastrando $QUANTIDADE alunos ficticios"
"${TESTES[@]}" testes php docker/iniciar-banco.php gktgym_teste --recriar
"${TESTES[@]}" testes php tests/carga/popular_volume.php "$QUANTIDADE" --confirmo-banco-de-teste
"${TESTES[@]}" -e GKT_NOVA_SENHA="$S" testes php DB/criar_admin.php "$U" "VOLUME MEDICAO" vol@exemplo.invalid

echo ">>> Subindo uma copia do sistema ligada ao banco de teste"
parar
"${COMPOSE[@]}" run -d --no-deps --name "$COPIA" -e GKT_DB_DATABASE=gktgym_teste app > /dev/null
for _ in $(seq 1 45); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$COPIA")" = healthy ] && break
    sleep 2
done

echo ">>> Medindo (tempo medio de 5 acessos por tela)"
"${TESTES[@]}" -e U -e S -e BASE="https://$COPIA" testes bash tests/carga/medir_paginas.sh
docker stats --no-stream --format '{{.Name}}: CPU {{.CPUPerc}}, memoria {{.MemUsage}}' "$COPIA" gkt-banco-1
