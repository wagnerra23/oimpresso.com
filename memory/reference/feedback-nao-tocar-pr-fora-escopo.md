---
name: NÃO entrar em PR fora do escopo declarado — ci-monitor-event ≠ ordem
description: Quando ci-monitor-event chega sobre um PR que não é parte do meu escopo declarado (PRs de Wagner ou outra área), agente IGNORA. Tocar em PR alheio gera conflitos + custo tokens + drift de foco
type: feedback
---

Quando um `<ci-monitor-event>` ou notificação CI mostra falhas/comentários em PR que **não pertence ao escopo declarado** da sessão atual (PR de Wagner direto, PR de outro agent, PR de outra Onda/módulo), o agente DEVE ignorar — não investigar, não consertar, não comentar.

**Por que:** Wagner 2026-05-26 (sessão pacote 11 CnabDrivers paralelos PaymentGateway):

> "b não se meta nos outros, por isso que fica dando merda. olhe os ultimos conflitos, e gastos desnecessários de tokens."

Padrão observado nesta sessão:
- ci-monitor chegou sobre PR #1599 (Wagner Fiscal/Cockpit) enquanto eu vigiava 11 PRs PaymentGateway
- Tentação automática: oferecer "consertar os 3 failures"
- Wagner rejeitou explicitamente — gera conflito com trabalho dele em paralelo + drift de foco + desperdício de tokens analisando código alheio

**Conflitos típicos que geram quando agente "ajuda" fora de escopo:**
1. Force-push concorrente quebra HEAD de outro contribuidor
2. Rebase quebra worktrees paralelos
3. Resolver UI Lint baseline em PR alheio pode mascarar regressão real que Wagner queria ver
4. Pest fix sem entender contexto do PR principal vira hot-fix errado
5. Comentar em PR alheio polui code review do dono

**How to apply:**

1. **Identificar escopo declarado** no início da sessão (1 ou N PRs específicos, 1 ou N branches específicas)
2. Quando `<ci-monitor-event>` ou `<task-notification>` chegar, **primeiro check:** é do meu escopo? Sim → agir. Não → ignorar e reportar a Wagner em 1 frase ("PR #X não é do escopo, segue vigiando os meus")
3. **Nunca** rodar `gh pr edit`, `git push --force-with-lease`, ou Edit em arquivos do PR alheio
4. Se Wagner pedir explícito ("conserta o PR #1599 também"), AÍ entra — caso contrário, vigia só o que foi mandado
5. Em paralelo: se eu spawnei N agents, **só** monitoro PRs deles. Outros PRs do repo (mergeados, abertos por humanos, abertos por outro agente irmão sem coordenação minha) são alheios

**Refs:**
- [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §2 — Tiered cost (token não é grátis)
- [publication-policy skill](../../.claude/skills/publication-policy/SKILL.md) — escala vs executa direto
- Sessão de origem: 2026-05-26 pacote 11 CnabDrivers — PR #1599 Fiscal/Cockpit ignorado a pedido Wagner
