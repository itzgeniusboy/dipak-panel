#!/bin/bash
# One-command local host for checking the panel on this device.
#
#   bash tools/host.sh            start everything (Ctrl-C to stop, .env restored)
#   bash tools/host.sh --restore  restore .env after an unclean kill
#
# Starts MariaDB + the PHP built-in server inside proot debian, points the app
# at a LOCAL seeded database, and restores the real .env when you stop it.

set -u
P=/data/data/com.termux/files/home/opencode/dipak-panel
T=$P/tools
SCHEMA=/data/data/com.termux/files/usr/tmp/opencode/schema.sql
PORT=8080
DBNAME=dipak_panel
DBUSER=paneluser
DBPASS=panelpass
ENV=$P/.env
ENV_BAK=$T/.env.hostbak
PIDFILE=$T/host.pid
OWNER_PASS=secret123

restore_env() {
  if [ -f "$ENV_BAK" ]; then cp "$ENV_BAK" "$ENV"; rm -f "$ENV_BAK"; fi
}

# --- safety net after a Ctrl-C / killed shell ---------------------------------
case "${1:-}" in
  --restore)
    restore_env
    echo "restored $ENV"
    exit 0
    ;;
  --stop)
    if [ -f "$PIDFILE" ]; then
      PID=$(cat "$PIDFILE" 2>/dev/null || true)
      [ -n "${PID:-}" ] && kill -TERM "$PID" 2>/dev/null && echo "stopping pid $PID"
      for _ in $(seq 1 10); do [ -f "$PIDFILE" ] || break; sleep 1; done
    else
      echo "no running session"
    fi
    restore_env
    if command -v mariadbd >/dev/null 2>&1; then
      mariadb -u root --socket=/run/mysqld/mysqld.sock -e 'SHUTDOWN' 2>/dev/null
    else
      proot-distro login debian -- bash -c \
        "mariadb -u root --socket=/run/mysqld/mysqld.sock -e 'SHUTDOWN' 2>/dev/null" >/dev/null 2>&1
    fi
    rm -f "$PIDFILE"
    echo "stopped, $ENV restored"
    exit 0
    ;;
esac

# --- re-exec inside proot if we were called from Termux ----------------------
if ! command -v mariadbd >/dev/null 2>&1; then
  if [ -f "$ENV_BAK" ]; then
    echo "! previous session left a test .env behind — restoring first"
    restore_env
  fi
  exec proot-distro login debian -- bash "$P/tools/host.sh" "$@"
fi

# inner path: recover from a previous unclean kill before we touch anything
if [ -f "$ENV_BAK" ]; then
  echo "! stale backup from an unclean stop — restoring"
  restore_env
fi
echo $$ >"$PIDFILE"

mkdir -p /run/mysqld
[ -d "$T" ] || mkdir -p "$T"

# --- 1. database --------------------------------------------------------------
if ! mariadb -u root --socket=/run/mysqld/mysqld.sock -e 'SELECT 1' >/dev/null 2>&1; then
  echo "[1/3] starting MariaDB..."
  mariadbd --user=root --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock \
    --port=3306 --bind-address=127.0.0.1 >"$T/mariadb.log" 2>&1 &
  for _ in $(seq 1 40); do
    mariadb -u root --socket=/run/mysqld/mysqld.sock -e 'SELECT 1' >/dev/null 2>&1 && break
    sleep 1
  done
else
  echo "[1/3] MariaDB already running"
fi

mariadb -u root --socket=/run/mysqld/mysqld.sock -e "CREATE DATABASE IF NOT EXISTS \`$DBNAME\`"
mariadb -u root --socket=/run/mysqld/mysqld.sock -e \
  "CREATE USER IF NOT EXISTS '$DBUSER'@'localhost' IDENTIFIED BY '$DBPASS';
   CREATE USER IF NOT EXISTS '$DBUSER'@'127.0.0.1' IDENTIFIED BY '$DBPASS';
   GRANT ALL ON \`$DBNAME\`.* TO '$DBUSER'@'localhost';
   GRANT ALL ON \`$DBNAME\`.* TO '$DBUSER'@'127.0.0.1';
   FLUSH PRIVILEGES;"

TABLES=$(mariadb -u root --socket=/run/mysqld/mysqld.sock -N -e "SHOW TABLES" "$DBNAME" | wc -l)
if [ "$TABLES" -eq 0 ] && [ -f "$SCHEMA" ]; then
  echo "[2/3] loading schema..."
  mariadb -u root --socket=/run/mysqld/mysqld.sock "$DBNAME" <"$SCHEMA"
else
  echo "[2/3] schema present ($TABLES tables)"
fi

# --- 2. seed a usable login ---------------------------------------------------
HASH=$(php -r "echo password_hash(md5('XquxmymXDtWRA66D$OWNER_PASS'), PASSWORD_DEFAULT, ['cost'=>8]);")
mariadb -u root --socket=/run/mysqld/mysqld.sock "$DBNAME" -e \
  "INSERT INTO users (username,password,fullname,level,status,expiration_date,telegram_chat_id)
   VALUES ('owner','$HASH','Panel Owner',1,1,'2031-12-31 23:59:59','123456789')
   ON DUPLICATE KEY UPDATE password='$HASH', level=1, status=1, expiration_date='2031-12-31 23:59:59';"

# --- 3. point .env at the local DB (backed up, restored on stop) --------------
if [ ! -f "$ENV_BAK" ]; then cp "$ENV" "$ENV_BAK"; fi
sed -i "s#^database.default.hostname.*#database.default.hostname = '127.0.0.1'#; \
        s#^database.default.database.*#database.default.database = '$DBNAME'#; \
        s#^database.default.username.*#database.default.username = '$DBUSER'#; \
        s#^database.default.password.*#database.default.password = '$DBPASS'#; \
        s#^CI_ENVIRONMENT.*#CI_ENVIRONMENT = 'production'#" "$ENV"

# --- 4. web server ------------------------------------------------------------
command -v php >/dev/null 2>&1 || { echo "php not found"; exit 1; }
echo "[3/3] starting PHP on port $PORT"

cleanup() {
  echo
  echo "stopping — restoring .env and shutting MariaDB down"
  [ -n "${PHPPID:-}" ] && kill "$PHPPID" 2>/dev/null
  restore_env
  rm -f "$PIDFILE"
  mariadb -u root --socket=/run/mysqld/mysqld.sock -e 'SHUTDOWN' 2>/dev/null
}
trap cleanup EXIT INT TERM HUP

php -S 127.0.0.1:$PORT -t "$P" "$P/index.php" >"$T/php.log" 2>&1 &
PHPPID=$!
sleep 3

echo
echo "=================================================="
echo "  Panel running:  http://localhost:$PORT"
echo "  Login:          owner / $OWNER_PASS"
echo "  Stop:           Ctrl-C  (real .env is restored)"
echo "=================================================="
echo
wait $PHPPID
