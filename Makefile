.PHONY: setup up down logs test artisan composer npm restart-workers fresh

# First run: build images, install dependencies, migrate and seed.
setup:
	@test -f .env || cp .env.example .env
	docker compose build
	docker compose run --rm --no-deps app composer install
	docker compose run --rm --no-deps node npm install
	docker compose up -d mysql redis
	docker compose run --rm app php artisan key:generate
	docker compose run --rm app php artisan migrate --seed --force
	docker compose up -d

up:
	docker compose up -d --build
down:
	docker compose down
logs:
	docker compose logs -f queue scheduler reverb
test:
	docker compose exec app php artisan test
fresh:
	docker compose exec app php artisan migrate:fresh --seed
artisan:
	docker compose exec app php artisan $(cmd)
composer:
	docker compose exec app composer $(cmd)
npm:
	docker compose run --rm node npm $(cmd)
restart-workers:
	docker compose restart queue scheduler reverb
