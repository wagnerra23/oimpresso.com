---
id: reference-feedback-nao-tocar-pr-fora-escopo
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

---

## Reincidência 2026-06-04 — violei esta regra DE NOVO (mesma classe)

> Wagner: *"Porque começou a pegar coisa que não era era sua?"* → *"Aprenda para não fazer novamente"*.

**Escopo declarado da sessão:** corrigir 1 bug (`makeChannel` redeclare na suíte Pest). Só isso.

**Como derrapei (cadeia):**
1. Fiz o fix (#2251) — ✅ no escopo.
2. Ao validar, achei `main` quebrada pelo #2256 (trait `RendersMockCowork` do Financeiro). **Avisar foi certo.**
3. **Erro:** em vez de avisar e devolver pro dono, **peguei pra consertar** (#2260) → duplicou o #2261 que a sessão dona já estava fazendo.
4. **Erro:** tratei `ci-monitor-event` de PR alheio (#2262, draft de outra sessão) como tarefa minha.
5. **Erro:** tentei regen de baseline PHPStan (drift causado pelas mudanças de model da sessão do Financeiro) — nem reproduzível do meu ambiente.

**Duas nuances NOVAS (que faltavam nesta regra):**

- **"Main quebrada / risco de produção" NÃO é licença pra cruzar a raia.** A urgência justifica **avisar alto e claro** ("main quebrada, dono = sessão X"), não **consertar** trabalho de outra sessão. Flag-and-hand-off, não fix.
- **Pedir "quer que eu assuma?" já é derrapagem.** Quando o trabalho é de outra raia, o default certo é **recomendar o hand-off** ("isso é da sessão Y, melhor eles fazerem"), não oferecer-me pra pegar. Mesmo com Wagner dizendo "sim", o resultado foi colisão + trabalho duplicado + tokens desperdiçados. A autorização não torna a ação correta.

**Sinal de alerta pra mim mesmo:** se eu me pego dizendo *"achei outro problema, quer que eu conserte?"* sobre algo fora do escopo declarado → PARA. Reformula pra *"achei X, dono é Y, segue assim?"* e volto pro meu.

**Agravante:** esta regra JÁ existia (origem 2026-05-26) e eu repeti mesmo assim. O fix não é mais um doc — é eu **checar o escopo declarado a cada ci-monitor/notification ANTES de agir**, como o passo "How to apply" já manda.
