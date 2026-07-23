---
id: requisitos-jana-auditoria-modo-c-2026-05-09
slug: jana-auditoria-modo-c-2026-05-09
title: "Jana — Auditoria Modo C (7 telas Inertia/React) baseline 2026-05-09"
type: auditoria
module: Jana
status: baseline
date: 2026-05-09
auditor: Claude (Opus 4.7)
framework: cockpit-runbook Modo C + BENCHMARKS.md (6 categorias) + Nielsen 8H + Cockpit V2 (ADR 0110)
---

# Auditoria Modo C — Módulo Jana (7 telas) — baseline 2026-05-09

> Auditoria comparativa contra `BENCHMARKS.md` (6 categorias), heurísticas Nielsen (8) e Cockpit Pattern V2 ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)).
> **Read-only** — nenhum `.tsx`/`.php` foi modificado. Score 0-100 por tela ([CHECKLIST.md §G](../../../.claude/skills/cockpit-runbook/CHECKLIST.md)).

## Sumário executivo

- **Score médio Jana:** **66/100** 🟠 (precisa refactor cross-cutting antes de mergear novas telas)
- **Telas Admin/* (Custos, Governança, Qualidade)** estão visivelmente mais maduras que **Chat/Cockpit/Memoria** — usam `PageHeader`, `KpiGrid`, `KpiCard`, `EmptyState`, `SubNav` shared.
- **Cockpit.tsx** é o pior da lista: lógica de "fingir resposta" (mock typing + `setTimeout`) ainda em produção, sem backend real.
- **Memoria.tsx** tem **R-DS-002 sistemático** (categorias com `bg-blue-100 text-blue-800` etc — 5 ocorrências) que rebenta dark mode.
- **Qualidade/Index.tsx** tem **emojis hardcoded em conteúdo** (✅/🔴 — viola R-DS-003) e cores hex literais (`#3b82f6`).
- Score mais alto: **Custos/Index** (84/100 🟡) — referência local de boa prática Jana.
- Score mais baixo: **Cockpit.tsx** (48/100 🔴) — reescrever ou matar a rota.

## Ranking por score

| # | Tela | Categoria BENCHMARKS | Score | Banda |
|---|------|----------------------|-------|-------|
| 1 | `Admin/Custos/Index.tsx` | 3 — Dashboard/KPI | **84/100** | 🟡 Bom |
| 2 | `Admin/Governanca/Index.tsx` | 3 — Dashboard/KPI + 5 — Settings (sub-nav) | **78/100** | 🟡 Bom |
| 3 | `Dashboard.tsx` | 3 — Dashboard/KPI | **74/100** | 🟡 Bom |
| 4 | `Chat.tsx` | 1 — Inbox conversacional | **66/100** | 🟠 Precisa refactor |
| 5 | `Admin/Qualidade/Index.tsx` | 3 — Dashboard/KPI + 6 — Listagem | **62/100** | 🟠 Precisa refactor |
| 6 | `Memoria.tsx` | 6 — Listagem operacional | **52/100** | 🟠 Precisa refactor |
| 7 | `Cockpit.tsx` | 1 — Inbox conversacional | **48/100** | 🔴 Reescrever |

**Score médio: 66/100 🟠**

---

## 1. Admin/Custos/Index.tsx — 84/100 🟡

**Arquivo:** [resources/js/Pages/Jana/Admin/Custos/Index.tsx](../../../resources/js/Pages/Jana/Admin/Custos/Index.tsx) (390 linhas)
**Categoria BENCHMARKS:** §3 Dashboard/KPI overview (Mixpanel/Amplitude/Vercel Analytics).

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 36/40 | 0 CRITICAL, 1 WARN (sem `EmptyState` shared no caso "0 usuários"), 1 INFO |
| ADR (30) | 26/30 | Atende V2 typography parcial — header ok, sem KPI cards padrão V2 (mini-KPIs `text-4xl tabular-nums`), sem 5 filter pills |
| UX (30) | 22/30 | 1 WARN (Q4 — sem skeleton em fetch debounced), 1 INFO (H7 — sem atalhos `/` etc) |
| **TOTAL** | **84/100** | 🟡 Mergear OK; polish opcional |

### Top 3 violações

1. **`Index.tsx:331-338`** — empty state inline em vez de `<EmptyState/>` shared (texto "Nenhum consumo de IA no período…" com `<br/>` e `<span>`). Patterns 7 do BENCHMARKS §3 (empty state com instrução) atendido **textualmente** mas perdeu shared component.
2. **`Index.tsx:296-298`** — preset "Mês atual / Mês anterior / 90d" não cobre "Hoje / 7d" — BENCHMARKS §3 pattern 2 (time range picker) tem 4 presets canônicos esperados; falta granularidade curta (Hoje/7d).
3. **`Index.tsx:328-378`** — tabela `<table>` HTML cru sem componente shared `<DataTable>` (não existe ainda no DS); aceitável mas comparado ao Notion DB / Airtable (BENCHMARKS §6) faltam: sort, density toggle, sticky header, search.

### Top 3 vitórias

1. **PageHeader + KpiGrid + KpiCard shared** — usa todos os 3 componentes do DS ([Index.tsx:211-254](../../../resources/js/Pages/Jana/Admin/Custos/Index.tsx)). Padrão limpo.
2. **`GastoDiarioChart` SVG inline com viewBox** — sem dep de `recharts`/`chart.js`, dark-mode-safe via `text-primary` + `fill-primary/15`. Pattern de [Index.tsx:86-183](../../../resources/js/Pages/Jana/Admin/Custos/Index.tsx).
3. **Filtro custom com form `de`/`ate` + preserveState/preserveScroll** — [Index.tsx:196-207](../../../resources/js/Pages/Jana/Admin/Custos/Index.tsx). Atende `preference_cache_estado_preservado` (auto-mem) e ADR 0039 §4.

### Prioridade fix

- **P2:** trocar empty inline por `<EmptyState/>` (cosmético)
- **P2:** adicionar preset "Hoje" + "7 dias"
- **P1:** adicionar atalho `/` foca filtro de período (Governança já fez isso — copiar)

---

## 2. Admin/Governanca/Index.tsx — 78/100 🟡

**Arquivo:** [resources/js/Pages/Jana/Admin/Governanca/Index.tsx](../../../resources/js/Pages/Jana/Admin/Governanca/Index.tsx) (503 linhas)
**Categoria BENCHMARKS:** §3 Dashboard + §5 Settings (sub-nav).

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 32/40 | 1 CRITICAL (`bg-emerald-500/amber-500/orange-500/rose-500` cor crua em `statusBarClass` — R-DS-002), 1 WARN (cores cruas `stroke-amber-400`), 1 INFO |
| ADR (30) | 25/30 | ADR 0039 §4 atende (`oimpresso.copiloto.governanca.preset` em localStorage); SubNav próprio em vez de pattern V2 pills (não-bloqueante — sub-nav é variant aceita) |
| UX (30) | 21/30 | 1 WARN (H8 — 503 linhas em 1 arquivo, 3 seções condicionais misturadas; deveria ser 3 sub-rotas), 1 WARN (Q1 — sem 1 KPI hero claro entre os 4) |
| **TOTAL** | **78/100** | 🟡 Mergear; refactor split em 3 sub-rotas no próximo ciclo |

### Top 3 violações

1. **`Index.tsx:72-79`** — `statusBarClass()` retorna `bg-emerald-500 h-2`, `bg-amber-500 h-2`, `bg-orange-500 h-2`, `bg-rose-500 h-2` — viola R-DS-002 (cor crua Tailwind). Status fixo é exceção R-DS-002 OK, **mas misturar com `h-2` na mesma string vira shotgun-CSS** — separar em const + classe utility.
2. **`Index.tsx:139`** — `stroke-amber-400 dark:stroke-amber-300` em SVG; aceitável (status fixo) mas `borderTop: '1px dashed'` inline em [Index.tsx:155](../../../resources/js/Pages/Jana/Admin/Governanca/Index.tsx) é CSS hardcoded inline (R-DS-007).
3. **`Index.tsx:218-493`** — 3 seções (`consumo` / `acesso` / `usuarios`) em um Page só, controladas por estado local com `localStorage`. **Deveria ser 3 rotas Inertia** — separação de concerns brutal (ADR 0094 §5). Render condicional caro pra o cliente.

### Top 3 vitórias

1. **Atalho `/` foca seletor de período** com cleanup correto — [Index.tsx:190-200](../../../resources/js/Pages/Jana/Admin/Governanca/Index.tsx). Único da família que implementa Q7 (atalho power-user) + GOTCHAS.md "Listener sem cleanup".
2. **`EmptyState` shared usado em 4 sub-cenários** (sem dados, denied, top tools, top users) — [Index.tsx:96-102, 357-362, 388-393, 427-432, 462-467](../../../resources/js/Pages/Jana/Admin/Governanca/Index.tsx). H4 (consistency) bem atendido.
3. **`StatusBadge kind="mcp_status"`** — usa shared component pra padronizar status MCP — [Index.tsx:367](../../../resources/js/Pages/Jana/Admin/Governanca/Index.tsx). Boa abstração.

### Prioridade fix

- **P1:** split em 3 rotas (`/admin/governanca/consumo`, `.../acesso`, `.../usuarios`) — reduz blast radius
- **P2:** consolidar `statusBarClass` em const map sem misturar `h-2`
- **P2:** trocar `borderTop: '1px dashed'` inline por classe utility

---

## 3. Dashboard.tsx — 74/100 🟡

**Arquivo:** [resources/js/Pages/Jana/Dashboard.tsx](../../../resources/js/Pages/Jana/Dashboard.tsx) (224 linhas)
**Categoria BENCHMARKS:** §3 Dashboard/KPI overview (cards de meta com farol).

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 30/40 | 0 CRITICAL, 2 WARN (`bg-emerald-500/amber-400/rose-500` aceitos como status fixo, mas `text-emerald-500/text-rose-500` em sparkline marginais), 0 INFO |
| ADR (30) | 23/30 | Não usa `PageHeader`/`KpiGrid` shared (faz `<h1>` cru — Custos/Index usa); sem KPI hero (cobertura, % alcançadas) — apenas N cards individuais |
| UX (30) | 21/30 | 1 WARN (Q4 — sem loading state), 1 INFO (H7 — cards inteiros não-clicáveis), 1 WARN (Q5 — empty state OK mas trajetória linear em meta sazonal vai mentir) |
| **TOTAL** | **74/100** | 🟡 Bom; polish vale a pena |

### Top 3 violações

1. **`Dashboard.tsx:182-196`** — header inline (`<h1 className="text-2xl font-semibold">Dashboard de Metas</h1>`) em vez de `<PageHeader/>` shared como Custos/Index/Governança fazem. Inconsistência cross-Jana (H4).
2. **`Dashboard.tsx:54-70`** — `calcularFarol` assume trajetória linear hardcoded; meta sazonal (faturamento Dezembro) vai acender vermelho injustamente. RUNBOOK-dashboard.md já documenta como pegadinha mas não foi resolvido. Severidade: a meta `R$ [redacted Tier 0]MM/ano` usa esse cálculo.
3. **`Dashboard.tsx:198-205`** — empty state inline (12 linhas de JSX) em vez de `<EmptyState/>` shared. Mesmo gap detectado em Custos/Index.

### Top 3 vitórias

1. **Sparkline SVG inline + tendência up/down/flat** com lucide icons — [Dashboard.tsx:87-125](../../../resources/js/Pages/Jana/Dashboard.tsx). Boa aplicação de R-DS-003.
2. **Farol semântico com 4 estados** + `aria-hidden="true"` na faixa lateral — [Dashboard.tsx:136](../../../resources/js/Pages/Jana/Dashboard.tsx). A11y consciente.
3. **Persistent Layout** correto via `Dashboard.layout = (page) => <AppShellV2>...` — [Dashboard.tsx:220-224](../../../resources/js/Pages/Jana/Dashboard.tsx). Padrão Anthropic atendido.

### Prioridade fix

- **P1:** trocar header por `<PageHeader/>` (consistência com Custos/Governança)
- **P1:** trocar empty inline por `<EmptyState/>` shared
- **P2:** documentar trajetória sazonal em ADR + adicionar `periodo.trajetoria` no cálculo

---

## 4. Chat.tsx — 66/100 🟠

**Arquivo:** [resources/js/Pages/Jana/Chat.tsx](../../../resources/js/Pages/Jana/Chat.tsx) (363 linhas)
**Categoria BENCHMARKS:** §1 Inbox conversacional (Front/Intercom/WhatsApp Web).

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 22/40 | 1 CRITICAL (`bg-emerald-100 text-emerald-800`/`bg-amber-100 text-amber-800`/`bg-rose-100 text-rose-800` em `DIFICULDADE_CONFIG` — R-DS-002 cor crua sistemática), 2 WARN (classes `sb-action`/`sb-conv`/`sb-bullet` CSS custom externo não-tokenizado), 1 INFO |
| ADR (30) | 22/30 | ADR 0039 §3 (LinkedAppsPanel) ausente — Jana tem contexto cross-módulo (cliente, OS, NFe) que merece sidebar direita; ADR 0039 §4 (atalhos J/K/E/A) ausente |
| UX (30) | 22/30 | 1 WARN (H4 — `ConvSidePanel` reinventa o que `AppShellV2.conversas` já faz; duplicação), 1 WARN (H7 — sem atalho `/` foca search), 1 INFO (Q5 — empty "Arraste para fixar" não convida ação clara) |
| **TOTAL** | **66/100** | 🟠 Precisa refactor — corrigir CRITICAL antes de seguir |

### Top 3 violações

1. **`Chat.tsx:86-90`** — `DIFICULDADE_CONFIG` usa `bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300` repetido em 3 entries. Apesar de cobrir dark mode, a granularidade é cor crua direta — viola R-DS-002. Fix: tokens `--badge-success-bg`, `--badge-warning-bg`, `--badge-danger-bg` em `cockpit.css`.
2. **`Chat.tsx:294-363`** — `ConvSidePanel` interno com classes `sb-actions`, `sb-action`, `sb-conv`, `sb-bullet` que vêm de CSS externo (cockpit.css?) não-tokenizado pelo DS. AppShellV2 já recebe `conversas` prop e renderiza sua própria lista — duplicação. GOTCHAS.md item "Sidebar custom em vez de LinkedAppsPanel" se aplica.
3. **`Chat.tsx:236-239`** — `selectConv` faz `router.get(/copiloto/conversas/${id})` em **2 lugares** (no `ConvSidePanel` props E no AppShellV2 prop). Risco de duplicar navegação.

### Top 3 vitórias

1. **Adapter `adaptarMensagem(MensagemBackend → CockpitMensagem)`** — [Chat.tsx:100-133](../../../resources/js/Pages/Jana/Chat.tsx). Separa contrato backend Jana do contrato visual Cockpit. SoC brutal (ADR 0094 §5).
2. **`useMemo` em `mensagensCockpit` e `conversaFoco`** — [Chat.tsx:219-232](../../../resources/js/Pages/Jana/Chat.tsx). Performance consciente.
3. **`PropostaCard` com `aria-label` em ações destrutivas** — [Chat.tsx:188-196](../../../resources/js/Pages/Jana/Chat.tsx). Acessibilidade explícita (R-DS-006).

### Prioridade fix

- **P0:** trocar `DIFICULDADE_CONFIG` por tokens semânticos
- **P0:** consolidar lista de conversas — usar APENAS o canal AppShellV2 ou APENAS o ConvSidePanel; remover duplicação
- **P1:** registrar atalhos `J`/`K`/`E`/`/` (BENCHMARKS §1 pattern 4) ou justificar ausência em ADR per-tela

---

## 5. Admin/Qualidade/Index.tsx — 62/100 🟠

**Arquivo:** [resources/js/Pages/Jana/Admin/Qualidade/Index.tsx](../../../resources/js/Pages/Jana/Admin/Qualidade/Index.tsx) (375 linhas)
**Categoria BENCHMARKS:** §3 Dashboard + §6 Listagem operacional (trend + tabela detalhada).

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 20/40 | 2 CRITICAL (`color = '#3b82f6'` + paleta hex inline em `allMetrics` — R-DS-002 + R-DS-007 cor hex hardcoded), 1 CRITICAL (emoji `'✅'`/`'🔴'` em `gateStatus` — R-DS-003 só lucide), 2 WARN (`text-emerald-600 dark:text-emerald-400`/`text-red-600` cores cruas), 1 INFO |
| ADR (30) | 22/30 | Não usa SubNav apesar de ter 3 sub-tabelas; sem persistência de filtro `dias`/`business_id` em localStorage (ADR 0039 §4) |
| UX (30) | 20/30 | 1 WARN (Q1 — 8 KPIs por business + tabela trend + tabela detalhada = sobrecarga cognitiva, sem hierarquia clara), 1 WARN (H8 — 3 tabelas similares concorrem), 1 INFO (H6 — gabarito display em 1 linha sem destaque) |
| **TOTAL** | **62/100** | 🟠 Precisa refactor — emojis + hex hardcoded são P0 |

### Top 3 violações

1. **`Index.tsx:101-102`** — `gateStatus()` retorna `emoji: '✅'` ou `'🔴'`. Viola **R-DS-003** (só lucide-react) e **GOTCHAS.md** ("Ícones de bibliotecas alternativas — emojis nem SVG custom"). Fix: usar `<CheckCircle/>` / `<XCircle/>` lucide com cor token.
2. **`Index.tsx:144-152`** — array `allMetrics` com `color: '#3b82f6'`, `'#10b981'`, `'#8b5cf6'`, `'#f59e0b'`, `'#ef4444'`, `'#06b6d4'`, `'#84cc16'`, `'#ec4899'`. **8 cores hex hardcoded passadas pra `<Sparkline color={...}/>`**. Viola R-DS-002 + R-DS-007. Fix: tokens CSS `--metric-color-1..8` ou paleta semântica do DS.
3. **`Index.tsx:108`** — `function Sparkline({ values, color = '#3b82f6' })` — **default hex hardcoded** + parâmetro hex (não token). Componente local não-tokenizado.

### Top 3 vitórias

1. **Função `gateStatus` separa avaliação de regra de UI** — [Index.tsx:94-103](../../../resources/js/Pages/Jana/Admin/Qualidade/Index.tsx). Lógica testável.
2. **`KpiCard tone={ok ? 'success' : 'danger'}` dinâmico** — [Index.tsx:221-244](../../../resources/js/Pages/Jana/Admin/Qualidade/Index.tsx). Usa o tom semântico do DS (não cor crua) — boa prática parcial mesmo com problemas.
3. **`ScrollArea max-h-[600px]` + `sticky top-0`** na trend table — [Index.tsx:258-260](../../../resources/js/Pages/Jana/Admin/Qualidade/Index.tsx). BENCHMARKS §6 pattern 9 (sticky header) atendido.

### Prioridade fix

- **P0:** trocar emojis `'✅'/'🔴'` por `<CheckCircle/>`/`<XCircle/>` lucide
- **P0:** mover paleta de 8 cores hex pra tokens CSS ou usar `text-primary`/`text-rose-500` etc consistentes
- **P1:** persistir `dias`/`business_id` em localStorage `oimpresso.copiloto.qualidade.*`
- **P2:** considerar SubNav pra separar "KPIs por business" de "Trend table" de "Runs detalhada"

---

## 6. Memoria.tsx — 52/100 🟠

**Arquivo:** [resources/js/Pages/Jana/Memoria.tsx](../../../resources/js/Pages/Jana/Memoria.tsx) (204 linhas)
**Categoria BENCHMARKS:** §6 Listagem operacional (CRUD de fatos).

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 14/40 | **3 CRITICAL** (`CATEGORIA_LABELS` 5 entries com cor crua sem dark variant — R-DS-002 + R-DS-005 quebra dark mode; `<textarea className="w-full text-sm rounded-md border p-2">` HTML cru sem `<Textarea/>` shadcn — R-DS-001 sentido amplo; `text-red-600`/`text-green-600` cores cruas), 2 WARN (sem `<EmptyState/>` shared; `<CardHeader className="px-0 pb-2">` sem Card wrapper — usa fora do contrato shadcn) |
| ADR (30) | 18/30 | Sem PageHeader; sem persistência de filtro de categoria; toca dado LGPD-sensível mas sem confirma type-to-confirm em deleção (BENCHMARKS §5 pattern 3) |
| UX (30) | 20/30 | 1 CRITICAL (Q2 — `confirm('Tem certeza?')` window.confirm é UX ruim em 2026; deveria ser AlertDialog shadcn), 1 WARN (H5 — undo ausente após "Esquecer" — LGPD pode demandar tombstone reversível) |
| **TOTAL** | **52/100** | 🟠 Precisa refactor — dark mode quebra (CRITICAL P0) |

### Top 3 violações

1. **`Memoria.tsx:41-47`** — `CATEGORIA_LABELS` com `bg-blue-100 text-blue-800`, `bg-purple-100 text-purple-800`, `bg-red-100 text-red-800`, `bg-gray-100 text-gray-800`, `bg-amber-100 text-amber-800`. **Sem variantes `dark:`**. Em dark mode, `bg-blue-100` (#dbeafe quase branco) sobre fundo escuro = **ilegível**. R-DS-002 + R-DS-005 violados gravemente.
2. **`Memoria.tsx:78`** — `if (!confirm('Tem certeza? Essa memória será esquecida e não voltará.'))` — `window.confirm()` é nativo do browser, fora do DS. BENCHMARKS §5 pattern 3 (type-to-confirm em destrutivo) e Nielsen H5 (error prevention) esperam `<AlertDialog/>` shadcn com botão destacado vermelho.
3. **`Memoria.tsx:124`** — `<textarea className="w-full text-sm rounded-md border p-2 min-h-[80px]"/>` HTML cru — viola spirit do R-DS-001 (componentes shadcn). DS tem `@/Components/ui/textarea`. Também `text-red-600`/`text-green-600` em [Memoria.tsx:104, 110](../../../resources/js/Pages/Jana/Memoria.tsx).

### Top 3 vitórias

1. **Agrupamento por categoria** com `useMemo`-like reduce — [Memoria.tsx:137-142](../../../resources/js/Pages/Jana/Memoria.tsx). UX clara (H6 recognition over recall).
2. **Edit inline com `useForm`** + `preserveScroll` — [Memoria.tsx:63-75](../../../resources/js/Pages/Jana/Memoria.tsx). Atende BENCHMARKS §6 pattern 6 (inline edit).
3. **Copy LGPD explícito no header** — "Você pode editar ou esquecer qualquer um a qualquer momento (LGPD)" — [Memoria.tsx:152-154](../../../resources/js/Pages/Jana/Memoria.tsx). H2 (match real-world) + transparência (Constituição §7).

### Prioridade fix

- **P0:** adicionar variantes `dark:` em `CATEGORIA_LABELS` ou refatorar pra tokens semânticos `bg-info-subtle text-info`
- **P0:** trocar `confirm()` por `<AlertDialog/>` shadcn
- **P1:** trocar `<textarea>` cru por `<Textarea/>` shadcn
- **P2:** considerar undo (toast com "desfazer 5s") após esquecer — mais alinhado a LGPD

---

## 7. Cockpit.tsx — 48/100 🔴

**Arquivo:** [resources/js/Pages/Jana/Cockpit.tsx](../../../resources/js/Pages/Jana/Cockpit.tsx) (138 linhas)
**Categoria BENCHMARKS:** §1 Inbox conversacional — **MVP/piloto declarado**.

### Score detalhado

| Categoria | Score | Detalhe |
|-----------|-------|---------|
| DS (40) | 22/40 | 0 CRITICAL diretos (delega ao `Thread`/`Composer` shared); 2 WARN (`👍` emoji literal em [Cockpit.tsx:103](../../../resources/js/Pages/Jana/Cockpit.tsx) — R-DS-003), 1 WARN (`localStorage.setItem` fora de useEffect — render-side-effect anti-pattern React) |
| ADR (30) | 12/30 | **3 violações graves**: (a) ADR 0094 §1 (context as product) — typing/reply mockados com `setTimeout` em produção; (b) ADR 0094 §4 (loop fechado por métrica) — não há fetch real, sem telemetria; (c) GOTCHAS.md "Cache/estado preservado" — usa `setMensagensLocal` local em vez de Inertia state, ao trocar conversa perde |
| UX (30) | 14/30 | **1 CRITICAL (Q1)** — usuário em produção que digita uma mensagem recebe "Recebido, vou verificar e te respondo já já 👍" hardcoded — induz erro grave; **1 CRITICAL (H1)** — visibility of system status mente (mostra typing falso); 1 WARN (Q4 — sem loading real) |
| **TOTAL** | **48/100** | 🔴 **Reescrever ou matar a rota** — risco de cliente acreditar que Jana respondeu |

### Top 3 violações

1. **`Cockpit.tsx:81-109`** — `handleSend()` simula resposta com `setTimeout(setTyping(true), 600)` + `setTimeout(reply, 2400)` com texto hardcoded `"Recebido, vou verificar e te respondo já já 👍"`. **Em produção**. Em `/copiloto/cockpit` qualquer usuário (incluindo Larissa via demo) pode digitar e ver falsa resposta — induz erro de confiança grave (Nielsen H1 mentindo). Comentário do código já reconhece "(Fase 3: substituir por POST...real)" mas tela está aceita em rota live.
2. **`Cockpit.tsx:75-79`** — `localStorage.setItem(LS.CHAT_TAB, chatTab)` rodando **fora de useEffect** dentro do render (`if (typeof window !== 'undefined') { localStorage.setItem(...) }`). Isso roda em CADA render. Anti-pattern React + custos de I/O sync. Comentário "useEffect não necessario — escreve direto, idempotente" é incorreto — é side-effect de render proibido pelas docs do React.
3. **`Cockpit.tsx:103`** — emoji `👍` literal em string de UI (R-DS-003). Cumulativo com o problema (1) — texto hardcoded com emoji.

### Top 3 vitórias

1. **AppShellV2 com `conversaFoco` + `activeConvId` + `onSelectConv`** — [Cockpit.tsx:111-126](../../../resources/js/Pages/Jana/Cockpit.tsx). Usa o canal canônico do shell (não duplica como Chat.tsx faz com ConvSidePanel).
2. **Compõe `ChatTabs` + `ThreadHeader` + `Thread` + `Composer` shareds** — [Cockpit.tsx:127-135](../../../resources/js/Pages/Jana/Cockpit.tsx). Reuso máximo, código mínimo.
3. **138 linhas total** — densidade boa pra MVP. Bem comentado sobre status piloto.

### Prioridade fix

- **P0:** **DESATIVAR rota `/copiloto/cockpit`** ou colocar atrás de feature flag `MVP_COCKPIT=false` em prod até ter backend real
- **P0:** remover `setTimeout` mock e plugar `POST /copiloto/conversas/{id}/mensagens` real (Chat.tsx já tem)
- **P1:** mover `localStorage.setItem` pra `useEffect`
- **P2:** remover emoji `👍`

---

## Ratchet baseline

> **Score mínimo aceito a partir de 2026-05-09** — qualquer PR que tocar uma das 7 telas Jana e baixar o score deste valor deve ser bloqueado em CI.

| Tela | Score atual | **Mínimo (ratchet)** |
|------|-------------|----------------------|
| Admin/Custos/Index.tsx | 84 | **84** |
| Admin/Governanca/Index.tsx | 78 | **78** |
| Dashboard.tsx | 74 | **74** |
| Chat.tsx | 66 | **66** |
| Admin/Qualidade/Index.tsx | 62 | **62** |
| Memoria.tsx | 52 | **52** |
| Cockpit.tsx | 48 | **48** |

**Score médio do módulo Jana — ratchet:** **66**

### Padrão de implementação Pest (sugerido)

Criar `tests/Feature/DesignSystem/JanaAuditTest.php` (Pest) que:

1. Lê `.tsx` de cada tela auditada via `file_get_contents`.
2. Aplica regex/AST counters pelas regras D1-D8 do CHECKLIST.md (cor crua, `<button>` cru, hex hardcoded, `<textarea>` cru, emoji literal em conteúdo, `setTimeout` em handler de produção).
3. Calcula score via fórmula CHECKLIST §G.1.
4. `expect($score)->toBeGreaterThanOrEqual($minimum)` por tela.

```php
// tests/Feature/DesignSystem/JanaAuditTest.php (esqueleto)
it('Jana — Cockpit.tsx mantém score >= 48 (ratchet 2026-05-09)', function () {
    $score = (new TelaScoreCalculator(
        path: 'resources/js/Pages/Jana/Cockpit.tsx',
    ))->compute();

    expect($score)->toBeGreaterThanOrEqual(48);
});
```

Quando uma tela subir de score (ex: Memoria 52→70 após fix dark mode), **atualizar o ratchet pra 70 no mesmo PR** — append-only de baseline.

---

## Top 5 fixes P0 cross-cutting (≥3 telas afetadas)

Itens que aparecem em pelo menos 3 das 7 telas — atacar primeiro pra subir score médio.

### P0-1. Cor crua Tailwind sem `dark:` variant em tokens semânticos de status (R-DS-002 + R-DS-005)

**Telas afetadas:** Chat (`DIFICULDADE_CONFIG`), Memoria (`CATEGORIA_LABELS`), Qualidade (paleta hex), Governanca (`statusBarClass`).

**Sintoma:** dark mode quebra em Memoria de forma crítica (`bg-blue-100` quase-branco sobre fundo escuro). Telas usam `bg-{tom}-100 text-{tom}-800` Light-only.

**Fix:** centralizar em `cockpit.css` ou DS tokens:

```css
:root {
  --badge-info-bg: theme(colors.blue.100);
  --badge-info-fg: theme(colors.blue.800);
}
.dark {
  --badge-info-bg: theme(colors.blue.900 / 0.30);
  --badge-info-fg: theme(colors.blue.300);
}
```

Esforço: ~3h (1 PR cross-tela). ROI: +15 pontos médios em Memoria.

### P0-2. Emojis hardcoded em conteúdo UI (R-DS-003)

**Telas afetadas:** Qualidade (`gateStatus` retorna `'✅'`/`'🔴'`), Cockpit (reply mockado `'👍'`), Chat (`'💬'` indireto via texto?).

**Fix:** trocar por lucide icons (`<CheckCircle/>`, `<XCircle/>`, `<ThumbsUp/>`) com `text-emerald-500`/`text-rose-500`.

Esforço: ~1h. ROI: 0 quebras de bundle, consistência visual.

### P0-3. Empty state inline em vez de `<EmptyState/>` shared

**Telas afetadas:** Custos (tabela "Nenhum consumo"), Dashboard (sem metas), Memoria (sem fatos), Chat (lista de conversas vazia inline).

**Sintoma:** UX inconsistente entre módulos Jana — Governança usa `<EmptyState/>` corretamente em 4 lugares; resto reinventa com `<div className="text-center py-12 text-muted-foreground">`.

**Fix:** importar `@/Components/shared/EmptyState` em todas. Adicionar `primaryAction` prop em cada (Q5 BENCHMARKS — empty deve convidar ação).

Esforço: ~2h. ROI: padroniza H4 (consistency) cross-Jana.

### P0-4. PageHeader shared não usado em telas que poderiam (consistência)

**Telas afetadas:** Dashboard (h1 cru), Memoria (h1 cru), Chat (delega ao ThreadHeader que não é PageHeader).

**Sintoma:** `PageHeader` shared é usado em Custos/Governanca/Qualidade. Outras 4 reinventam com markup próprio. Quebra H4 (consistency).

**Fix:** trocar markup por `<PageHeader icon="..." title="..." description="..." action={...}/>`.

Esforço: ~1.5h. ROI: visual unificado Jana cross-rotas.

### P0-5. localStorage gravado fora de useEffect / sem prefixo `oimpresso.` consistente

**Telas afetadas:** Cockpit (`localStorage.setItem` em render), Governanca (correto, com prefixo `oimpresso.copiloto.governanca.*`), Qualidade (sem persistência), Custos (sem persistência).

**Sintoma:** Cockpit tem race condition; Qualidade/Custos perdem filtro entre sessões; ADR 0039 §4 inconsistente cross-tela.

**Fix:** padronizar em `useEffect` + chave `oimpresso.copiloto.<tela>.<campo>`.

Esforço: ~1h. ROI: previsibilidade + Q3 (recognition over recall).

---

## Notas de auditoria

- **Não auditadas explicitamente:** componentes `_components/FabJana.tsx` (delegado), `Components/cockpit/*` (já auditados em Whatsapp/Conversations 2026-05-07 — referência cruzada).
- **Severidade calibrada:** Wagner valoriza honestidade (`memory/regras-time.md`); scores não foram inflados. Cockpit 48 não é crueldade — é leitura objetiva de risco produção (mock reply hardcoded em rota live).
- **Próxima auditoria sugerida:** após batch de fixes P0-1 + P0-2 + P0-3 (estimado +20 pontos médios). Reauditar em 2026-06.
- **Charter pendente:** considerar criar `Memoria.charter.md` + `Cockpit.charter.md` antes de qualquer refactor — define Mission/Non-Goals (skill `charter-write` Tier A dormente, S4+).

---

**Tela que merece atenção primeiro:** **Cockpit.tsx** — reply mockado em produção é risco de confiança com cliente. Desativar rota ou plugar backend real antes de qualquer outra ação.

**Última atualização:** 2026-05-09
