---
pattern_id: PT-07
nome: Feed/Timeline
camada: 3-padroes-tela
status: draft
versao: 0.1
created: 2026-07-11
parent_adr: UI-0013
applied_in:
  - Pages/team-mcp/CcSessions/Index.tsx
  - Pages/ProjectMgmt/Inbox/Index.tsx
  - Pages/Fiscal/Eventos.tsx
---

# PT-07 · Feed/Timeline — padrão canônico de tela-cronologia

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md#camada-1--fundacoes) + [Shell](../README.md#camada-2--shell) e nunca contradiz. Módulo só configura os slots (filtros, tipo de item, drawer), **não** muda a estrutura.
>
> ⚠️ **status: draft.** Formalizado a partir de **3 telas reais** que a rede `pt-conformance` (wave 1, 2026-07-11) barrou como "lista via `.map` / timeline" — nenhuma cabia em PT-01 (Lista paginável de entidade em `<table>`), mas as três compartilham o mesmo arquétipo: **cronologia de eventos/itens renderizada em cards, não em tabela**. Promove a `live` quando as 3 convergirem no golden (hoje Fiscal/Eventos usa CSS `fx-*` próprio — ver §Drift) + revisão Wagner.

## Quando aplicar

Tela cujo propósito é **percorrer uma cronologia** — eventos, notificações, sessões, atividades — onde o dado é **append-only / temporal** e o usuário lê de cima pra baixo (mais recente primeiro), **não** edita uma grade de colunas. Cada item é um **card** com carimbo de tempo relativo, não uma linha de `<table>`.

Sinais de que é PT-07 e não PT-01:
- O item **não tem colunas fixas comparáveis** (é um "cartão de acontecimento", largura variável) — some o cabeçalho de colunas.
- Ordena por **tempo** (recência), não por campo pivotável.
- Frequentemente **agrupado por tipo/dia** (`grouped.map` → `<section>`), com filtro por categoria em chips.
- Ação primária é **abrir o detalhe** (drawer/deep-link), não editar in-place.

Não aplicar pra:
- Lista paginável de **entidade** com colunas (clientes, NF-e, vendas) → [PT-01 Lista](PT-01-Lista.md) (`<table>`/DataTable).
- Painel de leitura **agregada** (KPIs gigantes + gráficos) → [PT-04 Dashboard](PT-04-Dashboard.md).
- Timeline **dentro** de um detalhe (histórico de 1 registro) → é slot de [PT-03 Detalhe](PT-03-Detalhe.md), não tela própria.

## Golden eleito · `team-mcp/CcSessions/Index.tsx`

[`resources/js/Pages/team-mcp/CcSessions/Index.tsx`](../../../../resources/js/Pages/team-mcp/CcSessions/Index.tsx) · "Feed cronológico de sessões Claude Code do time".

**Por que esta:**
- **Único candidato que monta o feed 100% com shared canônicos**: `PageHeader` (`:159`) + `KpiGrid`/`KpiCard` (`:165-170`) + `EmptyState` (`:231,246`) — os outros hand-rolam o header ou o vazio.
- **Item é card com carimbo de tempo relativo** (`fmtRelative(s.started_at)` `:282`) + status como **dot** (sem bg-fill, `:267,293`) + métricas em `tabular-nums` (`:287-295`) — exatamente a gramática de um feed.
- **3 estados de vazio explícitos** — skeleton (`:226`), primeiro-acesso com CTA (`:231`), busca-sem-resultado (`:246`) — o feed nunca "some".
- **Abrir = drawer** (`SessionDrawer` `:320`), não modal-sobre-modal nem navegação destrutiva.
- **Toolbar de filtros + busca `/`** (`:173-222`) e **paginação** (`:302-316`) sem virar `<table>`.
- **Atalhos J/K/↵/`/`** (`:220`, handler no componente) — navegação de teclado igual PT-01.

**Por que descartei as outras (viram "aplicado em", não professor):**
- **`ProjectMgmt/Inbox/Index.tsx`** — **bom aluno**: usa `PageHeader` + `KpiGrid`/`KpiCard` shared (`:286-289`), J/K/Enter/R (`:191-225`), agrupa por tipo em `<section>` (`:301`). Mas hand-rola o EmptyState (`:292`, sem `<EmptyState>` shared) e não tem drawer (deep-link pro Board). Segue o padrão, não o define.
- **`Fiscal/Eventos.tsx`** — timeline legítima (`fx-timeline` `:157`, filtro por tipo em chips `:125`, callout de janelas legais), mas estiliza com **CSS próprio `fiscal-cockpit.css`** (`fx-timeline`/`fx-chip`/`fx-callout` `:20`) em vez dos shared. É a prova de que o arquétipo existe em ≥2 módulos, mas o visual é ilha — **anti-golden parcial** (ver §Drift).

## Anatomia · 6 slots

```
┌─────────────────────────────────────────────────────────────┐
│ 1 · PageHeader     ícone · título · descrição/contexto        │ ← sticky
├─────────────────────────────────────────────────────────────┤
│ 2 · KPI strip      (opcional) <KpiGrid> resumo do período     │
├─────────────────────────────────────────────────────────────┤
│ 3 · Filtros        chips por tipo/categoria + período + busca │
├─────────────────────────────────────────────────────────────┤
│ 4 · Feed           cronologia em cards via .map               │
│                    (agrupada por tipo/dia em <section>)       │
│                    item = dot status · tempo relativo · resumo│
├─────────────────────────────────────────────────────────────┤
│ 5 · Paginação      (opcional) links OU "carregar mais"        │
├─────────────────────────────────────────────────────────────┤
│ 6 · Drawer         slide-in do detalhe do item (deep-link)    │ ← reusa PT-03
└─────────────────────────────────────────────────────────────┘
```

## 8 regras binárias (sim/não) — ancoradas na golden

| # | Regra (pergunta sim/não) | Evidência na golden (`CcSessions/Index.tsx`) |
|---|---|---|
| **R1** | **O feed é `.map` de itens-card, NÃO `<table>`/DataTable?** container do feed é `<div>` com `data-testid` de feed. | `:253` `data-testid="cc-feed"` · `:254` `sData.map` · `:262` `data-testid="cc-feed-item"` |
| **R2** | **Cada item tem carimbo de tempo RELATIVO** (`fmtRelative`/`timeAgo`), não só data crua? | `:282` `fmtRelative(s.started_at)` (com `title` = ISO absoluto no hover) |
| **R3** | **Status é dot/badge SEM bg-fill** (Stripe-style — cor no ponto/texto, fundo transparente)? | `:267` `<span className={meta.dot}>` · `:293` dot inline · nunca `bg-red-500` no card |
| **R4** | **Números/métricas do item usam `tabular-nums`?** msgs/tokens/custo/duração alinhados. | `:287` `... text-[10px] ... tabular-nums` · `:251` contagem `tabular-nums` |
| **R5** | **Header é `<PageHeader>` shared** (ícone+título+descrição), com filtro/contexto no slot de ação? | `:159-163` `<PageHeader icon title description>` |
| **R6** | **3 estados de vazio cobertos**: skeleton (carregando) · primeiro-acesso (CTA) · busca-sem-resultado? | `:226` skeleton · `:231` `<EmptyState>` primeiro-acesso c/ CTA · `:246` `<EmptyState variant="search">` |
| **R7** | **Abrir item = drawer/deep-link** (NÃO modal-sobre-modal nem navegação que perde o scroll)? | `:263` `onClick={openSession}` → `:320` `<SessionDrawer>` |
| **R8** | **Cabe em 1280px, sem scroll horizontal; item trunca (`line-clamp`/`truncate`) em vez de estourar?** | `:276` `truncate` · `:284` `line-clamp-2` · container `overflow-hidden` `:253` |

**Placar:** 8/8 = canon. 6-7 = 1 round de ajuste. <6 = não é PT-07 (rever arquétipo).

## §Nunca

- ❌ Renderizar a cronologia como `<table>` só pra "ganhar colunas" — feed é card, não grade. Se o dado tem colunas fixas comparáveis, é **PT-01**, não PT-07.
- ❌ **CSS de página paralelo** (`fx-timeline`, `.xxx-cockpit.css` escopado) pra estilizar o feed — é o débito do Fiscal/Eventos (ver §Drift). Card/dot/tempo vêm dos shared + tokens.
- ❌ Status com **bg-fill** colorido (`bg-red-500` no card inteiro) — dot + texto, fundo transparente ([PT-01 regra de ouro](PT-01-Lista.md#-sempre)).
- ❌ Data **crua sem relativo** (`2026-07-11 14:32:01`) como carimbo primário — use `fmtRelative`/`timeAgo` com o ISO no `title`.
- ❌ Item sem **truncamento** — resumo longo estoura o layout; `line-clamp`/`truncate` sempre.
- ❌ Abrir detalhe em **modal full-screen** — drawer/Sheet lateral ou deep-link preservando scroll.
- ❌ Feed sem **estado de vazio** — os 3 estados (loading/primeiro-acesso/busca-vazia) são obrigatórios.
- ❌ Emoji em UI produtiva · cor crua (`#hex`/`bg-blue-500`) · label não-PT-BR.

## Estados obrigatórios

1. **Cheio** — N itens cronológicos, paginação/carregar-mais ativo
2. **Loading skeleton** — placeholders enquanto `Inertia::defer`/reload resolve (golden `:226`)
3. **Primeiro acesso** — `<EmptyState>` com CTA/explicação (golden `:231`)
4. **Filtro/busca sem resultado** — `<EmptyState variant="search">` contextual (golden `:246`)
5. **Item selecionado/hover** — focus visível (`bg-primary/10`/`hover:bg-muted/50`, golden `:265`)
6. **Erro de fetch** — toast/alert + retry

## Drift conhecido (corrija ao copiar — não herde)

- ⚠️ **`Fiscal/Eventos` usa `fiscal-cockpit.css` próprio** (`fx-timeline`/`fx-chip`/`fx-tl-item`) em vez dos shared — ilha CSS. Ao portar pra PT-07 canon, troque por card + `KpiGrid` + dot de status + `EmptyState` shared (padrão da golden). O arquétipo está certo; o visual é o débito.
- ⚠️ **`ProjectMgmt/Inbox/Index` hand-rola o EmptyState** (`:292`, `<div>` em vez de `<EmptyState>`). Troca pelo shared ao evoluir.
- ⚠️ **Paginação por `dangerouslySetInnerHTML`** na golden (`:312`, label do link do paginator Laravel) — herança do backend; ao replicar, prefira componente `<Pagination>` shared quando existir.

## Aplicado em (estado real)

| Página | R1 card/.map | R2 tempo-rel | R3 dot | R5 Header shared | R6 3-vazios | R7 drawer | Nota |
|---|---|---|---|---|---|---|---|
| `team-mcp/CcSessions/Index.tsx` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | **golden** |
| `ProjectMgmt/Inbox/Index.tsx` | ✓ | ✓ | ✓ (badge) | ✓ | parcial (hand-roll) | deep-link | bom aluno |
| `Fiscal/Eventos.tsx` | ✓ | ✓ (`when`) | ✓ (`fx-tl-badge`) | ✗ (`FxShell`) | parcial | deep-link | ilha CSS |

**Métrica adoção PT-07 (2026-07-11):** 1/3 telas atinge o canon estrutural completo. Próximo passo: desbundlar `Fiscal/Eventos` (fx-* → shared) e trocar o EmptyState hand-rolled do Inbox — quando as 3 convergirem, bump v1.0 (→ live).

## Arquétipo vizinho NÃO formalizado — PT-06 Ferramenta/Calculadora

`ComunicacaoVisual/Index.tsx` (calculadora de m²) é um **4º atípico** da wave 1, mas é **ferramenta interativa de cálculo** (estado local + `useMemo`, sem lista/feed/form-de-cadastro), não feed. Há **só 1 tela** desse arquétipo no repo → **não** vira PT (regra: ≥2 pra formalizar). Fica **bespoke construída do DS** (charter declara isso, sem token PT). **Reabrir PT-06** quando surgir uma 2ª ferramenta (ex.: simulador de frete, calculadora fiscal).

## Referências

- **ADR-mãe**: [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Rede de verificação**: [`scripts/governance/pt-conformance.mjs`](../../../../scripts/governance/pt-conformance.mjs) (sinal `feed` + `REQUIRED['PT-07']`)
- **Âncora por charter**: [`prototipo-ui/ancora.mjs`](../../../../prototipo-ui/ancora.mjs) (`related_prototype`)
- **Tipografia KPI/tempo canon**: [ADR 0110 Cockpit Pattern V2](../../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- **Lista irmã**: [PT-01 Lista](PT-01-Lista.md) · **Dashboard irmão**: [PT-04 Dashboard](PT-04-Dashboard.md)
- **Índice de design**: [INDEX-DESIGN-MEMORIAS.md](../INDEX-DESIGN-MEMORIAS.md)

## Versão

**v0.1** · 2026-07-11 · primeira formalização (draft). Arquétipo eleito por 3 telas reais barradas pela rede `pt-conformance` (wave 1). Golden = `team-mcp/CcSessions/Index`.
**Bump v1.0 (→ live)** quando as 3 telas convergirem no golden (Fiscal desbundlado + Inbox com EmptyState shared) + Wagner aprovar screenshot.
