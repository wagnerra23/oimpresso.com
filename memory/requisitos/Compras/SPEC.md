---
slug: compras
title: "Especificação funcional — Compras"
type: spec
module: Compras
status: proposed
related_adrs: [0093, 0094, 0101, 0104, 0105, 0106, 0107, 0114, 0143]
pii: false
updated_at: 2026-05-21
last_updated: 2026-05-21
version: 0.1
owner: wagner
---

# Especificação funcional — Compras

> Convenção do ID: `US-COM-NNN` user stories, `R-COM-NNN` regras Gherkin.
> Status `proposed` até ADR `compras-modulo-greenfield-hibrido` ser promovida a `accepted`.

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
**Implementado em:** `resources/js/Pages/Compras/Index.tsx` (a criar Wave 4)

Como user com permission `compras.view`, quero acessar `/compras` e ver cockpit completo das compras do meu business com 4 KPIs (aberto/trânsito/mês/fornecedor) e lista paginada filtrável por estágio FSM, pra poder priorizar conferência/pagamento sem precisar abrir cada compra.

**Regras:**

- R-COM-001 — Sem permission `compras.view` → 403
- R-COM-002 — Multi-tenant Tier 0 ADR 0093 — só vê compras do próprio `business_id` (session). Job `ImportarDfeComoCompraJob` recebe `$businessId` no constructor
- R-COM-003 — KPIs `aberto`/`transito`/`mes`/`fornec` calculados via `TransactionUtil::getListPurchases($business_id)` agregado server-side. Defer via `Inertia::defer` (pattern obrigatório)
- R-COM-004 — Filtros: query string `?q=...&stage=...&supplier_id=...`. Sem session storage (anti-hook charter)
- R-COM-005 — Linha clicada abre drawer ≤480px com 4 abas (Geral / Linhas / Pagamentos / Timeline)

### US-COM-002 — Criar compra manual

**Status:** pending (Wave 3+)
**Persona:** user com permission `compras.create`

Como user com permission `compras.create`, quero criar uma compra manual com fornecedor, linhas (produto + qty + custo) e plano de pagamento, sem precisar abrir o Blade legacy `/purchases/create`.

**Regras:**

- R-COM-101 — Wrapper sobre `PurchaseController::store` extraído pra `ComprasService::criar()`
- R-COM-102 — Disparar event `PurchaseCreatedOrModified` (listeners existentes não quebram)
- R-COM-103 — Observer Financeiro (`TransactionObserver`) já cria `fin_titulos` type=pagar automaticamente

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

**Status:** pending (Wave 8)
**Persona:** infra — não user-facing direto

Como Wagner, quero `/purchases` redirecionar 301 → `/compras` (padrão Financeiro #1283) e esconder Expense/Account dropdowns legacy quando `compras_module_enabled`, mantendo o Blade desativado mas presente pra rollback.

**Regras:**

- R-COM-301 — Feature flag per-business `compras_module_enabled` (NÃO hardcode `if (biz=4)`)
- R-COM-302 — `/purchases` → 301 `/compras` quando flag ON
- R-COM-303 — Menu legacy "Purchases" desaparece quando flag ON

### US-COM-005 — Entrada matricial tam×cor (GradeMatrixInput)

**Status:** pending (Wave 4.5)
**Persona:** Larissa @ ROTA LIVRE biz=4 vestuário (validação canary)
**Esforço:** ~6-8h IA-pair (referência [arte 2026-05-21](../../sessions/2026-05-21-arte-grade-matrix-input-vestuario.md))

Como Larissa criando compra de modelo `type='variable'` no `/compras/create`, quero selecionar produto pai e abrir grade visual onde linhas = tamanhos (PMGG) e colunas = cores (Preto/Branco/...), digitar qty por célula com Tab/Enter, ver totais por linha/coluna/grand on-the-fly e salvar tudo de uma vez, sem precisar adicionar SKU filho um por vez.

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
- [memory/decisions/proposals/compras-modulo-greenfield-hibrido.md](../../decisions/proposals/compras-modulo-greenfield-hibrido.md) — ADR proposta
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) Processo MWART canônico
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) Recalibração 10x
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) Visual gate F3
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) Cowork loop
- Precedente Soft wrapper PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288) + PR [#1297 Home](https://github.com/wagnerra23/oimpresso.com/pull/1297)
- Protótipo canon: `public/cowork-preview/erp-shell-v2/compras-page.{jsx,css}` + `Compras.html`
