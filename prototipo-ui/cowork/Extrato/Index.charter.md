---
page: /financeiro/extrato/{contaId}
component: resources/js/Pages/Financeiro/Extrato/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-07
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-FICHA.md
related_adrs: [0101]
tier: A
charter_version: 1
---

# Page Charter — /financeiro/extrato/{contaId}

> **Status:** live (sprint Inter PJ Open Finance — US-RB-046).
> Tela read-only que mostra extrato bancário sincronizado via gateway (Inter API hoje).

---

## Mission

Mostrar extrato (saldo + lançamentos + totais) de uma conta bancária do business atual, filtrável por período.

---

## Goals — Features (faz)

- Header com nome do banco + nome da conta + número (quando preenchido)
- Botão "← Voltar pra contas" navegando pra `/financeiro/contas-bancarias`
- 4 cards de KPI: saldo atual + crédito período + débito período + count lançamentos
- Filtro de período (from/to) com Apply manual (não realtime)
- Lista de lançamentos: data, valor, tipo C/D, descrição, contraparte
- Indicador de "Sincronizado em {timestamp}" pra saldo cached
- Format BRL (`pt-BR currency`) e date (`dd/mm/yyyy`) em todo valor
- Multi-tenant: `contaId` valida `business_id` no Controller

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição de lançamento (extrato bancário é imutável vindo do gateway)
- ❌ Categorização de lançamento (vai pra Modules/Accounting futuro)
- ❌ Download de OFX/CSV (extrato gateway tem export próprio)
- ❌ Trigger sync manual via botão (sync é via cron diário)
- ❌ Conciliação contábil (escopo Modules/Accounting)
- ❌ Multi-banco simultâneo (uma tela, uma conta)
- ❌ Comparativo período-anterior (M2 backlog)
- ❌ Gráfico de saldo histórico (futuro feature)

---

## UX Targets

- p95 first-paint < 800ms
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal (ROTA LIVRE)
- Filtro `preserveScroll` + `preserveState` ao aplicar (sem reload total)
- Format BRL consistente em todos os 4 KPIs
- Saldo `null` mostra `—` (não R$ [redacted Tier 0])
- "Sem sync" visível quando `saldo_atualizado_em` é null

---

## UX Anti-patterns

- ❌ Modal pra editar conta (vai pra outra tela)
- ❌ Confirmação dupla pra aplicar filtro
- ❌ Auto-apply em mudança de date input (custo de sync alta)
- ❌ Mostrar saldo "0" quando não tem dado (engana usuário)
- ❌ `window.location.reload()` ao aplicar filtro (quebra preserveState)

---

## Automation Hooks

- Endpoint `ExtratoController::index($contaId)` agrega lançamentos + totais
- Cron diário (`InterExtratoJob` ou similar) sincroniza saldo + lançamentos do gateway
- Multi-tenant: query usa `business_id` global scope no model `ContaBancaria`

---

## Automation Anti-hooks

- ❌ Não dispara sync ao abrir tela (cron é o gatilho)
- ❌ Não dispara emails
- ❌ Não muda saldo no banco (read-only puro)
- ❌ Não cria/edita lançamento (vem do gateway)
- ❌ Não chama gateway externo na request (lê do cached local)
- ❌ Não acessa conta de outro `business_id` (multi-tenant Tier 0)

---

## Métricas vivas (Pest GUARD)

- `FinanceiroExtratoCharterTest::it_renders_under_800ms_p95()`
- `FinanceiroExtratoCharterTest::it_does_not_call_external_gateway_on_render()`
- `FinanceiroExtratoCharterTest::it_does_not_mutate_balance()`
- `FinanceiroExtratoCharterTest::it_does_not_emit_emails()`
- `FinanceiroExtratoCharterTest::it_isolates_by_business_id()`
- `FinanceiroExtratoCharterTest::it_returns_404_for_other_tenant_account()`
- `FinanceiroExtratoCharterTest::it_formats_null_balance_as_dash()`
- `FinanceiroExtratoCharterTest::it_preserves_scroll_on_filter()`

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Charter criado em S6 F1 (Foundation). Tela vive da sprint Inter PJ Open Finance (US-RB-046). |
