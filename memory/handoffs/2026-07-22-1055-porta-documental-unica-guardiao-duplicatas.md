---
date: "2026-07-22"
time: "10:55 BRT"
slug: "porta-documental-unica-guardiao-duplicatas"
tldr: "O README raiz virou a única porta global; a antiga porta gerada foi transformada em onboarding específico de agente; hook e memory-health passaram a bloquear duplicatas documentais."
decided_by: [W]
cycle: null
prs: [4672]
us: []
next_steps:
  - "Confirmar os required checks do PR #4672 e executar o merge já autorizado por Wagner."
related_adrs:
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Handoff 2026-07-22 10:55 BRT — porta documental única e guardião de duplicatas

## TL;DR

O problema visível que faltou no PR #4671 foi corrigido no PR #4672. O `README.md` passou a ser a única porta global e oferece rotas por objetivo. `GUIA-DO-SISTEMA.md`, `memory/INDEX.md` e `memory/governance/_README.md` ficaram explicitamente como guia, catálogo e porta local. A antiga `memory/reference/COMECE-AQUI.md`, gerada por `system-map.mjs`, foi renomeada na própria máquina para `ONBOARDING-AGENTE-GERADO.md` e deixou de competir com o README.

## Entregas

- `README.md`: rota produto/operação, arquitetura, agentes, módulos, execução, infraestrutura e catálogo.
- `scripts/governance/document-authority.mjs`: uma implementação de identidade documental compartilhada.
- `.claude/hooks/block-memory-drift.mjs`: bloqueio pré-escrita de conteúdo vivo idêntico e colisão `type+slug`.
- `scripts/governance/memory-health.mjs` Check Q: porta canônica única, sem heading global paralelo, sem conteúdo idêntico e sem `type+slug` repetido no merge.
- `system-map.mjs` + workflow + canário: a saída derivada passou a ser onboarding específico de agente.
- Changelogs de CRM e Essentials: a única duplicata literal real foi desambiguada sem inventar histórico.

## Provas

| Prova | Resultado |
|---|---|
| Hook: cópia literal | bloqueou com exit 2 |
| Hook: mesmo `type+slug` | bloqueou com exit 2 |
| Hook: documento único | liberou com exit 0 |
| Gate-selftest Check Q | fixture boa/ruim 2/2 |
| `memory-health` real | Check Q 0 fail / 0 warn |
| Vitest `memoryHealth.spec.ts` | 38/38 |
| `system-map --check` | verde |
| `onboarding-paths-check` | 2 docs, 0 paths mortos |

## Estado MCP no momento do registro

As tools MCP do oimpresso não foram expostas nesta sessão do Codex. O estado foi conferido por filesystem, git e GitHub CLI; nenhum retorno MCP foi inventado.

## Referências

- PR: [#4672](https://github.com/wagnerra23/oimpresso.com/pull/4672)
- Session log: [2026-07-22-porta-documental-unica-guardiao-duplicatas.md](../sessions/2026-07-22-porta-documental-unica-guardiao-duplicatas.md)
- Handoff anterior: [2026-07-22-1008-ciclo-documental-guardiao-fechado.md](2026-07-22-1008-ciclo-documental-guardiao-fechado.md)
