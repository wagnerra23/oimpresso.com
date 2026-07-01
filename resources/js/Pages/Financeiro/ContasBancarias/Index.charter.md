---
page: /financeiro/contas-bancarias
component: resources/js/Pages/Financeiro/ContasBancarias/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-19"
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-INVENTARIO.md
related_adrs: [101, 144, 170]
related_us: [US-FIN-008]
smoke: "2026-07-01 — render prod OK (Chrome MCP, sessão matriz wagner@wr2 business_id=0; empty-state correto + 22 bancos suportados). Tenant real (biz≥1) ainda não smokado."
related_prototype: n/a (sem protótipo Cowork — form dados bancários/beneficiário CNAB; credenciais gateway vivem em /settings/payment-gateways, ADR 0170)
tier: A
charter_version: 2
---

# Page Charter — /financeiro/contas-bancarias

> **Status:** live. v2 (2026-05-19) — credenciais API saíram daqui pra `/settings/payment-gateways` (PR #1153/#1154 + ADR 0170). Esta tela passa a cuidar SÓ de dados bancários + beneficiário pra boleto CNAB direto.

---

## Mission

Listar contas bancárias do business + permitir configurar dados pra emissão de boleto (banco, agência, carteira, beneficiário) por conta. Credenciais de gateway (Inter, Asaas, C6 etc) NÃO ficam aqui — vivem em `/settings/payment-gateways` e referenciam a conta via FK `payment_gateway_credentials.conta_bancaria_id`.

---

## Goals — Features (faz)

- Listar accounts com beneficiário + carteira + status
- StatusBadge contextual: "Faltam dados" | "Inativo" | "Ativo · Cart. X"
- Sheet "Configurar boleto" pra editar dados bancários da conta (`ConfigurarBoletoSheet`)
- Suporta bancos CNAB tradicionais (BB, Caixa, Sicoob, Inter etc) + conta virtual PJ (Asaas)
- Link rápido pro cadastro de credenciais em `/settings/payment-gateways`
- Multi-tenant: `business_id` global scope

---

## Non-Goals — Features (NÃO faz)

- ❌ Cadastrar/editar credencial API de gateway (vai em `/settings/payment-gateways`)
- ❌ Persistir `client_secret` / certificado mTLS / API token (foi removido em 2026-05-19; cofre vive em `payment_gateway_credentials`)
- ❌ Criação de conta nova nesta tela (vem do flow de cadastro UltimatePOS)
- ❌ Sincronizar saldo direto desta tela (`/financeiro/extrato/{id}` faz)
- ❌ Emitir boleto avulso aqui (vai pra outras telas do RB/Cobrança)
- ❌ Configurar webhook (admin separado)
- ❌ Teste de conectividade gateway (botão "Testar" fica em Settings)

---

## UX Targets

- p95 first-paint < 1000ms
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal
- StatusBadge usa cores semânticas (amber=warning, emerald=ok)
- Sheet `ConfigurarBoletoSheet` carrega <300ms
- Subtítulo deixa claro que credenciais vivem em outro lugar (link direto)

---

## UX Anti-patterns

- ❌ Confirmação dupla pra abrir Sheet (low-risk, 1 click suficiente)
- ❌ Modal sobre Sheet (anti-stack)
- ❌ Status text-only (sempre badge com cor)
- ❌ Mostrar nome do gateway aqui (info pertence a `/settings/payment-gateways`)

---

## Automation Hooks

- Endpoint controller carrega accounts + bancos_suportados (CNAB BANCO_MAP + Asaas 274)
- `ConfigurarBoletoSheet` faz POST em `/financeiro/contas-bancarias/{id}` (upsert)
- Validação dados beneficiário no save (CEP, UF, etc)

---

## Automation Anti-hooks

- ❌ Não dispara teste de credencial gateway no abrir (gateway vive em `/settings/payment-gateways`)
- ❌ Não emite boletos ao salvar (config-only)
- ❌ Não acessa contas de outro `business_id`
- ❌ Não persiste credencial em plain text (responsabilidade movida pra módulo PaymentGateway)
- ❌ Não chama driver Inter/Asaas/C6 daqui

---

## Métricas vivas (Pest GUARD)

- `FinanceiroContasBancariasCharterTest::it_isolates_by_business_id()` — Tier 0
- `FinanceiroContasBancariasCharterTest::it_does_not_persist_credential_plaintext()` — após 2026-05-19, validar que request sem campos `gateway_*` ainda salva fin_contas_bancarias
- `FinanceiroContasBancariasCharterTest::it_does_not_render_credentials_section()` — snapshot do Sheet
- TODO F1.5: testes integrados upsert + reverse-lookup PaymentGatewayCredential pela conta

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Opus + Wagner | Stub criado em S6 F1. Detalhamento Pest GUARD pendente F1.5. |
| 2026-05-19 | Opus + Wagner | v2 — removidos campos gateway_* (credencial API). PaymentGateway extraído (ADR 0170). FK canon agora é `payment_gateway_credentials.conta_bancaria_id` (PR #1154). Tela passa a ser CNAB-only + beneficiário. |
