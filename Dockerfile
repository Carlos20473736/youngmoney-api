FROM php:8.2-apache

# Instalar extensões PHP necessárias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar módulos do Apache
RUN a2enmod rewrite headers

# Copiar arquivos da aplicação
COPY . /var/www/html/

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html

# Configurar Apache para passar variáveis de ambiente para PHP
RUN echo "PassEnv DB_HOST" >> /etc/apache2/apache2.conf && \
    echo "PassEnv DB_PORT" >> /etc/apache2/apache2.conf && \
    echo "PassEnv DB_USER" >> /etc/apache2/apache2.conf && \
    echo "PassEnv DB_PASSWORD" >> /etc/apache2/apache2.conf && \
    echo "PassEnv DB_NAME" >> /etc/apache2/apache2.conf && \
    echo "PassEnv SERVER_ENCRYPTION_KEY" >> /etc/apache2/apache2.conf

# Expor porta 80
EXPOSE 80

# Comando para iniciar o Apache
CMD ["apache2-foreground"]
