# ADR UI-0020 · Dark mode WARM (hue 282, DS-v6) — retune dos tokens dark azulados

- **Status**: accepted
- **Data**: 2026-07-07
- **Aprovado em**: 2026-07-07 — Wagner: *"autorizo tudo"*, em resposta à proposta explícita do retune ("a decisão que destrava o resto: tokens dark 258/265 → 282, chroma baixo, igual ao protótipo")
- **Decisores**: Wagner (aprovação), Claude Code (medição + execução)
- **Categoria**: ui · fundações · tokens
- **Refs**:
  - Gabarito canônico: [`prototipo-ui/cowork/ds-v6/gabarito-vendas.html`](../../../../../prototipo-ui/cowork/ds-v6/gabarito-vendas.html) (bloco dark, linhas ~25-36) — fonte de TODOS os valores novos, verbatim
  - [UI-0018](0018-canon-visual-vivo-ds-v6-manual-identidade.md) — DS-v6 como canon visual vivo
  - [financeiro-unificado-visual-comparison.md §Round 2026-07-07](../../../Financeiro/financeiro-unificado-visual-comparison.md) — o smoke dark que expôs o delta
  - ADR 0300 (DTCG SSOT) — o fluxo de edição usado (json → `tokens:build` → CSS gerado)

## Contexto

O smoke dark do Financeiro (2026-07-07, [W] *"ainda não bateu... cor na lista... o que pode estar interferindo?"*) mediu por sonda DOM que o dark de produção usa **neutros azulados** herdados do shadcn default — `background oklch(0.137 0.036 258.5)`, `card 0.208 0.04 265.7`, `border 0.27 0.006 250`, `muted-foreground 0.68 0.01 250` — enquanto o protótipo canônico (DS-v6) define o dark **warm**: hue 282, chroma 4-5× menor (`bg 0.165 0.008 282`, `surface 0.205 0.009 282`, `border 0.30 0.012 282`, `text 0.965 0.004 282`). O grupo `cockpit` do DTCG também estava divergente do próprio gabarito (hue 240 vs 282).

Efeito percebido: **toda superfície, linha e texto do app em dark "não bate" com o protótipo**, mesmo quando estrutura, tamanhos e pesos estão idênticos (ex.: th da lista 10px/500 nos dois — só a cor destoava).

## Decisão

1. **O dark do oimpresso é WARM (hue 282, chromas do DS-v6), verbatim do gabarito.** Retune aplicado em 37 tokens dark do `semantic.tokens.json` (grupos `semantic` shadcn, `cockpit`, `sidebar`, `bubble/thread`), via fluxo DTCG canônico (`tokens:build`; `dtcg-equivalence` 296/296 ✓).
2. **Cores funcionais NÃO mudam**: `info*` (azul semântico), paleta de charts (slate/blue/indigo/violet…), personas (customer/supplier/employee), `stage-*`, `origin-*`, `kpi-feature` (hero navy intencional), `canal-fb` (cor de marca) — azul ali é significado, não tema.
3. **Light inalterado** (nenhum `$value` light tocado).
4. Follow-up obrigatório no merge: re-gerar snapshots dark do VRT (`pest tests/Browser/ --update-snapshots` no CT100) e commitar — o PR de tokens sofre skip-as-pass no gate visual (não toca paths de UI), então os baselines antigos quebrariam o próximo PR de UI alheio.

## Consequências

- Todas as telas em dark mudam de temperatura de uma vez — é o objetivo (1 fix de fundação em vez de N fixes por tela).
- Sondas de comparação prod×protótipo passam a bater também em cor no dark (o delta "fundo/linha/texto diferente" morre na raiz).
- Regra de método pareada (RUNBOOK aplicar-prototipo Fase 1): **toda sonda de estilo mede nos DOIS temas** — lição [W] 2026-07-07 ("a máquina tem que pegar os dois temas").
