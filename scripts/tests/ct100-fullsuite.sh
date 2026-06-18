#!/usr/bin/env bash
# ct100-fullsuite.sh — nightly full-suite Pest contra MySQL REAL no CT 100.
# FV-F3 (plano SDD 2026-06-12) — corrida DIAGNOSTICA: 1o numero real da suite
# inteira; NUNCA vira required. Artefatos: junit.xml + summary.json + run.log.
#
# Instalado em: /opt/oimpresso-fullsuite/ct100-fullsuite.sh (COPIA deste arquivo
# versionado — atualizar la apos merge; ver RUNBOOK-ct100-fullsuite.md).
# Cron root: 0 2 * * * (host TZ America/Sao_Paulo => 02:00 BRT).
#
# Guard-rails (ADR 0062 — CT 100 isolado, NUNCA prod):
#   - DB de teste DEDICADA (recriada a cada run) no container mysql-workers;
#   - usuario MySQL proprio com GRANT SOMENTE no schema *_test;
#   - aborta se DB_DATABASE nao terminar em _test;
#   - creds vivem APENAS em /opt/oimpresso-fullsuite/.env.local (chmod 600).
set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

BASE="${FULLSUITE_BASE:-/opt/oimpresso-fullsuite}"
CODE="$BASE/code"
RUNS="$BASE/runs"
IMAGE="${FULLSUITE_IMAGE:-oimpresso/mcp:latest}"     # PHP 8.4 ZTS + pdo_mysql
NET="${FULLSUITE_NET:-docker-host_default}"          # rede onde mysql-workers resolve por DNS
MYSQL_CONTAINER="${FULLSUITE_MYSQL_CONTAINER:-mysql-workers}"
REPO_URL="${FULLSUITE_REPO:-https://github.com/wagnerra23/oimpresso.com.git}"
TIMEOUT_S="${FULLSUITE_TIMEOUT:-14400}"              # hard-kill 4h
KEEP_RUNS="${FULLSUITE_KEEP_RUNS:-14}"
ENV_LOCAL="$BASE/.env.local"

# lock — cron + run manual nunca sobrepoem
exec 9>"$BASE/.lock"
flock -n 9 || { echo "ct100-fullsuite: outro run em andamento (lock $BASE/.lock) — saindo"; exit 0; }

[ -f "$ENV_LOCAL" ] || { echo "FATAL: $ENV_LOCAL ausente (DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD)"; exit 1; }
# shellcheck disable=SC1090
source "$ENV_LOCAL"

case "$DB_DATABASE" in
  *_test) ;;
  *) echo "FATAL: DB_DATABASE '$DB_DATABASE' nao termina em _test — protecao anti-prod (ADR 0062)"; exit 1 ;;
esac

TS="$(date +%Y%m%d-%H%M%S)"
RUN_DIR="$RUNS/$TS"
mkdir -p "$RUN_DIR"
exec > >(tee -a "$RUN_DIR/run.log") 2>&1
echo "=== ct100-fullsuite $TS (db=$DB_DATABASE host=$DB_HOST image=$IMAGE) ==="

# container PHP descartavel (entrypoint da imagem e octane — sempre sobrescrever)
dphp() {
  docker run --rm --network "$NET" \
    -v "$CODE":/workspace -v "$RUN_DIR":/artifacts \
    -w /workspace --entrypoint php "$IMAGE" "$@"
}

echo "--- [1/7] sync codigo (origin/main)"
if [ ! -d "$CODE/.git" ]; then
  git clone --depth 1 "$REPO_URL" "$CODE"
else
  git -C "$CODE" fetch --depth 1 origin main
  git -C "$CODE" reset --hard FETCH_HEAD
  git -C "$CODE" clean -fd --quiet || true
fi
SHA="$(git -C "$CODE" rev-parse --short HEAD)"
echo "$SHA" > "$RUN_DIR/sha.txt"
echo "HEAD: $SHA"

echo "--- [2/7] composer install (imagem composer:2 — myfatoorah/* e source-only, exige git)"
# A imagem oimpresso/mcp nao tem composer nem git; composer:2 tem ambos. So
# baixa deps (--no-scripts): nenhum codigo do app roda; runtime real e o mcp.
mkdir -p "$BASE/.composer-cache" \
         "$CODE/storage/framework/cache" "$CODE/storage/framework/sessions" \
         "$CODE/storage/framework/views" "$CODE/storage/logs" "$CODE/bootstrap/cache"
docker run --rm --network "$NET" -v "$CODE":/app -w /app \
  -v "$BASE/.composer-cache":/tmp/composer-cache -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
  composer:2 composer install --no-interaction --prefer-dist --no-progress --no-scripts --ignore-platform-reqs

echo "--- [3/7] recria DB de teste dedicada ($DB_DATABASE)"
docker exec -i "$MYSQL_CONTAINER" sh -c 'MYSQL_PWD=$(cat /run/secrets/mysql_root) exec mysql -uroot' <<SQL
DROP DATABASE IF EXISTS \`$DB_DATABASE\`;
CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'%';
-- US-GOV-020 Frente C: o dump database/schema/mysql-schema.sql tem triggers com DEFINER
-- de PROD (ex trg_mcp_audit_log_no_update, append-only Art 9). O migrate:fresh do
-- RefreshDatabase recarrega o dump COMO ESTE USUARIO (nao-root): sem estes 2, falha em
-- ERROR 1419 (binlog) e depois 1227 (SET_USER_ID/DEFINER) e aborta o load em 188/364
-- tabelas -> schema incompleto -> cascata Base-table-not-found pros testes seguintes.
-- Provado no CT100: com os 2, o load do fullsuite vai de 188->377 tabelas / 0->4 triggers.
-- log_bin_trust = GLOBAL (root) p/ funcoes/triggers deterministicos sob binlog; SET_USER_ID
-- = privilegio dinamico MySQL 8 p/ criar objeto com DEFINER alheio (DB de TESTE dedicada).
SET GLOBAL log_bin_trust_function_creators=1;
GRANT SET_USER_ID ON *.* TO '$DB_USERNAME'@'%';
FLUSH PRIVILEGES;
SQL

echo "--- [4/7] .env testing + key + discover"
cat > "$CODE/.env" <<EOF
APP_NAME=oimpresso-fullsuite
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
LOG_CHANNEL=stderr
DB_CONNECTION=mysql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array
BCRYPT_ROUNDS=4
TELESCOPE_ENABLED=false
SCOUT_DRIVER=null
MCP_TOOLS_EXPOSED=false
EOF
dphp artisan key:generate --force
dphp artisan package:discover --ansi

echo "--- [5/7] migrate (schema baseline) + seed minimo multi-tenant"
# A imagem mcp nao tem o CLI mysql que `artisan migrate` invoca pra carregar o
# schema dump ("Loading stored database schemas" → sh: mysql: not found).
# Preload manual do baseline (820 migrations ja registradas no dump) usando o
# client do proprio container mysql; o migrate entao so roda migrations novas.
docker exec -i "$MYSQL_CONTAINER" sh -c "MYSQL_PWD=\$(cat /run/secrets/mysql_root) exec mysql -uroot $DB_DATABASE" \
  < "$CODE/database/schema/mysql-schema.sql"
dphp artisan migrate --force
# seed identico ao canon CI (.github/actions/pest-mysql-setup): biz=1 fixture + biz=2 Tier 0
cat > "$CODE/storage/fullsuite-seed.php" <<'PHPEOF'
<?php
use Illuminate\Support\Facades\DB;
$curId = optional(DB::table('currencies')->first())->id ?? 1;
if (! DB::table('business')->where('id', 1)->exists()) {
    $uid = DB::table('users')->insertGetId(['first_name'=>'CI','username'=>'ci_admin','password'=>bcrypt('ci'),'created_at'=>now(),'updated_at'=>now()]);
    $bid = DB::table('business')->insertGetId(['name'=>'CI Biz','currency_id'=>$curId,'owner_id'=>$uid,'stop_selling_before'=>0,'weighing_scale_setting'=>'','certificado'=>'','officeimpresso_numerodemaquinas'=>0,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('users')->where('id', $uid)->update(['business_id'=>$bid]);
    echo 'seed biz='.$bid.' user='.$uid.PHP_EOL;
}
if (! DB::table('business')->where('id', 2)->exists()) {
    $uid2 = DB::table('users')->insertGetId(['first_name'=>'CI Biz2','username'=>'ci_admin_b2','password'=>bcrypt('ci'),'created_at'=>now(),'updated_at'=>now()]);
    DB::table('business')->insert(['id'=>2,'name'=>'CI Biz 2','currency_id'=>$curId,'owner_id'=>$uid2,'stop_selling_before'=>0,'weighing_scale_setting'=>'','certificado'=>'','officeimpresso_numerodemaquinas'=>0,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('users')->where('id', $uid2)->update(['business_id'=>2]);
    echo 'seed biz2=2 user='.$uid2.PHP_EOL;
}
DB::statement('SET FOREIGN_KEY_CHECKS=0');
$bizId = optional(DB::table('business')->first())->id;
if ($bizId && ! \Modules\Financeiro\Models\ContaBancaria::query()->where('business_id', $bizId)->exists()) {
    \Modules\Financeiro\Models\ContaBancaria::create([
        'business_id'=>$bizId,'account_id'=>999001,'agencia'=>'0001','carteira'=>'0',
        'beneficiario_documento'=>'00000000000000','beneficiario_razao_social'=>'CI Test','saldo_cached'=>0,
    ]);
    echo 'conta criada biz='.$bizId.PHP_EOL;
}
if ($bizId && ! DB::table('business_locations')->where('business_id', $bizId)->exists()) {
    DB::table('business_locations')->insert(['business_id'=>$bizId,'name'=>'Matriz CI','country'=>'BR','state'=>'SP','city'=>'SP','zip_code'=>'0','created_at'=>now(),'updated_at'=>now()]);
    echo 'location criada'.PHP_EOL;
}
if ($bizId && ! DB::table('contacts')->where('business_id', $bizId)->where('type', '!=', 'lead')->exists()) {
    DB::table('contacts')->insert(['business_id'=>$bizId,'type'=>'customer','name'=>'Cliente CI','contact_id'=>'CO0001','created_by'=>1,'is_default'=>1,'created_at'=>now(),'updated_at'=>now()]);
    echo 'contact criado'.PHP_EOL;
}
PHPEOF
dphp artisan db:seed --class="Database\\Seeders\\CurrenciesTableSeeder" --force || true
dphp artisan db:seed --class="Database\\Seeders\\PermissionsTableSeeder" --force || true
dphp artisan tinker --execute="require base_path('storage/fullsuite-seed.php');"

echo "--- [6/7] pest full-suite (diagnostico — fail e DADO, nao erro; timeout ${TIMEOUT_S}s)"
docker rm -f oimpresso-fullsuite-run >/dev/null 2>&1 || true
# Arquivo com uses(TestCase) file-level dentro de pasta ja vinculada no
# tests/Pest.php ->in('Feature') MATA o loader da suite inteira (exit 255)
# antes de executar 1 teste — 4 casos conhecidos em tests/Feature (2026-06-12).
# Diagnostico nao pode morrer com isso: poe o arquivo de lado SO NO CLONE
# descartavel, REGISTRA em loader-blockers.txt (vira dado pro triage Q2) e
# re-tenta. Consertar os arquivos e das lanes de burn-down, nao desta.
PEST_EXIT=0
for attempt in $(seq 1 12); do
  PEST_EXIT=0
  # C1 (triage Q2 2026-06-13): phpunit.xml <env DB_CONNECTION=sqlite> NAO tem force=,
  # entao o PHPUnit so seta a var se ela ainda NAO existir no ambiente. Sem estes -e,
  # o pest rodava contra sqlite :memory: VAZIO (sem schema/seed) — ~880 'no such table'
  # / 'near MODIFY syntax error' (DDL MySQL-only). Passando DB_* como env REAL do
  # container, o <env> sqlite e ignorado e o pest usa o MySQL seedado (mysql-schema.sql
  # + migrate + seed biz=1/biz=2 dos passos 3-5). ADR 0101 (testes MySQL real, nao sqlite).
  #
  # US-GOV-018 Frente A (A.1 + A.2):
  #  - A.1: a imagem oimpresso/mcp nao tem o binario CLI mysql que o migrate:fresh/
  #    schema:load do RefreshDatabase invoca em "Loading stored database schemas" pra
  #    recarregar o dump => 72x "mysql: not found" => schema some mid-run. O Dockerfile
  #    da imagem ja ganhou mariadb-client (fix duravel); este apk-add e o fallback
  #    imediato ate o rebuild+deploy da imagem ao CT 100 (no-op se ja presente).
  #  - A.2 (FULLSUITE_FK_OFF) REVERTIDO: o floor empirico do run 20260613-115507 PROVOU
  #    que o FK-off PIORAVA — deixava ~30 testes era-sqlite dropar tabelas CORE
  #    compartilhadas (business sumiu 252x) que entao faltavam pros testes seguintes.
  #    SEM o FK-off esses drops falham-seguro (Cannot-drop 3730 no proprio teste) e a
  #    tabela CORE SOBREVIVE pro resto da suite. O isolamento real desses testes e a
  #    US-GOV-021 front-2 (nao deixar teste dropar tabela compartilhada). Tests\TestCase
  #    ::setUp fica inerte sem a flag (gated em getenv) — reversivel.
  timeout -s TERM "$TIMEOUT_S" docker run --rm --name oimpresso-fullsuite-run \
    --network "$NET" -v "$CODE":/workspace -v "$RUN_DIR":/artifacts \
    -e DB_CONNECTION=mysql -e "DB_HOST=$DB_HOST" -e "DB_PORT=$DB_PORT" \
    -e "DB_DATABASE=$DB_DATABASE" -e "DB_USERNAME=$DB_USERNAME" -e "DB_PASSWORD=$DB_PASSWORD" \
    -w /workspace --entrypoint sh "$IMAGE" -c '
      if ! command -v mysql >/dev/null 2>&1; then
        apk add --no-cache mariadb-client >/dev/null 2>&1 \
          || apk add --no-cache mysql-client >/dev/null 2>&1 || true
      fi
      if command -v mysql >/dev/null 2>&1; then
        echo "[harness A.1] mysql client OK: $(mysql --version 2>&1 | head -1)"
      else
        echo "[harness A.1] WARN mysql client AUSENTE — migrate:fresh/RefreshDatabase vai envenenar o schema (sem reload do dump)"
      fi
      # A.1 (parte 2 — TLS): o mariadb-client VERIFICA o cert TLS de mysql-workers por
      # DEFAULT, e o "mysql ... < dump" que o migrate:fresh/RefreshDatabase emite NAO passa
      # flag de ssl (Laravel so emite --ssl=off se config options MYSQL_ATTR_SSL_VERIFY_
      # SERVER_CERT===false, que o repo nao seta) => ERROR 2026 "Certificate verification
      # failure" => o dump NAO recarrega. So o binario NAO basta (provado no CT100: bare
      # load TLS-fail; com este config => OK). Desliga a verificacao SO neste container
      # efemero do nightly (encriptacao mantida; o config NAO entra na imagem de prod).
      mkdir -p /etc/my.cnf.d 2>/dev/null || true
      printf "[client]\nssl-verify-server-cert=0\n" > /etc/my.cnf.d/zz-fullsuite-no-ssl-verify.cnf 2>/dev/null || true
      exec php -d memory_limit=2G vendor/bin/pest --log-junit /artifacts/junit.xml --colors=never
    ' \
    2>&1 | tee "$RUN_DIR/pest-out.txt" || PEST_EXIT=$?
  # Detector 1 — Pest loader: uses(TestCase) file-level dentro de pasta ja vinculada
  # ("can not be used. The folder [/workspace/...]").
  BLOCKER=$(grep -oP 'can not be used\. The folder \[/workspace/\K[^]]+' "$RUN_DIR/pest-out.txt" | head -1 || true)
  # Detector 2 — PHP "Cannot redeclare" fatal no LOAD (helper/classe/const global com
  # mesmo nome em 2 arquivos do escopo ->in()). PHP morre ANTES do 1o teste (exit 255 +
  # junit.xml 0 bytes) e o detector 1 NAO pega. A mensagem nomeia o arquivo SENDO
  # CARREGADO em "... in /workspace/<F> on line N"; quarentenar ESSE deixa a declaracao
  # "previously declared in <outro>" sobreviver. Caso real 2026-06-16..18 (3 nights sem
  # floor): insertAuditLog() em Jana/ImmutabilityTriggersTest vs Arquivos/AuditLogCommandTest.
  # O loop re-tenta e pega colisao em cadeia (1 arquivo por volta, ate 12).
  if [ -z "$BLOCKER" ] && grep -qE 'Cannot (re)?declare' "$RUN_DIR/pest-out.txt"; then
    BLOCKER=$(grep -E 'Cannot (re)?declare' "$RUN_DIR/pest-out.txt" \
      | grep -oP 'in /workspace/\K[^ ]+(?= on line)' | head -1 || true)
  fi
  [ -z "$BLOCKER" ] && break
  # Sanity: extracao errada de path NAO deve matar o run sob set -e — so quarentena o
  # arquivo que realmente existe no clone.
  if [ ! -f "$CODE/$BLOCKER" ]; then
    echo "LOADER-BLOCKER ($attempt): '$BLOCKER' detectado mas inexistente no clone — sem quarentena; abortando loop"
    break
  fi
  echo "LOADER-BLOCKER ($attempt): $BLOCKER — movido pro lado no clone, registrado"
  echo "$BLOCKER" >> "$RUN_DIR/loader-blockers.txt"
  mkdir -p "$CODE/.loader-quarantine/$(dirname "$BLOCKER")"
  mv "$CODE/$BLOCKER" "$CODE/.loader-quarantine/$BLOCKER"
done
docker rm -f oimpresso-fullsuite-run >/dev/null 2>&1 || true
echo "pest exit code: $PEST_EXIT (loader-blockers: $(wc -l < "$RUN_DIR/loader-blockers.txt" 2>/dev/null || echo 0))"

echo "--- [7/7] summary (junit-summary.mjs FV-F1 — tripwire artefato 0 bytes) + retencao"
node "$CODE/scripts/tests/junit-summary.mjs" "$RUN_DIR/junit.xml" --out "$RUN_DIR/summary.json" \
  || echo "junit-summary exit $? — XML ausente/incoerente (run morreu antes do flush?)"
ln -sfn "$RUN_DIR" "$RUNS/latest"
find "$RUNS" -maxdepth 1 -mindepth 1 -type d | sort | head -n "-$KEEP_RUNS" | xargs -r rm -rf
echo "=== done $TS sha=$SHA pest_exit=$PEST_EXIT artefatos=$RUN_DIR ==="
