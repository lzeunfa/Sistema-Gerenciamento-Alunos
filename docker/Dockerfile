# Imagem do Sistema GKT: PHP 8.2 + Apache + drivers do SQL Server, em Debian 12.
# Alvos:  producao (padrao do docker-compose)  |  testes (producao + PHPUnit e a pasta tests/)
# Como usar: docker/README.md

# ---------- base: PHP, Apache e drivers ----------
FROM php:8.2-apache-bookworm AS base

# Driver ODBC 18 e sqlcmd da Microsoft (repositorio oficial para Debian 12)
RUN apt-get update \
 && apt-get install -y --no-install-recommends gnupg curl ca-certificates unixodbc-dev \
 && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
 && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list -o /etc/apt/sources.list.d/mssql-release.list \
 && apt-get update \
 && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 mssql-tools18 \
 && pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
 && docker-php-ext-enable sqlsrv pdo_sqlsrv \
 && apt-get purge -y --auto-remove gnupg \
 && rm -rf /var/lib/apt/lists/* /tmp/pear

ENV PATH="${PATH}:/opt/mssql-tools18/bin"

# PHP de producao + endurecimento (mesmas regras do php.ini do XAMPP, ver DOC/SEGURANCA_LGPD.md)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-gkt.ini $PHP_INI_DIR/conf.d/zz-gkt.ini

# Apache: .htaccess ligado, sem listagem de pastas, sem revelar versao
# "zz-" para carregar depois do security.conf do Debian, que define ServerTokens OS e ServerSignature On
COPY docker/apache-gkt.conf /etc/apache2/conf-available/zz-gkt.conf
RUN a2enmod headers alias && a2enconf zz-gkt

WORKDIR /var/www/html

# ---------- dependencias de desenvolvimento (PHPUnit, PHPStan) ----------
FROM composer:2 AS dependencias-dev
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist

# ---------- producao ----------
FROM base AS producao
COPY . /var/www/html
# nada de testes nem ferramentas de desenvolvimento na imagem que vai para a academia
RUN rm -rf tests phpunit.xml phpstan.neon .github \
 && chown -R root:www-data /var/www/html \
 && find /var/www/html -type d -exec chmod 750 {} + \
 && find /var/www/html -type f -exec chmod 640 {} + \
 && chmod 750 docker/*.sh
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS -o /dev/null http://127.0.0.1/index.php || exit 1

# ---------- testes ----------
FROM base AS testes
COPY . /var/www/html
COPY --from=dependencias-dev /app/vendor /var/www/html/vendor
RUN chmod +x docker/*.sh tests/*.sh
CMD ["bash", "tests/rodar_testes_container.sh"]
