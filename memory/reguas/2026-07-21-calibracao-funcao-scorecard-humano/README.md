# Calibração do `funcao-scorecard` vs gold HUMANO · ESTADO: PAUSADA (esperando [W] rotular)

Braço **"κ vs gold HUMANO" + "incidente com função REAL"** que o método `funcao-scorecard` nomeou
como o gap restante (§5 rodada 3, [FUNCAO-SCORECARD-METODO.md](../../requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md):
*"Falta pra 8-9: braço-incidente com função REAL (não sintética), κ vs gold HUMANO (hoje é κ vs rótulo
objetivo)…"*). Reusa o mecanismo `tipo:"juiz"` já canon ([PR #4472](https://github.com/wagnerra23/oimpresso.com/pull/4472),
`governance/sdd-verification-ledger.json _meta.schema_entry_juiz` + `ledger-check.mjs --juiz-report`).

## Por que existe (o que a fixture NÃO cobre)

| Ground-truth | O que prova | Limite |
|---|---|---|
| **Fixture de mutação** ([`tests/governance-fixtures/funcao-scorecard/`](../../../tests/governance-fixtures/funcao-scorecard/)) | O juiz acha o **defeito mecânico** não-circularmente. κ=1,0 vs **rótulo objetivo** (a mutação). Twins sintéticos, cegueira por construção. | Rótulo objetivo, defeito plantado. Não mede intenção-ambígua. |
| **Esta rodada** (gold humano) | O juiz acerta quando **a intenção é ambígua** — `incerto` certo, super-afirmação, deferência-por-doc-gap. Funções **REAIS** de risco. | Custo humano ([W] rotula); N pequeno; canon é contexto (correto aqui, cola na fixture). |

São **complementares** — dois ground-truths, não um estende o outro. O número desta rodada **não**
substitui a fixture e vice-versa.

## O que existe nesta pasta

| Arquivo | O que é | Pode abrir? |
|---|---|---|
| `folha-cega.md` | As 9 funções que [W] rotula (1 veredito por critério). Sem vereditos do juiz. | ✅ sim |
| `gabarito-SELADO.md` | Os vereditos do juiz Fable + evidência + âncora. **Selado.** | ⛔ só DEPOIS de [W] rotular |

## Por que está limpa (`_quem_monta_nao_exibe`)

O juiz (subagente **Fable**, tier do refutador do ledger) rodou **isolado**, aplicou a rubrica dos 8
critérios lendo o código real, gravou os vereditos direto no arquivo selado, e devolveu ao agente
**só a contagem + path**. O agente **nunca viu** os vereditos → o canal do rotulador ([W]) está limpo.
Regra: `governance/sdd-verification-ledger.json _meta.schema_entry_juiz._quem_monta_nao_exibe` (o erro
da 1ª montagem da rodada 1 de status — imprimir o gabarito no chat — não se repete aqui).

## Como retomar (numa sessão futura, depois que [W] rotular)

1. **[W]** preenche `folha-cega.md` — responde os 9 vereditos no chat (`concordo`/`discordo`/`incerto`/
   `n/a` + marca `(canon)`/`(cabeça)` quando resolve). Não abre o gabarito antes.
2. O agente **só então** lê `gabarito-SELADO.md`, compara com os rótulos de [W], mostra a tabela
   item-a-item (incluindo onde o juiz divergiu) e separa os 4 modos de discordância (deferência-certa /
   miss-de-lookup / super-afirmação / miss-de-direção — ver `folha-cega.md`).
3. Registra 1 entry `tipo:"juiz"` no ledger:
   ```json
   { "tipo":"juiz", "lote_id":"JUIZ-CAL-2026-07-funcao-scorecard-humano", "data":"<data>", "pr":<PR>,
     "juiz":"funcao-scorecard (Fable, funções reais de risco — C1/C2/C3/C6/C7)", "rotulador":"[W]",
     "cego":true, "itens_rotulados":9, "concordancias":<K>, "concordancia_pct":<K/9*100 com 1 casa>,
     "evidencia":"memory/reguas/2026-07-21-calibracao-funcao-scorecard-humano/" }
   ```
   (o validador exige: `rotulador` com sigla do time, `cego:true`, `concordancia_pct` fechar com `K/9`,
   e **não** carregar `veredito`/`error_rate_pct` — esses são de refutação de lote.)
4. `node scripts/governance/ledger-check.mjs --juiz-report` agrega esta rodada com as de status.

## Limite honesto (perene)

- Este braço calibra o juiz em **função real de risco com intenção ambígua** (C1/C2/C3/C6/C7).
  **NÃO** cobre C4/C5/C8 (mecânicos — a fixture os pega melhor), nem generaliza pro juiz de **lote**
  do ledger (aquele julga anchors/prosa, não funções).
- **Contaminação por canon é aceita aqui** (ao contrário da fixture sintética): achar a ADR 0066/0093
  ao julgar código real **é** o que um humano faria. Logo mede "o juiz aplica a rubrica + acha o canon",
  não "acerta do zero".
- **N=9** é pequeno e será publicado como `K/9`, nunca arredondado pra adjetivo. É o 1º denominador
  humano deste juiz, não o último — o placar do ledger agrega rodadas.
