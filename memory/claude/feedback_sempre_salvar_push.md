---
name: Sempre push + PR sem perguntar
description: Após commit no projeto oimpresso, sempre fazer push e abrir PR automaticamente sem pedir confirmação
type: feedback
originSessionId: 2c84ea6a-e3ab-4f01-8b84-8f14c4c544b2
---
Após qualquer `git commit` no projeto oimpresso, **sempre** executar em sequência sem perguntar:
1. `git push`
2. `gh pr create` (se não existir PR aberto para a branch)

**Why:** Wagner delegou supervisão (ADR 0040). Push + PR é ação rotineira reversível. Perguntar "faço agora?" após o commit é fricção desnecessária e inconsistente com a delegação já concedida.

**How to apply:** commit → push → PR, tudo na mesma resposta. Só escalar se a ação for irreversível e alto impacto (ex.: force push em main, drop de tabela em produção).
