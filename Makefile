.PHONY: setup mysql db api queue web test build
setup:  ## one-time: Laravel skeleton in ./api + web dependencies
	./scripts/bootstrap-api.sh
	cd web && npm install
mysql:  ## create databases and user (needs MySQL running and its root password)
	mysql -u root -p < scripts/create-databases.sql
db:     ## create tables and demo data
	cd api && php artisan migrate --force && php artisan db:seed --class=DemoSeeder --force
api:    ## http://localhost:8000
	cd api && php artisan serve
queue:  ## unit/global fan-out worker (needed for non-peer sends)
	cd api && php artisan queue:work
web:    ## http://localhost:5173 (demo mode, no backend)
	cd web && npm run dev
test:
	cd api && php artisan test
build:
	cd web && npm run build
