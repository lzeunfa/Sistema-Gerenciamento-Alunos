# Imagem do Sistema GKT: PHP 8.2 + Apache + drivers do SQL Server, em Debian 12.
# Alvos:  producao (padrao do docker-compose)  |  testes (producao + PHPUnit e a pasta tests/)
# Como usar: docker/README.md

# ---------- base: PHP, Apache e drivers ----------
FROM php:8.2-apache-bookworm AS base

# downloads tolerantes a rede instavel (Wi-Fi); docker/construir.sh constroi com a rede do host
# porque em alguns provedores os servidores da Microsoft so respondem por IPv6
RUN printf 'Acquire::Retries "5";\n' > /etc/apt/apt.conf.d/80-rede-gkt \
 && printf 'retry = 5\nretry-delay = 3\nretry-all-errors\n' > /root/.curlrc

# Driver ODBC 18 da Microsoft (o banco e preparado pelo PHP, sem sqlcmd: menos pacotes na imagem)
RUN apt-get update \
 && apt-get install -y --no-install-recommends gnupg curl ca-certificates unixodbc-dev openssl \
 && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
 && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list -o /etc/apt/sources.list.d/mssql-release.list \
 && apt-get update \
 && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
 # baixa o pacote pelo link direto: em algumas redes a consulta de versoes do pecl (/rest) falha
 && curl -fsSL -o /tmp/sqlsrv.tgz https://pecl.php.net/get/sqlsrv-5.12.0.tgz \
 && curl -fsSL -o /tmp/pdo_sqlsrv.tgz https://pecl.php.net/get/pdo_sqlsrv-5.12.0.tgz \
 && pecl install /tmp/sqlsrv.tgz /tmp/pdo_sqlsrv.tgz \
 && rm -f /tmp/sqlsrv.tgz /tmp/pdo_sqlsrv.tgz \
 && docker-php-ext-enable sqlsrv pdo_sqlsrv \
 && apt-get purge -y --auto-remove gnupg \
 && rm -rf /var/lib/apt/lists/* /tmp/pear

# PHP de producao + endurecimento (mesmas regras do php.ini do XAMPP, ver DOC/SEGURANCA_LGPD.md)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-gkt.ini $PHP_INI_DIR/conf.d/zz-gkt.ini

# Apache: .htaccess ligado, sem listagem de pastas, sem revelar versao
# "zz-" para carregar depois do security.conf do Debian, que define ServerTokens OS e ServerSignature On
COPY docker/apache-gkt.conf /etc/apache2/conf-available/zz-gkt.conf
RUN a2enmod headers alias ssl rewrite && a2enconf zz-gkt
# HTTPS: a porta 80 redireciona para a 443; certificado gerado na primeira subida (docker/entrypoint.sh)
COPY docker/apache-https.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/gkt-entrypoint
RUN chmod 755 /usr/local/bin/gkt-entrypoint

WORKDIR /var/www/html

# ---------- dependencias de desenvolvimento (PHPUnit, PHPStan) ----------
FROM composer:2 AS dependencias-dev
WORKDIR /app
COPY composer.json composer.lock ./
# ate 5 tentativas: em rede instavel algum download do GitHub pode falhar (os ja baixados ficam no cache)
RUN for tentativa in 1 2 3 4 5; do \
      composer install --no-interaction --no-progress --no-scripts --prefer-dist && exit 0; \
      echo "composer falhou (tentativa $tentativa), tentando de novo..."; sleep 5; \
    done; exit 1

# ---------- producao ----------
FROM base AS producao
COPY . /var/www/html
# nada de testes nem ferramentas de desenvolvimento na imagem que vai para a academia
RUN rm -rf tests phpunit.xml phpstan.neon .github \
 && chown -R root:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 750 {} + \
 && find /var/www/html -type f -exec chmod 640 {} +
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsSk -o /dev/null https://127.0.0.1/index.php || exit 1
CMD ["gkt-entrypoint"]

# ---------- testes ----------
FROM base AS testes
COPY . /var/www/html
COPY --from=dependencias-dev /app/vendor /var/www/html/vendor
CMD ["bash", "tests/rodar_testes_container.sh"]
