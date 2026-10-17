.PHONY: up down fresh test lint stan check logs shell

up:            ## Start the stack on http://localhost:8000
	docker compose up -d --build
	docker compose exec app php artisan migrate --force

down:          ## Stop everything
	docker compose down

fresh:         ## Rebuild the database with demo data
	docker compose exec app php artisan migrate:fresh --seed --force

test:          ## Run the test suite
	php artisan test

lint:          ## Fix code style
	vendor/bin/pint

stan:          ## Static analysis
	vendor/bin/phpstan analyse

check: lint stan test  ## Everything CI runs

logs:          ## Follow the queue worker
	docker compose logs -f queue

shell:         ## Shell into the app container
	docker compose exec app sh
