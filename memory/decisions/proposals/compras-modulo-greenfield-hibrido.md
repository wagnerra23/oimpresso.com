---
adr: proposed
title: "Modules/Compras greenfield híbrido — reusa transactions polimórfica"
status: proposed
date: 2026-05-21
deciders: [wagner]
supersedes: []
superseded_by: []
related: [0093, 0094, 0101, 0104, 0105, 0106, 0107, 0114, 0143]
module: Compras
---

# ADR — Modules/Compras greenfield híbrido (reusa `transactions` polimórfica)

## Status

**Proposed** — 2026-05-21. Aguarda decisão Wagner pra promover a `accepted`.

## Contexto

Larissa @ ROTA LIVRE (biz=4, vestuário PME) tem dor real de entrada de compra:

- 50+ modelos por entrega × 4 tams × 3-5 cores = 600-1000 SKUs por lote
- Blade legacy `purchase/create.blade.php` (1.769 linhas em `PurchaseController`) força linha-a-linha
- Sem grade visual tam×cor → 10-15 min por modelo digitado
- Sem botão "Importar XML DF-e" → re-digita dados que já chegaram via SEFAZ NSU (`nfe_dfe_recebidos` US-NFE-049/051, ADR 0116)

PR #1295 mergeou discovery + F1 GradeMatrixInput. Falta materializar módulo Compras + componente real (8-10h IA-pair).

Audit `memory/sessions/2026-05-21-como-integrar-compras.md` mapeou 3 caminhos:

- **A** (renomear Blade→React no UPos core) — cosmético, mantém Controller 1769 linhas. **Rejeitado.**
- **B puro** (`Modules/Compras/` greenfield com tabela `purchases` própria) — quebra Observer Financeiro, TransactionUtil, FK Fiscal/SPED, Manufacturing. **Rejeitado custo.**
- **B híbrido** (greenfield Controllers/Pages, REUSA `transactions` polimórfica + Observer Financeiro). **Recomendado.**

## Decisão

Adotar **caminho B híbrido**:

1. **Greenfield só em superfície:** `Modules/Compras/` com `Http/Controllers/`, `Routes/`, `resources/js/Pages/Compras/`, `Services/`, `Jobs/`, `Tests/`.
2. **Backend canônico preservado:** tabela `transactions` (polimórfica, type='purchase'/'purchase_order'/'purchase_return') continua sendo a fonte da verdade. Não criar tabela `purchases` própria.
3. **Reuso explícito:**
   - `TransactionUtil::getListPurchases`, `createPurchase`, `updatePurchaseOrderStatus` continuam servindo Compras
   - `TransactionObserver` Financeiro continua criando `fin_titulos` automático (Onda 2, 2026-04-25)
   - Event `PurchaseCreatedOrModified` continua disparando — listeners legacy não quebram
4. **Wrapper inicial:** `ComprasController` chama `TransactionUtil` via DI. `ComprasService` nasce como wrapper fino; refactor pra service nativo é roadmap (não bloqueia MVP).
5. **GAP NOVO único — bridge XML DF-e:** `ImportarDfeComoCompraService` + `ImportarDfeComoCompraJob` (constructor `$businessId` por ADR 0093). Migration nova: `nfe_dfe_recebidos.transaction_id` nullable FK + UNIQUE compound `(business_id, transaction_id)` pra idempotência.
6. **Deprecação `/purchases`:** Wave 8 — 301 redirect padrão Financeiro #1283, feature flag `compras_module` per-business (NÃO hardcode biz_id). Blade legacy desativado mas presente pra rollback.

## Alternativas rejeitadas (com motivo)

### Caminho A — renomear Blade→React no UPos core

- **Pró:** zero ADR, zero módulo novo
- **Contra:** mantém `PurchaseController` 1769 linhas; sem caminho pra GradeMatrixInput vestuário; sem caminho pra Import XML DFe; viola SoC brutal (Constituição v2 §5 ADR 0094)
- **Veredito:** rejeitado — não destrava Larissa, só esconde dívida

### Caminho B puro — tabela `purchases` própria

- **Pró:** isolamento total Compras
- **Contra:**
  - TransactionObserver Financeiro perde gatilho (precisa duplicar pra `purchases`)
  - SPED/Fiscal lê `transactions.type='purchase'` em 4 lugares — quebra
  - Manufacturing `production_purchase` é "parente" semântico — confusão de dois fluxos com nomes parecidos
  - 3.044 linhas de `TransactionUtil` precisariam ser duplicadas ou abandonadas
- **Veredito:** rejeitado custo proibitivo + risco regressão sistêmica

## Consequências

### Positivas

- ✅ Larissa unblocked em ~8-10h IA-pair (Waves 1-5) pra cockpit; +6-8h pra GradeMatrixInput (Wave 4.5); +8-12h pra bridge XML DFe (Wave 6)
- ✅ Observer Financeiro continua funcionando sem mudança — `fin_titulos` automático
- ✅ SPED/Fiscal NÃO quebra (continuam lendo `transactions.type='purchase'`)
- ✅ Manufacturing NÃO confunde — `production_purchase` permanece tipo separado
- ✅ Rollback Wave 9 simples — feature flag OFF, Blade legacy volta
- ✅ Padrão Soft wrapper (PR #1288 Caixa, PR #1297 Home) consolidado como caminho aceito pré-Rewrite

### Negativas

- ⚠️ Dívida arquitetural temporária — `ComprasController` chama `TransactionUtil` (core legacy) por DI; refactor pra service nativo é roadmap
- ⚠️ Bridge XML DF-e (Wave 6) é único componente verdadeiramente novo — maior risco técnico (concorrência import + auto-match supplier + idempotência re-import)
- ⚠️ Sidebar tem 2 entradas "Purchases" + "Compras" durante Waves 1-7 (até Wave 8 deprecação) — confusão UX temporária. Mitigado por feature flag esconder legacy quando `compras_module` ON

### Neutras

- 🟰 FSM 6 estágios visual no MVP mapeia `transactions.status` + `payment_status` simples; integração FSM canônico ADR 0143 fica pra fase 3 (não bloqueia)

## Plano de execução (9 waves)

| Wave | Conteúdo | Estimate IA-pair |
|---|---|---|
| 1 | Scaffold módulo nWidart + sidebar + perms + Pest smoke | ~2-4h |
| 2 | SPEC + RUNBOOK + BRIEFING + esta ADR | ~2h |
| 3 | Backend wrapper ComprasController + ComprasService + Pest baseline F2 | ~4-6h |
| 4 | Bundle CSS Cowork + Charter + Index.tsx F1 pin literal | ~2h |
| 4.5 | GradeMatrixInput.tsx (TanStack Table v8 headless) | ~6-8h |
| 5 | TS check + build + smoke local + PR Wave 1-5 | ~1-2h |
| 6 | Bridge XML DF-e (ImportarDfeComoCompraService + Job + migration) | ~8-12h |
| 7 | Pest multi-tenant + idempotência re-import | ~3-4h |
| 8 | Deprecação `/purchases` legacy (301 + feature flag) | ~3-4h |
| 9 | Canary 7d biz=1 → biz=4 ROTA LIVRE | RELÓGIO MUNDO REAL |
| **Total codável** | | **~30-45h IA-pair (≈ 3-5 dias agressivos)** |

## Referências

- [memory/sessions/2026-05-21-como-integrar-compras.md](../../sessions/2026-05-21-como-integrar-compras.md) — plug-points + 8 pegadinhas
- [memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md](../../sessions/2026-05-21-arte-grade-matrix-input-vestuario.md) — recomendação componente
- [memory/requisitos/Compras/SPEC.md](../../requisitos/Compras/SPEC.md)
- [memory/requisitos/Compras/BRIEFING.md](../../requisitos/Compras/BRIEFING.md)
- [memory/requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md](../../requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md)
- ADRs: [0093](../0093-multi-tenant-isolation-tier-0.md), [0094](../0094-constituicao-v2-7-camadas-8-principios.md), [0101](../0101-tests-business-id-1-nunca-cliente.md), [0104](../0104-processo-mwart-canonico-unico-caminho.md), [0105](../0105-cliente-como-sinal-guiar-sem-mandar.md), [0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md), [0107](../0107-emendation-0104-visual-comparison-gate-f3.md), [0114](../0114-prototipo-ui-cowork-loop-formalizado.md), [0143](../0143-fsm-pipeline-canonico.md)
- Precedente Soft wrapper: PR [#1288 Caixa](https://github.com/wagnerra23/oimpresso.com/pull/1288) + PR [#1297 Home Dashboard](https://github.com/wagnerra23/oimpresso.com/pull/1297)
- Protótipo canon: `public/cowork-preview/erp-shell-v2/compras-page.{jsx,css}`
