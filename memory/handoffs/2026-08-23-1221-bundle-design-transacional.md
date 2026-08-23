---
date: "2026-08-23"
time: "12:21 BRT"
slug: bundle-design-transacional
tldr: "O Design Sync agora baixa snapshot uma vez e depois somente delta; valida o lote inteiro em staging, promove com rollback e publica a lista fonte→Page→módulo com evidência aplicada/testada ligada a hashes. `_ds` permanece cache derivado."
prs: [6150]
decided_by: [W]
next_steps:
  - "Acompanhar os checks e mesclar a PR #6150; a ADR 0379 nasce proposta e exige flip próprio para ratificação."
  - "Na próxima rodada real do Cowork, gerar/aplicar o snapshot v2 e versionar active-bundle.json, application-report.json e applications.json junto das Pages React."
---

# O Design agora chega como transação — e “recebido” não significa “aplicado”

## Como deveria ser

A primeira rodada baixa um snapshot completo. As próximas recebem o manifesto anterior e baixam
somente arquivos adicionados ou modificados. Arquivos removidos são declarados sem transportar
conteúdo; inalterados não viajam. Todas as partes dizem a qual bundle pertencem, qual base exigem
e quantas partes existem.

O consumidor remonta chunks SHA-256 e monta quatro stagings: espelho Cowork, design-docs, runtime
do preview e estado. Só promove depois de validar o estado-alvo inteiro e seu grafo. Parte ausente,
hash corrupto ou base errada não toca no vivo. Falha durante os swaps restaura os roots anteriores.

## A lista que faltava

`scripts/design-sync/status.mjs` lê o relatório canônico e mostra:

- arquivo adicionado/modificado/removido;
- fonte do Design;
- Page React de destino;
- módulo, inclusive Superadmin e Officeimpresso;
- se está pendente, aplicada, testada, a criar ou bloqueada;
- ação seguinte quando o vínculo não é inequívoco.

Mapeamentos 1:N são preservados: uma fonte pode alimentar várias Pages. Receber a fonte no espelho
não marca essas Pages como prontas. Após aplicar e testar, a evidência registra hashes de fonte e
alvo; qualquer mudança posterior invalida a prova e devolve a tela a pendente.

## Por que `_ds` fica em cache

`_ds` é a forma executável do design system para o preview. Ele contém build/tokens/fontes que
podem ser reconstruídos e não deve ser base de sincronização nem histórico. Transformá-lo em
estado oficial criaria segunda fonte e misturaria produto durável com artefato derivado. Manifesto,
relatório e ledger ficam fora dele, em `scripts/design-sync/state/`.

## Provas e estado de publicação

Passaram testes do produtor, consumidor transacional, CLI ponta a ponta, detector modular,
workflow, protocolo e 76/76 casos do gate-selftest. O rollback foi injetado depois de dois swaps
e restaurou bytes e estado. A única vermelhidão é a dívida preexistente do selftest embutido de
`block-sonda-que-mente`, fora deste diff.

A mudança foi publicada na PR #6150 pela branch `codex/design-bundle-transaction`. Estado MCP:
tools de memória indisponíveis; fallback pelo canon git.
