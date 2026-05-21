---
slug: 0180-sidebar-v3-5-grupos-ghosts-header
number: 180
title: "Sidebar v3 — 5 grupos canônicos (VENDER · OPERAR · FINANÇAS · PESSOAS · SISTEMA) + ghosts ARIA tablist no header da tela + Cmd+K global + Pinned/Favoritos"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: cockpit
quarter: 2026-Q2
tags: [ux, navigation, sidebar, hicks-law, persona-larissa, datacontroller, cmd-k, multi-tenant-tier-0, ADR-0093-tier0, ADR-0094-constituicao, ADR-0178-tabs-padrao, anthropic-design-plugin]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0178-sells-unified-tabs-visao-supersede-0136"
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
pii: false
review_triggers:
  - "Larissa @ ROTA LIVRE biz=4 reclamar que não acha feature X em <4s → revisar mapeamento ghost ou pinned default"
  - "Tickets de suporte 'onde fica Y' >baseline -50% após 30d → reabrir e revisar grupo ou label"
  - "Módulo novo entrar e não couber em 1 dos 5 grupos → reabrir (sinal de gap taxonômico, não criar grupo 6)"
  - "Telemetria mostrar >40% dos usuários usando Cmd+K como entrada primária → considerar reduzir sidebar pra rail-only default"
  - "Score Module Grade D2 (UX) <85 pós-rollout → re-avaliar nomes/ordem dos grupos"
---

# ADR 0180 — Sidebar v3: 5 grupos canônicos + ghosts header + Cmd+K

## Contexto

Sidebar atual (`resources/js/Components/cockpit/Sidebar.tsx` v2, [Sidebar.tsx:126](../../resources/js/Components/cockpit/Sidebar.tsx#L126)) declara **11 grupos** com ~50 labels visíveis em sessão típica:

```
ACESSOS RÁPIDOS · OFICINA AUTO · FINANCEIRO·OPERAÇÃO · FINANCEIRO·ANÁLISE ·
FINANCEIRO·AJUSTES · FINANCEIRO (legacy) · ESTOQUE · FISCAL · RH ·
CONHECIMENTO · RELATÓRIOS · IA & PRODUTIVIDADE · GOVERNANÇA · PLATAFORMA
```

Wagner 2026-05-21 sessão: *"acho muito grande isso assusta"*. Pesquisa estado-da-arte (WebSearch Linear/Stripe/Shopify/Notion/Vercel + Hick's Law) e benchmark em 10 dimensões pesadas mostraram:

| Sistema | Score |
|---|---|
| Linear | 93/100 |
| Stripe Dashboard | 89/100 |
| Vercel | 88/100 |
| Shopify Admin | 85/100 |
| Notion | 82/100 |
| **oimpresso v2 (hoje)** | **58/100** |

Gaps principais:
1. **11 grupos** viola Hick's Law (7±2 ótimo, líderes têm 4-5)
2. **Hierarquia no sidebar em vez de in-screen** — vs Linear/Stripe que usam sidebar slim + headers contextuais
3. **Sem Cmd+K global** — discoverability ruim pra power-user
4. **Sem mobile/responsive plan** — ghosts truncam <1280px
5. **Multi-tenant fraco** — frontend hardcode labels (Wagner regra 2026-05-19 já mitiga via `data['group']`)
6. **Sem pinned/favoritos** — Shopify/Notion pattern ausente

Proposta inicial (1 item/grupo + header ghosts) subiu score 58→81 (+23pts). Para chegar **Linear-tier (91/100)** faltam 5 ajustes: Cmd+K, mobile-aware, pinned, atalhos kbd, ARIA tablist.

## Decisão

**Sidebar v3 adota arquitetura unificada em 4 camadas:**

### 1. Topo fixo (3 itens, sem grupo)

Sempre visível, single-click, hierarquia mais alta.

| Item | Atalho | Destino | Ghosts |
|---|---|---|---|
| ✦ **IA** | `G I` | `/copiloto` | Memórias · Brief · Regras · KB · Relatórios cross-domínio |
| ☎ **Atendimento** | `G A` | `/inbox` | WhatsApp · Tickets · OS Pública |
| ◐ **Equipe** | `G E` | `/equipe` | Pessoas · Tarefas · Convites |

### 2. 5 grupos canônicos

> Nomes verbo-PT-BR-Larissa-friendly. Substantivos universais. Extensível pra 4 verticais (vestuário/oficina/gráfica/conserto) sem forçar mental model de um sobre outro.

```
TOPO       → IA · Atendimento · Equipe

VENDER     → $ Vendas · ♥ Clientes · ▣ Catálogo
OPERAR     → ⚙ Ordens de Serviço · ⚒ Produção · ▥ Estoque
FINANÇAS   → ₿ Financeiro · ⎙ Fiscal
PESSOAS    → ☻ RH
SISTEMA    → ⚖ Governança · ◇ Plataforma
```

**Métricas:** 3 + 11 = **14 labels visíveis** · Hick's Law 10/10 · score projetado **91/100**.

### 3. Header da tela (PageHeader canon)

Hierarquia segue **in-screen, não in-sidebar**. Cada destino abre tela com **PageHeader unificado**:

```
┌────────────────────────────────────────────────────────────────────┐
│  Financeiro                                              ⌘K  ⓘ  ?  │
│                                                                    │
│  [+ Novo título]   Unificado · Pagar · Receber · Caixa · ⋯ Mais   │
│   ▲ colorido        ▲ ghost tabs (ARIA role="tablist")            │
└────────────────────────────────────────────────────────────────────┘
```

- **`[+ Novo X]`** primary colorido — atalho `N`
- **Ghosts** ARIA `role="tablist"` → `tab/tabpanel` (a11y WCAG)
- **Default tab = "Unificado"** ([ADR 0178](0178-sells-unified-tabs-visao-supersede-0136.md) pattern)
- **Overflow >5 ghosts** vira "⋯ Mais" dropdown
- **Hue por grupo** (OKLCH var `--gh`) — Financeiro 145, Vender 60, Operar 350, etc

### 4. Cmd+K global + Pinned

- **Cmd+K palette** — indexa MenuItems + ghosts + entities recentes. Fuzzy match. Global em qualquer página.
- **Pinned/Favoritos** — LocalStorage `oimpresso.cockpit.b<bizId>.pinned[]`. Click direito em ghost → "Fixar no sidebar". Renderiza seção FIXADOS no topo.
- **Atalhos kbd `G X X`** — sequência de letras (G + grupo + ghost). Overlay visual da sequência.

## Justificativa

**Por que 5 grupos e não 4 nem 7:**

- **4 grupos** (Opção C "Fluxo do dinheiro") cria grupo "SAÍDA" mistura OS+Produção+Estoque+RH — RH com Estoque conceitualmente errado. Score 84.
- **6+ grupos** volta a violar Hick's Law (>7±2 estressa decisão). Linear tem 5, Shopify tem 5.
- **5 grupos com PESSOAS isolado** é honesto: hoje só RH; quando entrar Treinamento/Avaliação/Recrutamento, encaixam.

**Por que verbos (VENDER/OPERAR) e não substantivos (COMERCIAL/OPERAÇÃO):**

- Persona-fit Larissa @ ROTA LIVRE biz=4 (dona de loja não-técnica) — verbo > substantivo > acrônimo
- Universal pros 4 verticais (vestuário/oficina/gráfica/conserto) — todos VENDEM, OPERAM, têm FINANÇAS, gerenciam PESSOAS, configuram SISTEMA
- "Vender" tem energia ativa; "Comercial" é frio
- "Operar" abraça OS+Produção+Estoque sem forçar analogia (vs "Entregar" que sugere delivery)

**Por que Relatórios NÃO vira grupo:**

- Linear/Stripe **não** isolam reports — vivem dentro de cada domínio como aba/ghost
- Hoje cada Module já tem seu "Relatórios" interno — duplicação remove
- Cross-domínio sobe pra `IA → Brief` (Copiloto consolida)

**Por que header ghosts e não sub-itens no sidebar:**

- Hierarquia in-screen escala 5→50 features sem restructure (padrão Linear/Notion/Vercel/Stripe)
- Sidebar persistente como mapa de **destinos**, não de **ações**
- Sub-funções (Pagar/Receber/Boletos) são contextuais da tela — ghost ARIA tablist preserva acessibilidade

**Quando faz sentido reabrir esta ADR:**

- Persona power-user pedir multi-split (Financeiro + Vendas lado a lado) → trocar ghosts por panels
- Módulo novo não couber em nenhum dos 5 grupos → reavaliar taxonomia (NÃO criar grupo 6)
- Telemetria mostrar >40% entrada via Cmd+K → considerar sidebar rail-only default
- Larissa decorar fluxos novos (feedback-rotalivre 2026-04-24) e nomes verbos atrapalharem → reverter pra substantivo

## Consequências

**Positivas:**

- **Sidebar de 50 → 14 labels** (-72%) — Hick's Law 6→10
- **Score consolidado 58 → 91** (+33pts) — Linear-tier (gap só polish microcopy/animação)
- **Mental model PME-BR universal** — 4 verticais cabem nos mesmos 5 grupos sem força
- **Hierarquia in-screen escala 5→50 features** sem mudar sidebar
- **Cmd+K cobre power-user** — Larissa por sidebar, Wagner por palette
- **Pinned/Favoritos personaliza** sem fragmentar arquitetura
- **A11y WCAG** ARIA tablist + atalhos kbd + foco visível
- **Multi-tenant Tier 0 preservado** — DataController declara por `business_id`
- **Backward-compat** — `findGroupKey()` fallback por label + LEGACY_GROUP_MAP cobrindo migração faseada

**Negativas / Trade-offs:**

- **Renomear `SIDEBAR_GROUPS` de 11→5 keys** quebra qualquer hardcode externo. Mitigação: LEGACY_GROUP_MAP em [Sidebar.tsx:221](../../resources/js/Components/cockpit/Sidebar.tsx#L221) `findGroupKey()`
- **17 DataControllers** precisam migrar (`data['group']` + declarar `ghosts[]` + consolidar múltiplos MenuItems em 1 canônico) — ~2h × 17 = 34h
- **~30 telas Inertia** precisam adotar `<PageHeader/>` — ~1h × 30 = 30h
- **PESSOAS com só 1 item (RH)** parece grupo vazio — aceitável, extensível
- **Cmd+K palette é componente novo** — 2d dev + testing
- **Mobile/responsive** ghosts viram scroll-x snap — precisa testing real em 360px-1024px range
- **Larissa decora fluxos** — labels grupo mudam (`ACESSOS RÁPIDOS` some, `VENDER` aparece) — mitigação: localStorage migration silenciosa + tooltip primeira semana

**Riscos mitigados:**

- **Larissa não acha "Boletos" sem treino** → Cmd+K resolve (Larissa pode digitar "bole" e ir direto)
- **OfficeImpresso churn migração** → ghost "Officeimpresso" preservado dentro de SISTEMA→Plataforma
- **Módulo novo sem grupo** → fallback `mais` (collapse fechado default) cobre — depois ADR decide grupo canônico

## Plano de execução (9 fases sequenciais)

> Backward-compat: `LEGACY_GROUP_MAP` permite migração faseada — módulos não-migrados ainda funcionam. Cada fase é PR atômico ≤300 LOC ([commit-discipline](0094-constituicao-v2-7-camadas-8-principios.md)).

| Fase | PR | Conteúdo | Esforço | Bloqueia? |
|---|---|---|---|---|
| **0** | Este ADR + protótipo Cowork (`prototipo-ui/prototipos/sidebar-v3-unificado/`) — DOCS only | 1h | — |
| **1** | `app/Sidebar/MenuItemContract.php` — schema novo (`group`/`shortcut`/`primary`/`ghosts`) + validação + Pest | 0.5d | F0 |
| **2** | `Sidebar.tsx` substituir `SIDEBAR_GROUPS` 11→5 keys + `SIDEBAR_GROUP_HUE` 5 keys + `LEGACY_GROUP_MAP` fallback | 0.5d | F1 |
| **3** | `PageHeader.tsx` componente novo + responsive scroll-x + ARIA tablist + overflow "Mais" | 1d | F2 |
| **4** | Migração 17 DataControllers (ordem uso: Sells · Financeiro · Crm · ProductCatalogue · Repair · OficinaAuto · Compras · NfeBrasil · Manufacturing · Whatsapp · TeamMcp · Jana · Essentials · Ponto · Governance · Cms · Officeimpresso) | 4-5d (paralelizável em 4 subagents → 1.5d) | F3 |
| **5** | ~30 telas Inertia adotam `<PageHeader/>` + charters atualizados | 4d (paralelizável em 4 subagents → 1.5d) | F4 |
| **6** | `CmdPalette.tsx` + fuzzy match + atalho global ⌘K/Ctrl+K + Pest browser MCP | 2d | F5 |
| **7** | Pinned/Favoritos — LocalStorage `oimpresso.cockpit.b<bizId>.pinned[]` + click direito em ghost + render FIXADOS topo | 1d | F6 |
| **8** | Atalhos kbd `G X X` — listener global + overlay visual da sequência | 1d | F7 |
| **9** | Cleanup legacy — remover `SUPERADMIN_LABELS` (já vazio), `USER_MENU_LABELS` legacy, hardcoded labels antigas. Pest snapshot re-baseline. | 0.5d | F8 |

**Total: ~15-17 dias (1 dev sequencial) ou ~5-7 dias (4 sub-agents paralelos em F4 + F5).**

## Contrato DataController v2

```php
// Modules/<X>/Sidebar/DataController.php
public function items(int $businessId): array
{
    if (!$this->isInstalledFor($businessId)) {
        return [];  // Tier 0 multi-tenant — tenant sem módulo não declara
    }

    return [[
        'label'    => 'Financeiro',                  // ← label canônico
        'href'     => route('financeiro.index'),
        'icon'     => 'currency-dollar',
        'group'    => 'financas',                    // ← uma das 5: vender/operar/financas/pessoas/sistema
        'shortcut' => 'G F',                         // ← NOVO: atalho kbd sidebar
        'primary'  => [                              // ← NOVO: botão "+ Novo" colorido
            'label'    => 'Novo título',
            'href'     => route('financeiro.create'),
            'shortcut' => 'N',
        ],
        'ghosts'   => [                              // ← NOVO: header tabs ARIA
            ['key' => 'unificado',   'label' => 'Unificado',     'href' => '/financeiro?tab=unificado'],
            ['key' => 'pagar',       'label' => 'Pagar',         'href' => '/financeiro?tab=pagar'],
            ['key' => 'receber',     'label' => 'Receber',       'href' => '/financeiro?tab=receber'],
            ['key' => 'caixa',       'label' => 'Caixa',         'href' => '/financeiro?tab=caixa'],
            ['key' => 'conciliacao', 'label' => 'Conciliação',   'href' => '/financeiro?tab=conciliacao'],
            ['key' => 'boletos',     'label' => 'Boletos',       'href' => '/financeiro?tab=boletos'],
            ['key' => 'dre',         'label' => 'DRE',           'href' => '/financeiro?tab=dre'],
            ['key' => 'plano',       'label' => 'Plano de Contas','href' => '/financeiro?tab=plano'],
        ],
    ]];
}
```

## Mapeamento canônico Módulo → Grupo (36 módulos)

| Módulo | Grupo v3 | Vira label canônico | Notas |
|---|---|---|---|
| `Sells` (UltimatePOS core) | `vender` | "Vendas" | [ADR 0178](0178-sells-unified-tabs-visao-supersede-0136.md) já unifica |
| `Crm` | `vender` | "Clientes" | absorve Contatos |
| `ProductCatalogue` | `vender` | "Catálogo" | absorve Preços como ghost |
| `Vestuario` | `vender` (ghost de Catálogo) | — | vertical vestuário |
| `Woocommerce` | `vender` (ghost de Vendas) | — | canal de venda |
| `Repair` | `operar` | "Ordens de Serviço" | absorve verticais |
| `OficinaAuto` | `operar` (ghost de OS) | — | vertical CNAE 4520/2212/4581 |
| `ComunicacaoVisual` | `operar` (ghost de OS) | — | vertical gráfica |
| `ConsultaOs` | (topo `atendimento`, ghost) | — | link público |
| `Manufacturing` | `operar` | "Produção" | — |
| `Compras` | `operar` (ghost de Estoque) | — | input do estoque |
| `AssetManagement` | `operar` (ghost de Estoque) | — | gestão ativos |
| `Financeiro` | `financas` | "Financeiro" | absorve PaymentGateway+RecurringBilling+core legacy |
| `PaymentGateway` | `financas` (ghost) | — | [ADR 0170](0170-paymentgateway-extracao-camada-cobranca.md) |
| `RecurringBilling` | `financas` (ghost) | — | — |
| `NfeBrasil` | `financas` | "Fiscal" | absorve NFSe |
| `NFSe` | `financas` (ghost de Fiscal) | — | — |
| `Essentials` (HRM) | `pessoas` | "RH" | — |
| `Ponto` | `pessoas` (ghost de RH) | — | — |
| `Jana` | (topo `ia`) | "IA" | absorve KB/Brief/SRS |
| `KB` | (topo, ghost de IA) | — | — |
| `Brief` | (topo, ghost de IA) | — | — |
| `SRS` | (topo, ghost de IA) | — | possível deprecação |
| `Whatsapp` | (topo `atendimento`) | "Atendimento" | — |
| `TeamMcp` | (topo `equipe`) | "Equipe" | — |
| `ProjectMgmt` | (topo, ghost de Equipe) | — | tarefas do time |
| `Governance` | `sistema` | "Governança" | — |
| `ADS` | `sistema` (ghost) | — | — |
| `Auditoria` | `sistema` (ghost) | — | — |
| `Cms` | `sistema` (ghost de Plataforma) | — | — |
| `Connector` | `sistema` (ghost de Plataforma) | — | — |
| `Officeimpresso` | `sistema` (ghost de Plataforma) | — | desktop legacy |
| `Superadmin` | `sistema` (ghost de Plataforma) | — | — |
| `Arquivos` | `sistema` (ghost de Plataforma) | — | — |
| `Spreadsheet` | `sistema` (ghost de Plataforma) | — | Planilha |
| `Admin` | shell (core) | — | install/setup |

## LEGACY_GROUP_MAP (compat durante migração)

```ts
// resources/js/Components/cockpit/Sidebar.tsx
const LEGACY_GROUP_MAP: Record<string, string> = {
  // 11 keys antigas → 5 keys novas
  'office':       'vender',      // ACESSOS RÁPIDOS (Sells/Crm/Catálogo)
  'oficina':      'operar',      // OFICINA AUTO
  'fin':          'financas',    // FINANCEIRO legacy
  'fin-op':       'financas',    // FINANCEIRO · OPERAÇÃO
  'fin-analise':  'financas',    // FINANCEIRO · ANÁLISE
  'fin-config':   'financas',    // FINANCEIRO · AJUSTES
  'estoque':      'operar',      // ESTOQUE
  'fiscal':       'financas',    // FISCAL (junta com Financeiro)
  'rh':           'pessoas',     // RH
  'conhecimento': 'ia',          // CONHECIMENTO → topo IA
  'rel':          'ia',          // RELATÓRIOS → topo IA (Brief consolida)
  'ia':           'ia',          // IA & PRODUTIVIDADE → topo
  'governanca':   'sistema',     // GOVERNANÇA
  'plataforma':   'sistema',     // PLATAFORMA
};
```

Módulos não-migrados ainda funcionam — caem no grupo certo via map. Migração modulo-a-modulo em PRs separados.

## Multi-tenant Tier 0 (obrigatório — [ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- DataController recebe `business_id` e filtra antes de declarar (`isInstalledFor`)
- Ghosts também respeitam — ex: `RH → Ponto` só aparece se `Modules/Ponto` instalado **pro `business_id`**
- LocalStorage pinned scopado per-business: `oimpresso.cockpit.b<bizId>.pinned[]`
- Cmd+K palette indexa só MenuItems do business ativo (Inertia shared props já filtra)

## Métricas de sucesso (loop fechado — [Constituição v2 princípio 4](0094-constituicao-v2-7-camadas-8-principios.md))

| Métrica | Baseline (v2 hoje) | Meta v3 |
|---|---|---|
| Labels visíveis no sidebar | ~50 | ≤17 |
| Tempo médio achar feature (Larissa biz=4, browser MCP smoke) | ~12s | ≤4s |
| Cliques pra abrir "Boletos" | 3 (grupo→expand→click) | 2 (Financeiro→Boletos) ou 1 (⌘K "bole") |
| Hick's Law score (qualitative) | 6/10 | 9/10 |
| Score benchmark vs Linear (10 dims pesadas) | 58/100 | 91/100 |
| Tickets suporte "onde fica X" | medir baseline 30d pré-rollout | -50% em 30d pós |
| Module Grade D2 (UX) | medir baseline | ≥85 |
| Cmd+K como entrada primária (% sessões) | 0% (não existe) | 15-30% (telemetria 30d) |

## Referências

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — MWART (5 fases)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — Loop Cowork ↔ Claude Code
- [ADR 0178](0178-sells-unified-tabs-visao-supersede-0136.md) — Pattern tabs/visão unificada
- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer 760px (pattern visual)
- [Dossiê comparativo Linear/Stripe/Shopify/Notion/Vercel](../sessions/2026-05-21-arte-sidebar-navegacao-comparativo.md)
- [Skill `sidebar-menu-arch`](../../.claude/skills/sidebar-menu-arch.md) — arquitetura DataController
- [Protótipo Cowork v3](../../prototipo-ui/prototipos/sidebar-v3-unificado/visual-source.html)
