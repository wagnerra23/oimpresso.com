---
name: mwart-quality
description: Use ANTES de criar/editar tela MWART (Module Web App React Transition Blade→Inertia/React) no oimpresso. Ativa quando user pede "migrar tela X pra MWART", "S2.5/S2.6 tela", "nova tela Inertia em Modules/", OU em qualquer Edit em `Modules/<X>/Http/Controllers/*Controller.php` que chama `Inertia::render(...)`, OU em `resources/js/Pages/<Module>/**/*.tsx`. Carrega 9 pré-flight checks que evitam os 5 padrões de bug recorrentes detectados nos PRs #138-#145 (route() Ziggy não definido / shape backend↔frontend mismatch / Schema column não existe / CommonChart objeto onde TSX espera array / Component shared DS prop contract). Cada bug do passado custou 1 round-trip Wagner=tela branca em prod. Substitui leitura repetida dos commits b34aa821 + 102dc1f9 + 7581802d + 145.
trust_level: L2
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
tier: B
parent_adr: 0095
---

# MWART Quality — pré-flight checks pra evitar retrabalho

> Wagner alertou em 2026-05-07: *"tem que aumentar a qualidade desses modelos, e ficar melhor antes de fazer as outras páginas vai gerar retrabalho."* Esta skill codifica os 5 padrões de bug que apareceram nas 4 telas Repair S2.5 (PRs #138-#141) e custaram 3 PRs corretivos (#143, #144, #145) — todos detectados só após Wagner abrir a tela em prod e ver branco/erro.

## Quando ativa

- Edit em `Modules/<X>/Http/Controllers/*Controller.php` que chama `Inertia::render('<Modulo>/<Tela>/Index', [...])`
- Edit em `resources/js/Pages/<Module>/**/*.tsx`
- Pedido explícito: "migrar tela X pra MWART", "Sprint 2.5/2.6 nova tela", "port Blade pra Inertia"
- Adição de nova entrada em `config/mwart.php` (`'<modulo>_<tela>_index' => [...]`)

## Padrão MWART canônico

**Pattern Strangler Fig (Martin Fowler) com feature flag.** Mesmo controller, branch interno baseado em `mwartEnabled('<key>', $business_id)`:

```php
public function index() {
    // ... carrega dados (compartilhado entre Blade e Inertia)
    
    if ($this->mwartEnabled('repair_status_index', (int) $business_id)) {
        return Inertia::render('Repair/Status/Index', [/* shape limpo */]);
    }
    return view('repair::status.index')->with(/* legacy */);
}

private function mwartEnabled(string $key, int $business_id): bool {
    if (! config("mwart.{$key}.enabled")) return false;
    $beta = (array) config("mwart.{$key}.business_ids", []);
    return empty($beta) || in_array($business_id, $beta, true);
}
```

## 9 pré-flight checks (executar ANTES de marcar PR done)

### Check 1 · Backend NÃO passa Eloquent collection raw pro Inertia

**❌ Errado** — Inertia serializa Eloquent magic, mas TSX recebe shape imprevisível:
```php
return Inertia::render('Repair/Dashboard/Index', [
    'job_sheets_by_status' => $repairUtil->getRepairByStatus($business_id),  // Eloquent\Collection
]);
```

**✅ Certo** — transformar pra array de objetos planos com chaves explícitas:
```php
'job_sheets_by_status' => collect($result)->map(fn ($r) => [
    'status' => $r->status_name ?? '—',
    'count' => (int) $r->total_job_sheets,
])->values()->all(),
```

**Razão:** o TSX declara `interface { status: string; count: number }` — se backend manda `{status_name, total_job_sheets}`, render quebra silencioso ou mostra `undefined`.

**Bug evitado:** Dashboard `i.slice is not a function` (PR #145).

### Check 2 · NÃO passar objeto CommonChart/Highcharts pra Inertia

**❌ Errado:**
```php
$trending_brand_chart = $this->repairUtil->getTrendingRepairBrands($business_id);  // CommonChart instance
return Inertia::render(..., ['trending_brand_chart' => $trending_brand_chart]);  // serializa como objeto Highcharts
```

**✅ Certo** — re-query inline ou refactor pra retornar array puro:
```php
$trendingBrands = JobSheet::leftJoin('brands', 'repair_job_sheets.brand_id', '=', 'brands.id')
    ->where('repair_job_sheets.business_id', $business_id)
    ->whereNotNull('repair_job_sheets.brand_id')
    ->select('brands.name as brand', DB::raw('COUNT(repair_job_sheets.id) as count'))
    ->groupBy('brands.id')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get()
    ->toArray();
return Inertia::render(..., ['trending_brand_chart' => $trendingBrands]);
```

**Bug evitado:** Dashboard `TypeError: i.slice is not a function` (PR #145) — porque CommonChart não tem `.length` nem `.slice()`.

### Check 3 · SELECT só colunas que existem em prod

**❌ Errado:**
```php
DeviceModel::select(['id', 'name', 'description', 'device_id', ...])  // 'description' não existe na tabela
```

**✅ Certo** — verificar com `Schema::hasColumn` ou abrir o `Modules/<X>/Database/Migrations/*create_<tabela>*.php`:
```bash
grep -E "string\(.description.\)|text\(.description.\)" Modules/Repair/Database/Migrations/*create_device_models*
# Sem hit → coluna não existe → não selecionar
```

**Se a feature exige a coluna**: criar migration **no mesmo PR** + rodar em prod ANTES de mergear (skill `runtime-rules-hostinger-ct100`).

**Bug evitado:** DeviceModels `SQLSTATE[42S22] Column 'description' not found` (PR #145).

### Check 4 · Defensive `Array.isArray` guard em TSX que recebe coleções

**❌ Errado:**
```tsx
function SimpleListCard({ items }) {
  return items.length === 0 ? <Empty/> : items.slice(0, 10).map(...)
}
```

**✅ Certo** — proteção contra type drift do backend:
```tsx
function SimpleListCard({ items }) {
  const list = Array.isArray(items) ? items : [];
  return list.length === 0 ? <Empty/> : list.slice(0, 10).map(...)
}
```

**Razão:** Inertia serialização pode mandar `null`, `{}`, ou objeto Highcharts onde TS declara `T[]`. Crash → tela branca → Wagner vê.

### Check 5 · Componente `@/Components/shared/PageHeader` API

**❌ Errado:**
```tsx
<PageHeader
  title="..."
  subtitle="..."        // ❌ NÃO existe — é "description"
  actions={<Button/>}   // ❌ NÃO existe — é "action" (singular)
/>
```

**✅ Certo:**
```tsx
<PageHeader
  icon="wrench"          // string kebab-case lucide
  title="..."
  description="..."      // ✅ singular
  action={<Button/>}     // ✅ singular, ReactNode
/>
```

### Check 6 · `EmptyState` e `KpiCard` aceitam `icon: string`, NÃO `ReactNode`

**❌ Errado:**
```tsx
<EmptyState icon={<Smartphone className="h-12 w-12" />} title="..." />
```

**✅ Certo:**
```tsx
<EmptyState icon="smartphone" title="..." />  // string kebab-case
```

**Bug evitado:** componentes shared crashavam com `<icon>.type is undefined` (PR #143).

### Check 7 · Icons via `<Icon name="kebab-case" />`, NÃO import direto de `lucide-react`

**❌ Errado:**
```tsx
import { Plus, Smartphone, ListChecks } from 'lucide-react';
<Plus className="mr-2 h-4 w-4" />
```

**✅ Certo:**
```tsx
import { Icon } from '@/Components/Icon';
<Icon name="plus" className="mr-2 h-4 w-4" />
```

**Razão:** o componente `<Icon>` resolve via mapa interno (suporte a fallback, lazy load, e PageHeader/EmptyState esperam string).

### Check 8 · `route()` Ziggy NÃO está disponível neste projeto

**❌ Errado:**
```tsx
<Link href={route('job-sheet.create')}>Nova OS</Link>
```

Causa `ReferenceError: route is not defined` em runtime → tela 100% branca.

**✅ Certo** — URL hardcoded baseada no `RouteServiceProvider` do módulo (`Routes/web.php` tem `Route::prefix('repair')->group(...)`):
```tsx
<Link href="/repair/job-sheet/create">Nova OS</Link>
<Link href={`/repair/status/${s.id}/edit`}>Editar</Link>
```

**Razão:** `tightenco/ziggy` não está instalado em `composer.json` e `inertia.blade.php` não tem `@routes` directive.

**Bug evitado:** 3 telas brancas em prod (PR #144).

### Check 9 · Smoke test em prod ANTES de marcar PR done

Após PR mergear + GH Action `build-inertia-auto.yml` rodar (~30-45s):

```
mcp__Claude_in_Chrome__browser_batch:
  - navigate https://oimpresso.com/<modulo>/<tela>?cachebust=N
  - wait 3s
  - screenshot
  - read_console_messages pattern:"error|Error|exception|TypeError|ReferenceError"
```

**Critério done**: screenshot mostra render correto + console sem nenhum exception.

Sem este check, bugs só aparecem quando o usuário (Wagner/Larissa) abre. **Foi exatamente o ciclo que custou os PRs #143/#144/#145.**

## Receita pré-PR (cole no checklist mental)

```
[ ] Check 1 — backend mapeia coleções pra arrays planos com chaves explícitas
[ ] Check 2 — nenhum CommonChart/Highcharts no payload Inertia
[ ] Check 3 — todas colunas no SELECT existem (grep migration ou Schema::hasColumn)
[ ] Check 4 — TSX que recebe T[] tem Array.isArray guard
[ ] Check 5 — PageHeader usa icon=string + description + action (singular)
[ ] Check 6 — EmptyState/KpiCard usam icon=string
[ ] Check 7 — todos icons via <Icon name="..."/>, zero import lucide-react direto
[ ] Check 8 — zero route() Ziggy; URLs hardcoded com prefix do módulo
[ ] Check 9 — após merge, smoke test browser MCP + console clean
```

## ROI / por que esta skill existe

| Sprint | PRs corretivos | Causa | Cobertura desta skill |
|---|---|---|---|
| S2.5 PR #138 (Status) | #143 (DS contract), #144 (route()) | Checks 5,6,7,8 | ✅ |
| S2.5 PR #139 (DeviceModels) | #143, #144, #145 (Schema) | Checks 3,5,6,7,8 | ✅ |
| S2.5 PR #140 (Dashboard) | #143, #145 (CommonChart) | Checks 1,2,4,5,6,7 | ✅ |
| S2.5 PR #141 (JobSheet) | #143, #144 | Checks 5,6,7,8 | ✅ |

**4 PRs novos × 3 PRs corretivos = 12 round-trips.** Esta skill, se ativa antes do PR original, reduz pra 4 PRs limpos (1× cada tela).

## Próximo passo (P1, fora desta skill)

Instalar `tightenco/ziggy` formal + `@routes` em `inertia.blade.php`:
1. Habilita autocomplete de rotas no IDE
2. Refactor de URLs detectado em compile time
3. Resolve Check 8 estruturalmente (sem hardcoded URL)

Até lá, Check 8 hardcoded é o caminho.

## Referências

- Commit `b34aa821` (PR #143) — DS contract fix retroativo
- Commit `102dc1f9` (PR #144) — route() Ziggy fix retroativo
- Commit `7581802d` (PR #145) — Dashboard CommonChart + DeviceModels Schema fix retroativo
- [ROTEIRO-MESTRE.md §S2.5](../../memory/sprints/ROTEIRO-MESTRE.md) — escopo MWART
- [ADR 0011](../../memory/decisions/0011-alinhamento-padrao-jana.md) — imitar antes de criar
- Skill irmã: `criar-modulo` — análogo pra módulos Laravel novos
