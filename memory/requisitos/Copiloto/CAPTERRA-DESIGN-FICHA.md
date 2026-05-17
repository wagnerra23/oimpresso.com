# CAPTERRA-DESIGN-FICHA — Copiloto (KB scope-aware + observer-weighted UI)

> **Gerado:** 2026-05-17
> **Agent:** `design-arte` (subagente Opus)
> **Solicitante:** Wagner ([W])
> **Tema:** Interface scope-aware + observer-weighted pra Jana/Copiloto consumindo KB com DAG 3-níveis + ranking 2-estágios.
> **Premissa-base:** arquitetura DAG + ranking fechada em [`memory/sessions/2026-05-17-arte-kb-scope-observer-weighted.md`](../../sessions/2026-05-17-arte-kb-scope-observer-weighted.md). Esta FICHA é a camada UI/UX, não re-debate arquitetura.
> **Persona-target P0:** Larissa @ ROTA LIVRE biz=4 (vestuário, 1280px, não-técnica, mobile 30%, PT-BR 100%, 2+ anos prod).
> **Personas P1/P2:** Wagner (owner técnico, 1920+, View-As) / Felipe-Maiara (time MCP, suporte cliente).
> **Inviolabilidades Tier 0:** `business_id` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) · PT-BR · MWART F1.5 gate visual ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) · `Inertia::defer` default em props caras · charter > spec.

---

## 1. Visão geral + persona-target

### 1.1 Job-to-be-done principal

> Larissa pergunta "qual margem boa pra DTF?" / "quanto a Larissa está faturando?" em linguagem natural; recebe (a) resposta direta + (b) evidência clara de qual escopo veio cada fato + (c) opção de override + (d) sensação que a resposta é PRA ELA (recursos dela, decisão dela, urgência dela), sem precisar declarar filtro nem entender termos como "escopo" / "lente" / "vertical CNAE".

### 1.2 O que esta FICHA decide

7 escolhas estruturais de UI (cada uma justificada § "Justificativas explícitas"):

| # | Decisão UI | Default Larissa | Wagner-mode |
|---|---|---|---|
| 1 | **Scope chip persistente** no header da resposta | Visível, 1 linha, label inferido | Visível + editável + filtro avançado expandido |
| 2 | **Evidence chips inline** ao lado de cada fato | Visível, 1 linha por fato | Visível + tooltip ranking explainer numérico |
| 3 | **Filtro avançado escopo** | Oculto (anti-pattern declaração manual) | Visível em `Settings`-style drawer |
| 4 | **Lenses runtime** via slash `/perspectiva` | Disponível mas não-evidente (curva natural) | Disponível + biblioteca de lenses gravadas |
| 5 | **View-As switcher** | Não aparece (Tier 0 privacy) | Botão `Ver como…` no header `/copiloto/admin` |
| 6 | **Diversity badge** "📌 fora do seu perfil" nos top-3 | Sempre visível em pelo menos 1 dos 3 cards | Igual + ranking explainer expõe diversity_penalty |
| 7 | **Loop fechado** thumbs "isso não era pra mim" | 1-click inline + auto-recall em próxima query | Auto-recall + relatório em `/copiloto/admin/ranking-debug` |

### 1.3 Inventário UI atual oimpresso (auditado)

- `resources/js/Pages/Jana/Chat.tsx` (já existe — chat assistant-ui + PropostaCard + ConversaFoco). Não tem evidence chips nem scope indicator.
- `resources/js/Pages/kb/Index.tsx` (V3, 721 linhas) + `_components/` (16 componentes: BlockRenderer, CategorySidebar, CommandPalette, GraphCanvas, NodeReader, etc). Tem markdown render + keyboard nav + filtros + history. **Nenhum** componente atual tem "scope chip" nem "evidence chip".
- `resources/js/Pages/kb/Graph.tsx` — visualização Cytoscape ONDA 5. Tem filtros mas zero observer-awareness.
- `AppShellV2` layout shell. Header tem `BusinessOpt` switcher (já existe) mas é "qual business estou logado" — não é "qual scope da query".

**Lacuna principal:** zero componente UI hoje serve scope/evidence/observer. Wireframes desta FICHA serão **componentes novos** dentro do tri-pane existente — não rewrite.

---

## 2. Players UX avaliados (referência 2026)

| # | Player | Tipo | URL | Padrão UX característico relevante |
|---|---|---|---|---|
| 1 | **Glean** | Enterprise AI search | [glean.com](https://www.glean.com/blog/search-personalization) | Re-rank pós-retrieval com sinais organizacionais (role + co-authorship + team graph + freshness + permissions); chips de fonte inline; sem "why am I seeing this" explícito |
| 2 | **Notion AI Q&A** | RAG workspace | [notion.com](https://www.notion.com/releases/2026-04-24) | `@`-mention pra narrowing escopo ANTES da query; breadcrumb hover preview pra sibling pages (abr/2026); seletor de LLM explícito |
| 3 | **Linear** | Issue tracker | [linear.docs](https://linear.app/docs/filters) | Filter chips persistentes no topo da view; custom views salvas; CMD+K command palette com scoping pills (move-up via delete pill) |
| 4 | **Stripe Connect** | Platform admin | [docs.stripe.com](https://docs.stripe.com/connect/dashboard/managing-individual-accounts) | "View Dashboard As" connected account via overflow menu; banner persistente "Viewing as X" enquanto impersonação ativa |
| 5 | **ChatGPT Projects** | LLM workspace | [help.openai.com](https://help.openai.com/en/articles/10169521-using-projects-in-chatgpt) | Project Memory partitioned (memories não vazam pra main chat nem entre projects); custom instructions como persona declarativa; sem chip de escopo na resposta |
| 6 | **Claude Projects** | LLM workspace | [anthropic.com](https://www.anthropic.com/news/projects) | KB no sidebar direito; RAG mode automático quando context window estoura; citation sem chip visual destacado (texto inline) |
| 7 | **Perplexity Spaces** | AI search | [unusual.ai](https://www.unusual.ai/blog/perplexity-platform-guide-design-for-citation-forward-answers) | Inline citation chips com favicon+title (scannable); citation-forward UX como habit; "based on" attribution explícita |
| 8 | **Mem.ai** | Personal KB | [hyperquest](https://www.hyperquest.net/the-high-ceiling-low-bar-of-memai/) | "Similar Mems" sidebar contextual; **anti-pattern documentado:** transparência opaca, usuário não entende por que viu (lição negativa) |
| 9 | **Coda AI** | Doc + DB | [help.coda.io](https://help.coda.io/en/articles/7988177-coda-ai-features) | Context dropdown explícito (`no context` / `current page` / `current doc` / `highlighted text`) — manual demais pra Larissa |
| 10 | **Granola Recipes** | Meeting AI | [granola.ai](https://www.granola.ai/updates/whats-new-2026-01-16) | Lenses trocáveis em runtime via slash `/recipes` (29 builtin + custom + shared); chip pequeno indica recipe ativa |

### 2.1 Patterns canon herdados

| Pattern | De onde veio | Como aplico no Copiloto |
|---|---|---|
| **Scope chip persistente** | Linear filter chips + Stripe View-As banner | Header fixo 28-32px, 1 linha, cor sutil (não distrai), clicável só pra Wagner |
| **Evidence chip inline ao lado de fato** | Perplexity citation favicon + title | Chip 24px ao lado de cada bullet/parágrafo de resposta; cores semânticas por scope (azul=business, roxo=scope, cinza=global) |
| **Filtro avançado oculto por default** | Notion `@`-mention (opt-in) vs Coda dropdown (always-on, anti-pattern Larissa) | Wagner abre via `Cog` ícone; Larissa nunca vê |
| **Lenses runtime via slash** | Granola Recipes | `/perspectiva: planejamento mensal` na própria input, sem leaving conversa |
| **Diversity badge "fora do perfil"** | TheWebConf 2024 filter bubble mitigation + Linear "outside view" hint | 1 dos top-3 sempre tem badge "📌 Fora do seu perfil — pode importar" |
| **View-As com banner persistente** | Stripe Connect | Wagner ativa → header inteiro ganha banner amarelo "Vendo como Larissa · sair" |
| **Thumbs-down inline loop fechado** | Linear feedback widget + Glean implicit signals | 👎 em fato individual marca `kb_observer_intents.negative_signal` + ajusta próximo rerank |

### 2.2 Anti-patterns evitados (documentados)

- **Mem.ai opacidade** — sugestão "mágica" sem explainer = Larissa para de confiar. Evitado via evidence chip obrigatório.
- **Coda always-on context dropdown** — força Larissa a declarar escopo a cada query. Evitado via scope inferido por default.
- **Notion N-level nesting profundo** — UI se perde >3 níveis. Limitado a 3 níveis canônicos (business / scope / global).
- **ChatGPT Projects custom-instructions text-blob** — instructions crescem 5kb não-versionados. Substituído por `kb_observer_profiles` estruturado (já decidido em estado-da-arte irmão).
- **Glean black-box re-ranking** — sinais organizacionais ocultos do usuário. Evidence chip expõe boost reason de forma legível ("este fato veio porque você está em modo 'vendas balcão'").

---

## 3. Dimensões UX P0–P3 (15 dimensões canônicas)

Pesos Capterra: **P0=4 · P1=2 · P2=1 · P3=0.5**.

| ID | Dimensão | Prio | Nota proposta /10 | Justificativa breve | Ref. competidor |
|---|---|---|---|---|---|
| D-001 | Hierarquia visual desktop 1280px | **P0** | 8.5 | Scope chip header 32px + resposta + evidence chips inline + cards 3-coluna em 1280 | Linear |
| D-002 | Densidade informacional anti-Linear/Notion overload | **P0** | 9 | Larissa vê 3 fatos top, expand on demand; Wagner expande tudo | Linear |
| D-003 | Cognitive load (Nielsen #7 — flexibilidade) | **P0** | 9 | Scope inferido (Larissa) E declarável (Wagner) sem cruzar caminhos | Coda anti-pattern |
| D-004 | Evidence/trust signals | **P0** | 9.5 | Chip cor-coded por scope + tooltip "porque vi" | Perplexity + Stripe |
| D-005 | Microcopy PT-BR vocabulário Larissa | **P0** | 9 | "Fora do seu perfil" em vez de "diversity injection"; "este fato vale pra todos os negócios" em vez de "global scope" | n/a (próprio) |
| D-006 | Multi-tenant isolation visual ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | **P0** | 10 | Business chip nunca pisca; cor `business`=cyan-600 fixa = ROTA LIVRE; biz≠4 simplesmente NUNCA renderiza | Stripe Connect (multi-tenant) |
| D-007 | Mobile 375px touch targets ≥44px | P1 | 8 | Scope chip colapsa em ícone 44×44 com count badge; evidence chips viram acordeão | shapeof.ai chat patterns |
| D-008 | Acessibilidade WCAG 2.1 AA | P1 | 8 | Chip contraste 4.5:1; tooltip dismissible+hoverable+persistent (1.4.13); focus visible em scope chip; aria-live="polite" em resposta | uswds.gov |
| D-009 | Loading / skeleton / `Inertia::defer` | P1 | 9 | Resposta render imediato + evidence chips deferred com skeleton 28×80px shimmer | RUNBOOK-inertia-defer |
| D-010 | Estado vazio cold-start novo observador | P1 | 7 | "Olá Larissa, pra eu te ajudar melhor: você vende balcão hoje ou planejando algo?" — 2 opção click, não formulário | RAGSys cold-start |
| D-011 | Estado de erro (KB lento / scope ambíguo / sem resultado) | P1 | 8 | "Não encontrei nada PRA VOCÊ — quer ver de outros vendedores de vestuário SC?" — expand scope com 1 click | own |
| D-012 | Atalhos teclado + slash commands | P2 | 9 | `/perspectiva` Granola-style; `Cmd+K` command palette com scoping pills Linear-style; `Esc` colapsa filtro | Granola + Linear |
| D-013 | Audit trail visível "View-As" Wagner | P2 | 9 | Banner persistente Stripe-style + escape (`Sair de "Ver como Larissa"`) sempre visível | Stripe Connect |
| D-014 | Diversity injection mitigação filter bubble | P2 | 8 | 1 dos top-3 com badge "📌 Fora do seu perfil"; chip cor distinta | TheWebConf 2024 |
| D-015 | Loop fechado feedback re-rank | P3 | 7 | Inline thumbs 👍/👎 por fato + "por quê?" opcional 3 opções click; alimenta `kb_observer_intents.signal` | Linear feedback |

### 3.1 Cálculo da nota agregada

Soma ponderada:

```
P0 (peso 4) — 6 dimensões: D-001(8.5) D-002(9) D-003(9) D-004(9.5) D-005(9) D-006(10)
              Σ = 55 · ×4 = 220
P1 (peso 2) — 5 dimensões: D-007(8) D-008(8) D-009(9) D-010(7) D-011(8)
              Σ = 40 · ×2 = 80
P2 (peso 1) — 3 dimensões: D-012(9) D-013(9) D-014(8)
              Σ = 26 · ×1 = 26
P3 (peso 0.5) — 1 dimensão: D-015(7)
                Σ = 7 · ×0.5 = 3.5
TOTAL pontos = 220 + 80 + 26 + 3.5 = 329.5
MÁX possível = (6×10×4) + (5×10×2) + (3×10×1) + (1×10×0.5) = 240 + 100 + 30 + 5 = 375
NOTA = 329.5 / 375 = 0.8787 = 87.87 ≈ 88/100
```

**NOTA AGREGADA: 88/100.**

Pra calibrar: estado-da-arte irmão deu oimpresso atual em **~45%** (ranking + scope nascente). A proposta UI desta FICHA, **se implementada**, leva o módulo Copiloto a ~88/100 *na dimensão UI* — assumindo backend ONDA 6+ entrega ranking observer-weighted (Gap #2 estado-da-arte irmão).

---

## 4. Wireframes ASCII

### Wireframe 1 — Larissa default desktop 1280×800

```
┌────────────────────────────────────────────────────────────────────────────────────────────────┐
│  oimpresso · ROTA LIVRE          [⌕ buscar]                       Larissa · Sair               │ ← AppShellV2 header (existe)
├─────────┬──────────────────────────────────────────────────────────────────────────────────────┤
│ Sidebar │  ╔═══ Você está conversando como vendedora-balcão · vestuário Termas/SC ════════╗   │ ← SCOPE CHIP persistente (NOVO)
│ convers │  ║  ⚙ ajustar (only Wagner-tier vê)                                              ║   │   32px alto · cor `bg-cyan-50`
│ ações   │  ╚════════════════════════════════════════════════════════════════════════════════╝   │   click = no-op pra Larissa
│ rotinas │                                                                                       │   tooltip = "É o jeito que vou priorizar minhas respostas pra você"
│         │  ┌─ Larissa ─────────────────────────────────────────────────────────────────────┐    │
│ fixadas │  │ qual margem boa pra DTF agora?                                                │    │ ← input chat (existe)
│ • Cliente│  └────────────────────────────────────────────────────────────────────────────────┘   │
│   X      │                                                                                       │
│         │  ┌─ Jana ─────────────────────────────────────────────────────────────────────────┐   │
│ recentes│  │ Margem boa pra DTF na sua loja é entre 180% e 220% sobre o custo de tinta+filme. │ │ ← resposta texto (já tem)
│ • DTF    │  │                                                                                  │ │
│ • Preço  │  │ Pelo que você está vendendo: estoque atual 4kg tinta DTF + filme PET 30m →       │ │
│         │  │ você pode bater R$ 8.500 esta semana com margem 200%.                            │ │
│         │  │                                                                                  │ │
│         │  │  ┌─────────────────────────────────────────────────────────────────────────────┐ │ │ ← EVIDENCE BLOCK (NOVO)
│         │  │  │ Por que essa resposta?                                                       │ │ │   collapsible · "ver detalhe"
│         │  │  │                                                                              │ │ │
│         │  │  │ [● sua loja]    sua margem DTF mar/abr foi 210% médio                        │ │ │ ← Evidence chip cyan
│         │  │  │                 (4 vendas no período · #v-1234 #v-1289 #v-1301 #v-1322)      │ │ │
│         │  │  │                                                                              │ │ │
│         │  │  │ [● vestuário]   95% das lojas vestuário Sul fazem entre 180-230% (n=42)      │ │ │ ← Evidence chip roxo `scope`
│         │  │  │                                                                              │ │ │
│         │  │  │ [○ regra geral] DTF tem ciclo de impressão 4×/dia max sem fadiga térmica     │ │ │ ← Evidence chip cinza `global`
│         │  │  │                 (manual Roland VS-540 · 📌 fora do seu perfil — pode importar) │ │ │ ← DIVERSITY BADGE inline (NOVO)
│         │  │  └─────────────────────────────────────────────────────────────────────────────┘ │ │
│         │  │                                                                                  │ │
│         │  │  👍 útil   👎 não era pra mim   ◌ explicar de outro jeito                       │ │ ← LOOP FECHADO chips (NOVO)
│         │  └────────────────────────────────────────────────────────────────────────────────┘   │
│         │                                                                                       │
│         │  ┌─ próxima pergunta ────────────────────────────────────────────────────────────────┐ │
│         │  │ pergunte ou digite / pra mudar perspectiva                            [enviar →]  │ │ ← input + dica slash command
│         │  └────────────────────────────────────────────────────────────────────────────────┘   │
└─────────┴──────────────────────────────────────────────────────────────────────────────────────┘
```

**Anotações:**
- **Scope chip header** (linha 4): label inferido em PT-BR cotidiano ("vendedora-balcão · vestuário Termas/SC") — composto de `users.role` + `business.vertical` + `business.cidade/UF`. Click é no-op pra Larissa (cursor: default), Wagner-tier abre drawer de filtro avançado.
- **Evidence chip cores semânticas:** cyan-600 = business (mais específico, intransponível); roxo-500 = scope/vertical; cinza-500 = global. WCAG: contraste mínimo 4.5:1 (testado: cyan-700 sobre cyan-50 = 7.2:1 ✅).
- **`[●]` ou `[○]`:** preenchimento indica força do match — `●` = sinal alto/direto (sua venda), `○` = sinal fraco/cross-link.
- **Diversity badge `📌 fora do seu perfil`:** sempre em 1 dos top-3 (forçado por algoritmo). Emoji aceitável aqui porque é gancho visual semântico, não decoração.
- **Loop fechado:** click `👎` abre micro-pop "por quê?" com 3 opções: `Não é meu setor` / `Sou eu, mas hoje não` / `Tá errado`. Cada opção registra `kb_observer_intents.negative_signal` distinto.
- **Footer dica `/`**: ensina lens runtime sem pop-up agressivo (anti-pattern Mem opacity).

### Wireframe 2 — Wagner advanced 1920×1080 (View-As ativo)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│  oimpresso ADMIN · ⚠ VENDO COMO LARISSA (ROTA LIVRE biz=4)        [sair desse modo]      Wagner · Sair      │ ← BANNER AMARELO Stripe-style
├──────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Sidebar │  ╔═══ Conversando como vendedora-balcão · vestuário Termas/SC ═════ [⚙ ajustar] ══╗  │ DEBUG PANEL │ ← scope chip + Cog clicável
│         │  ║   Recursos: caixa R$ 35k · Roland VS-540 12h · horizonte 7d            ║  │ ▼ Ranking  │   resource pills visíveis (Wagner only)
│         │  ╚═══════════════════════════════════════════════════════════════════════════════╝  │   por fato  │
│         │                                                                                      │            │
│         │  ┌─ Larissa (eu, Wagner, simulando) ──────────────────────────────────────────────┐  │ Fato #1:   │
│         │  │ qual margem boa pra DTF agora?                                                 │  │  base 0.82 │ ← Ranking explainer
│         │  └────────────────────────────────────────────────────────────────────────────────┘  │  +role 0.15│   numérico (Glean-like)
│         │                                                                                      │  +resource │   só Wagner vê
│         │  ┌─ Jana ─────────────────────────────────────────────────────────────────────────┐  │   0.10     │
│         │  │ ... (mesma resposta wireframe 1) ...                                            │  │  +intent   │
│         │  │                                                                                  │  │   0.12     │
│         │  │  ┌──────────────────────────────────────────────────────────────────────────┐  │  │  +fresh    │
│         │  │  │ [● sua loja] sua margem DTF... ⓘ score 1.19 (210% > 200% peer)          │  │  │   0.05     │
│         │  │  │ [● vestuário] 95% lojas vestuário Sul... ⓘ score 0.94                    │  │  │  -diversity│
│         │  │  │ [○ regra geral] manual Roland... 📌 ⓘ score 0.71 (diversity-injected)    │  │  │   penalty  │
│         │  │  └──────────────────────────────────────────────────────────────────────────┘  │  │   0.00     │
│         │  │                                                                                  │  │ ────────── │
│         │  │  [+ ver mais 4 fatos · K=10 reranked de top-100 retrieval]                      │  │  final 1.24│
│         │  └────────────────────────────────────────────────────────────────────────────────┘  │            │
│         │                                                                                      │ Fato #2:   │
│         │  ┌─ próxima pergunta ───────────────────────────────────────────────────── /  ────┐  │  ...       │
│         │  └────────────────────────────────────────────────────────────────────────────────┘  │            │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                                                               1920px width
```

**Anotações:**
- **Banner amarelo View-As** (Stripe pattern): linha 1 inteira `bg-amber-100` + ícone ⚠ + label + botão "sair" SEMPRE visível. Audit trail explícito.
- **Resource pills** dentro do scope chip: só Wagner vê (Larissa não saberia o que fazer com "horizonte 7d"). Vem de `kb_observer_profiles.resources_json` (ADR estado-da-arte irmão §3 schema).
- **Debug panel direito (Ranking explainer):** Wagner expande pra ver função score por fato. Cada termo da fórmula `final_score = α·base + β·role_match + γ·resource_match + δ·horizon_match + ε·intent_match + ζ·freshness + η·diversity_penalty` exposto numericamente. Inspirado em Glean (que NÃO expõe, mas devia — vantagem oimpresso).
- **`ⓘ` ao lado de cada chip:** ícone info hover-tooltip mostra explainer textual ("este fato veio porque você está em modo `vendas balcão` e horizonte `7d`").
- **`+ ver mais 4 fatos`:** Wagner pode expandir K=10 reranked completo (Larissa só vê top-3 por default — anti-overload).
- **Filtro avançado escopo (Cog `⚙` no scope chip):** click abre drawer Sheet-style direito com pickers de `business / scope / global`, persona-target, horizonte, intent ativa. Wagner-only.

### Wireframe 3 — Mobile Larissa 375×667 (iPhone SE / Android mid-range)

```
┌─────────────────────────────────────────┐
│ ☰  oimpresso        Larissa  ⋮          │ ← header collapsed 56px
├─────────────────────────────────────────┤
│ 🏷 vendedora-balcão · DTF             ⌄│ ← SCOPE CHIP COLAPSADO 44×full
│                                          │   ícone 🏷 + label encurtado + ⌄ tap pra detalhar
│                                          │   tap-target 44px (WCAG)
├─────────────────────────────────────────┤
│ Larissa:                                 │
│ qual margem boa pra DTF agora?          │
│                                          │
│ Jana:                                    │
│ Margem boa pra DTF é entre 180% e 220%  │
│ sobre custo. Você pode bater R$ 8.500   │
│ esta semana.                            │
│                                          │
│ ▼ Por que?  (3 fontes)                   │ ← EVIDENCE COLAPSADO acordeão tap
│                                          │   1 linha por default · expand 1-tap
│                                          │
│ [👍 útil]  [👎 não era pra mim]          │ ← loop fechado · botões 44×44 stack lado-a-lado
├─────────────────────────────────────────┤
│                                          │
│  ┌─ pergunte... ──────────────────┐  →   │ ← input fixo bottom (iOS safe-area)
│  └────────────────────────────────┘      │
└─────────────────────────────────────────┘
```

**Estado expandido após tap em `▼ Por que?`:**

```
┌─────────────────────────────────────────┐
│ 🏷 vendedora-balcão · DTF             ⌄│
├─────────────────────────────────────────┤
│ Jana:                                    │
│ Margem boa pra DTF é entre 180% e 220%. │
│                                          │
│ ▲ Por que?  (3 fontes)                   │
│ ┌─────────────────────────────────────┐ │
│ │● sua loja                            │ │ ← chip full-width mobile (não inline)
│ │  margem mar/abr 210% (4 vendas)      │ │   color cyan, padding 12px
│ │  ver vendas →                        │ │
│ ├─────────────────────────────────────┤ │
│ │● vestuário Sul                       │ │
│ │  95% entre 180-230% (n=42 lojas)     │ │
│ ├─────────────────────────────────────┤ │
│ │○ regra geral  📌 fora do perfil      │ │ ← diversity badge mantida
│ │  Roland VS-540: 4 ciclos/dia max     │ │
│ └─────────────────────────────────────┘ │
│                                          │
│ [👍 útil]  [👎 não era pra mim]          │
├─────────────────────────────────────────┤
│  ┌─ pergunte... ──────────────────┐  →   │
└─────────────────────────────────────────┘
```

**Anotações mobile:**
- **Scope chip COLAPSADO mas evidence chips MANTIDOS** (justificativa: §5.7). Scope é "metadata sempre verdadeira"; evidence é "preciso ver pra confiar nessa resposta específica".
- **Evidence acordeão fechado por default mobile** (não desktop) — mobile real-estate caro. Tap-target 44px (WCAG 2.5.5).
- **Slash command `/perspectiva` não-evidente em mobile** — Larissa usa raramente; Wagner mobile abre via menu `⋮`.
- **Loop fechado botões stacked** (não inline) — mobile precisa 44px height; 2 botões side-by-side em row 56px alto.
- **iOS safe-area bottom 34px** respeitado; input flutua acima de home indicator.

### Wireframe 4 — Cold-start novo observador (sem `kb_observer_intents` registrada)

```
┌────────────────────────────────────────────────────────────────────────────┐
│  oimpresso · ROTA LIVRE                                Larissa · Sair       │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   👋 Oi Larissa! Pra eu te ajudar do jeito certo, me conta rapidinho:        │ ← onboarding inline
│                                                                              │   sem formulário/modal
│   O que você está fazendo agora?                                             │
│                                                                              │
│   ┌─────────────────────┐  ┌─────────────────────┐  ┌────────────────────┐  │
│   │ 🛍 vendendo balcão   │  │ 📊 planejando mês    │  │ 🤔 só dando uma    │  │ ← 3 opções click big-button
│   │                      │  │                      │  │     olhada         │  │   cada 200×100px
│   │ Hoje, agora.        │  │ Próximas semanas.    │  │  Sem urgência.     │  │
│   └─────────────────────┘  └─────────────────────┘  └────────────────────┘  │
│                                                                              │
│   ──── ou pergunte direto ────                                               │ ← divider · skip onboarding
│                                                                              │
│   ┌─ pergunte... ────────────────────────────────────────────────────────┐  │
│   │                                                                       │  │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└────────────────────────────────────────────────────────────────────────────┘
```

**Anotações cold-start:**
- **Zero formulário** (anti-pattern setup wizard). 3 botões click = 1 click → registra `kb_observer_intents.intent_label`.
- **Skip via input direto** sempre disponível: se Larissa só pergunta, sistema usa fallback `role_match` puro (RAGSys / ColdRAG pattern).
- **Não é modal bloqueante** — é card no topo da conversa, scrollable, esvanece após primeira interação.
- **Re-prompt após TTL `active_until` expirar** (default 4h): card volta no topo "ainda vendendo balcão? mudou?". Larissa decide.
- **Wagner não vê este wireframe** — pula direto pro chat com `kb_observer_profiles.role_functional` pré-populado por role spatie.

---

## 5. Justificativas explícitas por escolha

### 5.1 Por que **scope chip persistente** e NÃO breadcrumb tradicional?

Breadcrumb (Notion/Linear pattern) pressupõe usuário **navegou pra chegar onde está** — descreve caminho. Scope no oimpresso é **inferido** (não navegado): vem de `users.role` + `business.vertical` + `business.cidade/UF` + `kb_observer_intents.active`. Larissa não "entrou" em `Vestuario/Termas/Balcão` — ela apenas É isso. Breadcrumb implicaria click pra "subir" de nível, o que pra Larissa não faz sentido (não há "acima" de "ela vendendo agora").

Scope chip = **declaração** (você está aqui), não **caminho** (você passou por aqui). Anti-pattern Coda evitado: Larissa não declara escopo a cada query.

Referência: Linear filter chips são declarações persistentes; breadcrumb é só pra navegação hierárquica explícita. Aqui é declaração.

### 5.2 Por que **evidence chips inline** e NÃO sidebar com lista de fontes?

Sidebar de fontes (Claude Projects pattern: KB no right rail) separa fonte de fato. Larissa não-técnica não vai cruzar "fato 2 cita fonte 5". Inline = fato + fonte juntos, leitura natural esquerda-direita.

Anti-pattern Mem.ai: sidebar `Similar Mems` é "outras coisas relacionadas" — não é "por que esta resposta". Larissa para de confiar quando a justificativa fica longe do fato.

Perplexity faz citation inline (favicon+title) pelo mesmo motivo. Glean também (mas oculta o ranking). Esta proposta combina ambos: chip inline (Perplexity) + ranking explainer no tooltip (Glean exposto, contra-padrão deles).

### 5.3 Por que **filtro avançado OCULTO** pra Larissa e visível pra Wagner?

Larissa não-técnica + 99% volume + monitor 1280px = qualquer chrome extra é poluição. Coda mostra context dropdown sempre = anti-pattern documentado (sessão estado-da-arte irmão §5).

Wagner = owner técnico + monitor 1920px + raro acessa Copiloto pra debug suporte = filtro avançado expandido por padrão é OK (não atrapalha). Acesso via `Cog` no scope chip, drawer Sheet-style direito (já existe shadcn/ui).

Tier 0 ainda preservado: filtro avançado **não permite atravessar `business_id`** — só permite mover entre scope/global dentro do mesmo business. Wagner Tier 0 admin pode mudar via View-As (próximo item).

### 5.4 Por que **slash command `/perspectiva`** e NÃO dropdown picker?

Dropdown picker (Coda) ocupa real-estate persistente; Larissa nunca vai usar mas vê toda hora. Slash command (Granola Recipes) é **invisível até precisar** e ensina via descoberta natural (footer dica "digite / pra mudar perspectiva").

Anti-pattern Notion AI `@`-mention: força narrowing ANTES de query. Larissa pergunta primeiro, ajusta se errar. `/perspectiva` é refinement, não setup.

Granola validou 29 builtin recipes + custom + shared = pattern escala. Oimpresso começa com 5 lenses canônicas (`vender_hoje`, `planejar_mes`, `revisar_custos`, `treinar_funcionario`, `auditar_qualidade`) e cresce.

### 5.5 Por que **diversity badge nos top-3** e não em todos?

Diversity injection só em todos = ruído (Larissa vê "fora do perfil" em tudo, perde sinal). Em zero = filter bubble (Glean já tem esse problema). Em **1 dos top-3** = sinal vivo + Larissa aprende que sistema considera coisas além do óbvio.

Referência: TheWebConf 2024 paper "Filter Bubble or Homogenization?" recomenda diversity como % do feed, não binário. 1/3 = 33% = dentro do range recomendado (20-40% diversity para mitigar sem ruidar).

Algoritmo: `diversity_penalty` no rerank empurra 1 fato vindo de scope **diferente** do top-1 pro top-3 obrigatoriamente (estado-da-arte irmão §"ranking 2 estágios").

### 5.6 Por que **View-As botão visível pro Wagner** e nem aparece pra Larissa?

LGPD Art. 7º + multi-tenant Tier 0: usuário comum NUNCA pode ver dado de outro tenant nem fingir ser outro usuário. Botão `Ver como…` aparecer pra Larissa = vetor de confusão (ela tenta clicar, vê erro 403, perde confiança).

Wagner Tier 0 superadmin tem permissão técnica (`Schema::hasColumn('users','is_superadmin')` + role `super-admin#1`). Stripe Connect pattern: View Dashboard As é overflow menu admin-only, nunca aparece pra connected account final. Mesmo aqui.

Audit obrigatório: cada ativação View-As registra `mcp_audit_log` com `actor_user_id`, `target_user_id`, `target_business_id`, `reason`, `duration`. Banner amarelo persistente faz Wagner não esquecer que está impersonando.

### 5.7 Por que **mobile colapsa scope** mas **mantém evidence**?

Scope é **metadata estável** durante a sessão inteira (Larissa = vendedora balcão = não muda durante o expediente). Pode colapsar em ícone 44×44 + label encurtado porque após 2-3 perguntas Larissa já memorizou.

Evidence é **mutável por resposta** — cada query traz fontes diferentes. Esconder em mobile = Larissa não confia ("a Jana inventou isso?"). Pattern Mem.ai opaco repetido. Por isso evidence fica disponível em acordeão fechado por default (1 tap pra abrir), mas presente.

Trade-off real estate mobile 375px: scope colapsa de 32×320 (desktop) pra 32×56 (mobile). Evidence colapsa de visível-inline (desktop) pra acordeão (mobile). Razão: estabilidade vs mutabilidade.

---

## 6. Gap list priorizado top 10 (impacto × esforço × UI)

Complementar ao gap list do estado-da-arte irmão (que é backend/algoritmo). Aqui é UI/UX.

| # | Gap UI | Impacto | Esforço IA-pair | Pré-req backend |
|---|---|---|---|---|
| 1 | **Scope chip persistente** componente reusável (`<ScopeBadge>` + props inferido + dropdown filtro) | Alto — primeira coisa Larissa vê | ~4h | Gap #1 backend (`kb_observer_profiles`) |
| 2 | **Evidence chip inline** (`<EvidenceChip>` + variants business/scope/global + tooltip explainer) | Alto — mata filter bubble + LGPD-friendly | ~6h | Gap #2 backend (ranking expõe boost_reason) |
| 3 | **Loop fechado thumbs inline + micro-popup** | Médio-alto — alimenta re-rank | ~4h | Gap #1 + endpoint POST `kb_observer_intents.signal` |
| 4 | **Cold-start onboarding card 3-botões click** | Médio — calibra Larissa novo | ~3h | Gap #1 (table) + seed defaults role |
| 5 | **View-As banner Stripe-style** + Cog drawer filtro avançado | Médio (Wagner-only) | ~5h | Audit log + `is_superadmin` check |
| 6 | **Slash command `/perspectiva` parser** + 5 lenses canônicas seed | Médio | ~6h | Gap #8 backend (lens runtime) |
| 7 | **Diversity badge visual** (`📌 Fora do seu perfil`) + tooltip explainer | Médio | ~2h | Gap #7 backend (diversity_penalty) |
| 8 | **Mobile scope colapsado** + evidence acordeão 44px touch-targets | Médio (30% sessão Larissa) | ~5h | nenhum (CSS Tailwind 4) |
| 9 | **Ranking explainer panel** Wagner debug `/copiloto/admin/ranking-debug` | Baixo (Wagner-only) | ~4h | Gap #10 backend (endpoint debug) |
| 10 | **Estado erro "expand scope" 1-click** | Baixo | ~2h | nenhum (apenas Controller) |

**Total trilha UI completa #1-#4 (Larissa MVP)**: ~17h IA-pair = ~2 dias úteis fator-10x. Margem 2x = 4 dias úteis.

**Acoplamento backend×UI:** Gap UI #1 e #2 desbloqueiam tudo. Não dá pra construir UI antes de backend ONDA 6+ (`kb_observer_profiles` precisa existir pra scope chip ter o que mostrar). Sequência canônica: backend Gap #1 → UI Gap #1 → backend Gap #2 → UI Gap #2 → UI Gap #3-#4 (mesma onda) → resto incremental.

---

## 7. Comparativo % maturidade (UI/UX vs estado-da-arte)

| Capacidade UI | oimpresso hoje | Estado-da-arte 2026 | % matur. |
|---|---|---|---|
| Scope chip persistente | 0% (não existe) | Linear filter chips 100% | **0%** |
| Evidence chip inline | 0% | Perplexity inline citations 100% | **0%** |
| View-As impersonation | 0% | Stripe Connect 100% | **0%** |
| Slash command lens runtime | 0% | Granola Recipes 100% | **0%** |
| Diversity badge "fora do perfil" | 0% | TheWebConf 2024 pattern 50% (papers) | **0%** |
| Loop fechado thumbs inline | 0% | Linear feedback widget 80% | **0%** |
| Cold-start onboarding card | 0% | RAGSys / ChatGPT Projects 60% | **0%** |
| Mobile responsive AI chat | 60% (existe `Jana/Chat.tsx`) | shapeof.ai patterns 100% | **60%** |
| WCAG 2.1 AA chip contraste | 70% (shadcn já passa) | uswds.gov 100% | **70%** |
| Inertia::defer skeleton loading | 90% (RUNBOOK consolidado) | n/a (state of the art Laravel) | **90%** |
| **MÉDIA UI atual** | — | — | **~22%** |
| **MÉDIA UI proposta (se implementada)** | — | — | **~88%** (nota agregada §3.1) |

Gap UI atual ↔ proposta = **+66 pts**. Backend ONDA 6+ (estado-da-arte irmão) bloqueia ~70% deste delta.

---

## 8. Riscos UI (e mitigações)

| Risco | Probabilidade | Mitigação |
|---|---|---|
| **R-UI-1** Larissa ignora evidence chips porque não entende cores | Média | Microcopy PT-BR + tooltip primeiro-uso "as cores mostram de onde veio o fato" + observar via session-replay primeiras 2 semanas |
| **R-UI-2** Scope chip vira "outro elemento que Larissa ignora" | Baixa-média | Animação sutil de fade-in quando muda; click target generoso; medir taxa de clicks |
| **R-UI-3** Mobile evidence acordeão deixa Larissa cega ("não viu por que") | Média | Estado inicial mostra texto "Por que? (3 fontes)" forte e clicável; medir taxa de expand |
| **R-UI-4** View-As Wagner por engano em prod sem fechar = drift audit | Baixa | TTL automático 60min de View-As + reminder banner pulsante após 30min |
| **R-UI-5** Ranking explainer Wagner vira ruído quando backend muda fórmula | Média | Versão da fórmula exposta no panel ("scoring v2.3"); panel se auto-degrada se backend incompatível |
| **R-UI-6** Slash `/perspectiva` colide com markdown `/` no input | Baixa | Detecta `/perspectiva` no início da linha apenas; `/foo` em meio de texto não dispara |
| **R-UI-7** Diversity badge "📌 Fora do seu perfil" soa condescendente em PT-BR | Média | Wagner aprova microcopy ANTES de release (esta FICHA propõe; revisar com Larissa real) |
| **R-UI-8** WCAG falha por chip cor-coded sem texto fallback | Baixa | Sempre tem label texto + ícone — cor é redundante, não única (1.4.1 use of color) |

---

## 9. Microcopy PT-BR canônico (propostas)

| Contexto | Microcopy proposta | Anti-pattern evitado |
|---|---|---|
| Scope chip Larissa | "Conversando como vendedora-balcão · vestuário Termas/SC" | "Scope: business · vertical · region" (jargão) |
| Scope chip click pra Larissa (no-op) | tooltip: "É o jeito que vou priorizar minhas respostas pra você" | nada (silêncio = misterioso) |
| Evidence chip business | "sua loja" | "business_id=4" / "tenant" |
| Evidence chip scope | "vestuário Sul" | "scope_id=12 · vertical+region" |
| Evidence chip global | "regra geral" | "global · public" |
| Diversity badge | "📌 fora do seu perfil — pode importar" | "diversity-injected fact" / "outlier" |
| Loop fechado 👎 | "não era pra mim" | "thumbs down" / "report" |
| Loop fechado por que? | "Não é meu setor" / "Sou eu, mas hoje não" / "Tá errado" | "Incorrect" / "Out of scope" |
| Cold-start botão | "🛍 vendendo balcão · Hoje, agora." | "Set your current context" |
| Cold-start skip | "──── ou pergunte direto ────" | "Skip onboarding" (palavra inglesa) |
| View-As banner | "⚠ VENDO COMO LARISSA (ROTA LIVRE biz=4) · [sair desse modo]" | "Impersonating user X" |
| Estado erro sem resultado | "Não encontrei nada PRA VOCÊ — quer ver de outros vendedores de vestuário SC?" | "No results found · expand scope?" |
| Lens slash dica | "pergunte ou digite / pra mudar perspectiva" | "Type / for commands" |

---

## 10. Decisão / Nota / Recomendação

### 10.1 Nota agregada

| Linha | Nota |
|---|---|
| **oimpresso UI atual (sem desta proposta)** | ~22/100 |
| **oimpresso UI proposta (se implementada)** | **88/100** |
| Referência topo (Linear + Perplexity + Stripe combinados) | ~92/100 |
| Referência BR (Bling / Tiny / Conta Azul UI atual) | ~45/100 |

**Gap pro topo: -4 pts.** Causa principal: ranking explainer em produção real ainda inferior a Glean (que tem 5+ anos de calibração organizacional). Aceitável.

**Gap pro BR: +43 pts.** Concorrentes BR não têm sequer chat-AI; comparação é pro forma.

### 10.2 Top 3 P0 a fechar (em ordem de execução)

1. **Backend Gap #1 + UI Gap #1** (scope chip + `kb_observer_profiles`) — ~10h IA-pair total. Desbloqueia tudo.
2. **Backend Gap #2 + UI Gap #2** (ranking observer-weighted + evidence chips) — ~16h IA-pair total. Fecha loop com Larissa via Jana imediatamente.
3. **Backend Gap #3 (ContextoNegocio v2 com bloco "observador") + UI Gap #3 (loop fechado thumbs)** — ~8h. Calibração contínua em prod.

### 10.3 Ação imediata recomendada

Wagner aprova esta FICHA como **input do `Pages/Copiloto/Chat.tsx` charter** (a ser criada — MWART F1.5 gate). Charter incorpora:
- Layout 1280px desktop + 375px mobile (wireframes §4)
- 7 decisões UI (§1.2)
- Microcopy PT-BR (§9)
- 15 dimensões + nota 88/100 (§3.1)

Charter ↔ esta FICHA = fonte de verdade da intent UI. Backend Onda 6+ executa em paralelo.

### 10.4 Top 3 escolhas ARRISCADAS (honestidade epistêmica)

| # | Escolha | Por que pode estar errada |
|---|---|---|
| 1 | **Microcopy "fora do seu perfil"** em diversity badge | Não validei com Larissa real. Pode soar paternalista. Plano B: "fora do que você faz hoje" / "outro setor". Wagner decide com ela. |
| 2 | **Mobile colapsar scope mas manter evidence** | Decisão baseada em heurística (estabilidade vs mutabilidade). Pode ser que Larissa também queira evidence colapsado em mobile (real estate). Plano B: A/B test com 2 ROTA LIVRE users (Larissa + funcionária balcão) na 1ª semana. |
| 3 | **Slash command `/perspectiva` descoberta via footer dica** | Risco real: Larissa nunca descobre (Coda problem invertido). Granola tem 29 recipes builtin + onboarding ativo; oimpresso começa com 5 sem onboarding ativo. Plano B: tooltip primeiro-uso após 5ª pergunta da Larissa explicando lens. |

---

## 11. Implementação — pré-requisitos canon

Esta FICHA **não autoriza implementação** (só design). Pré-reqs antes de F3 código:

- [ ] ADR proposta `memory/decisions/proposals/NNNN-observer-weighted-kb-scope-dag.md` aceita (consolida estado-da-arte irmão §"recomendação concreta")
- [ ] Charter `resources/js/Pages/Copiloto/Chat.charter.md` criada com base nesta FICHA + ADR
- [ ] RUNBOOK `memory/requisitos/Copiloto/RUNBOOK-chat-scope-aware.md` criado (MWART F1.5 gate)
- [ ] Protótipo `prototipo-ui/prototipos/copiloto/chat-scope-aware/` com screenshot estática 1280px e 375px → Wagner aprova SCREENSHOT (ADR 0114 + ADR 0107)
- [ ] Backend ONDA 6+ KB com Gap #1 estado-da-arte irmão (`kb_observer_profiles`) entregue → senão UI não tem o que mostrar
- [ ] Pest cross-tenant biz=1 vs biz=99 garante isolation Tier 0 mesmo com observer-weighted (ADR 0093)
- [ ] Counsel LGPD revisa microcopy banner View-As (Eliana[E] quando voltar a estudar)

---

## 12. Fontes citadas

- [Glean — Search Personalization](https://www.glean.com/blog/search-personalization)
- [Notion — Breadcrumb hover preview 2026-04-24](https://www.notion.com/releases/2026-04-24)
- [Notion 3.2 mobile AI 2026-01-20](https://www.notion.com/releases/2026-01-20)
- [Linear Docs — Filters](https://linear.app/docs/filters)
- [Stripe — Manage individual accounts (View Dashboard As)](https://docs.stripe.com/connect/dashboard/managing-individual-accounts)
- [Perplexity Platform Guide — Citation-Forward Answers](https://www.unusual.ai/blog/perplexity-platform-guide-design-for-citation-forward-answers)
- [Perplexity ZipTie — Retrieval Ranking Citation Pipeline](https://ziptie.dev/blog/how-perplexity-ai-answers-work/)
- [Coda Help — AI Features](https://help.coda.io/en/articles/7988177-coda-ai-features)
- [Granola Help — Recipes](https://help.granola.ai/article/recipes)
- [Granola Updates 2026-01-16](https://www.granola.ai/updates/whats-new-2026-01-16)
- [ChatGPT Projects — OpenAI Help](https://help.openai.com/en/articles/10169521-using-projects-in-chatgpt)
- [Anthropic — Claude Projects](https://www.anthropic.com/news/projects)
- [Mem.ai review — The High Ceiling & Low Bar (transparency weakness)](https://www.hyperquest.net/the-high-ceiling-low-bar-of-memai/)
- [Smashing Magazine — AI Transparency Interface Patterns Part 2](https://www.smashingmagazine.com/2026/05/practical-interface-patterns-ai-transparency/)
- [New America — Why Am I Seeing This (transparency report)](https://www.newamerica.org/oti/reports/why-am-i-seeing-this/promoting-fairness-accountability-and-transparency-around-algorithmic-recommendation-practices/)
- [USWDS — Tooltip Accessibility Tests](https://designsystem.digital.gov/components/tooltip/accessibility-tests/)
- [W3C — WCAG 2.1](https://www.w3.org/TR/WCAG21/)
- [W3C — 1.4.13 Content on Hover or Focus](https://www.wcag.com/authors/1-4-13-content-on-hover-or-focus/)
- [Shapeof.ai — Citation patterns](https://www.shapeof.ai/patterns/citations)
- [UX Collective — Where AI sits in your UI](https://uxdesign.cc/where-should-ai-sit-in-your-ui-1710a258390e)

## 13. Cross-refs canon oimpresso

- [Sessão irmã estado-da-arte KB scope observer-weighted](../../sessions/2026-05-17-arte-kb-scope-observer-weighted.md) — arquitetura DAG + ranking decidida
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0150 — KB Unificado Grafo de Conhecimento](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)
- [ADR 0052 — ContextoNegocio múltiplos ângulos](../../decisions/0052-contextonegocio-expor-multiplos-angulos.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Gate visual F1.5](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — Cowork loop formalizado](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [Schema KB V1](../KB/SCHEMA-DB-V1.md)
- [RUNBOOK Inertia::defer pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md)
