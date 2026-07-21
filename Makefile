.PHONY: help setup test uninstall

.DEFAULT_GOAL := help

help:
	@echo "Usage: make <target>"
	@echo ""
	@echo "  help        Show this help"
	@echo "  setup       Build containers and install app dependencies (first-time setup)"
	@echo "  test        Run the test suite against a dedicated test database"
	@echo "  uninstall   Stop and remove containers, volumes, and images (full reset)"

setup:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@if [ ! -f app/.env ]; then cp app/.env.example app/.env; fi
	docker compose up -d --build
	@. ./.env; echo ""; echo "App is available at: http://localhost:$${APP_PORT}"

test:
	@. ./.env; \
	if ! docker compose exec -T postgres psql -U "$$DB_USER" -d "$$DB_NAME" -tc "SELECT 1 FROM pg_database WHERE datname = '$${DB_NAME}_test'" | grep -q 1; then \
		docker compose exec -T postgres psql -U "$$DB_USER" -d "$$DB_NAME" -c "CREATE DATABASE $${DB_NAME}_test"; \
	fi
	docker compose exec -e APP_ENV=test php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	docker compose exec -u "$$(id -u):$$(id -g)" php php bin/phpunit

uninstall:
	docker compose down -v --remove-orphans --rmi all
