FROM php:8.4-cli

# Installer les extensions PHP nécessaires (pdo_mysql est le fix critique)
RUN docker-php-ext-install pdo pdo_mysql mbstring curl \
    && docker-php-ext-enable pdo pdo_mysql mbstring curl

WORKDIR /app
COPY . /app

# Railway fournit la variable $PORT dynamiquement
EXPOSE 8080
CMD php -S 0.0.0.0:${PORT:-8080} -t /app
