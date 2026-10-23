#!/usr/bin/env bash
# Varredura OWASP ZAP logada como administrador (telas internas: cadastro, edicao, exportacao, exclusao).
# ATENCAO: o ZAP envia formularios de verdade e cria/altera alunos ficticios no banco do container.
# Use so no ambiente Docker de teste; no fim o banco do container e recriado do zero.
#   bash docker/varredura-autenticada.sh
set -euo pipefail
cd "$(dirname "$0")/.."
COMPOSE=(docker compose -f docker/docker-compose.yml)

export ZAP_USUARIO="zap_varredura"
ZAP_SENHA="Zap-$(openssl rand -hex 16)-9"
export ZAP_SENHA

echo ">>> Criando administrador temporario $ZAP_USUARIO"
"${COMPOSE[@]}" exec -T -e GKT_NOVA_SENHA="$ZAP_SENHA" app php DB/criar_admin.php "$ZAP_USUARIO" "ZAP VARREDURA" zap@exemplo.invalid

remover_admin() {
    echo ">>> Apagando o administrador temporario"
    "${COMPOSE[@]}" exec -T app php -r 'require "BACKEND/CONNECTION/config.php"; Database::getConexao()->prepare("DELETE FROM ADMINISTRACAO WHERE ADMLOGIN = ?")->execute(["zap_varredura"]); echo "ok\n";' || true
}
trap remover_admin EXIT

mkdir -p relatorios && chmod 777 relatorios
"${COMPOSE[@]}" --profile seguranca run --rm -e ZAP_USUARIO -e ZAP_SENHA \
    -v "$PWD/docker/zap-autenticado.yaml:/zap/plano.yaml:ro" \
    zap zap.sh -cmd -autorun /zap/plano.yaml

echo ">>> Relatorio: relatorios/zap-autenticado.html"
echo ">>> Para limpar os alunos que o ZAP cadastrou, recrie o banco do container:"
echo "    docker compose -f docker/docker-compose.yml down -v && docker compose -f docker/docker-compose.yml up -d --wait app"
