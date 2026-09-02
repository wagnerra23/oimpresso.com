---
id: resources-js-pages-jana-acoes-casos
casos: Jana Ações · fila HITL · prévia e recibo do servidor · aba da área · /ia/acoes
irmaos: Acoes.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-acoes.md (runbook) · prototipo-ui/contrato/jana-acoes.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-02"
---

# Casos de uso — /ia/acoes (aba Ações da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Derivados do `Acoes.charter.md`, do `jana-acoes.contract.json` e da âncora
> (`node prototipo-ui/ancora.mjs Jana/Acoes` → `jana-telas-novas.jsx` §`JmAcoesFila`) — **não** do
> `Acoes.tsx` (§5 2026-06-05). O que a âncora diz sobre FORMA vale; os números dela são do Martinho.

## UC-ACAO-00 — A aba existe na barra da área (4ª) e leva a `/ia/acoes`
Status: 🧪 (`AcoesContratoTest` — aguarda run verde na lane MySQL)

**Aceite:** Dado usuário com `jana.access` · Quando abre `/ia/acoes` · Então o ghost
`{key: acoes, label: Ações, href: /ia/acoes}` está na 4ª posição (Painel · Conversa · Alertas ·
**Ações** · Memória) e a página responde 200 com `Jana/Acoes`.

## UC-ACAO-01 — A fila traz as 5 ações do serviço, com CTA byte-idêntico e prévia do SERVIDOR
Status: 🧪 (`AcoesContratoTest`)

**Aceite:** a prop `acoes` tem exatamente as chaves de `AcaoHitlService::ACOES`, na ordem; cada
linha traz `cta` igual ao `ACOES[key]`, `titulo` igual a `TITULOS[key]`, e `previa`/`alcance` iguais
ao `previa(key, business)` do serviço no mesmo instante. `recibo` é `null` quando ninguém aprovou.

**Regressão que defende:** rótulo de CTA renomeado só na tela (o UC-JPAIN-12 do Painel amarra a
mesma paridade); prévia escrita no cliente.

## UC-ACAO-02 — Aprovar grava o recibo, e ele é do MEU business
Status: 🧪 (`AcoesContratoTest`)

**Aceite:** Dado uma aprovação registrada pela rota `jana.acoes.aprovar` · Quando abro `/ia/acoes` ·
Então a linha daquela chave traz `recibo` com `quem`, `quando` e a `previa` **gravada** (igual a
`AcaoAprovacao.previa`); uma aprovação de OUTRO business para a mesma chave **não** aparece (Tier 0).

## UC-ACAO-03 — Copy e ordem do contrato estão na tela
Status: 🧪 (`AcoesContratoTest` — asserção de ARQUIVO sobre o `alvo`)

**Aceite:** toda seção do `jana-acoes.contract.json` tem âncora `data-contract` no alvo, toda copy
pinada está presente, e a ordem das âncoras respeita `ordem`.

## Backlog de casos (sem id — entram quando tiverem teste)

- **[BACKLOG]** Disparo real (WhatsApp/e-mail) a partir do recibo — backend, PR próprio.
- **[BACKLOG]** Recusar/desfazer aprovação (`status=recusada`) — sem rota hoje.

## Trilha do tempo
- 2026-09-02 · trio nascido junto (charter + casos + Pest + e2e stub + contrato). Refs: UI-0013 · ADR 0264 G-1/G-2.
