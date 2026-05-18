.PHONY: up down logs sh migrate fresh test pint stan seed

up:
	docker compose up -d --build
	docker compose exec app composer install --no-interaction
	docker compose exec app php artisan key:generate --force

down:
	docker compose down

logs:
	docker compose logs -f --tail=200

sh:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

test:
	docker compose exec app php artisan test --parallel

pint:
	docker compose exec app vendor/bin/pint

stan:
	docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M

seed:
	docker compose exec app php artisan db:seed
