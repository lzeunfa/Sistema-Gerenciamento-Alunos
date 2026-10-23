#!/usr/bin/env bash
# Mede o tempo de resposta das telas principais, logado como administrador temporario.
# Roda no container "testes" contra o "app". Variaveis: U (login), S (senha), BASE (padrao https://app).
# Saida: uma linha por tela com o tempo medio de 5 acessos.
set -euo pipefail
pote=$(mktemp)
t=$(curl -sk -c "$pote" -b "$pote" ${BASE:-https://app}/index.php | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p')
curl -sk -o /dev/null -c "$pote" -b "$pote" --data-urlencode "csrf_token=$t" --data-urlencode "inputUser=$U" --data-urlencode "inputUserPass=$S" ${BASE:-https://app}/BACKEND/CONTROLLER/actions.php

medir() {
    local nome="$1" url="$2" total=0 tempo
    for _ in 1 2 3 4 5; do
        tempo=$(curl -sk -o /dev/null -b "$pote" -w '%{time_total}' "${BASE:-https://app}/$url")
        total=$(awk -v a="$total" -v b="$tempo" 'BEGIN { print a + b }')
    done
    awk -v n="$nome" -v t="$total" 'BEGIN { printf "%-34s %7.0f ms\n", n, t / 5 * 1000 }'
}

medir "Dashboard (contagens)" "sistema.php?pagina=DASHBOARD"
medir "Alunos, pagina 1" "sistema.php?pagina=ALUNOS"
medir "Alunos, pagina 250" "sistema.php?pagina=ALUNOS&p=250"
medir "Alunos, filtro pendentes" "sistema.php?pagina=ALUNOS&filtrando=alunos_pendentes"
medir "Alunos, filtro plano 2x" "sistema.php?pagina=ALUNOS&filtrando=plano_2"
medir "Turmas, modalidade 1" "sistema.php?pagina=TURMAS&filtrando=1"
medir "Cadastrar (formulario)" "sistema.php?pagina=CADASTRAR"
rm -f "$pote"
