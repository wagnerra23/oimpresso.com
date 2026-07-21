---
slug: compras-briefing
title: "BRIEFING — Modules/Compras"
type: briefing
module: Compras
status: em-construcao
updated_at: 2026-07-03
version: 0.2
owner: wagner
---

# BRIEFING — Modules/Compras

> 🗺️ **Quais arquivos são deste contexto?** → [SUPERFICIE.md](SUPERFICIE.md) — índice **gerado** (`module-surface.mjs`), agrupado por papel, não apodrece (`--check` no CI).

## TL;DR (5 linhas)

- **O que é:** cockpit de compras (`/compras`) — lista paginada + 4 KPIs + drawer denso 5 tabs. Complementa (não substitui) o CRUD `/purchases/*` Inertia (convergência C1). Domínio: nota de entrada / pedido de compra sobre `transactions` polimórfica.
- **Estado (2026-07-03):** cockpit **live no código** (grade 59, bucket Médio, subiu de 38 pós-ESTABILIZAR). **NÃO está em produção nem canary pra nenhum business** (D5=0). Larissa @ ROTA LIVRE biz=4 sinalizou dor mas nunca usou.
- **Caminho:** B híbrido — greenfield Controllers/Pages, REUSA `transactions` + `TransactionUtil` + Observer Financeiro.
- **Maior gap:** a **ponte DF-e→compra** (`nfe_dfe_recebidos → Transaction type=purchase`, US-COM-003) — o import fiscal já existe no NfeBrasil, falta plugar. + **zero teste de cálculo custo/estoque** (Tier 0).
- **Capacidade vs mercado:** **34/100** ([CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)) — ganha em Tier 0 + UI, vazio no motor de compra (import/matching/recebimento/conciliação).

## Capacidades atuais (2026-07-03)

| Capacidade | Status | Onde |
|---|---|---|
| Cockpit `/compras`: lista + 4 KPIs + filtros query-string | ✅ live no código | `ComprasController::index` + `ComprasService` (Inertia::defer 4 props) · `Index.tsx` |
| Drawer detalhe 5 tabs (Resumo/Itens/Documentos/Pagamentos/Histórico) + timeline | ✅ | `Drawer.tsx` (608 LOC) |
| Multi-tenant Tier 0 REAL (Pest cross-tenant biz=1/99 + guard SQL + auth-based `business_id`) | ✅ | `MultiTenantTest.php` + `MultiTenantSqlGuardTest.php` (hotfix R1 `contacts.business_id`) |
| Throttle 60/1 + FormRequest `ListarComprasRequest` + OTel spans | ✅ (throttle sem Pest do 429) | `Routes/web.php` + `Http/Requests/` + `ComprasService` |
| Estoque na entrada (movimenta `variation_location_details` por variação/local) | ✅ **preservado do Blade** + guard `assertPurchaseVariationsOwnership` | `PurchaseController::store` → `ProductUtil` (fluxo `/purchases`) |
| Grade tam×cor matricial (vestuário) | ✅ **construída ponta-a-ponta** (upgrade sobre Blade linha-a-linha) | `GET /purchases/grade-matrix` → `GradeMatrixInput`/`GradeProductCombobox` em `Purchase/Create.tsx` (US-COM-005) |
| Contas a pagar automático ao comprar | ✅ herdado (Observer Financeiro cria `fin_titulos` type=pagar) | `TransactionObserver` via `/purchases/store` |
| FSM 6 estágios PERSISTIDA | ❌ só `const STAGES` visual no `Drawer.tsx` (não é máquina de estado) | — |
| Importar XML DF-e como compra | 🟡 **substrato pronto no NfeBrasil** (pull SEFAZ NSU + manifestação testados); falta só a **ponte→compra** | `Modules/NfeBrasil/Services/Manifestacao/*` ✅ · bridge `ImportarDfeComoCompraService` ❌ (US-COM-003) |
| Matching automático XML→produto (EAN/xProd) | ❌ | depende da ponte |
| Recebimento parcial (qty recebida ≠ pedida) | ❌ | — |
| Teste de cálculo custo/total/estoque (Tier 0) | ❌ (hardening tests são source-grep tautológicos) | gap G-03 |
| PiiRedactor no Drawer (CNPJ/CPF/mobile/email) | ❌ raw (gap LGPD ativo) | `Drawer.tsx:266/275/281` |

## Capabilities mapeadas pra vertical

- **horizontal:** todo business que compra de fornecedor — base de qualquer ERP
- **vestuário (Larissa):** grade tam×cor por modelo pai (US-COM-005) — construída, aguarda canary
- **automotivo (OficinaAuto):** futuro — compra de peças com lote/garantia
- **gráfica (Officeimpresso):** futuro — matéria-prima (PVC, papel) com unidade fracionada

## Score (atual — 3 réguas distintas)

| Régua | Nota | O que mede | Fonte |
|---|:-:|---|---|
| **module-grade v3** | **59** (Médio) | governança/higiene (Tier 0, Pest, doc, sec, LGPD, obs) | `php artisan module:grade Compras` |
| **CAPTERRA design** | **67** | UX/UI do protótipo Cowork F1 | [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md) |
| **CAPTERRA capacidade** | **34** | features/automação/fiscal/recebimento vs líderes | [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) |

> Leitura: as três divergem de propósito. A higiene (59) e o design (67) escondem que o **motor de compra** (34) não fecha o ciclo — importa/manifesta (via NfeBrasil) mas não vira compra, não casa PO, não recebe parcial, não concilia. Ver §8 da FICHA.

## Próximos passos (backlog priorizado)

Fonte viva: [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (16 tasks P0-P3, aguarda aprovação Wagner) + Onda 2.1 do programa de ondas.

1. **G-01 [P0]** Ponte DF-e recebida → Compra (US-COM-003) — reusa NfeBrasil, esforço M
2. **G-03 [P0]** Teste E2E cálculo custo/total/estoque (Tier 0 valor/estoque)
3. **G-02 [P0]** Matching automático XML→produto (EAN+xProd)
4. **[P1]** FSM persistida · recebimento parcial · GradeMatrixInput smoke/canary Larissa
5. **[P2]** PiiRedactor no Drawer (LGPD) · eager-load anti-N+1

## Pegadinhas conhecidas (Tier 0)

- Multi-tenant ADR 0093 — `auth()->user()->business_id` (não session); Job recebe `$businessId` no constructor
- **Valor/estoque (regra-mestre):** entrada de compra MEXE EM ESTOQUE (1 célula de grade = 1 SKU × custo × qty) — dupla confirmação + antes→depois + aprovação antes de mergear cálculo
- Pest biz=1/99 NUNCA biz=4 (ADR 0101) — biz=4 só canary 7d pós-merge
- Convergência C1 — NÃO criar `Pages/Compras/Create.tsx`; "+ Nova compra" delega `/purchases/create` via `router.visit`
- Cowork bundle aplicar INTEIRO 1ª vez (proibicoes.md Tier 0)

## Owner & ADRs

- Owner: Wagner
- ADRs base: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · 0094 · [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · 0104 · [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) · 0106 · 0114 · 0143
- ADR proposta: [compras-purchase-convergencia-c1](../../decisions/proposals/compras-purchase-convergencia-c1.md) (C1 vigente)

## Refs

- [SPEC.md](SPEC.md) (US-COM-001..011) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (capacidade 34) · [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md) (design 67) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (backlog)
- [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) · [DISCOVERY-LARISSA-COMPRAS.md](DISCOVERY-LARISSA-COMPRAS.md) · [RUNBOOK-compras-index.md](RUNBOOK-compras-index.md)
- Session logs: [2026-05-21 como-integrar](../../sessions/2026-05-21-como-integrar-compras.md) · [2026-07-03 capterra](../../sessions/2026-07-03-capterra-compras.md)
