# Use php:8.3-cli-alpine as the base image
FROM php:8.3-cli-alpine as sio_test

# Install necessary tools and PHP extensions
RUN apk add --no-cache git zip bash postgresql-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql

# Set Composer cache directory
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

# Copy Composer from its image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Setup php app user
ARG USER_ID=1000
RUN adduser -u ${USER_ID} -D -H app
USER app

# Set the working directory and copy the application files
COPY --chown=app . /app
WORKDIR /app

# Expose port 8337 for the application
EXPOSE 8337

# Command to run when the container starts
CMD ["php", "-S", "0.0.0.0:8337", "-t", "public"]
