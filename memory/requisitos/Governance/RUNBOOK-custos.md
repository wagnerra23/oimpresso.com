---
slug: governance-runbook-custos
title: "Governança — Runbook da tela Custos de IA (/governance/custos)"
type: runbook
module: Governance
tela: governance/Custos
status: ativo
owner: W
last_validated: "2026-08-05"
date: 2026-08-05
related_adrs:
  - 0366-fronteira-jana-forja-governance-kb
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0086-fase-5-mvp-governance-actiongate-warn
preconditions:
  - "Módulo Governance instalado + `governance_module` no pacote da subscription"
  - "Permissão Spatie `jana.admin.custos.view` atribuída ao role"
  - "Tabelas `jana_conversas` + `jana_mensagens` com `tokens_in`/`tokens_out` populados"
steps:
  - "Controller resolve preset/período e chama CustosService::painel"
  - "Page /governance/custos renderiza KPIs + gráfico + tabela por usuário"
  - "Troca de filtro dispara partial reload server-driven"
---

# RUNBOOK — `/governance/custos`

> **Porte da tela `Jana/Admin/Custos/Index`** ([ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B/§D-C item 2).
> **NÃO é migração MWART Blade→Inertia** — não existe Blade legado desta tela. É movimento de dono
> de módulo: a pergunta que ela responde é *"a regra está sendo cumprida?"* (custo de IA sob controle),
> não *"como está meu negócio?"*. O `Jana/Chat.charter.md` já mandava: *"custo vai pra /governance — Wagner-only"*.
>
> Origem: [`memory/requisitos/Jana/RUNBOOK-custos-admin.md`](../Jana/RUNBOOK-custos-admin.md) (fica como fóssil datado após o cutover).

## 1. Objetivo

Dar ao auditor do business a visão consolidada de **quanto a IA custou no período** — mês atual, mês
anterior, últimos 90 dias ou range custom. 4 KPIs (R$, mensagens, tokens, usuários ativos), gráfico de
área do gasto diário e breakdown por usuário. Base do ROI (Onda 1) sem depender do superadmin.

## 2. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/governance/custos` | Login com `jana.admin.custos.view` → URL → título "Custos de IA" |
| Strip de sub-navegação da Governança | `<GovernancaSubNav active="custos" />` como primeiro filho; ghost `custos` marcado |
| AppShellV2 via Persistent Layout | Inspetor: wrapper `.cockpit`; breadcrumb "Governança / Custos de IA" |
| 4 KPIs preenchidos | `KpiGrid cols={4}` com `KpiCard` shared |
| Filtro de preset + range custom | `<Select>` shadcn; `custom` revela form De/Até |
| Gráfico de área SVG inline | `<svg viewBox="0 0 800 220">` com `polygon` + `polyline` |
| Tabela "Por usuário" com `tfoot` de total | Total bate com os KPIs |
| Empty state | "Nenhum consumo de IA no período." quando `por_usuario` vazio |

## 3. Pré-condições

- [ ] Módulo `Governance` instalado em `/manage-modules` ([ADR 0024](../../decisions/0024-instalacao-1-clique-modulos.md))
- [ ] `governance_module` marcado no pacote da subscription (`/superadmin/packages/{id}/edit`) — senão o `GovernancaSubNav` renderiza `null` (silencioso por design)
- [ ] Permissão **`jana.admin.custos.view`** no role do usuário — **o nome NÃO foi renomeado no porte** (rename de permissão exige ADR + migration própria; ver §6)
- [ ] Rota `GET /governance/custos` → `CustosController@index` (name `governance.custos.index`) em [`Modules/Governance/Http/routes.php`](../../../Modules/Governance/Http/routes.php)
- [ ] Page Inertia em [`resources/js/Pages/governance/Custos.tsx`](../../../resources/js/Pages/governance/Custos.tsx) + charter ao lado
- [ ] Ghost `custos` declarado no `modifyAdminMenu` do [`DataController` da Governança](../../../Modules/Governance/Http/Controllers/DataController.php) — sem ele a tela nasce órfã
- [ ] `Modules\Jana\Services\CustosService` disponível (o Service **fica no Jana** — só o controller mudou de dono; precedente: `Modules/Forja/.../RoadmapController` importa `Modules\Jana\Entities\Mcp\McpTask`)

## 4. Passo-a-passo

### 1. Controller (Governança) resolve período e chama o Service do Jana

```php
// Modules/Governance/Http/Controllers/CustosController.php
namespace Modules\Governance\Http\Controllers;

use Modules\Jana\Services\CustosService;   // Service permanece no Jana (ADR 0366 §D-C)

class CustosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:jana.admin.custos.view');   // NÃO renomear
    }

    public function index(Request $request, CustosService $service): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');
        // preset validado contra allowlist; range resolvido pelo Service
        $painel = $service->painel($businessId, $range['inicio'], $range['fim']);

        return Inertia::render('governance/Custos', [ /* ... */ ]);
    }
}
```

> **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)): o `business_id` vem
> da **sessão**, nunca do request. Sem `withoutGlobalScopes`, sem `?business_id=` na query.

### 2. Sem `Inertia::defer` nestas 3 props — e isso é intencional

`kpis` / `por_usuario` / `serie_diaria` são **eager**. O HOTFIX de Wagner 2026-05-25 removeu o defer
porque a Page desestrutura direto (`serie_diaria.reduce(...)`) sem `<Deferred>` — defer entrega
`undefined` no primeiro render → `TypeError` → **tela branca em prod** (mesmo bug do PR #1550/#1552).

**Reintroduzir defer SÓ junto com o wrap no frontend:**

```tsx
<Deferred data={['kpis', 'por_usuario', 'serie_diaria']} fallback={<Skeleton />}>
```

Uma coisa sem a outra reabre o incidente.

### 3. Page usa a strip da Governança, não o header do Jana

```tsx
import GovernancaSubNav from '@/Pages/governance/_shared/GovernancaSubNav';
// ...
<GovernancaSubNav active="custos" />   // primeiro filho
```

Zero import de `@/Pages/Jana/**` na tela nova.

### 4. Partial reload aponta pra rota nova

```tsx
router.get('/governance/custos', { ...filters, ...patch }, {
  preserveState: true, preserveScroll: true, replace: true,
  only: ['kpis', 'por_usuario', 'serie_diaria', 'periodo', 'filters'],
});
```

`pricing` (config estática) fica de fora do `only` de propósito.

### 5. Build + smoke

```bash
npm run build:inertia
grep -i "Pages/governance/Custos" public/build-inertia/manifest.json
```

Smoke real em prod pós-deploy (R1): `curl -sv https://oimpresso.com/governance/custos 2>&1 | grep '^< HTTP'`
+ screenshot 1280/1440 via browser MCP **antes** de declarar pronto.

## 5. Estados visuais

| Estado | Trigger | Implementação |
|---|---|---|
| `default` | — | `Card` shadcn + `KpiGrid` |
| `empty (tabela)` | `por_usuario.length === 0` | "Nenhum consumo de IA no período." + dica |
| `empty (gráfico)` | `serie_diaria.length === 0` | "Sem dados no período." dentro do SVG wrapper |
| `loading` | `router.get` do filtro | ❌ sem skeleton — herdado da origem |
| `error` | render 500 | error boundary global |

## 6. Pegadinhas

- ⚠️ **Permissão mantém o prefixo `jana.`** — `jana.admin.custos.view`. Renomear pra `governance.custos.view`
  exige ADR + migration de `permissions`/`role_has_permissions` + re-atribuição nos roles de cada business.
  Está explícito em `Modules/Jana/Http/routes.php`: *não renomeie permissão no movimento de tela*.
  O ghost do menu e a rota já são `governance.*`; só a permissão fica com o nome legado, **de propósito**.
- ⚠️ **O Service continua no Jana** (`Modules\Jana\Services\CustosService`) e lê `jana_conversas`/`jana_mensagens`.
  Isso é estado intermediário legítimo declarado pela ADR 0366 §Consequências ("telas movidas e tabelas não").
  Registrar em `db_tables_consumed` do `SCOPE.md` da Governança.
- ⚠️ **URL hardcoded no `router.get`** — origem já tinha essa dívida. Ziggy (`route('governance.custos.index')`)
  é o caminho, mas trocar agora acopla o porte a outra mudança. Fica como resíduo honesto.
- ⚠️ **`GovernancaSubNav` renderiza `null` em silêncio** quando falta pacote/permissão/`AdminSidebarMenu`.
  Tela sem strip **não** é bug de CSS — é gate de menu.
- ❌ **Labels sem `htmlFor`** eram 3 violações `jsx-a11y/label-has-associated-control` grandfathered no
  eslint-baseline da tela antiga. Na tela nova elas foram **corrigidas** (`<Label htmlFor>` + `id` no input) —
  arquivo novo não herda grandfather.

## 7. ADRs de origem

- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira dos 4 módulos; §D-B manda esta tela pra cá
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — processo MWART (F1 = este RUNBOOK)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — `business_id` da sessão
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Governance Fase 5 MVP

**Tests:** `Modules/Jana/Tests/Feature/Admin/CustosControllerTest.php` cobre a rota antiga — portar/duplicar
pro `Modules/Governance/Tests/Feature/` no PR que remove a rota do Jana.
