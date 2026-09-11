---
slug: 0390-emenda-0384-smoke-em-ambiente-controlado
number: 390
title: "Emenda à 0384 — o smoke que leva a `validated` aceita ambiente controlado (host no recibo: producao · staging-ct100 · ci)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-06"
module: governance
tags: [design, cowork, protocolo, recibo, smoke, ci, validated, tenant]
supersedes: []
superseded_by: []
supersedes_partially:
  - 0384-design-sync-recibos-executaveis-por-tela
related:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
---

# Emenda à 0384 — o smoke que leva a `validated` aceita ambiente controlado

> Nasce `proposto`. [W] autorizou a implementação em 2026-09-06 ("o estado `validated` passa a
> aceitar smoke em ambiente controlado não-produção"); o merge de uma ratificação própria
> continua sendo o ato formal previsto pela ADR 0257.

## Contexto

O D-6 da ADR 0384 diz que `validated` exige rota, SHA do deploy, screenshot durável, resultado
positivo e tenant permitido — e que o smoke é **manual de produção**. Produção exige login
humano que o agente não digita. Resultado medido no `application-report.json` de 2026-09-06:
lifecycle `{anchored 62, to-create 23, compared 4, tested 3, applied 1}` — **0 de 93** telas
`validated`, por construção, não por falta de trabalho. O estado existia no vocabulário e era
inalcançável na prática; um estado que nunca acende é carimbo, não medida.

O ambiente controlado já existe e já renderiza telas autenticadas todo dia: o app efêmero do
`visual-regression.yml` (PHP 8.4 + MySQL 8 + schema baseline + seeds Visreg biz=1/2/98/99 +
Inertia buildado + Playwright), com o auth-bridge `/_visreg-login/{id}` restrito a
`local|testing`. Faltava só ligar esse render ao recibo.

## Decisão

**D-6 emendado — o recibo de smoke ganha `host`**, enum fechado, gravado pelo registrador e
validado pelo schema (`bundle.schema.json` → `$defs.smokeReceipt.host`):

| `host` | O que é | Quem produz |
|---|---|---|
| `producao` | oimpresso.com, login humano, biz=1 | pessoa, `status.mjs --record-smoke` (default) |
| `staging-ct100` | clone anonimizado no CT 100 | pessoa ou agente com acesso ao CT 100 |
| `ci` | app efêmero do próprio GitHub Actions, seed biz=1/2 do visual-regression | `design-smoke-ci.yml` + `smoke-consumir.mjs` |

- **`ci`:** o workflow `design-smoke-ci.yml` (push em `main` + dispatch) sobe o app, seleciona
  as telas `tested|validated` do `application-report.json`, deriva a rota (`screen.route` se
  existir; senão `route:` → `url:` → `page:` do charter ao lado do `.tsx`, só valores que
  começam com `/`), renderiza cada uma logada como o admin do biz=1 e publica PNG + `manifest.json`
  na branch órfã `governance/design-smokes`. `scripts/design-sync/smoke-consumir.mjs` baixa a
  órfã, casa cada smoke com o alvo **atual** pelo blob git do `.tsx` no instante do render e
  chama `status.mjs --record-smoke … --tenant 1 --host ci`. Alvo que mudou depois do render é
  pulado com motivo — nunca vira recibo.
- **Tenant continua 1** em qualquer host. `biz=4` continua recusado (ADR 0358).
- **Screenshot durável:** `scripts/design-sync/state/smokes/<slug-da-tela>.png`, um por tela,
  sobrescrito a cada consumo (o hash no recibo é o que prova qual foto sustenta o estado).
- **`deploySha`** = SHA do commit renderizado (`github.sha` no CI), não o SHA de quem grava.
- O único escritor de recibo continua sendo `status.mjs` (D-1 da 0384: uma projeção, nenhum
  ledger paralelo). Recibos anteriores a esta emenda, sem `host`, contam como `producao`.

## Consequências

**Positivas:** `validated` deixa de ser inalcançável; a distinção "testou" × "abriu de pé numa
máquina real com dado" volta a existir; o relatório diz **onde** o smoke foi feito, então
produção e CI não se confundem num mesmo número.

**Custos:** mais um workflow pós-merge (~10-15 min quando há tela elegível; ~1 min quando não
há), advisory e registrado no `gates-registry.json` com `promote_by`; a órfã
`governance/design-smokes` é force-pushed a cada `main` (só o último retrato vive lá — o
histórico é o hash no ledger, não a branch).

**O que NÃO muda:** `biz=4` proibido; teste verde antes do smoke (o consumidor recusa tela que
regrediu a `applied`); invalidação em cascata do D-7 (mudou o `.tsx`, cai o smoke); a catraca
gradual do D-8; nenhum check novo vira required — `design-smoke-ci.yml` nunca falha o merge.

**Não decidido:** dispensar o smoke de produção onde o CI já fotografou — os dois hosts
coexistem no recibo e a leitura "só CI basta?" é decisão futura, com o número de telas
`validated` por host na mão.
