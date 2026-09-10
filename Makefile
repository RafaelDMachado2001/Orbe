DC := docker compose
API := $(DC) exec -T api

.DEFAULT_GOAL := help

.PHONY: help
help: ## Lista os comandos disponíveis
	@echo "Comandos disponíveis:"
	@echo "  setup       Cria o .env quando necessário, constrói e sobe os serviços"
	@echo "  up          Sobe os serviços"
	@echo "  down        Para os serviços, mantendo os volumes"
	@echo "  build       Reconstrói as imagens sem cache"
	@echo "  ps          Mostra o status dos serviços"
	@echo "  logs        Acompanha os logs"
	@echo "  shell       Abre um shell na API"
	@echo "  web-shell   Abre um shell no frontend"
	@echo "  migrate     Executa as migrations"
	@echo "  fresh       Recria o banco e executa o seeder"
	@echo "  seed        Executa apenas o seeder"
	@echo "  test        Executa os testes PHP"
	@echo "  pint        Formata o PHP"
	@echo "  stan        Executa a análise estática"
	@echo "  tsc         Verifica os tipos do frontend"
	@echo "  lint        Executa o ESLint"
	@echo "  check       Executa a esteira de qualidade"
	@echo "  artisan     Executa um comando Artisan (ex.: make artisan cmd=route:list)"

.PHONY: setup
setup: ## Cria o .env quando necessário, constrói e sobe os serviços
	$(ENV_SETUP)
	$(DC) up -d --build

.PHONY: up
up: ## Sobe todos os serviços
	$(DC) up -d

.PHONY: down
down: ## Para os serviços, mantendo os volumes
	$(DC) down

.PHONY: build
build: ## Reconstrói as imagens sem cache
	$(DC) build --no-cache

.PHONY: ps
ps: ## Mostra o status dos serviços
	$(DC) ps

.PHONY: logs
logs: ## Acompanha os logs de todos os serviços
	$(DC) logs -f --tail=100

.PHONY: shell
shell: ## Abre um shell no container da API
	$(DC) exec api bash

.PHONY: web-shell
web-shell: ## Abre um shell no container do frontend
	$(DC) exec web sh

.PHONY: migrate
migrate: ## Executa as migrations pendentes
	$(API) php artisan migrate --force

.PHONY: fresh
fresh: ## Recria o banco e executa o seeder
	$(API) php artisan migrate:fresh --seed --force

.PHONY: seed
seed: ## Executa apenas o seeder
	$(API) php artisan db:seed --force

.PHONY: test
test: ## Executa os testes PHP
	$(API) php artisan test

.PHONY: pint
pint: ## Formata o código PHP
	$(API) ./vendor/bin/pint

.PHONY: stan
stan: ## Executa a análise estática
	$(API) ./vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: tsc
tsc: ## Verifica os tipos do frontend
	$(DC) exec -T web npx tsc --noEmit

.PHONY: lint
lint: ## Executa o ESLint no frontend
	$(DC) exec -T web npm run lint

.PHONY: check
check: pint stan test tsc lint ## Executa a esteira de qualidade

.PHONY: artisan
artisan: ## Executa um comando Artisan
	$(API) php artisan $(cmd)

ifeq ($(OS),Windows_NT)
ENV_SETUP = powershell -NoProfile -Command "if (!(Test-Path .env)) { Copy-Item .env.example .env }"
else
ENV_SETUP = test -f .env || cp .env.example .env
endif
