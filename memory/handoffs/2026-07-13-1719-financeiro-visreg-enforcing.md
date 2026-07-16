---
date: "2026-07-13"
time: "17:19 BRT"
slug: financeiro-visreg-enforcing
tldr: "Financeiro passou de cobertura visual advisory para estados e fluxos com baseline e gate enforcing; PRs #4236–#4239 estão mergeadas."
decided_by: ["W"]
prs: [4236, 4237, 4238, 4239]
next_steps:
  - "Investigar o drift de cinco snapshots remanescentes e estabilizar o update de baseline."
  - "Corrigir oficina-os dark antes de promover os estados globais para enforcing."
---

## Estado MCP no momento do fechamento

- Brief diário: SDD composto 55,3; Visual regression CI sem flag crítica.
- Nesta sessão, as tools MCP de `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` não estavam expostas; não foi inventado snapshot adicional.
- GitHub confirmado: PRs #4236, #4237, #4238 e #4239 estão `MERGED`; não há PR aberta nas branches de ativação.

## TL;DR

Financeiro agora tem baselines e gate enforcing para estados e fluxos. A promoção global ficou corretamente limitada pelo drift ainda real de Oficina dark.

## O que aconteceu

O diagnóstico adversarial estava correto: uma baseline ausente deixava testes advisory verdes sem provar render. O pacote tornou a prova do Financeiro executável e bloqueante:

- #4236 corrigiu o modo `--update-snapshots`, que antes comparava contra baseline velha mesmo no update.
- #4237 criou as baselines de cinco estados isolados e 12 fluxos Financeiro nos viewports compact, desktop e wide.
- #4238 estabilizou cinco snapshots que ainda variaram numa segunda atualização.
- #4239 promoveu **estados do Financeiro** e **fluxos visuais Financeiro** a enforcing; o gate agora falha em regressão acima dos thresholds.

Tentativa de promover todos os estados globais foi deliberadamente limitada: `oficina-os · estado=dark` ainda difere 6,7793%, portanto o grupo global permanece advisory. Isso evita declarar cobertura que não existe.

## Artefatos gerados

- `tests/Browser/Support/VisregThreshold.php` — update de baseline respeita `--update-snapshots`.
- `.github/workflows/visual-regression.yml` — estados Financeiro e fluxos Financeiro enforcing.
- Snapshots Financeiro para estados e fluxos, commitados pelas PRs de baseline.

## Persistência

- Git: PRs #4236–#4239 mergeadas em `origin/main`.
- Handoff e session log desta sessão serão publicados em PR documental própria para o índice canônico e o webhook MCP.
- BRIEFING do módulo não foi alterado: a mudança é de teste/CI, sem nova capacidade funcional para cliente.

## Próximos passos pra retomar

`gh run list --workflow visual-regression.yml --limit 10`

## Lições catalogadas

- Teste visual sem baseline armada é observabilidade, não proteção.
- `workflow_dispatch` de update precisa ignorar a comparação; do contrário a manutenção da baseline quebra.
- Um green CI só vale para a cobertura que realmente está enforcing.

## Pointers detalhados

- PR #4236: correção do update.
- PR #4237 e #4238: baselines.
- PR #4239: ativação enforcing.
