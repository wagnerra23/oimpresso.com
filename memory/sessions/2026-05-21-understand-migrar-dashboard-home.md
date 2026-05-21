---
slug: 2026-05-21-understand-migrar-dashboard-home
title: "wagner-understand — migrar tela /home (dashboard pós-login UPOS) Blade → Inertia/React"
type: understand-decode
date: 2026-05-21
session: frosty-greider-83ab2f
spawned_by: claude-pai
status: ready-for-execution
blocking: ["decisao-ressuscitar-vs-deprecar", "criar-prototipo-ui-F1", "carater-soft-wrapper-vs-rewrite-completo"]
related_adrs: ["0093", "0094", "0101", "0104", "0107", "0114"]
related_skills: ["mwart-process", "mwart-comparative", "charter-first", "inertia-defer-default", "multi-tenant-patterns"]
related_refs: ["LICOES_F3_FINANCEIRO_REJEITADO.md", "PROTOCOLO-WAGNER-SEMPRE.md", "feedback-cowork-bundle-aplicar-inteiro.md"]
---

# Decodificação — "consegue migrar a tela dashboard? home"

## Pedido cru de Wagner (texto exato)

> "consegue migrar a tela dashboard? home"

Vírgula+interrogação sugere que Wagner está pensando em **UMA tela só** que tem dois nomes (`/home` rota Laravel + "dashboard" semântica de UX). Confirmado pelo código: `HomeController::index()` retorna `view('home.index')` que é o painel pós-login do UltimatePOS (welcome banner + 2 charts + widgets + filtros locação/data).

## Decodificação refinada

- **Objetivo principal:** migrar tela `/home` (Blade legacy `resources/views/home/index.blade.php`, 1.436 linhas) para Inertia/React `resources/js/Pages/Home/Index.tsx`, dentro do processo MWART canônico (ADR 0104 + 0107 + 0114).
- **Sub-objetivos atômicos:**
  1. Decidir caráter da migração: **(a) Soft wrapper** (precedente Caixa F6 #1288 — wrapper Inertia read-only reusando dados Blade existentes, sem rewrite UX) **vs (b) Rewrite completo F1→F4** (protótipo Cowork + critique + screenshot Wagner + Inertia novo).
  2. Decidir se ressuscita o módulo `memory/requisitos/Dashboard.md` (atualmente `status: ausente_branch_atual, action_required: decidir_ressuscitar_ou_deprecar`) — escrever SPEC + RUNBOOK obrigatórios antes do código.
  3. Criar `prototipo-ui/prototipos/home/` (não existe) — F1 Cowork OU F1-bypass-justificado com base em `home/index.blade.php` cópia literal.
  4. Reescrever `HomeController::index()` pra `Inertia::render('Home/Index', [...])` com `Inertia::defer()` nas props caras (charts, widgets, KPIs — todas batem DB).
  5. Criar `Pages/Home/Index.tsx` + `Index.charter.md` (precedente direto: `Pages/governance/Dashboard.charter.md` v2 com `<PageHeader>` + `<KpiGrid>` shared).
  6. Pest: multi-tenant scope (business_id biz=1 — ADR 0101 NÃO biz=4) + render Inertia + 5 endpoints AJAX (`getTotals`, `getProductStockAlert`, `getPurchasePaymentDues`, `getSalesPaymentDues`, `getCalendar`) preservados ou migrados pra Inertia partial reload.
- **Critério de pronto:**
  - Screenshot prod `https://oimpresso.com/home` mostra novo cockpit pós-login renderizado (Chrome MCP captura pós-merge).
  - `gh pr checks` 100% verde.
  - Pest verde (multi-tenant + smoke biz=1).
  - Larissa (biz=4) consegue fazer login → cair em `/home` novo → ver Vendas Hoje + Estoque Baixo sem console error.
  - `BRIEFING.md` do módulo Dashboard atualizado refletindo capacidade ressuscitada (skill `brief-update` auto-ativa).
- **Persona alvo:** **Larissa @ ROTA LIVRE biz=4** (cliente piloto, vestuário, monitor 1280px). Wagner usa biz=1 pra smoke, mas tela é cliente-facing — UX tem que servir Larissa.
- **Implícitos detectados:**
  - Está implícito que Wagner já decidiu **ressuscitar** (não deprecar) o módulo Dashboard — o pedido é "migrar", não "deprecar".
  - Está implícito que segue MWART (porque é a única via canônica desde ADR 0104).
  - Está implícito que segue protocolo Cowork bundle inteiro na primeira aplicação (precedente Wagner 2026-05-18) — se ainda não existe bundle Cowork pra "home/dashboard".
  - Está implícito que é UMA tela só (não inclui também `/calendar` que compartilha controller).
- **Ambiguidades a confirmar com Wagner ANTES de codar:**
  - **Caráter da migração:** Soft wrapper (rápido, ~1 PR, ~1 sessão, preserva UX UPOS) vs Rewrite completo F1→F4 (protótipo Cowork novo, ~5-7 PRs, ~2-3 sessões, cockpit V2 estado-da-arte)? Precedente recente em Caixa #1288 foi Soft. Precedente em governance/Dashboard foi rewrite. Quem decide é Wagner.
  - **Escopo:** Migrar SÓ a tela principal `/home` view, ou também os 5 endpoints AJAX (`/home/get-totals`, `/home/product-stock-alert`, `/home/purchase-payment-dues`, `/home/sales-payment-dues`, `/calendar`)? Endpoints AJAX hoje retornam HTML parcial — em Inertia viram partial reload via `<Deferred>`.
  - **Charts:** UPOS usa `App\Charts\CommonChart` (Echarts wrapper PHP). Em Inertia/React substituir por Echarts JS direto, ou outra lib (Recharts, Chart.js)? Validar com Wagner pra evitar trazer dep nova sem ADR.
  - **Module widgets pluggable:** `$module_widgets = $this->moduleUtil->getModuleData('dashboard_widget')` agrega widgets de outros módulos (Crm, Repair, etc) via slots de view Blade. Em Inertia, esse mecanismo Blade-only quebra — vai precisar arquitetura nova (ex: registry React de widgets por módulo). Decisão arquitetural = ADR nova provavelmente.
  - **Customer redirect:** `if ($user->user_type == 'user_customer') return redirect()->action([Crm\DashboardController::class, 'index']);` — preserva ou migra customer dashboard junto? (provavelmente preservar — fora do escopo).

---

## Regras protocolo aplicáveis (R1-R11)

| Regra | Aplica? | O que exige especificamente |
|---|---|---|
| **R1 — Smoke real** | ✅ obrigatório | Após merge: Chrome MCP em `https://oimpresso.com/home` + screenshot + read_console_messages. Como é shell-shared adjacency (afeta entrada do app), checar ao menos 3 rotas adjacentes (`/home`, `/sells`, `/financeiro/fluxo`) pra validar topnav + AppShellV2 não quebraram. |
| **R2 — Cópia literal design aprovado** | ✅ se F2 acontecer | Se Cowork gerar protótipo + Wagner aprovar screenshot, copiar literal — não slice. Se Soft wrapper, cópia literal do Blade legacy quanto possível. |
| **R3 — Workflow 3 fases (PRÉ+DURING+POST)** | ✅ obrigatório | **PRÉ:** ler `memory/requisitos/Dashboard.md` (já feito — ausente, exige decisão) + `LICOES_F3_FINANCEIRO_REJEITADO.md` + charter de referência `Pages/governance/Dashboard.charter.md` + ADR 0104+0107+0114. **DURING:** commit incremental por subfase MWART, `git push` WIP a cada ~30min, TodoWrite ativo. **POST:** PR + CI verde + docs canon + BRIEFING.md. |
| **R4 — Multi-tenant Tier 0 IRREVOGÁVEL** | ✅ obrigatório | Controller usa `session('user.business_id')` (canon UPOS — não `auth()->user()->business_id`). Toda query do dashboard scopada — `Transaction::where('business_id', $business_id)`, `BusinessLocation::forDropdown($business_id)`. Tests Pest cobrem leak (NoHardcodeBusinessIdInModulesTest pattern). |
| **R5 — PT-BR + economia** | ✅ sempre | Texto/commit/comentário PT-BR. Confirmar AMBIGUIDADES (4 itens acima) com Wagner ANTES de codar pra evitar retrabalho — escopo grande (1.436 linhas Blade + 7 endpoints + charts + widgets pluggable). |
| **R6 — biz=1 não biz=4 em smoke** | ✅ Pest | Pest com `biz=1` (ADR 0101). Larissa biz=4 só pra smoke visual pós-deploy, NÃO pra suite. |
| **R7 — Charter + visual-comparison ANTES de Edit Page** | ✅ obrigatório | Criar `Pages/Home/Index.charter.md` ANTES de Index.tsx (hook block-mwart-violation.ps1 BLOQUEIA Edit em .tsx sem RUNBOOK e charter). Criar `prototipo-ui/prototipos/home/` com `COMPARISON.md` + `critique-score.json` se F1 Cowork acontecer. |
| **R8 — Branch/worktree disciplina** | ✅ se worktree | Sessão atual em `.claude/worktrees/frosty-greider-83ab2f/`. Paths absolutos do worktree, não main. Cuidado junction `vendor/` Windows (catalogado proibições.md). |
| **R9 — Zero auto-mem privada** | ✅ sempre | Devolutiva escrita em `memory/sessions/` (git canon), NÃO em `~/.claude/projects/*/memory/`. |
| **R10 — Aprovação humana antes commit/push/merge** | ✅ "sim pode" explícito | Wagner aprova ESCOPO (Soft vs Rewrite) ANTES + aprova merge no final. Aprovação cobre escopo, não cada step (R11 calibra autonomia interna). |
| **R11 — Continuar autonomamente até desfecho dentro do escopo aprovado** | ✅ se Wagner disser "sim pode" | Após Wagner aprovar caminho (Soft OR Rewrite + N PRs), Claude executa do começo ao fim sem pausa pedindo "ok continuar?" — só pausa em violação de R1-R10 ou descoberta nova. |

---

## Inventário no projeto

| O que procurei | Onde achei | Status |
|---|---|---|
| HomeController legacy | `app/Http/Controllers/HomeController.php` | **634 linhas**, métodos `index()`, `getTotals()`, `getProductStockAlert()`, `getPurchasePaymentDues()`, `getSalesPaymentDues()`, `getCalendar()` |
| View Blade legacy principal | `resources/views/home/index.blade.php` | **1.436 linhas** (welcome banner + filtros + KPIs widgets + 2 charts + tabelas dues + módulo widgets pluggable) |
| Views Blade partials | `resources/views/home/partials/*.blade.php` | 8 partials (expense, invoice_due, net, purchase_due, total_purchase, total_purchase_return, total_sell, total_sell_return), 29-41 linhas cada |
| Calendar view | `resources/views/home/calendar.blade.php` | 144 linhas (fora do escopo provável) |
| Rotas | `routes/web.php:155-161` | 7 rotas top-level + `dashboard-configurator` resource em :593 + 3 notif routes em :660-673 |
| Page Inertia destino | `resources/js/Pages/Home/` | **NÃO EXISTE** — vai ser criado |
| Charter destino | `resources/js/Pages/Home/Index.charter.md` | **NÃO EXISTE** — criar antes do .tsx (hook bloqueador) |
| Protótipo Cowork | `prototipo-ui/prototipos/home/` ou `dashboard/` | **NÃO EXISTE** (nem no backup `_BACKUP-NAO-USAR/`) — F1 Cowork seria do zero |
| Requisitos canon | `memory/requisitos/Dashboard.md` | **stub status=ausente_branch_atual** — exige decisão ressuscitar/deprecar/adiar |
| Requisitos folder | `memory/requisitos/Dashboard/` | NÃO EXISTE (sem SPEC, sem RUNBOOK, sem BRIEFING) |
| Charter precedente direto (cockpit pattern) | `resources/js/Pages/governance/Dashboard.charter.md` | **v2 live 2026-05-09** — gold-standard `<PageHeader>` + `<KpiCard>` shared + 2 fileiras KpiGrid (`Constituição` cols=6 + `Saúde do ecossistema` cols=3). **REUSAR como template direto.** |
| Pattern Soft wrapper Inertia | PR #1288 (Caixa) — commit 68c4c9e73 | Wagner aprovou 2026-05-21 sabor Soft: wrapper Inertia read-only reusando tabela core. 139 linhas Controller + 252 linhas .tsx + 4 KPI cards. **Precedente válido pra Soft.** |
| Wave migração Financeiro F1→F4 | PRs #1266→#1289 (~15 PRs em 2 semanas) | Precedente Rewrite Completo (DRE backend + frontend + sidebar 12 entradas + Soft wrappers Fase 6). |
| Anti-padrões F3 catalogados | `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` | 6 meta + 15 técnicos. Mais relevantes pra Dashboard: `M-AP-1` (não ler Page existente), `M-AP-2` (commit "completo" sendo stub), `tenant middleware fantasma`, `auth()->user()->business_id` vs `session('user.business_id')`. |
| Module widget mechanism | `HomeController::index():199-208` | `$this->moduleUtil->getModuleData('dashboard_widget')` — mecanismo Blade-only de slots. **Quebra em Inertia** — pendente decisão arquitetural. |
| Custom redirect | `HomeController::index():67-70` | `user_type=user_customer` redireciona pra `Crm\DashboardController::index` — preserva. |
| Charts | `app/Charts/CommonChart.php` + `App\Charts\CommonChart` | Echarts PHP wrapper UPOS. Em Inertia: substituir por Echarts JS direto (verificar se já tem dep) ou Recharts. Decisão a confirmar Wagner. |
| Skill correlata | `.claude/skills/inertia-defer-default/` Tier B | Aplica diretamente — todas props do dashboard são caras (queries agregadas, charts, count, etc). |

**Status crítico:** se Wagner já aceita Soft wrapper, a tela está **0% migrada hoje** mas pode ficar pronta em ~1 PR + ~1 sessão (precedente Caixa). Se Rewrite completo, ~5-7 PRs + ~2-3 sessões (precedente DRE wave). **Não há nada parcialmente feito — escolha de caráter é greenfield em ambos os casos.**

---

## Pegadinhas conhecidas (cruzando proibições.md + LICOES_F3 + feedback canon)

- `[Tier 0]` **`business_id` global scope IRREVOGÁVEL** (ADR 0093) — usar `session('user.business_id')` canon UPOS, NÃO `auth()->user()->business_id` (LICOES_F3 M-AP-1).
- `[Tier 0]` **Middleware stack canon UPOS** = `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']` — NÃO usar `'tenant'` fantasma (LICOES_F3 PR rejeitado 2026-05-09).
- `[Tier 0]` **Permission gate** preservar: `auth()->user()->can('dashboard.data')` — se sem permissão, retorna view simplificada (linha 76-78 controller).
- `[Tier 0]` **Edit em `.tsx` sem charter ao lado** é BLOQUEADO pelo hook `block-mwart-violation.ps1` + CI `mwart-gate.yml`. Criar `Index.charter.md` ANTES.
- `[Tier 0]` **F2 BACKEND BASELINE sem Pest 5+ fixtures do entrypoint** = anti-padrão MWART. Endpoints `getTotals`/`getProductStockAlert`/etc precisam de Pest fixtures ANTES de refator.
- `[Tier 0]` **F4 QA com biz=4** (cliente Larissa) em vez de biz=1 = grave (ADR 0101). Suite Pest = biz=1. Larissa só pra smoke visual.
- `[Pegadinha cowork-bundle]` **Cherry-pick incremental CSS Cowork** = banido (Wagner 2026-05-18). Primeira aplicação = bundle inteiro (`resources/css/cowork-home-bundle.css` ou similar), customização vem depois em PRs separados.
- `[Pegadinha Windows]` **PowerShell 5.1 `Set-Content -Encoding utf8`** grava BOM → quebra PHP. Usar `[System.IO.File]::WriteAllText(..., UTF8Encoding($false))` ou Python `python -c` pra arquivos novos.
- `[Pegadinha Windows]` **`git worktree remove --force`** com junction `vendor/` apaga vendor do main. Remover junction explícita ANTES.
- `[Lição F3]` **Commit message "F3 completo"** sendo stub-mock = M-AP-2. Honestidade WIP: commitar incremental, sem marketing otimista.
- `[Lição F3]` **Mock `rand(0, 1500)`** em controller = banido (LICOES_F3). Sem dados, retornar `null`/`[]` + indicador UI "—", NÃO mock.
- `[Lição F3]` **Mutações NO-OP** (`return back()` vazio sem efeito) = banido. Se ação não está implementada, lança 501 explicitamente ou esconde botão.
- `[Lição F3]` **Models/Services inventados** (`FinancialEntry`, `BaixaService` que não existem) = banido. Sempre ler `Modules/*/Entities/` e `Modules/*/Services/` ANTES de assumir nome.
- `[Inertia::defer]` Toda prop do dashboard cai na categoria "cara" (sells_chart_1, sells_chart_2, module_widgets, stock_alert, payment_dues) — TODAS precisam `Inertia::defer()` + `<Deferred fallback={skeleton}>` no frontend. Validado D-14: 300ms → 50ms.
- `[Claim sem evidência]` **Declarar "funcionando" sem `curl -sv` + screenshot Chrome MCP** = banido (sessão 2026-05-17). Hook `block-claim-without-evidence.ps1` ativo.
- `[Post-merge UI smoke]` **Hook `post-merge-ui-smoke-required.ps1`** força Chrome MCP screenshot após merge de PR que toca UI files antes de declarar "pronto".
- `[Module widgets]` Mecanismo pluggable `getModuleData('dashboard_widget')` Blade-only QUEBRA em Inertia. Decisão arquitetural pendente — **provavelmente ADR nova** se Rewrite completo. Se Soft wrapper, manter `view('home.index')` original e renderizar dentro `<iframe>` ou `<div dangerouslySetInnerHTML>` (gambiarra, mas Soft).
- `[Dashboard.md status]` **`memory/requisitos/Dashboard.md` diz `ausente_branch_atual` + `action_required: decidir_ressuscitar_ou_deprecar`** — não escrever SPEC sem decisão Wagner registrada.

---

## Plug-points (onde EXATAMENTE plugar)

### Caminho A — Soft wrapper Inertia (precedente Caixa #1288)

| Camada | Arquivo | Mudança |
|---|---|---|
| Controller | `app/Http/Controllers/HomeController.php:65-214` | `index()` retorna `Inertia::render('Home/Index', [...]);` com `Inertia::defer()` em sells_chart_1/2/widgets/etc. Manter endpoints AJAX (`getTotals`, etc) como estão — Inertia partial reload aciona. |
| Page Inertia | `resources/js/Pages/Home/Index.tsx` (NEW, ~250 linhas precedente Caixa) | AppShellV2 + `<PageHeader title="Início" />` + `<KpiGrid>` 4-6 cards + 2 `<Deferred>` blocks pra charts + banner "Bem-vindo, {nome}". |
| Charter | `resources/js/Pages/Home/Index.charter.md` (NEW) | Mission/Goals/Non-goals/Tests reusando estrutura `governance/Dashboard.charter.md` v2. |
| CSS bundle | `resources/css/cowork-home-bundle.css` (NEW) | Se Wagner decidir bundle inteiro (precedente 2026-05-18). Caso contrário, reusar `cockpit.css` shared. |
| Routes | `routes/web.php:155` | Sem mudança (rota `/home` continua a mesma). |
| RUNBOOK | `memory/requisitos/Dashboard/RUNBOOK-home-index.md` (NEW) | Exigido por mwart-gate.yml. |
| SPEC | `memory/requisitos/Dashboard/SPEC.md` (NEW) | Substitui stub `Dashboard.md` — ressuscita módulo. |
| BRIEFING | `memory/requisitos/Dashboard/BRIEFING.md` (NEW) | 1-pager executivo skill `brief-update` Tier B auto-ativa. |
| Pest | `tests/Feature/Home/HomeIndexInertiaTest.php` (NEW) | Render Inertia + multi-tenant scope biz=1 + permission gate + customer redirect. |

### Caminho B — Rewrite completo F1→F4 (precedente DRE wave)

Tudo do Caminho A +:

| Camada | Arquivo | Mudança |
|---|---|---|
| Protótipo F1 | `prototipo-ui/prototipos/home/page.tsx` (NEW Cowork) | Gerado por [CC] Claude Design. |
| Critique F1.5 | `prototipo-ui/prototipos/home/critique-score.json` | Score ≥80 obrigatório (ADR 0114). |
| Screenshot F2 | Aprovação Wagner em `SYNC_LOG.md` | `[W2]: approved` antes de F3. |
| A11y F3.5 | `prototipo-ui/prototipos/home/a11y-report.md` | WCAG 2.1 AA sem `severity: critical`. |
| ADR nova (module widgets) | `memory/decisions/proposals/home-dashboard-widget-registry-react.md` | Decisão arquitetural pra substituir mecanismo Blade pluggable. |
| Echarts dep | `package.json` (se trazer echarts) | ADR nova justificando dep. |

---

## Tasks atômicas + estimate (fator 10x IA-pair ADR 0106 + margem 2x)

### Caminho A — Soft wrapper Inertia

| Task | Estimate | Bloqueia? |
|---|---|---|
| T0: Wagner confirma "Soft wrapper" + escopo | ~5min (conversa) | Bloqueia tudo |
| T1: Criar `memory/requisitos/Dashboard/` (SPEC + RUNBOOK + BRIEFING) | ~20min | Bloqueia hook mwart-gate |
| T2: Pest fixtures F2 `tests/Feature/Home/HomeIndexInertiaTest.php` (baseline) | ~25min | Bloqueia F3 |
| T3: Charter `Pages/Home/Index.charter.md` | ~15min | Bloqueia hook block-mwart |
| T4: Controller `HomeController::index()` → `Inertia::render` + defer | ~30min código + 15min Pest | Bloqueia frontend |
| T5: Page `Pages/Home/Index.tsx` (~250 linhas) | ~1h | Bloqueia TS check |
| T6: TS check + npm run build | ~10min | Bloqueia smoke |
| T7: Smoke local + Chrome MCP local Herd | ~15min | Bloqueia PR |
| T8: PR + `gh pr checks` verde | ~15min | Bloqueia merge |
| T9: Merge + deploy SSH | ~15min (Wagner aprova) | Bloqueia smoke prod |
| T10: Chrome MCP `oimpresso.com/home` + screenshot + 3 rotas adjacentes | ~15min | Define "pronto" |
| T11: Update `BRIEFING.md` (skill brief-update auto) | ~10min | Pós-merge |
| **TOTAL Caminho A** | **~4h sessão única** | — |

### Caminho B — Rewrite completo F1→F4

| Task | Estimate | Bloqueia? |
|---|---|---|
| T0: Wagner confirma "Rewrite" + escopo + decisão module widgets | ~15min | Bloqueia tudo |
| T1: ADR `home-dashboard-widget-registry-react.md` proposta | ~30min | Bloqueia F1 |
| T2: F0 BRIEF — entrada em `prototipo-ui/COWORK_NOTES.md` | ~10min | Bloqueia [CC] |
| T3: F1 [CC] Cowork gera protótipo `prototipos/home/page.tsx` | ~assíncrono Wagner (fora deste Claude) | Bloqueia F1.5 |
| T4: F1.5 [CD] critique-score.json ≥80 | ~30min (1 round refator se 70-79) | Bloqueia F2 |
| T5: F2 [W2] Wagner aprova screenshot | ~assíncrono (Wagner viu visual) | Bloqueia F3 |
| T6: SPEC + RUNBOOK + BRIEFING ressuscitando Dashboard | ~30min | Bloqueia F3 |
| T7: Pest F2 BACKEND BASELINE | ~30min | Bloqueia F3 |
| T8: Charter F3 + Controller + Page Inertia | ~2-3h | Bloqueia A11y |
| T9: F3.5 [CA] accessibility-review WCAG AA | ~30min | Bloqueia merge |
| T10: PR + CI verde + merge + smoke prod | ~1h | Define "pronto" |
| T11: BRIEFING.md + SYNC_LOG.md + HANDOFF.md | ~20min | Pós-merge |
| **TOTAL Caminho B** | **~2-3 sessões + 5-7 PRs** | — |

---

## Recomendação pro Claude pai

**Caminho recomendado:** **Caminho A (Soft wrapper Inertia)** como primeira tentativa, em UMA sessão única, ~4h. Justificativa:

1. **Precedente recente válido:** Caixa #1288 (mergeado HOJE 2026-05-21 — mesmo dia desta sessão) usou Soft wrapper e Wagner aprovou.
2. **Risco baixo:** read-only wrapper preserva mecanismo Blade legacy (module widgets pluggable) — se quebra, fallback rápido pro Blade.
3. **Critério de pronto rápido:** Larissa vê tela nova em prod em ~4h, não 2-3 sessões.
4. **F1 Cowork pode vir DEPOIS** se Wagner quiser cockpit V2 estado-da-arte — Soft wrapper agora destrava migração da entrada do app, e Rewrite Cockpit V2 vira PR D wave separado.
5. **Module widgets pluggable** — pendência arquitetural complexa — fica preservada no Soft (continua Blade-only) e adiada pra Rewrite.

**Mas só Wagner decide.** Confirmar ANTES de codar:

### O que confirmar com Wagner ANTES de codar (em UMA pergunta curta)

> "Wagner, pra migrar `/home`: **Caminho A Soft wrapper Inertia (~4h, precedente Caixa #1288)** ou **Caminho B Rewrite F1→F4 com Cowork (~2-3 sessões, 5-7 PRs)**? Soft preserva widgets pluggable Blade-only (mais rápido + risco baixo). Rewrite traz cockpit V2 estado-da-arte mas exige decisão ADR pro mecanismo de widgets em React + protótipo Cowork novo + critique + screenshot."

### Skills que DEVEM ativar (independente do caminho)

- `mwart-process` Tier A — 5 fases obrigatórias
- `mwart-comparative` Tier A — V4 gate visual + Claude Design plugin (se Caminho B)
- `charter-first` Tier A — bloqueia .tsx sem charter
- `inertia-defer-default` Tier B — props caras default defer
- `multi-tenant-patterns` Tier A — `session('user.business_id')` scope
- `commit-discipline` Tier A — 1 PR ≤300 linhas (Caminho B exige split)
- `brief-update` Tier B — pós-merge atualizar BRIEFING.md
- `smoke-prod-evidence` Tier B — Chrome MCP + screenshot pós-deploy

### ADRs canon relacionadas (já lidas no preflight)

- **ADR 0093** — Multi-tenant isolation Tier 0 IRREVOGÁVEL
- **ADR 0094** — Constituição v2 7 camadas + 8 princípios
- **ADR 0101** — Tests biz=1 nunca biz=4 cliente
- **ADR 0104** — Processo MWART canônico único caminho
- **ADR 0107** — Emendation 0104 visual-comparison gate F3
- **ADR 0114** — Protótipo-ui Cowork loop formalizado
- **ADR 0106** — Recalibração velocidade fator 10x IA-pair
- **ADR 0167** — (verificar relação se aplicável)
- **ADR nova (proposta)** — `home-dashboard-widget-registry-react.md` se Caminho B (decisão arquitetural pra mecanismo widgets pluggable em React).

### Próximo passo concreto pro Claude pai

1. Pergunta única ao Wagner (texto acima).
2. Aguardar resposta.
3. Spawn TodoWrite com tasks T0-T11 do caminho escolhido.
4. Executar com R11 ativa (autonomia interna até desfecho dentro do escopo aprovado).
5. R1 obrigatório pós-merge (Chrome MCP + screenshot prod + 3 rotas adjacentes).

---

**Devolutiva escrita.** Aguardando Claude pai retornar a Wagner com a pergunta de confirmação.
