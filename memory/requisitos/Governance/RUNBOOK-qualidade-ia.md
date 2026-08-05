---
slug: governance-runbook-qualidade-ia
title: "Governança — Runbook da tela Qualidade IA (/governance/qualidade-ia)"
type: runbook
module: Governance
tela: governance/QualidadeIa
status: ativo
owner: W
last_validated: "2026-08-05"
date: 2026-08-05
related_adrs:
  - 0366-fronteira-jana-forja-governance-kb
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0049-camadas-memoria-agente-fase-por-fase
  - 0050-metricas-obrigatorias-memoria-table
  - 0086-fase-5-mvp-governance-actiongate-warn
preconditions:
  - "Módulo Governance instalado + `governance_module` no pacote da subscription"
  - "Permissão Spatie `jana.mcp.usage.all` atribuída ao role (Wagner/superadmin)"
  - "Tabela `copiloto_memoria_metricas` alimentada pelo cron diário 23:55"
  - "Tabela `jana_memoria_gabarito` com perguntas ativas"
steps:
  - "Controller monta gates canônicos ADR 0049/0050 + séries por business"
  - "Page /governance/qualidade-ia renderiza KPIs de gate + sparklines + runs recentes"
  - "Filtro dias/business dispara partial reload server-driven"
---

# RUNBOOK — `/governance/qualidade-ia`

> **Porte da tela `Jana/Admin/Qualidade/Index`** ([ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B/§D-C item 2).
> **NÃO é migração MWART Blade→Inertia.** É movimento de dono: decisão [W] 2026-08-03 — *eval é **gate de
> conformidade**, medido contra piso/baseline igual `module-grades` e `drift`*. A pergunta que a tela
> responde é *"a regra está sendo cumprida?"*, não *"como está meu negócio?"*.
>
> Origem: [`memory/requisitos/Jana/RUNBOOK-qualidade-admin.md`](../Jana/RUNBOOK-qualidade-admin.md) (fica como fóssil datado após o cutover).

## 1. Objetivo

Trend 7-90d das **8 métricas obrigatórias + 3 RAGAS** lidas de `copiloto_memoria_metricas`, com os gates
canônicos da [ADR 0049](../../decisions/0049-camadas-memoria-agente-fase-por-fase.md) em verde/vermelho.
Serve pro auditor decidir se uma evolução de camada está liberada (`recall_at_3 ≥ 0.80` é **bloqueante**)
e quando calibrar HyDE/Reranker/RRF.

**8 obrigatórias** (★ = bloqueante de evolução de camada):
`recall_at_3` ≥ 0.80 ★ · `precision_at_3` ≥ 0.60 ★ · `mrr` ≥ 0.70 ★ · `latencia_p95_ms` ≤ 2000 ★ ·
`tokens_medio` ≤ 3000 · `memory_bloat` ≥ 0.60 · `taxa_contradicoes_pct` ≤ 2.0% · `cross_tenant_violations` == 0.

**3 RAGAS-aligned:** `faithfulness` (≥0.85) · `answer_relevancy` · `context_precision`.

## 2. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/governance/qualidade-ia` | Login com `jana.mcp.usage.all` → URL → título "Qualidade IA" |
| Strip de sub-navegação da Governança | `<GovernancaSubNav active="qualidade-ia" />` como primeiro filho |
| AppShellV2 via Persistent Layout | Breadcrumb "Governança / Qualidade IA" |
| Filtro Janela (7/30/60/90d) + Business + Aplicar | `<Select>` shadcn no topo |
| 1 Card por business com 8 KpiCards de gate | `tone="success"` quando o valor passa o alvo |
| Trend table com sparklines SVG | `<svg width="120" height="28">` 1 `polyline`/série |
| Runs recentes — top 30 desc | `useMemo` sobre `series.flatMap(...)` |
| Empty trend | Dica pra rodar `php artisan copiloto:metrics:apurar` |

## 3. Pré-condições

- [ ] Módulo `Governance` instalado em `/manage-modules` ([ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md))
- [ ] `governance_module` marcado no pacote da subscription — senão o `GovernancaSubNav` renderiza `null`
- [ ] Permissão **`jana.mcp.usage.all`** no role — **NÃO renomeada no porte** (ver §6)
- [ ] Rota `GET /governance/qualidade-ia` → `QualidadeIaController@index` (name `governance.qualidade-ia.index`)
- [ ] Page Inertia em [`resources/js/Pages/governance/QualidadeIa.tsx`](../../../resources/js/Pages/governance/QualidadeIa.tsx) + charter ao lado
- [ ] Ghost `qualidade-ia` declarado no `modifyAdminMenu` do [`DataController` da Governança](../../../Modules/Governance/Http/Controllers/DataController.php)
- [ ] Entity `Modules\Jana\Entities\MemoriaMetrica` (**fica no Jana**) + tabelas `copiloto_memoria_metricas` e `jana_memoria_gabarito`
- [ ] Cron `copiloto:metrics:apurar` (daily 23:55) e/ou `copiloto:eval --persist` já rodaram

## 4. Passo-a-passo

### 1. Controller (Governança) monta gates + séries

```php
// Modules/Governance/Http/Controllers/QualidadeIaController.php
namespace Modules\Governance\Http\Controllers;

use Modules\Jana\Entities\MemoriaMetrica;   // Entity permanece no Jana (ADR 0366 §D-C)

class QualidadeIaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.mcp.usage.all');   // NÃO renomear
    }

    public function index(Request $request): Response
    {
        $dias = (int) max(7, min(90, $request->get('dias', 30)));   // clamp [7..90]
        // business_id opcional — visão de PLATAFORMA (superadmin), não do tenant logado
        return Inertia::render('governance/QualidadeIa', [ /* ... */ ]);
    }
}
```

> **Cross-business é INTENCIONAL aqui.** A tela é de plataforma (`jana.mcp.usage.all` = superadmin),
> não do business logado — não viola a [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md);
> é a exceção da Constituição Art. 6+8 que a ADR 0366 §Consequências preserva explicitamente. O filtro
> `business_id` é de **leitura agregada**, e `cross_tenant_violations == 0` é justamente a métrica que
> vigia o isolamento.

### 2. Sem `Inertia::defer` nas 5 props — intencional

HOTFIX Wagner 2026-05-25: a Page desestrutura direto (`series.map`, `kpis.map`) e defer entregava
`undefined` no primeiro render → `TypeError undefined.filter` → tela branca em prod. Reintroduzir
**só** com `<Deferred data={['series','kpis']} fallback={…}>` no frontend.

As 2 closures que **permanecem** (`gabarito_total`, `gabarito_por_categoria`) não são defer — são
closures D-14: gabarito é da plataforma, não muda com filtro, então **pula no partial reload**
(`only: ['series','kpis','filtros']`) e roda normal no load cheio. Isso não crasha porque o partial
não re-renderiza a árvore do zero.

### 3. Page usa a strip da Governança

```tsx
import GovernancaSubNav from '@/Pages/governance/_shared/GovernancaSubNav';
<GovernancaSubNav active="qualidade-ia" />
```

Zero import de `@/Pages/Jana/**`.

### 4. Partial reload aponta pra rota nova

```tsx
router.get('/governance/qualidade-ia', params, {
  preserveScroll: true, preserveState: true,
  only: ['series', 'kpis', 'filtros'],
});
```

### 5. Build + smoke

```bash
npm run build:inertia
grep -i "Pages/governance/QualidadeIa" public/build-inertia/manifest.json
```

Smoke real pós-deploy (R1) + screenshot 1280/1440 antes de declarar pronto.

## 5. Estados visuais

| Estado | Trigger | Implementação |
|---|---|---|
| `default` | — | `Card` + `KpiGrid` |
| `gate ok / fail` | `gateStatus()` | `tone={ok ? 'success' : 'danger'}` |
| `metric null` | métrica ausente | `'—'` em `fmtPct/fmtNum/fmtMs` |
| `empty (sparkline)` | `< 2` pontos válidos | "N ponto(s)" muted |
| `empty (trend)` | `series.length === 0` | dica com o comando artisan |
| `loading` | `router.get` | ❌ sem skeleton — herdado da origem |

## 6. Pegadinhas

- ⚠️ **Permissão mantém o prefixo `jana.`** — `jana.mcp.usage.all`. Rename exige ADR + migration própria
  (regra explícita em `Modules/Jana/Http/routes.php`). Rota e ghost já são `governance.*`; só a permissão
  fica legada, **de propósito**.
- ⚠️ **Entity e tabelas continuam no Jana** (`MemoriaMetrica`, `copiloto_memoria_metricas`,
  `jana_memoria_gabarito`). Estado intermediário legítimo (ADR 0366 §Consequências). Precedente vivo:
  `Modules/Forja/.../RoadmapController` importa `Modules\Jana\Entities\Mcp\McpTask`. Registrar em
  `db_tables_consumed` do `SCOPE.md` da Governança.
- ❌ **Sparkline normaliza por min/max LOCAL** da série — visual engana: 0.78→0.79 "parece" o mesmo
  movimento de 0.20→0.85. Dívida herdada da origem; o número absoluto ao lado do sparkline mitiga.
  Fix futuro: escala absoluta 0..1 nas métricas % ou linha de referência no `gates[m.key].alvo`.
- ❌ **Cores HEX hardcoded** no array `allMetrics` (`#3b82f6`…) violam R-DS-002 — dívida herdada.
  Fix futuro: tokens `--chart-N` do shadcn.
- ❌ **Imports mortos removidos no porte** — a tela antiga tinha `Badge` e `useMemo` importados sem uso
  (2 violações `no-unused-vars` grandfathered) e um `(p as any)` (`no-explicit-any`). Na tela nova:
  `Badge` fora, `useMemo` **usado de verdade** (memoiza os runs recentes — antes recomputava a cada render)
  e o acesso dinâmico à métrica é tipado sem `any`.
- ⚠️ **Dropdown de business só lista quem JÁ TEM métrica** (`series.filter(...)`) — business novo sem cron
  rodado fica invisível. Bug funcional herdado.
- ⚠️ **`gabarito_total === 0`** deixa todas as métricas `null` — a tela mostra o contador discreto, sem
  empty state explícito.

## 7. ADRs de origem

- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — §D-B manda esta tela pra cá (eval = gate de conformidade)
- [ADR 0049](../../decisions/0049-camadas-memoria-agente-fase-por-fase.md) — **define os gates**
- [ADR 0050](../../decisions/0050-metricas-obrigatorias-memoria-table.md) — **define as 8+3 métricas** (MEM-MET-4 é esta tela)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART (F1 = este RUNBOOK)
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Governance Fase 5 MVP

**Tests:** sem suite Pest dedicada hoje (nem na origem). Ao remover a rota do Jana, criar
`Modules/Governance/Tests/Feature/QualidadeIaControllerTest.php` validando shape de `series`/`kpis`/`gates`
+ 403 sem `jana.mcp.usage.all`.
**Comandos relacionados:** `php artisan copiloto:metrics:apurar` (cron daily 23:55) ·
`php artisan copiloto:eval --persist [--business=N]`.
