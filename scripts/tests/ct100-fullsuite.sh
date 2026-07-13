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
TIMEOUT_S="${FULLSUITE_TIMEOUT:-14400}"              # timeout do FLOOR (run 1) — 4h
COV_TIMEOUT_S="${FULLSUITE_COV_TIMEOUT:-14400}"      # timeout da COVERAGE (run 2) — 4h; bump só quando o sharding (V1) fizer o pcov caber num nightly
KILL_GRACE_S="${FULLSUITE_KILL_GRACE:-120}"          # timeout -k: apos TERM, SIGKILL do docker-run em Ns (V4 — sem isso o timeout NAO mata o container do pcov e ele viraria runaway de 16h)
SHARDS_N="${FULLSUITE_SHARDS:-8}"                     # V1 sharding: N shards bin-packed (OOM: suite unica morria a ~53%; 8 => ~1/8 do pico por processo)
SHARD_TIMEOUT_S="${FULLSUITE_SHARD_TIMEOUT:-3600}"   # timeout por shard (1h; -k KILL_GRACE_S) — a noite nao tem teto unico, o .lock evita overlap
# dirs descobertos pelo filesystem mas NAO-rodaveis no nightly (poda no shards-plan). tests/Browser
# = Pest Browser (exige Playwright, ausente na imagem => PlaywrightNotInstalledException mata o shard);
# tests/governance-fixtures = testes SINTETICOS bad/good dos gates, nao reais. Provado 2026-07-12:
# 3 shards mortos so por tests/Browser. O `pest` sem-args (single-process) so roda os <testsuite> do
# phpunit.xml; o sharded passa dirs explicitos, entao precisa da MESMA poda.
SHARD_EXCLUDE="${FULLSUITE_SHARD_EXCLUDE:-tests/Browser,tests/governance-fixtures}"
# tentativas de quarentena por shard (loader-blocker de LOAD + killer-test MID-RUN via events).
# 12 = mesmo teto do run unico historico; o SHARD_TIMEOUT_S e o bound duro real.
SHARD_MAX_ATTEMPTS="${FULLSUITE_SHARD_ATTEMPTS:-12}"
KEEP_RUNS="${FULLSUITE_KEEP_RUNS:-14}"
ENV_LOCAL="$BASE/.env.local"

# lock — cron + run manual nunca sobrepoem. V4 (2 lanes): o timeout -k (KILL_GRACE_S)
# abaixo garante que run 1 (floor) + run 2 (coverage) TERMINAM dentro de ~2x TIMEOUT
# (~8h << 24h) — assim a lane de coverage NUNCA segura este lock alem do proximo cron
# 02:00 e a cadencia do floor (metrica-mae) fica protegida. Antes do -k, o timeout
# mandava TERM mas o `docker run` do pcov ignorava e rodava 16h+ -> lock preso ->
# noite seguinte pulava por "outro run em andamento" (3 skips catalogados jul/2026).
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

echo "--- [6/7] pest SHARDED (V1/V3 fix OOM — 1 processo PHP fresco por shard; consome shards-plan.mjs)"
# ARQUITETURA (ADR proposta 2026-07-12 + chip node #4166 SDD P04): a suite inteira num
# processo unico morria por OOM a ~53% (SIGKILL do LXC) => junit.xml 0 bytes em 9/10
# noites => floor congelado. Cura: shards-plan.mjs particiona os dirs de teste (tests/ +
# Modules/) em N shards bin-packed por contagem de arquivo; cada shard roda num processo
# php FRESCO (heap reset => pico cai pra fracao da suite), escreve junit-shard-<i>.xml e
# vira shard-<i>.summary.json (junit-summary FV-F1). O passo [7/7] funde os summaries
# VIVOS (shards-merge.mjs): 1 shard morto perde SO ele, NAO a noite.
#
# C1 (triage Q2): DB_* como -e REAL => o <env DB_CONNECTION=sqlite> sem force= do phpunit
# e ignorado, pest usa o MySQL seedado (ADR 0101). US-GOV-018 A.1: mariadb-client (apk
# fallback) + TLS-verify-off por container. A.2 FULLSUITE_FK_OFF continua REVERTIDO.
# CWD OBRIGATORIO = $CODE: o shards-plan.mjs (chip #4166) descobre os dirs de teste
# relativos a process.cwd() (discoverTestDirs(roots, base=cwd)) — sem CLI --base. O
# harness roda de /opt/oimpresso-fullsuite, entao `--roots tests,Modules` daqui acha 0
# dirs => noite VAZIA (universe-gate passa vacuo "0 cobertos/0 perdidos"). Provado no run
# 20260712-195945: 8 shards vazios, 0 vivos. cd pro clone faz os roots resolverem.
( cd "$CODE" && node "$CODE/scripts/tests/shards-plan.mjs" --roots tests,Modules --shards "$SHARDS_N" \
    --exclude "$SHARD_EXCLUDE" --out "$RUN_DIR/shards-plan.json" ) > /dev/null \
  || { echo "FATAL: shards-plan falhou — sem plano nao rodo"; exit 1; }
# GUARD anti-noite-vazia: 0 dirs descobertos (cwd errado / roots errados / clone vazio) NAO
# pode virar universe-gate verde vacuo. Aborta ALTO em vez de gravar uma noite falsa-valida.
TOTAL_DIRS=$(node -e 'process.stdout.write(String(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).total_dirs||0))' "$RUN_DIR/shards-plan.json")
[ "${TOTAL_DIRS:-0}" -gt 0 ] 2>/dev/null \
  || { echo "FATAL: shards-plan descobriu 0 dirs de teste (cwd=$CODE? roots?) — noite vazia NAO e valida, abortando"; exit 1; }
# universe-gate (SDD P04): prova que os shards cobrem EXATAMENTE o universo descoberto —
# nenhum dir de teste some no particionamento (senao a noite mede menos e mente non-stale).
( cd "$CODE" && node "$CODE/scripts/tests/shards-plan.mjs" --verify --roots tests,Modules --exclude "$SHARD_EXCLUDE" --plan "$RUN_DIR/shards-plan.json" ) \
  || { echo "FATAL: universe-gate do plano de shards FALHOU (dir de teste perdido) — abortando"; exit 1; }
N_SHARDS=$(node -e 'process.stdout.write(String(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).n_shards||0))' "$RUN_DIR/shards-plan.json")
echo "shards-plan: $TOTAL_DIRS dirs de teste descobertos"
echo "shards planejados: $N_SHARDS (bin-pack de tests/ + Modules/; 1 processo php fresco por shard)"

# killer-test MID-RUN (fix "todos" 2026-07-12): quando o junit fica 0-byte SEM loader-blocker
# de LOAD, o php morreu NO MEIO rodando um teste que TRAVA o processo (segfault/exit/crash) —
# nao ha fatal impresso (padrao "morte silenciosa"). O events-log (--log-events-text) grava 1
# linha por evento com flush imediato; o ULTIMO "Test Preparation Started" NOMEIA o teste em voo
# no instante da morte. Deriva o arquivo PSR-4 da classe (Pest prefixa P\; converte \ -> / via
# octal \134 pra nao depender de backslash-literal; Tests\ do topo mapeia pra tests/). Quarentenar
# esse arquivo deixa o shard SOBREVIVER perdendo so o teste assassino (registrado), nao a noite.
# Provado 2026-07-12: shard 0 morreu em Wave23SaturationTest (37 testes, nao OOM), shard 1 em
# SeedAdrsMetadataTest (986) — ambos derivados corretos + existentes no clone.
killer_from_events() {
  ev="$1"; [ -f "$ev" ] || return 0
  line=$(grep 'Test Preparation Started (' "$ev" | tail -1)
  [ -z "$line" ] && return 0
  cls=$(printf '%s' "$line" | tr '\134' '/' | sed 's|.*(P/||; s|::.*||; s|^Tests/|tests/|')
  [ -z "$cls" ] && return 0
  printf '%s.php' "$cls"
}

# reset da quarentena de runs anteriores (o `git clean -fd` do passo 1 nao pega
# .loader-quarantine se gitignored) — cada run parte do universo COMPLETO.
rm -rf "$CODE/.loader-quarantine" 2>/dev/null || true
SHARDS_LIVE=0
PEST_EXIT=0
i=0
# RESILIENCIA (run 20260712-202355 morreu no shard 1): o laço faz seu PROPRIO tratamento de
# erro (checa -s $SJUNIT, detecta blocker/killer, loop-guard) — errexit AQUI so mata o pai
# quando um shard crasha. `set +e` no laço + `set -e` depois. Shard morto NUNCA derruba a noite.
set +e
while [ "$i" -lt "$N_SHARDS" ]; do
  # args pest deste shard: shards[].paths (plano v2 — recursivo-disjuntos; unidade mista
  # expandida em arquivos, fix do shard 2 morto run 20260712-212018) com fallback .dirs (v1)
  SDIRS=$(node -e 'const p=JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")); const s=(p.shards||[]).find(x=>x.index==Number(process.argv[2])); process.stdout.write(((s&&(s.paths||s.dirs))||[]).join(" "))' "$RUN_DIR/shards-plan.json" "$i")
  if [ -z "$SDIRS" ]; then echo "SHARD $i: sem dirs (vazio) — pulado"; i=$((i+1)); continue; fi
  SJUNIT="$RUN_DIR/junit-shard-$i.xml"
  SOUT="$RUN_DIR/shard-$i-out.txt"
  docker rm -f "oimpresso-fullsuite-run-$i" >/dev/null 2>&1 || true
  for attempt in $(seq 1 "$SHARD_MAX_ATTEMPTS"); do
    # 1 processo php FRESCO por shard (container efemero -> heap zerado). memory_limit 4G =
    # TETO (nao reserva); com sharding raramente alcancado. timeout -k mata o container no
    # teto (V4). Mesmos fixes A.1 do run unico (mysql client + TLS-off). SHARD_DIRS/SHARD_IDX
    # via -e (o container nao tem node/plano). --log-events-text por-shard = post-mortem FV-F4.
    timeout -k "$KILL_GRACE_S" -s TERM "$SHARD_TIMEOUT_S" docker run --rm --name "oimpresso-fullsuite-run-$i" \
      --network "$NET" -v "$CODE":/workspace -v "$RUN_DIR":/artifacts \
      -e DB_CONNECTION=mysql -e "DB_HOST=$DB_HOST" -e "DB_PORT=$DB_PORT" \
      -e "DB_DATABASE=$DB_DATABASE" -e "DB_USERNAME=$DB_USERNAME" -e "DB_PASSWORD=$DB_PASSWORD" \
      -e "SHARD_DIRS=$SDIRS" -e "SHARD_IDX=$i" \
      -w /workspace --entrypoint sh "$IMAGE" -c '
        if ! command -v mysql >/dev/null 2>&1; then
          apk add --no-cache mariadb-client >/dev/null 2>&1 \
            || apk add --no-cache mysql-client >/dev/null 2>&1 || true
        fi
        mkdir -p /etc/my.cnf.d 2>/dev/null || true
        printf "[client]\nssl-verify-server-cert=0\n" > /etc/my.cnf.d/zz-fullsuite-no-ssl-verify.cnf 2>/dev/null || true
        # shellcheck disable=SC2086
        exec php -d memory_limit=4G vendor/bin/pest $SHARD_DIRS \
          --log-junit "/artifacts/junit-shard-$SHARD_IDX.xml" \
          --log-events-text "/artifacts/pest-events-shard-$SHARD_IDX.txt" --colors=never
      ' \
      < /dev/null > "$SOUT" 2>&1
    docker rm -f "oimpresso-fullsuite-run-$i" >/dev/null 2>&1 || true
    # NAO teamos a saida do shard pro run.log (ia via `exec > >(tee run.log)`): um shard que
    # crasha despeja MBs de stacktrace num burst (×ate SHARD_MAX_ATTEMPTS) e pode quebrar o pipe
    # do tee do run.log -> o pai bash leva SIGPIPE no proximo write e morre SILENCIOSO (causa do
    # run 20260712-202355). A saida vive so no $SOUT (post-mortem/detectores); run.log fica limpo.
    # junit presente e NAO-vazio => shard OK (pass OU fail = DADO). Para de retentar.
    [ -s "$SJUNIT" ] && break
    # junit 0b/ausente: php morreu no LOAD. Detectores loader-blocker (HOST, grep -oP GNU):
    # folder / redeclare / parse — os 3 do run unico, agora por-shard, quarentena no clone.
    BLOCKER=$(grep -oP 'can not be used\. The folder \[/workspace/\K[^]]+' "$SOUT" | head -1 || true)
    if [ -z "$BLOCKER" ] && grep -qE 'Cannot (re)?declare' "$SOUT"; then
      BLOCKER=$(grep -E 'Cannot (re)?declare' "$SOUT" | grep -oP 'in /workspace/\K[^ ]+(?= on line)' | head -1 || true)
    fi
    if [ -z "$BLOCKER" ] && grep -qE 'Parse error' "$SOUT"; then
      BLOCKER=$(grep -E 'Parse error' "$SOUT" | grep -oP 'in /workspace/\K[^ ]+(?= on line)' | head -1 || true)
    fi
    # junit 0b SEM loader-blocker de LOAD => killer-test MID-RUN (trava o processo, sem fatal).
    # O events-log nomeia o teste em voo; quarentena ele e retenta (shard sobrevive perdendo
    # so o assassino). Bound real = SHARD_TIMEOUT_S; killer profundo caro (re-roda ate ele) mas
    # limitado. OOM externo genuino nao nomeia culpado util => quarentena nao progride e desiste.
    if [ -z "$BLOCKER" ]; then
      BLOCKER=$(killer_from_events "$RUN_DIR/pest-events-shard-$i.txt")
      [ -n "$BLOCKER" ] && echo "SHARD $i: killer-test em voo detectado via events: $BLOCKER"
    fi
    # sem blocker/killer recuperavel => shard morto (OOM externo/timeout). NAO retenta: o merge do
    # passo 7 registra como missing e a noite segue (all_shards_measured=false).
    [ -z "$BLOCKER" ] && { echo "SHARD $i: morto sem blocker/killer recuperavel (OOM externo/timeout?) — segue"; break; }
    [ ! -f "$CODE/$BLOCKER" ] && { echo "SHARD $i: blocker inexistente no clone ($BLOCKER) — sem quarentena"; break; }
    # loop-guard: se ja quarentenamos ESSE arquivo e o shard AINDA morre, nao ha progresso
    # (ex OOM que nomeia sempre o mesmo teste em voo) — desiste em vez de girar em falso.
    if grep -qxF "$BLOCKER" "$RUN_DIR/loader-blockers.txt" 2>/dev/null; then
      echo "SHARD $i: $BLOCKER ja quarentenado e o shard ainda morre — sem progresso, desiste"; break
    fi
    echo "SHARD $i quarentena ($attempt/$SHARD_MAX_ATTEMPTS): $BLOCKER — movido no clone, registrado"
    echo "$BLOCKER" >> "$RUN_DIR/loader-blockers.txt"
    mkdir -p "$CODE/.loader-quarantine/$(dirname "$BLOCKER")" 2>/dev/null || true
    mv "$CODE/$BLOCKER" "$CODE/.loader-quarantine/$BLOCKER" 2>/dev/null || true
    # plano v2 passa ARQUIVOS como args: arg quarentenado (movido) quebraria o pest no
    # retry ("path does not exist") — remove da lista; se esvaziou, nada a rodar.
    SDIRS=$(printf '%s\n' $SDIRS | grep -vxF "$BLOCKER" | tr '\n' ' ' | sed 's/ $//')
    [ -z "$SDIRS" ] && { echo "SHARD $i: todos os args quarentenados — sem retry"; break; }
  done
  # summary por-shard (junit-summary FV-F1): junit valido => shard-<i>.summary.json coerente;
  # junit 0b/ausente => marcador {invalid} (o shards-merge trata como shard morto = missing).
  if node "$CODE/scripts/tests/junit-summary.mjs" "$SJUNIT" --out "$RUN_DIR/shard-$i.summary.json" >/dev/null 2>&1; then
    SHARDS_LIVE=$((SHARDS_LIVE+1)); echo "SHARD $i: ok (summary coerente)"
  else
    echo "SHARD $i: DEAD (summary invalido/ausente — perde so ele)"
  fi
  i=$((i+1))
done
set -e  # restaura errexit apos o laço de shards (resiliencia do run 202355)
echo "shards vivos: $SHARDS_LIVE/$N_SHARDS (loader-blockers: $([ -f "$RUN_DIR/loader-blockers.txt" ] && wc -l < "$RUN_DIR/loader-blockers.txt" || echo 0))"

# --- [P07 coverage] (ADR 0275 C2 · 2a invocacao SEPARADA — floor nunca refem) -----
# Historia: a 1a nightly com pcov no MESMO processo (20260702-073601) morreu silenciosa
# aos 5933/11144 testes (53%, sem fatal impresso, shim containerd morto 09:03:23 —
# padrao de kill externo por pressao de memoria; swap do CT100 foi a 2.4G) e levou o
# junit junto (0 bytes) = noite de floor perdida. Correcao ESTRUTURAL: coverage roda
# DEPOIS do junit salvo, em container proprio. Falha aqui => so coverage_pct fica
# not_yet_measured (coverage-compute valida o clover: truncado/ausente nao conta);
# o diagnostico da noite ja esta em disco. memory_limit maior (6G): o dado de
# cobertura agregado (CodeCoverage per-test) cresce com a suite inteira — 2G matava
# aos ~53%. Sem --log-junit aqui: o junit canonico e o do run 1 (FV-F1). pcov na
# suite INTEIRA (nunca o lane sqlite curado — ADR 0275:68). Fail e DADO: exit != 0
# de teste falhando e esperado; o que importa e o clover flushado no fim.
#
# V4 (lane isolada, NUNCA contamina o floor): o pcov single-core na suite inteira leva
# 16h+ (e travou a ~77% em jul/2026). timeout -k (KILL_GRACE_S) e OBRIGATORIO aqui — o
# `-s TERM` sozinho NAO mata o `docker run` do pcov (o php ignora TERM no C do pcov), o
# container virava runaway de 16h e segurava o .lock -> pulava a nightly seguinte. Com o
# -k, o container e SIGKILLado no teto (COV_TIMEOUT_S) e o `docker rm -f` abaixo garante
# zero zumbi. Coverage killed mid-run deixa clover TRUNCADO -> coverage-compute rejeita
# (exige </coverage> no fim) -> read-side fica not_yet_measured (nunca mente baixo). A
# lane so vira PRODUTIVA (clover completo -> coverage_pct) quando o sharding (V1) fizer o
# pcov caber num nightly; ate la ela roda bounded e honestamente sem numero.
if docker run --rm --entrypoint php "$IMAGE" -m 2>/dev/null | grep -qi '^pcov$'; then
  echo "--- [P07 coverage] run separado com pcov (clover em $RUN_DIR/clover.xml; log em cov-out.txt)"
  COV_EXIT=0
  timeout -k "$KILL_GRACE_S" -s TERM "$COV_TIMEOUT_S" docker run --rm --name oimpresso-fullsuite-cov \
    --network "$NET" -v "$CODE":/workspace -v "$RUN_DIR":/artifacts \
    -e DB_CONNECTION=mysql -e "DB_HOST=$DB_HOST" -e "DB_PORT=$DB_PORT" \
    -e "DB_DATABASE=$DB_DATABASE" -e "DB_USERNAME=$DB_USERNAME" -e "DB_PASSWORD=$DB_PASSWORD" \
    -w /workspace --entrypoint sh "$IMAGE" -c '
      if ! command -v mysql >/dev/null 2>&1; then
        apk add --no-cache mariadb-client >/dev/null 2>&1 \
          || apk add --no-cache mysql-client >/dev/null 2>&1 || true
      fi
      mkdir -p /etc/my.cnf.d 2>/dev/null || true
      printf "[client]\nssl-verify-server-cert=0\n" > /etc/my.cnf.d/zz-fullsuite-no-ssl-verify.cnf 2>/dev/null || true
      exec php -d memory_limit=6G -d pcov.enabled=1 -d pcov.directory=. -d "pcov.exclude=~(vendor|node_modules|storage)~" vendor/bin/pest --coverage-clover /artifacts/clover.xml --colors=never
    ' > "$RUN_DIR/cov-out.txt" 2>&1 || COV_EXIT=$?
  # `docker rm -f` sempre (o -k desbloqueia o timeout mas o container pode sobreviver ao
  # SIGKILL do CLI — este e o kill definitivo do zumbi). exit 124/137 = timeout -k matou
  # (pcov estourou o teto): clover truncado/ausente, coverage-compute rejeita.
  docker rm -f oimpresso-fullsuite-cov >/dev/null 2>&1 || true
  case "$COV_EXIT" in
    124|137) echo "[P07 coverage] TIMEOUT — cov matou no teto ${COV_TIMEOUT_S}s (exit=$COV_EXIT); clover incompleto, coverage_pct segue not_yet_measured (sharding V1 pendente)";;
    *)       echo "[P07 coverage] exit=$COV_EXIT clover=$(stat -c%s "$RUN_DIR/clover.xml" 2>/dev/null || echo 0) bytes";;
  esac
else
  echo "--- [P07 coverage] pcov ausente na imagem — coverage pulado (read-side segue not_yet_measured)"
fi

echo "--- [7/7] merge dos summaries por-shard -> summary.json (shards-merge SDD P04 · FV-F1) + retencao"
# shards-merge funde os shard-<i>.summary.json VIVOS numa medicao da noite (schema
# fullsuite-summary-sharded/v1): coherent=(>=1 shard vivo), all_shards_measured=(0 mortos).
# floor-compute v2 le all_shards_measured — noite parcial NAO vira burn-down fake (guard
# anti-mascaramento). O summary.json canonico da noite agora VEM do merge (nao do junit
# unico): 1 shard OOM perde so ele, a noite segue valida com os outros.
node "$CODE/scripts/tests/shards-merge.mjs" --shards-dir "$RUN_DIR" --plan "$RUN_DIR/shards-plan.json" \
  --out "$RUN_DIR/summary.json" || true
COHERENT=$(node -e 'try{process.stdout.write(String(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).coherent===true))}catch{process.stdout.write("false")}' "$RUN_DIR/summary.json")
ALL_MEASURED=$(node -e 'try{process.stdout.write(String(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).all_shards_measured!==false))}catch{process.stdout.write("false")}' "$RUN_DIR/summary.json")
MISSING=$(node -e 'try{process.stdout.write((JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).shards_missing||[]).join(","))}catch{}' "$RUN_DIR/summary.json")
if [ "$COHERENT" = "true" ]; then
  echo "[7/7] noite VALIDA — shards vivos $SHARDS_LIVE/$N_SHARDS, all_shards_measured=$ALL_MEASURED${MISSING:+, mortos: $MISSING}"
  # all_shards_measured (0 mortos) => apaga eventos; parcial => mantem (post-mortem dos mortos)
  [ "$ALL_MEASURED" = "true" ] && rm -f "$RUN_DIR"/pest-events-shard-*.txt
else
  # coherent=false => TODOS os shards morreram => noite invalida (muito mais raro que o run
  # unico, onde 1 morte ja zerava). FV-F4: alerta grep-avel nomeia o teste em voo.
  LAST_EVENTS="$(ls -t "$RUN_DIR"/pest-events-shard-*.txt 2>/dev/null | head -1)"
  IN_FLIGHT="$(grep 'Test Prepared (' "$LAST_EVENTS" 2>/dev/null | tail -1 | sed 's/^Test Prepared (//; s/)[[:space:]]*$//' || true)"
  echo "[ALERT] fullsuite_run_invalid ts=$TS sha=$SHA shards_live=$SHARDS_LIVE n_shards=$N_SHARDS shards_missing=\"${MISSING:-todos}\" last_test_in_flight=\"${IN_FLIGHT:-desconhecido}\""
fi
ln -sfn "$RUN_DIR" "$RUNS/latest"
find "$RUNS" -maxdepth 1 -mindepth 1 -type d | sort | head -n "-$KEEP_RUNS" | xargs -r rm -rf

# --- [floor] (ADR 0279 write-side · Opcao A) -------------------------------------
# Computa o FLOOR = intersecao dos arquivos-que-falham entre >=2 nightlies VALIDOS
# (def US-GOV-018) e publica governance/nightly-floor.json na branch ORFA
# governance/nightly-floor via deploy key (/root/.ssh/oimpresso_floor_deploy; orfa =
# imune a shallow + NAO toca a protecao do main). O scorecard materializa esse arquivo
# no CI e mede full_suite. <2 runs validos => floor_count null => read-side fica
# not_yet_measured (nunca mente 0). Falha aqui NAO derruba o run (o diagnostico ja rodou).
FLOOR_KEY=/root/.ssh/oimpresso_floor_deploy
if [ -f "$FLOOR_KEY" ]; then
  FLOORDIR="$(mktemp -d)"
  mkdir -p "$FLOORDIR/governance"
  # V6-B (elo 2 · avaliacao SDD 2026-07-12): publica TAMBEM o summary per-file (files[]) da noite
  # pro gate verde advisory (anchor-lint --junit --check-verde le da orfa no CI · anchor-drift.yml).
  # So se COHERENT (>=1 shard vivo); noite invalida => ausente => la vira behavior_unknown (V6-A).
  { [ "$COHERENT" = "true" ] && [ -f "$RUN_DIR/summary.json" ] \
      && cp "$RUN_DIR/summary.json" "$FLOORDIR/governance/nightly-fullsuite-summary.json"; } || true
  if node "$CODE/scripts/tests/floor-compute.mjs" --runs "$RUNS" --window 3 --out "$FLOORDIR/governance/nightly-floor.json"; then
    # SDD P07 (ADR 0275 coverage_pct): mesmo transporte do floor (branch orfa +
    # deploy key + push [skip ci]). coverage-compute le os clover.xml das ultimas
    # nightlies e escreve governance/nightly-coverage.json. Falha aqui NAO derruba
    # o floor (a metrica viva) — se coverage-compute falhar (ex pcov ainda nao na
    # imagem => sem clover), so o nightly-floor.json e commitado (read-side de
    # coverage fica not_yet_measured, nunca mente 0).
    if node "$CODE/scripts/tests/coverage-compute.mjs" --runs "$RUNS" --window 3 --out "$FLOORDIR/governance/nightly-coverage.json"; then
      echo "[coverage] nightly-coverage.json computado"
    else
      echo "[coverage] coverage-compute falhou (clover ausente? pcov pendente no rebuild) — so o floor sera publicado"
      rm -f "$FLOORDIR/governance/nightly-coverage.json"
    fi
    ( cd "$FLOORDIR" \
      && git init -q \
      && git config core.sshCommand "ssh -i $FLOOR_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new" \
      && git add governance/nightly-floor.json \
      && { [ -f governance/nightly-coverage.json ] && git add governance/nightly-coverage.json || true; } \
      && { [ -f governance/nightly-fullsuite-summary.json ] && git add governance/nightly-fullsuite-summary.json || true; } \
      && git -c user.email=ct100-floor@oimpresso.local -c user.name="ct100-nightly-floor" commit -q -m "chore(sdd): nightly floor+coverage+verde-summary $TS [skip ci]" \
      && git push -f git@github.com:wagnerra23/oimpresso.com.git HEAD:refs/heads/governance/nightly-floor 2>&1 | tail -2 ) \
      && echo "[floor] publicado em governance/nightly-floor (+coverage se presente)" \
      || echo "[floor] push falhou (ver acima) — read-side fica notYet"
  else
    echo "[floor] floor-compute falhou — pulo publicacao"
  fi
  rm -rf "$FLOORDIR"
else
  echo "[floor] deploy key ausente ($FLOOR_KEY) — pulo publicacao (read-side notYet)"
fi

echo "=== done $TS sha=$SHA pest_exit=$PEST_EXIT artefatos=$RUN_DIR ==="
