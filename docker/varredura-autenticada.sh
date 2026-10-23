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

# uma varredura anterior (sem login) dispara o bloqueio por tentativas no IP do container do ZAP;
# sem limpar, o login do administrador temporario seria recusado pela propria protecao
echo ">>> Limpando tentativas de login de varreduras anteriores"
"${COMPOSE[@]}" exec -T app php -r 'require "BACKEND/CONNECTION/config.php"; Database::getConexao()->exec("DELETE FROM LOGIN_TENTATIVAS"); echo "ok\n";'

echo ">>> Criando administrador temporario $ZAP_USUARIO"
"${COMPOSE[@]}" exec -T -e GKT_NOVA_SENHA="$ZAP_SENHA" app php DB/criar_admin.php "$ZAP_USUARIO" "ZAP VARREDURA" zap@exemplo.invalid

remover_admin() {
    echo ">>> Apagando o administrador temporario"
    "${COMPOSE[@]}" exec -T app php -r 'require "BACKEND/CONNECTION/config.php"; Database::getConexao()->prepare("DELETE FROM ADMINISTRACAO WHERE ADMLOGIN = ?")->execute(["zap_varredura"]); echo "ok\n";' || true
}
trap remover_admin EXIT

# alunos ficticios para as telas de editar/exportar/excluir aparecerem para o ZAP
echo ">>> Cadastrando alunos ficticios para a varredura"
"${COMPOSE[@]}" exec -T app php -r '
    require "BACKEND/CONTROLLER/funcoes.php";
    $alunos = [["ZAP ADULTO", "52998224725", "zap.adulto@exemplo.invalid", "1990-05-10", "", ""],
               ["ZAP MENOR", "11144477735", "zap.menor@exemplo.invalid", date("Y-m-d", strtotime("-10 years")), "RESPONSAVEL ZAP", "12345678909"]];
    foreach ($alunos as [$nome, $cpf, $email, $nasc, $resp, $respCpf]) {
        if (!array_filter(listarAluno(), fn($a) => $a["ALUCPF"] === $cpf)) {
            cadastrarAluno($nome, "61999990000", $email, $cpf, "M", $nasc, "70000000", "Rua Ficticia 1", [1, 3], 1, 1, 1, $resp, $respCpf);
        }
    }
    echo count(listarAluno()), " aluno(s) no banco do container\n";'

# login de verdade, dentro do container do sistema, passando pelo token CSRF como um navegador
echo ">>> Entrando no sistema com o administrador temporario"
ZAP_COOKIE=$("${COMPOSE[@]}" exec -T -e ZAP_USUARIO -e ZAP_SENHA app bash -c '
    pote=$(mktemp)
    token=$(curl -s -c "$pote" -b "$pote" http://127.0.0.1/index.php | sed -n "s/.*name=\"csrf_token\" value=\"\([^\"]*\)\".*/\1/p")
    curl -s -o /dev/null -c "$pote" -b "$pote" --data-urlencode "csrf_token=$token" \
         --data-urlencode "inputUser=$ZAP_USUARIO" --data-urlencode "inputUserPass=$ZAP_SENHA" \
         http://127.0.0.1/BACKEND/CONTROLLER/actions.php
    if curl -s -b "$pote" http://127.0.0.1/sistema.php | grep -q "Total de alunos"; then
        awk "\$6 == \"GKTSESSID\" {print \"GKTSESSID=\" \$7}" "$pote"
    fi
    rm -f "$pote"')
if [[ -z "$ZAP_COOKIE" ]]; then
    echo "Nao foi possivel entrar com o administrador temporario." >&2
    exit 1
fi
echo ">>> Sessao obtida; iniciando a varredura logada"

# copia temporaria do plano com o cookie ja preenchido (o ZAP nao recebia a variavel de ambiente);
# so o usuario atual le o arquivo, que e apagado no fim junto com o administrador temporario
PLANO=$(umask 077; mktemp /tmp/zap-plano-XXXXXX.yaml)
sed "s|\${ZAP_COOKIE}|$ZAP_COOKIE|" docker/zap-autenticado.yaml > "$PLANO"
chmod 644 "$PLANO" # o ZAP roda como outro usuario dentro do container
trap 'rm -f "$PLANO"; remover_admin' EXIT

mkdir -p relatorios && chmod 777 relatorios
"${COMPOSE[@]}" --profile seguranca run --rm \
    -v "$PLANO:/zap/plano.yaml:ro" \
    zap zap.sh -cmd -autorun /zap/plano.yaml

echo ">>> Relatorio: relatorios/zap-autenticado.html"
echo ">>> Para limpar os alunos que o ZAP cadastrou, recrie o banco do container:"
echo "    docker compose -f docker/docker-compose.yml down -v && docker compose -f docker/docker-compose.yml up -d --wait app"
