DC = docker compose
PHP = $(DC) exec php
PHP_RUN = $(DC) run --rm php
CONSOLE = $(PHP) php bin/console

.DEFAULT_GOAL := help

.PHONY: help install build start stop restart down clean \
        composer-install db-start db-wait db-create migrate \
        migration cache-clear shell console logs ps db-reset

help: ## Affiche les commandes disponibles
	@echo ""
	@echo "e-Radiologie Backend"
	@echo "===================="
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'
	@echo ""

install: build db-start db-wait composer-install db-create migrate start cache-clear ## Installe et démarre complètement le projet
	@echo ""
	@echo "Projet installé avec succès."
	@echo "Backend : http://localhost:18740"
	@echo ""

build: ## Construit les images Docker
	$(DC) build

start: ## Démarre tous les conteneurs
	$(DC) up -d

stop: ## Arrête les conteneurs
	$(DC) stop

restart: stop start ## Redémarre les conteneurs

down: ## Arrête et supprime les conteneurs
	$(DC) down

clean: ## Supprime les conteneurs et les volumes
	$(DC) down -v --remove-orphans

ps: ## Affiche l'état des conteneurs
	$(DC) ps

logs: ## Affiche les logs
	$(DC) logs -f

db-start: ## Démarre uniquement PostgreSQL
	$(DC) up -d database

db-wait: ## Attend que PostgreSQL soit disponible
	@echo "Attente de PostgreSQL..."
	@until $(DC) exec -T database sh -c 'pg_isready -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"' > /dev/null 2>&1; do \
		echo "PostgreSQL n'est pas encore prêt..."; \
		sleep 2; \
	done
	@echo "PostgreSQL est prêt."

composer-install: ## Installe les dépendances Composer
	$(PHP_RUN) composer install

db-create: ## Crée la base de données si nécessaire
	$(PHP_RUN) php bin/console doctrine:database:create --if-not-exists

migrate: ## Exécute les migrations Doctrine
	$(PHP_RUN) php bin/console doctrine:migrations:migrate --no-interaction

migration: ## Génère une nouvelle migration Doctrine
	$(CONSOLE) doctrine:migrations:diff

cache-clear: ## Vide le cache Symfony
	$(CONSOLE) cache:clear

shell: ## Ouvre un shell dans le conteneur PHP
	$(PHP) sh

console: ## Affiche les commandes Symfony disponibles
	$(CONSOLE) list

db-reset: ## Supprime, recrée et migre complètement la base de données
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	@echo "Base de données réinitialisée."
