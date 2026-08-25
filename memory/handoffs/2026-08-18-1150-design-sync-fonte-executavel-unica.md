---
date: "2026-08-18"
time: "11:50 BRT"
slug: design-sync-fonte-executavel-unica
tldr: "[W] pediu limpar os arquivos conflitantes e manter uma única fonte de verdade. O PR #5914 consolidou IDs, destinos, fases e comandos em protocolo.config.mjs, reduziu F3/runbook/skill a ponteiros e definiu cowork/_ds como cache derivado. Remover o cache expôs e corrigiu um falso-verde do check-ignore."
prs: [5914]
decided_by: [W]
next_steps:
  - "[W] revisar e autorizar o merge do PR #5914; esta sessão não o mesclou."
  - "Depois do merge, a sessão Claude/DesignSync autenticada ainda precisa obter o bundle e as três fontes mono pendentes e provar o preview completo."
---

# A fonte única já existia no nome; as cópias continuavam mandando

## TL;DR

`prototipo-ui/protocolo.config.mjs` já se declarava fonte executável única, mas o procedimento
continuava copiado no protocolo F3, no runbook, na skill e no hook. Algumas cópias ainda carregavam
ZIP, staging, testes locais e gates do protocolo v1. O #5914 removeu a competição sem quebrar links
históricos: os paths antigos viraram ponteiros de compatibilidade.

## O que ficou canônico

- `prototipo-ui/PROTOCOL.md`: política, papéis, autoridade e invariantes v2;
- `prototipo-ui/protocolo.config.mjs`: únicos IDs, destinos, fases e comandos executáveis;
- `scripts/design-sync/mirror-snapshot/`: único destino versionado do runtime `_ds`;
- `prototipo-ui/cowork/_ds/`: somente cache gitignored, materializado pelo preview.

O selftest do painel agora lê cinco ponteiros e reprova se algum voltar a copiar os comandos ou os
IDs. Também reprova qualquer arquivo `_ds` rastreado dentro do espelho Cowork.

## Achado durante a limpeza

Seis arquivos do runtime estavam materializados no cache local. Depois da remoção segura, dois
testes do `absentLocal` ficaram vermelhos: o `git check-ignore` era recusado pelo safe-directory do
sandbox e o cache existente impedia que o ramo defeituoso fosse executado. A chamada agora passa a
raiz explicitamente, sem alterar configuração global. A suíte voltou a passar com `_ds` ausente.

## Provas

- `protocolo.config --selftest`: dois IDs medidos, cinco ponteiros sem cópia operacional e zero
  `_ds` rastreado;
- suites do applier e do comparador de frescor: verdes;
- hook, índice de skills, integridade de design-memory e wire do gate: verdes;
- `git diff --check`: verde;
- zero alteração em `Modules/` ou `resources/js/Pages/`.

## Residual

Esta limpeza não fabrica os quatro artefatos que já estavam ausentes. Ela garante que, quando forem
baixados, só haverá um destino versionado e uma receita executável para recebê-los.
