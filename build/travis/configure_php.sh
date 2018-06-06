#!/usr/bin/env bash

if [[ "$REMOVE_XDEBUG" = true ]]; then
  phpenv config-rm xdebug.ini;
fi

if [[ "$INSTALL_SWOOLE" = true ]]; then
    pecl install swoole << EOF
`#enable debug/trace log support? [no] :`
`#enable sockets supports? [no] :`y
`#enable openssl support? [no] :`
`#enable http2 support? [no] :`
`#enable async-redis support? [no] :`
`#enable mysqlnd support? [no] :`
`#enable postgresql coroutine client support? [no] :`
EOF
fi

echo date.timezone = Europe/Berlin >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
