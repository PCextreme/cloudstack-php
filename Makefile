PHPUNIT=./vendor/bin/phpunit
PHPCS=./vendor/bin/phpcs
PHPCBF=./vendor/bin/phpcbf
PHPSTAN=./vendor/phpstan/phpstan/bin/phpstan
INFECTION=./vendor/infection/infection/bin/infection

.PHONY: all

# Default target when just running 'make'
all: analyze test

vendor: composer.json composer.lock
	composer install

build/logs:
	mkdir -p build/logs

$(PHPUNIT): vendor
$(PHPCS): vendor
$(PHPCBF): vendor
$(PHPSTAN): vendor
$(INFECTION): vendor


#
#  T E S T S
#

.PHONY: test test-unit test-infection

test: test-unit test-infection

test-unit: $(PHPUNIT) vendor build/logs
	$(PHPUNIT) --coverage-text

test-infection: $(INFECTION) vendor build/logs
	$(INFECTION) --threads=4 --only-covered --min-covered-msi=50


#
#  A N A L Y S I S
#

.PHONY: analyze cs-fix cs-check phpstan validate

analyze: cs-check phpstan validate

cs-fix: $(PHPCBF)
	$(PHPCBF) src --standard=phpcs.ruleset.xml

cs-check: $(PHPCS)
	$(PHPCS) src --standard=phpcs.ruleset.xml

phpstan: $(PHPSTAN)
	$(PHPSTAN) analyze src --level=3

validate:
	composer validate --strict
