#!/bin/bash

# Migration and seeding script for deployment
set -e

echo "Starting database migration and seeding..."

# Check if environment is provided
if [ -z "$1" ]; then
    echo "Usage: $0 <environment>"
    echo "Available environments: production, staging, development"
    exit 1
fi

ENVIRONMENT=$1

# Set environment file
case $ENVIRONMENT in
    production)
        ENV_FILE=".env.production"
        ;;
    staging)
        ENV_FILE=".env.staging"
        ;;
    development)
        ENV_FILE=".env"
        ;;
    *)
        echo "Invalid environment: $ENVIRONMENT"
        exit 1
        ;;
esac

# Check if environment file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "Environment file $ENV_FILE not found!"
    exit 1
fi

# Copy environment file
cp "$ENV_FILE" .env

echo "Using environment: $ENVIRONMENT"

# Wait for database to be ready
echo "Waiting for database connection..."
php artisan tinker --execute="DB::connection()->getPdo();" || {
    echo "Database connection failed. Retrying in 10 seconds..."
    sleep 10
    php artisan tinker --execute="DB::connection()->getPdo();" || {
        echo "Database connection failed after retry. Exiting."
        exit 1
    }
}

echo "Database connection successful!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed database based on environment
if [ "$ENVIRONMENT" = "production" ]; then
    echo "Seeding production data..."
    php artisan db:seed --class=UserSeeder --force
elif [ "$ENVIRONMENT" = "staging" ]; then
    echo "Seeding staging data..."
    php artisan db:seed --force
else
    echo "Seeding development data..."
    php artisan db:seed --force
fi

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Set proper permissions
echo "Setting file permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

echo "Migration and seeding completed successfully!"