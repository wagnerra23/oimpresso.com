---
name: Deploy Hostinger + recovery patterns (composer install, cache stale, orphan tables, quick-sync fallback)
description: Receitas operacionais pós-deploy Hostinger. composer install obrigatório se composer.json/lock muda (quick-sync.yml NÃO faz). Tela branca Inertia pós optimize:clear = cache stale do bundle (hard reload resolve). Recovery tabela órfã (DDL MySQL não-transacional). Quick-sync fallback SSH manual.
type: reference
---
# Deploy + recovery patterns (Hostinger)

> **⚠️ STATUS 2026-06-10 — o caminho de deploy MUDOU ([ADR 0269](../decisions/0269-deploy-automatico-build-no-runner.md)).** A partir de agora **`deploy.yml` dispara AUTOMÁTICO em push pra main** (exceto docs: `memory/**`, `**.md`, `prototipo-ui/**`, `cowork-inbox/**`), com o **JS buildado NO RUNNER** (ubuntu, não no Hostinger) + publish atômico + OPcache reset **obrigatório** + smoke que valida hash de bundle. **Merge agora = publicado** pra mudanças não-doc — some a pegadinha "merge ≠ publicado" das §2.1/§2.2 abaixo. Consequências práticas:
> - **`quick-sync.yml` perdeu o trigger `push`** (virou `workflow_dispatch`-only). Toda referência abaixo a "quick-sync auto on push" está **superada** — quem auto-roda no merge é o `deploy.yml`.
> - O **build no shared host** (causa raiz dos 500/hashes stale, §2.3) **saiu do fluxo normal** — só sobra no `force-clean-rebuild` (nuclear manual). **Não usar `force-clean-rebuild` no fluxo padrão** — ele rebuilda no Hostinger, exatamente o que o 0269 elimina.
> - As receitas de **recovery manual abaixo continuam válidas como FALLBACK** quando o auto-deploy falha (SSH flaky, etc.) — mas confira primeiro `gh run list --workflow=deploy.yml --limit 1`, não o quick-sync.

Consolidação de receitas operacionais que se entrelaçam em sessões de deploy. Validadas várias vezes em 2026-04-25 → 2026-05-10.

> **⚠️ MUDANÇA DE POLÍTICA 2026-06-10 (ADR 0269) — ler antes do resto:** o auto-deploy canônico em push pra main passou a ser o **`deploy.yml`** (`Deploy to Hostinger`), que builda o JS **no runner** (ubuntu, determinístico) e publica os bundles via tar/ssh. O **`quick-sync.yml` perdeu o trigger `push`** (virou escape manual `workflow_dispatch`-only). Várias receitas abaixo (§2, §2.1-2.4, §5) descrevem o mundo "quick-sync auto + build no Hostinger" — continuam válidas como **recuperação manual** e pro escape `quick-sync`, mas o caminho-padrão agora é o auto-deploy do `deploy.yml`. Ver §8.

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

**Causa raiz:** `quick-sync.yml` **NÃO roda** `composer install` (deps) nem `php artisan migrate`. Quando PR adiciona método novo em Controller existente, classe nova, ou novo binding de FQCN, o **composer autoload** de prod fica desatualizado → Laravel não consegue resolver classes → ServiceProvider crash → 500 cascateando em todo módulo.

> **⚠️ Atualização 2026-06-03 (PR #2162 — supera parte do que está abaixo):** o `quick-sync.yml` passou a rodar **`composer dump-autoload --no-scripts`** após o `git reset --hard`. Isso **regenera o autoload de classe/método novo** — então o sintoma "classe nova → 500" descrito aqui **deixou de exigir o Deploy completo**. O `Deploy to Hostinger` (full) **só** é obrigatório agora pra (a) **nova dependency** em `composer.json`/`.lock` (`composer install`) ou (b) **migration nova** (`artisan migrate`). A matriz abaixo foi ajustada.

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
| Controller — método novo OU classe nova OU trait composition | ✅ **Sim** (após PR #2162) | quick-sync agora roda `composer dump-autoload` → regenera autoload de classe nova. (Antes exigia Deploy.) |
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

### 2.3 Build do quick-sync falha + ESVAZIA build-inertia → site 500 (estouro de threads Hostinger)

**Disparo:** PR mergeado, quick-sync roda, mas `/login` (e tudo) retorna **500**. `public/build-inertia/` ficou **vazio** e `manifest.json` **sumiu**.

**Causa raiz (incident 2026-06-03):** o step de build (`npm run build:inertia`) usa Tailwind v4 / lightningcss / oxide — todos **rayon (Rust)** que tenta criar um thread pool grande. No **shared hosting** o limite de threads/processos estoura:
```
ThreadPoolBuildError { kind: IOError(... code: 11 ... "Resource temporarily unavailable") }
fatal runtime error: failed to initiate panic
```
Vite roda `emptyOutDir` **antes** de buildar → quando o build morre, `public/build-inertia/` fica vazio → `@vite`/PHP não acha o `manifest.json` → **500 em todo o app** (não só Inertia). Pior que stale: é **outage**.

**Fix imediato (restaura o site):** rebuild **single-thread**:
```bash
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done   # warm-up
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && \
   export NVM_DIR=~/.nvm && . "$NVM_DIR/nvm.sh" && \
   export RAYON_NUM_THREADS=1 && \
   npm run build:inertia && php artisan optimize:clear'
```
`RAYON_NUM_THREADS=1` força single-thread → não estoura. Custo ~35s (vs ~50s). Validado 2026-06-03: build que falhava com erro de thread passou limpo.

**Fix permanente:** `quick-sync.yml` exporta `RAYON_NUM_THREADS=1` antes dos 2 `npm run build*` — **PR #2183**.

### 2.4 Recuperação manual canônica do quick-sync (consolidado 2026-06-03)

Quando o `quick-sync` falha (qualquer um dos 3 modos: Setup SSH flaky §2 · classe nova sem autoload §2.2 · estouro de threads §2.3), a recuperação manual completa e idempotente é:
```bash
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done   # warm-up SSH flaky
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
  -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && \
   export NVM_DIR=~/.nvm && . "$NVM_DIR/nvm.sh" && export RAYON_NUM_THREADS=1 && \
   git fetch origin && git reset --hard origin/main && \
   composer dump-autoload --no-scripts && \
   npm run build:inertia && \
   php artisan optimize:clear'
```
Ordem importa: `dump-autoload` ANTES dos `php artisan` (senão classe nova → BindingResolutionException, §2.2). Depois confirmar no disco (não via HTTP, que tem cache LiteSpeed): `grep -rl "<string nova>" public/build-inertia/assets/*.js` + `ls -la public/build-inertia/manifest.json`. **PHP lê o manifest do disco** ao renderizar — o `manifest.json` servido por HTTP pode estar cacheado e enganar.

> **Hardening do quick-sync.yml (PRs):** `composer dump-autoload` + `ssh-keyscan ... || true` (**#2162**) · `RAYON_NUM_THREADS=1` no build (**#2183**). Após esses merges os 3 modos de falha do auto-deploy ficam cobertos.

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

## 8. Merge ≠ publicado — auto-deploy unificado + build no runner (2026-06-10, ADR 0269)

**Lição central:** *"Merge ≠ publicado"*. Até 2026-06-10 publicar em prod exigia orquestrar workflows na mão — `deploy.yml` (manual: composer/migrate, mas **não** buildava JS) + `force-clean-rebuild` — e **o JS buildava no shared host** (npm no Hostinger), causa raiz dos hashes stale (20/05) e do 500 por estouro de threads (§2.3, 03/06). O `quick-sync.yml` até auto-disparava, mas era leve (sem composer/migrate) e também buildava no servidor.

**O que mudou (ADR 0269):**
- **Auto-trigger:** push em main (exceto `memory/**`, `**.md`, `prototipo-ui/**`, `cowork-inbox/**`) dispara o `deploy.yml` sozinho. Merge na main = publicado.
- **Build no runner:** job `build` em ubuntu-latest (node 24 + `npm ci` + `build:inertia` + `build`) → artefato → job `deploy` envia via **tar/ssh + swap atômico** (`.new` → `mv`, mantém `.old` pra rollback). Acabou o build frágil no Hostinger.
- **`quick-sync.yml`** perdeu o `push` (escape manual only) pra não rodar deploy concorrente (mesma concurrency `deploy-production`).
- **OPcache reset OBRIGATÓRIO** (warning → falha): secret `OPCACHE_RESET_TOKEN` criado; deploy grava o token em `storage/app/opcache_reset_token` (fora do git/webroot, sobrevive a `git reset --hard`); `_ops_opcache_reset.php` lê dessa fonte (script PHP cru não lê o `.env` via `getenv()` no LSPHP). Só tolera `OPCACHE_UNAVAILABLE`.
- **Smoke valida hash de bundle:** compara `/assets/` servidos antes×depois; se `resources/js|css` mudou no push mas o hash não mudou → deploy vermelho (publicação não chegou).

**Pegadinha catalogada hoje (10/06):** ao rodar deploy.yml + force-clean manualmente pra "destravar", os **hashes dos bundles vieram idênticos** antes×depois (`app-PfSk7SD1.js` / `inertia-Bawe61gt.css`). Isso **não é bug** — hash do Vite é determinístico do conteúdo: um cold rebuild do `main` reproduzir o mesmo hash **prova que o prod já servia o `main` atual**. Hash idêntico após rebuild = prod já estava publicado, não há o que "republicar" no nível do bundle. Se o operador ainda vê tela velha com hash igual, o problema **não é o bundle** (é dado server-side, OPcache, ou cache de navegador) — investigar lá, não rebuildar de novo.

**Redes de segurança mantidas:** backup com rotação (5 mais recentes), maintenance on/off, composer (sem `--no-dev` — Faker em prod), migrate, caches (sem `route:cache`, hotfix 27/05), smoke estrito.

**Incidente do 1º auto-deploy (10/06, corrigido no mesmo dia):**
1. **`npm run build` NÃO sai em `public/build/`.** O build legado (vite.config.js) emite **`public/css/tailwind.css`** (e nada em `public/build/`). O publish do deploy testava `test -d artifact/build` sob `set -e` → falhou em ~6ms. **Os dois artefatos a publicar são `public/build-inertia/` (dir Inertia) + `public/css/` (Tailwind legado)** — ambos gitignored (`/public/build-inertia/` + `/public/css/`). `build-inertia` faz swap de diretório; `css` é copiado por cima.
2. **Site preso em 503.** O publish falhou DEPOIS do `php artisan down` e ANTES do `up` → maintenance preso até intervenção manual. **Restauração rápida** (sem precisar consertar o bug): `gh workflow run deploy.yml -f skip_backup=true -f skip_migrate=true -f extra_artisan=up` — o step `Artisan extra` roda `php artisan up` antes do publish quebrar. **Fix permanente:** step `Failsafe` com `if: always()` que garante `php artisan up` em qualquer saída (invariante "auto-deploy nunca deixa o site em maintenance").
3. **Bundles do servidor sobrevivem.** `git reset --hard` NÃO toca arquivos gitignored → `public/build-inertia/` e `public/css/` do último build válido permanecem. Por isso destravar o maintenance já restaura o site funcional, mesmo com o publish do deploy falho.

Refs: ADR 0269; `.github/workflows/deploy.yml`; `public/_ops_opcache_reset.php`.

## 9. Deploy "preso" 15-30min / falso-sucesso — flake do SSH Hostinger no Pré-deploy check (RECORRENTE · lição 2026-06-20)

> Modo de falha MAIS comum do auto-deploy. Bateu **3x numa sessão só** (2026-06-20). É a **65002 (porta SSH) flakando**, NÃO bug do código que você acabou de mergear.

### Assinatura (reconhecer em 30s)
- Deploy `in_progress` por **15-30min+** (normal é ~7min).
- `gh run view <id> --json jobs`: passo **"Pré-deploy check — server state" = failure**, e TODOS os passos de deploy (Backup / Maintenance mode ON / Git pull / Composer / Publicar bundles / Smoke) = **skipped**; o run fica preso no **"Failsafe" (`if: always()`)** que faz SSH e trava.
- **Prod 200 (saudável) no build ANTIGO** — o fail-safe funcionou: NADA foi aplicado.
- Prova de que NÃO subiu: `curl https://oimpresso.com/build-inertia/manifest.json` -> ache o chunk (`Index-XXXX.js` / `ComposerV4-XXXX.js`) -> `grep` do marcador novo (testid/string da mudança) = **0**.

### Causa-raiz
A rota SSH do Hostinger (**porta 65002**) cai/flaka intermitente **enquanto a 443/HTTPS segue 200** (por isso o site responde mas o deploy não conecta). O Pré-deploy check faz SSH via `ssh_exec.sh` (`-4 ConnectTimeout=180 ConnectionAttempts=3`) e falha quando a 65002 está fora na janela. Mesmo flake de `hostinger.md` §"warm-up + retry".

### Recuperação (canon — NÃO martelar = risco de ban)
1. Confirme a assinatura (pré-check failure + steps skipped + prod 200 antigo + chunk vivo sem o marcador).
2. Probe da rota: `timeout 12 bash -c 'cat </dev/null >/dev/tcp/148.135.133.115/65002'` -> conectou = rota voltou.
3. `gh run cancel <id>` no run preso (nada aplicado -> cancelar é seguro). O Failsafe ignora o cancel por ~min até o GitHub forçar; o deploy enfileirado assume.
4. **Re-disparar UMA vez** com a rota de pé: `gh run rerun <id>` ou `gh workflow run deploy.yml --ref main`. Passa de primeira quando a 65002 está no ar.
5. **NÃO** re-disparar em loop. **NÃO** encurtar ConnectTimeout (lição cara 2026-06-11 — piorou; ver `hostinger.md`).

### Por que recorre + fix SEGURO proposto (deploy.yml)
É inerente: 65002 flaky + design fail-safe (correto — não aplica nada se o server não responde). O deploy **falhar** está certo; o que incomoda é (a) **travar ~30min no Failsafe** e (b) recuperação manual.
- **Fix seguro (sem encurtar timeout, sem hammering):** o Failsafe (`if: always()`) só precisa de SSH se o deploy chegou a ligar maintenance. Quando o Pré-deploy check falha, **maintenance NUNCA ligou -> site já está up -> o Failsafe não precisa SSHar**. Gatear o SSH do Failsafe pra só rodar se o passo "Maintenance mode ON" tiver executado (dar `id:` ao passo e usar `if: always() && steps.<id>.outcome == 'success'`). Assim, flake no pré-check = run **falha rápido** (sem pendurar 30min), sem tocar em timeout.
- **NÃO** encurtar o ConnectTimeout do Failsafe: se a rota está só LENTA (não DOWN), ele precisa do tempo pra rodar `php artisan up` e não deixar o site em 503 (motivo de existir — §8 / 2026-06-10).

Cross-ref: `hostinger.md` (flags SSH canônicos · "esperar a rota voltar e re-disparar UMA vez").

## 10. Merge-flurry → janela "source à frente do classmap" → cron `schedule:run` crasha (2026-06-20)

> Achado durante o incidente `procedure_drift` ([session 2026-06-20](../sessions/2026-06-20-incidente-procedure-drift-false-positivo.md)). **Web 200 o tempo todo, mas TODO `php artisan` — incluindo o cron — fatala no boot.** Não é deploy quebrado; é uma **janela transitória** que se repete durante um flurry de merges.

### Assinatura (reconhecer em 30s)
- `php artisan <qualquer>` (incl. cron `schedule:run` a cada minuto, e `jana:health-check` 06:00) fatala:
  `Illuminate\Contracts\Container\BindingResolutionException — Target class [Modules\…\AlgumCommand] does not exist`.
- `grep -c AlgumCommand vendor/composer/autoload_classmap.php` = **0**, **enquanto** o arquivo `.php` existe em disco e o namespace/PSR-4 estão corretos.
- **`/login` (web) segue 200** — comandos são lazy no kernel HTTP; o crash é só no console.
- **A classe que falha MUDA entre observações** (segue o último merge: 20/06 foi `McpTasksOrphansCommand` #3106 → depois `ProfileDistillCommand` #3115). Janela móvel ≠ deploy único interrompido.

### Causa-raiz
O `deploy.yml` faz `git reset --hard origin/main` (avança o **source** pro tip atual de main) e **só depois**, num passo separado e mais lento, `composer dump-autoload -o --classmap-authoritative` (regenera o **classmap**). Numa **rajada de merges** (20/06: ~12 deploys em ~40min) com Hostinger lento (~min/deploy), o source roda à frente do classmap. Como o classmap é **authoritative** (PSR-4 fallback **desligado**), classe nova fora dele é **irresolvível**. O OS cron dispara `php artisan schedule:run` a cada minuto e cai na janela. O crash é no **boot do console kernel** (resolve `commands([...])` do provider via `Artisan::starting`→`resolveCommands`→`make()`) — **antes** de qualquer checagem de maintenance mode, então `php artisan down` **não** protege o cron.

### Por que o boot-smoke (§deploy, #2912/#2952) NÃO cobre
O boot-smoke console (`php artisan about` pós-dump-autoload) + o failsafe boot-gated garantem que o **deploy** não declare sucesso sobre um classmap stale (falha vermelho / segura 503). Mas a janela vulnerável é **durante/entre os deploys do flurry**, e o **cron externo não passa pelo deploy**. O guard protege o sinal do deploy, não o runtime do cron.

### Recuperação
- **Preferir DEIXAR a fila drenar.** Cada deploy roda seu próprio `dump-autoload` contra o source final → o classmap casa e o crash para sozinho (self-heal confirmado 20/06). Confirmar: `php artisan about` boota + `grep -c <Command> vendor/composer/autoload_classmap.php` = 1.
- **Só reconciliar manual se ficar stale com a fila VAZIA** (após warm-up curl 5×, receita `hostinger.md`):
  ```bash
  composer dump-autoload -o --classmap-authoritative --no-scripts \
    --ignore-platform-req=ext-opentelemetry --ignore-platform-req=ext-sodium
  ```
  **Mirror do canon do deploy** — NÃO usar `-o` puro (desliga authoritative, diverge do estado canônico até o próximo deploy). `--ignore-platform-req` evita o abort do dump-autoload standalone (lição 2026-06-10, §2.4/`deploy.yml`).
- **CUIDADO com deploy in_progress:** rodar composer manual concorrente ao composer do deploy pode correr na escrita de `vendor/composer/autoload_classmap.php`. Se há deploy rodando, deixe-o terminar.

### Revisão do modelo de concorrência (pedido Wagner 2026-06-20)
- `deploy.yml` usa `concurrency: { group: deploy-production, cancel-in-progress: false }`. Serializa e **descarta runs PENDENTES superados** (só o mais novo pendente sobrevive); o in_progress sempre completa. Na prática os ~12 triggers de 20/06 rodaram ~4 deploys reais (resto cancelado pendente) — o modelo **já coalesce** razoável.
- **NÃO trocar pra `cancel-in-progress: true`** num deploy de PROD. Cancelar um deploy no meio (git reset / composer dump / swap de bundle / maintenance ON) deixa estado parcial — inclusive o próprio classmap **corrompido** (a falha deste incidente). O `false` é proposital.
- **Causa comportamental raiz = cadência de merge.** 12 merges code-touching em ~40min num shared host lento garante janelas sobrepostas. **Mitigação primária (zero código): serializar/bachar merges** — mergear, deixar `gh run watch` do deploy fechar (~7min), só então o próximo. Crítico à noite/madrugada quando ninguém observa o cron.
- **Defesa-em-profundidade (opcional): gatear o cron.** Como o crash é no boot, o único jeito de poupar o cron na janela é um guard **shell** (sem bootar artisan) na linha do crontab do Hostinger, que pula quando o app está em maintenance:
  ```bash
  [ -f storage/framework/down ] || [ -f storage/framework/maintenance.php ] || /usr/bin/php artisan schedule:run
  ```
  (confirmar o flag exato em prod). Silencia o cron **durante** deploys (que ligam maintenance) — não cobre janela fora de maintenance, mas mata o ruído/jobs-perdidos do caso comum.
- **Anti-padrão:** NÃO adicionar `dump-autoload` ao deploy (já roda, incondicional) nem outro boot-smoke (já existe, #2912/#2952). O gap é **runtime/cadência**, não deploy-time.

Cross-ref: `hostinger.md` (SSH/composer/php paths) · [session 2026-06-20](../sessions/2026-06-20-incidente-procedure-drift-false-positivo.md) · `deploy.yml` (boot-smoke console #2912 · failsafe boot-gated #2952).
