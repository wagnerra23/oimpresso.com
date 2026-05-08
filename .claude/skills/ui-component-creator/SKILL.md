---
name: ui-component-creator
description: Use ao criar/modificar componentes React (Pages Inertia, sub-componentes em _components/, ou shareds em Components/shared/) seguindo Cockpit Pattern V2 (ADR 0110). NÃO usar pra módulos backend ou config — só UI. Lê Design.md + ADR 0110 antes.
tier: B
parent_adr: 0095
related_adrs: [0094, 0107, 0109, 0110]
enabled: true
ativacao: 2026-05-08 (junto com Design.md §16 Cockpit V2 spec)
---

# ui-component-creator — Tier B

> Adaptação do template SKILL.md genérico (Vue/shadcn-vue) pro stack real do oimpresso: **React 19 + Inertia v3 + Tailwind 4 + shadcn UI**. Substitui invocações ad-hoc tipo "cria um KPI custom" por workflow padronizado que reusa shared components e respeita canon ADR 0110.

## Quando invocar

- Wagner pede "cria componente X" / "adiciona KPI Y na página Z" / "novo card de status"
- Migração MWART F3 (FRONTEND INCREMENTAL) precisa renderizar Page Inertia
- Refator visual aplicando Cockpit Pattern V2 em Page legacy

**NÃO usar:**
- Backend Laravel (Controller/Model/Migration) — usa skill `criar-modulo` ou direto
- Config arquivo (.env, composer, package.json) — direto
- Texto/copy puro sem visual — Edit direto

## Entradas

| Param | Descrição |
|---|---|
| `component_name` | Nome do componente (ex: `SaleSheet`, `KpiOverdue`, `FilterPills`) |
| `target_path` | Onde criar (ex: `resources/js/Pages/Sells/_components/`, `resources/js/Components/shared/`) |
| `purpose` | O que faz (ex: "drawer lateral pra detalhe de venda") |
| `page_charter_path` | (opcional) `*.charter.md` ao lado do `.tsx` da Page parent |
| `design_spec` | (opcional) Mockup, screenshot Figma, ou descrição visual detalhada |

## Fluxo de execução

### 1. Leitura de contexto obrigatória

Em ordem:

1. **`Design.md` raiz** — §16 (Cockpit V2 spec) + §3 princípios não-negociáveis
2. **[ADR 0110](memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)** — anatomia + tipografia + cores semânticas
3. **Page parent `.charter.md`** se existir — Mission + Goals + Non-Goals + UX Targets
4. **Pages canon vivos pra cargo cult:**
   - List+detail: [Sells/Index.tsx](resources/js/Pages/Sells/Index.tsx) + [SaleSheet.tsx](resources/js/Pages/Sells/_components/SaleSheet.tsx)
   - Form: [Sells/Create.tsx](resources/js/Pages/Sells/Create.tsx)
   - Dashboard: [governance/Dashboard.tsx](resources/js/Pages/governance/Dashboard.tsx)
   - Kanban: [ProjectMgmt/Board/Index.tsx](resources/js/Pages/ProjectMgmt/Board/Index.tsx)

### 2. Verificação de existência (REUSE primeiro)

Buscar shared components antes de criar custom:

```
ls resources/js/Components/shared/  → PageHeader, KpiCard, EmptyState
ls resources/js/Components/ui/       → shadcn primitives (button, card, sheet, etc)
ls resources/js/Pages/[Modulo]/_components/  → componentes do módulo
```

**Se já existe equivalente:** parar e perguntar — provável que tarefa é "estender" não "criar novo". Cite o arquivo encontrado.

### 3. Decisão: Page Inertia vs Sub-componente vs Shared

| Tipo | Onde | Quando |
|---|---|---|
| Page Inertia (rota nova) | `resources/js/Pages/[Modulo]/[Nome].tsx` | Tem rota Laravel + Controller render Inertia |
| Sub-componente local | `resources/js/Pages/[Modulo]/_components/[Nome].tsx` | Só usado dentro do módulo |
| Shared global | `resources/js/Components/shared/[Nome].tsx` | Usado em 2+ módulos diferentes |
| UI primitive shadcn | `resources/js/Components/ui/[nome].tsx` | Wrapper de Radix UI primitive |

### 4. Criação do componente

**Template Page Inertia (canon V2):**

```tsx
// resources/js/Pages/[Modulo]/[Nome].tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';

interface Props {
  // ...
}

export default function [Nome](props: Props) {
  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <PageHeader
        icon="[icon-lucide]"
        title="[Título]"
        description="[Subtitle canon]"
        action={
          <Button>
            <Plus className="mr-1.5 h-4 w-4" />
            Novo X
          </Button>
        }
      />
      {/* KPIs + filter pills + tabela + drawer */}
    </div>
  );
}

[Nome].layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
```

**Template Sub-componente local:**

```tsx
// resources/js/Pages/[Modulo]/_components/[Nome].tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface Props {
  // tipos minimalistas
}

export default function [Nome]({ ...props }: Props) {
  return (/* JSX usando shadcn primitives + cores semânticas */);
}
```

**Decisões obrigatórias durante geração:**

- ✅ Importa `@/Layouts/AppShellV2` se Page (NUNCA `<AppShell>` sem V2)
- ✅ Persistent Layout: `[Nome].layout = (page) => <AppShellV2>{page}</AppShellV2>`
- ✅ h1 via `<PageHeader>` shared OU inline `text-2xl font-semibold tracking-tight`
- ✅ Cores semânticas Cockpit (rose/emerald/amber/blue) — NÃO `bg-gray-100` etc
- ✅ Filter pills `rounded-full px-3.5 py-1.5 text-xs font-medium` (NÃO `border-b-2`)
- ✅ Lista+detail usa `<Sheet>` lateral (NÃO modal/dialog)
- ✅ KPIs usam `<KpiCard>` shared (NÃO inline custom)
- ✅ `localStorage` com prefixo `oimpresso.` se persistir state (NUNCA `sessionStorage`)
- ✅ PT-BR em label/copy/comentário; código (var/method) em inglês OK
- ✅ Multi-tenant: se faz fetch, endpoint deve respeitar `business_id` global scope (ADR 0093)

### 5. Tests Pest estruturais

Para Page Inertia nova, gerar test estrutural espelho de [SellsIndexPageTest.php](tests/Feature/Sells/SellsIndexPageTest.php):

```php
// tests/Feature/[Modulo]/[Nome]PageTest.php
const PAGE_PATH = 'resources/js/Pages/[Modulo]/[Nome].tsx';

it('Page importa AppShellV2 (Persistent Layout)', function () {
    expect(file_get_contents(base_path(PAGE_PATH)))->toContain('@/Layouts/AppShellV2');
});

it('Page declara interface [Nome]PageProps', function () {/*...*/});
it('Page tem h1 canônico (text-2xl font-semibold tracking-tight)', function () {/*...*/});
it('Page NÃO usa cor crua não-semântica', function () {
    expect(file_get_contents(base_path(PAGE_PATH)))
        ->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});
// ...
```

### 6. Charter (S4+ — quando aplicável)

Se Page é canon target ou crítica do negócio, criar `[Nome].charter.md` ao lado do `.tsx`:

```yaml
---
page: /modulo/path
component: resources/js/Pages/[Modulo]/[Nome].tsx
owner: wagner
status: live | draft
last_validated: YYYY-MM-DD
parent_module: [Modulo]
related_adrs: [0110, 0107, 0104]
tier: A | B
charter_version: 1
---

# Page Charter — /modulo/path

## Mission
[1-2 frases — o que essa página resolve pro usuário]

## Goals — Features (faz)
- ...

## Non-Goals — Features (NÃO faz)
- ❌ ...

## UX Targets
- p95 first-paint < Xms
- ...

## UX Anti-patterns
- ❌ ...
```

### 7. Validação

Antes de declarar "feito":

```bash
# Build OK + bundle gerado
npm run build:inertia

# Tests Pest passam
./vendor/bin/pest tests/Feature/Design/ tests/Feature/[Modulo]/

# Smoke visual: abre tela em prod e screenshot
# (humano valida — NÃO declarar "OK" só por build verde)
```

## Saídas

- Componente `.tsx` em `resources/js/Pages/...` ou `_components/` ou `shared/`
- Test Pest estrutural em `tests/Feature/[Modulo]/[Nome]Test.php` (se Page nova)
- Charter `.charter.md` ao lado do `.tsx` (se Page canon target)
- Build Inertia atualizado (`public/build-inertia/manifest.json`)
- (Opcional) atualização em [Design.md §16](Design.md) se novo pattern

## Anti-padrões deste skill

- ❌ Gerar template Vue/shadcn-vue (stack errado — é React+Inertia)
- ❌ Usar `tailwind.config.js` v3 (Tailwind 4 é CSS-first em `resources/css/app.css`)
- ❌ Usar `gray/red/green-N` (proibido — Cockpit V2)
- ❌ Criar Modal pra detalhe de lista (canon = Sheet lateral)
- ❌ Pular leitura de Design.md + ADR 0110
- ❌ Criar custom `<KpiCard>` inline em vez de reusar shared
- ❌ Esquecer Persistent Layout `.layout = (page) =>`

## Refs

- [Design.md §16 Cockpit V2 spec](Design.md)
- [ADR 0110](memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0107 Visual gate F1.5](memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0109 Claude Design plugin](memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
- [Sells/Index.tsx](resources/js/Pages/Sells/Index.tsx) — referência viva canon
- Skill `mwart-comparative` V3 — visual gate F1.5 invocação Claude Design plugin
