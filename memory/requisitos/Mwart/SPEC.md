---
module: Mwart
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
na_justified:
  D5: "Mwart é meta-processo de governança (enforcement do caminho canônico Blade→Inertia — ADR 0104) — NÃO é módulo de features cliente-facing. Não há biz=4 ROTA LIVRE consumindo features Mwart; consumidores são `Modules/*` que migram para Inertia. D5 cliente real não aplica por design."
  D4.b: "Mwart não tem state machine FSM (ADR 0143). É processo administrativo de gating (skill Tier A + hook PreToolUse + CI workflow), não fluxo de negócio com transições Eloquent. D4.b FSM canônica N/A."
na_justified_v3:
  D6.a: "Mwart é meta-processo (enforcement skill + hook + CI) — sem Controllers Inertia próprios. Inertia::defer N/A por ausência de telas geradas pelo módulo."
related_adrs:
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0106-recalibracao-velocidade-fator-10x-ia-pair"
  - "0153-module-grade-rubrica-v1"
  - "0154-module-grade-v2-na-justificado"
  - "0155-module-grade-v3-sub-dimensoes-gate-ci"
  - "0156-module-grade-v3-errata-otel-helper-na-justified"
---

# Especificação funcional — MWART (processo canônico)

> **N/A justificado** D5 + D4.b + D6.a — meta-processo de enforcement (skill + hook + CI gate), sem features cliente nem FSM nem Controllers Inertia próprios. Detalhes em [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md).

> **Convenção do ID:** `US-MWART-NNN` para user stories de meta-processo.
> **Origem:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Wagner 2026-05-08 pediu único caminho de migração com enforcement, falhas inaceitáveis.
> **Skill mãe:** [mwart-process](../../../.claude/skills/mwart-process/SKILL.md) (Tier A always-on).
> **Estimates recalibradas:** [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — fator 10x em codáveis + margem 2x. Total 28h → 5.5h reais.

## 1. Glossário

- **MWART** — Module Web App React Transition (Blade → Inertia/React)
- **Camadas de enforcement:** (1) skill Tier A, (2) hook PreToolUse, (3) CI workflow gate
- **Score audit:** 0-100 produzido pelo `cockpit-runbook` modo B (CHECKLIST §G)
- **Override autorizado:** comentário `/mwart-override <razão>` em PR — registra exceção em ADR per-tela

## 2. User stories — meta-processo de enforcement

### US-MWART-001 · Camada 2+3 enforcement — Hook + CI workflow

> owner: wagner · priority: p0 · estimate: 1.5h · status: todo · type: story · origin: adr-0104
> blocked_by: —

**Implementado em:** _parcial_ · `.claude/hooks/block-mwart-violation.ps1` · `.claude/settings.json` · verificado@8af585a (2026-07-02) — camada 2 (hook PreToolUse) viva e registrada; camada 3 (CI mwart-gate.yml) foi DELETADA pela ADR 0271 onda 2 (era soft continue-on-error — teatro); régua viva de cobertura de tela hoje = casos-gate required (ADR 0264); MwartGateWorkflowTest nunca criado

**Contexto.** ADR 0104 define 3 camadas de enforcement. A camada 1 (skill Tier A `mwart-process`) já está ativa. Faltam 2 e 3 — sem elas, o processo depende exclusivamente do agent lembrar (pode falhar em sessão longa, dev humano sem Claude Code, ou agent novo). Esta US implementa as travas em runtime e merge.

**Escopo:**
- [ ] Hook PreToolUse `.claude/hooks/block-mwart-violation.ps1` — bloqueia `Edit`/`Write` em:
  - `resources/js/Pages/<Mod>/<Tela>.tsx` se `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` não existe (F1 incompleta)
  - controller chamando `Inertia::render('<Mod>/<Tela>')` se SPEC.md não tem US `<MOD>-002` com status `done` (F2 incompleta)
  - Mensagem de erro PT-BR explica qual fase pular gerou bloqueio + comando pra corrigir
- [ ] Registrar hook em `.claude/settings.json` (skill `update-config` cobre)
- [ ] CI workflow `.github/workflows/mwart-gate.yml` — trigger em PR que toca `resources/js/Pages/**/*.tsx`:
  - Verifica RUNBOOK existe em `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md`
  - Verifica SPEC.md tem ≥1 US do tipo MWART migration referenciando esta tela
  - Roda `php artisan cockpit-runbook:audit <path>` e exige score ≥ 70 (CRITICAL=0)
  - Roda Pest baseline da F2 (filtro por nome do controller)
  - Aceita override via comentário PR `/mwart-override <razão>` que cria ADR per-tela
- [ ] Atualizar `mwart-quality` SKILL.md com referência a [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) e a este SPEC
- [ ] Atualizar `cockpit-runbook` SKILL.md idem (modo B vira gate canônico de F3 e F4)
- [ ] Atualizar `memory/proibicoes.md` — adicionar "❌ Caminho alternativo de MWART (sem F1+F2 completas)"

**Acceptance criteria:**
- [ ] Tentativa de Edit em `Pages/Sells/Create.tsx` sem RUNBOOK existir → hook bloqueia com mensagem clara
- [ ] PR que adiciona Page Inertia sem RUNBOOK falha CI com link pro processo
- [ ] PR com `/mwart-override` registrado por Wagner passa CI + cria ADR per-tela
- [ ] Pest test `MwartGateWorkflowTest` valida o pipeline completo (mock PR)

**Refs:** [ADR 0104 §Enforcement (3 camadas)](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [skill update-config](../../../.claude/skills/update-config/SKILL.md)

### US-MWART-002 · Backfill — audit das 78 telas Inertia já existentes

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: story · origin: adr-0104
> blocked_by: US-MWART-001

**Implementado em:** _pendente_ — tabela mcp_pages_audits, comando artisan mwart:backfill-audit e plug no dashboard de qualidade não existem no repo (grep vazio em app/, Modules/ e database/)

**Contexto.** O ADR 0104 estabelece processo canônico, mas as ~78 telas Inertia existentes foram migradas antes do processo formalizar. Backfill garante: cada tela tem RUNBOOK retroativo + score audit registrado + SPEC com US `done` (status histórico).

**Escopo:**
- [ ] Tabela nova `mcp_pages_audits` (migration) — `page_path` (PK), `module`, `runbook_exists`, `score_total`, `score_ds`, `score_adr`, `score_ux`, `audit_date`, `audited_by`
- [ ] Comando artisan `mwart:backfill-audit` — itera por `resources/js/Pages/**/*.tsx`, roda audit modo B, grava em `mcp_pages_audits`
- [ ] Job assíncrono se demorar (78 telas × ~30s/audit = ~40min)
- [ ] Para telas com score < 70: registrar em SPEC.md do módulo como US-MOD-NNN `mwart-debt` (priority p2, type debt)
- [ ] Para telas com score ≥ 70 e sem RUNBOOK: gerar RUNBOOK retroativo via `cockpit-runbook` (modo Generate forçado)
- [ ] Dashboard `/copiloto/admin/qualidade` mostra trend dos scores (já existe estrutura — só plugar nova tabela)
- [ ] Marcar todas USs históricas como `done` no SPEC retroativo (lifecycle `historical`)

**Acceptance criteria:**
- [ ] Comando `mwart:backfill-audit` roda em prod e grava 78+ rows em `mcp_pages_audits`
- [ ] Dashboard mostra distribuição de scores (% verde/amarelo/laranja/vermelho)
- [ ] Telas <50 score viram tasks p2 explicitamente — Wagner decide ordem do refactor backlog
- [ ] Próximas migrações já entram com RUNBOOK + SPEC desde o dia 1

**Refs:** [CHECKLIST.md §G — Score 0-100](../../../.claude/skills/cockpit-runbook/CHECKLIST.md), dashboard existente em `Modules/Jana/Http/Controllers/Admin/QualidadeController.php`

### US-MWART-003 · Métricas de adoção do processo

> owner: wagner · priority: p2 · estimate: 1h · status: todo · type: story · origin: adr-0104
> blocked_by: US-MWART-002

**Implementado em:** _pendente_ — health-check mwart_process_compliance_24h não existe (grep vazio); depende de US-MWART-002 (tabela mcp_pages_audits) que também está pendente

**Contexto.** "Não pode falhar" exige observabilidade. Métricas chave que respondem se o processo está sendo seguido:

**Escopo:**
- [ ] Health-check novo em `php artisan jana:health-check`: `mwart_process_compliance_24h`
- [ ] SQL: `SELECT COUNT(DISTINCT pr_url) FROM mcp_pages_audits WHERE audit_date > NOW() - INTERVAL 24 HOUR AND score_total >= 70`
- [ ] Alert se houver merge sem audit nas últimas 24h (campo `score_total IS NULL` em PR mergeado)
- [ ] Brief inclui linha: "MWART compliance 24h: X/Y PRs com audit ≥70"
- [ ] Tela `/copiloto/admin/qualidade` mostra:
  - Score médio das telas Inertia (trend 30d)
  - # PRs MWART com `/mwart-override` no quarter (alerta se > 3)
  - Top 5 telas com score baixo (refactor backlog)

**Acceptance criteria:**
- [ ] Wagner abre `/copiloto/admin/qualidade` e vê trend dos últimos 30d
- [ ] Health-check falha visível em alert log se compliance < 100% por >24h
- [ ] Brief diário (06:00 BRT) carrega métrica MWART junto das outras

**Refs:** [ADR 0094 — Constituição V2 §Loop fechado por métrica](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [ADR 0091 — Daily Brief](../../decisions/0091-daily-brief.md)

## 3. User stories — ondas de migração do backbone Blade (roadmap)

> **Origem:** [ROADMAP-ONDAS-BLADE-ADVERSARIOS.md](ROADMAP-ONDAS-BLADE-ADVERSARIOS.md) (2026-06-13) + [ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md).
> **Long-horizon (p1–p3) — fora do cycle de Receita ativo.** Cada onda = 1 onda do roadmap; acceptance = **critério de desligamento** (route Blade morto/302). Cada tela passa pelo ciclo MWART (F1→F5) + gate visual F1.5 contra o **adversário [CD]** da onda. [W] decide quando cada uma vira fila.

### US-MWART-004 · Onda 1 — migrar Vendas, PDV & Caixa e desligar Blade

> owner: — · priority: p1 · estimate: 16h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: —

**Implementado em:** _parcial_ · `resources/js/Pages/Sells/Index.tsx` · `resources/js/Pages/Sells/Create.tsx` · `resources/js/Pages/Sells/Caixa/Index.tsx` · verificado@8af585a (2026-07-02) — telas React vivas (Index/Create/Edit/Show/Drafts/Quotations/Subscriptions + Caixa); falta PDV-balcão puro, tela Devolução e Fechar-caixa; critério de desligamento NÃO atingido: resource pos (L532), cash-register (L643) e sell-return (L673) seguem vivos em routes/web.php

Domínios E (Vendas/PDV) + H (Caixa), ≈66 fn. Plano F1: [ONDA-1-VENDAS-PDV-CAIXA-PLANO.md](ONDA-1-VENDAS-PDV-CAIXA-PLANO.md) · **mapa verificado:** [ONDA-1-CUTOVER-LEDGER.md](ONDA-1-CUTOVER-LEDGER.md). **Adversário [CD]:** Square POS + Stripe Checkout. **Já vivo em React:** Sells/Index, Create, Edit, Show, Drafts, Quotations, Subscriptions, Caixa/Index. **Lacunas verificadas (red-team [CX]):** construir **3 telas React** — PDV-balcão puro (`pos/index` ≠ Sells/Create), **Devolução** (`sell-return` é 100% Blade, **zero twin**) e **Fechar-caixa** (`/vendas/caixa` não tem) — *depois* desligar.

**Critério de desligamento (acceptance — corrigido pelo ledger):**
- [ ] PDV-balcão puro React cobre `pos/index` → `resource('pos')` 302/removido; `pos.store` = `keep-api`
- [ ] Tela de Devolução React construída → `sell-return` 302/removido; rotas órfãs `edit`/`update`/`get-product-row` removidas do roteador
- [ ] Fechar-caixa React → `resource('cash-register')` 302 → `/vendas/caixa`; `close-register` migrado
- [ ] Fallbacks Blade removidos (`return view(...)` apagado de show/edit/drafts/quotations/create) **+ flag `useV2SellsCreate` 100%** (senão Blade responde a deep-link sem `X-Inertia`)
- [ ] Links guest públicos (`/invoice|/quote|/pay/{token}`) tratados; 10 rotas duplicadas (l.376-383 vs 449-456) resolvidas
- [ ] PDFs/print/FSM/`store` mantidos como `keep-api` (não matar — a tela React consome)
- [ ] Pest baseline F2 antes de qualquer Edit no front; Tier 0 business_id ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

### US-MWART-005 · Onda 2 — migrar Clientes & contatos e desligar /contacts

> owner: — · priority: p2 · estimate: 10h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: —

**Implementado em:** _parcial_ · `resources/js/Pages/Cliente/Index.tsx` · `resources/js/Pages/Cliente/Import.tsx` · `resources/js/Pages/Cliente/Ledger.tsx` · verificado@8af585a (2026-07-02) — /cliente vivo com drawer + import + ledger em React; critério de desligamento NÃO atingido: resource contacts segue vivo em routes/web.php (L303), sem redirect e sem lápide dos blades de contact

Domínio C, ≈26 fn. **Adversário [CD]:** Attio (ficha viva, contexto sem cliques). **Já vivo:** `/cliente` drawer 760px ([ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)/[0188](../../decisions/0188-contacts-multi-type-flag-aditiva.md)) + abas anexos/vendas/pagamentos/assinaturas.

**Critério de desligamento (acceptance):**
- [ ] `resource('contacts')` redireciona pra `/cliente` nos 6 tipos (customer/supplier/employee/representative…)
- [ ] ledger, import, mapa, customer-group, lookup CNPJ portados
- [ ] `contact/*.blade` lápide
- [ ] Depende leve da Onda 1 (venda referencia cliente) — pode rodar em paralelo

### US-MWART-006 · Onda 3 — migrar Produtos & catálogo e desligar Blade

> owner: — · priority: p2 · estimate: 24h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: —

**Implementado em:** _parcial_ · `resources/js/Pages/Produto/Index.tsx` · `resources/js/Pages/Produto/Unificado/Index.tsx` · verificado@8af585a (2026-07-02) — catálogo React vivo (Index/Create/Edit/Show/BulkEdit/SellingPrices/StockHistory + Unificado); critério de desligamento NÃO atingido: resource products segue vivo em routes/web.php (L423) + satélites (taxonomies/brands/units/barcodes/discount)

Domínio D (o maior), ≈55 fn. **Adversário [CD]:** Linear (densidade) + Shopify Admin (editor de produto/variações). **Já vivo:** `/products/unificado` (5 sub-telas) + `produtos-page` Cowork.

**Critério de desligamento (acceptance):**
- [ ] `resource('products')`, `taxonomies`, `brands`, `units`, `barcodes`, `discount` e satélites desligados
- [ ] editor de variação cobre 100% do que `product/edit.blade` fazia (variações, selling-prices, BOM, combo, labels, import)
- [ ] pré-requisito de qualidade das Ondas 1/4/8 (não bloqueia a 1 — catálogo Blade serve enquanto isso)

### US-MWART-007 · Onda 4 — migrar Estoque & inventário e desligar Blade

> owner: — · priority: p2 · estimate: 8h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: US-MWART-006

**Implementado em:** _parcial_ · `resources/js/Pages/StockAdjustment/Index.tsx` · `resources/js/Pages/StockTransfer/Index.tsx` · verificado@8af585a (2026-07-02) — DRIFT vs roadmap 2026-06-13 ("Já vivo: nada"): telas React de ajuste e transferência (Index+Create) já existem e estão roteadas; critério de desligamento NÃO atingido: resources stock-adjustments (L638) e stock-transfers (L655) seguem vivos em routes/web.php

Domínio G, ≈14 fn. **Adversário [CD]:** Linear + Cron (registro auditável em 2 cliques). **Já vivo:** nada (100% Blade) — reusa DataGrid shared candidato da Onda 3.

**Critério de desligamento (acceptance):**
- [ ] `resource('stock-adjustments')` e `resource('stock-transfers')` desligados (+ print, update-status, opening-stock)
- [ ] movimentos preservam append-only/auditoria
- [ ] **depende da Onda 3** (produto é a entidade do movimento)

### US-MWART-008 · Onda 5 — migrar Compras & suprimentos e desligar Blade

> owner: — · priority: p2 · estimate: 14h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: US-MWART-006, US-MWART-007

**Implementado em:** _parcial_ · `resources/js/Pages/Compras/Index.tsx` · `resources/js/Pages/Purchase/Create.tsx` · verificado@8af585a (2026-07-02) — DRIFT vs roadmap 2026-06-13 ("repo 100% Blade"): cockpit /compras (Modules/Compras) + trilho Purchase React (Create/Edit/Index/Show) já vivos; critério de desligamento NÃO atingido: resource purchases segue vivo em routes/web.php (L510) + purchase-order/purchase-return/requisition

Domínio F, ≈22 fn. **Adversário [CD]:** Ramp / procurement moderno (fluxo aprovação + recebimento costurado). **Já vivo:** protótipo `compras-page.jsx` no Cowork; repo 100% Blade.

**Critério de desligamento (acceptance):**
- [ ] `resource('purchases')`, `purchase-order`, `purchase-return`, requisition, combined-return desligados
- [ ] entrada de compra alimenta o estoque da Onda 4
- [ ] **depende das Ondas 3 + 4** (produto + estoque)

### US-MWART-009 · Onda 6 — migrar Contábil & tesouraria (camada Account) e desligar Blade

> owner: — · priority: p2 · estimate: 16h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: US-MWART-004, US-MWART-008

**Implementado em:** _pendente_ — nenhuma tela React da camada Account do UltimatePOS (account/fund-transfer/cash-flow/balance-sheet/trial-balance/expenses) existe em resources/js/Pages; resource account segue 100% Blade (Pages/Financeiro é o módulo Financeiro, domínio distinto por definição da US)

Domínio I, ≈30 fn. **Distinta do módulo Financeiro React já migrado** — é a camada `Account` do UltimatePOS. **Adversário [CD]:** Mercury + QuickBooks (saldo/fluxo calmo + balancete/conciliação).

**Critério de desligamento (acceptance):**
- [ ] `resource('account')` (+ fund-transfer, deposit, cash-flow, balance-sheet, trial-balance, link-account), `account-types`, `payments`, `expenses` desligados
- [ ] balancete e cash-flow nativos no cockpit
- [ ] **depende de Vendas (1) + Compras (5)** pra fonte de lançamentos

### US-MWART-010 · Onda 7 — migrar Configurações, admin & documentos e desligar Blade

> owner: — · priority: p3 · estimate: 20h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: —

**Implementado em:** _parcial_ · `app/Http/Controllers/ModuleManagementController.php` · verificado@8af585a (2026-07-02) — Gerenciador de Módulos React vivo (ModuleManagementController@index renderiza a tela via Inertia); critério de desligamento NÃO atingido: resources de settings (business/invoice-layouts/schemes/tax-rates/printers/roles/users) e settings_custom_labels seguem Blade

Domínios K + L, ≈55 fn. **Adversário [CD]:** Stripe Settings + Vercel (busca + agrupamento, nunca muro de toggles AdminLTE). **Já vivo:** Gerenciador de Módulos React + preferências tema/sidebar.

**Critério de desligamento (acceptance):**
- [ ] cada `resource()` de settings desligado por grupo (business/location/invoice-layouts/schemes/tax-rates/printers/notification/backup/roles/users)
- [ ] `settings_custom_labels.blade` (37 KB) reescrito como tela de busca; note-documents/mídia portados
- [ ] tax-rates/invoice-schemes (pré-requisito fiscal de 1 e 6) — Blade serve até portar

### US-MWART-011 · Onda 8 — migrar Relatórios (a represa) e desligar Blade

> owner: — · priority: p2 · estimate: 24h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: US-MWART-004, US-MWART-005, US-MWART-006, US-MWART-007, US-MWART-008, US-MWART-009, US-MWART-010

**Implementado em:** _pendente_ — nenhum relatório do domínio J (/reports/* do UltimatePOS: lucro/perda, estoque, fiscal, vendedor, lote) migrado pra React (Pages/Financeiro/Relatorios e Pages/Ponto/Relatorios pertencem a módulos próprios, não ao domínio J); bloqueada por construção pelas Ondas 1-7

Domínio J, ≈45 fn que leem TODOS os domínios. **Adversário [CD]:** Metabase + Stripe Sigma (filtro vivo + drill-down + export que o contador aceita). **Não pode vir antes:** relatório que lê dado de domínio não-migrado mente.

**Critério de desligamento (acceptance):**
- [ ] todos os `/reports/*` desligados (lucro/perda, estoque, fiscal, vendedor, lote…)
- [ ] números batem com os domínios já migrados (1–7) — **prova de integridade da rota inteira**
- [ ] **depende de TODAS as Ondas 1–7** — é onde a rota "só para depois de todas migradas"

### US-MWART-012 · Onda 9 — migrar Acesso & onboarding e remover /login/old

> owner: — · priority: p3 · estimate: 8h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: —

**Implementado em:** _parcial_ · `resources/js/Pages/Site/Login.tsx` · verificado@8af585a (2026-07-02) — login React vivo como default (LoginController); critério de desligamento NÃO atingido: /login/old segue registrado em routes/web.php (L178) e register/password-reset/business-register/social-auth/install continuam Blade

Domínio A, ≈10 fn. **Adversário [CD]:** WorkOS + Linear (login limpo, social, sem AdminLTE). Baixa frequência → tarde, mas é a primeira impressão.

**Critério de desligamento (acceptance):**
- [ ] `/login/old` removido; register e password reset em React
- [ ] business-register (+ checks), social-auth (Google/Microsoft), install wizard portados
- [ ] `auth/*.blade` e `install/*` lápide — independente, pode rodar em paralelo

### US-MWART-013 · Onda 10 — gate de zero-Blade (prova de honestidade [CX])

> owner: — · priority: p2 · estimate: 6h · status: todo · type: story · origin: roadmap-ondas-blade
> blocked_by: US-MWART-004, US-MWART-005, US-MWART-006, US-MWART-007, US-MWART-008, US-MWART-009, US-MWART-010, US-MWART-011, US-MWART-012

**Implementado em:** _pendente_ — gate de CI zero-Blade e contador de routes Blade vivos não existem (nenhum workflow em .github/workflows cobre; contagem manual em routes/web.php ainda encontra resources Blade vivos de contacts/products/purchases/pos/stock/sell-return); bloqueada por construção pelas Ondas 1-9

A onda que torna a rota verdadeira. **Adversário [CX] — o permanente:** red-team do processo, "qual route Blade ainda responde escondido atrás do React?".

**Critério de desligamento (acceptance):**
- [ ] grep no `web.php` por `resource()` e `view()` Blade vivos → cada um vira lápide ou 302
- [ ] **Gate de CI: 0 view AdminLTE servida em rota autenticada**
- [ ] smoke `/_smoke-probe` + visual-regression (US-GOV-013) atravessam todas as rotas sem cair em layout legado
- [ ] **contador de routes Blade vivos = 0** → a rota PAROU. Antes disso, nenhuma onda anterior pode ser chamada de "concluída"

---

**Última atualização:** 2026-06-13 — adicionadas US-MWART-004…013 (ondas de migração do backbone Blade · roadmap 2026-06-13). Histórico meta-processo: 2026-05-08.
