#!/usr/bin/env bash
# Constroi as imagens do sistema usando a rede do proprio computador (--network host).
# Motivo: em alguns provedores os servidores de pacotes da Microsoft so respondem por IPv6,
# e a rede padrao dos containers so tem IPv4. Depois disso, suba com:
#   docker compose -f docker/docker-compose.yml up -d
set -euo pipefail
cd "$(dirname "$0")/.."

docker build --network host -f docker/Dockerfile --target producao -t gkt-app:producao .
docker build --network host -f docker/Dockerfile --target testes   -t gkt-app:testes   .
echo "Imagens gkt-app:producao e gkt-app:testes prontas."
