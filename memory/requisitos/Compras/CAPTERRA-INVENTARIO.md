# CAPTERRA-INVENTÁRIO — Compras

> Gerado por skill `comparativo-do-modulo` (`/comparativo Compras`) em **2026-07-03** — Passo 2 da Onda 2.1 do programa de ondas.
> Fontes: [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) (19 capacidades, nota 30/100) + [`SPEC.md`](SPEC.md) (US-COM-001..011) + `Modules/Compras/` + `resources/js/Pages/{Compras,Purchase}/`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) + [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal).

## Resumo

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 3 | 16% |
| 🟡 PARCIAL | 8 | 42% |
| ❌ AUSENTE | 8 | 42% |
| **Total** | 19 | 100% |

**Por score (score = peso da capacidade na FICHA):**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| **P0** (bloqueador/define o domínio) | 1 | 2 | 3 | 6 |
| **P1** (mercado tem, cliente vai pedir) | 0 | 3 | 3 | 6 |
| **P2** | 2 | 1 | 2 | 5 |
| **P3** | 0 | 2 | 0 | 2 |

**Diagnóstico:** o módulo tem **higiene boa** (multi-tenant Tier 0 real, cockpit+drawer, KPIs, defer) e o **substrato fiscal pronto** (import DF-e + manifestação testados no `Modules/NfeBrasil`), mas o **motor de compra não fecha o ciclo** — os 3 P0 ausentes (matching XML→PO, recebimento parcial, 3-way match) + a ponte DF-e→compra (C01, a última milha) são o que **É** uma compra BR. Como "tela de leitura + import fiscal ao lado" funciona; como "recebe a NF-e do fornecedor → vira compra → casa com o pedido → concilia", ainda não. Nenhuma capacidade está **em uso por cliente real** (D5=0 — módulo não está em prod nem canary). Ver §8 da FICHA ("O que a nota esconde").

## Inventário detalhado

| # | Capacidade (FICHA) | Score | Status | Evidência | Falta |
|---|---|:-:|:-:|---|---|
| C01 | Importar XML NF-e como compra + manifestação destinatário SEFAZ | P0 | 🟡 | **pull + manifestação PRONTOS no `Modules/NfeBrasil`**: `DistribuicaoDfeService` (SEFAZ NSU) + `BuscarDfesRecebidosJob`/cron + `ManifestacaoService`/`Controller` (testados) + `nfe_dfe_recebidos`/`itens`/`eventos` | Só a **última milha — a ponte→compra**: `nfe_dfe_recebidos.transaction_id` + `ImportarDfeComoCompraService` (DFe→`type=purchase`) (US-COM-003) |
| C02 | Matching automático XML→PO (fornecedor + produto) | P0 | ❌ | Sem código de match por CNPJ nem por EAN/xProd | Auto-match supplier por CNPJ + produto por EAN+`xProd` (depende C01) |
| C03 | Recebimento parcial (qty recebida ≠ pedida) | P0 | ❌ | `Drawer.tsx` só mostra estado inteiro; sem qty-recebida por linha | Modelo de recebimento parcial + trânsito residual + autosave check-in |
| C04 | Cálculo custo/total da compra correto — comprovado por teste | P0 | 🟡 | `ComprasService::buscarDetalhe` calcula `line_total = qty × price_inc_tax` (PHP); `GapsHardeningTest`/`GapsP1HardeningTest` são `file_get_contents`+`str_contains` (tautológicos) | Teste E2E que submete compra (grade+frete+desc+imposto) e assere `final_total`/`purchase_lines`/estoque persistidos (Tier 0 valor/estoque) |
| C05 | 3-way match (PO ↔ Recebimento ↔ NF-e) | P0 | ❌ | Nem PO-vs-receipt nem receipt-vs-NFe | Algoritmo de match + tolerância configurável + UI "Discrepâncias" (depende C01+C03) — **sinal pendente** (teto P2P, avaliar se PME loja precisa) |
| C06 | Isolamento multi-tenant (Tier 0) | P0 | ✅ | `business_id` do `auth()` + `abort_if` + cross-check drift + `MultiTenantTest.php` (HTTP real biz=1/99) + `MultiTenantSqlGuardTest.php` (`->toSql()`) + hotfix R1 `contacts.business_id` | — (mantido pela catraca) |
| C07 | Cálculo de estoque na entrada (baixa/movimentação) | P1 | 🟡 | **preservado do Blade + endurecido** — `Purchase/Create.tsx` → `PurchaseController::store` → `ProductUtil::createOrUpdatePurchaseLines`+`updateProductQuantity` grava `variation_location_details.qty_available` por variação/local; guard Tier 0 `assertPurchaseVariationsOwnership` antes de escrever | Teste de invariante de estoque (pareia C04); import DF-e ainda não alimenta (G-01) |
| C08 | Contas a pagar automático (Observer Financeiro) | P1 | 🟡 | `TransactionObserver` cria `fin_titulos` type=pagar quando `/purchases/store` roda — herdado | Cobrir o fluxo `/compras`→AP com teste; hoje não é capacidade própria |
| C09 | FSM de estágios persistida + auditável | P1 | ❌ | `const STAGES` em `Drawer.tsx:12` (UI-only), mapeada sobre `transactions.status` string legacy; sem state machine, sem history, sem transição gateada | `spatie/laravel-model-states` ou coluna `stage` + histórico (ADR 0143) |
| C10 | Grade tam×cor (entrada matricial vestuário) | P1 | 🟡 | **construído ponta-a-ponta** — `GET /purchases/grade-matrix`→`PurchaseController::gradeMatrix` + `GradeMatrixInput`/`GradeProductCombobox` (US-COM-005) expandem célula→`variation_id`→purchase_line→estoque. Upgrade sobre Blade (matricial vs linha-a-linha). **Fora do `/compras`** (C1) | Zero teste + smoke/canary Larissa pendente |
| C11 | Supplier scorecard (OTIF / lead-time / defect / fill-rate) | P1 | ❌ | Nenhuma métrica de fornecedor | Métricas + dashboard — **sinal pendente** (feature-wish sem dor reportada) |
| C12 | Aprovação / workflow multi-nível de compra | P1 | ❌ | Sem alçada nem approval chain | Budget control + aprovação por alçada — **sinal pendente** (PME loja pode não precisar) |
| C13 | KPIs cockpit (a pagar / trânsito / mês / fornecedores) | P2 | ✅ | 4 KPIs agregados server-side + `Inertia::defer` + cores semânticas (`ComprasService::calcularKpis`) | — |
| C14 | Drawer detalhe denso (list-detail) | P2 | ✅ | `Drawer.tsx` 480px, 5 tabs, timeline activitylog, breakdown financeiro | — |
| C15 | Anti-N+1 / perf (defer + eager-load) | P2 | 🟡 | `Inertia::defer` nas 4 props + eager-load em `buscarDetalhe` | `listarCompras().paginate()` sem `->with(['contact','location'])` (N+1 nas rows) |
| C16 | Autosave rascunho de compra | P2 | ❌ | Sem draft persistido; forms não modelados no `/compras` | `localStorage` draft `{biz}.{user}` (Larissa atende telefone no meio) |
| C17 | LGPD / PII fornecedor redigida | P2 | ❌ | `Drawer.tsx:266/275/281` renderiza `tax_number`(CNPJ/CPF)+`mobile`+`email` **raw** | `PiiRedactor` por role + entry em `module_clients.yaml` (destrava D5) |
| C18 | A11y (WCAG 2.1 AA) | P3 | 🟡 | Cores contrastam; drawer sem `role=dialog`/focus-trap/`aria-label` no botão fechar | Focus trap + `aria-*` + `Esc` handler (herdado do protótipo F1) |
| C19 | Atalhos teclado (`/` `N` `I` `↑↓` `Esc`) | P3 | 🟡 | Declarados no footer **sem handlers** | Implementar handlers React (risco de expectativa frustrada) |

## Tasks propostas (aguardando aprovação Wagner)

> **Ordem por prioridade** (P0 primeiro). Cada task nasce com `module:Compras priority:P{N}` + tags `["capterra-gap","onda-2.1"]` + `parent_plan:programa-ondas`.
> **Sinal ADR 0105:** tasks marcadas **⏸️ sinal pendente** NÃO devem ir pro backlog ativo sem dor reportada por cliente — são feature-wish (ADR 0105). Recomendo aprovar só as **✅ execute**.
> **NÃO foram criadas no MCP ainda.** Aprove com "todas" / "só P0" / "1,2,3,6,12" / "as ✅ execute".

### P0 — bloqueador / define o domínio

1. **[P0] Ponte DF-e recebida → Compra** (US-COM-003) — ✅ execute — _**reusa o que já existe no `Modules/NfeBrasil`** (`DistribuicaoDfeService` pull NSU + `nfe_dfe_recebidos` + `ManifestacaoService`, testados); constrói só a última milha: migration `nfe_dfe_recebidos.transaction_id` + `ImportarDfeComoCompraService` (DFe→`type=purchase`) + modal "Importar XML" listando DF-e pendentes + auto-match fornecedor por CNPJ. Diferencial nº1 BR + unlock Larissa. Esforço **M** (o import fiscal caro já está pronto). Evidência: C01 🟡._
2. **[P0] Teste E2E de cálculo custo/total/estoque da compra** — ✅ execute (dever Tier 0) — _submete compra (grade+frete+desc+imposto) → assert `final_total`/`purchase_lines`/movimentação de estoque persistidos. Blinda Tier 0 valor/estoque (1 célula grade = 1 SKU × custo × qty = write de estoque). Evidência: C04 🟡, hardening tests tautológicos._
3. **[P0] Matching automático XML→produto (EAN + xProd; fallback manual)** — ✅ execute (depende #1) — _o que faz o import valer a pena em vez de mapear item a item. Evidência: C02 ❌._
4. **[P0] Recebimento parcial (qty recebida por linha ≠ pedida + trânsito residual + autosave check-in)** — ✅ execute — _vestuário recebe parcial real (lote incompleto). Evidência: C03 ❌. Líder: Lightspeed/Shopify/Zoho._
5. **[P0-teto] 3-way match (PO ↔ Recebimento ↔ NF-e) + tolerância + UI "Discrepâncias"** — ⏸️ sinal pendente — _"essential 2026" no P2P mid-market, mas pode ser over-engineering pra loja PME. Só se Larissa/outro reportar overpayment. Depende #1+#4. Evidência: C05 ❌._

### P1 — mercado tem, cliente vai pedir

6. **[P1] FSM de estágios PERSISTIDA** (`spatie/laravel-model-states` ou coluna `stage`) — ✅ execute — _parar de mentir "Recebido" na tela quando o banco diz `pending`. Base pra 3-way match. Evidência: C09 ❌ (const UI-only Drawer.tsx:12). ADR 0143._
7. **[P1] Teste de invariante de estoque no fluxo de entrada** — ✅ execute (pareia #2) — _movimentação de estoque na entrada de compra coberta por teste. Evidência: C07 🟡._
8. **[P1] Cobrir fluxo `/compras`→contas a pagar (Observer Financeiro) com teste** — ✅ execute — _hoje herdado de `/purchases/store`, não é capacidade própria testada. Evidência: C08 🟡._
9. **[P1] GradeMatrixInput — smoke + canary + validação Larissa** (US-COM-005) — ✅ execute — _o unlock da persona vive em `Purchase/Create.tsx` e nunca foi validado. Evidência: C10 🟡. (Canary biz=1 antes; biz=4 só pós-7d — ADR 0101.)_
10. **[P1] Supplier scorecard básico (OTIF / lead-time / defect / fill-rate)** — ⏸️ sinal pendente — _feature-wish sem dor reportada. Evidência: C11 ❌._
11. **[P1] Aprovação / workflow multi-nível de compra (alçada)** — ⏸️ sinal pendente — _PME loja (Larissa) pode não precisar; só com sinal. Evidência: C12 ❌._

### P2 — LGPD / perf / higiene

12. **[P2] PiiRedactor no Drawer (CNPJ/CPF+mobile+email) + entry `module_clients.yaml`** — ✅ execute — _dever LGPD 2026 ATIVO (`Drawer.tsx:266/275/281` raw) + destrava D5 (sai do feature-theater). Evidência: C17 ❌._
13. **[P2] Autosave rascunho de compra** (`localStorage` `{biz}.{user}` debounced) — 🟡 sinal médio — _Larissa atende telefone no meio; mas forms de compra vivem em `/purchases`, avaliar placement. Evidência: C16 ❌._
14. **[P2] Eager-load `->with(['contact','location'])` em `listarCompras().paginate()`** — ✅ execute — _anti-N+1 nas rows do cockpit. Evidência: C15 🟡. Esforço XS._

### P3 — polish

15. **[P3] A11y drawer (`role=dialog` + focus-trap + `aria-label` + `Esc`)** — ✅ execute — _dever WCAG; herdado do protótipo F1. Evidência: C18 🟡._
16. **[P3] Atalhos teclado com handlers React** (`/` `N` `I` `↑↓` `Esc`) — 🟡 sinal baixo — _declarados no footer sem funcionar = quebra confiança; mas Larissa não é power-user. Evidência: C19 🟡._

---

**Recomendação (não-vinculante):** aprovar as **12 tasks ✅ execute** (1-4, 6-9, 12, 14, 15 + a #13 se decidir placement) e **segurar as 4 ⏸️/🟡 sinal pendente** (5, 10, 11, 16) como feature-wish ADR 0105 até haver dor de cliente. Isso alinha o backlog ativo ao caminho da FICHA (G-01→G-02→G-03) sem inflar com o que ninguém pediu.

**Próximo passo após aprovação:** `tasks-create` no MCP (1 por task aprovada) + apêndice das US ao `SPEC.md` (seção "Backlog vindo do Capterra-Inventário") + commit. Depois, Passo 3 da onda (régua por tela) e Passo 4 (catraca).
