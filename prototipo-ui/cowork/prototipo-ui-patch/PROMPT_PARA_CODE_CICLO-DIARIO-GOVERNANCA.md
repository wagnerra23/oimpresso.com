# PROMPT_PARA_CODE — Ciclo diário de governança (resolve + gradua todo dia, sem [W] cutucar)

> **Origem:** [CC] 2026-06-03. [W]: *"não quero repetir 2x nem ficar pedindo pra organizar — resolve e gradua todo dia."*
> **Natureza:** §10.4 PROPOSTA. **Estende o que JÁ roda** (`jana:health-check` cron · `loop-fechar-o-loop.json` · loop 0-humano ADR 0241 · `review-freshness` #2078). **NÃO** cria daemon novo, **NÃO** numera ADR, **NÃO** mergeia Tier 0.
> **Pré-req:** as pontes irmãs `governanca:scorecard` + `protocol_freshness` (mesmo lote). Este job as orquestra; sozinho não tem o que ler.

## Passo 0 — verificar vs origin/main (estender, não recriar)
1. `jana:health-check` (`HealthCheckCommand`) já roda diário em cron? Confirmar o agendamento real (Kernel/schedule).
2. `loop-fechar-o-loop.json` (SessionStart) + `review-freshness.mjs` (#2078) + `design-return-gate.yml` existem? São os tijolos — reusar.
3. `COWORK_NOTES.md` é o inbox `[W]→[CC]`/`[CD]`? É o ponto onde decisão de [W] entra. Confirmar.

## O ciclo (1 orquestrador diário sobre checks que já existem)
1. **Regenera o estado** → roda `governanca:scorecard` e escreve `storage/reports/governanca-state.json` (aprovado = derivado do `main`; pendente = PRs abertas + pontes; a-fazer = fila). **O painel do Cowork passa a ler esse JSON** no re-sync, em vez de [CC] digitar.
2. **Frescor** → roda `governanca_graduation_ratio` + `protocol_freshness` + charter-coverage + `review-freshness`. Acende (advisory) o que defasou.
3. **Gradua o inbox** → toda entrada nova de [W] em `COWORK_NOTES.md` é **obrigada a receber `Graduação:`** (MEC→check / JULG→regra) em ≤1 ciclo; sem isso = amarelo no digest. **É o "não repetir 2x": a decisão de [W] vira regra/check uma vez, e não é re-litigada.**
4. **Drena a fila** → o que é §10.4/mergeable o [CL] resolve autônomo (já 0-humano, ADR 0241); **só Tier 0 sobe pra [W]**.
5. **Digest diário** → UMA saída (`storage/reports/governanca-digest.md` + append em `CODE_NOTES.md`): *"graduou X · acendeu Y · espera [W] em Z"*. [W] lê 1 coisa/dia, não cutuca.

## Guards / Tier 0 (não cruzar)
- **Advisory** — nada derruba o cron; nada auto-mergeia Tier 0.
- **NÃO** numerar ADR (soberania [W], 0238). **NÃO** flipar nada irreversível. O digest é informativo; o irreversível continua [W].
- **NÃO** criar 7º motor de score — orquestrar os existentes (G1).

## §10.4 / autorização
Aditivo, só orquestra checks que já existem + escreve 2 artefatos (state.json, digest.md). Retorno em `CODE_NOTES.md` com o **1º digest real** gerado no `main` de hoje.

## new_design_memories
- **golden**: a governança/painel se mantém por um **ciclo diário** (estende `jana:health-check`) que regenera o estado, roda frescor, **gradua o inbox de [W]** e drena a fila — [W] só toca Tier 0 e lê 1 digest/dia. Tira [W] **e** [CC] do caminho recorrente (anti "repetir 2x" / "pedir pra organizar").
- **regra**: decisão de [W] no inbox sem `Graduação:` em ≤1 ciclo = amarelo — toda decisão grada (vira check ou regra) ou aparece como dívida.
