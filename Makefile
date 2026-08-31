DC := docker compose
API := $(DC) exec -T api

.DEFAULT_GOAL := help

.PHONY: help
help: ## Lista os comandos disponiveis
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: ## Primeira execucao: copia .env, sobe tudo e popula a base
	@test -f .env || cp .env.example .env
	@sed -i "s/^UID=.*/UID=$$(stat -c '%u' .)/; s/^GID=.*/GID=$$(stat -c '%g' .)/" .env
	$(DC) build
	$(DC) up -d
	@echo "\n  API  -> http://localhost:8000/api/v1"
	@echo "  Web  -> http://localhost:5173\n"

.PHONY: up
up: ## Sobe todos os servicos
	$(DC) up -d

.PHONY: down
down: ## Derruba os servicos (mantem os volumes)
	$(DC) down

.PHONY: build
build: ## Reconstroi as imagens
	$(DC) build --no-cache

.PHONY: ps
ps: ## Status dos servicos
	$(DC) ps

.PHONY: logs
logs: ## Segue o log de todos os servicos
	$(DC) logs -f --tail=100

.PHONY: shell
shell: ## Abre um shell no container da API
	$(DC) exec api bash

.PHONY: web-shell
web-shell: ## Abre um shell no container do frontend
	$(DC) exec web sh

.PHONY: migrate
migrate: ## Roda as migrations pendentes
	$(API) php artisan migrate --force

.PHONY: fresh
fresh: ## Recria a base do zero e roda o seeder de demonstracao
	$(API) php artisan migrate:fresh --seed --force

.PHONY: seed
seed: ## Roda apenas o seeder
	$(API) php artisan db:seed --force

.PHONY: test
test: ## Roda a suite de testes (Pest)
	$(API) php artisan test

.PHONY: pint
pint: ## Formata o codigo PHP
	$(API) ./vendor/bin/pint

.PHONY: stan
stan: ## Analise estatica (PHPStan/Larastan)
	$(API) ./vendor/bin/phpstan analyse --memory-limit=1G

.PHONY: tsc
tsc: ## Checagem de tipos do frontend
	$(DC) exec -T web npx tsc --noEmit

.PHONY: lint
lint: ## ESLint no frontend
	$(DC) exec -T web npm run lint

.PHONY: check
check: pint stan test tsc lint ## Roda toda a esteira de qualidade

.PHONY: artisan
artisan: ## Executa um comando artisan: make artisan cmd="route:list"
	$(API) php artisan $(cmd)
