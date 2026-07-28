---
id: resources-js-pages-recurring-billing-configuracoes-index-casos
casos: Configurações da cobrança recorrente · /recurring-billing/configuracoes
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela que diz COMO o business cobra — URL de webhook errada é dinheiro que não entra.
owner: wagner
last_run: "2026-07-28"
---

# Casos de Uso & Aceite — Configurações (`/recurring-billing/configuracoes`)

> **Âncora:** `CU-RB-12` (entender como o business cobra) e `CU-RB-07` (receber e conciliar o webhook)
> do [SDD §6.1](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md).
> Os UC derivam do **contrato**, nunca do `.tsx`.
>
> **Por que o webhook mora aqui:** o webhook não tem tela própria — e esta é a tela que **exibe a URL
> que o operador cola no painel do gateway**. O contrato da rota é o outro lado da mesma moeda. Criar
> um arquivo paralelo pra "fluxo sem tela" seria tipo novo, proibido
> ([ADR 0351](../../../../../memory/decisions/0351-sdd-from-source.md) D-B).
>
> ⚠️ **Força do veredito:** rodam na **nightly CT100**, **não no PR**, **não bloqueiam merge**
> (zero linhas de `RecurringBilling` em `.github/ci-sqlite-pest.list`). Status **🧪, nunca ✅**.

## Rastreabilidade

| UC | Caso de uso | Prio | CU | Teste | Status |
|----|-------------|------|----|-------|--------|
| UC-RBCFG-01 | Lista os gateways sem vazar o `config_json` cifrado | must `[T0]` | `CU-RB-12` 1 | `Wave8ConfiguracoesIndexTest` | 🧪 |
| UC-RBCFG-02 | Mostra a régua de dunning com as 3 retentativas | should | `CU-RB-12` 2 | `Wave8ConfiguracoesIndexTest` | 🧪 |
| UC-RBCFG-03 | Gateway de um business não aparece pro outro | must `[T0]` | `CU-RB-12` 3 | `Wave8ConfiguracoesIndexTest` | 🧪 |
| UC-RBCFG-04 | A URL de webhook reflete o business da sessão | must `[T0]` `[V0]` | `CU-RB-12` 4 | `Wave8ConfiguracoesIndexTest` | 🧪 |
| UC-RBCFG-05 | Webhook sem token ou com token errado **não credita** | must `[T0]` `[V0]` | `CU-RB-07` 1/2 | `AsaasWebhookAuthTest` | 🧪 |
| UC-RBCFG-06 | Token de um business não credita no outro | must `[T0]` `[V0]` | `CU-RB-07` 3/4 | `AsaasWebhookAuthTest` | 🧪 |
| UC-RBCFG-07 | Evento repetido não é reprocessado (idempotência) | must `[V0]` | `CU-RB-07` 5/6 | `AsaasWebhookIdempotencyTest` | 🧪 |
| UC-RBCFG-08 | O webhook do Inter tem as mesmas garantias do Asaas | must `[T0]` `[V0]` | `CU-RB-07` 1-5 | `InterWebhookControllerTest` | 🧪 |

---

## UC-RBCFG-01 · Lista os gateways sem vazar o segredo · `must` `[T0]`
- **Persona:** Wagner quer conferir quais bancos estão configurados sem abrir 4 painéis externos.
- **Aceite:** Dado credenciais de boleto cadastradas · Quando abro a tela · Então vejo banco,
  ambiente, nome de exibição e se está ativo — e **nunca** o `config_json` (que carrega token/secret
  cifrados). As props Inertia batem com o componente canônico.
- **Teste:** [`Wave8ConfiguracoesIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php)
  — `renderiza Page Inertia com componente correto e props canônicas`.
- **Contrato:** `CU-RB-12` item 1 + `Index.charter.md` §UX Anti-patterns
  (*"Mostrar `config_json` cifrado (vaza tokens)"*) + [ADR tech/0007](../../../../../memory/requisitos/RecurringBilling/adr/tech/0007-encryption-pattern-credenciais-boleto.md).
- **Regressão que defende:** alguém fazer `->get()` sem `select()` e o token do gateway ir parar no
  HTML da página — credencial bancária exposta no DOM.
- **Status: 🧪**

---

## UC-RBCFG-02 · A régua de dunning aparece estruturada · `should`
- **Aceite:** Dado a tela carregada · Quando leio a seção de cobrança escalada · Então vejo as **3
  retentativas** (+3d / +7d / +15d → `past_due` → falha) como dado estruturado com severidade — não
  como parágrafo solto.
- **Teste:** [`Wave8ConfiguracoesIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php)
  — `expõe régua de dunning canônica com 3 retentativas estruturadas`.
- **Contrato:** `CU-RB-12` item 2.
- **Regressão que defende:** a régua exibida divergir da régua real. ⚠️ **Limite honesto:** hoje a
  régua é **hardcoded na tela** — não existe motor que a execute (SDD D4). Este UC prova que a tela
  **mostra** o que promete, não que o sistema **faz**.
- **Status: 🧪**

---

## UC-RBCFG-03 · Gateway de um business não aparece pro outro · `must` `[T0]`
- **Aceite:** Dado credenciais em biz=1 · Quando um usuário biz=99 abre a tela · Então nenhuma delas
  aparece.
- **Teste:** [`Wave8ConfiguracoesIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php)
  — `aplica multi-tenant Tier 0 — gateways biz=1 não aparecem pra biz=99`.
- **Contrato:** `CU-RB-12` item 3 + `CU-RB-10` + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Status: 🧪**

---

## UC-RBCFG-04 · A URL de webhook reflete o business da sessão · `must` `[T0]` `[V0]`
- **Persona:** Wagner copia a URL daqui e cola no painel do Asaas. Se o `businessId` na URL estiver
  errado, **os pagamentos daquele gateway são creditados no tenant errado — ou em nenhum.**
- **Aceite:** Dado a sessão de um business · Quando leio as URLs de webhook (Asaas e Inter PJ) ·
  Então o `{businessId}` no path é o **do business ativo**, e muda quando a sessão muda.
- **Teste:** [`Wave8ConfiguracoesIndexTest`](../../../../../Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php)
  — `expõe webhooks Asaas e Inter PJ com URL scopada por business_id` + `URLs de webhook refletem o
  business_id da session ativa`.
- **Contrato:** `CU-RB-12` item 4.
- **Regressão que defende:** a URL virar constante (`/webhooks/asaas/1`) num refactor — todo cliente
  novo colaria a URL do business 1. É `[V0]` porque o desfecho é dinheiro creditado no lugar errado.
- **Status: 🧪**

---

## UC-RBCFG-05 · Webhook sem token ou com token errado não credita · `must` `[T0]` `[V0]`
- **Persona:** um atacante descobre a URL (ela é **pública**, sem auth) e tenta forjar um
  `PAYMENT_RECEIVED` pra dar baixa numa fatura que ninguém pagou.
- **Aceite:** Dado `POST /webhooks/asaas/{businessId}` · Quando o header `asaas-access-token` está
  **ausente** → **401**; quando está **errado** → **401**. Em ambos: **nada é creditado e nenhum job
  é despachado** — a autenticidade é verificada **antes** de qualquer processamento.
- **Teste:** [`AsaasWebhookAuthTest`](../../../../../Modules/RecurringBilling/Tests/Feature/AsaasWebhookAuthTest.php)
  — `EXPLOIT: webhook sem header asaas-access-token NÃO credita (401)` + `EXPLOIT: webhook com token
  errado (atacante) NÃO credita (401)`.
- **Contrato:** `CU-RB-07` itens 1 e 2. Numa rota **sem sessão**, o global scope é no-op — quem
  isola é o `hash_equals` contra o `webhook_secret` da credencial daquele business
  ([SDD §5.3 F6](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md)).
  Mesma doutrina do canal público de feedback (US-INFRA-002 · [ADR 0334]).
- **Regressão que defende:** mover a verificação pra **depois** do dispatch (pra "responder 200 mais
  rápido") — o job rodaria com payload forjado. Crédito falso em `account_transactions`.
- **Status: 🧪**

---

## UC-RBCFG-06 · Token de um business não credita no outro · `must` `[T0]` `[V0]`
- **Aceite:** Dado credenciais válidas em dois businesses · Quando envio o token do business 1 pra
  `/webhooks/asaas/2` · Então **401**, sem crédito. E business **sem** credencial ativa → **404**;
  credencial **inativa** não autentica (também 404). Token legítimo → **200 + dispatch**.
- **Teste:** [`AsaasWebhookAuthTest`](../../../../../Modules/RecurringBilling/Tests/Feature/AsaasWebhookAuthTest.php)
  — `multi-tenant Tier 0: token do business 1 NÃO credita no business 2`, `rejeita 404 quando business
  não tem credencial Asaas ativa`, `credencial Asaas inativa rejeita 404`, `aceita e dispatcha quando
  token é válido`.
- **Contrato:** `CU-RB-07` itens 3 e 4 + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** comparar o token contra **qualquer** credencial Asaas do sistema em vez
  da daquele business — vazamento de crédito cross-tenant. O caso legítimo (token certo → 200) está
  no mesmo UC de propósito: um guard que barra tudo também está errado.
- **Status: 🧪**

---

## UC-RBCFG-07 · Evento repetido não é reprocessado · `must` `[V0]`
- **Persona:** o Asaas reenvia o mesmo evento (acontece em produção — at-least-once). O cliente **não
  pode** ser creditado duas vezes.
- **Aceite:** Dado um `event_id` já registrado em `pg_webhook_events` · Quando a 2ª chamada chega ·
  Então responde 200 **sem despachar o job**. A `UNIQUE(provider, event_id)` é enforced **no banco**.
  Quando o gateway não manda id, ele é derivado de forma **determinística** (`md5(event+payment.id)`),
  e providers diferentes podem legitimamente repetir o mesmo id.
- **Teste:** [`AsaasWebhookIdempotencyTest`](../../../../../Modules/RecurringBilling/Tests/Feature/AsaasWebhookIdempotencyTest.php)
  — `rejeita 2ª chamada com mesmo event_id sem dispatchar job (idempotência)`, `gera event_id
  determinístico…`, `UNIQUE constraint pg_webhook_events(provider, event_id) é enforced no DB`,
  `eventos de providers diferentes podem ter mesmo event_id (cross-provider OK)`.
- **Contrato:** `CU-RB-07` itens 5, 6 e 7 + [ADR tech/0001](../../../../../memory/requisitos/RecurringBilling/adr/tech/0001-idempotencia-charge-attempts-e-webhooks.md)
  + [ADR tech/0002](../../../../../memory/requisitos/RecurringBilling/adr/tech/0002-webhook-asaas-at-least-once-resposta-rapida.md).
- **Regressão que defende:** a guarda de idempotência virar só um `SELECT` na aplicação (sem a UNIQUE
  no banco) — duas entregas concorrentes passariam pela janela de corrida e creditariam 2×.
- **Status: 🧪**

---

## UC-RBCFG-08 · O webhook do Inter tem as mesmas garantias do Asaas · `must` `[T0]` `[V0]`
- **Persona:** Wagner recebe PIX pelo Inter PJ. A URL `/webhooks/inter/pix/{businessId}` também é
  pública, e o crédito também é dinheiro.
- **Aceite:** Dado `POST /webhooks/inter/pix/{businessId}` · Quando falta o header
  `X-Inter-Webhook-Secret` ou ele está errado → **401**; business sem credencial Inter ativa → **404**;
  secret do business 1 em `/2` → **rejeitado**. Com secret válido → grava em `pg_webhook_events` e
  despacha. **Idempotência por `endToEndId`**: 2× o mesmo → 1 linha, 1 dispatch; vários PIX no mesmo
  request → 1 dispatch por `endToEndId`; PIX **sem** `endToEndId` é pulado (sem dispatch).
- **Teste:** [`InterWebhookControllerTest`](../../../../../Modules/RecurringBilling/Tests/Feature/InterWebhookControllerTest.php)
  — os 8 casos (`rejeita 404…`, `rejeita 401 sem header…`, `rejeita 401 com secret errado`,
  `aceita PIX com secret válido…`, `idempotência: 2× mesmo endToEndId…`, `múltiplos PIX…`,
  `PIX sem endToEndId é skipado`, `multi-tenant Tier 0: secret de business 1 não funciona pra business 2`).
- **Contrato:** `CU-RB-07` itens 1-5 aplicados ao Inter + `US-RB-047`/`US-RB-051`
  ([SDD §5.3 F6](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md)).
- **Regressão que defende:** o Inter divergir do Asaas no tratamento de autenticidade/idempotência.
  Dois gateways com doutrinas diferentes é como um deles fica sem a guarda — e a chave de idempotência
  aqui é **outra** (`endToEndId`, não `event_id`), então é fácil esquecer.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** `gateways` chega **deferido** (`Inertia::defer`) com skeleton — o charter promete.
- **[BACKLOG]** Falha na query de gateways degrada graciosamente (as outras 3 seções continuam) —
  anti-pattern declarado no charter, sem teste.
- **[BACKLOG]** Botão "Copiar" devolve feedback visual em < 100ms — smoke visual, não Pest.
- **[BACKLOG]** O estado do toggle "NFe automática" reflete a flag real
  `nfebrasil.auto_emission_on_invoice_paid` — hoje o toggle é **visual e disabled** (`CU-RB-09`).
- **[BACKLOG]** Régua de dunning **executável** per-business (`rb_dunning_rules`) — não existe motor
  (SDD D4); sem código, não há UC.

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md)
- SDD (âncora): [`SDD-cobranca-recorrente-v1.0.md`](../../../../../memory/requisitos/RecurringBilling/SDD-cobranca-recorrente-v1.0.md) §6.1 `CU-RB-07`/`CU-RB-12` · §5.3 F6
- SPEC (US): US-RB-012 · US-RB-041
- Símbolos: `ConfiguracoesController@index` · `AsaasWebhookController@handle` · `ProcessAsaasWebhookJob`
- Gate: `scripts/casos-coverage-guard.mjs` ([ADR 0264](../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
