---
roadmap_item: MV1
slug: espinha-dorsal-vital-signs
onda: 0
status: executed
executed_at: "2026-07-05"
depende_de: []
destrava: [MV2]
related_adrs: [256, 264, 314]
esforco_estimado: "0.5d codável (219 scorecards já existiam; é agregação + frescor + trend)"
---
# MV1 · Espinha dorsal dos sinais vitais (stream MV — Módulo Vivo)

> **Origem:** decisão Wagner 2026-07-05 — *"o software deve amadurecer e se tornar vivo por módulo"*.
> Pesquisa + desenho completo: [arte 2026-07-05](../../../sessions/2026-07-05-arte-maquina-governanca-telas.md)
> (Meta ACH · Spec Kit · Playwright Test Agents · Infection MSI · fitness functions).
> Stream MV = a máquina que cuida da frota (especificações + memórias + tarefas integradas);
> Wagner só aparece nos gates humanos (aprovar batch, gate visual, promoção de nível).

## Problema (2-3 frases)
As peças de governança existem (219 scorecards de tela com ratchet, casos-gate, dominio-gate,
screen-coverage, anchor-lint, screen-qa-specialist, audit-to-backlog) mas **ninguém as lê em
conjunto**: não há agregação tela→módulo ("pior tela puxa"), não há frescor que degrade
(scorecard de 60d parece tão confiável quanto o de ontem), e não há fila de prioridade
cross-módulo. Sem essa leitura unificada, o cron-metabolismo (MV2) não tem o que priorizar
— e Wagner segue sendo o único agregador manual.

## O que foi entregue (verificável)
- **`scripts/qa/vital-signs.mjs`** — determinístico, zero LLM (padrão ADR 0256): lê os
  scorecards + universo real de telas (`Pages/`, excl. pastas `_`) + presença de contrato
  (charter/casos ao lado do .tsx) e produz:
  - prontuário por módulo: `nota_min` (pior tela puxa) AO LADO da média · `sem_scorecard` ·
    `charter_pct`/`casos_pct` · frescor (stale >30d dinheiro-fiscal / >60d resto; **nunca
    medido = stale** — não medido ≠ bom);
  - fila de prioridade `peso_criticidade × (100 − nota) × 1.5-se-stale` (tela sem scorecard
    entra com nota 0 — pior caso honesto);
  - `--json` → `memory/governance/vital-signs.json` (snapshot que o MV2 lê);
  - `--history` → `memory/governance/vital-signs-history.jsonl` (trend append-only).
- **`scripts/qa/vital-signs.test.mjs`** — selftest ancorado no CONTRATO (arte §3.2/§3.4/§3.5),
  não na implementação: pior-tela-puxa, thresholds de frescor por classe, fórmula de
  prioridade, "sem prontuário nunca vira verde por omissão".

## DoD (counterfactual)
- `node scripts/qa/vital-signs.test.mjs` → exit 0 (16 checks de contrato).
- Counterfactual anti verde-stale: módulo com telas SEM scorecard aparece `⚠ stale` e as
  telas entram no TOPO da fila (verificado no run real: Financeiro/Impostos e ProvaViva a
  600 pontos — dinheiro sem prontuário vence tudo). Se alguém remover um scorecard, o módulo
  DEGRADA visivelmente em vez de sumir do radar.
- NÃO é gate (lei ADR 0314: nasce advisory; leitura, não bloqueio). As catracas seguem nos
  gates existentes (`screen-grades-ratchet`, `screen-coverage-map --check`).

## Primeiro run real (2026-07-05, baseline do trend)
Frota: **234 telas · 219 com scorecard · 141 charter · 25 casos.md · 15 stale**.
Topo da fila: Financeiro/Impostos (600) · Financeiro/ProvaViva (600) · depois cauda
Financeiro nota 64-70 sem casos. Confirma o diagnóstico da arte: o gargalo é contrato
(casos.md 25/234), não ausência de teste.

## Próximo (MV2 — depende deste)
Cron-metabolismo no CT100 (nunca Hostinger — ADR 0062): lê `vital-signs.json` → seleciona
top-N da fila respeitando batimento por classe (arte §3.4) e budget → propõe batch via
`audit-to-backlog` (**gate humano Wagner** — publication-policy; nunca cria task sozinho) →
sessões de execução por tela (screen-qa-specialist) → gates registram → BRIEFING regenera.
