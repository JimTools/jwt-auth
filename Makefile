.DEFAULT_GOAL := help

help:
	@echo ""
	@echo "Available tasks:"
	@echo "    test    Run all tests and generate coverage"
	@echo "    watch   Run all tests and coverage when a source file is upaded"
	@echo "    lint    Run only linter and code style checker"
	@echo "    unit    Run unit tests and generate coverage"
	@echo "    static  Run static analysis"
	@echo "    vendor  Install dependencies"
	@echo "    clean   Remove vendor and composer.lock"
	@echo ""

vendor: $(wildcard composer.lock)
	composer install --prefer-dist

lint: vendor
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix --dry-run

unit: vendor
	XDEBUG_MODE=coverage vendor/bin/phpunit --display-warnings --exclude-group=rector

static: vendor
	vendor/bin/phpstan --error-format=github

watch: vendor
	find . -name "*.php" -not -path "./vendor/*" -o -name "*.json" -not -path "./vendor/*" | entr -c make test

test: lint unit static

clean:
	rm -rf vendor
	rm composer.lock

.PHONY: help lint unit watch test clean
