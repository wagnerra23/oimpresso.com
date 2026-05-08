---
page: /financeiro/contas-bancarias
component: resources/js/Pages/Financeiro/ContasBancarias/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-07
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-FICHA.md
related_adrs: [0101]
tier: A
charter_version: 1
---

# Page Charter — /financeiro/contas-bancarias (stub F1)

> **Status:** stub estruturado em F1. Detalhamento (testes reais sobre gateway flows) entra em F1.5/F2.

---

## Mission

Listar contas bancárias do business + permitir configurar dados de boleto e gateway (Inter/Asaas) por conta.

---

## Goals — Features (faz)

- Listar accounts com beneficiário + carteira + status
- StatusBadge contextual: "Faltam dados" | "Inativo" | "Inter API · sandbox" | "Ativo · Cart. X"
- Sheet "Configurar boleto" pra editar conta selecionada (`ConfigurarBoletoSheet`)
- Suporte a 2 gateways: Inter API + Asaas
- Distingue ambiente sandbox vs prod visualmente
- Multi-tenant: `business_id` global scope

---

## Non-Goals — Features (NÃO faz)

- ❌ Criação de conta nova nesta tela (vem do flow de cadastro UltimatePOS)
- ❌ Sincronizar saldo direto desta tela (`/financeiro/extrato/{id}` faz)
- ❌ Emitir boleto avulso aqui (vai pra outras telas do RB)
- ❌ Configurar webhook (admin separado)
- ❌ Trocar credencial gateway sem retest sandbox primeiro

---

## UX Targets

- p95 first-paint < 1000ms
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal
- StatusBadge usa cores semânticas (amber=warning, emerald=ok, blue=sandbox)
- Sheet `ConfigurarBoletoSheet` carrega <300ms
- Sandbox claramente diferenciado de prod (não confundir)

---

## UX Anti-patterns

- ❌ Confirmação dupla pra abrir Sheet (low-risk, 1 click suficiente)
- ❌ Modal sobre Sheet (anti-stack)
- ❌ Status text-only (sempre badge com cor)
- ❌ Sandbox e prod com mesma cor

---

## Automation Hooks

- Endpoint controller carrega accounts + bancos_suportados
- `ConfigurarBoletoSheet` faz PATCH em `/financeiro/contas-bancarias/{id}`
- Validação dados beneficiário no save (CEP, UF, etc)

---

## Automation Anti-hooks

- ❌ Não dispara teste de credencial gateway no abrir (custa requisição externa)
- ❌ Não emite boletos ao salvar (config-only)
- ❌ Não acessa contas de outro `business_id`
- ❌ Não persiste credencial em plain text (gateway_credential_id é FK pra cofre)

---

## Métricas vivas (Pest GUARD — completar em F1.5)

- `FinanceiroContasBancariasCharterTest::it_does_not_call_gateway_on_render()` (stub)
- `FinanceiroContasBancariasCharterTest::it_isolates_by_business_id()` (stub)
- `FinanceiroContasBancariasCharterTest::it_does_not_persist_credential_plaintext()` (stub)
- `FinanceiroContasBancariasCharterTest::it_distinguishes_sandbox_from_prod_visually()` (stub)
- TODO F1.5: testes integrados no flow do Sheet de Boleto

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Stub criado em S6 F1. Detalhamento Pest GUARD pendente F1.5. |
