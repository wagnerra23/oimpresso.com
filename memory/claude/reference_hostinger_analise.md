---
name: Como analisar o banco na Hostinger (receita SSH + MySQL)
description: Receita pronta pra rodar queries no banco de produção via SSH. Usa PHP inline via heredoc (evita scp flaky) ou mysql CLI direto. Warm da rota antes, timeouts altos, tratar aspas do .env.
type: reference
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
Conectar na Hostinger é lento/flaky (connection timeouts frequentes). Usar este padrão pra economizar tempo:

## 1. Warm da rota antes de SSH (reduz timeout)

```bash
for i in 1 2 3; do curl -s -o /dev/null -w "$i:%{http_code}\n" https://oimpresso.com/login --max-time 15; done
```

3-5 hits em sequência "esquentam" a conexão antes do SSH. Sem isso, primeiro SSH quase sempre dá `Connection timed out`.

## 2. SSH config para sobreviver conexão ruim

```bash
ssh -4 \
  -o ConnectTimeout=900 \
  -o ServerAliveInterval=3 \
  -o ServerAliveCountMax=200 \
  -o ConnectionAttempts=5 \
  -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "comando_aqui"
```

- `-4` obrigatório (IPv6 falha)
- `ServerAliveInterval=3` envia keepalive frequente
- `ConnectionAttempts=5` retry embutido

## 3. Query SQL via mysql CLI (mais rápido que tinker)

`.env` da Hostinger tem valores entre aspas — remover com `tr -d '"'`:

```bash
ssh ... "cd domains/oimpresso.com/public_html && \
  U=\$(grep ^DB_USERNAME .env | cut -d= -f2 | tr -d '\"') && \
  P=\$(grep ^DB_PASSWORD .env | cut -d= -f2 | tr -d '\"') && \
  D=\$(grep ^DB_DATABASE .env | cut -d= -f2 | tr -d '\"') && \
  mysql -h localhost -u \"\$U\" -p\"\$P\" \"\$D\" -e \"SELECT * FROM business ORDER BY id LIMIT 5\""
```

`mysql` CLI não precisa bootar Laravel — é ~10× mais rápido que `artisan tinker`.

## 4. Script PHP inline (quando precisa de Eloquent/Carbon/permissions Spatie)

Usa heredoc `<< 'PHPEOF'` e escapa `$` nas interpolações do bash:

```bash
ssh ... "cat > domains/oimpresso.com/public_html/temp.php << 'PHPEOF'
<?php
require __DIR__.'/vendor/autoload.php';
\$app = require_once __DIR__.'/bootstrap/app.php';
\$app->make(\\\\Illuminate\\\\Contracts\\\\Console\\\\Kernel::class)->bootstrap();

// código aqui — use \$ pra evitar expansão no heredoc
\$rows = DB::select('SELECT ...');
foreach (\$rows as \$r) { echo \$r->id.PHP_EOL; }
PHPEOF
cd domains/oimpresso.com/public_html && php temp.php && rm temp.php"
```

Critico:
- `<< 'PHPEOF'` com aspas simples = sem interpolação bash (PHP recebe `$` literal)
- `\\\\Illuminate` porque shell → ssh → bash → PHP precisa 4 níveis de escape
- Sempre `rm` o arquivo no fim (produção não deve ter PHP órfão)

## 5. Aviso: tinker falha com namespaces em CLI --execute

`php artisan tinker --execute="Carbon\Carbon::now()"` dá **PARSE ERROR** por causa do backslash. Alternativas:
- Usar heredoc PHP com `use Carbon\Carbon;`
- Ou comando sem namespace: `date('Y-m-d H:i:s')`, `DB::select(...)`, etc. (funcs globais)
- Ou script PHP inline (receita 4)

## 6. Deploy da branch via git (reset --hard)

```bash
ssh ... "cd domains/oimpresso.com/public_html && \
  git fetch origin 6.7-bootstrap && \
  git reset --hard origin/6.7-bootstrap && \
  php artisan view:clear && \
  php artisan config:clear && \
  php artisan cache:clear"
```

Branch atual é `6.7-bootstrap` (ver `project_current_branch.md`).

## 7. Queries úteis pra diagnosticar incidentes

**Listar vendas recentes de um business:**
```sql
SELECT id, invoice_no, transaction_date, created_at,
  TIMESTAMPDIFF(MINUTE, created_at, transaction_date) as diff_min
FROM transactions WHERE business_id=4 AND type='sell'
  AND DATE(created_at) BETWEEN '2026-04-21' AND '2026-04-23'
ORDER BY created_at DESC;
```

**Ver timezone do MySQL server:**
```sql
SELECT NOW() as mysql_now, @@session.time_zone, @@global.time_zone;
```
Hostinger: `SYSTEM = UTC` (server Linux em UTC). PHP pode estar em SP (via `.env APP_TIMEZONE`). Diferença de 3h entre MySQL `NOW()` e `Carbon::now()` é o esperado.

**Auditar roles sem permissão de location (bug ROTA LIVRE replicável):**
```sql
SELECT r.name as role, r.business_id, (
  SELECT COUNT(*) FROM model_has_permissions mhp
  JOIN permissions p ON p.id = mhp.permission_id
  WHERE mhp.model_type = 'Spatie\\Permission\\Models\\Role'
    AND mhp.model_id = r.id
    AND (p.name LIKE 'location.%' OR p.name = 'access_all_locations')
) as loc_perms
FROM roles r HAVING loc_perms = 0;
```

## Atalho: nunca usar computer-use pra operar Hostinger

Sempre SSH. Se SSH falhar depois de 3-5 retries, pode ser problema transitório — esperar 30s e tentar de novo, ou desistir e usar Git Actions workflow manual (`workflow_dispatch` no `.github/workflows/deploy.yml`).
