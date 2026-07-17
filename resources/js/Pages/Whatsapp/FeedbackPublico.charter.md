---
page: /feedback
component: resources/js/Pages/Whatsapp/FeedbackPublico.tsx
owner: wagner
status: live
last_validated: "2026-07-17"
parent_module: Whatsapp
parent_adr: memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md
related_adrs: [93, 101, 104, 105, 334]
related_adrs_ui: [UI-0013]
tier: A
charter_version: 1
---

# Page Charter — `/feedback` (canal público de sinal do cliente)

> Invariantes da tela onde **o cliente reporta direto**, sem intermediário. Mudanças que
> violem este charter exigem PR + bump `charter_version`.

## Mission

Deixar a Larissa (ROTA LIVRE biz=4, não-técnica) contar a dor dela em ~30 segundos, a
partir de um link, **sem login e sem depender de alguém ouvir e transcrever**.

É o órgão sensor da [ADR 0334](../../../../memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md):
o canal `whatsapp` de `clients_feedbacks` só produz sinal quando o [W] clica "Capturar" no
inbox — o [W] **é** o nervo, manualmente. Esta tela instala o nervo que a ADR 0105 pressupõe
("backlog só recebe item se cliente paga + **reporta**") e que nunca existiu.

## Goals

- 1 campo livre + 1 escala de severidade (0-4) + nome opcional — nada além disso
- Funciona sem login, a partir de um link assinado com validade de 30d
- Sinal cai em `clients_feedbacks` com `canal=web_form`, ao lado do canal `whatsapp`
- Reusa dedup-por-signature, `relevance_score` e workflow de status já existentes
- Captura o contexto barato que o cliente não sabe informar (user-agent, viewport, referrer)

## Non-Goals

> ⚠️ Cada item vira Pest GUARD. Só o [W] preenche esta seção — o agente é **proibido de
> inferir** (`how-trabalhar.md` §Pedido de tela). Os 2 abaixo são decisão [W] literal de
> 2026-07-17 (escolha "Estender clients_feedbacks" + ressalva da grade); o resto fica em
> aberto até o [W] dizer.

- ❌ **Não criar tabela própria de sinal** (`mcp_client_signals`). Decisão [W] 2026-07-17:
  estender `clients_feedbacks`, que já tem dedup/relevance/status/dashboard/sync-git e já é
  Tier 0. Tabela nova = "duplica régua consolidada" (proibicoes §5).
- ❌ **Não classificar o sinal com IA** (adaptive taxonomy tipo Enterpret). Ressalva
  explícita da grade de réguas 2026-07-17: IA cara, N=2 clientes não paga. Só a
  **existência** da superfície.

_(pendente [W]: demais non-goals — ex. se a tela pode um dia LER algo do business, se
aceita upload, se ganha auth.)_

## Automation Anti-hooks

> ⚠️ Mesma regra: só o [W] preenche. Vazio ≠ "não há" — significa "ainda não declarado".

_(pendente [W])_

## Invariantes Tier 0 (ADR 0093 — IRREVOGÁVEL)

Esta tela é servida por rota **sem auth**, então `session('user.business_id')` é null e o
global scope `ScopeByBusiness` é **NO-OP** aqui (retorna cedo em `!auth()->check()`).
Nada no Eloquent isola o tenant — quem isola é o HMAC da URL assinada.

1. **`business_id` vem da URL assinada, nunca do input.** Adulterar `?biz` quebra a
   assinatura → 403. Travado por `FeedbackPublicoSignedUrlTest` (UC-FBP-01/02).
2. **A tela é write-only.** Nenhuma prop pode expor dado do tenant além do nome do business
   (já implícito em quem tem o link). Um `GET` que exponha dado aqui vira vazamento na hora.
3. **Toda query do controller filtra `business_id` à mão** — não confiar no global scope.

## Contrato visual

_(pendente [W] — copy literal + ordem. A tela nasceu sem protótipo Cowork: é pública, fora
do cockpit, e não segue PT-01..05, que assumem o shell autenticado.)_

## Estados

| Estado | O que aparece |
|---|---|
| inicial | form: "O que aconteceu?" + escala + nome opcional |
| erro de validação | mensagem no campo (`role="alert"`), input preservado |
| enviando | botão desabilitado, "Enviando…" |
| recebido | confirmação + link "Contar outra coisa" |
| link inválido/expirado | 403 do middleware `signed` (não é estado da tela) |

## Referências

- [RUNBOOK — Feedback Público](../../../../memory/requisitos/Whatsapp/RUNBOOK-feedback-publico.md) (F1 PLAN, ADR 0104)
- [ADR 0105 — cliente como sinal](../../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0334 — anti-atrofia da inteligência de negócio](../../../../memory/decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)
- [ADR 0093 — multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
