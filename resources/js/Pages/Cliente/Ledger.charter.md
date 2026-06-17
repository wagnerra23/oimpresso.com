---
page: /contacts/ledger
component: resources/js/Pages/Cliente/Ledger.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Cliente
related_adrs: [110, 107, 93, 94, 104, 149]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/clientes/"
  blueprint_screenshot_approval: "N/A (divergente)"
  derived_screens: [Ledger]
  divergence_from_blueprint: "tabela financeira densa diferente do Index card layout"
---

# Page Charter — /contacts/ledger (DRAFT)

> Backend canon: `ContactController::getLedger()` linha 1326. **Divergência ADR 0149:** Layout densidade financeira (similar a extratos bancários) — tabela débito/crédito/saldo distinta do Index card-list. Não exige novo Cowork F1.5; pattern aceito como utility report.

## Mission

Extrato financeiro detalhado do cliente com filtros de data/formato/local + tabela com débito/crédito/saldo + export PDF/Excel. Substitui 3 Blades (`ledger`, `ledger_format_2`, `ledger_format_3`).

## Goals

- Header: nome + doc + back-link pra Show
- 3 KPI cards: total débitos, total créditos, saldo atual (vermelho se positivo = a receber)
- Filtros: data inicial, data final, formato (padrão/resumido/detalhado), local
- Tabela linhas: data, documento, descrição, débito (rose), crédito (emerald), saldo (preto)
- Action buttons: PDF (abre nova aba), Excel (download)
- Multi-tenant: `business_id` global scope

## Non-Goals

- ❌ Reconciliação bancária (rota /financeiro/reconciliacao futura)
- ❌ Filtrar por método de pagamento (filtro futuro)
- ❌ Gráfico de evolução do saldo (gráfico vai pra Dashboard, não inline)
- ❌ Marcar baixa de pagamento inline (rota /pagamentos)

## UX Targets

- p95 first-paint < 1000ms para 100 lançamentos
- Filtros aplicam via full-page reload (preserva URL deep-link)
- PDF gera < 3s para 100 linhas

## Automation Anti-hooks

- ❌ Não dispara envio do extrato por email automaticamente (rota dedicada `sendLedger`)
- ❌ Não modifica dados (read-only)
- ❌ Não acessa Contact/Transaction de outro `business_id`

## Refs

- Backend: `ContactController::getLedger()` + `TransactionUtil::getLedgerDetails()`
- Pattern divergência: ADR 0149 §"Casos que NÃO se qualificam"
