---
name: Deploy Hostinger + recovery patterns (composer install, cache stale, orphan tables, quick-sync fallback)
description: Receitas operacionais pós-deploy Hostinger. composer install obrigatório se composer.json/lock muda (quick-sync.yml NÃO faz). Tela branca Inertia pós optimize:clear = cache stale do bundle (hard reload resolve). Recovery tabela órfã (DDL MySQL não-transacional). Quick-sync fallback SSH manual.
type: reference
---
# Deploy + recovery patterns (Hostinger)

Consolidação de receitas operacionais que se entrelaçam em sessões de deploy. Validadas várias vezes em 2026-04-25 → 2026-05-10.

## 1. composer install OBRIGATÓRIO pós-push em main (se composer.json/lock muda)

**Quando rodar:**
- Push em main com diff em `composer.json` ou `composer.lock`
- Após `composer install`/`require`/`update` local que altera lockfile
- NÃO precisa se só mudou PHP/JS sem deps novas

**Sintoma se esquecer (caso real 2026-04-25, Inertia v2→v3):**
- `composer.json`: `"inertiajs/inertia-laravel": "^3.0"`
- `composer.lock`: `v3.0.6`
- Bundle JS: `@inertiajs/react ^3.0.3` (vai pelo build Vite)
- **`vendor/inertiajs/inertia-laravel/`: AINDA v2.0.24** (composer install nunca rodou)

Mismatch backend manda payload v2, JS espera v3 → **tela branca Inertia** → console: `TypeError: Cannot read properties of null (reading 'component')` em `app-XXXX.js:138`.

**Fix correto (SSH Hostinger):**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   composer install --optimize-autoloader --no-interaction && \
   php artisan optimize:clear"
```

**NUNCA usar `--no-dev`** em produção neste projeto:
- ServiceProvider/Module usa `Faker\Generator` que está em `require-dev`
- Sem Faker, `php artisan package:discover` falha → HTTP 500
- Caiu em 2026-04-25; resolvido restaurando dev deps
- TODO: identificar quem usa Faker em prod e mover pra `require`

**Smoke validation:**
```bash
ssh ... "cd ... && composer show inertiajs/inertia-laravel | grep versions"
# Esperado: 'versions : * v3.0.6'
```

**`quick-sync.yml` NÃO faz composer install** — TODO: adicionar step `composer install --optimize-autoloader` após git pull + validar diff em composer.lock + notificar Wagner se falhar.

## 2. Quick-sync deploy fallback SSH manual

Workflow `quick-sync.yml` (auto on push em main → Hostinger) ocasionalmente falha em **"Setup SSH"** (key/timeout flaky).

**Sintoma:**
- PR mergeado em main
- Workflow run aparece em github.com/wagnerra23/oimpresso.com/actions
- Status = `failure` (não `success`)
- File em prod ainda na versão anterior

**Verificar antes de testar:**
```bash
gh api 'repos/wagnerra23/oimpresso.com/actions/workflows/quick-sync.yml/runs?per_page=3' \
  --jq '.workflow_runs[] | {id, status, conclusion, head_sha}'
```

**Fallback SSH manual (se conclusion=failure pro head_sha do merge):**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && \
   git fetch origin main && \
   git reset --hard origin/main && \
   php artisan optimize:clear'
```

Se mudança em frontend (`.tsx`/`.ts`/`.css`) — também:
```bash
ssh ... 'cd ~/domains/oimpresso.com/public_html && \
  source ~/.nvm/nvm.sh && nvm use 24 && \
  npm run build:inertia'
```

(quick-sync.yml normalmente roda esse build step também — só rodar manual se workflow falhou)

**`php artisan optimize:clear`** limpa view cache + config cache + bootstrap. Sem isso, classes PHP cacheadas (opcache implícito) servem versão antiga, especialmente após mudanças em traits/classes que afetam composition.

Validado 2026-05-10: PRs #440 e #456 falharam workflow, fallback resolveu 2x.

### 2.1 Checklist "tela nova não aparece em prod pós-merge"

Disparo: Wagner mergeou PR no GitHub, tenta abrir feature em prod, e não funciona (404, sidebar sem entrada, ou cache antigo). Protocolo direto:

```bash
# 1) Em qual commit Hostinger está agora?
ssh hostinger 'cd ~/domains/oimpresso.com/public_html && git log --oneline -3'

# 2) Compare com origin/main
gh api repos/wagnerra23/oimpresso.com/commits/main --jq '.sha[:9]'

# 3) Se Hostinger atrás → quick-sync rodou?
gh run list --workflow=quick-sync.yml --limit 5

# 4) Se conclusion=failure → fallback SSH manual da §2 acima
# 5) Se npm run build necessário (novo .tsx/.ts/.css) → §2 também cobre
```

**Caso real 2026-05-14 (saga F3 Fluxo de caixa US-FIN-014):**
- PRs #838 + #839 mergeados em ~7:24/7:25 BRT
- `quick-sync.yml` rodou pro #838 mas falhou em **Setup SSH** em 12s (`ssh-keyscan -p *** -H *** ...` timeout — flaky idêntico pattern §2)
- Hostinger ficou em commit `d1d48380e` (anterior aos meus 2 merges) — confirmado via SSH read-only
- Wagner abriu `oimpresso.com/jana` no Brave esperando ver feature nova → sidebar Financeiro ainda só tinha 4 itens (sem "Fluxo de caixa") porque `Modules/Financeiro/Resources/menus/topnav.php` em prod era versão antiga
- Fix: rodar §2 fallback (git reset --hard origin/main + npm run build:inertia + optimize:clear)

**Lição:** após qualquer merge importante que cria tela nova / adiciona entry topnav / modifica controller, **sempre verificar `gh run list --workflow=quick-sync.yml --limit 1` ANTES** de dizer "tá em prod" pro Wagner. Falha silenciosa do workflow é o pattern dominante de "tela não aparece".

### 2.2 Quick-sync vs Deploy: quando precisa composer install em prod

**Disparo:** PR mergeado, `quick-sync.yml` rodou success, mas rotas do módulo afetado retornam **500 Server Error**. Outras rotas funcionam normalmente.

**Causa raiz:** `quick-sync.yml` **NÃO roda** `composer install` nem `php artisan migrate` — só faz `git pull` + `npm run build:inertia` + `optimize:clear`. Quando PR adiciona método novo em Controller existente, classe nova, ou novo binding de FQCN, o **composer autoload** de prod fica desatualizado → Laravel não consegue resolver classes → ServiceProvider crash → 500 cascateando em todo módulo.

**Sintomas catalogados (Wave 7-C 2026-05-20):**
- `oimpresso.com/oficina-auto/ordens-servico` → 500
- `oimpresso.com/oficina-auto/veiculos` → 500
- `oimpresso.com/home` → 200 (rotas que não dependem do método novo continuam)
- `Force Clean Rebuild` workflow falha em `view:cache` com erro `Symfony\Component\Finder\DirectoryNotFoundException` (sintoma secundário do autoload quebrado)

**Fix canônico:**

```bash
# Disparar Deploy to Hostinger workflow_dispatch (full deploy: composer + migrate + cache)
gh workflow run "Deploy to Hostinger" --ref main

# Aguardar com watch
$run = (gh run list --workflow=deploy.yml --limit 1 --json databaseId | ConvertFrom-Json).databaseId
gh run watch $run --interval 20
```

**Quando usar Deploy vs Quick-sync — matriz de decisão:**

| Mudança do PR | Quick-sync basta? | Por quê |
|---|---|---|
| Só `.tsx`/`.ts`/`.css` frontend | ✅ Sim | npm run build:inertia regenera bundles |
| `.blade.php` view edit | ✅ Sim | view:clear no quick-sync |
| Controller — método novo OU classe nova OU trait composition | ❌ **Não** — usar Deploy | composer autoload precisa regenerar |
| Adicionar dependency em `composer.json` | ❌ **Não** — usar Deploy | composer install obrigatório |
| Migration nova (`*_create_*.php` em Database/Migrations) | ❌ **Não** — usar Deploy | artisan migrate obrigatório |
| ServiceProvider/binding/Module.json alteração | ❌ **Não** — usar Deploy | package:discover + composer dump-autoload |
| Documentação `memory/*.md` puramente | ✅ Sim (irrelevante — não muda runtime) | git pull basta |

**Lição executiva:** **toda PR `feat()` que adiciona método novo em Controller existente DEVE ser deployada via `Deploy to Hostinger` workflow_dispatch**, não confiar no quick-sync auto-trigger. Quick-sync é OK pra hotfix `.tsx` puro ou docs. Validar via matriz acima antes de declarar "tá em prod".

**Caso real 2026-05-20 (Wave 7-C):**
- PR #1195 adicionou `ServiceOrderFsmActionController::history()` (método novo)
- Quick-sync trigger automático mergou → SSH `git reset --hard` → tentou recompor índice
- Erro: `unable to create file ServiceOrderSheet.tsx: File exists` (filesystem Linux case-sensitive vs Windows case-insensitive — bug secundário)
- Mesmo após chore-vite quick-sync subsequente "limpar" o estado git, autoload Composer ficou stale
- Todo `Modules/OficinaAuto/*` 500 até `Deploy to Hostinger` rodar composer install + migrate + cache rebuild
- Skill `smoke-prod-evidence` detectou via browser MCP — sem ela, falha silenciosa porque quick-sync workflow reportava success

## 3. Tela branca Inertia pós optimize:clear = cache stale do bundle

**Não é regressão real** — tab Chrome com bundle JS antigo trava após `optimize:clear`.

**Causa:** service worker / cache de browser tem bundle JS Inertia velho que aponta pra hashes Vite (`build-inertia/assets/app-XXXXX.js`) que **não existem mais** após `optimize:clear` rebuilder o cache. Bundle tenta carregar import dinâmico → 404 silencioso → render trava.

**Sintomas:**
- Tela branca (`document.body.innerHTML` vazio ou minimal)
- `document.title` = URL bruta (sem fallback friendly)
- Console **sem erros JS** (carga falhou silencioso)
- `curl https://oimpresso.com/home` retorna HTML normal **com referências aos novos hashes** (servidor OK)
- Renderer Chrome eventualmente trava (timeout JS)

**Reproduce:**
1. Abrir `/home` ou tela MWART (`/nfe-brasil/manifestacao`) com Chrome
2. Rodar `php artisan optimize:clear` em prod
3. F5 na mesma tab → tela branca

**Fix imediato:** **tab nova** ou **hard reload** (`Ctrl+Shift+R` Windows, `Cmd+Shift+R` Mac). Service worker novo carrega + bundle hashes corretos.

**NÃO é problema de servidor — checklist diagnóstico:**
- `composer install` rodou OK
- `vendor/autoload.php` regenerado
- `public/build-inertia/manifest.json` válido (368KB, 153 Pages em 2026-05-10)
- `bootstrap/cache/services.php` regenerado
- `laravel.log` sem erro Inertia/Vite/render
- curl direto retorna HTML normal

```bash
curl -s -o /dev/null -w "HTTP %{http_code} | size=%{size_download}\n" https://oimpresso.com/home
# Esperado: 302 → /login (sem cookie) ou 200 + size > 10KB (com cookie sessão)
```

**Prevenção:**
- Após `optimize:clear` em prod, comunicar Wagner pra hard reload
- Idealmente, deploy script faz `optimize:clear` ANTES de regenerar Vite manifest

## 4. Recovery tabela órfã pós-migrate falho (DDL MySQL não-transacional)

DDL em MySQL **não é transacional**. Quando `php artisan migrate` falha em meio a uma migration que faz `Schema::create(...)` seguido de `$table->foreign(...)`:

1. `CREATE TABLE` executa → tabela existe em prod
2. `ALTER TABLE ADD FOREIGN KEY` falha → exception
3. Laravel **não consegue rollback** (DDL não transacional)
4. Migration **NÃO é registrada** em `migrations` table (Laravel só insere após sucesso completo)

Resultado: tabela órfã (existe + sem FK + sem registro em migrations).

**Sintomas:** próximo `migrate --force`:
```
SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'comvis_materiais' already exists
```
Migrate aborta na 1ª migration falha, bloqueando todas as próximas (sequencial).

**Recovery (com 0 rows — seguro):**

**Exige autorização explícita Wagner** (DROP TABLE é destrutivo per CLAUDE.md proibições).

```sql
DROP TABLE comvis_materiais;
```
```bash
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan migrate --force'
```

Migration corrigida re-cria tabela com schema certo + FK + registra em migrations. Idempotente.

**Recovery (com rows > 0):** NÃO fazer DROP. Opções:
1. Adicionar `if (!Schema::hasColumn(...))` na migration corrigida pra ser idempotente
2. INSERT manual em `migrations` table → Laravel pula migration (drift permanente — ruim)
3. Backup → DROP → re-migrate → re-INSERT rows (ideal mas trabalhoso)

**Cenário inverso — tabela some mas migrations table tem entry `Ran`:**

2026-05-10 tarde: `nfe_fiscal_rules` + `nfe_fiscal_rule_tax_rate_links` SUMIRAM do MySQL local Laragon mas migrations table tinha entry. Causa: tests destrutivos antigos com `Schema::dropIfExists` em afterEach derrubaram em runs passados.

```php
// fix-stale-migrations-log.php — 1-shot pra recuperar drift
DB::table('migrations')
    ->whereIn('migration', [
        '2026_05_06_010000_create_nfe_fiscal_rules_table',
        '2026_05_06_020000_create_nfe_fiscal_rule_tax_rate_links_table',
    ])
    ->delete();
// php artisan migrate
```

**Prevenção:**
- Sempre rodar migrations primeiro em DB MySQL local (Laragon `oimpresso` com FK strict) antes de push pra main. SQLite local não enforce FK type matching
- **Não usar `Schema::dropIfExists` destrutivo em afterEach de tests** quando schema real existe em MySQL — usa pattern dual-mode (ver tests-pest-canon.md)
- FK pra business.id sempre `unsignedInteger` (NÃO BigInteger) — ver ultimatepos-integracao.md

**Histórico:**
- 2026-05-10 manhã: `comvis_materiais` órfã após PR #461 (BigInteger errado). DROP autorizado Wagner. DROP + re-migrate aplicou 5 migrations limpas (4 comvis_* + ENUM nfe_emissoes que estava bloqueada)
- 2026-05-10 tarde: cenário inverso (tabelas NfeBrasil sumiram, migrations entry presente)

## 5. Ordem canônica deploy completo (com mudança composer.json)

```bash
# 1. Workflow auto (push → main → quick-sync.yml)
git push origin main

# 2. Verificar workflow status
gh api 'repos/wagnerra23/oimpresso.com/actions/workflows/quick-sync.yml/runs?per_page=1' \
  --jq '.workflow_runs[0] | {status, conclusion, head_sha}'

# 3. Se composer.json/lock mudou — SSH manual (workflow não faz):
ssh ... 'cd ~/domains/oimpresso.com/public_html && \
  composer install --optimize-autoloader --no-interaction'

# 4. Migrate (se schema mudou):
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan migrate --force'

# 5. Limpar caches:
ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan optimize:clear'

# 6. Avisar Wagner pra hard reload Chrome (Ctrl+Shift+R) — bundle stale local
```

Refs SSH: hostinger.md (IP, key, repo path).

## 6. Bug latente Controller authorize — Illuminate\Routing\Controller vs App\Http\Controllers\Controller

**Sintoma reproduzido (Wave 7 hotfix PR #1211, 2026-05-20):**

Drawer JSON novo (ServiceOrderSheet via fetch Accept-aware) começou a hitar `show()` que tinha `$this->authorize('view', $order)` desde Wave 15 (Security hardening) — mas sempre via Inertia HTML page, que era servida via outro fluxo de middleware que mascarava a exception. Drawer JSON expôs o crash real:

```
Method Modules\OficinaAuto\Http\Controllers\ServiceOrderController::authorize does not exist
```

**Causa raiz:** Controllers em `Modules/<X>/Http/Controllers/*.php` que precisam de `$this->authorize()`, `$this->validate()` OU `$this->dispatch()` DEVEM extender `App\Http\Controllers\Controller` (projeto canon que já inclui os 3 traits via `AuthorizesRequests`, `ValidatesRequests`, `DispatchesJobs`).

**Anti-pattern (FALHA silenciosa até alguém chamar o método):**

```php
namespace Modules\OficinaAuto\Http\Controllers;

use Illuminate\Routing\Controller;  // ❌ Base raw, sem traits

class ServiceOrderController extends Controller
{
    public function show($order)
    {
        $this->authorize('view', $order);  // 💥 Method does not exist
    }
}
```

**Fix canônico:**

```php
namespace Modules\OficinaAuto\Http\Controllers;

use App\Http\Controllers\Controller;  // ✅ Canônico — inclui traits

class ServiceOrderController extends Controller
{
    public function show($order)
    {
        $this->authorize('view', $order);  // ✅ Funciona
    }
}
```

**Auditoria preventiva:** procure todos os Controllers em `Modules/` que combinam:

```bash
# Combos que indicam o bug — quando aparecem juntos, FIX OBRIGATÓRIO:
grep -rln 'use Illuminate\\Routing\\Controller' Modules/ | while read f; do
  if grep -l 'this->authorize\|this->validate' "$f" >/dev/null; then
    echo "POSSIVEL BUG: $f"
  fi
done
```

**Lição operacional:** bug latente revelado por feature nova (drawer JSON Wave 7-A) que adicionou novo fetch path numa rota pré-existente. **Smoke browser MCP pós-deploy** (skill `smoke-prod-evidence`) descobre essa categoria de regressão escondida — sem isso, o 500 ficou ~7 dias sem detecção em prod biz=1.

## 7. Pattern try-catch diagnose pra investigar 500 opaco em prod

**Disparo:** endpoint retorna `500 Server Error` sem mensagem, browser DevTools / Network não revela trace, SSH com senha não acessível pra `tail laravel.log`. Adicionar wrapper try-catch temporário no método ofensor retornando exception trace JSON.

**Template canônico (extraído PR #1209 task #24):**

```php
public function show(Request $request, ServiceOrder $order)
{
    if (! $request->wantsJson()) {
        // Branch Inertia HTML — unchanged
        return Inertia::render('...');
    }

    try {
        // Conteúdo original do branch JSON
        $this->authorize('view', $order);
        $order->load([...]);
        return response()->json([... payload ...]);
    } catch (\Throwable $e) {
        \Log::emergency('[diagnose task #N]', [
            'order_id' => $order->id ?? null,
            'msg' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'class' => get_class($e),
        ]);

        return response()->json([
            '__debug_diagnose_task_N' => true,  // flag pra identificar resposta no smoke
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'class' => get_class($e),
            'trace' => collect($e->getTrace())
                ->take(10)
                ->map(fn ($t) => [
                    'file' => $t['file'] ?? null,
                    'line' => $t['line'] ?? null,
                    'function' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
                ])
                ->all(),
        ], 200);  // 200 pro navegador parsear como JSON
    }
}
```

**Fluxo:**

1. Aplicar wrapper localmente — commit + push branch `hotfix/<modulo>-<endpoint>-diagnose`
2. PR + merge + Deploy to Hostinger (workflow_dispatch — composer/migrate/cache)
3. Hit endpoint em prod via browser MCP `mcp__Claude_in_Chrome__javascript_tool` com `fetch(...)` + ler JSON
4. Identifica causa raiz no trace + file:line
5. **Hotfix real** em branch separada — corrige causa + REMOVE o wrapper try-catch
6. PR + merge + Deploy + smoke validando resposta sem `__debug_diagnose_task_N` flag

**Lição:** flag `__debug_diagnose_task_N` no payload + status 200 (não 500) permite distinguir "endpoint corrigido" vs "wrapper ainda ativo" no smoke pós-fix. Caso real PR #1209 (diagnose) → PR #1211 (fix authorize trait).

Refs SSH: hostinger.md (IP, key, repo path).
