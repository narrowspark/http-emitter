#!/usr/bin/env bash

# Create logs dir
mkdir -p build/logs
#!/bin/bash

set +e
bash -e <<TRY
    if [[ "$PHPSTAN" = true ]]; then
        ./vendor/bin/phpstan analyse -c phpstan.neon -l 5 src
    fi

    if [[ "$PHPUNIT" = true && "$SEND_COVERAGE" = true ]]; then
        ./vendor/bin/php-cs-fixer fix --config=.php_cs --verbose --diff --dry-run
        ./vendor/bin/phpunit -c phpunit.xml.dist --verbose --coverage-text="php://stdout" --coverage-clover=coverage.xml;
    fi
TRY
if [ $? -ne 0 ]; then
  exit 1
fi
