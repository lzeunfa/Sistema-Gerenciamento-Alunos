#!/usr/bin/env bash
# Varredura OWASP ZAP logada como administrador (telas internas: cadastro, edicao, exportacao, exclusao).
# O ZAP envia formularios de verdade: por isso ataca uma copia do sistema ligada ao banco de TESTE
# (docker/copia-teste.sh), nunca o gktgym. Relatorio em relatorios/zap-autenticado.html.
#   bash docker/varredura-autenticada.sh
set -euo pipefail
cd "$(dirname "$0")/.."
source docker/copia-teste.sh

ZAP_USUARIO="zap_varredura"
ZAP_SENHA="Zap-$(openssl rand -hex 16)-9"
export ZAP_USUARIO ZAP_SENHA

PLANO=""
trap 'if [ -n "$PLANO" ]; then rm -f -- "$PLANO"; fi; parar_copia_teste' EXIT
subir_copia_teste

echo ">>> Criando administrador temporario e alunos ficticios no banco de teste"
cria_admin_teste "$ZAP_USUARIO" "$ZAP_SENHA"
# alunos para as telas de editar/exportar/excluir aparecerem para o ZAP
na_copia php -r '
    require "BACKEND/CONTROLLER/funcoes.php";
    cadastrarAluno("ZAP ADULTO", "61999990000", "zap.adulto@exemplo.invalid", "52998224725", "M", "1990-05-10", "70000000", "Rua Ficticia 1", [1, 3], 1, 1, 1, "", "");
    cadastrarAluno("ZAP MENOR", "61999990000", "zap.menor@exemplo.invalid", "11144477735", "F", date("Y-m-d", strtotime("-10 years")), "70000000", "Rua Ficticia 1", [2], 1, 1, 1, "RESPONSAVEL ZAP", "12345678909");
    echo count(listarAluno()), " aluno(s) no banco de teste\n";'

# login de verdade, dentro da copia, passando pelo token CSRF como um navegador
echo ">>> Entrando no sistema com o administrador temporario"
ZAP_COOKIE=$(na_copia -e ZAP_USUARIO -e ZAP_SENHA bash -c '
    pote=$(mktemp)
    token=$(curl -sk -c "$pote" -b "$pote" https://127.0.0.1/index.php | sed -n "s/.*name=\"csrf_token\" value=\"\([^\"]*\)\".*/\1/p")
    curl -sk -o /dev/null -c "$pote" -b "$pote" --data-urlencode "csrf_token=$token" \
         --data-urlencode "inputUser=$ZAP_USUARIO" --data-urlencode "inputUserPass=$ZAP_SENHA" \
         https://127.0.0.1/BACKEND/CONTROLLER/actions.php
    if curl -sk -b "$pote" https://127.0.0.1/sistema.php | grep -q "Total de alunos"; then
        awk "\$6 == \"GKTSESSID\" {print \"GKTSESSID=\" \$7}" "$pote"
    fi
    rm -f "$pote"')
if [[ -z "$ZAP_COOKIE" ]]; then
    echo "Nao foi possivel entrar com o administrador temporario." >&2
    exit 1
fi
echo ">>> Sessao obtida; iniciando a varredura logada"

# copia temporaria do plano com o cookie ja preenchido (o ZAP nao recebia a variavel de ambiente);
# so o usuario atual le o arquivo, que e apagado no fim junto com a copia do sistema
PLANO=$(umask 077; mktemp /tmp/zap-plano-XXXXXX.yaml)
sed "s|\${ZAP_COOKIE}|$ZAP_COOKIE|" docker/zap-autenticado.yaml > "$PLANO"
chmod 644 "$PLANO" # o ZAP roda como outro usuario dentro do container

mkdir -p relatorios && chmod 777 relatorios
"${COMPOSE[@]}" --profile seguranca run --rm --no-deps \
    -v "$PLANO:/zap/plano.yaml:ro" \
    zap zap.sh -cmd -autorun /zap/plano.yaml

echo ">>> Relatorio: relatorios/zap-autenticado.html"
