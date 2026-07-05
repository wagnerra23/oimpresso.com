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
na_justified_v3:
  D6.c: "O paginate() vive no ComprasController::buildRowsPayload mas opera o query builder montado pelo ComprasService::listarCompras, que usa leftJoin (transaction_payments/contacts) + ->with([...]) — sem relação Eloquent lazy no caminho da listagem. A heurística D6.c só inspeciona o arquivo do controller (paginate sem with no MESMO arquivo) e devolve falso-negativo. N+1 real está TRAVADO por teste físico: Modules/Compras/Tests/Feature/ComprasListagemNPlusUmTest.php (trava contagem de queries — PR #3715, C15 da CAPTERRA-FICHA verificado falso-positivo)."
pii: false
updated_at: "2026-07-05"
last_updated: "2026-07-05"
version: "0.4"
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

### v0.3 — 2026-07-03 — Backlog Capterra-Inventário (Onda 2.1)

Materializa no backlog as capacidades ausentes/parciais detectadas pelo [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) (Passo 2 do programa de ondas). **Passe adversarial de deduplicação** cruzou as 16 tasks propostas contra este SPEC (US-COM-001..010) + estado real do código (`Modules/Compras`, `Modules/NfeBrasil`); resultado: **9 US novas ativas** (US-COM-011..016, 018..020 · §9) + **1 retirada** (US-COM-017 — redigir PII de fornecedor na UI não cabe em ERP; ver §9), **3 já eram US existentes** (nº1→US-COM-003, nº9→US-COM-005, parte do nº12→US-COM-010) e **4 seguradas** como feature-wish ADR 0105 (sinal de cliente pendente). Nada foi recriado. Ver §9.



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
**Implementado em:** _parcial_ · `app/Http/Controllers/PurchaseController.php` · `resources/js/Pages/Purchase/Create.tsx` · `Modules/Compras/Http/Controllers/ComprasController.php` · verificado@176f9bc (2026-07-01) — cancelled-c1: cockpit delega "+Nova compra" pro trilho Purchase (piloto MWART · ADR 0141); permissions purchase.create no ComprasController; NÃO nasce Pages/Compras/Create.tsx nativo

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
**Implementado em:** _pendente_ — bridge `ImportarDfeComoCompraService` + migration `add_transaction_id_to_nfe_dfe_recebidos` + rota `importar-dfe` ainda não criados (GAP NOVO Wave 6; routes/web.php só cita no comentário)

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
**Implementado em:** `app/Http/Controllers/PurchaseController.php` · `Modules/Compras/Http/Controllers/ComprasController.php` · `Modules/Compras/Http/Controllers/DataController.php` · verificado@176f9bc (2026-07-01) — inverted-c1: /purchases (Inertia) e /compras (cockpit) coexistem; sem redirect 301; flag compras_module (DataController) controla só visibilidade do entry no sidebar

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
> ~~Bypass MCP `tasks-create` (mcp_jira_projects ainda não tem entry "Compras")~~ — **nota corrigida 2026-07-03:** o projeto **Compras já existe no MCP** (`tasks-list module:Compras` retorna as US); `tasks-create module:Compras` funciona normal (grava a US no SPEC → webhook sincroniza no push). Não há passo de "criar projeto".

### US-COM-006 · Pest cross-tenant biz=1 vs biz=99 (4 testes)

> owner: — · priority: p0 · estimate: 4h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/Compras/Tests/Feature/MultiTenantTest.php` · `Modules/Compras/Tests/Feature/MultiTenantSqlGuardTest.php` · verificado@176f9bc (2026-07-01) — 4 cenários cross-tenant biz=1 vs biz=99 (list isolation, show 404, KPIs scope, filtro ?q= JOIN contacts)

**Acceptance:**
- [ ] 4 testes Pest em `Modules/Compras/Tests/Feature/MultiTenantTest.php`
- [ ] cenários: list/create/show/update de compras isolados biz=1 vs biz=99
- [ ] cobertura mínima: ComprasController + ServicesPurchase + Repository
- [ ] CI verde

**Pré-req:** MySQL local online (atual OFFLINE detectado em module:grade)
**Refs:** ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL), AUDIT-SENIOR-2026-05-25.md §3.1

### US-COM-007 · Fix business_id source: auth() em vez de session() + abort_if

> owner: — · priority: p0 · estimate: 2h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/Compras/Http/Controllers/ComprasController.php` · verificado@176f9bc (2026-07-01) — business_id via auth()->user()->business_id (não session), abort_if($businessId <= 0), cross-check session vs auth, show retorna 404 (não 403) cross-tenant

**Acceptance:**
- [ ] Trocar `session('user.business_id')` → `auth()->user()->business_id` em todos Controllers Compras
- [ ] Adicionar `abort_if($entity->business_id !== auth()->user()->business_id, 404)` em show/edit/update/destroy
- [ ] Pest cobre cenário cross-tenant (404 esperado)

**Breaking risk:** mudança comportamental — Wagner sign-off se código legacy depende exclusivo da session
**Refs:** [Laracopilot 2026](https://laracopilot.com/blog/laravel-multi-tenancy-saas-guide/), AUDIT-SENIOR-2026-05-25.md §3.2

### US-COM-008 · Throttle 60/1 em /compras + FormRequest ListarComprasRequest

> owner: — · priority: p0 · estimate: 2h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/Compras/Routes/web.php` · `Modules/Compras/Http/Requests/ListarComprasRequest.php` · `Modules/Compras/Tests/Feature/GapsHardeningTest.php` · verificado@176f9bc (2026-07-01) — falta Pest comportamental do 429 (61º request; GapsHardeningTest é source-grep de throttle:60,1, admite diferimento pra CI MySQL que nunca nasceu)

**Acceptance:**
- [ ] `Route::middleware(['web', 'throttle:60,1'])` em route group `/compras`
- [ ] FormRequest `ListarComprasRequest` com validação de filtros (period, status, supplier_id)
- [ ] Pest cobre rate limit (61º request → 429)

**Refs:** AUDIT-SENIOR-2026-05-25.md §3.3

### US-COM-009 · Validar JOIN scope contacts.business_id em TransactionUtil::getListPurchases (R1 leak)

> owner: — · priority: p0 · estimate: 3h · status: done · type: story
> blocked_by: —

**Implementado em:** `app/Utils/TransactionUtil.php` · `Modules/Compras/Tests/Feature/MultiTenantSqlGuardTest.php` · verificado@176f9bc (2026-07-01) — hotfix scope contacts.business_id no leftJoin de getListPurchases (L4916); guard SQL toSql() cobre Purchases/Sells/Expenses (R1 risk register fechado)

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

**Implementado em:** _pendente_ — entry `Compras:` ainda ausente em `config/governance/module_clients.yaml` (36 módulos listados, nenhum Compras)

**Acceptance:**
- [ ] Adicionar entry Compras em `config/governance/module_clients.yaml`
- [ ] Larissa @ ROTA LIVRE biz=4 como `piloto_reportando_dor` (+8 pts D5)
- [ ] Sinal qualificado já CONFIRMADO no DISCOVERY 2026-05-21 ("ela compra e tem entrada por grade")
- [ ] Pós Onda CONSOLIDAR: promover pra `biz_4_rota_livre_prod` (+15 pts) via canary 7d

**Refs:** ADR 0105 (Cliente como sinal qualificado), AUDIT-SENIOR-2026-05-25.md §6

## 9. Backlog vindo do Capterra-Inventário (Onda 2.1)

> Origem: [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md) (19 capacidades, nota FICHA 30/100) — Passo 2 do programa de ondas. Governança: [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).
> **Materialização:** US escritas direto no SPEC (`US-COM-011..020`) — que é **exatamente o que o `tasks-create` do MCP faz** (grava US no SPEC do módulo → webhook GitHub→MCP sincroniza no push). O projeto Compras já está no MCP (a nota §8 de "bypass" era stale, corrigida). O `task_id` canônico é o próprio `US-COM-NNN`. Metadata de task fica na linha `>` de cada US (`parent_plan` / `tags` / `cycle`). Daqui pra frente, novas US podem nascer via `tasks-create module:Compras` (gera `US-COM-021+`).
> **Cycle:** — (sem cycle ativo no fechamento — brief 2026-07-03).

### 9.0 Ledger da deduplicação adversarial

Cada uma das 16 tasks propostas no CAPTERRA-INVENTARIO cruzada contra este SPEC (US-COM-001..010) + backlog + código real (`Modules/Compras`, `Modules/NfeBrasil`). Veredito:

| # proposta (CAPTERRA) | Cap. | Veredito | Destino |
|---|:-:|---|---|
| 1 · Ponte DF-e → Compra | C01 | **DUPLICA US-COM-003** (pending Wave 6; `DistribuicaoDfeService`/`ManifestacaoService`/`NfeDfeRecebido` já existem no NfeBrasil, `ImportarDfeComoCompraService` ausente) | não recriada — segue em US-COM-003 |
| 2 · Teste E2E custo/total/estoque | C04 | **NOVA** | US-COM-011 |
| 3 · Matching XML→produto (EAN+xProd) | C02 | **NOVA** (US-COM-003 R-COM-202 cobre só match de fornecedor por CNPJ; match de produto por EAN não está em nenhuma US) | US-COM-012 |
| 4 · Recebimento parcial | C03 | **NOVA** | US-COM-013 |
| 5 · 3-way match | C05 | **SEGURADA** (⏸️ sinal pendente ADR 0105) | não entra no backlog ativo |
| 6 · FSM estágios persistida | C09 | **NOVA** (§5 lista FSM canônico como out-of-scope V1 / fase 3, não como US — vira item de backlog fase 3) | US-COM-014 |
| 7 · Teste invariante de estoque | C07 | **NOVA** | US-COM-015 |
| 8 · Cobrir `/compras`→contas a pagar (teste) | C08 | **NOVA** | US-COM-016 |
| 9 · GradeMatrixInput smoke+canary | C10 | **DUPLICA US-COM-005** (in_progress; `GradeMatrixInput.tsx` + `PurchaseGradeMatrixTest.php` já existem, falta smoke/canary Larissa) | não recriada — segue em US-COM-005 |
| 10 · Supplier scorecard | C11 | **SEGURADA** (⏸️ sinal pendente ADR 0105) | não entra no backlog ativo |
| 11 · Aprovação / alçada | C12 | **SEGURADA** (⏸️ sinal pendente ADR 0105) | não entra no backlog ativo |
| 12 · PiiRedactor Drawer + `module_clients.yaml` | C17 | **PARCIAL** — `module_clients.yaml` **DUPLICA US-COM-010** (todo); "PiiRedactor no Drawer" **RETIRADA** (Wagner 2026-07-03: o operador do ERP precisa ver CNPJ/telefone/email do fornecedor — a regra de PII é de git/log/IA, não de UI) | entry yaml segue em US-COM-010; US-COM-017 retirada |
| 13 · Autosave rascunho | C16 | **NOVA** | US-COM-018 |
| 14 · Eager-load `->with` em `listarCompras` | C15 | **NOVA** | US-COM-019 |
| 15 · A11y drawer | C18 | **NOVA** | US-COM-020 |
| 16 · Atalhos teclado com handlers | C19 | **SEGURADA** (🟡 sinal baixo — Larissa não é power-user) | não entra no backlog ativo |

**Saldo:** 9 US novas ativas (US-COM-011..016, 018..020) · 1 retirada (US-COM-017 — redação de PII na UI não se aplica a ERP) · 3 já eram US (003/005/010) · 4 seguradas (feature-wish ADR 0105). Zero duplicação.

### US-COM-011 · Teste E2E de cálculo custo/total/estoque da compra (Tier 0 valor/estoque)

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como time, quero um teste E2E que submete uma compra (com grade + frete + desconto + imposto) e assere `final_total`, `purchase_lines` e a movimentação de estoque (`variation_location_details.qty_available`) **persistidos**, pra blindar o Tier 0 valor/estoque (1 célula de grade = 1 SKU × custo × qty = write de estoque).

**Acceptance:**
- [ ] Pest E2E que faz `POST /purchases` com payload realista (grade expandida) biz=1
- [ ] Assere `final_total` correto por 2 caminhos (recompute à mão + soma das lines) — regra-mestre cálculo de valor
- [ ] Assere `purchase_lines` (qty × unit_cost) + `variation_location_details.qty_available` por variação/local
- [ ] Substitui os hardening tautológicos (`GapsHardeningTest`/`GapsP1HardeningTest` são `file_get_contents`+`str_contains`)

**Refs:** CAPTERRA C04 🟡, proibicoes.md "CÁLCULO DE VALOR ou ESTOQUE" (Tier 0), ADR 0101 (biz=1 nunca cliente).

### US-COM-012 · Matching automático XML→produto (EAN + xProd; fallback manual)

> owner: — · priority: p0 · estimate: M · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: US-COM-003

Como user importando DF-e, quero que cada item do XML seja auto-matchado ao produto do catálogo por EAN (`cEAN`) e, no fallback, por similaridade de `xProd`, com resolução manual pro que não casar, pra não mapear item a item.

**Acceptance:**
- [ ] Match produto por `cEAN`/`cEANTrib` → `variations.sub_sku`/barcode
- [ ] Fallback por `xProd` (similaridade) + UI de resolução manual do não-matchado
- [ ] Complementa o match de **fornecedor** por CNPJ que já vive em US-COM-003 (R-COM-202)

**Refs:** CAPTERRA C02 ❌. Depende do import (US-COM-003).

### US-COM-013 · Recebimento parcial (qty recebida por linha ≠ pedida + trânsito residual + autosave check-in)

> owner: — · priority: p0 · estimate: M · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como user, quero registrar recebimento parcial (qty recebida por linha diferente da pedida), com o residual permanecendo em trânsito e autosave do check-in, porque vestuário recebe lote incompleto de verdade.

**Acceptance:**
- [ ] Modelo de recebimento parcial por linha (qty_recebida vs qty_pedida)
- [ ] Residual permanece em trânsito (não fecha a compra)
- [ ] Autosave do check-in em progresso

**Refs:** CAPTERRA C03 ❌. Líderes: Lightspeed/Shopify/Zoho.

### US-COM-014 · FSM de estágios PERSISTIDA + auditável

> owner: — · priority: p1 · estimate: M · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como time, quero que os estágios da compra parem de ser `const STAGES` só-UI (`Compras/components/Drawer.tsx`) mapeados sobre `transactions.status` legacy, e virem estado persistido + histórico + transição gateada, pra a tela não "mentir" Recebido enquanto o banco diz `pending`.

**Acceptance:**
- [ ] Estado persistido (coluna `stage` ou `spatie/laravel-model-states`) + history append-only
- [ ] Transição gateada (não UPDATE direto)
- [ ] Alinhado ao FSM canônico [ADR 0143]

**Nota de escopo:** §5 lista integração FSM canônico como out-of-scope V1 (fase 3). Esta US é o item de backlog dessa fase 3 — **não sobe sem sinal/decisão de priorização**.
**Refs:** CAPTERRA C09 ❌, ADR 0143.

### US-COM-015 · Teste de invariante de estoque no fluxo de entrada

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como time, quero teste cobrindo a movimentação de estoque na entrada de compra (`ProductUtil::createOrUpdatePurchaseLines`+`updateProductQuantity`), pareando com US-COM-011.

**Acceptance:**
- [ ] Pest que assere `qty_available` por variação/local antes→depois da entrada
- [ ] Cobre o guard Tier 0 `assertPurchaseVariationsOwnership`

**Refs:** CAPTERRA C07 🟡. Pareia com US-COM-011.

### US-COM-016 · Cobrir fluxo `/compras`→contas a pagar (Observer Financeiro) com teste

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como time, quero teste do fluxo compra→`fin_titulos` type=pagar (via `TransactionObserver`), porque hoje é herdado de `/purchases/store` e não é capacidade própria testada.

**Acceptance:**
- [ ] Pest que assere criação de `fin_titulos` type=pagar ao salvar compra biz=1
- [ ] Cobre o caminho `/compras` (não só `/purchases`)

**Refs:** CAPTERRA C08 🟡.

### US-COM-017 · ~~PiiRedactor no Drawer de compra~~ → RETIRADA (2026-07-03)

> status: retirada · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1]
> blocked_by: —

**Retirada por Wagner (dono do negócio) 2026-07-03.** Redigir `tax_number` (CNPJ/CPF), `mobile` e `email` do **fornecedor** no Drawer **contradiz a função do ERP**: o operador de compras precisa ver esses dados pra trabalhar (Wagner textual: *"isso vai ser problema no erp, aqui eu preciso ter a informação"*). A CAPTERRA C17 importou indevidamente a regra de PII — que é de **git / commit / log / PR / resposta de IA** (`[REDACTED]`/`PiiRedactor`, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) + regras-time.md) — pra **camada de UI**, onde não se aplica. Base legal do tratamento: execução de contrato/compra (LGPD Art. 7º).

O que **de fato** protege dados no ERP já existe e **não** é redação de tela: (1) RBAC Spatie por permission (quem abre o módulo — Camada 3 do multi-tenant), (2) minimização em **logs/exports/saída de IA** (é aí que o `PiiRedactor` mora), (3) retention/purge + DSR (Modules/Jana). Nada disso apaga o campo pro operador autorizado.

**Reabre só com sinal** (ADR 0105): um business com role de baixo privilégio (ex.: conferente) que **não deva** ver o contato do fornecedor → aí sim mascaramento por role. Larissa (piloto, operador único) não tem esse split.

**Nota:** o entry de Compras em `config/governance/module_clients.yaml` (a parte legítima do item nº12) segue em **US-COM-010** — não é afetado por esta retirada.

### US-COM-018 · Autosave rascunho de compra (`localStorage` `{biz}.{user}` debounced)

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como Larissa (atende telefone no meio da compra), quero rascunho auto-salvo por `{biz}.{user}` pra não perder o que digitei.

**Acceptance:**
- [ ] Draft debounced em `localStorage` chaveado por `{business_id}.{user_id}`
- [ ] Avaliar placement (forms de compra vivem em `/purchases` — C1)

**Refs:** CAPTERRA C16 ❌ (sinal médio). Placement a decidir com US-COM-005/Purchase.

### US-COM-019 · Eager-load `->with(['contact','location'])` em `listarCompras().paginate()`

> owner: — · priority: p2 · estimate: XS · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como time, quero eliminar N+1 nas rows do cockpit garantindo eager-load das relações renderizadas (`ComprasService::listarComprasInterno` monta a query via `getListPurchases` sem `->with`).

**Acceptance:**
- [ ] Eager-load das relações usadas nas rows (validar se vêm do JOIN existente ou lazy)
- [ ] Sem regressão no filtro `?q=` (que já usa `contacts.*`)

**Refs:** CAPTERRA C15 🟡. Esforço XS.

### US-COM-020 · A11y do drawer (`role=dialog` + focus-trap + `aria-label` + `Esc`)

> owner: — · priority: p3 · estimate: 3h · status: todo · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como usuário de teclado/leitor de tela, quero o drawer acessível (WCAG 2.1 AA) — `role=dialog`, focus-trap, `aria-label` no botão fechar, handler `Esc`.

**Acceptance:**
- [ ] `role="dialog"` + `aria-modal` + focus-trap
- [ ] `aria-label` no botão fechar + `Esc` fecha
- [ ] Herdado do protótipo F1

**Refs:** CAPTERRA C18 🟡, WCAG 2.1 AA.

### 9.1 Seguradas (feature-wish ADR 0105 — NÃO no backlog ativo)

Sem dor reportada por cliente / sinal qualificado, estas **não** viram US ativa (ADR 0105 — cliente como sinal). Ficam catalogadas como wish, reabrem com sinal:

- **3-way match (PO↔Recebimento↔NF-e)** (C05, P0-teto) — só com overpayment reportado; depende US-COM-003+013.
- **Supplier scorecard (OTIF/lead-time/defect/fill-rate)** (C11, P1) — sem dor reportada.
- **Aprovação / workflow multi-nível (alçada)** (C12, P1) — PME loja (Larissa) pode não precisar.
- **Atalhos de teclado com handlers** (C19, P3) — declarados no footer sem funcionar; Larissa não é power-user (risco de expectativa frustrada baixa prioridade).
