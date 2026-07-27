---
date: "2026-07-27"
topic: "Âncora investigada (o pedido morreu na medição) → 13 scripts órfãos ligados → o elo detectar→decidir: 12 de 12 sentinelas criavam ZERO task"
authors: [C, W]
type: session
module: governance
pii: false
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0070-jira-style-task-management-current-md-removed
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Órfãos ligados + o elo detectar→decidir

## TL;DR

Começou como investigação da âncora `**Implementado em:**` e virou outra coisa: **o sistema tem detecção excelente e fechamento zero**. Três PRs — [#4834](https://github.com/wagnerra23/oimpresso.com/pull/4834) (13 scripts órfãos → 2), [#4841](https://github.com/wagnerra23/oimpresso.com/pull/4841) (3 pendências entram no canal HITL), [#4846](https://github.com/wagnerra23/oimpresso.com/pull/4846) (o transporte sentinela→HITL). O pedido original **não virou código** — a medição o matou, e isso está registrado como US-GOV-058.

## 1. O pedido da âncora morreu na medição

Pedido: reconciliar a âncora quando o símbolo é renomeado/movido (inspirado no auto-sync do Swimm). Três premissas caíram:

| premissa | medido |
|---|---|
| "a âncora é `path::simbolo`" | é lista de **paths**; símbolo aparece em **19 de 447** campos (4,3%) e **0 estão mortos** |
| "renome quebra a âncora" | **0 de 469** renames em 180d deixaram âncora apontando path antigo; `anchored_dead=1` e `zombie=0` em 981 US |
| "falta a metade da reconciliação" | o P6 rename-proof foi **cortado conscientemente** em 2026-06-23 (workflow de 14 agentes) — minha medição confirma o corte |

O único `anchored_dead` do corpus é um porte `.ps1→.mjs` que **`git log --follow` não resolve** (git não detecta esse rename). E o mecanismo proposto teria **inventado** um destino errado: o `-M` acusou `Whatsapp/Settings.tsx → Atendimento/JanaTemplates.tsx` num commit de restauração em massa, mas o `Settings.tsx` segue vivo — FP 1/1 na única proposta que ele geraria.

**O eixo que tem tamanho é outro:** 428 âncoras carimbam 25 SHAs, e **15 não são ancestrais do HEAD** (cobrem 280 âncoras = 65%). O `verificado@` grava o sha da *branch* e o squash-merge o descarta. Isso **já estava escrito** na US-GOV-055 em 2026-07-17 (*"a convenção do carimbo está sistematicamente quebrada"*) e nada mudou em 10 dias. Virou **US-GOV-058** — o ato, não a re-medição.

## 2. Os 13 órfãos — e quem deveria ter avisado

[W]: *"quem seria responsável por responder essas perguntas, qual máquina é a dona?"*

**Já existe e já avisava:** `selftest-registry-check --scripts`, nascido em 2026-07-26, rodando em CI. Ele é report-only **por design** (o autor mediu ~67% de precisão do melhor critério automático). O que faltava não era o aviso — era **o ato de julgar a lista**.

Triagem pela taxonomia de [proibicoes.md](../proibicoes.md) §Sempre fazer:

- **Ligados em CI** (medidor, advisory): `feature-lint` · `reguas-indexar` · `doc-auto-relink --detect` · `funcao-scorecard-outcome-probe` · `detect-handoff` (este no `design-memory-gate`, path-scoped onde o sinal nasce).
- **Porta npm, não CI** (CLI sob-demanda por design): `adr-supersede` · `doc-id-stamp` · `funcao-scorecard-humano` · `resolver-reclamacao` · `normalize-adr-frontmatter` (dry-run).
- **Porta npm porque CI seria gate MUDO**: `hook-replay` — lê `~/.claude/projects`, que não existe no runner.
- **Não ligados, esperando [W]**: `charter-promote-signal` (escreve `draft→live`) e `agent-corpus-counterfactual` (ligar ou aposentar) → **US-GOV-056**.

**Efeito medido pela própria porta viva, no runner: 13 → 2.**

O `detect-handoff` já pagou o wiring: bite-test em sync real (`--base f9e116aab7~1`) achou 2 arquivos de `produto-preco-especial` mudados **sem nenhum charter com `visual_source:`** — handoff não-roteável invisível havia 10 dias.

## 3. O elo detectar→decidir (a classe inteira)

Varredura dos sentinelas agendados: **12 de 12 criam ZERO task**. Todos notificam (`McpInboxNotification` `due_soon`) ou logam e param. Evidência viva no `my-inbox`: o `handoff-stale` repetindo o mesmo alerta há **38 dias** (30d→33d→35d→36d→38d).

O canal de decisão **já existia**: a procedure do brief define `HITL pending Wagner` = `mcp_tasks WHERE status='blocked' AND owner='wagner'`. Faltava **transporte**.

`HitlEscalationService` (em `Modules/Jana`, junto do dono de `mcp_tasks`): `task_id` determinístico → re-escalar atualiza a MESMA task. E **o estado humano vence**: `done`/`cancelled` não reabre; `todo`/`doing`/`review` não rebaixa; fail-open se a tabela não existir. 7 testes / 17 assertions no CT 100 com MySQL real.

**Ligado em 1 sentinela, não em 12** — big-bang de legado morre no CI (§5 2026-07-12).

## 4. Erros meus nesta sessão (todos pegos por máquina, nenhum por narrativa)

1. **Teste corruptor.** A 1ª versão do `HitlEscalationServiceTest` fazia `Schema::dropIfExists('mcp_tasks')` + DDL em `activity_log`. O `SDD ratchet GT-G3` acusou `sqlite_corruptors 0→1`; a porta viva detalhou: *score 105 · tier S · corruptsOnMysql · highBlast[activity_log]*. **Rodando no CT 100 contra MySQL persistente, aquele drop apaga a tabela real de tasks.** Fix: mock de facade + `activity()->disableLogging()` → corruptors 0, arquivo em score −15/tier C. Dano no staging reparado (as 3 tabelas não existiam antes — o 1º run falhou com `Table doesn't exist`, prova de que nada real se perdeu).
2. **Duas varreduras próprias antes de achar a porta viva** (deram 5 e 31 órfãos; a verdade era 13). Classe **LC-08**, cometida enquanto eu investigava governança de medição.
3. **`^{commit}` mangled pelo MSYS** deu "25/25 SHAs não existem" — falso; conferido antes de virar conclusão.
4. **Afirmei que o session log da auditoria não existia** — existia; meu clone estava atrás do main. Corrigido no mesmo turno.
5. **Devolvi ao [W] uma escolha que era minha** (consertar a classe vs construir o Zelador) — e uma que ele já tinha decidido há 46 dias (US-GOV-015). Cobrado: *"eu tenho opção? deveria ter escolha aqui?"*.

## 5. O que ficou aberto

- **US-GOV-056/057/058** no canal HITL (`blocked`/`wagner`) — aparecem no brief.
- **`handoff integrity` vermelho no main desde 2026-07-22.** Levantei: 2 dos 3 prompts pousaram no essencial, mas `prototipo-ui/cowork/ds-v6/tokens.css` **segue no git** (deletá-lo era o ponto), e a fila do Cowork está **congelada** — o que elimina uma das 2 saídas do gate.
- **`Governance Drift (ADR 0216)` vermelho no main há 8 dias** — e está **certo**: `secrets:audit` sai 1 por design com drift de secret. Os drifts são o token Hostinger `EXPIRED 2026-05-28` e o Tailscale cross-tailnet — o Tier 0 gap cuja Opção B espera *"só Wagner (setup 1×)"* desde 12/07.
- **Zelador (US-GOV-015)** — charter `ZELADOR.md` existe, executável **não**; `status: todo` há 46 dias. É a máquina desenhada pra fechar exatamente os loops acima, e outros scripts já empurram trabalho pra ela em comentário.

## 6. O que testei e estava OK

`cron-watchdog`: **24/24** crons com heartbeat < limite, 0 artefatos de estado parados. `selftest-registry` (testes): 125/125 registrados. Órfãos fora de `scripts/governance/`: **1** (`scripts/curador/parity-fixtures.mjs`) — mas o detector só vigia 94 de 146 scripts; 52 ficam fora do radar.
