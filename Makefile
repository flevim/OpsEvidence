COMPOSE ?= docker compose
BACKEND ?= $(COMPOSE) exec -T backend
AGENT_DIR ?= agent

.DEFAULT_GOAL := help
.PHONY: help up down restart build ps logs logs-worker install migrate seed fresh test test-backend test-agent lint lint-fix typecheck backend-shell frontend-shell db-shell redis-shell tinker queue-restart report clean agent-install check

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Levanta todos los servicios (build incluido)
	$(COMPOSE) up -d --build
	@echo ""
	@echo "OpsEvidence disponible en:"
	@echo "  Frontend : http://localhost:5180"
	@echo "  API      : http://localhost:8010/api"
	@echo "  Health   : http://localhost:8010/api/health"

down: ## Detiene y elimina los contenedores
	$(COMPOSE) down

restart: ## Reinicia los servicios de aplicacion
	$(COMPOSE) restart backend worker scheduler frontend

build: ## Construye las imagenes
	$(COMPOSE) build

ps: ## Estado de los servicios
	$(COMPOSE) ps

logs: ## Logs de todos los servicios
	$(COMPOSE) logs -f --tail=100

logs-worker: ## Logs del worker de colas
	$(COMPOSE) logs -f --tail=100 worker

install: ## Ejecuta el bootstrap del backend (composer, key, migraciones, seed)
	$(COMPOSE) run --rm init

migrate: ## Ejecuta las migraciones pendientes
	$(BACKEND) php artisan migrate

seed: ## Siembra datos de demostracion (idempotente)
	$(BACKEND) php artisan db:seed --force

fresh: ## Recrea el esquema y siembra datos de demostracion
	$(BACKEND) php artisan migrate:fresh --seed --force

test: test-backend test-agent ## Ejecuta toda la suite de tests

test-backend: ## Tests del backend (Pest/PHPUnit)
	$(COMPOSE) exec -T -e DB_DATABASE=opsevidence_test backend php artisan test

test-agent: ## Tests del agente (pytest)
	cd $(AGENT_DIR) && python -m pytest -q

lint: ## Verifica estilo y analisis estatico (sin modificar)
	$(BACKEND) ./vendor/bin/pint --test
	$(BACKEND) ./vendor/bin/phpstan analyse --no-progress

lint-fix: ## Corrige el estilo automaticamente
	$(BACKEND) ./vendor/bin/pint

typecheck: ## Analisis estatico
	$(BACKEND) ./vendor/bin/phpstan analyse --no-progress

check: lint test ## Lint + tests

backend-shell: ## Shell dentro del contenedor backend
	$(COMPOSE) exec backend sh

frontend-shell: ## Shell dentro del contenedor frontend
	$(COMPOSE) exec frontend sh

db-shell: ## psql dentro del contenedor de PostgreSQL
	$(COMPOSE) exec postgres psql -U opsevidence -d opsevidence

redis-shell: ## redis-cli dentro del contenedor de Redis
	$(COMPOSE) exec redis redis-cli

tinker: ## REPL de Laravel
	$(COMPOSE) exec backend php artisan tinker

queue-restart: ## Reinicia los workers de cola de forma ordenada
	$(COMPOSE) exec backend php artisan queue:restart

agent-install: ## Instala dependencias del agente en un venv local
	cd $(AGENT_DIR) && python -m venv .venv && .venv/Scripts/python -m pip install -e ".[dev]" || .venv/bin/python -m pip install -e ".[dev]"

clean: ## Detiene todo y elimina volumenes (DESTRUCTIVO: borra la base de datos)
	$(COMPOSE) down -v
