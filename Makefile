.DEFAULT_GOAL := help

-include Makefile.setup

dc = docker compose
composer = $(dc) run -eXDEBUG_MODE=off --rm --no-deps php composer
php = $(dc) run --rm --no-deps php php

.PHONY: help
help: # print documentation from comments: https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
	@egrep -h '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort -n | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-24s\033[0m %s\n", $$1, $$2}'

.PHONY: vendor
vendor: composer.lock composer.json ## install composer dependencies
	@$(composer) install --no-progress

composer.lock:
	@$(composer) install --no-progress

.PHONY: test
test: ## run tests
	@$(php) vendor/bin/phpunit --testdox

.PHONY: static
static: ## run static analysis
	@$(php) vendor/bin/phpstan -v

.PHONY: fix
fix: ## fix code style
	@$(dc) run -ePHP_CS_FIXER_IGNORE_ENV=1 --rm --no-deps php vendor/bin/php-cs-fixer fix

.PHONY: git-enable-hooks
git-enable-hooks: ## set git core.hookspath, exec-bit
	@git config core.hookspath .git-hooks
	@chmod +x .git-hooks/*

.PHONY: git-disable-hooks
git-disable-hooks: ## unset core.hookspath
	@git config --unset core.hookspath










