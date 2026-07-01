---
slug: compras
title: "Especificação funcional — Compras"
type: spec
module: Compras
status: ativo
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0101-tests-business-id-1-nunca-cliente"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0106-recalibracao-velocidade-fator-10x-ia-pair"
  - "0107-emendation-0104-visual-comparison-gate-f3"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0141-skill-migracao-blade-react"
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0149-mwart-screen-pattern-reuse-cowork"
related_proposals:
  - "compras-modulo-greenfield-hibrido"
  - "compras-purchase-convergencia-c1"
pii: false
updated_at: "2026-05-25"
last_updated: "2026-05-25"
version: "0.2"
owner: wagner
---

# Especificação funcional — Compras

> Convenção do ID: `US-COM-NNN` user stories, `R-COM-NNN` regras Gherkin.
> Status `proposed` até ADRs `compras-modulo-greenfield-hibrido` + `compras-purchase-convergencia-c1` serem promovidas a `accepted`.

## 0. Amendments

### v0.2 — 2026-05-25 — Convergência C1

ADR proposta [`compras-purchase-convergencia-c1`](../../decisions/proposals/compras-purchase-convergencia-c1.md) **inverte a direção** que esta SPEC v0.1 prometia em duas frentes:

- **US-COM-002 (criar compra manual)** → **CANCELADA**. Cockpit `/compras` botão "+ Nova compra" delega `/purchases/create` Inertia (Trilho A piloto MWART Wave 2 B5 · ADR 0141). Não nasce `Pages/Compras/Create.tsx`. Wrapper `ComprasService::criar()` só nasce SE review trigger ADR C1 ativar (vertical-específico vestuário Larissa).
- **US-COM-004 (deprecar `/purchases`)** → **INVERTIDA**. `/purchases` permanece canônico (Inertia React desde Wave 2 B5). `/compras` é cockpit complementar de listagem + KPIs + drawer denso Cowork. Coexistem. R-COM-302 (redirect 301) **anulada**.
- **US-COM-005 (GradeMatrixInput Wave 4.5)** — entra em `Pages/Purchase/Create.tsx` (C1) OU nasce `Pages/Compras/Create.tsx` vertical-específico SE Larissa validar canary Bloco 4.5 (review trigger #3 ADR C1).
- **US-COM-001** + **US-COM-003 (importar XML DF-e)** — **mantidas** intactas. GAP NOVO bridge `ImportarDfeComoCompraService` segue na Wave 6.

Motivação: comparativo Compras × Sells 2026-05-25 (sessão `frosty-greider-83ab2f`) descobriu que `/purchases/{create,edit,show}` JÁ eram Inertia React (1.729 LOC + 4 charters Tier A) desde a Wave 2 B5 do piloto `migracao-blade-react`. Manter Wave 8 cria 3 telas pra mesma operação + 1.729 LOC duplicados. Detalhes em [`memory/sessions/2026-05-25-como-integrar-c1-compras-converge-purchase.md`](../../sessions/2026-05-25-como-integrar-c1-compras-converge-purchase.md).



## 1. Glossário rápido

- **Compra** — Transaction com `type='purchase'`, `'purchase_order'`, ou `'purchase_return'` (tabela polimórfica core UPos)
- **FSM Compras** — 6 estágios visuais no cockpit: `rascunho → pedido → trânsito → recebido → conferido → pago` (mapeados sobre `transactions.status` + `payment_status` no MVP; FSM canônico ADR 0143 fica pra fase 3)
- **DF-e recebida** — `nfe_dfe_recebidos`, XML NF-e puxado via SEFAZ NSU em nome do CNPJ destinatário (US-NFE-049/051, ADR 0116)
- **Bridge DFe→Compra** — `ImportarDfeComoCompraService` que pega NfeDfeRecebido → cria Transaction type=purchase + lines (Wave 6, gap novo principal)
- **Grade tam×cor** — entrada matricial pra produtos `type='variable'` (PMGG × cores); cada célula = 1 SKU filho (`variation_id`). Padrão Cin7/Lightspeed (memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md)
- **Caminho B híbrido** — `Modules/Compras/` greenfield só pra Controllers/Pages/Sidebar; reusa `transactions` polimórfica + TransactionUtil + TransactionObserver Financeiro (ADR proposta `compras-modulo-greenfield-hibrido`)

## 2. Cliente sinal (ADR 0105)

- **Persona piloto:** Larissa @ ROTA LIVRE biz=4 (vestuário, 1280px, não-técnica)
- **Sinal qualificado:** Larissa reportou dor real de entrada de compra em vestuário (50+ modelos/entrega × 4 tams × 3-5 cores = 600-1000 SKUs/lote por linha-a-linha no Blade legacy hoje)
- **Validação pendente:** call Bloco 4.5 ([DISCOVERY-LARISSA-COMPRAS.md:68](DISCOVERY-LARISSA-COMPRAS.md#L68)) — Wave 4.5 GradeMatrixInput **NÃO** pode subir prod sem essa validação (R11 não vale aqui)
- **Métrica detecta drift:** tempo de entrada de PO ≥ 10min/modelo no Blade legacy hoje; meta com grade ≤ 2min/modelo

## 3. Princípios arquiteturais (resumo ADR proposta)

- **Caminho B HÍBRIDO** decidido — greenfield Controllers/Pages + REUSA `transactions` polimórfica + TransactionUtil + Observer Financeiro. Greenfield puro foi rejeitado custo (quebraria Financeiro/Manufacturing/Fiscal).
- **Soft wrapper inicial** (precedente Caixa #1288 + Home #1297) — `ComprasController` é wrapper Inertia que delega lógica a `TransactionUtil` existente. Service nativo Compras vem depois (refactor incremental, não bloqueador).
- **GAP NOVO único:** bridge `NfeDfeRecebido → Transaction(type=purchase)` + UI "Importar XML" listando DF-e pendentes. Wave 6 — maior risco técnico.

## 4. User stories ativas

### US-COM-001 — Cockpit `/compras` (lista paginada + 4 KPIs + drawer)

**Status:** in_progress (Wave 1 scaffold = 2026-05-21)
**Persona:** todo user com permission `compras.view` (admin biz, financeiro, gestor compras)
**Esforço:** ~6-8h IA-pair (Waves 1-5 já dimensionadas)
**Implementado em:** `resources/js/Pages/Compras/Index.tsx` · verificado@fd96258 (2026-06-13)

Como user com permission `compras.view`, quero acessar `/compras` e ver cockpit completo das compras do meu business com 4 KPIs (aberto/trânsito/mês/fornecedor) e lista paginada filtrável por estágio FSM, pra poder priorizar conferência/pagamento sem precisar abrir cada compra.

**Regras:**

- R-COM-001 — Sem permission `compras.view` → 403
- R-COM-002 — Multi-tenant Tier 0 ADR 0093 — só vê compras do próprio `business_id` (session). Job `ImportarDfeComoCompraJob` recebe `$businessId` no constructor
- R-COM-003 — KPIs `aberto`/`transito`/`mes`/`fornec` calculados via `TransactionUtil::getListPurchases($business_id)` agregado server-side. Defer via `Inertia::defer` (pattern obrigatório)
- R-COM-004 — Filtros: query string `?q=...&stage=...&supplier_id=...`. Sem session storage (anti-hook charter)
- R-COM-005 — Linha clicada abre drawer ≤480px com 4 abas (Geral / Linhas / Pagamentos / Timeline)

### US-COM-002 — Criar compra manual

**Status:** ~~pending Wave 3+~~ → **cancelled-c1** (2026-05-25 · ADR `compras-purchase-convergencia-c1`)
**Persona:** user com permission `purchase.create` (C1 — não `compras.create`)

> **C1:** Cockpit `/compras` delega botão "+ Nova compra" pra `/purchases/create` Inertia (Trilho A piloto MWART Wave 2 B5). `Pages/Purchase/Create.tsx` (520 LOC + charter Tier A ★) cobre 100% do form. Não nasce `Pages/Compras/Create.tsx` agora.

Como user com permission `purchase.create`, quero clicar "+ Nova compra" no cockpit `/compras` e navegar via Inertia (sem reload) pra `/purchases/create` que já é React, sem precisar reabrir o cockpit nem trocar de aba/tab.

**Regras:**

- R-COM-101 ~~Wrapper sobre `PurchaseController::store` extraído pra `ComprasService::criar()`~~ → **anulada**. `PurchaseController::store` permanece canon
- R-COM-102 — `router.visit('/purchases/create')` injeta header `X-Inertia` automaticamente; dispara dual-path em `PurchaseController:400` → `Purchase/Create.tsx`. NÃO usar `<a href>` ou `window.location.href` (cai no Blade legacy)
- R-COM-103 — Observer Financeiro (`TransactionObserver`) já cria `fin_titulos` type=pagar automaticamente — comportamento preservado pois `/purchases/store` continua sendo o handler

**Review trigger pra reabrir US-COM-002:** Larissa @ ROTA LIVRE biz=4 ou outro cliente piloto reportar dor real de vertical-específico vestuário que `Purchase/Create.tsx` não atende. Aí nasce `Pages/Compras/Create.tsx` apenas pro vertical (não default).

### US-COM-003 — Importar XML DF-e como compra (GAP NOVO)

**Status:** pending (Wave 6 — maior risco)
**Persona:** user com permission `compras.import_xml`

Como user com permission `compras.import_xml`, quero abrir modal "Importar XML" e ver lista de DF-e pendentes do meu business (puxadas pela rotina SEFAZ NSU), selecionar uma e auto-criar compra com lines pré-populadas + fornecedor auto-matchado por CNPJ, pra não digitar nada.

**Regras:**

- R-COM-201 — Modal lista `NfeDfeRecebido::where('business_id', $biz)->whereNull('transaction_id')`
- R-COM-202 — Auto-match supplier via `Contact::where('tax_number', $dfe->cnpj_emitente)`. Se não achar, abre form "criar fornecedor" inline
- R-COM-203 — Cria Transaction type=purchase via `ImportarDfeComoCompraService::executar($dfeId, $businessId)` (Job opcional pra async)
- R-COM-204 — UNIQUE compound `(business_id, transaction_id)` em `nfe_dfe_recebidos` — re-import idempotente
- R-COM-205 — Migration nova: `2026_05_22_000000_add_transaction_id_to_nfe_dfe_recebidos.php` (Wave 6)
- R-COM-206 — Advisory lock anti-race se 2 users importam mesmo DFe simultâneo

### US-COM-004 — Deprecar `/purchases` legacy

**Status:** ~~pending Wave 8~~ → **inverted-c1** (2026-05-25 · ADR `compras-purchase-convergencia-c1`)
**Persona:** infra — não user-facing direto

> **C1 inverte premissa:** `/purchases/*` não é Blade legacy — desde Wave 2 B5 do piloto `migracao-blade-react` (ADR 0141) é Inertia React via dual-path `X-Inertia` header ou `?v=2`. `/compras` é cockpit complementar, não substituto.

Como Wagner, quero `/purchases/*` (Inertia React MWART Wave 2 B5) e `/compras` (cockpit Cowork denso) coexistirem como entry-points complementares: `/compras` lista + KPIs + drawer; `/purchases/create|edit|show` CRUD pesado reusado via `router.visit`.

**Regras:**

- R-COM-301 — Feature flag per-business `compras_module_enabled` ainda controla **visibilidade do entry "Compras"** no sidebar (não bloqueia `/purchases`)
- R-COM-302 ~~`/purchases` → 301 `/compras` quando flag ON~~ → **anulada**. Sem redirect
- R-COM-303 ~~Menu legacy "Purchases" desaparece quando flag ON~~ → **anulada**. Topnav `core_topnavs.php` key 'Purchase' (label "Compras") mantido + sidebar entry "Compras" (`/compras`) coexistem

**Review trigger pra reabrir US-COM-004:** Time MCP (Felipe/Maiara) reportar "qual link uso `/compras` ou `/purchases`?" >3 vezes em 7d → criar `Modules/Compras/Resources/menus/topnav.php` apontando `/purchases/create` (Cowork visual sidebar) pra unificar entry-points sem deprecar `/purchases`.

### US-COM-005 — Entrada matricial tam×cor (GradeMatrixInput)

**Status:** in_progress (Wave 4.5 — modo grade plugado, aguarda smoke/canary)
**Persona:** Larissa @ ROTA LIVRE biz=4 vestuário (validação canary)
**Esforço:** ~6-8h IA-pair (referência [arte 2026-05-21](../../sessions/2026-05-21-arte-grade-matrix-input-vestuario.md))
**Implementado em:** `resources/js/Pages/Purchase/Create.tsx` (modo grade) + `PurchaseController::gradeMatrix` + `resources/js/Pages/Purchase/_components/GradeMatrixInput.tsx` · 2026-06-22

> **Placement C1 (2026-06-22):** a grade entra em `Pages/Purchase/Create.tsx` (não `/compras/create`) — convergência C1. **Modelo 2D auto-detectado:** UltimatePOS guarda variação em 1 eixo; o backend monta 2D quando os nomes de variação são compostos e parseáveis, senão cai pra grade de 1 eixo (linhas = variações reais). Nunca grade vazia silenciosa (loga o `mode`). R-COM-405 ajustado: o caller expande as células em `purchases[]` (1 célula = 1 `variation_id`) e POSTa via `form.post('/purchases')`.

Como Larissa criando compra de modelo `type='variable'` no `/purchases/create`, quero selecionar produto pai e abrir grade visual onde linhas = tamanhos (PMGG) e colunas = cores (Preto/Branco/...), digitar qty por célula com Tab/Enter, ver totais por linha/coluna/grand on-the-fly e salvar tudo de uma vez, sem precisar adicionar SKU filho um por vez.

**Regras:**

- R-COM-401 — `<GradeMatrixInput>` custom TanStack Table v8 headless + inputs React 19 (não AG Grid, não Handsontable — bundle ~15KB)
- R-COM-402 — Trigger: Combobox pai `type='variable'` abre grade. `type='single'` mostra 1 input qty único
- R-COM-403 — Teclado: Tab → próxima cor, Enter → próxima linha, Esc → cancela, F2 → modo edit, setas → navegação 4 direções
- R-COM-404 — Custo unitário 1 por modelo (override por célula só em "modo avançado" V2)
- R-COM-405 — Save atomic: `onSubmit({ product_id, lines: [{ variation_id, qty, unit_cost }] })`. Caller acumula em state e POST único no submit do form
- R-COM-406 — Edição posterior: ao reabrir purchase salva, reagrupa `purchase_lines` por `product_id` e re-renderiza grade

## 5. Out of scope (V1)

- Paste Excel / bulk editor spreadsheet
- OCR XML → grade auto-fill via AI (Lightspeed style)
- Custo por célula override (V2)
- Mobile/touch — Larissa usa 1280px desktop
- Integração FSM canônico ADR 0143 (fase 3, MVP usa `transactions.status` simples)

## 6. Critério de pronto Wave 1 (scaffold)

- [ ] `Modules/Compras/` estruturado com module.json + ServiceProvider + Routes + Controllers stub
- [ ] `modules_statuses.json` com `"Compras": true`
- [ ] `phpunit.xml` registrando `./Modules/Compras/Tests/Feature`
- [ ] `Tests/Feature/ComprasIndexTest.php` 3 testes (200, Inertia component, 403 sem permission) — verde
- [ ] `php artisan module:list` mostra Compras enabled
- [ ] PR aberto com Infra Contract: `curl -sv http://localhost/compras` 200 + `gh pr checks` verde
- [ ] ADR proposta `compras-modulo-greenfield-hibrido` linkada no PR

## 7. Refs

- [memory/sessions/2026-05-21-como-integrar-compras.md](../../sessions/2026-05-21-como-integrar-compras.md) — plug-points caminho B híbrido
- [memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md](../../sessions/2026-05-21-arte-grade-matrix-input-vestuario.md) — estado-da-arte GradeMatrixInput
- [memory/requisitos/Compras/DISCOVERY-LARISSA-COMPRAS.md](DISCOVERY-LARISSA-COMPRAS.md) — discovery cliente
- [memory/requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md](AUDITORIA-COMPRAS-2026-05-21.md)
- [memory/requisitos/Compras/CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md)
- [memory/decisions/proposals/compras-modulo-greenfield-hibrido.md](../../decisions/proposals/compras-modulo-greenfield-hibrido.md) — ADR proposta (Wave 8 parcial superseded por C1)
- [memory/decisions/proposals/compras-purchase-convergencia-c1.md](../../decisions/proposals/compras-purchase-convergencia-c1.md) — **ADR C1 vigente** (2026-05-25)
- [memory/sessions/2026-05-25-como-integrar-c1-compras-converge-purchase.md](../../sessions/2026-05-25-como-integrar-c1-compras-converge-purchase.md) — plug-points C1
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) Processo MWART canônico
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) Recalibração 10x
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) Visual gate F3
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) Cowork loop
- Precedente Soft wrapper PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288) + PR [#1297 Home](https://github.com/wagnerra23/oimpresso.com/pull/1297)
- Protótipo canon: `prototipo-ui/cowork/compras-page.{jsx,css}` + `Compras.html`

## 8. Onda Audit Sênior 2026-05-25

> Origem: [`AUDIT-SENIOR-2026-05-25.md`](AUDIT-SENIOR-2026-05-25.md). Compras 38/100 Crítico — sai pra ~63/100 pós PR-0 Estabilizar (Tier 0 + Sec + Cliente).
> Bypass MCP `tasks-create` (mcp_jira_projects ainda não tem entry "Compras") — webhook sincroniza no próximo push.

### US-COM-006 · Pest cross-tenant biz=1 vs biz=99 (4 testes)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: —

**Acceptance:**
- [ ] 4 testes Pest em `Modules/Compras/Tests/Feature/MultiTenantTest.php`
- [ ] cenários: list/create/show/update de compras isolados biz=1 vs biz=99
- [ ] cobertura mínima: ComprasController + ServicesPurchase + Repository
- [ ] CI verde

**Pré-req:** MySQL local online (atual OFFLINE detectado em module:grade)
**Refs:** ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL), AUDIT-SENIOR-2026-05-25.md §3.1

### US-COM-007 · Fix business_id source: auth() em vez de session() + abort_if

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Acceptance:**
- [ ] Trocar `session('user.business_id')` → `auth()->user()->business_id` em todos Controllers Compras
- [ ] Adicionar `abort_if($entity->business_id !== auth()->user()->business_id, 404)` em show/edit/update/destroy
- [ ] Pest cobre cenário cross-tenant (404 esperado)

**Breaking risk:** mudança comportamental — Wagner sign-off se código legacy depende exclusivo da session
**Refs:** [Laracopilot 2026](https://laracopilot.com/blog/laravel-multi-tenancy-saas-guide/), AUDIT-SENIOR-2026-05-25.md §3.2

### US-COM-008 · Throttle 60/1 em /compras + FormRequest ListarComprasRequest

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Acceptance:**
- [ ] `Route::middleware(['web', 'throttle:60,1'])` em route group `/compras`
- [ ] FormRequest `ListarComprasRequest` com validação de filtros (period, status, supplier_id)
- [ ] Pest cobre rate limit (61º request → 429)

**Refs:** AUDIT-SENIOR-2026-05-25.md §3.3

### US-COM-009 · Validar JOIN scope contacts.business_id em TransactionUtil::getListPurchases (R1 leak)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Sintoma potencial:** `TransactionUtil::getListPurchases` faz JOIN com `contacts` table sem scope `contacts.business_id = X` — vazamento cross-tenant em listagem de fornecedores.

**Acceptance:**
- [ ] Auditar query em `app/Utils/TransactionUtil.php` (6435 LOC compartilhadas com Sells/Expense — mexer com cuidado)
- [ ] Se vazamento confirmado: hotfix imediato + Pest regressão
- [ ] Adicionar scope explícito `contacts.business_id = ?` em todas JOIN com contacts

**Atenção:** mexe em arquivo compartilhado — coordenar com módulos Sells/Expense
**Refs:** AUDIT-SENIOR-2026-05-25.md §3.4 (Risk Register R1) — blast radius MÁXIMO

### US-COM-010 · Adicionar Compras em config/governance/module_clients.yaml (Larissa biz=4 piloto reportando)

> owner: — · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Acceptance:**
- [ ] Adicionar entry Compras em `config/governance/module_clients.yaml`
- [ ] Larissa @ ROTA LIVRE biz=4 como `piloto_reportando_dor` (+8 pts D5)
- [ ] Sinal qualificado já CONFIRMADO no DISCOVERY 2026-05-21 ("ela compra e tem entrada por grade")
- [ ] Pós Onda CONSOLIDAR: promover pra `biz_4_rota_livre_prod` (+15 pts) via canary 7d

**Refs:** ADR 0105 (Cliente como sinal qualificado), AUDIT-SENIOR-2026-05-25.md §6
