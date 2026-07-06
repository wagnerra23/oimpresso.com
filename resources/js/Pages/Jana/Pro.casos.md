---
casos: Jana Pro · paywall/upgrade · /ia/pro
irmaos: Pro.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-07-06"
---

# Casos de uso — /ia/pro (Jana Pro paywall)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do charter `Pro.charter.md` (Mission/Goals/Non-Goals/Anti-hooks) + `ProController::index()`.
> Persona-alvo: Larissa (ROTA LIVRE, decisão rápida). **Sprint A (ADR 0140):** tela + dados
> representativos; billing real é Sprint JANA-B. **Honestidade de escopo:** esta é uma tela de
> conversão majoritariamente visual/marketing — o testável **backend** é o **contrato de props**
> que o Controller entrega e o **isolamento Tier 0**. Estados de CTA, atalhos de teclado, layout
> 1280px e tokens de cor são **visual-only** (Pest não morde; ficam ⬜ manual/visreg).

## UC-PRO-01 — Rota abre a tela de decisão (200 + componente)
Status: 🧪 (ProContractTest P1 — status + component)
Usuário autenticado do business abre `/ia/pro`. O grupo `/ia` já garante auth; o Controller
renderiza o componente Inertia `Jana/Pro` (não redireciona, não 403 — é upsell aberto a
qualquer user auth). Âncora: charter Automation Hooks "`ProController::index()` renderiza `Jana/Pro`".
**Pronto quando:** GET `/ia/pro` autenticado → 200 e `assertInertia(component 'Jana/Pro')`.

## UC-PRO-02 — Contrato de props do paywall (plan/pricing/proof/business)
Status: 🧪 (ProContractTest P2 — shape das 4 chaves)
A tela recebe exatamente os 4 blocos que o design aprovado consome: `plan` (plano atual),
`pricing.monthly` + `pricing.trialDays` (preço honesto + trial), `proof.bruto/liquido/caixa`
(card de prova, 3 ângulos de faturamento) e `business.id/name`. Âncora: charter Goals
("Preço honesto", "Card de prova 3 ângulos Bruto/Líquido/Caixa", "Comparação Grátis vs Pro").
**Pronto quando:** props têm `plan`, `pricing.monthly`, `pricing.trialDays`, `proof.bruto`,
`proof.liquido`, `proof.caixa`, `business.id`, `business.name`.

## UC-PRO-03 — Preço e trial batem o plano comercial (ADR 0140)
Status: 🧪 (ProContractTest P3 — valores canon)
`pricing.monthly` = 49 (tier Pro entry, ADR 0140) e `pricing.trialDays` = 14 (trial do charter
CTA "14 dias grátis"). Âncora: charter Goals "R$ 49/mês" + CTA "Pro ativo · 14 dias grátis" +
ProController (`monthly => 49`, `trialDays => 14`).
**Pronto quando:** `pricing.monthly === 49` e `pricing.trialDays === 14`.

## UC-PRO-04 — Plano atual é 'free' (paywall assume Grátis) — Sprint A
Status: 🧪 (ProContractTest P4 — plan free mock)
Enquanto o billing real (Asaas) é Sprint JANA-B, a tela assume `plan = 'free'` (estado mock A1,
como PainelController). A comparação Grátis×Pro e a CTA "Ativar" partem daí. Âncora: charter
Non-Goals "Billing real é Sprint JANA-B; CTA é mock client-side" + Controller comentário A1.
**Pronto quando:** `plan === 'free'`. **Nota de escopo:** quando Sprint B ligar assinatura real,
este UC muda de "sempre free" para "reflete a assinatura" — o teste será atualizado junto.

## UC-PRO-05 — Tier 0: business é o da sessão, nunca de input
Status: 🧪 (ProContractTest P5 — business.id == sessão)
`business.id` vem SEMPRE de `session('user.business_id')`, nunca de query string / body — a tela
nunca mostra dado de outro `business_id` (ADR 0093). Âncora: charter Non-Goals "Não mostrar dados
de outro business_id" + Anti-hooks + Controller (`session()->get('user.business_id')`).
**Pronto quando:** com sessão biz=1, `business.id == 1` mesmo passando `?business_id=999` na URL.

## UC-PRO-06 — Render sem efeito colateral (leitura pura, sem billing/LLM)
Status: 🧪 (ProContractTest P6 — sem escrita / mock idempotente)
Abrir `/ia/pro` NÃO escreve no banco, NÃO dispara email/SMS/WhatsApp, NÃO chama LLM/Brain B e
NÃO cobra nada (billing gated Sprint B). Âncora: charter Non-Goals "Não escrever no banco no
render" + Automation Anti-hooks (sem email/LLM/cobrança). **Escopo:** o teste morde o observável
barato — a resposta é estável entre dois GETs (props idênticos), provando ausência de mutação de
estado no render. CTA "Ativar" é mock client-side (não há endpoint POST server-side pra morder).
**Pronto quando:** dois GETs seguidos devolvem o mesmo `plan`/`pricing`/`proof` (render idempotente).

---

## Fora do alcance backend (visual-only — ⬜ manual / visreg)

> Honestidade: os itens abaixo são do charter mas **não são testáveis por Pest de Controller**
> (são de layout/estado client). Ficam como contrato visual, cobertos por smoke real / visreg.

- ⬜ **Modo FOCO** (sem `JanaSubNav` de ghosts) — header só breadcrumb + título + "Voltar ao chat".
- ⬜ **Estados da CTA** `idle → Ativando… → Pro ativo · 14 dias grátis` (mock client-side).
- ⬜ **Atalhos de teclado** `⌘/Ctrl+Enter` ativa · `Esc` volta ao chat.
- ⬜ **Cabe em 1280px** (Larissa) sem rolar muito; comparação + preço + confiança visíveis.
- ⬜ **Tokens canon** `bg-primary` roxo (ADR 0190), `text-success`, zero `blue-*`/emoji.
- ⬜ **A11y** `:focus-visible` em todo interativo; CTA tabável; card de prova legível a ~1m.
