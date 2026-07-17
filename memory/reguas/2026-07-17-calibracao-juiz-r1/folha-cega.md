# Calibração do juiz — rodada 1 · FOLHA CEGA (responder sem consultar nada)

> 🚨 **AVISO DE CONTAMINAÇÃO — leia antes de responder.** Na sessão que montou esta folha
> (2026-07-17), o agente **imprimiu o gabarito no chat do [W]** enquanto pesquisava: o comment
> do PR #4274 com `CONFIRMADO` item-a-item, e a tabela dos 11 `status` carimbados. **Se [W] leu
> aquela conversa, esta folha está queimada pra ele** — a resposta seria ancorada, e a entry tem
> que declarar `cego: false` (= rejeitada pelo validador, corretamente).
>
> Esta folha só vale para: (a) um rotulador humano que **não** leu aquela sessão, ou (b) [W]
> confirmando que não viu as saídas. Caso contrário: montar a **rodada 2** com um lote que não
> passou pelo canal, seguindo `_meta.schema_entry_juiz._quem_monta_nao_exibe` do ledger.
> Mantida versionada de propósito — o erro é evidência, não lixo.

> **Para [W].** Chip C10 da grade 2026-07-17 (orquestração-adversarial 6,0/10). O juiz — o
> refutador adversarial que aprova/reprova os lotes — **nunca foi conferido contra humano**.
> Sem denominador humano, "o juiz acerta" é prosa. Esta folha é o denominador.

## O que responder

Para cada módulo abaixo, qual é o **status correto hoje**? Escolha 1 dos 8:

`producao` · `piloto` · `em-construcao` · `parcial` · `backlog` · `shared-infra` · `meta` · `deprecated`

Responda no chat, na ordem (ex: `1 producao, 2 piloto, ...`). Se um módulo for ambíguo,
responda o que você diria a um cliente — e comente. **O comentário vale tanto quanto o rótulo:**
onde você hesitar é onde a régua é frouxa.

## ⚠️ O que NÃO fazer antes de responder (senão a rodada é inválida)

Não abrir: os `memory/requisitos/<Mod>/BRIEFING.md` · o PR #4274 · o `gabarito-SELADO.md`
desta pasta. Qualquer um deles entrega a resposta — e rotular vendo o veredito é ancoragem,
não calibração (`cego: true` no ledger é uma declaração sua).

## Os 11 itens

| # | Módulo | Status correto hoje? |
|---|---|---|
| 1 | **Crm** | |
| 2 | **Financeiro** | |
| 3 | **Governance** | |
| 4 | **Jana** | |
| 5 | **NfeBrasil** | |
| 6 | **OficinaAuto** | |
| 7 | **PaymentGateway** | |
| 8 | **RecurringBilling** | |
| 9 | **Repair** | |
| 10 | **Sells** | |
| 11 | **Whatsapp** | |

---

## Procedência da amostra (auditável)

- **Fonte:** PR [#4274](https://github.com/wagnerra23/oimpresso.com/pull/4274) — lote
  `backfill-briefings-status-updated-at`, entry no `governance/sdd-verification-ledger.json`.
- **Juiz sob calibração:** Fable 5 (sessão fresca, tier superior) refutando gerador Opus 4.8.
  Veredito da máquina: **aprovado**, `itens_verificados: 11`, `erros_confirmados: 0`,
  `error_rate_pct: 0`.
- **Amostra: 100% (11/11)** — é o lote inteiro, então **não há seleção** e nenhum viés de
  seleção meu. (O protocolo já exige 100% pra `anchors`; aqui sai de graça.)
- **Por que este lote e não outro:** varredura dos **31 PRs** do ledger (2026-07-17) —
  **18 não têm comment de refutação** (viveu em task ad-hoc/session log) e os 13 restantes são
  resumos que narram **só os refutados**. O #4274 é o **único** com veredito por item
  (11 itens, 1 linha cada). Foi seleção por **disponibilidade de dado**, não por conveniência.
- **Por que dá pra rotular em minutos:** os itens são status de módulo — você é a autoridade
  do domínio e sabe de cabeça. Não exige reverificar código (o custo que inviabiliza calibrar
  os lotes de `anchors`).

## Como o resultado será lido (declarado ANTES, pra não escolher a régua depois)

- **Concordância** = seu rótulo == o status que o juiz confirmou. Discordância = **o juiz errou**
  (você é o gabarito, não ele).
- **Baseline trivial a bater: 8/11 = 72,7%** — é o que um chutador tira respondendo `producao`
  em tudo (o lote tem 8 `producao`). Uma taxa perto disso não prova competência do juiz;
  o sinal está nos 3 itens não-óbvios.
- **N=11 é pequeno** e será publicado como está: `K/11`, nunca arredondado pra adjetivo.
  Uma rodada não decide nada sozinha — o placar do ledger agrega as rodadas.
- Isto é **medição, não portão**: nenhum merge trava com esse número
  (`ledger-check.mjs --juiz-report` sai 0 sempre).

## Depois que você responder

Confiro contra o gabarito, mostro a tabela item-a-item (incluindo onde o juiz errou), e
registro 1 entry `tipo: "juiz"` no ledger. `node scripts/governance/ledger-check.mjs --juiz-report`
passa de `NAO CALIBRADO` pro número real.
