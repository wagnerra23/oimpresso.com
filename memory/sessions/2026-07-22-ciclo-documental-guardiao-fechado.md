---
date: "2026-07-22"
hour: "10:08 BRT"
duration: "~2h"
topic: "Fechamento do ciclo documental com máquina de processo, recibo do mesmo detector e ZELADOR semanal"
authors: [W, C]
outcomes:
  - "Agregador determinístico de desvios documentais e recibo antes→depois implementados e testados."
  - "Máquina de processo e automação guardiã semanal ativadas, limitadas a um PR sem merge por rodada."
  - "PR #4671 aberto para ratificação humana."
prs: [4671]
us: []
related_adrs:
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Session log 2026-07-22 — ciclo documental com guardião e recibo

## TL;DR

Foi implementado o ciclo completo: detectar deriva documental, selecionar um desvio, corrigir o dono, provar o fechamento com o mesmo ID/detector, abrir PR e revalidar após merge. O PR #4671 ficou aberto para ratificação; a automação semanal já foi ativada, mas nunca faz merge.

## Contexto

W perguntou se a documentação técnica existia, como deveria ser estruturada, se máquinas de processo e hooks deveriam participar, se a estrutura permaneceria no tempo e se um guardião fecharia o ciclo. Depois de revisar o canon e as abordagens rejeitadas, W autorizou a implementação com “pode fazer”.

## Cronologia

| Quando | Evento |
|---|---|
| 08:10–09:20 | Canon, handoff, sessão recente, ADRs e detectores existentes foram inspecionados. |
| 09:20–09:50 | O loop documental e a máquina de processo foram implementados; o ZELADOR e o registry de automações foram conectados. |
| 09:50–10:05 | Foram executados selftests, auditoria Node e controles reais negativo/positivo contra `origin/main`. |
| 10:07–10:08 | Commit `9dd5c9cfaa` publicado e PR #4671 aberto sem merge. |

## Entregas

- **Agregador/recibo documental** — `scripts/governance/documentation-loop.mjs`.
- **Máquina de processo** — `.claude/workflows/documentacao-tecnica.js`.
- **Selftest registrado em CI** — `scripts/governance/documentation-loop.test.mjs` e `.github/workflows/governance-script-tests.yml`.
- **Guardião e liveness** — `scripts/governance/ZELADOR.md` e `memory/governance/AUTOMATIONS.md`.
- **Automação Codex** — `zelador-ciclo-documental`, segundas-feiras às 07:10 BRT, ativada em 2026-07-22.
- **PR #4671** — “feat(governance): fecha ciclo documental com recibo” → aberto.

## Decisões cinzentas resolvidas

| Pergunta | Decisão Wagner | Justificativa |
|---|---|---|
| Implementar ou apenas planejar? | Implementar — “pode fazer”. | A autorização cobriu a estrutura discutida, as máquinas e o guardião. |
| O fechamento poderia ser inferido por melhora de métrica? | Não; o ID precisa desaparecer no mesmo detector. | Evitou um falso verde por alteração colateral da contagem. |
| A automação poderia alterar vários documentos? | Não; no máximo um desvio acionável por rodada. | Limitou blast radius e preservou revisão humana. |

## Aprendizados / pegadinhas

- Um recibo documental precisa guardar a identidade estável do desvio; reduzir uma métrica global não prova que o item original foi resolvido.
- A comparação em worktree temporária permitiu medir `origin/main` e a correção sem contaminar o checkout compartilhado.
- A composição dos detectores existentes fechou o ciclo sem criar uma quarta fonte de verdade.

## Próximos passos (não-bloqueante)

- [ ] W revisar e ratificar o PR #4671.
- [ ] O primeiro ZELADOR posterior ao merge confirmar o ID ausente em `main` e publicar liveness.

## Referências

- Handoff: [2026-07-22-1008-ciclo-documental-guardiao-fechado.md](../handoffs/2026-07-22-1008-ciclo-documental-guardiao-fechado.md)
- ADR 0270: [Ciclo de vida da informação](../decisions/0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md)
- ADR 0314: [Poda de gates e lei de fusões](../decisions/0314-poda-gates-onda-2-lei-fusoes.md)
