---
slug: 0427-auto-merge-de-pr-de-thread-emenda-0040
number: 427
title: "Auto-merge de PR de thread de playbook verde — emenda da 0040"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-05"
module: governance
tags: [governanca, publicacao, merge, playbook, cowork]
supersedes: []
supersedes_partially:
  - 0040-policy-publicacao-claude-supervisiona
related:
  - 0040-policy-publicacao-claude-supervisiona
  - 0104-processo-mwart-canonico-unico-caminho
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
pii: false
---

# ADR 0427 — Auto-merge de PR de thread de playbook verde (emenda da 0040)

## Contexto

A matriz da [ADR 0040](0040-policy-publicacao-claude-supervisiona.md) põe todo merge para
`main` com o [W] ("Sempre escala"). Em 2026-10-05 a sessão que importou o handoff 45 do
Cowork distribuiu as threads de playbook em sessões paralelas. Em meio dia saíram cerca de 20
PRs, e o merge de cada um esperando o [W] virou o maior gargalo do plano de levar o protótipo
inteiro a produção.

O [W], no chat dessa sessão, textual: *"autorizo o merge automático dos PRs de thread verdes"*.

## Decisão

PR que executa uma thread de playbook do Cowork entra por **auto-merge** (`gh pr merge <N>
--auto --squash`) sem pedir ao [W], quando cumpre as quatro condições:

1. executa uma thread de `prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/` e traz o
   recibo `_saida-NN.md` dela;
2. a thread estava `proximo` no `placar.mjs` quando o trabalho começou;
3. tem no máximo 300 linhas, ou o motivo de passar está escrito no corpo, e o teste citado
   executou na lane do CI;
4. não cai em nenhuma exceção.

Quem decide que o PR está verde é a branch protection: o auto-merge só entra quando os checks
obrigatórios passam.

**Exceções, que continuam com o [W]:**

- mexe em valor ou estoque (REGRA MESTRE de `memory/proibicoes.md`);
- migration destrutiva, porque o deploy roda `migrate --force` e mergear é aplicar;
- cutover: apagar Blade que uma rota viva serve, ou ligar flag em produção ([ADR 0104](0104-processo-mwart-canonico-unico-caminho.md), F5 humano);
- baseline visual nova ou regravada ([ADR 0409](0409-zero-baseline-de-tolerancia-conformidade-absoluta.md)).

O resto da matriz da 0040 segue valendo. Esta ADR troca só a linha de merge para `main`, e só
para PR de thread.

## Consequências

- O operacional fica em [`.claude/skills/publication-policy/SKILL.md`](../../.claude/skills/publication-policy/SKILL.md) §Auto-merge de thread, que é onde a sessão lê antes de mergear.
- O [W] deixa de ser o gargalo de merge das threads, mas continua decidindo as exceções e o que
  entra na fila (decisões dos índices, escopo de módulo, cutover).
- Risco: um PR verde de thread entra sem olho humano. Ele é contido pelos checks obrigatórios,
  pelo recibo, que diz o que o PR fez, e pela lista de exceções. Se um merge automático causar
  incidente, a regra volta para o [W] por nova ADR.
- Autorização não se repassa por recado entre sessões: a fonte é esta ADR e a seção da skill.
