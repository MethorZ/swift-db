.PHONY: help start stop restart logs shell db-shell test test-unit test-integration cs-check cs-fix analyze quality clean

# Colors
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)

## Help
help: ## Show this help
	@echo ''
	@echo 'Usage:'
	@echo '  ${YELLOW}make${RESET} ${GREEN}<target>${RESET}'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  ${YELLOW}%-20s${GREEN}%s${RESET}\n", $$1, $$2}' $(MAKEFILE_LIST)

## Docker
start: ## Start Docker containers
	docker-compose up -d
	@echo "Waiting for database to be ready..."
	@sleep 5

stop: ## Stop Docker containers
	docker-compose down

restart: stop start ## Restart Docker containers

logs: ## View Docker logs
	docker-compose logs -f

shell: ## Open PHP shell
	docker-compose exec php bash

db-shell: ## Open MySQL shell
	docker-compose exec database mysql -u methorz -pmethorz methorz_test

## Testing
test: test-unit test-integration ## Run all tests

test-unit: ## Run unit tests only
	vendor/bin/phpunit --testsuite=unit

test-integration: start ## Run integration tests (starts Docker if needed)
	vendor/bin/phpunit --testsuite=integration
	$(MAKE) stop

## Quality
cs-check: ## Check code style
	vendor/bin/phpcs

cs-fix: ## Fix code style
	vendor/bin/phpcbf

analyze: ## Run static analysis
	vendor/bin/phpstan analyze --memory-limit=512M

quality: cs-fix cs-check analyze ## Run all quality checks

## Cleanup
clean: stop ## Clean up Docker volumes and cache
	docker-compose down -v
	rm -rf .phpcs.cache .phpstan.cache .phpunit.cache

