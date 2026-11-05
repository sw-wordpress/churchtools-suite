#!/usr/bin/env bash
# Starts/stops a private, throw-away MariaDB instance for the WordPress integration tests
# (never touches the development site's database).
# Usage: bin/test-db.sh start|stop|status   (data dir: $CTS_TEST_DB_DIR, default /tmp/cts-test-db)
set -euo pipefail

DIR="${CTS_TEST_DB_DIR:-/tmp/cts-test-db}"
PORT="${CTS_TEST_DB_PORT:-33080}"
SOCKET="$DIR/mysql.sock"

case "${1:-}" in
  start)
    if [ -S "$SOCKET" ] && mariadb-admin --socket="$SOCKET" ping >/dev/null 2>&1; then echo "already running"; exit 0; fi
    rm -rf "$DIR"; mkdir -p "$DIR/data"
    mariadb-install-db --no-defaults --datadir="$DIR/data" --auth-root-authentication-method=normal >/dev/null
    nohup mariadbd --no-defaults --datadir="$DIR/data" --socket="$SOCKET" --port="$PORT" --bind-address=127.0.0.1 \
      --pid-file="$DIR/mysql.pid" --skip-name-resolve --innodb-flush-log-at-trx-commit=0 \
      --character-set-server=utf8mb4 --sql-mode=STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO \
      >"$DIR/mysqld.log" 2>&1 &
    for _ in $(seq 1 60); do
      mariadb-admin --socket="$SOCKET" ping >/dev/null 2>&1 && break; sleep 0.5
    done
    mariadb --socket="$SOCKET" -uroot -e "CREATE USER IF NOT EXISTS 'cts'@'%' IDENTIFIED BY 'cts'; GRANT ALL ON *.* TO 'cts'@'%'; CREATE USER IF NOT EXISTS 'cts'@'localhost' IDENTIFIED BY 'cts'; GRANT ALL ON *.* TO 'cts'@'localhost'; FLUSH PRIVILEGES;"
    mariadb --socket="$SOCKET" -uroot -e "CREATE DATABASE IF NOT EXISTS cts_wp_tests CHARACTER SET utf8mb4"
    echo "started on 127.0.0.1:$PORT (user cts / cts, strict SQL mode)"
    ;;
  stop)
    [ -f "$DIR/mysql.pid" ] && kill "$(cat "$DIR/mysql.pid")" 2>/dev/null || true
    echo stopped
    ;;
  status)
    mariadb-admin --socket="$SOCKET" ping 2>&1 || true
    ;;
  *) echo "usage: $0 start|stop|status"; exit 1;;
esac
