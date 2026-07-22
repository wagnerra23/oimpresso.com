---
date: "2026-07-22"
hour: "10:55 BRT"
duration: "1h"
topic: "Porta documental única e prevenção mecânica de duplicatas"
authors: [W, C]
outcomes:
  - "README raiz promovido a única porta documental global com rotas por objetivo."
  - "Antiga porta gerada transformada em onboarding específico de agente na própria máquina system-map."
  - "Hook e memory-health Check Q fecharam criação local e bypass de merge de duplicatas."
prs: [4672]
us: []
related_adrs:
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Session log 2026-07-22 — porta documental única e guardião de duplicatas

## TL;DR

W apontou que a execução anterior não havia mudado a experiência visível e lembrou que a estrutura ideal havia sido sugerida pelo próprio assistente no início da conversa. A auditoria encontrou três declarações de entrada em `README`, `GUIA` e `INDEX`, mais uma quarta porta gerada em `memory/reference/COMECE-AQUI.md`. A correção foi feita na estrutura e nas máquinas que a mantêm.

## Decisões e execução

1. O `README.md` foi fixado como porta global única; demais índices viraram rotas ou catálogos explícitos.
2. Portas locais `README.md` de módulos continuaram permitidas; o Check Q não as confunde com uma porta global.
3. A saída gerada `COMECE-AQUI.md` não foi editada como exceção: `system-map.mjs`, workflow e canário foram corrigidos e a saída foi renomeada.
4. O hook existente foi estendido, em vez de criar outro hook.
5. O `memory-health` existente recebeu o Check Q fail-class, em vez de criar outro workflow ou baseline.
6. A duplicata literal CRM/Essentials foi corrigida e o repositório real terminou com Check Q zerado.

## Validação

- `node .claude/hooks/block-memory-drift.test.mjs` — verde, incluindo três casos Q.
- `node scripts/governance/gate-selftest.mjs --only memory-health-authority` — 2/2.
- `npm run test:memory-health` — 38/38.
- `node scripts/governance/system-map.mjs --check` — verde.
- `node scripts/governance/onboarding-paths-check.mjs` — 0 paths mortos.
- `node scripts/governance/memory-health.mjs --json --warn-only` — 0 fails; Check Q sem achados.

## Autorização

W havia pedido “faça tudo do plano” e “merge” nesta conversa. O PR #4672 foi criado dentro desse escopo; no momento deste registro, os checks remotos ainda seriam aguardados antes do merge.

## Referências

- Handoff: [2026-07-22-1055-porta-documental-unica-guardiao-duplicatas.md](../handoffs/2026-07-22-1055-porta-documental-unica-guardiao-duplicatas.md)
- PR: [#4672](https://github.com/wagnerra23/oimpresso.com/pull/4672)
