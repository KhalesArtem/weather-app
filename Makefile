# Weather App Makefile

# Variables
DOCKER_COMPOSE = docker compose
PHP_CONTAINER = php
CONSOLE = $(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/console

# Help command
.PHONY: help
help:
	@echo "Weather App - Available commands:"
	@echo ""
	@echo "Setup commands:"
	@echo "  make setup          - Full project setup (build, install, db setup)"
	@echo "  make build          - Build Docker containers"
	@echo "  make install        - Install PHP dependencies"
	@echo ""
	@echo "Database commands:"
	@echo "  make db-create      - Create databases (main and test)"
	@echo "  make db-migrate     - Run database migrations"
	@echo "  make db-fixtures    - Load data fixtures"
	@echo "  make db-reset       - Drop, create, migrate and load fixtures"
	@echo "  make test-db        - Setup test database only"
	@echo ""
	@echo "Test commands:"
	@echo "  make test           - Run all PHPUnit tests"
	@echo "  make test-unit      - Run unit tests only"
	@echo "  make test-functional - Run functional tests only"
	@echo ""
	@echo "Development commands:"
	@echo "  make cache-clear    - Clear Symfony cache"
	@echo "  make logs           - Show weather logs"
	@echo "  make shell          - Enter PHP container shell"
	@echo "  make up             - Start containers"
	@echo "  make down           - Stop containers"
	@echo "  make restart        - Restart containers"

# Database commands
.PHONY: db-create
db-create:
	@echo "Creating databases..."
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:database:create --if-not-exists --env=test

.PHONY: db-migrate
db-migrate:
	@echo "Running migrations..."
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --env=test

.PHONY: db-fixtures
db-fixtures:
	@echo "Loading fixtures..."
	$(CONSOLE) doctrine:fixtures:load --no-interaction

.PHONY: db-reset
db-reset:
	@echo "Resetting database..."
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:drop --force --if-exists --env=test
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:database:create --env=test
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --env=test
	$(CONSOLE) doctrine:fixtures:load --no-interaction

# Cache commands
.PHONY: cache-clear
cache-clear:
	@echo "Clearing cache..."
	$(CONSOLE) cache:clear

# Test commands
.PHONY: test
test:
	@echo "Running tests..."
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/phpunit

.PHONY: test-unit
test-unit:
	@echo "Running unit tests..."
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/phpunit --testsuite=Unit

.PHONY: test-functional
test-functional:
	@echo "Running functional tests..."
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) php bin/phpunit --testsuite=Functional

.PHONY: test-setup
test-setup:
	@echo "Setting up test environment..."
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction
	$(CONSOLE) cache:clear --env=test

# Log commands
.PHONY: logs
logs:
	@echo "Showing weather logs..."
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) tail -f var/log/weather_dev.log

# Container commands
.PHONY: shell
shell:
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bash

# Docker commands
.PHONY: up
up:
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down:
	$(DOCKER_COMPOSE) down

.PHONY: build
build:
	$(DOCKER_COMPOSE) build

.PHONY: restart
restart: down up

# Development helpers
.PHONY: install
install:
	@echo "Installing dependencies..."
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer install

.PHONY: update
update:
	@echo "Updating dependencies..."
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer update

# Setup commands
.PHONY: setup
setup: build install db-setup
	@echo "✅ Project setup complete!"
	@echo "You can now access the application at http://localhost:8080"
	@echo "Run 'make test' to verify everything is working"

.PHONY: db-setup
db-setup: db-create db-migrate db-fixtures test-db
	@echo "✅ Database setup complete!"

# Test database setup
.PHONY: test-db
test-db:
	@echo "Setting up test database..."
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction
	$(CONSOLE) cache:clear --env=test
	@echo "✅ Test database ready!"

# Default target
.DEFAULT_GOAL := help