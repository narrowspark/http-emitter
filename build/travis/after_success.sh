#!/usr/bin/env bash

if [[ "$SEND_COVERAGE" = true ]]; then
    bash <(curl -s https://codecov.io/bash)
fi
