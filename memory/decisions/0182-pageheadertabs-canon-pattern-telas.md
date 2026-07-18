---
slug: 0182-pageheadertabs-canon-pattern-telas
number: 182
title: "PageHeaderTabs pattern canon — header obrigatório de telas Inertia com sub-navegação (sidebar v3)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: core
quarter: 2026-Q2
tags: [ux, design-system, header-pattern, sidebar-v3, hicks-law, anti-duplication, persona-larissa, ADR-0180-sidebar-v3, ADR-0093-tier0, ADR-0094-constituicao]
supersedes: []
supersedes_partially: []
amends:
  - "0180-sidebar-v3-5-grupos-ghosts-header"
superseded_by:
  - "0189-pageheader-canon-v3-1-cadastro-roxo"   # superseded parcial 2026-05-24 — layout v3.1 (3 blocos fechados)
  - "0190-primary-button-roxo-universal-295"     # superseded parcial 2026-05-25 — primary universal roxo 295 (não mais hue per grupo)
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0178-sells-unified-tabs-visao-supersede-0136
  - 0180-sidebar-v3-5-grupos-ghosts-header
pii: false
review_triggers:
  - "Larissa @ ROTA LIVRE biz=4 reportar inconsistência entre tela X e tela Y do mesmo grupo (ex Financeiro/Vendas/OS) → checar se pattern foi aplicado em ambas"
  - "Tela nova adotando padrão diferente OU usando botão custom sem `os-btn primary` no canto direito → CI gate `pageheader:health` futuro alerta"
  - "Densidade >3 elementos visíveis no header além de ghost tabs + ⋯ Mais + primary → revisitar (provável: pattern não foi 100% aplicado)"
  - "Hick's Law score da tela <8/10 em audit → pattern não está rendendo o ganho previsto"
  - "Sinal qualificado de power-user ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)) pedindo botões inline em vez de overflow → reabrir e considerar prop opt-out"
---

# ADR 0182 — PageHeaderTabs pattern canon: header obrigatório de telas Inertia com sub-navegação

## Contexto

[ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md) estabeleceu o sidebar v3 (5 grupos canon + ghosts) e Fase 5 piloto Financeiro/Unificado canonizou o **header de tela** correspondente. Após:

- F5 piloto: `Pages/Financeiro/Unificado/Index.tsx` adota `<FinanceiroSubNav/>` + tweaks Wagner (PR #1363/#1364/#1365)
- F5 propagação: 11 outras telas Financeiro adotam (PR #1366)
- F5 fix: `hidePrimary` em todas as 11 pra preservar botões originais (PR #1367)
- F5 refine: botões action features movem pra `⋯ Mais` overflow + duplicados-com-ghost removidos (PR #1368)

O pattern foi validado em 12 telas Financeiro em produção biz=1 (review smoke Wagner 2026-05-21 — todas renderizam com layout consistente).

Pattern testado tem 3 ganhos mensuráveis:
1. **Hick's Law**: header tem ≤3 elementos visíveis (ghosts + ⋯ Mais + primary) em vez de 8-12 botões inline antigos
2. **Paridade visual entre telas**: usuário aprende UMA vez e o conhecimento transfere (ROTA LIVRE / OficinaAuto / etc)
3. **Manutenibilidade**: adicionar ação features = 1 entry em `extraOverflowItems[]` em vez de novo `<button>` JSX

Sem ADR canônica, **propagação pras outras telas (Vendas/OS/Compras/Catálogo/etc) corre risco de drift**: cada desenvolvedor pode interpretar o pattern de forma diferente, e o ganho de paridade evapora.

## Decisão

**Toda tela Inertia do oimpresso com sub-navegação (`ghosts`) DEVE adotar o pattern canonizado de header em 3 zonas.**

### 3 zonas obrigatórias do header

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Nome da tela · subtítulo       │  Ghost tabs ARIA  │  ⋯ Mais  │  + Novo X │
│ {periodo} · {biz} · {extra}    │  ARIA tablist     │  overflow│  primary  │
└──────────────────────────────────────────────────────────────────────────┘
   ◄── ZONA L ──►                ◄────── ZONA C ──────►        ◄── ZONA R ─►
   (título + sub)                (navegação inter-views)        (primary action)
```

**ZONA L (esquerda):** título `<h1>` + subtitle `<p>` ([ADR 0110](0110-tipografia-canon-h1-subtitle.md) tipografia canon). Classe `os-page-h-l` OU equivalente do componente PageHeader shared.

**ZONA C (centro):** ghost tabs ARIA via `<{Modulo}SubNav active="X" hidePrimary extraOverflowItems={[]}/>`. Renderiza:
- Ghost tabs ARIA `role="tablist"` (até `maxVisible=5` inline)
- Botão `⋯ Mais` (overflow `DropdownMenu` shadcn) com ghosts excedentes + `extraOverflowItems[]` (ações features) separados por divider

**ZONA R (direita):** botão `+ Novo X` primary com label contextualizado por tela. Classe `os-btn primary` (canon visual `oimpresso-cockpit.css`) OU `<Button>` shadcn com hue OKLCH do grupo via `style={{ backgroundColor: 'oklch(0.6 0.15 {hue})' }}`.

### Regras de classificação dos botões originais

Pra cada botão existente no header pre-pattern, decidir:

| Tipo | Critério | Destino |
|---|---|---|
| **Duplicado-com-ghost** | Botão navega pra outra tela que JÁ está como ghost (ex `Conciliar` → `/financeiro/conciliacao`, e o ghost `conciliacao` existe) | **Remover** — ghost cobre |
| **Ação features** | Abre dialog/sheet/modal (não-navegacional) — ex Resumir mês, Gateways, OCR boleto, Apresentar fullscreen | **Move pra `extraOverflowItems[]`** (com `icon` + `onClick` + opcional `title`) |
| **Primary action única** | "Nova X" / "Novo Y" / "Emitir Z" — UMA ação dominante da tela | **Zona R** — botão único no canto direito |
| **Botão per-linha tabela** | "Pagar"/"Receber"/"Editar" em cada row | **Manter intacto** — não é botão do header |

### Componentes canon disponíveis

| Componente | Path | Pra quê |
|---|---|---|
| `PageHeaderTabs` | [resources/js/Components/shared/PageHeaderTabs.tsx](../../resources/js/Components/shared/PageHeaderTabs.tsx) | Componente shared genérico — ghost tabs ARIA + overflow + opcional primary inline |
| `FinanceiroSubNav` | [resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx](../../resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx) | Wrapper que lê `shell.menu` Inertia e injeta ghosts do módulo Financeiro |
| `os-btn primary` | classe CSS `oimpresso-cockpit.css` | Botão primary canon (compatível com `os-page-h` header) |

### Pattern de wrapper por módulo (template)

Quando aplicar em novo módulo (Vendas/OS/Compras/etc), criar wrapper análogo ao `FinanceiroSubNav`:

```tsx
// resources/js/Pages/<Modulo>/_shared/<Modulo>SubNav.tsx
import { usePage } from '@inertiajs/react';
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderPrimary,
  type PageHeaderOverflowItem,
} from '@/Components/shared/PageHeaderTabs';

interface Props {
  active: string;
  extraOverflowItems?: PageHeaderOverflowItem[];
  hidePrimary?: boolean;
}

export default function ModuloSubNav({ active, extraOverflowItems, hidePrimary }: Props) {
  const sharedShell = (usePage().props as any)?.shell as {
    menu?: Array<{ label: string; group?: string; primary?: PageHeaderPrimary; ghosts?: PageHeaderGhost[] }>;
  } | undefined;

  // Procura entry do módulo no shell.menu (declarada pelo DataController via attrs `group`/`primary`/`ghosts`)
  const item = sharedShell?.menu?.find(
    (m) => m.group === '<grupo_v3>' || m.label?.toLowerCase() === '<label_canon>',
  );

  if (!item?.ghosts?.length) return null;

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : item.primary}
      ghosts={item.ghosts}
      activeGhostKey={active}
      group="<grupo_v3>"
      maxVisible={5}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
```

### Pre-requisitos backend (ADR 0180 Fase 4)

`Modules/<Modulo>/Http/Controllers/DataController::modifyAdminMenu()` deve declarar attrs no entry principal:

```php
$menu->url('/<modulo>/<rota_principal>', '<Label>', [
    'icon'     => 'fa fas fa-<icon>',
    'group'    => '<grupo_v3>',  // ou key legacy v2 (LEGACY_GROUP_MAP cobre)
    'shortcut' => 'G <X>',
    'primary'  => [
        'label'    => 'Novo <X>',
        'href'     => '/<modulo>/<rota_create>',
        'shortcut' => 'N',
    ],
    'ghosts'   => [
        ['key' => 'unificado',    'label' => '<Label canon>', 'href' => '/<modulo>/<rota_principal>'],
        ['key' => '<sub-view-1>', 'label' => '<Sub-view 1>',  'href' => '/<modulo>/<sub-view-1>'],
        // ... até 13 ghosts (overflow `⋯ Mais` absorve >5)
    ],
])->order(N);
```

## Justificativa

**Por que pattern obrigatório em vez de "boa prática":**

- Sem gate, drift entre telas é inevitável (devs interpretam diferente)
- Larissa @ ROTA LIVRE (persona piloto) decora fluxos — se 2 telas Financeiro têm headers diferentes, ela re-aprende em cada uma (custo cognitivo desnecessário)
- ROI da F5 (12 telas Financeiro) só se materializa se OUTRAS 30+ telas (Vendas/OS/Compras/Catálogo/etc) seguirem o mesmo pattern

**Por que botões action vão pro `⋯ Mais` (e não inline):**

- Hick's Law: 8 botões inline = 8 escolhas paralelas (eye tracking 800-1200ms). 1 botão `⋯ Mais` = 1 escolha (200ms) + opcional clique pra refinar
- Ações features-específicas (Resumir mês / OCR / Apresentar) NÃO são uso diário — uso ocasional aceita 1 clique extra pra abrir overflow
- Densidade visual reduzida = menos "assusta" (Wagner feedback 2026-05-21)

**Por que primary fica na zona R (não dentro do PageHeaderTabs):**

- Hierarquia visual canônica de Western UI: F-pattern eye tracking favorece canto-superior-direito pra primary CTA
- Wagner 2026-05-21 review explícita: "Botão na posição errada" — primary inline ao lado dos ghosts era ambíguo
- Separação `os-btn primary` custom (CSS canon `os-page-h`) vs `Button` shadcn (Tailwind) garante harmonia visual com header `os-page-h` legacy

**Por que removemos botões duplicados-com-ghost:**

- DRE tinha `Conciliar` + `Plano de contas` inline + ghost tabs com mesmos destinos → 2x mesmo clique-alvo
- Anti-padrão de UI clássico (Sherwin's Razor: "navegação UMA vez por tela")

**Quando reabrir esta ADR:**

- Power-user (sinal qualificado [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)) pedir botões features inline em vez de overflow
- Telas com >13 ghosts onde `⋯ Mais` fica denso demais → repensar arquitetura
- Mobile (≤768px) precisar adaptação além do scroll-x snap atual
- Tema dark/light alterar contrast de `os-btn primary` ou hue OKLCH

## Consequências

**Positivas:**

- **Paridade visual** entre todas as telas dos 36 módulos (quando F5 propagar)
- **Larissa decora UMA vez** o jogo de header — economiza ~15min/tela em onboarding novos colaboradores
- **Sub-agents replicam pattern facilmente** — wrapper `<Modulo>SubNav` é template, ROI propagação <2h por módulo
- **CI gate futuro viável** — gate pode validar presença de `<XxxSubNav active=...` em todo `Pages/<Mod>/Index.tsx`
- **Manutenibilidade** — adicionar ação features = 1 entry em array, sem JSX novo

**Negativas / Trade-offs:**

- **Refator inicial de 30+ telas não-Financeiro** ainda pendente (Vendas/OS/Compras/Catálogo/RH/etc) — esforço ~1.5-2 dias com 4 sub-agents paralelos
- **Botões features ficam 1 clique mais distantes** (overflow) — power-user pode reclamar; mitigação Cmd+K (F6 ADR 0180) cobre
- **Wrappers por módulo** (FinanceiroSubNav, VendasSubNav, etc) = ~5 arquivos novos — aceitável pra clareza
- **Charters de cada tela precisam atualização** ([ADR 0101](0101-sistema-charter-capterra-governanca-escopo.md)) — documentar pattern aplicado

**Riscos mitigados:**

- **Drift inter-tela**: este ADR vira referência de revisão de PR (reviewers checam se header tem 3 zonas)
- **Botão primary inconsistente**: classe `os-btn primary` canon documentada
- **Sub-agent inventa pattern alternativo**: ADR canon referenciado em todos prompts F5 daqui pra frente

## Plano de execução

| Fase | O quê | Esforço |
|---|---|---|
| **F0** | Esta ADR + atualização [skill `cockpit-runbook`](../../.claude/skills/cockpit-runbook/SKILL.md) referenciando | 1h (este PR) |
| **F1** | Atualizar [skill `mwart-comparative`](../../.claude/skills/mwart-comparative/SKILL.md) pra incluir checklist "header em 3 zonas" no F1.5 visual-comparison gate | 30min |
| **F2** | Aplicar pattern pras outras ~30 telas — 4 sub-agents paralelos: Vendas (Sells) · OS (Repair/OficinaAuto) · Comercial (Crm/ContasReceber/ContasPagar já feitos) · Operação (Compras/Manufacturing/Estoque/Fiscal/RH) | 1.5-2 dias |
| **F3** | CI gate `pageheader:health` — valida que cada `Pages/<Mod>/Index.tsx` em main com sub-views correspondentes tem `<XxxSubNav/>` no JSX (warn-only inicial, hard após backfill) | 2-3h |
| **F4** | Atualizar charters das telas migradas com nota "header pattern: ADR 0182 OK" | ~30min × 12 = 6h (paralelizável) |

## Wrapper por módulo (templates futuros)

| Módulo | Wrapper sugerido | Grupo v3 |
|---|---|---|
| Sells (Vendas) | `resources/js/Pages/Sells/_shared/VendasSubNav.tsx` | `vender` |
| Crm (Clientes) | `resources/js/Pages/Crm/_shared/ClientesSubNav.tsx` | `vender` |
| ProductCatalogue | `resources/js/Pages/ProductCatalogue/_shared/CatalogoSubNav.tsx` | `vender` |
| Repair/OficinaAuto | `resources/js/Pages/OS/_shared/OsSubNav.tsx` (compartilhado) | `operar` |
| Manufacturing | `resources/js/Pages/Producao/_shared/ProducaoSubNav.tsx` | `operar` |
| Compras | `resources/js/Pages/Compras/_shared/ComprasSubNav.tsx` | `operar` |
| Estoque (AssetManagement+) | `resources/js/Pages/Estoque/_shared/EstoqueSubNav.tsx` | `operar` |
| **Financeiro** | `resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx` ✅ existe | `financas` |
| NfeBrasil (Fiscal) | `resources/js/Pages/Fiscal/_shared/FiscalSubNav.tsx` | `financas` |
| Essentials (RH) | `resources/js/Pages/Rh/_shared/RhSubNav.tsx` | `pessoas` |
| Governance | `resources/js/Pages/Governanca/_shared/GovernancaSubNav.tsx` | `sistema` |
| Cms/Connector/Officeimpresso | `resources/js/Pages/Plataforma/_shared/PlataformaSubNav.tsx` | `sistema` |

## Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- `shell.menu` já filtra por `business_id` (HandleInertiaRequests + LegacyMenuAdapter)
- Wrappers retornam `null` se `item.ghosts.length === 0` (tenant sem o módulo)
- `extraOverflowItems` que abrem dialog devem checar `auth()->user()->can(...)` no handler (não no DTO)

## Métricas de sucesso (loop fechado — [Constituição v2 princípio 4](0094-constituicao-v2-7-camadas-8-principios.md))

| Métrica | Baseline (pré-F5) | Meta pós-F5 propagação |
|---|---|---|
| Telas com pattern 3 zonas | 0/30+ | 100% (gate CI valida) |
| Densidade média elementos no header | 8-12 botões inline | ≤3 (ghosts + ⋯ + primary) |
| Tempo de aprendizagem inter-tela (smoke usuário novo) | medir baseline | -50% |
| Tickets suporte "como faço X" cross-tela | medir baseline 30d | -40% em 60d |
| Hick's Law score qualitative (audit) | 5-6/10 | ≥9/10 |

## Referências

- [ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3 canon (este ADR amends)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0110](0110-tipografia-canon-h1-subtitle.md) — Tipografia canon h1/subtitle
- [ADR 0178](0178-sells-unified-tabs-visao-supersede-0136.md) — Tabs unificada pattern
- PR #1363/#1364/#1365 — F5 piloto Financeiro/Unificado (3 tweaks pré-canon)
- PR #1366 — F5 propagação 11 telas Financeiro
- PR #1367 — F5 fix hidePrimary
- PR #1368 — F5 refine botões pro overflow
- Wagner reviews 2026-05-21: "Botão na posição errada, por favor e a cor" · "Os botões das telas têm nomes legais" · "ja tem botões na tela deve ir para ⋯ se não forem duplicados" · "Salve o padrão do header"
- Smoke prod 2026-05-21: 11/12 telas Financeiro validadas em biz=1 (DRE deu 500 transitório de cache Vite, voltou OK em 5min)
