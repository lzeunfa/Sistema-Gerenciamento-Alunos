#!/usr/bin/env bash
# Roda todos os testes dentro do container "testes" (docker/README.md).
# Recria o banco gktgym_teste do zero e passa os argumentos para o PHPUnit (ex.: --testsuite unidade).
set -euo pipefail
cd "$(dirname "$0")/.."
php docker/iniciar-banco.php gktgym_teste --recriar
exec vendor/bin/phpunit "$@"
