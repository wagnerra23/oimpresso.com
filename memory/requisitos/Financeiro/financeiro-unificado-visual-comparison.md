---
slug: financeiro-unificado-visual-comparison
title: "Financeiro — Comparativo visual da tela Visão Unificada"
type: visual-comparison
module: Financeiro
status: approved
date: 2026-05-09
last_updated: 2026-07-07
last_appended_ondas: [24, 25, "PR D quick wins", "diff-prod-2026-07-06", "inventario-regioes-2026-07-07"]
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

---

## Round 2026-07-07 — INVENTÁRIO POR REGIÃO (protótipo × produção, ancorado linha a linha)

> **Gatilho [W]:** *"divida a página em etapas — header, page header, meio, canto, footer — e descreva cada componente modificado e sua diferença total entre protótipo e produção. coloque isso no meu protocolo."*
> **Método (formalizado na Fase 1 do [RUNBOOK-aplicar-prototipo](../../../prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md)):** 6 regiões × **199 comparações** componente-a-componente. Toda linha com **âncora dupla** (`arquivo:linha` do protótipo E da produção, lidas de `origin/main` @ `386dce0638` — SHA confirmado RODANDO em produção via SSH Hostinger 2026-07-07 16:28 UTC) + **medição DOM computada ao vivo** (Chrome MCP em `oimpresso.com/financeiro/unificado`, logado biz=1) pros valores de cor/raio/fonte. Workflow 6 agentes-região + spot-check manual dos achados fortes.

### Placar geral (199 comparações)

| Veredito | Qtde | Leitura |
|---|---:|---|
| **IDENTICO** | 64 | núcleo do design replicado fielmente (título/subtítulo, 3 lentes, primary roxo 295, 8 abas na ordem, 5 KPIs+labels+alarme, presets período, chips lifecycle, 9 colunas, row 34px, ✓ Recebi/Paguei, barra lateral 3px, color-mix 22/28 DirIcon, footer summary, densidade 2 modos) |
| **DIVERGE** | 70 | maioria micro-token/microcopy; **~14 relevantes** (tabelas abaixo) |
| **PROD_A_FRENTE** | 32 | produção evoluiu além — sort ⇅, bulk 5 ações, OCR OpenAI Vision real, RBAC Spatie, sparkline 30d real, DeltaBadge %, favoritos, boleto Inter, KV 17 campos WR, paginação. **Nunca regredir pro protótipo.** |
| **SO_PROTOTIPO** | 18 | faltam na produção — candidatos a aplicar (lista priorizada abaixo) |
| **SO_PRODUCAO** | 15 | features novas sem par no protótipo (Arquivados, chips aprovação, TituloEditSheet, ⌘K grupo Ações, B/N/P/Space) |

### 🔴 Achados P0/P1 (cada um re-verificado à mão além do agente)

| # | Achado | Evidência | Ação |
|---|---|---|---|
| **P0** | **Copy de UI renderiza "±R$ [redacted Tier 0]"** na lente Conciliação do drawer — usuário vê o placeholder de redação na tela. Artefato do `git filter-repo` (redação BRL 2026-06-08) que reescreveu string de UI; protótipo diz o valor da tolerância | prod `Index.tsx:2324` · proto `financeiro-page.jsx:1576` | fix 1-linha (restaurar tolerância literal do protótipo) |
| **P1** | **Toggles "Só atrasados"/"Arquivados" LIGADOS não têm NENHUM feedback visual** — JSX aplica só a classe `on`; não existe regra CSS `.fin-filter-toggle.on` (só `.on.warn`, morta — o sufixo `warn` nunca é adicionado). No protótipo, ligado = vermelho neg-soft | prod `Index.tsx:1664`+`1682`, CSS `fin-cowork.css:379`/`bundle:4528` · proto `financeiro-page.jsx:632` | fix CSS pequeno (adicionar regra `.on` ou o sufixo `warn` no JSX) |
| **P1** | **KPI clicado sem anel de lente ativa** (`fin-stat-on` inexistente em `resources/`) + cards clicáveis **sem hover de elevação/cursor** + **sem dots verde/vermelho** entrada×saída | proto `financeiro-page.jsx:398` + `fin-boletos.css:65-68,77` · prod grep vazio | aplicar CSS do protótipo (só visual) |
| **P1** | **`ClienteCombobox` ÓRFÃO** — componente pronto (busca server-side debounce 300ms, WAI-ARIA) com **zero imports**; `TituloCreateSheet` usa `<Input>` cru | prod `_components/ClienteCombobox.tsx` (0 imports) · `TituloCreateSheet.tsx:123` · proto `financeiro-page.jsx:1288` | ligar componente (fecha US-FIN-024) |

### Região 1 — PageHeader + SubNav (10 ID · 7 DIV · 3 PAF · 2 SP)

| Veredito | Componente · propriedade | Protótipo → Produção | Âncoras (proto · prod) |
|---|---|---|---|
| DIVERGE | SubNav · label aba | "Impostos **&** obrigações" → "Impostos **e** obrigações" (único label divergente de 8) | `data.jsx:45` · `financeiroMenu.ts:63` |
| DIVERGE | Overflow ··· · posição | header (ao lado do primary) → fim da barra de abas ⋯ (ADR 0313 declara) | `financeiro-page.jsx:182` · `PageHeaderTabs.tsx:246` |
| DIVERGE | Overflow ··· · itens | renames: "Fechamento mensal"→"Fechamento" · "Exportar CSV"→"Exportar XLSX/PDF" · "Ler boleto (OCR)" migrou pro dropdown do primary; perde separadores visuais | `financeiro-page.jsx:229` · `Index.tsx:1541-1546` |
| SO_PROTOTIPO | SubNav · separador "·" hub/ghost | proto distingue hubs de sub-telas com `·` (`ph-nav-sep`) → prod barra uniforme | `app.jsx:394` · — |
| SO_PROTOTIPO | SubNav · ícones 12px por aba + opacity 0.66 ghosts | proto hierarquiza; prod abas texto puro uniformes | `app.jsx:390,401` · `PageHeaderTabs.tsx:225-228` |
| DIVERGE | Lentes · micro-tokens | radius 7px→6px · altura 30px→28px (h-7) · ativo peso 600→500+shadow-sm | `financeiro.css:1438,1456` · `Index.tsx:1465,1478` |
| PROD_A_FRENTE | Primary · comportamento | botão→sheet direto no proto; prod = dropdown 3 itens (Novo recebimento/pagamento/OCR — decisão [W] 2026-05-21) + 9 ghosts legacy no ⋯ + auto-promote + ARIA tablist | `financeiro-page.jsx:227` · `Index.tsx:1515-1522`, `financeiroMenu.ts:65-73` |
| IDENTICO (destaques) | título+sufixo · subtítulo dinâmico · 3 lentes (labels/ordem) · primary roxo `oklch(0.55 0.15 295)` · 8 abas mesma ordem · aba ativa roxa sublinhada | — | `Index.tsx:1454-1456,1505` medido DOM ao vivo |

### Região 2 — KPI strip (9 ID · 7 DIV · 4 PAF · 3 SP · 3 SPd)

| Veredito | Componente · propriedade | Protótipo → Produção | Âncoras |
|---|---|---|---|
| SO_PROTOTIPO | anel de lente ativa (`fin-stat-on`) | ver P1 acima | `financeiro-page.jsx:398` · — |
| SO_PROTOTIPO | hover elevação (`translateY(-1px)` + sh-2) | ver P1 | `fin-boletos.css:65` · — |
| SO_PROTOTIPO | dots verde/vermelho entrada×saída no label | ver P1 (Caso 08) | `fin-boletos.css:77` · — |
| DIVERGE | sparkline · cor por sinal | tokens `var(--pos)/var(--neg)` (flipam com tema) → **hardcoded** `oklch(0.78 0.13 145)/oklch(0.65 0.18 25)` (pensados pro hero warm-dark ANTIGO; hero hoje é claro) | `financeiro-page.jsx:412` · `Index.tsx:508` |
| DIVERGE | sparkline · linha-base | `stroke var(--text-4)` y fixo 24 → hardcoded `oklch(0.65 0.01 80)`, y = saldo real do 1º dia (melhor semanticamente, pior token) | `financeiro-page.jsx:424` · `Index.tsx:576` |
| DIVERGE | grid/card · literais | `1.5fr` gap 12 radius 12px → `minmax(260px,1.6fr)` gap 8 radius 8px | `fin-boletos.css:46-48` · `fin-cowork.css:117-127` |
| DIVERGE | microcopy hints | "vencida há 3 **dias**"→"3**d**" · "próx. **dd/mm**"→"**10 mai**" · fallback "nada em aberto"→"0 títulos" · "1 título**s**" sem flexão | `financeiro-page.jsx:438-454` · `Index.tsx:932-951` |
| PROD_A_FRENTE | sparkline 30d REAL + tooltip por ponto · DeltaBadge % 5 KPIs (US-FIN-023) · pendente colorido valor cheio · clique lifecycle multi-select + `<button>` semântico | — | `Index.tsx:598,585,881,902-942` |

### Região 3 — Barra de filtros (11 ID · 12 DIV · 3 PAF · 2 SP)

| Veredito | Componente · propriedade | Protótipo → Produção | Âncoras |
|---|---|---|---|
| DIVERGE | **toggle ON sem CSS** (Só atrasados/Arquivados) | ver **P1** acima | `financeiro-page.jsx:632` · `Index.tsx:1664` |
| DIVERGE | FILTRAR POR · cor do ativo | `var(--accent)` roxo → `text-foreground` neutro | `financeiro.css:1523` · `Index.tsx:1584` |
| DIVERGE | chip "Pagas" · hue | 240 azul → 295 roxo (comentário prod declara decisão v4 deliberada; canon do protótipo diz 240 — **decidir [W]**) | `financeiro-page.jsx:596` · `Index.tsx:241` |
| DIVERGE | PeriodBar · setas ‹ › | navegam pela unidade do preset + disabled em "Tudo" → sempre-mês, nunca desabilita | `financeiro-page.jsx:699,68-75` · `FinPeriodBar.tsx:96-120` |
| DIVERGE | PeriodBar · label central | "Jul 2026"/"Semana 1–7 jul"/"Todo o período" (por preset) → "Julho 2026" fixo | `financeiro-page.jsx:700,77-91` · `FinPeriodBar.tsx:101,126` |
| DIVERGE | Personalizado · layout | SUBSTITUI o nav, separador "até" → ADICIONA após presets, separador "–" (+ botão × limpar, PROD_A_FRENTE) | `financeiro-page.jsx:693` · `FinPeriodBar.tsx:171,182` |
| DIVERGE | busca · aplicação + "/" | live-onChange, "/" abre CmdK → Enter-para-aplicar (backend paginado), "/" foca o campo + hint `kbd /` visível | `financeiro-page.jsx:666,1939` · `Index.tsx:1763-1768,1338` |
| DIVERGE | multi-select contas · trigger "on" | borda/cor accent quando há seleção → só muda text-muted→foreground (destaque perdido); popup sem Escape (SP) | `financeiro.css:1558`,`fpage:561` · `Index.tsx:416,382` |
| DIVERGE | densidade · ícones/labels | SVG linhas + "Compacta/Confortável" → glifos ◰/▦ + "Compacto/**Médio**" | `financeiro-page.jsx:657` · `Index.tsx:1780-1783` |
| SO_PROTOTIPO | chips · botão "Limpar" | CSS pronto na prod (`fin-filter-clear`) mas JSX não usa — regra morta | `financeiro-page.jsx:626` · `fin-cowork.css:342` |
| DIVERGE | plano de contas · widget | `<select>` nativo optgroup mock → Radix Select flat com árvore DCASP real (regra `ds/no-native-select` — intencional) | `financeiro-page.jsx:642` · `Index.tsx:1744-1752` |
| PROD_A_FRENTE | 🗄 Arquivados · chips workflow aprovação (⏳✓✗) · kbd "/" no campo | — | `Index.tsx:1682,281,1695-1727` |

### Região 4 — Tabela (9 ID · 16 DIV · 6 PAF · 2 SP · 1 SPd)

| Veredito | Componente · propriedade | Protótipo → Produção | Âncoras |
|---|---|---|---|
| DIVERGE | colunas · posição do DirIcon | DEPOIS da data → ANTES da data (demais 9 colunas ordem idêntica) | `financeiro-page.jsx:875` · `Index.tsx:1808` |
| DIVERGE | DirIcon · glifo | SVG `ArrowDownLeft` ↙ entrada, container rounded-**full** → texto "↘"/"↗" bold, container rounded 4px 22×22 (medido DOM: box + sombra color-mix 28%) | `financeiro-page.jsx:727` · `Index.tsx:1066` |
| DIVERGE | **StatusPill · forma/peso** | `rounded-full` px-2 font-**semibold** → `rounded` (4px) px-1.5 font-**medium** (dot e fio 1px ~22% JÁ convergiram) | `financeiro-page.jsx:121` · `Index.tsx:468` |
| DIVERGE | **StatusPill · cores** | tokens DS `--pos/--neg/--warn` (verde **150** sat `0.95 0.075`) → tokens `success/destructive`-soft (verde **162** dessat `0.97 0.02`) e **`vencendo` = amber-50/800/200 Tailwind cru fora dos tokens warning** | `financeiro-page.jsx:111` · `Index.tsx:454-455`, `tokens/_generated-inertia-theme.css:44` |
| DIVERGE | Vencimento · sublabel | inline + relativo "há 3 dias/hoje/amanhã" + sufixo ano → empilhado 2 divs + fixo "em atraso"/"vencendo" | `financeiro-page.jsx:765` · `Index.tsx:1085` |
| DIVERGE | Valor · cor da SAÍDA | **neutra** (decisão explícita do proto: saída não grita) → `text-destructive` vermelha — **decidir [W]** | `financeiro-page.jsx:816` · `Index.tsx:1137` |
| DIVERGE | Categoria · dot | neutro `var(--border)` → semântico verde/âmbar por direção (comentário admite redundância com DirIcon) | `financeiro-page.jsx:797` · `Index.tsx:1104` |
| DIVERGE | ✓ Recebi/Paguei · estilo | ghost roxo accent sem borda → outline neutro com borda | `financeiro-page.jsx:828` · `Index.tsx:1145` |
| DIVERGE | linha selecionada | wash roxo accent 14% + barra inset accent → âmbar `bg-amber-50/40` sem barra | `fin-boletos.css:311` · `Index.tsx:1033` |
| DIVERGE | select-all · indeterminate | tri-state nativo → Radix sem indeterminate (seleção parcial não sinalizada) | `financeiro-page.jsx:867` · `Index.tsx:1797` |
| DIVERGE | agrupador de data | rótulo temporal relativo "· há N dias · hoje" → contagem "N lançamento(s)" | `financeiro-page.jsx:897` · `Index.tsx:1831` |
| SO_PROTOTIPO | linha liquidada · opacity-55 | proto esmaece a linha paga inteira → prod só o bg do DirIcon /0.6 | `financeiro-page.jsx:751` · `Index.tsx:1033` |
| SO_PROTOTIPO | menu ⋯ per-row + chip PIX/Boleto (`fin-cob-tag`) | não portados (ações vivem no drawer/bulk) | `financeiro-page.jsx:835,786` · — |
| PROD_A_FRENTE | sort ⇅ 5 colunas · paginação completa · badges fav/conferido/comentários · ApprovalPill · ícones reais Forma/Conta · bulk Cancelar/Plano lote | — | `Index.tsx:358,1876,1092,1136,1111,1125` |
| nota | densidade "spacious" removida = corte deliberado [W] Onda 12.6, NÃO regressão | `financeiro-page.jsx:101` · `Index.tsx:294` |

### Região 5 — Footer + ⌘K + atalhos (7 ID · 13 DIV · 5 PAF · 9 SPd · 2 SP)

| Veredito | Componente · propriedade | Protótipo → Produção | Âncoras |
|---|---|---|---|
| DIVERGE | footer · "Total entrada" | valor verde `var(--pos)` → neutro (no modo bulk a prod PINTA verde — só o summary diverge) | `financeiro-page.jsx:970` · `Index.tsx:2731`, `fin-cowork.css:470` |
| DIVERGE | footer container | radius 12px bottom-0 → 6px bottom:16px (sticky+offset = decisão [W] 2026-05-20 anti-sidebar) | `financeiro-page.jsx:964` · `fin-cowork.css:439` |
| DIVERGE | bulk · labels | "Liquidar selecionados"→"Marcar pago/recebido (N)" · "Editar em lote"→Categorizar/Plano lote (2 fluxos reais) · "Exportar"→"Exportar CSV" | `financeiro-page.jsx:988-999` · `Index.tsx:2665-2715` |
| DIVERGE | "/" · destino | abre CmdK → foca busca (prod é o mais literal com o hint "buscar") | `financeiro-page.jsx:1939` · `Index.tsx:1338` |
| DIVERGE | ⌘K · placeholder/empty | "Buscar lançamento, cliente, NFe, categoria…" / eco da query → "…contraparte ou ação…" / "Sem resultados." | `financeiro-page.jsx:1683,1690` · `Index.tsx:2619,2621` |
| DIVERGE | Resolver · contextualidade | "Resolver: **{título}**"/"Guia de divergências" por row → label fixo "? Resolver" | `financeiro-output.jsx:121` · `FinTroubleshooter.tsx:271` |
| SO_PROTOTIPO | ⌘K · rodapé (↑↓ ↵ + "N de M") | não portado | `financeiro-page.jsx:1711` · — |
| SO_PROTOTIPO | empty state · CTA "Ver todo o período" | escape do filtro de período não portado (prod oferece "+ Adicionar primeiro lançamento") | `financeiro-page.jsx:946` · `Index.tsx:1864` |
| PROD_A_FRENTE | atalhos DE VERDADE: J/K global+setas · Space marcar · B favoritar · N/P novo · R dupla semântica · Esc em cascata · guard inEditable uniforme (proto só PROMETE ␣/J-K no footer e não implementa) · ⌘K grupo Ações 8 itens · Resolver também no footer · contador ★ | — | `Index.tsx:1336-1412,2622-2643,2741-2743` |

### Região 6 — Drawer + Sheets (18 ID · 15 DIV · 11 PAF · 7 SP · 2 SPd)

| Veredito | Componente · propriedade | Protótipo → Produção | Âncoras |
|---|---|---|---|
| DIVERGE | **lente Conciliação · empty copy** | ver **P0** ("±R$ [redacted Tier 0]" renderizado) | `financeiro-page.jsx:1576` · `Index.tsx:2324` |
| DIVERGE | Aprovação · motivo rejeição | input inline + Confirmar/Voltar → `window.prompt` nativo (UX inferior) | `financeiro-ops.jsx:280` · `Index.tsx:2470` |
| SO_PROTOTIPO | Aprovação · desfazer/reenviar | estados terminais com ação + "por Wagner · dd/mm" → pills estáticas (US-ADR: workflow+audit) | `financeiro-ops.jsx:294,302` · `Index.tsx:2488-2493` |
| SO_PROTOTIPO | Anexos · drag&drop + multiple | drop-zone + `multiple` → botão 1-arquivo-por-clique | `financeiro-ops.jsx:203-205` · `FinAnexosPanel.tsx:84-88` |
| SO_PROTOTIPO | Anexos · seeds automáticos NFe/DANFE/comprovante | mock "do sistema" com lock → só lista o backend (US-ADR: toca NfeBrasil) | `financeiro-ops.jsx:165` · `FinAnexosPanel.tsx:68` |
| SO_PROTOTIPO | **TituloCreateSheet · campo cliente** | `FinClienteCombobox` autocomplete → `<Input>` cru (ver **P1**; ClienteCombobox pronto e órfão) | `financeiro-page.jsx:1288` · `TituloCreateSheet.tsx:123` |
| DIVERGE | FinBaixaSheet · forma pré-selecionada | PIX/Boleto pelo canal/direção do título → vazio "— escolha —" | `financeiro-ops.jsx:69,75` · `FinBaixaSheet.tsx:58,174` |
| SO_PROTOTIPO | FinBaixaSheet · resumo footer | "Baixa parcial/Quitação total · R$X" → ausente | `financeiro-ops.jsx:149` · — |
| SO_PROTOTIPO | OCR · fallback colar linha digitável | parse Febraban manual sem foto → ausente (hint admite "PDF não suportado") — **pergunta [W]** | `financeiro-ops.jsx:368` · `FinOcrBoletoSheet.tsx:223` |
| DIVERGE | microcopy | "Registrar recebimento"→"Receber lançamento" · "Adicionar ao caixa"→"Criar recebimento/pagamento" · "Criar título"→"Cadastrar título" · venc default +7d→hoje · "Emitido→"→"Lançado→" | ops/page vs Sheets (âncoras no JSON da sessão) |
| PROD_A_FRENTE | OCR REAL Vision+confiança+custo+cache · KV 17 campos WR (proto 5) · RBAC `financeiro.titulo.aprovar` · anexos download/soft-delete+confirm · boleto Inter Gerar/Copiar · TituloEditSheet dedicado · Ver NFe condicional funcional · badge "!" anomalia na aba IA | — | `FinOcrBoletoSheet.tsx:89-296`, `Index.tsx:2262-2278,1169,2543-2556,2519,2146` |

### Região 7 — Tema/Shell (medido DOM ao vivo 2026-07-07)

| Veredito | Componente | Protótipo → Produção |
|---|---|---|
| DIVERGE | fonte do body | IBM Plex global → `ui-sans-serif` no body, IBM Plex **só na tabela** (medido: `font_body` vs `font_tabela`) |
| RESOLVIDO [W] | tema default + sidebar | protótipo = dark + sidebar dark → **prod light + sidebar LIGHT = DEFINITIVO** ([W] 2026-07-07: "sidebar permanece light, foi superado e ficou como está hoje; revogue as anteriores") — dark-mode disponível funciona legível (medido: body `oklch(0.137)`, texto `oklch(0.984)`, 2/1773 elementos brancos) |
| IDENTICO | primary universal | `oklch(0.55 0.15 295)` medido computado no subnav ativo E no botão primary |

### Correções a rounds anteriores (o doc estava impreciso)
- Round 2026-07-06 dizia pill do protótipo "**SEM borda**" — errado: `StatusBadge` TEM fio `1px color-mix 22%` (`financeiro-page.jsx:122`). O delta real das pills hoje = **raio** (full→4px) + **peso** (semibold→medium) + **saturação/hue** (150 sat → 162 dessat) + `vencendo` fora dos tokens.
- "Seletor de data-campo `<select>`" — **FECHADO** (#3886, segmented confirmado DOM ao vivo). "Rótulo Recebi/Paguei" — **FECHADO** (#3887).

### Fila de aplicação sugerida (valor ÷ esforço, tudo só-visual salvo marcado)
1. **P0 copy Conciliação** (1 linha) → 2. **CSS toggles `.on`** → 3. **ClienteCombobox no CreateSheet** (US-FIN-024) → 4. **CSS KPI** (anel+hover+dots) → 5. **motivo rejeição inline** → 6. **forma pré-selecionada na baixa** → 7. pills raio/saturação (**decidir [W] antes**: protótipo full+saturado vs atual) → 8. Valor saída neutro vs vermelho (**decidir [W]**) → 9. tokens do sparkline (hardcoded→`--pos/--neg`) → 10. drag&drop anexos. **US-ADR (backend):** seeds NFe · desfazer/reenviar aprovação. **Nunca aplicar:** tudo marcado PROD_A_FRENTE.
