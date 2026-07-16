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

> **Esta skill cobre F2 BACKEND BASELINE + F3 FRONTEND INCREMENTAL** do processo MWART canônico definido em [ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md). Skill irmã [`mwart-process`](../mwart-process/SKILL.md) (Tier A) carrega o processo completo em 5 fases. **Não use esta skill sem a F1 PLAN completa** — hook `block-mwart-violation.ps1` bloqueia Edit em `Pages/<Mod>/<Tela>.tsx` se RUNBOOK ausente.

> Wagner alertou em 2026-05-07: *"tem que aumentar a qualidade desses modelos, e ficar melhor antes de fazer as outras páginas vai gerar retrabalho."* Esta skill codifica os 5 padrões de bug que apareceram nas 4 telas Repair S2.5 (PRs #138-#141) e custaram 3 PRs corretivos (#143, #144, #145) — todos detectados só após Wagner abrir a tela em prod e ver branco/erro.

> **Estilo canônico** desta skill segue o pattern de [`cockpit-runbook`](../cockpit-runbook/SKILL.md): workflow obrigatório + fontes canônicas (Read paralelo) + Modo Audit + anti-padrões + canon visual concreto. Wagner reforçou em 2026-05-07: *"o design desenvolveu técnicas apuradas... ele criou um manual de como fazer uma skill com runbook de precisão seguindo o tutorial"* — manual = [DESIGN.md](../../DESIGN.md).

## Quando ativa (3 modos)

| Modo | Gatilho típico | Output |
|---|---|---|
| **A. Pre-flight** | "migrar tela X pra MWART", "Sprint 2.6 nova tela", "port Blade pra Inertia" | Aplica 10 checks ANTES do código + smoke test pós-merge |
| **B. Audit** | "audita tela MWART X", "essa tela MWART tá certa?", PR review | Relatório `file:line — check N violado — fix` |
| **C. Refactor** | "essa tela está feia", "perdeu elementos", feedback Wagner sobre tela MWART | Comparar contra canon visual + propor fix incremental |

## Fontes canônicas (Read em 1 rodada paralela ANTES de codar)

Carregar TODAS antes de criar/auditar tela MWART — economiza round-trips e evita retrabalho:

1. [DESIGN.md](../../DESIGN.md) — hub visual + §6-§15 padrão técnico React + hierarquia de decisões UI (8 níveis)
2. [_DesignSystem ui_kits/cowork-2026-04-27/os-page.jsx](../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) — **CANON VISUAL** list+detail (Wagner mostrou screenshot 07-mai dessa estrutura como "padrão bonito")
3. [_DesignSystem ui_kits/cowork-2026-04-27/](../../memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/) — outros canons: `tasks.jsx` (inbox), `chat.jsx` (conversação), `viewers.jsx` (master/detail)
4. [_DS UI-0010](../../memory/requisitos/_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) — formaliza zip Cowork como canon visual
5. [ADR 0039](../../memory/decisions/0039-ui-chat-cockpit-padrao.md) — Cockpit layout-mãe 3-colunas
6. [_DS UI-0008](../../memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md) — Cockpit como mãe ERP
7. [_DS UI-0023](../../memory/requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) — sidebar PRETA (dark-fixo) nos dois modos
8. [_DesignSystem/SPEC.md](../../memory/requisitos/_DesignSystem/SPEC.md) — regras R-DS-001..N (tokens, shadcn, lucide, dark mode)
9. [Skill cockpit-runbook](../cockpit-runbook/SKILL.md) — para gerar/auditar runbook detalhado por tela
10. **A própria tela alvo** — `Modules/<X>/Http/Controllers/*Controller.php` + `resources/js/Pages/<Module>/<Tela>.tsx`

## Workflow obrigatório (Modo A — pre-flight)

Copiar este checklist no thinking e marcar conforme avança:

```
- [ ] 1. Receber tela alvo + módulo (Modules/<X>/, Pages/<Module>/<Tela>.tsx)
- [ ] 2. Read em paralelo das 10 fontes canônicas (ver §Fontes)
- [ ] 3. Identificar TIPO de tela (list+detail / inbox / chat / CRUD clássico)
- [ ] 4. Abrir o canon .jsx correspondente em ui_kits/cowork-2026-04-27/ e LER os 200-400 linhas
- [ ] 5. Aplicar Checks 1-9 (técnicos) + Check 10 (paridade visual com canon)
- [ ] 6. Implementar tela imitando estrutura do canon (nomes de cells, badges, avatars, filtros, localStorage keys)
- [ ] 7. Commit + push + merge PR
- [ ] 8. Aguardar GH Action build-inertia-auto.yml completar (~30-45s)
- [ ] 9. Smoke test browser MCP — screenshot + console clean
- [ ] 10. Comparar lado-a-lado com canon: tela MWART vs `os-page.jsx` renderizado em `Oimpresso ERP - Chat.html` (abrir local)
```

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

## Check 11 · Parent dropdown precisa de `'url'` ou cai em href='#'

**Aplica a:** qualquer módulo cujo `DataController::modifyAdminMenu()` cria um `Menu::dropdown(...)` com children. Especialmente crítico pra módulos admin de plataforma (PR #516 promove `attributes.url` → `url` em `MenuItem::make`, então passa só passar `'url'` no 3º param).

**❌ Errado** — `Modules/<X>/Http/Controllers/DataController.php::modifyAdminMenu()`:
```php
Menu::modify('admin-sidebar-menu', function ($menu) {
    $menu->dropdown('Office Impresso', function ($sub) {
        $sub->url('/officeimpresso/computadores', 'Computadores', [...]);
        // ... mais subitens
    }, ['icon' => 'fas fa-plug', 'style' => '...']);  // ❌ FALTA 'url' no parent
});
```

Resultado: no `SidebarMenuItem` React ([`Sidebar.tsx`](../../resources/js/Components/cockpit/Sidebar.tsx)), o item "Office Impresso" renderiza `<a href={item.href ?? '#'}>` — clicar no nome só toggla o sub-menu, não navega. [`LegacyMenuAdapter.php:307-315`](../../app/Services/LegacyMenuAdapter.php) só popula `result['href']` quando `$props['url']` existe.

**✅ Certo:**
```php
$menu->dropdown('Office Impresso', function ($sub) { ... }, [
    'url'   => '/officeimpresso/computadores',  // ✅ default landing — torna parent clickável
    'icon'  => 'fas fa-plug',
    'style' => '...',
]);
```

**Como auditar:** abrir o módulo no sidebar AppShellV2; clicar no nome do módulo (não chevron) deve navegar pra default page. Se não navega, falta `'url'`.

**Bug evitado:** "ele está no menu interno sem link para abrir" (Wagner 2026-05-10).

## Check 12 · Módulo superadmin — Spatie permission `superadmin` precisa existir + estar atribuída

**Aplica a:** módulos com guard `auth()->user()->can('superadmin')` em `DataController::modifyAdminMenu()` (todos os módulos superadmin-only).

**Sintoma do bug:** items dos módulos admin de plataforma (Officeimpresso, CMS, Backup, Conector, Módulos) simplesmente NÃO aparecem no sidebar nos grupos `office`/`plataforma` — sem mensagem, sem erro. Items publicados pelos DataControllers nunca chegam ao `shell.menu` porque o guard retorna early.

**Como detectar:**
```bash
php artisan tinker --execute="echo \App\User::find(1)->can('superadmin') ? 'OK' : 'FALTA_PERM'"
# Esperado: OK
# Se FALTA_PERM, próximo passo:
php artisan tinker --execute="
  \$exists = \Spatie\Permission\Models\Permission::where('name','superadmin')->exists();
  echo 'perm_exists=' . var_export(\$exists, true) . PHP_EOL;
"
```

**Fix se permission não existe:**
```sql
INSERT INTO permissions (name, guard_name, business_id, created_at, updated_at)
VALUES ('superadmin', 'web', 1, NOW(), NOW());

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT (SELECT id FROM permissions WHERE name='superadmin'), id
FROM roles WHERE name='Admin#1';
```

Depois: `php artisan permission:cache-reset`

**Por que verificar PRÉ-MWART:** se Wagner não tem a perm, F4 smoke biz=1 vai falhar silenciosamente (cascata vazia → ele não vê o item migrado → assume "tela quebrou"). Bug catalogado em sessão 2026-05-10 — local dev tinha `perm_exists=false`.

**Bug evitado:** debug de 2 horas tentando achar por que cascata Superadmin não renderizava.

## ⛔ Check 10 (HARD GATE) · Paridade visual com o Cockpit canônico (Claude design)

**Visual canon decidido em 2026-05-07 (madrugada):**
- ✅ **Cockpit (AppShellV2 visual)** — pretty, modern, clean. Mockup canônico em `https://claude.ai/design/p/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58` (file: "Oimpresso ERP - Chat.html").
- ❌ **AdminLTE Blade legacy** — *"feio"* (palavra do Wagner). NÃO usar como referência visual.

**Mas existe um GAP funcional crítico:** o Cockpit atual NÃO tem **topnav horizontal do módulo** (a barra com itens tipo "Reparar / Folhas de trabalho / Adicionar fatura / Marcas / Configurações"). Wagner disse explícito: *"sem navtop... tem que ter"*.

**Trajetória do feedback (registrar pra não interpretar errado):**
1. Wagner: *"o padrão do cockpit era muito superior"* — eu interpretei como "Blade > Cockpit" (errado).
2. Wagner: *"cokpit achei mais bonito"* — corrigiu: Cockpit é mais bonito.
3. Wagner: *"mais tbm sem navtop... tem que ter"* — gap exato é topnav horizontal ausente.
4. Wagner: *"blade feio o padrão bonito é [Claude design link]"* — Blade legacy é feio, não voltar.

### Tabela comparativa (visão correta após feedback)

| Elemento | Blade legacy | Cockpit MWART atual | Cockpit canônico (mockup) | Gap a fechar |
|---|---|---|---|---|
| Visual geral | ❌ feio | ✅ moderno e limpo | ✅ moderno e limpo | nenhum |
| Sidebar | ✅ funcional | ✅ Cockpit V2 | ✅ canônico | nenhum |
| **Topnav HORIZONTAL módulo** | ✅ tem (Reparar / Folhas / etc) | ❌ ausente | ❌ tampouco no mockup | **adicionar — FALTA EM TUDO** |
| Breadcrumb | ✅ "Reparar > Board" | ⚠️ genérico se sem `topnav.php` | ✅ "Oimpresso Matriz / OFFICEIMPRESSO > OS" | adicionar `topnav.php` por módulo |
| KPI cards | ❌ ausente | ⚠️ KpiCard simples | ✅ 4 cards ricos (Abertas, Atrasadas, Valor, Total) | enriquecer telas listagem |
| Tabs filtro | ❌ ausente | ❌ ausente | ✅ Abertas / Atrasadas / Todas / Orçado / etc | adicionar quando relevante |
| Tabela rica | ❌ DataTable Bootstrap | ⚠️ tabela básica | ✅ tabela polida com avatars, badges, datas relativas | usar TanStack Table + DS |

### REGRA HARD — antes de promover flag MWART em prod

**P0 BLOQUEADOR**: implementar **topnav horizontal do módulo** no `AppShellV2` antes de continuar criando telas MWART novas. Sem topnav horizontal, qualquer tela MWART vai gerar a reclamação *"sem navtop... tem que ter"* repetida.

**Onde implementar:**
- `resources/js/Layouts/AppShellV2.tsx` — adicionar `<nav className="topnav-module">` abaixo do `<header className="topbar">` (linha 337), populado com `useAutoModuleNav().items`
- Render só se `moduleItems.length > 0` (graceful degradation pra módulos sem `topnav.php`)
- Estilo segue o Claude design canônico (link acima)

**P1 — visual rico nas telas listagem:**
- KPI cards (4 metrics no topo, não 2 simples como Repair Dashboard)
- Tabs de filtro (status / período)
- Tabela TanStack com avatars, badges colorform, datas relativas ("hoje 14:00")

**O que NÃO fazer (errado mesmo se intuitivo):**
- ❌ Rollback `MWART_*=false` no .env achando que Blade é melhor — Blade é FEIO segundo Wagner (madrugada 07-mai)
- ❌ Implementar topnav só pra Repair (`topnav.php` ajuda no breadcrumb dropdown mas NÃO renderiza navbar horizontal — esse é um gap em AppShellV2 mesmo)
- ❌ Continuar criando telas MWART novas antes do topnav horizontal entrar no AppShellV2 — vai gerar mesma reclamação

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
[ ] Check 10 (HARD GATE) — paridade visual com Blade legacy: top navbar + topnav horizontal módulo + breadcrumb correto + action bar (sem isso NÃO promover flag MWART em prod)
[ ] Check 11 (SE módulo superadmin) — parent dropdown em DataController tem 'url' default page (cascata clickável)
[ ] Check 12 (SE módulo superadmin) — Spatie permission 'superadmin' existe no DB + atribuída ao role do user que vai testar
```

## ROI / por que esta skill existe

| Sprint | PRs corretivos | Causa | Cobertura desta skill |
|---|---|---|---|
| S2.5 PR #138 (Status) | #143 (DS contract), #144 (route()) | Checks 5,6,7,8 | ✅ |
| S2.5 PR #139 (DeviceModels) | #143, #144, #145 (Schema) | Checks 3,5,6,7,8 | ✅ |
| S2.5 PR #140 (Dashboard) | #143, #145 (CommonChart) | Checks 1,2,4,5,6,7 | ✅ |
| S2.5 PR #141 (JobSheet) | #143, #144 | Checks 5,6,7,8 | ✅ |

**4 PRs novos × 3 PRs corretivos = 12 round-trips.** Esta skill, se ativa antes do PR original, reduz pra 4 PRs limpos (1× cada tela).

## Próximo passo P0 (BLOQUEADOR DE NOVAS TELAS MWART)

**Sprint AppShellV2-paridade**: trazer pro Cockpit os 4 elementos críticos perdidos (top app navbar, topnav horizontal módulo, breadcrumb correto, action bar). Sem isso, qualquer tela nova MWART vai gerar a mesma reação *"perdeu elementos / era muito superior"*.

Estimativa: 2-4 dias de trabalho de plataforma (não tela). Executar **ANTES** de prometer S2.6/S2.7 ou outras telas Repair/módulos.

## Modo B — Audit de tela MWART existente

Quando o gatilho é "audita tela X MWART" ou "essa tela está certa?", **NÃO refatorar direto**. Em vez disso:

1. Read da tela `resources/js/Pages/<Module>/<Tela>.tsx` + Controller
2. Aplicar Checks 1-10 sequencialmente
3. Output em formato `file:line — check N violado — fix sugerido`:

```
Modules/Repair/Http/Controllers/DeviceModelController.php:119 — Check 3 violado (SELECT 'description' coluna inexistente em repair_device_models) — remover do select OU criar migration
resources/js/Pages/Repair/Dashboard/Index.tsx:118 — Check 4 violado (items.slice() sem Array.isArray guard) — adicionar `const list = Array.isArray(items) ? items : []`
resources/js/Pages/Repair/JobSheet/Index.tsx:65 — Check 8 violado (route('job-sheet.create') sem Ziggy) — usar URL hardcoded "/repair/job-sheet/create"
resources/js/Pages/Repair/Dashboard/Index.tsx:38 — Check 10 violado (sem topnav horizontal módulo, sem KPI cards rich, tabela ausente) — comparar com os-page.jsx canon
```

Esse modo NÃO altera código — entrega no chat. Wagner decide se ajusta a tela ou registra exceção.

## Modo C — Refactor de tela MWART feia

Quando o gatilho é "essa tela está feia" / "perdeu elementos" / *"o padrão do cockpit era muito superior"*:

1. Read do canon visual `os-page.jsx` em `_DesignSystem/ui_kits/cowork-2026-04-27/`
2. Comparar elemento por elemento com a tela MWART atual
3. Propor fix incremental (não refazer do zero):
   - Adicionar KPI cards no topo se faltam
   - Adicionar tabs filtro se faltam
   - Substituir tabela básica por tabela rica com avatars/badges/datas relativas
   - Adicionar topnav horizontal módulo (P0 — falta no AppShellV2 hoje)
4. Escrever PR ≤300 linhas (commit-discipline) com 1 melhoria por vez

## Anti-padrões (NUNCA fazer)

- ❌ Criar tela MWART sem ler `os-page.jsx` canon visual primeiro — gera reclamação Wagner garantida
- ❌ Promover flag `MWART_*=true` em prod sem smoke test browser MCP — bug branco vai aparecer só pro Wagner
- ❌ Passar Eloquent Collection raw pro Inertia sem `->values()->all()` — shape imprevisível
- ❌ SELECT colunas sem checar migration (`Schema::hasColumn` ou grep) — SQL error em prod
- ❌ Importar `lucide-react` direto em Page Inertia — usar `<Icon name="kebab-case"/>` do `@/Components/Icon`
- ❌ Usar `route()` Ziggy global — projeto não tem; usar URL hardcoded com prefix do módulo
- ❌ Rollback `MWART_*=false` achando que Blade é melhor — Wagner: *"blade feio o padrão bonito é [Cockpit]"* (07-mai)
- ❌ Continuar criando telas MWART ANTES de topnav horizontal entrar no AppShellV2 — Check 10 Hard Gate
- ❌ Passar objeto `CommonChart`/Highcharts pro Inertia onde TSX espera array — TypeError `.slice`
- ❌ Pular smoke test browser MCP achando "código está certo" — só prod confirma render
- ❌ **Módulo com parent dropdown sem `'url'` nas options** — sidebar fica com item parent que só toggla, não navega (Check 11)
- ❌ **Migrar módulo admin de plataforma sem confirmar `Spatie\Permission\Models\Permission::where('name','superadmin')->exists()`** — F4 smoke vai falhar silenciosamente (Check 12)
- ❌ **Colocar módulo de uso esporádico (Backup mensal, CMS raro) no topo do sidebar** — usa grupo `plataforma` no fim, não `office` (ACESSOS RÁPIDOS); skill `sidebar-menu-arch` codifica a regra

## Estrutura da skill (progressive disclosure — TODO P1)

Hoje: SKILL.md monolítico ~280 linhas. Próxima iteração quebrar em:

```
.claude/skills/mwart-quality/
├── SKILL.md      (este arquivo — overview ~150 linhas)
├── CHECKS.md     (10 checks expandidos com exemplos ✅/❌ completos)
├── EXAMPLES.md   (1 input + 1 output end-to-end + dicas de profundidade)
├── CHECKLIST.md  (DoD detalhado pra Modo A pre-flight)
└── GOTCHAS.md    (pegadinhas curadas append-only — começar com 5 do PR #143/#144/#145)
```

Pattern segue [`cockpit-runbook`](../cockpit-runbook/SKILL.md) que já provou ROI.

## Skills irmãs

- [`cockpit-runbook`](../cockpit-runbook/SKILL.md) — pra gerar RUNBOOK.md detalhado da tela MWART (11 seções, frontmatter YAML)
- [`criar-modulo`](../criar-modulo/SKILL.md) — pra módulo Laravel inteiro novo (não tela individual)
- [`commit-discipline`](../commit-discipline/SKILL.md) — Tier A always-on, garante 1 PR = 1 intent ≤300 linhas
- [`memory-sync`](../memory-sync/SKILL.md) — propaga session log gerado pro MCP via git push
- [`multi-tenant-patterns`](../multi-tenant-patterns/SKILL.md) — Tier A, qualquer query toca `business_id`

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
