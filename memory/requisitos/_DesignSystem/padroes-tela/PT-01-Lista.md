---
pattern_id: PT-01
nome: Lista
camada: 3-padroes-tela
status: live
versao: 1.0
created: 2026-05-24
parent_adr: UI-0013
supersedes_partial: UI-0006 (template tela operacional)
applied_in:
  - Pages/Sells/Index.tsx
  - Pages/Cliente/Index.tsx
  - Pages/Compras/Index.tsx
  - Pages/Purchase/Index.tsx
  - Pages/ComunicacaoVisual/Index.tsx
  - Pages/ConsultaOs/Index.tsx
  - Pages/Manufacturing/Index.tsx
  - Pages/Nfse/Index.tsx
  - Pages/Produto/Index.tsx
  - Pages/RecurringBilling/Index.tsx
  - Pages/Repair/Index.tsx
  - Pages/StockAdjustment/Index.tsx
  - Pages/Tarefas/Index.tsx
---

# PT-01 · Lista — padrão canônico de tela-lista

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md#camada-1--fundacoes) + [Shell](../README.md#camada-2--shell) e nunca contradiz. Módulo só configura os slots, **não** muda a estrutura.

## Quando aplicar

Sempre que o módulo tem **lista paginável de entidades** (clientes, OSs, NF-e, faturas, vendas, compras, ajustes, transferências, ordens recorrentes, tarefas, etc).

Não aplicar pra: tela de Detalhe full-page (PT-03 quando documentado), Dashboard com gráficos (PT-04), Configuração (PT-05), Form standalone sem lista (PT-02 quando documentado).

## Anatomia · 6 slots fixos

```
┌─────────────────────────────────────────────────────────────┐
│ 1 · PageHeader        título · subtítulo KPI · ações right  │ ← sticky
├─────────────────────────────────────────────────────────────┤
│ 2 · ModuleTopNav      sub-tabs (Todas / Em aberto / ...)    │ ← ghost
├─────────────────────────────────────────────────────────────┤
│ 3 · Toolbar           saved views │ filtros │ busca (⌘K)    │
├─────────────────────────────────────────────────────────────┤
│ 4 · BulkBar           flutuante quando selected > 0          │ ← z-20
├─────────────────────────────────────────────────────────────┤
│ 5 · Table             DataTable + status + hover actions     │
│                       row-h dinâmico (densidade)             │
├─────────────────────────────────────────────────────────────┤
│ 6 · Drawer            slide-in lateral pra criar/editar      │ ← PT-02
│                       (cada módulo implementa o seu)         │   futuro
└─────────────────────────────────────────────────────────────┘
```

## DNA · slot por slot

| Slot | Componente shared | Responsabilidade | Não faz |
|---|---|---|---|
| **1 · Header** | `<PageHeader>` ([Components/shared](../../../../resources/js/Components/shared/PageHeader.tsx)) | Título + subtítulo KPI-resumo (mono `tabular-nums`) + ações primárias à direita (Importar/Exportar/Novo). Sticky ao rolar. | filtros · busca · breadcrumb pesado |
| **2 · Sub-tabs** | `<ModuleTopNav>` ([Components/shared](../../../../resources/js/Components/shared/ModuleTopNav.tsx)) | Sub-páginas do módulo com count mono na pílula. Action button **ghost** (sem cor primária). [ADR 0040 raiz](../../../decisions/0040-modulos-densos-sub-tabs.md) — substitui itens-densos no sidebar. | navegação cross-módulo |
| **3 · Toolbar** | `<PageFilters>` + `<FilterDropdown>` + `<Input search>` | Saved views (4-6 presets opcionais) · separador · filtros (chips) · busca local. **⌘K** delega palette global. Filtros ativos viram chips removíveis (`<ActiveChip>`). | criar entidade · ações em lote |
| **4 · BulkBar** | `<BulkActionBar>` ([Components/shared](../../../../resources/js/Components/shared/BulkActionBar.tsx)) | Aparece flutuante quando `selected.length > 0`. Contagem + 2-4 ações em lote. Ação destrutiva **sempre por último em vermelho**. | filtros · navegação |
| **5 · Table** | `<DataTable>` ([Components/shared](../../../../resources/js/Components/shared/DataTable.tsx)) | Checkbox + colunas + status badge (sem bg-fill) + hover actions inline. Linha tem `--row-h` dinâmico (densidade compact/normal/comfy). Mono em IDs/valores/datas, sans no resto. Click-to-filter em campos pivotáveis. | criar/editar entidade |
| **6 · Drawer** | `<Sheet>` shadcn + form per-módulo | Slide-in lateral pra criar/editar a entidade. Largura padrão **760px** ([ADR 0185](../../../decisions/0185-drawer-760-canon-entidades-cadastrais.md)). Fullscreen abaixo de 1100px. | navegação · ações em lote |

## Regras de ouro

### ✅ Sempre

- **PT-BR** em todo label, mensagem, copy
- `tabular-nums` em todo número/timestamp/valor monetário
- Origin badge nas 5 cores fechadas (OS amber · CRM blue · FIN green · PNT violet · MFG orange) — sem cor crua
- Status badge **sem bg-fill** (Stripe-style — dot + texto colorido, bg transparente)
- Estado persistente em `localStorage` com prefixo `oimpresso.<modulo>.*` ([ADR 0093 multi-tenant](../../../decisions/0093-multi-tenant-isolation-tier-0.md) — nunca cross-tenant)
- Inertia::defer em props pesadas (skill `inertia-defer-default`) — KPIs e tabela carregam SPA-feel
- Atalhos canônicos: J/K navegação · Enter abre · N novo · / busca local · ⌘K palette global · ? cheat-sheet
- Charter .charter.md ao lado do .tsx ([skill `charter-first`](../../../../.claude/skills/charter-first/SKILL.md))

### ❌ Nunca

- Mudar a ordem dos 6 slots (Header sempre topo, BulkBar sempre flutuante, etc)
- Inventar 6ª origin color sem ADR
- Hardcoded color (`#hex` ou `bg-blue-500` literal) — anti-padrão AP1
- Cor crua em arquivo de página · use token CSS var
- Drawer modal sobre modal — abre nova rota ou usa o drawer único
- Botão destrutivo no meio da BulkBar — sempre por último, em vermelho
- Sub-tabs como itens-densos no sidebar — vira sub-tabs do header ([ADR 0040 raiz](../../../decisions/0040-modulos-densos-sub-tabs.md))

## Estados obrigatórios

Toda implementação PT-01 cobre:

1. **Cheia** — N linhas, paginação ativa
2. **Vazia/Primeiro acesso** — `<EmptyState>` com CTA "Criar primeira X" + opção "Importar CSV"
3. **Busca sem resultado** — empty state contextual ("Nada pra 'X' — tenta ajustar filtros")
4. **Loading skeleton** — `<KpiSkeleton>` + linhas placeholder enquanto Inertia::defer resolve
5. **Linha selecionada/hover** — focus state visível (border-l accent · bg-accent-soft)
6. **Erro de fetch** — toast + retry button

## Atalhos canônicos da Lista

| Tecla | Ação |
|---|---|
| `J` / `K` | Navegar linha anterior/próxima |
| `Enter` | Abrir linha focada (drawer ou show) |
| `N` | Nova entidade |
| `F` | Favoritar linha focada (se aplicável) |
| `/` | Foco na busca local |
| `⌘K` / `Ctrl+K` | Command palette global |
| `?` | Cheat-sheet de atalhos |
| `Esc` | Fecha drawer/palette/cheat-sheet |
| `G` + `1..4` | Saved view (se módulo tem) |
| `⌘P` | Imprimir ficha (na tela Show) |

Operacionalizado em [Cliente/Index.tsx — KB-9.75 Slice A](../../../../resources/js/Pages/Cliente/Index.tsx) (PR #1309).

## Aplicado em (estado real)

Lista das telas que **hoje** seguem PT-01 — usada como benchmark de adoção (métrica M4 — Adoção PT-01).

| Página | Slot 1 | Slot 2 | Slot 3 | Slot 4 | Slot 5 | Slot 6 | Charter | Score |
|---|---|---|---|---|---|---|---|---|
| `Sells/Index.tsx` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | gold |
| `Cliente/Index.tsx` | ✓ | — | ✓ | — | ✓ | ✓ (760px) | ✓ v3 | 9.4/10 |
| `Compras/Index.tsx` | ✓ | ✓ | ✓ | parcial | ✓ | parcial | ✓ | — |
| `Purchase/Index.tsx` | ✓ | ✓ | ✓ | parcial | ✓ | parcial | — | — |
| `Repair/Index.tsx` | ✓ | ✓ | ✓ | — | ✓ | ✓ | ✓ | — |
| `RecurringBilling/Index.tsx` | ✓ | ✓ | ✓ | — | ✓ | parcial | — | — |
| `ConsultaOs/Index.tsx` | ✓ | — | ✓ | — | ✓ | — | — | — |
| `Manufacturing/Index.tsx` | ✓ | ✓ | ✓ | — | ✓ | — | — | — |
| `Tarefas/Index.tsx` | ✓ | — | ✓ | — | ✓ | — | — | — |
| `Nfse/Index.tsx` | ✓ | — | ✓ | — | ✓ | — | — | — |
| `Produto/Index.tsx` | ✓ | — | ✓ | — | ✓ | — | — | — |
| `ComunicacaoVisual/Index.tsx` | ✓ | — | ✓ | — | ✓ | — | — | — |

**Métrica adoção PT-01 (2026-05-24):** 12/12 telas-lista usam **Slot 1 (Header)** + **Slot 5 (Table)**. Slot 4 (BulkBar) cobertura ~30%. Slot 6 (Drawer) cobertura ~50%. Próximo passo: PT-02 Form/Drawer consolidando padrão de 760px ([ADR 0185](../../../decisions/0185-drawer-760-canon-entidades-cadastrais.md)).

## Variantes documentadas

- **Variante drawer-first** — Cliente/Index (drawer 760px substitui Show full-page, [ADR 0179](../../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md))
- **Variante KPI-strip clicável** — proposta v2 (5 cards-filtro) — aplicar quando ≥2 módulos pedirem
- **Variante saved-views** — proposta v2 (4 pills + g+1..4) — aplicar quando módulo CRM pedir
- **Variante split (lista+preview)** — protótipo Cowork, sem uso real ainda

## Snippet pronto · estrutura mínima

```tsx
// resources/js/Pages/<Modulo>/Index.tsx
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { PageHeader } from '@/Components/shared/PageHeader';
import { ModuleTopNav } from '@/Components/shared/ModuleTopNav';
import { DataTable } from '@/Components/shared/DataTable';
import { BulkActionBar } from '@/Components/shared/BulkActionBar';
import { EmptyState } from '@/Components/shared/EmptyState';

export default function Index({ items, kpis, filters, permissions }) {
  return (
    <AppShellV2>
      {/* Slot 1 */}
      <PageHeader
        title="<Entidade>"
        subtitle={<KpiInlineSummary kpis={kpis} />}
        actions={<HeaderActions permissions={permissions} />}
      />

      {/* Slot 2 (opcional) */}
      <ModuleTopNav items={subTabItems} />

      {/* Slot 3 */}
      <Toolbar
        filters={filters}
        onSearch={handleSearch}
        savedViews={savedViews}
      />

      {/* Slot 4 (flutuante) */}
      <BulkActionBar selected={selected} actions={bulkActions} />

      {/* Slot 5 */}
      <Deferred data="items" fallback={<TableSkeleton />}>
        {items.length === 0
          ? <EmptyState ... />
          : <DataTable rows={items} columns={cols} onSelect={setSelected} />}
      </Deferred>

      {/* Slot 6 */}
      {drawerOpen && <EntidadeDrawer ... />}
    </AppShellV2>
  );
}
```

## Casos limite

- **Sem sub-tabs** (módulo simples · 1 vista só) — pula Slot 2, mantém ordem dos outros 5
- **Sem BulkBar** (módulo read-only) — pula Slot 4
- **Drawer fullscreen mobile** (<1100px) — CSS responsivo já no shared, sem código extra
- **Lista com >10K linhas** — virtualize (`react-virtual`) dentro do Slot 5 sem mudar API externa

## Quando PT-01 não cabe

Abre ADR explicando por que esse módulo precisa de padrão diferente. Exemplos válidos:

- Tela 100% gráfica (Dashboard com 8 charts) → PT-04 candidato
- Form gigante multi-step sem lista (cadastro inicial wizard) → PT-02 candidato
- Tela de configuração com seções verticais → PT-05 candidato

ADR vai em `06-decisoes/` (ou ADR UI no oimpresso) e justifica desvio com bench.

## Referências

- **ADR-mãe**: [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Substituído parcialmente**: [UI-0006 padrão tela operacional](../adr/ui/0006-padrao-tela-operacional.md) (UI-0006 = PT-01 antes de ser formalizado)
- **Drawer canon**: [ADR 0185](../../../decisions/0185-drawer-760-canon-entidades-cadastrais.md), [ADR 0179](../../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
- **Pattern reuse**: [ADR 0149](../../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- **Loop Design↔Code**: [PROTOCOL.md](../../../../prototipo-ui/PROTOCOL.md)
- **Skill correlata**: [`mwart-process`](../../../../.claude/skills/mwart-process/SKILL.md), [`charter-first`](../../../../.claude/skills/charter-first/SKILL.md)
- **Protótipo de origem**: [`prototipo-ui/prototipos/clientes/`](../../../../prototipo-ui/prototipos/clientes/) (KB-9.75 9,4/10)
- **Handoff Claude Design v2**: 2026-05-24 (Constituição UI v2, ponteiro em [UI-0013](../adr/ui/0013-constituicao-ui-v2-camadas.md))

## Versão

**v1.0** · 2026-05-24 · primeira formalização. Documenta o padrão já vivo em 12+ telas.

**Bump v1.1** quando: adicionar variante nova, esclarecer slot, atualizar adoção.
**Bump v2.0** quando: breaking change na anatomia (raro — exige ADR).
