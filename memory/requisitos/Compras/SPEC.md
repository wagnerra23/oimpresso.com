---
id: requisitos-compras-spec
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

> owner: — · priority: p0 · estimate: 4h · status: done · type: story · parent_plan: programa-ondas · tags: [capterra-gap, onda-2.1] · cycle: —
> blocked_by: —

Como time, quero um teste E2E que submete uma compra (com grade + frete + desconto + imposto) e assere `final_total`, `purchase_lines` e a movimentação de estoque (`variation_location_details.qty_available`) **persistidos**, pra blindar o Tier 0 valor/estoque (1 célula de grade = 1 SKU × custo × qty = write de estoque).

**Aceite:**
- [x] Pest E2E que faz `POST /purchases` com payload realista (grade expandida) biz=1
- [x] Assere `final_total` correto por 2 caminhos (recompute à mão + soma das lines) — regra-mestre cálculo de valor
- [x] Assere `purchase_lines` (qty × unit_cost) + `variation_location_details.qty_available` por variação/local
- [x] **NÃO deletar `GapsHardeningTest`/`GapsP1HardeningTest` wholesale** (o critério original dizia "substituir os hardening tautológicos" — PERIGOSO). O E2E prova valor/estoque; ele **não cobre** a superfície dos hardening (business_id, throttle, whitelist do `ListarComprasRequest`, JOIN scope, OTel). O **bloco Gap #4 (`ListarComprasRequest`, L53–142 de `GapsHardeningTest`) é comportamental** — instancia o FormRequest e assere as whitelists `stage`/`sort`/`per_page` (anti-SQLi/DOS) + `authorize()` (anti-IDOR) — e **PERMANECE**. Só os source-greps tautológicos `file_get_contents`+`str_contains` (Gap #2/#3 em L18–51 + L144–154; `GapsP1HardeningTest` inteiro) são de baixo valor e podem ser podados **à parte** quando houver comportamental equivalente (o JOIN scope já tem `MultiTenantSqlGuardTest`, US-COM-009).

**Implementado em:** `Modules/Compras/Tests/Feature/PurchaseCalculoValorEstoqueE2ETest.php` · verificado@83aca49 (2026-07-17) — E2E real POST /purchases (grade 2×2 + frete + desconto% + imposto) biz=1 provando final_total, total_before_tax, tax, shipping e discount por 2 caminhos + purchase_lines (qty×custo por variation_id) + variation_location_details.qty_available (delta de estoque por variação/local), com blindagem anti-num_uf ×100 (PR #3722; registrado em phpunit.xml linha 57). O DELIVERABLE desta US é o próprio teste E2E.
**Testado em:** `Modules/Compras/Tests/Feature/PurchaseCalculoValorEstoqueE2ETest.php` (@covers-us US-COM-011)

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

**Decisão (coordenação 2026-10-26, a partir da recomendação da frente Compras):** o recebimento parcial vai **pelo pedido de compra**, que o UltimatePOS já recebe em partes (`purchase_lines.po_quantity_purchased`, status `ordered → partial → completed` em `TransactionUtil::updatePurchaseOrderStatus`). Sem coluna nova: cada entrega vira uma compra ligada ao pedido, e o que falta fica no pedido. A tela React de compra passa a deixar escolher o pedido e mostra por linha Pedido / Já recebido / A receber. A opção do protótipo (quantidade recebida dentro da compra, com resíduo em trânsito, `CmpRecebimento` em `fsm-regras.jsx`) foi descartada porque mudaria quando o estoque entra. Mexe em estoque: regra mestre (dupla prova + antes→depois). Depende da US-COM-029 (trava entre empresas no mesmo caminho). O "autosave do check-in" do aceite acima sai do escopo desta US.

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

### US-COM-021 · Investigar flakiness das baselines dark/empty do VRT e reabilitar no gate L2

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

- Contexto: charter `Compras/Index.charter.md` registra poda deliberada — `states: [default, loading]` com nota "dark/empty podados: baselines flaky (não-reproduzíveis run-a-run no CI)".
- Motivação: medição 12/jun–10/jul mostrou dark = vetor nº 1 de re-trabalho visual do mês; Compras é a única tela do manifesto L2 sem dark.

**Acceptance:**
- [ ] Identificar a fonte de não-determinismo run-a-run (dados seedados? animação? fonte? data relativa?)
- [ ] Reproduzir 2 runs consecutivos do update-mode com snap idêntico (hash igual)
- [ ] Reabilitar `dark` (e `empty` se estabilizar) no charter + `tests/Browser/visreg-states.json` juntos (lint L2 exige sync)
- [ ] Regenerar baselines via workflow_dispatch update-mode

**Refs:** medição re-trabalho visual sessão 2026-07-10 · NÃO re-adicionar sem prova de reprodutibilidade (a poda no charter é decisão consciente).

### US-COM-022 · Lane required de Compras vira árvore-menos-quarentena (vermelha no main há 5 runs)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — o workflow `.github/workflows/compras-pest.yml` segue com allowlist inline; `.github/compras-pest-quarantine.list` não existe.

- Contexto: a lane `PHP / Pest (Compras · MySQL)` é **required** (`governance/required-checks-baseline.json`, promovida 2026-08-05 pela ADR 0369) e os **5 últimos runs no `main` estão em `failure`**. O step que falha é `Run Pest (Compras · MySQL) — ALLOWLIST VERDE (catraca)` — falha de teste, não de infra (a própria ADR 0369 classificou na promoção: compras 6, 0 infra).
- Por que é p0 mesmo sem dor de cliente: gate permanentemente vermelho **não detecta regressão nova** (ela entra escondida no meio das falhas), e como a lane é skip-as-pass o vermelho só cai sobre quem tocar os paths de Compras — dívida de terceiros bloqueando o PR de quem chegou. Foi exatamente o que travou o [#5378](https://github.com/wagnerra23/oimpresso.com/pull/5378) no Estoque.
- Medido em `origin/main` 2026-08-07: árvore `Modules/Compras/Tests/**Test.php` = **10** · nomeados na allowlist inline = **7** · fora = **3** (`ComprasScaffoldTest`, `ComprasSmokeRoutesTest`, `PurchaseCalculoValorEstoqueE2ETest`).
- ⚠️ `Modules/Compras/Tests` **está** no `phpunit.xml`, mas **registro não é execução** (§5 proibicoes 2026-08-02): se outra lane executa os 3 de fora, isso é **dado a medir**, não a supor.
- Receita já provada 2× no repo: `.github/workflows/financeiro-pest.yml` (origem) e `.github/workflows/estoque-pest.yml` ([#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387), mergeado 2026-08-07 — o `main` saiu de 3 `failure` seguidas para `success` em `888db02a6c4`).

**Acceptance:**
- [ ] `run-set` = tudo em `Modules/Compras/Tests/**Test.php` **menos** `.github/compras-pest-quarantine.list` (partição total — arquivo novo entra rodando, sem terceiro estado escuro)
- [ ] cada linha da quarentena nomeia **qual contrato falha** (UC + eixo); sem motivo escrito vira gaveta
- [ ] step de anti-apodrecimento: falha se path listado não existir mais
- [ ] quarentena impressa por extenso em todo run (quarentena silenciosa reconstrói o ponto cego)
- [ ] `merge=union` pro `.list` no `.gitattributes`
- [ ] status dos 3 de fora **medido** (rodados), não presumido, antes de entrar ou ficar de fora
- [ ] lane verde no `main` — com `assertions > 0` no sumário JUnit, não só `0 failed` (LC-13)

**Refs:** ADR 0369 · PR #5387 (modelo) · §5 proibicoes 2026-08-04 (isenção que esvazia o gate) · LC-13 (verde por não-execução).

### US-COM-023 · Teste de 7 dias do cockpit Compras na ROTA LIVRE (biz=4)

> owner: maiara · sprint: 2026-W44 · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: US-COM-003, US-COM-015, US-COM-022, US-FISCAL-018

**Implementado em:** _pendente_ — é fase de liberação, não código; o cockpit `/compras` não está habilitado para nenhum business.

- Janela planejada (cronograma de 2026-10-14): 28/out → 04/nov/2026, depois do teste do Fiscal na biz=4 (US-FISCAL-018). Uma fase por vez no mesmo cliente, para saber qual módulo causou um eventual problema.
- Antes: aviso prévio à Larissa; smoke na biz=1; US-COM-003, US-COM-015 e US-COM-022 fechadas.
- Regra mestre de valor/estoque: custo e estoque conferidos por dois caminhos + tabela antes→depois aprovada por [W] antes de ligar.
- Ligar só pelo pacote no superadmin, nunca por `business_id` no código.
- Inclui a grade tamanho×cor (US-COM-005).

**Acceptance:**
- [ ] 7 dias na biz=4 sem incidente
- [ ] smoke real em produção com recibo (status HTTP + screenshot)

### US-COM-024 · Custo do insumo numa data, pelo método da empresa (UC-FLX-38)

> owner: wagner · priority: p1 · estimate: 2h · status: doing · type: story

Como quem orça e produz, quero saber quanto um insumo custava num dia qualquer, para que o orçamento e a OS guardem o custo daquele dia e uma compra nova não reescreva o passado. É a base dos casos UC-FLX-33 a 37 da tela de fluxos.

**Implementado em:** `Modules/Compras/Services/CustoDoInsumoService.php` · `Modules/Compras/Database/Migrations/2026_10_20_000001_create_compras_insumo_metodo_custo_table.php` · `Modules/Compras/Entities/InsumoMetodoCusto.php` · `Modules/Compras/Tests/Feature/CustoDoInsumoServiceTest.php` · `Modules/Compras/Http/Controllers/InsumoMetodoCustoController.php` · `Modules/Compras/Tests/Feature/InsumoMetodoCustoEndpointTest.php` · `resources/js/Pages/Manufacturing/Insumos.tsx` · `Modules/Compras/Services/MetodoCustoDoProduto.php` · `Modules/Compras/Resources/views/partials/metodo_custo_produto.blade.php` · `Modules/Compras/Tests/Feature/MetodoCustoNoCadastroDoProdutoTest.php`

- Fonte: `purchase_lines` + `transactions.transaction_date`. O histórico já existia; `variations.dpp_inc_tax` guarda só o último preço.
- Método: o da regra "Atualizar o custo dos insumos" da empresa (`RegrasCompra`, `metodo`), padrão `medio`.
- Regra `por_insumo`: cada insumo diz o seu método na tabela-ponte `compras_insumo_metodo_custo` (`variations` é core, não ganha coluna). O método se escolhe na gaveta do insumo em `/manufacturing/insumos` (seção "Método de custo"), gravado por `PUT /compras/insumos/{variation}/metodo-custo` com `purchase.update`; variação de outra empresa responde 404. No cadastro do produto (formulário Blade de novo e de editar) o campo "Método de custo" vale para todas as variações, gravado pelo gancho `after_product_saved` do módulo; só aparece com o módulo Compras instalado (`system.compras_version`).
- Só leitura: o serviço não grava nada.

**Acceptance:**
- [x] Médio ponderado pela quantidade líquida (recebida − devolvida) das compras recebidas até o fim do dia pedido
- [x] Último preço = compra recebida mais recente até o fim do dia
- [x] Fora da conta: compra não recebida, saldo inicial, transferência e custo de fabricação
- [x] Sem compra até a data: custo nulo com o motivo
- [x] Tier 0: só compras da empresa pedida (teste com a mesma variação em outra empresa)
- [x] Método `por_insumo`: usa o método gravado no insumo (`medio` ou `ultimo`); insumo sem método → custo nulo com o motivo
- [x] Tier 0: o método que outra empresa deu ao mesmo insumo não vale
- [x] Tela para definir o método do insumo (drawer Insumos)
- [x] Campo no cadastro do produto, para item que não é ingrediente de receita (matéria-prima ou revenda)
- [ ] Consumidores: orçamento (UC-FLX-33) e OS (UC-FLX-37) passam a usar o serviço

### US-COM-025 · Rastreio do rolo: em que OS cada fração da mídia foi usada (UC-FLX-18) — **P2**

> owner: wagner · priority: p2 · estimate: 3h · type: story

Como quem compra e controla a mídia (bobina de lona, vinil, papel em rolo), quero abrir um rolo recebido e ver em que OS cada fração dele foi usada e quanto ainda sobra, para saber o custo de cada trabalho e de onde veio o defeito de um lote.

**Implementado em:** `Modules/Producao/Services/RastreioDoRolo.php` · `Modules/Producao/Http/Controllers/LoteRastreioController.php` · `Modules/Producao/Services/ConsumoMaterialService.php`

**Testado em:** `Modules/Producao/Tests/Feature/ProducaoRoloRastreioContratoTest.php` (lane `verticais-pest.yml`).

**Origem:** UC-FLX-18 da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:71`, etapa Recebido, fonte Mubisys, porte pequena, sem US até aqui). O que já existe (medido 2026-10-20):

- O lote é a linha da compra: `purchase_lines.lot_number`, ligado por empresa em `business.enable_lot_number` (`app/Http/Controllers/BusinessController.php:440`).
- A baixa da OS já sabe de que lote saiu (US-PCP-021): `Modules/Producao/Services/ConsumoMaterialService.php:122` grava a fração em `transaction_sell_lines_purchase_lines` pelo `mapPurchaseSell` (tipo `production_purchase`), e `pcp_consumos` liga a baixa à OS (`ordem_id`, `transaction_sell_line_id`).
- O lote é escolhido sozinho pelo método da empresa (FIFO/LIFO); o `mapPurchaseSell` já aceita `lot_no_line_id` (`app/Utils/TransactionUtil.php:3264`), mas a baixa da OS não o passa.

O que falta, e é o escopo desta US: a leitura "rolo → OS" (lote, frações por OS, saldo do rolo) e a escolha do rolo na baixa. Fora daqui: tabela `product_batches` própria, FEFO e picker de lote no PDV (US-INV-006 a US-INV-010, Estoque, adiadas por P7).

**Aceite:**
- [x] Dado um lote (`purchase_line_id`) recebido, `GET /producao/lotes/{id}/rastreio` lista cada OS que usou fração dele: OS, venda, etapa, quantidade, data e quem apontou, mais o saldo do rolo (recebido − devolvido − vendido − ajustado − usado em produção, as colunas que o `mapPurchaseSell` confere) (UC-RLT-04).
- [x] A soma das frações listadas é igual à quantidade do lote em `transaction_sell_lines_purchase_lines` das `production_sell`; dois caminhos no teste (UC-RLT-04).
- [x] A baixa da OS aceita o lote escolhido (`lote_id` → `lot_no_line_id`); sem escolha segue o método da empresa, como hoje; lote de outro material, de outro local, de outra empresa, de compra não recebida ou sem saldo para a quantidade inteira recusa e nada baixa (UC-PCMAT-08).
- [x] Tier 0: lote de outra empresa responde 404 (teste com lote da empresa 99 do mesmo formato; `business_id` em toda consulta) (UC-RLT-05).
- [x] Só quem tem `purchase.view` (e o módulo Produção no pacote) abre o rastreio; sem a permissão, 403 (UC-RLT-05).
- [ ] Tela: o rastreio dentro do relatório de Lotes (hoje só o JSON). Ver o `[BACKLOG]` de `Relatorios/Lotes/Index.casos.md`.
- [ ] Separar no relatório de Lotes a fração usada em OS da venda (a `consultaLotes` soma a baixa da OS em "vendido").

### US-COM-026 · Compra recebida aplica a regra de custo e preço (UC-FLX-17)

> owner: wagner · priority: p1 · estimate: 3h · status: doing · type: story

Como dono que configurou a regra "Atualizar o custo dos insumos" na aba Fluxos, quero que a compra, ao ficar recebida, grave o custo do insumo pelo método da regra e trate o preço de venda como a regra manda, para que o custo não seja o último preço digitado e o preço não mude sem eu escolher.

**Implementado em:** `Modules/Compras/Services/AplicarCustoDoRecebimento.php` · `Modules/Compras/Tests/Feature/AplicarCustoDoRecebimentoTest.php`

- Origem: UC-FLX-17 da tela de fluxos (etapa Recebido). Até aqui o recebimento não lia `RegrasCompra`: com `business.enable_editing_product_from_purchase` ligado, `ProductUtil::updateProductFromPurchase` gravava o último preço em `variations.default_purchase_price`/`dpp_inc_tax`, inclusive em compra pendente.
- Só vale para a empresa que gravou a regra de custo (`fsm_modelos_empresa.regras_compra` com a chave `insumo.custo`), ligada e disparando em Recebido. Empresa que nunca gravou a regra: comportamento de antes, sem nenhuma mudança.
- Custo: `CustoDoInsumoService::naData` (US-COM-024) na data da compra, pelo `metodo` da regra. Custo nulo (sem compra recebida ou método sem resposta): a variação não muda.
- Preço (`preco`): `so_custo` não muda o preço; `aplicar` recalcula o preço mantendo a margem atual da variação; `propor` grava uma proposta em `compras_propostas_preco` e não muda o preço.
- Fora daqui (follow-up): a fila de aprovação das propostas (`aprovacao`), `regraEm`, a etapa Conferido e a propagação para os produtos fabricados.

**Acceptance:**
- [x] Recebimento com a regra grava o custo pelo método da regra, em `default_purchase_price` e `dpp_inc_tax`
- [x] `so_custo`: preço igual, margem recalculada; `aplicar`: preço novo com a margem de antes; `propor`: proposta pendente com custo e preço antes/depois, preço igual
- [x] Com a regra, compra pendente não muda custo nem preço (a edição de produto pela compra do core fica desligada)
- [x] Empresa sem a regra gravada: o core segue como antes (teste do último preço)
- [x] Tier 0: só variações da empresa da compra; a outra empresa não muda
- [ ] Fila de aprovação das propostas — a do recebimento é a US-COM-028; Fabricação e Pendências seguem abertas

### US-COM-027 · Estoque mínimo vira requisição de compra (UC-FLX-19) — **P2**

> owner: — · priority: p2 · estimate: 3h · status: done · type: story
> blocked_by: —

Como quem compra, quero que o item que caiu abaixo do mínimo no local apareça sozinho numa requisição de compra, com a quantidade que falta, para não depender de alguém lembrar de olhar o relatório de alerta.

**Implementado em:** `Modules/Compras/Services/RequisicaoEstoqueMinimoService.php` — job diário (`RequisicaoEstoqueMinimoJob` + `compras:requisicao-estoque-minimo`), ligado na tela Fluxos (`app/Http/Controllers/FluxosController.php` · `requisicaoEstoqueMinimo`, interface `app/Contracts/Compras/RequisicaoEstoqueMinimo.php`); fornecedor visível em `resources/js/Pages/PurchaseRequisition/Index.tsx`.

**Testado em:** `Modules/Compras/Tests/Feature/RequisicaoEstoqueMinimoContratoTest.php` (@covers-us US-COM-027 · UC-REQMIN-01..08). A coluna Fornecedor (UC-REQC-06) e o interruptor em Fluxos (UC-FLXT-24) são provados nos testes de contrato das respectivas telas.

**Origem:** UC-FLX-19 da tela de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:72`, "Abaixo do mínimo → Cria a requisição com o fornecedor da última compra", fonte Mubisys, planejado). O que já existia (medido 2026-10-20):

- O mínimo é `products.alert_quantity` (por produto, não por local) — não há campo de mínimo no Compras.
- A requisição é do núcleo: `transactions.type = purchase_requisition` + `purchase_lines`, criada à mão em `app/Http/Controllers/PurchaseRequisitionController.php:314` (status `ordered`; `partial`/`completed` quando vira pedido).
- A tela de criar já lista os itens no alerta (`getRequisitionProducts`, `:481`, `qty_available <= alert_quantity`), mas só sob demanda — nada gerava a requisição sozinho.

**Aceite:**
- [x] Job diário por business: variação com saldo no local ABAIXO de `alert_quantity` entra numa requisição do núcleo daquele local, com quantidade = teto(mínimo − saldo). _(UC-REQMIN-01)_
- [x] Uma requisição automática aberta por local: rodar de novo acrescenta só o item novo; item já numa requisição aberta (`ordered`/`partial`) do local, automática ou manual, não é pedido de novo. _(UC-REQMIN-02/03)_
- [x] Desligado por padrão (`business.common_settings['compras_requisicao_estoque_minimo']`). _(UC-REQMIN-04)_
- [x] Não cria pedido de compra e não move estoque. _(UC-REQMIN-05)_
- [x] Tier 0: job recebe `$businessId`; produto e local de outro business nunca entram. _(UC-REQMIN-06, 98 × 99)_
- [x] A requisição sai com o fornecedor e o preço da última compra recebida do item: o fornecedor vai em `transactions.contact_id`, então há uma requisição automática por local **e fornecedor**; item nunca comprado fica numa sem fornecedor, com preço 0. A lista de requisições ganha a coluna Fornecedor. _(UC-REQMIN-07/08 · UC-REQC-06)_
- [x] Tela para ligar/desligar a regra: card Compra da aba Fluxos da empresa (`/fluxos?aba=empresa`), com `fluxos.configurar` — a permissão das outras regras da empresa nessa tela, não uma de Compras. _(UC-FLXT-24)_

### US-COM-030 · MRP: necessidade de compra somando todas as OS, com prazo do fornecedor — **P1**

> owner: — · priority: p1 · status: todo · type: story
> blocked_by: —

Como comprador, quero ver numa lista só quanto falta de cada insumo somando todas as OS abertas e o estoque mínimo, por local, e até quando preciso pedir pelo prazo de entrega do fornecedor, para não comprar OS por OS nem descobrir a falta quando a OS já está parada.

**Implementado em:** _pendente_

- Origem: lacunas de 2026-10-25 (P1 Compras). Ordem MRP → cotação → qualidade e desenho ancorado no DS e no padrão de tela: **decisão coordenação 2026-10-26**.
- Reaproveita a falta de material por OS (`Modules/Producao/Services/FaltaDeMaterialDaOs.php`) e o estoque mínimo que já vira requisição (US-COM-027, `RequisicaoEstoqueMinimoService`). Não cria um segundo cálculo de falta.
- Campo novo: prazo de entrega em dias por (produto, fornecedor), por empresa. Data para pedir = data em que a primeira OS precisa do insumo − prazo do fornecedor.
- Daqui sai a requisição de compra (e, depois, a cotação da US-COM-031).
- Tier 0: tudo filtrado pela empresa do usuário.

### US-COM-031 · Cotação de compra entre fornecedores — **P1**

> owner: — · priority: p1 · status: todo · type: story
> blocked_by: US-COM-030

Como comprador, quero pedir preço e prazo a vários fornecedores para os itens de uma requisição, comparar as respostas lado a lado e transformar a melhor em pedido de compra, para não decidir pela memória nem por telefone.

**Implementado em:** _pendente_

- Origem: lacunas de 2026-10-25 (P1 Compras). Decisão coordenação 2026-10-26 (ordem e desenho).
- Itens podem vir da requisição de compra ou de outro módulo: a frente Verticais (Oficina, "catálogo de peças por veículo com cotação") vai consumir esta mesma cotação, sem criar uma própria (combinado em 2026-10-26).
- A cotação vencedora vira `transactions.type = purchase_order` pelo fluxo existente, e o pedido entra no recebimento parcial (US-COM-013).

### US-COM-032 · Aprovação do pedido de compra por alçada de valor — **P2**

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

Como dono, quero que o pedido de compra acima do limite de quem o fez espere a aprovação de gerente ou diretoria, com os limites de valor configurados pela minha empresa, para que compra grande não saia sem alguém ver.

**Implementado em:** _pendente_

- **Decisão [W] 2026-10-26:** a alçada fica **no pedido de compra**, com limites de valor configuráveis por empresa (comprador, gerente, diretoria). Tira da seção 9.1 o item "Aprovação / workflow multi-nível (alçada)".
- O protótipo de Fluxos já põe a alçada em Requisição → Pedido (`prototipo-ui/cowork/Wagner/fluxos-processos.jsx`).
- Hoje o pedido nasce sem passo de aprovação (`PurchaseOrderController@store`).

### US-COM-033 · Avaliação automática do fornecedor por entrega — **P3**

> owner: — · priority: p3 · status: todo · type: story
> blocked_by: US-COM-013

Como comprador, quero ver por fornecedor quantas entregas chegaram no prazo e completas, calculado das entregas registradas, para escolher fornecedor com dado e não com impressão.

**Implementado em:** _pendente_

- **Decisão coordenação 2026-10-26:** automática, derivada do recebimento parcial pelo pedido (US-COM-013), sem nota digitada. Tira da seção 9.1 o item "Supplier scorecard".
- Prazo cumprido: data da compra que recebe × data de entrega prevista do pedido. Completa: soma recebida × soma pedida por linha.

### US-COM-034 · Inspeção de qualidade no recebimento da compra — **P2**

> owner: — · priority: p2 · estimate: 3h · status: doing · type: story
> blocked_by: US-COM-013

Como quem recebe mercadoria, quero inspecionar cada linha da compra recebida e marcar quanto foi aprovado e quanto foi reprovado, para que o reprovado não fique no estoque disponível e volte ao fornecedor como devolução.

**Implementado em:** _pendente_ — serviço `Modules/Compras/Services/InspecaoDoRecebimento.php`, tabela `compras_inspecoes` e o `InspecaoDoRecebimentoTest`, ainda sem veredito da lane `compras-pest`; a tela vem depois do protótipo (ADR 0438).

**Testado em:** `Modules/Compras/Tests/Feature/InspecaoDoRecebimentoTest.php`

- Origem: lacuna P2 de Compras (programa de lacunas, 2026-10-27), depois do recebimento parcial (US-COM-013). Diferente da US-PCP-033, que inspeciona a etapa da OS.
- Uma inspeção por linha: aprovado + reprovado = o que entrou pela linha (`quantity − quantity_returned`). Resultado: aprovado, reprovado ou parcial.
- O reprovado sai do estoque pela devolução de compra do núcleo, a mesma conta do `PurchaseReturnController@store`: `quantity_returned` += reprovado, estoque desce o mesmo, um `purchase_return` por compra com Σ `purchase_price_inc_tax × quantity_returned`. Vira crédito com o fornecedor.
- Desligada por padrão (`business.common_settings['compras_inspecao_recebimento']`).
- Tier 0: compra, linhas e devolução filtradas pela empresa.

**Aceite:**
- [ ] 10 recebidas, 7 aprovadas e 3 reprovadas: estoque 10 → 7 pelos dois caminhos e devolução de 3 × 12,50 = 37,50
- [ ] Tudo aprovado não mexe no estoque nem cria devolução
- [ ] Soma diferente do recebido, reprovado sem motivo e segunda inspeção da mesma linha são recusados
- [ ] Desligada, a inspeção é recusada; compra de outra empresa é recusada e nada muda
- [ ] Tela: inspecionar no detalhe da compra recebida (depois do protótipo)

### US-COM-028 · Aprovar ou recusar o preço proposto no detalhe da compra (UC-FLX-17) — **P1**

> owner: wagner · priority: p1 · estimate: 3h · status: doing · type: story

Como dono que deixou a regra de custo em "Propõe novo preço", quero ver no detalhe da compra recebida os preços propostos e aprovar ou recusar cada um, para que a proposta não fique esquecida numa tabela que ninguém lê.

**Implementado em:** _pendente_ — código no PR #9714 (serviço `PropostasDePreco`, `PropostasPrecoController`, seção `PrecosPropostos` no detalhe da compra e o `PropostasDePrecoContratoTest`), ainda sem veredito da lane `compras-pest`. A âncora vira path quando o teste ficar verde e a US fechar (ADR 0273 + doneness-lint ADR 0302: `doing` com path conflita).

**Testado em:** `Modules/Compras/Tests/Feature/PropostasDePrecoContratoTest.php`

- Origem: a US-COM-026 grava as propostas em `compras_propostas_preco`, sem lista nem decisão. A regra tem o parâmetro `aprovacao` (`recebimento` padrão · `fabricacao` · `pendencias`); esta US faz o `recebimento`.
- Aprovar: preço com imposto = `preco_proposto`; sem imposto = com imposto × 100 / (100 + imposto do produto); margem = (preço sem imposto − custo sem imposto atual) / custo × 100. Dá o mesmo resultado do `aplicar` no recebimento. Recusar não mexe na variação.
- Só aprova se o preço da variação ainda é o `preco_antes` da proposta; se mudou depois, a tela mostra e só deixa recusar.
- Exige `product.update`. Tier 0: proposta e variação filtradas pela empresa do usuário; proposta de outra empresa = 404.
- Enquanto `aprovacao` for `fabricacao` ou `pendencias`, a fila aparece aqui com aviso de que o lugar configurado ainda não existe.
- Regra em Conferido: a compra não tem essa etapa e o recebimento não aplica nada; o detalhe da compra avisa. Tratar a etapa é outro PR.

**Aceite:**
- [x] Detalhe da compra lista as propostas com custo e preço antes/depois e o status
- [x] Aprovar grava o preço proposto e devolve a margem de antes (dois caminhos no teste)
- [x] Recusar mantém o preço; preço mudado depois da proposta bloqueia a aprovação
- [x] Sem `product.update` = 403; outra empresa = 404
- [x] Aviso na tela quando a regra está em Conferido
- [ ] Fila na Fabricação (`aprovacao = fabricacao`) e nas Pendências da Visão geral

### US-COM-029 · Compra a partir de pedido não aceita pedido nem linha de outra empresa — **P0**

> owner: — · priority: p0 · estimate: 2h · status: doing · type: story
> blocked_by: —

Como dono de uma empresa, quero que a compra feita a partir de um pedido de compra só aceite pedidos e linhas de pedido da minha empresa, para que ninguém consiga mexer na quantidade recebida ou no status do pedido de outra empresa.

**Implementado em:** _pendente_ — trava `assertPurchaseOrderRefsBelongTo` no `PurchaseController` (store e update) e o `CompraDePedidoOutraEmpresaTest`, ainda sem veredito da lane `compras-pest`. A âncora vira path quando o teste ficar verde (ADR 0273 + doneness-lint ADR 0302).

**Testado em:** `Modules/Compras/Tests/Feature/CompraDePedidoOutraEmpresaTest.php`

- Origem (medido em 2026-10-26, `origin/main` `93d6a5d11c`): `purchase_order_ids` e `purchases.*.purchase_order_line_id` chegam crus do formulário e descem para `ProductUtil::updatePurchaseOrderLine` (`PurchaseLine::find`) e `TransactionUtil::updatePurchaseOrderStatus` (`Transaction::find`), sem filtro de empresa.
- O pedido tem de ser `purchase_order` da empresa do usuário; a linha, de um pedido da empresa. A linha não precisa estar na lista de pedidos, porque a edição pode tirar um pedido da lista e manter a linha já vinculada.
- Pré-requisito do recebimento parcial (US-COM-013), que reaproveita este caminho (pedido → compra com `po_quantity_purchased`).

**Aceite:**
- [ ] Pedido de outra empresa em `purchase_order_ids` = 422, pedido alheio intacto
- [ ] Linha de pedido de outra empresa = 422, `po_quantity_purchased` alheio intacto
- [ ] Pedido da própria empresa segue recebendo: 4 de 10 deixa o pedido `partial` (controle positivo)

### 9.1 Seguradas (feature-wish ADR 0105 — NÃO no backlog ativo)

Sem dor reportada por cliente / sinal qualificado, estas **não** viram US ativa (ADR 0105 — cliente como sinal). Ficam catalogadas como wish, reabrem com sinal:

- **3-way match (PO↔Recebimento↔NF-e)** (C05, P0-teto) — só com overpayment reportado; depende US-COM-003+013.
- ~~**Supplier scorecard (OTIF/lead-time/defect/fill-rate)** (C11, P1) — sem dor reportada.~~ → ativa como US-COM-033 (decisão coordenação 2026-10-26).
- ~~**Aprovação / workflow multi-nível (alçada)** (C12, P1) — PME loja (Larissa) pode não precisar.~~ → ativa como US-COM-032 (decisão [W] 2026-10-26).
- **Atalhos de teclado com handlers** (C19, P3) — declarados no footer sem funcionar; Larissa não é power-user (risco de expectativa frustrada baixa prioridade).
