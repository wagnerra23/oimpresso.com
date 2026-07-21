# Calibração do `funcao-scorecard` vs gold HUMANO · ESTADO: RODADA 1 FECHADA (7/9 = 77,8% · Cohen κ 0,591)

> **RODADA 1 FECHADA (2026-07-21).** [W] rotulou os 9 itens às cegas (todos `(canon)`); `rotulos-W.json` guarda os rótulos verbatim. `node scripts/governance/funcao-scorecard-humano.mjs --score memory/reguas/2026-07-21-calibracao-funcao-scorecard-humano/rotulos-W.json` → **K/9 = 7/9 (77,8%) · Cohen κ = 0,591** (moderate). Entry `tipo:juiz` no ledger (`ledger-check --juiz-report` = 1 rodada). É **medição, não portão** — o 1º denominador HUMANO do juiz, não o último.
>
> **As 2 divergências (tipadas):**
> - **#5 `calculateInvoiceTotal` C3 — miss-de-lookup:** juiz `incerto`, [W] `discordo (canon)`. O juiz deferiu por não achar o canon; [W] resolveu com o tópico canônico que registra o `false|array` como problemático. O juiz devia ter feito o lookup.
> - **#8 `FsmAuthorizationFlag` C7 — miss-de-direção (over-reach):** juiz `discordo`, [W] `concordo (canon)`. O juiz enfiou a LETRA do claim "reset no Octane" (uma questão de lifecycle/infra) num veredito de tipo/falha; [W] lê o C7 como o `bool` fail-secure honesto, com o Octane como concern **separado** (ressalva de [W], a verificar).
>
> **O achado que vale (confiança calibrada 2/2):** as duas divergências caíram EXATAMENTE nos dois itens que o juiz auto-marcou como os menos firmes no gabarito selado (#5 "o menos firme"; #8 "[W] pode ler como concordo com ressalva"). A incerteza do juiz **previu** onde ele ia divergir — concordância crua 7/9, mas a calibração de confiança foi perfeita.
>
> **Disclosure (auditabilidade `cego`):** o agente-scorer tinha lido o `gabarito-SELADO.md` no início desta sessão (investigação do #4626) — mas **nunca** exibiu o veredito no canal de [W] antes dele rotular; [W] respondeu independente, com citação de canon por item. `cego:true` vale pro canal do rotulador (regra `_quem_monta_nao_exibe`); a pontuação foi mecânica via `funcao-scorecard-humano.mjs`. Detalhe: [session 2026-07-21 rodada humana](../../sessions/2026-07-21-funcao-scorecard-rodada-humana-gap1.md).
>
> **Re-grade proposta:** validação-não-circular **9,0 → 9,2** (gap #1 ativado — 1ª medição humana; κ moderate, N=9, 1 rodada; o placar do ledger agrega). Pra 9,5-10: acumular rodadas + endereçar as 2 divergências (lookup do tópico no #5; escopar o C7 pra não engolir claim de infra no #8).

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

O cálculo deixou de ser manual: `node scripts/governance/funcao-scorecard-humano.mjs --template`
gera o JSON cego; depois dos 9 rótulos preenchidos, `--score <rotulos-W.json>` abre o selado e devolve
item-a-item, `K/9`, `concordancia_pct`, **Cohen κ** e o draft da entry `tipo:"juiz"`. Arquivo incompleto,
`cego:false`, rotulador diferente ou fonte fora de `canon|cabeca|nenhuma` falham antes da conclusão.

1. **[W]** preenche `folha-cega.md` — responde os 9 vereditos no chat (`concordo`/`discordo`/`incerto`/
   `n/a` + marca `(canon)`/`(cabeça)` quando resolve). Não abre o gabarito antes.
2. O agente **só então** lê `gabarito-SELADO.md`, compara com os rótulos de [W], mostra a tabela
   item-a-item (incluindo onde o juiz divergiu) e separa os 4 modos de discordância (deferência-certa /
   miss-de-lookup / super-afirmação / miss-de-direção — ver `folha-cega.md`).
3. Registra 1 entry `tipo:"juiz"` no ledger (o scorer já devolve o draft):
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
