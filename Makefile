.PHONY: up down build migrate fresh test shell logs help

help:
	@echo "up      - sobe os containers"
	@echo "down    - derruba os containers"
	@echo "migrate - roda as migrations"
	@echo "test    - roda os testes"

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

test:
	docker compose exec app php artisan test

shell:
	docker compose exec app sh

logs:
	docker compose logs -f app