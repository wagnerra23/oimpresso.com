---
id: requisitos-forja-capterra-design-ficha
title: "CAPTERRA-DESIGN-FICHA — Forja (UX/UI)"
slug: capterra-design-ficha-forja
type: capterra-design
module: Forja
status: draft
generated_at: 2026-09-01
generated_by: agente design-arte (auditoria estratégica UX/UI — NÃO tocou código)
source_ficha: CAPTERRA-FICHA.md
source_inventario: CAPTERRA-INVENTARIO.md
---

# CAPTERRA-DESIGN-FICHA — Forja (UX/UI)

> **Cruzamento gerado:** 2026-09-01
> **Skill aplicada:** `design-arte` (auditoria estratégica; ZOOM-OUT — não é `design:design-critique` tático).
> **Escopo:** as 12 telas de `Modules/Forja/Resources/js/Pages/Forja/**` lidas em `origin/main` (SHA `cb2e3be`), não no working tree local (stale).
> **Persona (corrigida — NÃO é a Larissa/vestuário):** time interno oimpresso — [W] (dono), [F] (dev), [M] (suporte+dev), [L] (iniciante), [E] (advogada+financeiro). É o "Linear/Jira interno" deles. Uso por gente técnica/semi-técnica em monitor desktop 1280–1440px, gerindo tasks/sprints/roadmap. Não cliente-facing.
> **Relação com os docs vizinhos:** o [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) audita **função** (features que o mercado tem e nós temos/não temos). Esta ficha audita **forma** (UX/UI). Onde o inventário diz "✅ shipou", esta ficha pergunta "shipou bem desenhado?". As duas convivem — não se recopiam.

---

## 0. Persona + jobs-to-be-done (Fase 1 — research cliente, sem inventar)

**Quem é.** Time de 5 pessoas (`memory/regras-time.md`), WIP máx 1–2 cada. Um dos operadores é **um agente** (tasks nascem via tool MCP `tasks-create` e o autor "some" quando a sessão fecha — recibo: Daily Brief #461, 519 tasks sem dono, mais antiga 95d). Essa é a premissa que separa a Forja da Jira e que o [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) já fixou.

**Contexto operacional.** Desktop 1280–1440px, teclado-first desejado (a Board já persegue isso), sessões curtas e frequentes ("o que tem pra fazer agora?"), leitura densa de listas. Sem exigência de touch/mobile real.

**Jobs-to-be-done principais (derivados dos charters + SPEC, não inventados):**
1. **"O que tem pra fazer agora?"** — MyWork / Trabalho / Backlog.
2. **Mover trabalho pelo fluxo** — Board (Kanban drag-drop + J/K/E/A).
3. **Triar o que o agente deixou órfão** — Triage (dossiê + aprovar/rejeitar/fundir) + Aprovações (fila `pending_approval`).
4. **Ver progresso do ciclo/epic** — Roadmap (quarter), Gantt (cronológico), Burndown.
5. **Auditar o que mudou** — Activity feed.

**Fricções conhecidas (com fonte, não impressão):**
- **Duas casas para o mesmo trabalho** — o cockpit `/forja` (abas triagem/backlog/quadro) sobrepõe as telas nativas Triage/Backlog/Board/Activity. "MOVIDO, não fundido" — decisão [W] pendente (BRIEFING §Próxima ação; SCOPE §cockpit). É fricção de **navegação**, não só de código.
- **Ritual de cycle que não acontece** — `cycles-active` → "Nenhum cycle ATIVO em COPI" (2026-08-04). Telas que assumem cycle vivo (Burndown, header da Board) degradam para estado vazio.
- **Fila órfã sem gatilho** — a superfície de triagem existe e é rica, mas nada empurra as 519 tasks sem dono pra ela (INVENTARIO proposta 002).

---

## 1. Players UX avaliados (referência 2026)

| # | Player | Tipo | Site | Especialidade (padrão concreto, não buzzword) |
|---|---|---|---|---|
| 1 | **Linear** | SaaS B2B (top) | linear.app | Command palette Cmd+K cobrindo **toda** ação · optimistic UI + **skeleton states** + cache agressivo (nav <100ms) · keyboard-first com atalho pra tudo · DS opinativo (remove customização) |
| 2 | **Height** | SaaS moderno | height.app | UI limpa/menos poluída que Jira · AI-native (autonomia de triagem) · squads pequenos rápidos |
| 3 | **Shortcut** | SaaS dev-team | shortcut.com | Stories/Iterations enxuto, keyboard-friendly, workflow leve pra times pequenos |
| 4 | **Jira Cloud** | Enterprise (mainstream) | atlassian.com | Completude funcional (Kanban+Backlog+Sprint+Roadmap+JQL) · **peso** e densidade que a Forja **não** quer importar |
| 5 | **Notion Projects** | Flex modular | notion.so | Empty states/onboarding fortes · views flexíveis · @mentions/inline |
| 6 | **GitHub Projects** | Dev-native | github.com | Board sobre issues, keyboard, integração git — premissa "operador técnico" igual à nossa |
| 7 | **Plane.so** | OSS self-host | plane.so | Único cuja premissa de deploy (1 tenant, 1 time, sem SaaS) bate com a Forja |

**Padrões SOTA 2026 que valem aqui (traduzindo premissa, não copiando — `proibicoes §5 2026-07-16`):**
- **Skeleton screens** viraram expectativa quando a carga fica entre ~400ms–3s (é exatamente a janela do `Inertia::defer` da Forja). Fonte: guias de loading-state 2026.
- **Command palette Cmd+K** é padrão de qualquer SaaS com >10 features — a Forja **já tem** (`AppShellV2` global + `SearchController`).
- **Optimistic UI** como decisão de design, não só de engenharia — a Board **já faz** (com 409 + revert), o resto do módulo não.

---

## 2. Dimensões UX P0–P3 (Forja × mercado)

Legenda: ✅ forte · 🟡 parcial/inconsistente · ❌ ausente. `oimpresso` = estado medido em `origin/main`.

| ID | Dimensão | Peso | Linear | Jira | Notion | **oimpresso Forja** | Nota /10 |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| D-001 | Hierarquia visual | P0 | ✅ | ✅ | ✅ | ✅ (PageHeader+KpiGrid canon em 12/12) | 8 |
| D-002 | Densidade informacional | P0 | ✅ | 🟡 | 🟡 | ✅ (boa; Board aperta a 1280 c/ 5-7 cols) | 7 |
| D-003 | Navegação primária | P0 | ✅ | 🟡 | ✅ | 🟡 (**duas casas**: `/project-mgmt/*` breadcrumb × `/forja/*` ForjaHub tabs) | 5 |
| D-004 | Sistema de design / tokens | P1 | ✅ | ✅ | ✅ | 🟡 (raw palette: DetailSheet 12, Roadmap 5, Triage 5; 2 gerações de PageHeader/layout) | 6 |
| D-005 | Microcopy PT-BR | P1 | ✅ | 🟡 | ✅ | ✅ (consistente, sem emoji; CTAs de vazio citam comando MCP) | 8 |
| D-006 | Empty states | P1 | ✅ | 🟡 | ✅ | 🟡 (Roadmap/Backlog/Activity/Triage têm; Board não tem explícito; CTA não-clicável) | 7 |
| D-007 | Loading + skeleton | P1 | ✅ | 🟡 | ✅ | ❌ (**0/15 telas** com `<Deferred>`/Skeleton — defer "pisca" de 0→valor) | 4 |
| D-008 | Error UX | P1 | ✅ | ✅ | 🟡 | 🟡 (Board excelente: 409/403/rede+`role=alert`+revert; Backlog bulk fire-and-forget sem catch) | 6 |
| D-009 | Atalhos teclado | P2 | ✅ | ✅ | 🟡 | 🟡 (Board best-in-class J/K/E/A/Enter/?/Esc + overlay + lane CI; ~7 telas sem atalho) | 7 |
| D-010 | Mobile/touch 1280px | P2 | ✅ | 🟡 | ✅ | 🟡 (desktop OK; alvos h-8=32px<44px; sem requisito touch real) | 6 |
| D-011 | Acessibilidade WCAG AA | P2 | ✅ | 🟡 | 🟡 | 🟡 (`role=alert`/`aria-label` parciais; **Activity/Roadmap 0 aria**; `text-[10px]` pervasivo) | 5 |
| D-012 | Feedback de ações | P2 | ✅ | ✅ | ✅ | 🟡 (Board otimista+banner; sem toast global; Backlog bulk sem confirmação visível) | 6 |
| D-013 | Formulários | P2 | ✅ | ✅ | ✅ | ✅ (filtros+bulk inline; Triage sinaliza `needs_owner` com borda âmbar) | 7 |
| D-014 | Dataviz | P3 | ✅ | ✅ | 🟡 | 🟡 (Roadmap barras, Burndown chart, Gantt svar-ui; barras usam palette crua) | 6 |
| D-015 | Onboarding | P3 | ✅ | 🟡 | ✅ | 🟡 (empty states + overlay `?` fazem as vezes; sem tour; aceitável p/ time-que-constrói) | 6 |

---

## 3. Decisão / Nota / Recomendação

### Notas

```
NOTA OIMPRESSO ATUAL (módulo Forja):        63/100
NOTA OIMPRESSO (tela-âncora Board):          70/100
NOTA REFERÊNCIA TOP (Linear):               ~92/100
NOTA REFERÊNCIA MAINSTREAM (Jira Cloud):    ~72/100

Gap pro topo (Linear):  −29 pts. Causa principal: ausência sistemática de skeleton no loading + duas casas de navegação + drift de token (raw palette).
Gap pro mainstream (Jira): −9 pts. (E parte do gap é intencional: a Forja recusa o peso/densidade da Jira por premissa — INVENTARIO §"Premissa da Jira que NÃO vale aqui".)
```

**Cálculo** (pesos calibrados: D1-3 = peso 3 · D4-8 = peso 2 · D9-15 = peso 1):
`(8+7+5)×3 + (6+8+7+4+6)×2 + (7+6+5+6+7+6+6)×1 = 60 + 62 + 43 = 165`; `Σpesos = 26`; `165/26 × 10 = 63,5 → 63/100`.
Board (âncora): `(8+7+6)×3 + (8+8+6+4+9)×2 + (9+6+6+8+7+7+7)×1 = 63+70+50 = 183`; `183/26×10 = 70,4 → 70/100`.

> ⚠️ **Honestidade da nota:** 63 não é ruim para ferramenta interna — a Board é genuinamente madura (optimistic-lock com 409, atalhos com lane de CI própria, DetailSheet que virou fonte do SaleSheet). O que puxa a média pra baixo são **3 dívidas transversais** que aparecem em quase toda tela: loading sem skeleton, navegação bifurcada e palette crua. Nenhuma é feature nova — são **conformidade** e **polish**, exatamente onde o fator 10x IA-pair rende mais.

### Causa principal do gap (1 frase)

O módulo **construiu as features** (o gap funcional do ADR 0100 fechou) mas **não fechou o acabamento transversal**: sem skeleton o defer pisca, a navegação tem duas portas para o mesmo trabalho, e o design-system vazou palette crua nas telas de detalhe/roadmap.

### Top gaps priorizados por impacto × esforço (fator 10x IA-pair, ADR 0106)

**Separados por natureza — conformidade com DS interno vs feature UX que o mercado tem e nós não:**

#### A. Conformidade com o DS interno (dívida nossa — fechar primeiro)

| # | Gap | Dim | Impacto | Esforço | Prioridade |
|---|---|---|:-:|:-:|:-:|
| G1 | **Skeleton/`<Deferred fallback>` nas props deferred** das 15 telas (hoje 0/15; o defer já existe no backend, falta só o fallback no front) | D-007 | Alto | S–M | **P0** |
| G2 | **Erradicar raw palette** — DetailSheet re-implementa `STATUS_BADGE` cru (linhas 140-146) em vez do canon `@/Components/board/badges` que a própria Board usa; idem Roadmap (5) e Triage (5) | D-004 | Médio | S | **P0** |
| G3 | **Unificar geração de layout** — 2 gerações convivem (`@/Components/shared/PageHeader` + sem primitivos × `@/Components/PageHeader` + `@/Components/layout`); telas antigas (Board/Backlog/Activity) não usam Grid/Inline/Stack | D-004 | Médio | M | P1 |
| G4 | **a11y mínimo uniforme** — `aria-label`/`role` ausentes em Activity/Roadmap; auditar `text-[10px]` (contraste+tamanho) e focus-visible | D-011 | Médio | S–M | P1 |
| G5 | **Error UX no Backlog bulk** — `.then(r=>r.json())` sem `.catch` nem toast; padronizar no padrão que a Board já tem | D-008/012 | Médio | S | P1 |

#### B. Feature de UX que o mercado tem e nós não (avaliar premissa antes)

| # | Gap | Dim | Premissa/veredito |
|---|---|---|---|
| G6 | **Resolver as duas casas de navegação** (cockpit `/forja` × telas nativas) | D-003 | **Decisão [W]** — não é técnica; fundir = deletar uma implementação. Maior impacto de UX do módulo, mas bloqueado por decisão de produto |
| G7 | Toast global de feedback (Linear/Jira têm) | D-012 | Vale — hoje só a Board dá feedback rico; um sistema de toast serviria todas |
| G8 | Skeleton→presence (avatar stack Centrifugo) | D-012 | **NÃO fazer** — INVENTARIO já recusou por premissa (5 pessoas, WIP 1-2; colisão coberta pelo 409) |
| G9 | Onboarding/tour | D-015 | Baixo sinal — o time constrói a ferramenta; empty states + overlay `?` bastam |

### Ação imediata recomendada (executável hoje)

**G1 + G2 juntos numa leva de conformidade DS.** Ambos são mecânicos, de baixo risco, cobertos pelo fator 10x, e atacam as duas dimensões de menor nota (D-007=4, e o D-004 que arrasta o bloco de peso 2). G1 elimina o "flash de zero" em 15 telas; G2 alinha o drawer-âncora (DetailSheet) ao token canon que ele mesmo deveria usar. Nenhum toca schema, valor, estoque ou multi-tenant — fora das travas Tier 0.

**NÃO começar por G6** (duas casas) apesar de ser o maior impacto — é decisão de produto [W], não conformidade; entra depois que [W] decidir fundir.

---

## 4. Notas de método (para a próxima reauditoria)

- Esta ficha é **auditoria**, não entrega — nada aqui está "pronto/implementado". As notas são medições datadas de 2026-09-01 em `origin/main`; se a data incomodar, re-meça (greps de raw palette e `<Deferred>` estão no session log).
- **Não vira gate por nome/pasta.** "Tela sem skeleton" é decidível mecanicamente (grep `Deferred|Skeleton` = 0), mas armar catraca disso é decisão [W] + FP medido antes (`proibicoes` "LIGUE A MÁQUINA" #4). Aqui só reporto.
- **Próxima reauditoria de design sugerida:** após G1/G2 fecharem, ou junto da próxima reauditoria funcional (INVENTARIO sugere 2026-11-04).

## Refs

- [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) · [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) · [`BRIEFING.md`](BRIEFING.md) · [`SCOPE.md`](SCOPE.md)
- [`CHARTER-board.md`](CHARTER-board.md) · [`RUNBOOK-index.md`](RUNBOOK-index.md) · [`RUNBOOK-gantt.md`](RUNBOOK-gantt.md)
- [`projectmgmt-index-visual-comparison.md`](projectmgmt-index-visual-comparison.md) · [`triage-analista-visual-comparison.md`](triage-analista-visual-comparison.md)
- Constituição UI v2 — [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · Sidebar dark-fixo [UI-0023](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md) (DEFINITIVO, não é gap)
- Fator 10x IA-pair [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) · Cliente como sinal [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- SOTA 2026: [Linear speed breakdown](https://performance.dev/how-is-linear-so-fast-a-technical-breakdown) · [Skeleton loading 2026](https://cpcloudhosting.com/how-to-design-loading-states-and-skeleton-screens/) · [Jira vs Height 2026](https://launchtry.com/compare/jira-vs-height)
