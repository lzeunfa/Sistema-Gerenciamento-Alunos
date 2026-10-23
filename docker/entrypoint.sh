#!/usr/bin/env bash
# Inicio do container app: garante um certificado HTTPS e sobe o Apache.
# Se /etc/gkt-certificados ja tiver gkt.crt e gkt.key (certificado real), usa esses.
# Senao, gera um autoassinado so desta instalacao (o navegador vai avisar; ver docker/README.md).
set -euo pipefail
DIR=/etc/gkt-certificados
if [[ ! -s "$DIR/gkt.crt" || ! -s "$DIR/gkt.key" ]]; then
    echo "Gerando certificado HTTPS autoassinado para esta instalacao..."
    mkdir -p "$DIR"
    openssl req -x509 -newkey rsa:3072 -nodes -days 825 -sha256 \
        -subj "/CN=localhost/O=GKT Artes Marciais" \
        -addext "subjectAltName=DNS:localhost,DNS:app,IP:127.0.0.1${GKT_CERT_IP:+,IP:$GKT_CERT_IP}" \
        -keyout "$DIR/gkt.key" -out "$DIR/gkt.crt" 2>/dev/null
fi
chmod 600 "$DIR/gkt.key"
exec apache2-foreground
