FROM php:8.3-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends iproute2 \
 && rm -rf /var/lib/apt/lists/*
COPY index.php /var/www/html/