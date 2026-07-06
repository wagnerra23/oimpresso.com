---
slug: financeiro-unificado-visual-comparison
title: "Financeiro — Comparativo visual da tela Visão Unificada"
type: visual-comparison
module: Financeiro
status: approved
date: 2026-05-09
last_updated: 2026-07-06
last_appended_ondas: [24, 25, "PR D quick wins", "diff-prod-2026-07-06"]
canon_reference: cowork-2026-05-09-protótipo "Visao Unificada" (Financeiro.html)
canon_secundario: tasks.jsx (inbox densa com atalhos teclado)
blade_source: n/a (greenfield — tela nova de unificação dos 4 estados)
inertia_target: resources/js/Pages/Financeiro/Unificado/Index.tsx
approved_by: wagner
approved_at: 2026-05-09
retro_from_pr: 349
related_adrs: [ui/0002, ui/0003, ui/0114, 0093]
---

# Comparativo visual — Financeiro · Visão Unificada (retroativo)

> **Tipo de tela:** lista densa estilo "inbox financeira" com KPIs no topo + drawer detalhe
> **Persona alvo:** Eliana [E] — financeiro escritório, monitor desktop ≥1024px, alta densidade, atalhos teclado
> **Refs:**
> - Blade legacy: ❌ **n/a** — tela nasce greenfield (4 telas separadas em legacy: contas-receber, contas-pagar, recebidas, pagas)
> - Canon Cockpit principal: protótipo Cowork "Visao Unificada" 2026-05-09 — KPIs + tabela + drawer + CmdK
> - Canon Cockpit secundário: [`tasks.jsx`](../_DesignSystem/ui_kits/cowork-2026-04-27/tasks.jsx) — inbox padrão (J/K nav + 1-clique baixa)
> - Charter: [`Index.charter.md`](../../../resources/js/Pages/Financeiro/Unificado/Index.charter.md)
> - ADRs: [ui/0002 plano original](adr/ui/0002-dashboard-unificado-4-estados.md), [ui/0003 amendment](adr/ui/0003-amendment-0002-visao-unificada-cockpit-v2.md)

> ⚠️ **Visual-comparison RETROATIVO** — escrito após a tela já estar em prod (PR #349 mergeada 2026-05-09 sem o artefato). Audit 2026-05-09 detectou ausência → este doc fecha a lacuna pra trazer a tela em conformidade com [ADR ui/0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) §F1.5.

## Resumo executivo

Eliana faz HOJE controle financeiro em 4 telas separadas (contas-receber, contas-pagar, recebidas, pagas) — precisa abrir 4 menus e perder contexto pra responder "quanto entra/sai esta semana". A tela Visão Unificada mistura tudo em uma view só com **5 KPIs no topo** (incluindo Saldo Previsto destacado), **filter chips** (Todas/Aberto/Receber/Pagar/Recebidas/Pagas/Atraso) clicáveis com drill-down, **tabela densa** estilo inbox com 1-clique baixa inline, **drawer detalhe** lateral, **CmdK palette** pra navegação por teclado. Persona Eliana é desktop fixo (sem mobile responsive em F1).

## Tabela comparativa — 8 dimensões

> **"Hoje (4 telas separadas)"** substitui "Blade legacy" — as 4 telas legacy CONTINUAM existindo em coexistência. Visão Unificada é uma view alternativa, não substitui.

### 1. Layout

| Aspecto | Hoje (4 telas separadas) | Canon Cockpit (protótipo Cowork) | Decisão MWART |
|---|---|---|---|
| Header | Tabela simples com search no topo | PageHeader Cockpit V2: ícone + título + descrição + action | PageHeader com `icon="coins"` + título "Financeiro · Visão unificada" + descrição dinâmica `{periodLabel} · {businessName}` + action (Conciliar + Novo) |
| Sidebar | UltimatePOS sidebar legacy (segue) | AppShellV2 sidebar com submenu Financeiro | AppShellV2 default; PR #358 adicionou entrada "Visão unificada" no submenu via DataController |
| Body grid | Tabela full-width única | KPI bar 5 colunas (lg) + filter bar + tabela full + drawer right 420px | KPI grid `grid-cols-5 lg:` + filter chips horizontal + tabela `flex-1` + Sheet drawer 420px |
| Topnav módulo | Submenu legacy: Contas a Receber / a Pagar / Caixa / Bancárias | Submenu Financeiro com 5 entradas + nova "Visão unificada" | DataController.modifyAdminMenu adicionou entrada (PR #358) |
| Footer | Ausente | Status bar `K palette / buscar  J/K navegar  selecionar` + indicador densidade | Footer fixo bottom com atalhos + densidade selector |
| Breakpoints | Responsive Bootstrap | `lg:grid-cols-5` (Eliana é desktop) | Desktop only ≥1024px (Persona Eliana — mobile vira US-FIN-025) |

### 2. Hierarquia visual

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Ação primária | Botão "+ Nova" pequeno cinza no topo | "+ Novo" primary destacado no header | `<Button size="sm">+ Novo</Button>` no header → leva pra `/unificado/novo` (picker Receber/Pagar) |
| Ações secundárias | Botões dispersos por linha | "Conciliar" outline + "Recebi/Paguei" inline 1-clique na linha | "Conciliar" `variant="outline"` no header + botão `✓ Recebi`/`✓ Paguei` inline em cada linha não-quitada |
| Hierarquia tipográfica | h2 + h3 mistas | PageHeader h1 24px + Sub 14px + KPI value `text-2xl semibold tabular-nums` + row 13px | h1 "Financeiro · Visão unificada" + sub `{period} · {biz}` + KPI label uppercase `text-xs` + value `tabular-nums` |
| Página título | "Contas a Receber" (cada tela) | "Financeiro · Visão unificada" + descrição dinâmica | Header dinâmico — bug PR #355 fix removeu hardcode "ROTA LIVRE" |
| KPI clicável | n/a (não tinham) | Cards drill-down filtram tabela | KpiCard `onClick` aplica tab filter (PR #358 fix) — gap vs ui/0002 §UX |

### 3. Densidade

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Espaçamento entre seções | `mb-3` (~16px) Bootstrap | `space-y-4` (16px) — denso pra inbox | `mt-4` entre seções (KPI bar / filter bar / tabela) |
| Row height | tabela Bootstrap ~52px | configurável: compact 32px / comfortable 44px / spacious 56px | Densidade configurável persiste em URL (`?densidade=compact|comfortable|spacious`) — adição vs ui/0002 |
| Card padding | panel-body ~15px | `p-4` (16px) | `p-4` em KpiCard |
| Line-height | 1.4 | 1.5 Tailwind | 1.5 |
| Gap KPIs | `col-md-3` margins | `gap-3` (12px) | `gap-3` no `grid-cols-5` |

### 4. Iconografia

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Sistema | FontAwesome 4 mistura | `lucide-react` 100% (R-DS-003) | `lucide-react` via `<Icon name="...">` |
| Ícones KPIs | nenhum | wallet, arrow-down-circle, clock, check-circle-2, arrow-up-circle | mesmo conjunto (PR #349) |
| Ícones tabela | nenhum | seta direcional ↑↓ (entrada/saída) | `↑` emerald-600 (entrada) / `↓` stone-500 (saída) — texto unicode (não lucide) |
| Cor | hardcoded `bg-success` etc | tokens shadcn (`text-emerald-600`, `text-rose-700`) | tokens emerald (entrada) / rose (saída) / amber (vencendo) / stone (neutro) |
| Tamanho | 14-16px arbitrário | 14-20px lucide | 16px KpiCard / 14px ações inline / 20px PageHeader |

### 5. Estados visuais

| Estado | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Empty (sem dados) | "Nenhum registro" centralizado pequeno | Empty state com CTA primário | "Nenhum lançamento em {periodLabel}." + Button "+ Adicionar primeiro lançamento" centralizado (PR #355) |
| Loading | Spinner Bootstrap | Sem skeleton (props vêm do controller) | Sem skeleton — Inertia entrega props prontas |
| Erro | Alert legacy | Flash session + back() | Flash session via Laravel `back()->with('error', ...)` |
| Sucesso 1-clique | redirect com flash genérica | Toast/flash imediato | Flash session "Titulo X marcado recebido" (back navigation) |
| Hover | sem | `hover:bg-stone-50/60` linha | Mesma — linha inteira clicável vira drawer |
| Selected (drawer aberto) | n/a | `bg-amber-50/40` na linha | Mesma — linha selected fica destacada |
| Atrasado | cor vermelha hardcoded | StatusPill `bg-rose-50 text-rose-700 border-rose-200` | Pill colorido por status: aberto/recebido/pago/atrasado/vencendo |

### 6. Atalhos teclado

| Atalho | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Busca | n/a | `/` | `/` foca search input |
| Palette | n/a | `Cmd+K` / `Ctrl+K` | `Cmd+K` abre CommandDialog |
| Navegação | n/a | `J` próx / `K` ant | Reservado em footer ("J/K navegar") — não implementado em F1 |
| Marcar pago/recebido | n/a | `_` (underscore) | Reservado em footer ("_ marcar pago/recebido") — não implementado em F1 |
| Tab filter | n/a | shortcut numérico ou click | Click em chip ou KPI |

### 7. Persistência de estado

| Aspecto | Hoje | Canon Cockpit | Decisão MWART |
|---|---|---|---|
| Filtros | session/POST form | querystring (URL state) | URL state via `router.get()` — bookmarkable |
| Densidade preferida | n/a | localStorage por user | URL state (não persiste sessão) — limitação F1; vira US futura |
| Coluna ordenada | session | querystring | n/a em F1 (sort não implementado, ordem default por vencimento desc) |
| Drawer aberto | n/a (era modal) | state local React | state local `selectedId` (não persiste em URL — drawer é volátil) |

### 8. Componentes shadcn/ui

| Componente | Uso | Variant |
|---|---|---|
| `<Button>` | Conciliar / Novo / 1-clique baixa / Limpar filtros | size sm + variant outline (secundárias) / default (primary) |
| `<Card>` + `<CardContent>` | wrapper tabela + KPIs (via `<KpiCard>`) | default |
| `<Input>` | search box (`placeholder="Buscar lançamento..."`) | default + atalho `/` |
| `<Sheet>` + `<SheetContent>` | drawer detalhe lateral | side="right" w-[420px] |
| `<CommandDialog>` + `<CommandInput>` + `<CommandList>` + `<CommandItem>` | CmdK palette | default — search by tab/conta/categoria |
| `<KpiCard>` (custom) | 5 KPIs do topo | tone success/danger/warning/default + `onClick` (drill-down PR #358) |
| `<PageHeader>` (shared) | header da tela | icon "coins" + title + description + action |
| `<StatusPill>` (custom inline) | status na tabela (Aberto/Recebido/Pago/Atrasado/Vencendo) | tone matching `statusTone()` |

## Décisions vs ui/0002 (formalizadas em ui/0003)

5 KPIs ao invés de 4 (+ Saldo Previsto destacado) · sem aging buckets · sem delta_pct mês anterior · sem combobox cliente · desktop only · pagination `limit(200)` simples · CmdK palette + densidade configurável + 1-clique inline (adições novas).

Ver [ADR ui/0003](adr/ui/0003-amendment-0002-visao-unificada-cockpit-v2.md) pra contexto e justificativa.

## Métricas a observar (post-launch)

| Métrica | Meta | Como medir |
|---|---|---|
| Cliques em KPI / total cliques de filtro | ≥70% | Logs Inertia/`router.get` com query params |
| Tempo abrir → primeira ação | <10s | Browser logger MCP |
| Adoção CmdK palette | ≥30% sessions | Event tracking `paletteOpen` |
| Reverter pra 4 telas legacy | <10% sessions após 30d | Comparar visits `/contas-receber` vs `/unificado` |

## Backlog (do charter)

US-FIN-021..028 — ver [Index.charter.md](../../../resources/js/Pages/Financeiro/Unificado/Index.charter.md) §"Backlog futuro". Cada um gated por sinal qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).

---

## Anexo: Ondas 24 + 25 (2026-05-25) — Plano de Contas + Insert manual

> **Status:** approved 2026-05-25 via Chrome MCP smoke prod biz=MARTINHO (PRs #1533 + #1538 mergeados).
> **Reabertura retroativa do gate F1.5 mwart-comparative V4** (G2 da auditoria 2026-05-25).

### Ondas 24/25 — diff visual vs canon

| Dimensão | Canon Cowork original | Implementação Onda 24+25 | Decisão |
|---|---|---|---|
| **Campo plano de contas no Edit** | Ausente (canon protótipo não previa) | `PlanoContaCombobox` searchable hierárquico DCASP filtrado por `kind` do título | **Adição funcional** — canon visual aceito porque DRE depende de plano classificado na origem |
| **Insert manual inline** | Canon previa botão "+" no header → tela separada `/novo` | `TituloCreateSheet` drawer inline reusando padrão `TituloEditSheet` | **Cumpre intent do canon** (sem navegação) — DropdownMenu existente "+ Novo título" virou wire pro Sheet |
| **Combobox hues semânticos por tipo DCASP** | n/a (canon não tinha plano de contas) | 6 tipos (receita, ativo, despesa, custo, passivo, patrimonio) via `style` inline oklch tokens | Fora dos tokens shadcn (ui:lint R1 escape). Hues coerentes com paleta financeira (receita=verde 145, despesa=rose 25, custo=amber 60, passivo=rose 25, ativo=verde 145, patrimonio=azul 240) |
| **Filtragem do combobox por `kind`** | n/a | `receivable` → receita+ativo (11 opções biz=MARTINHO) · `payable` → despesa+custo+passivo (17 opções) | Reduz cognitive load — Eliana não vê opções inválidas |
| **Numero sequencial R-/P-** | Canon previa `numero` opcional | `R-NNNNN` (receber) / `P-NNNNN` (pagar) com `lockForUpdate` business-isolado | R-FIN-002 idempotência forte |
| **2 botões vs Dropdown** | Decisão pendente | Opção A do design: Wagner aprovou 2 escolhas explícitas ANTES do form. Reusou `DropdownMenu` existente em vez de criar 2 botões novos no header | Menos poluição visual + mantém mental model Eliana |

### Validação Chrome MCP smoke prod (2026-05-25 biz=MARTINHO)

| Fluxo | Resultado |
|---|---|
| Dropdown "+ Novo título" 3 itens | ✅ |
| Novo recebimento → Sheet "Nova conta a receber" | ✅ Title + 6 labels + combobox `cr-plano` |
| Combobox receber filtragem | ✅ 11 opções tipos `ativo + receita` |
| Novo pagamento → Sheet "Nova conta a pagar" | ✅ placeholder "Despesa, custo ou passivo" |
| Combobox pagar filtragem | ✅ 17 opções tipos `passivo + custo + despesa` |
| Edit título #68042 (pagar) | ✅ combobox `ed-plano` filtrado correto, label "Plano de contas" entre Categoria/Vencimento |
| Console errors | 0 |

### PR D — Combobox keyboard navigation (G7 auditoria)

WAI-ARIA Combobox pattern adicionado pós-smoke:
- `↑` / `↓` navega lista
- `Enter` seleciona ativo
- `Esc` fecha
- `Home` / `End` primeiro/último
- `aria-activedescendant` linka input ↔ option pra screen reader
- Mouse hover atualiza activeIdx (consistência visual)
- `scrollIntoView({block:'nearest'})` mantém ativo visível em listas longas

### PR D — G12 audit log violação coerência

`UpdateTituloRequest::assertPlanoCoerente()` + `StoreTituloRequest::assertPlanoCoerente()` agora emitem `Log::warning('financeiro.plano_coerencia.violada', ...)` antes do abort(422) com payload completo (route, business_id, titulo_id, tipo_titulo, plano_id, plano_codigo, plano_tipo, user_id, ip). Permite alerta em dashboard se taxa > 0.1% (signal de bug UI ou tentativa tampering).

---

## Round 2026-07-06 — DIFF COMPLETO prod × protótipo aprovado (MEDIDO, não teorizado)

> **Gatilho:** Wagner viu o protótipo renderizado (light), aprovou a direção ("esse mesmo, gostei"), abriu a prod e estranhou ("bem diferente"). Pedido: "faça o diff completo".
> **Método:** o MESMO extrator DOM rodado nos dois lados (protótipo `financeiro-page.jsx` servido local + `oimpresso.com/financeiro/unificado` via Chrome logado) + inspeção da cascata CSS no repo + zoom de pixel. Deploy verificado EM DIA (main HEAD deployado; merges do dia tocaram 0 arquivos de UI).

### ✅ IDÊNTICOS (a implementação segue o protótipo)

Lentes Caixa/A receber/A pagar 3/3 · chips lifecycle + Só atrasados + Arquivados · período Dia→Personalizado 6/6 · KPIs 5/5 (hero SALDO PREVISTO + sparkline) · **tabela: as MESMAS 9 colunas na mesma ordem** (Vencimento→Valor, prod adiciona sort ⇅) · row-height 34px nos DOIS · fonte da tabela IBM Plex Sans nos DOIS · footer com totais + atalhos · ações por linha (Receber/Pagar ≙ Recebi/Paguei) · seletores Todas as contas / Todo o plano de contas.

### 🔴 BUG (P0+P1 = MESMA causa-raiz) — CORRIGIDO 2026-07-06

O botão primary "Novo título" do PageHeader é um `<button className="os-btn primary">` **cru** ([Index.tsx:1445](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx)) que dependia de `.os-btn.primary { background: var(--accent) }`. **Dois sintomas, uma causa:**
- **P0 GHOST** — a única regra `.os-btn.primary` do build é ESCOPADA `.fin-cowork .os-btn.primary` ([bundle:1549](../../../resources/css/cowork-canon-financeiro-bundle.css)) e o botão renderiza FORA do wrapper `.fin-cowork` (medido: bg transparente; se estivesse dentro, bg seria o accent, não transparente). Regra nunca casa → botão sem fundo. Mesma classe do furo do Portal Sheet (Index.tsx:1844).
- **P1 MAGENTA** — quando `var(--accent)` resolvia, herdava o `--accent` do [AppShellV2:381](../../../resources/js/Layouts/AppShellV2.tsx), que é **tweakável ao vivo** pelo TweaksPanel (slider "Tom do accent", `onHue=setAccentHue`, renderizado sempre — feature, não bug). O browser do Wagner tinha `accentHue=330` (magenta) do próprio slider. Por ADR 0190 o **primary é roxo 295 FIXO**, então nunca deveria seguir o `--accent` tweakável.

**Correção NÃO é clampar o shell** (isso mataria a personalização de accent + as bolhas WhatsApp que consomem `--bubble-me`). É travar o **primary** no 295 canon, imune ao escopo E ao slider: `style={{ backgroundColor:'oklch(0.55 0.15 295)', borderColor:'oklch(0.45 0.15 295)', color:'oklch(0.99 0 0)' }}` inline no botão (== o que `FinanceiroPrimaryButton.tsx` já fazia). **Provado ao vivo** (2026-07-06): injetar esse estilo no botão da prod → renderizou bloco roxo sólido com texto branco (antes: transparente). Fix landado em Index.tsx:1445.

> **Nota sobre o resto da sessão do Wagner:** os OUTROS accents magenta que ele vê (sidebar ativo, bolhas) são o **slider dele em 330** — personalização legítima, não bug. Pra voltar ao roxo canon no shell inteiro: arrastar "Tom do accent" pra ~295 no TweaksPanel (ou limpar `localStorage['oimpresso.cockpit.tweaks.accentHue']`). Só o **primary** é canon-locked (agora 295 sempre).

### 🟡 Divergências de DESIGN (decisão, não bug)

3. **Pills de status:** protótipo = pílula full-round (9999px), SEM borda, saturada (bg oklch .93/.11, fg .55/.17) · prod = retângulo 4px COM borda 1px, dessaturado (bg .97/.02, fg .51/.12) + hues deslocados (verde 150→162 · vermelho 25→18 · âmbar 80→95). É a diferença mais visível na tabela. Qual é o canon?
4. **Seletor de data-campo:** protótipo = segmented "FILTRAR POR Vencimento·Emissão·Pagamento·Competência" · prod = `<select>` dropdown compacto.
5. **Fonte do body:** prod = ui-sans-serif (só a tabela usa IBM Plex) · protótipo = IBM Plex global.
6. **Tema default:** protótipo = dark (decisão [W] 2026-06-03 no próprio protótipo) · prod = light. Pendente Wagner cravar o default da prod.

### 🔵 Prod À FRENTE do protótipo (não é regressão)

Botão flutuante **"Resolver N"** (anomalias) · ⌘K palette · J/K navegar · B favoritar linha · sorting ⇅ nas colunas.

### Anexos de evidência (sessão 2026-07-06)

Extractor JSON dos dois lados + probes de cascata + zoom do botão — na transcrição da sessão. localStorage do browser [W]: `oimpresso.cockpit.tweaks.accentHue=330` (confirmado ao vivo).
