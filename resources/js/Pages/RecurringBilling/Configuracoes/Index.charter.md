---
page: /recurring-billing/configuracoes
component: resources/js/Pages/RecurringBilling/Configuracoes/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-17
parent_module: RecurringBilling
related_adrs: [0093, 0094, 0101, 0104, 0107, 0110, 0114, 0143]
tier: A
charter_version: 1
visual_source: prototipo-ui/prototipos/recurring/recurring-page.jsx (tab Configurações — Onda 8)
canon_method: Cowork KB-9.75
sidebar_group: fin (FINANCEIRO)
---

# Page Charter — /recurring-billing/configuracoes (Configurações · v1 read-only)

> **Status:** live · Onda 8 do plano [Index-visual-comparison.md](../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md).
>
> **Read-only stub v1.** Exibe gateways cadastrados, régua de dunning canônica (hardcoded), NFe auto (US-RB-044 status), webhooks com botão copy. Ondas futuras tornam editável.

---

## Mission

Centralizar tudo que afeta como o módulo Cobrança Recorrente cobra clientes do business — gateways de boleto/pix cadastrados, régua de dunning (cobrança escalada), emissão automática de NFe ao pagamento, URLs de webhook pra colar no painel admin do gateway. Substituí a necessidade de Wagner abrir 4 telas diferentes (admin Asaas + admin Inter + memória manual + RUNBOOK markdown) pra entender o estado da cobrança.

---

## Goals — Features (faz · v1 read-only)

- **AppShellV2 sidebar** + header `Configurações · cobrança recorrente`
- **4 seções verticalizadas (cards)** com Tailwind 4 puro:
  - **a. Gateways de boleto/pix** — lista BoletoCredential do business (banco + ambiente + ativo + nome_display) + CTA stub "Adicionar gateway" linkando pra `/financeiro/contas-bancarias` existente (cadastro de credenciais ainda lá enquanto Onda futura não move pra aqui)
  - **b. Régua de dunning (cobrança)** — texto explicativo + tabela com 3 retentativas (+3d soft / +7d warn / +15d final → past_due → fail) · ícone-severidade colorido (info/warn/bad)
  - **c. NFe-de-boleto-pago automática** — toggle visual (disabled) explicando US-RB-044 (NfeBrasil listener) · estado lido do Controller
  - **d. Webhooks** — lista URLs canônicas por gateway com botão "Copiar" (clipboard.writeText) + link pra docs oficiais
- **Inertia::defer em `gateways`** (toca DB) com fallback skeleton — skill `inertia-defer-default` Tier B
- **Multi-tenant Tier 0:** `HasBusinessScope` automático em BoletoCredential + Controller scopa explícito via `session('user.business_id')` + Pest cross-tenant biz=1 vs biz=99
- **Rota nomeada** `recurring-billing.configuracoes.index` (GET `/recurring-billing/configuracoes`) com stack canônico middleware UltimatePOS
- **PT-BR** todos labels e copy
- **Tailwind 4 puro** — zero CSS Cowork escopado (`.rec-*`); Index.tsx pai usa wrapper Cowork, esta tela é simpler e usa tokens Tailwind diretos (zinc/violet/emerald/amber/rose)

---

## Non-Goals — Features (NÃO faz neste PR)

- ❌ Cadastrar/editar/remover BoletoCredential (CRUD fica em `/financeiro/contas-bancarias` existente até Onda futura mover pra aqui)
- ❌ Editar régua de dunning (hardcoded v1 — Onda futura cria `rb_dunning_rules` table per-business + per-plan)
- ❌ Toggle real do NFe auto (Onda futura wire ao listener NfeBrasil — US-RB-044)
- ❌ Test webhook (botão "Testar conexão" — Onda futura dispara payload mock pro gateway)
- ❌ Histórico de eventos webhook (timeline) — separar em PR próprio
- ❌ Métricas/saúde gateway (uptime, latência média, taxa erro) — Onda futura observability
- ❌ Migrar `Modules/Financeiro/Pages/ContasBancarias.tsx` pra cá — fora de escopo

---

## UX Targets

- p95 first-paint < 800ms (página leve, 1 query SQL deferida)
- 0 erros JS console em smoke biz=1
- Cabe em monitor 1280px sem scroll horizontal (canon Larissa ROTA LIVRE)
- Botão "Copiar webhook" feedback visual < 100ms (`navigator.clipboard.writeText` + state local "Copiado!" por 1.5s)
- Tipografia: title 24px/700/-0.02em, section title 16px/600, body 14px/400

---

## UX Anti-patterns

- ❌ Formulário de edição inline (canon v1 = read-only · edição em modal/drawer fica em Onda futura)
- ❌ Mostrar `config_json` cifrado (vaza tokens — só nome_display/banco/ambiente)
- ❌ Mostrar webhook URL sem botão Copy (Wagner cola manualmente errado se digitar)
- ❌ Cor de severidade só semântica (precisa ícone + cor — accessibility WCAG)
- ❌ Bloquear página inteira se gateways query falhar (gracefully degrade — outras seções continuam)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/recurring-billing/configuracoes` (X-Inertia) | Inertia render `RecurringBilling/Configuracoes/Index` props `{regua_dunning, nfe_auto, webhooks, gateways (defer)}` |

---

## Tests anti-regressão

- [Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php](../../../../Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php) — 4+ cenários:
  1. `/recurring-billing/configuracoes` retorna Inertia render correto biz=1 com props canônicas
  2. Cross-tenant isolation: BoletoCredential biz=1 NÃO aparece quando user biz=99
  3. Webhooks URLs corretas e por business_id (Asaas + Inter PJ)
  4. Régua de dunning estrutura canônica (3 retentativas com dias corretos)

---

## Refs

- [Index-visual-comparison.md](../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md) linha 114 — Onda 8 plano
- [BRIEFING.md](../../../../memory/requisitos/RecurringBilling/BRIEFING.md) — estado consolidado RecurringBilling
- [RUNBOOK-inter-pj.md](../../../../memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md) — onboarding Inter PJ inclui colar webhook
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- Skill [inertia-defer-default](../../../../.claude/skills/inertia-defer-default/SKILL.md) — gateways DB query
- Skill [sidebar-menu-arch](../../../../.claude/skills/sidebar-menu-arch/SKILL.md)
