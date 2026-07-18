# Rodada 2 — calibração do juiz · ESTADO: PAUSADA (esperando [W] rotular)

Chip C10 (grade de réguas 2026-07-17, dimensão orquestração-adversarial 6,0/10). O mecanismo
(`tipo: "juiz"` no ledger + `ledger-check.mjs --juiz-report`) já está em canon — [PR #4472](https://github.com/wagnerra23/oimpresso.com/pull/4472), mergeado 2026-07-17 (commit `0e3320b40c`).

## O que existe nesta pasta

| Arquivo | O que é | Pode abrir? |
|---|---|---|
| `folha-cega.md` | Os 12 módulos que [W] rotula (status, 1 palavra cada). Sem vereditos. | ✅ sim |
| `gabarito-SELADO.md` | Os vereditos do juiz Fable. **Selado.** | ⛔ só DEPOIS de [W] rotular |

## Por que está limpa (diferente da rodada 1)

A rodada 1 (`../2026-07-17-calibracao-juiz-r1/`) queimou porque o agente imprimiu o gabarito no
chat ao montá-la. Aqui: o juiz (subagente **Fable**, tier do refutador do ledger) rodou **isolado**,
gravou os vereditos direto no arquivo selado, e devolveu ao agente **só a contagem + path** — o
agente nunca viu os vereditos, então o canal do rotulador ([W]) está limpo. Regra em
`governance/sdd-verification-ledger.json _meta.schema_entry_juiz._quem_monta_nao_exibe`.

## Como retomar (numa sessão futura)

1. **[W]** preenche `folha-cega.md` — responde os 12 status no chat (não abre o gabarito antes).
2. O agente **só então** lê `gabarito-SELADO.md`, compara com os rótulos de [W], e mostra a tabela
   item-a-item (incluindo onde o juiz divergiu — [W] é o gabarito, não o juiz).
3. Registra 1 entry `tipo:"juiz"` no ledger:
   ```
   { "tipo":"juiz", "lote_id":"JUIZ-CAL-2026-07-r2", "data":"<data>", "pr":<PR>,
     "juiz":"ledger-refutador (Fable, status de módulo)", "rotulador":"[W]", "cego":true,
     "itens_rotulados":12, "concordancias":<K>, "concordancia_pct":<K/12*100>,
     "evidencia":"memory/reguas/2026-07-17-calibracao-juiz-r2/" }
   ```
   (o validador exige: rotulador com sigla do time, `cego:true`, e o pct fechar com K/12.)
4. `node scripts/governance/ledger-check.mjs --juiz-report` sai do `NÃO CALIBRADO` pro 1º número real.

## Limite honesto (perene)

Esta rodada calibra o juiz em **status de módulo** — barato pra [W] julgar de cabeça. **NÃO**
generaliza pra lotes de `anchors` (path/US vs código), que são a maioria do ledger e onde o custo
humano é proibitivo. É o 1º denominador, não o último.
