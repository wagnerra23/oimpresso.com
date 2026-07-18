# Calibração do juiz — rodada 2 · FOLHA CEGA (responder sem abrir o gabarito)

> **Para [W].** Rodada 2 do chip C10 (mecanismo mergeado em [#4472](https://github.com/wagnerra23/oimpresso.com/pull/4472)).
> A rodada 1 foi **contaminada** (o agente imprimiu o gabarito no chat ao montá-la). **Esta é limpa:**
> um juiz Fable fresco julgou os 12 abaixo em sessão isolada, gravou os vereditos em
> `gabarito-SELADO.md`, e devolveu ao agente **só a contagem** — o agente ficou cego.

## ⛔ Antes de responder

**NÃO abra `gabarito-SELADO.md` desta pasta** — ele tem as respostas do juiz. Abrir = rotular ancorado,
não às cegas → a entry vira `cego: false` (o validador rejeita, e está certo em rejeitar). Se você
abriu por engano, diga, e eu monto a rodada 3 com outro lote.

## O que responder

Para cada módulo, qual o **status canônico hoje**? Escolha 1 dos 8:

`producao` · `piloto` · `em-construcao` · `parcial` · `backlog` · `shared-infra` · `meta` · `deprecated`

Definições: **producao** = cliente real em prod · **piloto** = live mas 1 cliente/escopo restrito ·
**em-construcao** = ainda não serve cliente · **parcial** = existe mas falta o central OU flags OFF ·
**backlog** = feature-wish/quase inexistente · **shared-infra** = infra consumida por outros, não
vendável isolado · **meta** = governança/tooling interno · **deprecated** = legado sendo aposentado.

Responda na ordem, no chat (ex.: `1 em-construcao, 2 parcial, ...`). **Onde hesitar, comente** — a
hesitação é o dado mais valioso (marca onde a régua do juiz é frouxa).

| # | Módulo | seu status? |
|---|--------|---|
| 1 | **ComunicacaoVisual** | |
| 2 | **Compras** | |
| 3 | **KB** | |
| 4 | **NFSe** | |
| 5 | **ProjectMgmt** | |
| 6 | **Auditoria** | |
| 7 | **Accounting** | |
| 8 | **SRS** | |
| 9 | **ADS** | |
| 10 | **Manufacturing** | |
| 11 | **Pcp** | |
| 12 | **Officeimpresso** | |

---

## Procedência (auditável)

- **Juiz sob calibração:** subagente **Fable** (mesmo tier do refutador do ledger), sessão fresca,
  julgou cada módulo lendo o código em `origin/main` (não docs de status). Devolveu ao agente
  APENAS `12 módulos julgados + path` — zero vereditos no canal, então o rotulador ([W]) está limpo.
- **Amostra:** os 12 são de **fronteira** de propósito (verticais, legado, feature-wish, meia-boca) —
  módulos **fora** do lote #4274 (rodada 1) e **não** citados em nenhum chat. Nos casos óbvios um
  juiz acerta de graça; o sinal está nos difíceis.
- **Enum:** o mesmo do schema BRIEFING (`scripts/memory-schemas/briefing.schema.json`).

## Como o resultado será lido (declarado ANTES da resposta)

- **Concordância** = seu rótulo == o veredito do juiz. Discordância = **o juiz errou** (você é o gabarito).
- **N = 12.** Sem classe dominante forçada (escolha de fronteira), então o baseline trivial é mais
  baixo que os 72,7% da rodada 1 → mais sinal por item.
- Publicado como `K/12`, nunca arredondado pra adjetivo. Uma rodada não decide nada sozinha; o
  `--juiz-report` agrega as rodadas.
- É **MEDIÇÃO, não portão** — nenhum merge trava com esse número.

## Retomar (depois que [W] responder)

1. O agente lê `gabarito-SELADO.md` (só AGORA, com os rótulos de [W] já cravados no chat).
2. Mostra a tabela item-a-item, **incluindo onde o juiz divergiu**.
3. Registra 1 entry `tipo:"juiz"` em `governance/sdd-verification-ledger.json` (rotulador `[W]`, `cego:true`).
4. `node scripts/governance/ledger-check.mjs --juiz-report` sai do `NÃO CALIBRADO` pro 1º número real.
