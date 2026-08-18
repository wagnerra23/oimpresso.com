---
id: resources-js-pages-jana-pro-casos
casos: Jana Pro · paywall/upgrade · /ia/pro
irmaos: Pro.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-18"
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

## UC-PRO-07 — O "voltar" leva à Conversa, não ao Painel
Status: 🧪 (`tests/jana-pro-voltar.test.tsx` — 4 casos sob o describe que cita este UC)

> **Por que 🧪 e não ✅**, tendo o teste rodado verde localmente (`4 passed`, 2026-08-18): o **G-7 do
> casos-gate** não aceita a minha palavra — `Status: ✅` exige veredito no manifesto
> `scripts/casos-test-results.json`, que nasce do **JUnit da suíte inteira** via `casos:results`. Rodar
> só este arquivo e regravar o manifesto apagaria o veredito dos outros UCs. O ✅ sobe quando o CI rodar
> a suíte. _(O gate acusou `status:unverified` na 1ª redação, com razão — está funcionando.)_

Os dois botões que prometem a conversa — **"Voltar ao chat"** no header e **"Falar com a Jana sobre o
Pro"** no footer — e o atalho **`Esc`** levam a `/ia/conversa`. Âncora: charter Goals *"`Esc` volta ao
chat"* + *"Voltar ao chat"* no header + Non-Goals *"'Falar com a Jana' → nunca WhatsApp"*.

**Por que nasceu:** a onda de fusão (US-COPI-148) fez `/ia` virar o **Painel** e moveu o chat pra
`/ia/conversa`. Os três consumidores continuaram apontando pra `/ia`, então o rótulo passou a mentir —
quem clicava "Voltar ao chat" caía num dashboard. A copy está certa (é literal do protótipo); o endereço
é que ficou pra trás.

**Escopo — por que jsdom e não Pest:** os seis UCs anteriores mordem o **Controller**; nenhum toca a
tela. `router.visit` é client-side e Pest de Controller não o alcança.

**Pronto quando:** os dois cliques e o `Esc` chamam `router.visit('/ia/conversa')`, e **tecla qualquer
não navega** (o 4º caso é controle negativo — sem ele, um handler que navegasse a cada keydown passaria
nos três primeiros).

_Bite-test (2026-08-18): com o endereço antigo (`/ia`), 3 dos 4 casos **falham** e só o controle negativo
passa — como tem de ser._

---

## Fora do alcance backend (visual-only — ⬜ manual / visreg)

> Honestidade: os itens abaixo são do charter mas **não são testáveis por Pest de Controller**
> (são de layout/estado client). Ficam como contrato visual, cobertos por smoke real / visreg.

- ⬜ **Modo FOCO** (sem `JanaSubNav` de ghosts) — header só breadcrumb + título + "Voltar ao chat".
- ⬜ **Estados da CTA** `idle → Ativando… → Pro ativo · 14 dias grátis` (mock client-side).
- ⬜ **Atalhos de teclado** `⌘/Ctrl+Enter` ativa · `Esc` volta ao chat.
- ⬜ **Cabe em 1280px** (Larissa) sem rolar muito; comparação + preço + confiança visíveis.
- ⬜ **Tokens canon** `bg-primary` roxo (ADR 0190), `text-success`, zero `blue-*`/emoji.
- 🟡 **A11y** `:focus-visible` — o anel foi extraído pra constante `focusRing` e passou a valer também
  na **CTA primária**, que era o alvo natural do Tab e não o tinha (2026-08-18). Segue ⬜ como *contrato
  verificado*: nenhum teste mede o anel, e medir classe CSS não é medir foco renderizado. Card de prova
  legível a ~1m: ⬜.
