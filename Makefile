.PHONY: help setup uninstall

.DEFAULT_GOAL := help

help:
	@echo "Usage: make <target>"
	@echo ""
	@echo "  help        Show this help"
	@echo "  setup       Build containers and install app dependencies (first-time setup)"
	@echo "  uninstall   Stop and remove containers, volumes, and images (full reset)"

setup:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@if [ ! -f app/.env ]; then cp app/.env.example app/.env; fi
	docker compose up -d --build
	@. ./.env; echo ""; echo "App is available at: http://localhost:$${APP_PORT}"

uninstall:
	docker compose down -v --remove-orphans --rmi all
