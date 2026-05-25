---
slug: 0130-handoff-append-only-mcp-first
number: 130
title: "Handoff append-only + MCP-first antes de escrever — fim do overwrite cego de 08-handoff.md"
type: adr
status: aceito
authority: canonical
lifecycle: canon
decided_by: [W]
decided_at: "2026-05-10"
module: Governance
tags: [governanca, handoff, memoria, mcp, append-only, sessoes-paralelas]
supersedes: []
supersedes_partially: []
amends: [0070]
superseded_by: []
related: ["0027-gestao-memoria-roles-claros", "0040-policy-publicacao-claude-supervisiona", "0061-conhecimento-canonico-git-mcp-zero-automem", "0070-jira-style-task-management-current-md-removed", "0091-daily-brief", "0094-constituicao-v2-7-camadas-8-principios", "0114-prototipo-ui-cowork-loop-formalizado", "0119-migration-factory-capacidade-institucional"]
pii: false
review_triggers:
  - "Mais de 1 incidente de perda de narrativa entre sessões em 30d após implementação → endurecer regra com hook bloqueador (item P2 deste ADR)"
  - "Diretório memory/handoffs/ ultrapassar 100 arquivos → política de arquivamento (mover handoffs > 6 meses pra memory/handoffs/_archive/)"
  - "Brief-fetch passar a expor histórico de handoffs nativamente → reavaliar necessidade do índice em 08-handoff.md"
  - "Tools MCP `handoff-fetch` / `handoff-list` criadas (entram no padrão MCP-first universal) → reduzir 08-handoff.md a stub apontando pras tools"
---

# ADR 0130 — Handoff append-only + MCP-first antes de escrever

## Contexto

`memory/08-handoff.md` foi instituído pela [ADR 0070](0070-jira-style-task-management-current-md-removed.md) como "handoff narrativo entre sessões". A regra implícita até hoje: **arquivo único, sobrescrito a cada sessão**. O cabeçalho do próprio arquivo diz: *"Ele sempre reflete o estado mais recente. É sobrescrito a cada sessão."*

### Sintoma observado (2026-05-10)

Sessões Claude paralelas (worktrees isoladas — solução [ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md) + helper `tools/new-claude-session.ps1`) **não estão isoladas no plano narrativo**. Quem fecha por último ganha. A sessão "noite-2" (cycle higiene + ADR 0129 FSM) sobrescreveu a narrativa da "noite-1" sem reconciliar com:

1. **Tools MCP** com estado vivo (`cycles-active`, `my-work`, `tasks-list`)
2. **Sessões irmãs** rodando em paralelo (visíveis via `whats-active` Tier 1)
3. **ADRs aceitas no mesmo dia** (decisions-search `since:<data>`)

Wagner formulou o problema em 1 frase (2026-05-10):
> "tem que arrumar isso, antes de gerar handoff tem que consultar o mcp, ta sempres salvando sem consultar coisa antiga"

### Por que importa

Handoff é o **único artefato narrativo** que liga sessão N → sessão N+1. Estado vivo está no MCP (cycles, tasks, decisions — [ADR 0070](0070-jira-style-task-management-current-md-removed.md), [ADR 0091](0091-daily-brief.md)), mas o *contexto interpretativo* — "por que pivotei", "o que descobri", "qual lição processual" — só vive no handoff. Perder isso = perder a memória institucional do projeto. Constituição v2 ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)) princípio 7 (transparência) e princípio 4 (loop fechado por métrica) ficam comprometidos.

### Precedentes consultados

- **[ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)** já usa o padrão certo dentro de `prototipo-ui/`: `SYNC_LOG.md append-only` ao lado de `HANDOFF.md sobrescrito`. Ou seja, o padrão dual existe; só não foi propagado pro handoff de projeto.
- **[ADR 0084](0084-triggers-mysql-imutabilidade-mcp-audit-log.md)** estabelece append-only enforce no nível do banco (triggers MySQL). Mesma filosofia, plano diferente.
- **[ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md)** já proíbe auto-mem privada e centraliza conhecimento canônico no git. Esta ADR estende: dentro do git, **conhecimento narrativo é append-only**.

## Decisão

### 1. `memory/handoffs/` é o novo diretório append-only

Cada sessão que produzir narrativa de fechamento cria **arquivo novo** em:

```
memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md
```

Exemplos:
- `2026-05-10-2230-cycle-higiene-pivot-fsm.md`
- `2026-05-10-1430-d1-d4-prefix-helper-paralelo.md`
- `2026-05-11-0900-us-sell-011-fsm-tabelas.md`

**Append-only:** uma vez criado, **NUNCA editado**. Correção de fato vai em handoff novo subsequente (mesma disciplina das ADRs canon — princípio 5 SoC brutal).

### 2. `memory/08-handoff.md` vira **índice puro**

Conteúdo passa a ser ~30 linhas:

```markdown
# 08 — Handoff (índice)

> **Este arquivo é índice, não narrativa.** Cada sessão cria handoff próprio em `memory/handoffs/`.
> Estado VIVO (cycle, tasks, métricas) está nas tools MCP — chame `brief-fetch` primeiro.

## Últimos 5 handoffs

- [2026-05-11 09:00 — US-SELL-011 FSM tabelas](handoffs/2026-05-11-0900-us-sell-011-fsm-tabelas.md)
- [2026-05-10 22:30 — Cycle higiene + Pivot FSM + ADR 0129](handoffs/2026-05-10-2230-cycle-higiene-pivot-fsm.md)
- ...

## Como retomar

1. `brief-fetch` (Tier A always-on)
2. `my-work` (tasks DOING)
3. Ler handoff mais recente acima
4. (se sessão paralela suspeita) `whats-active` ([ADR 0119](decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
```

Índice **pode ser editado** (é o único arquivo de governança que muda no fluxo normal). Mas só pra:
- adicionar entrada nova no topo
- truncar lista pros 5 mais recentes (resto fica em `memory/handoffs/` acessível por glob)

### 3. Regra dura: MCP-first ANTES de escrever handoff

Skill `memory-sync` (Tier B auto-trigger) ganha checklist obrigatório que **PARA** o agente antes de `Write` em `memory/handoffs/`:

1. `cycles-active` — cycle ativo + goals + drift
2. `my-work` — tasks DOING/REVIEW reais
3. `sessions-recent limit:3` — handoffs/sessions irmãs
4. `decisions-search since:<data-último-handoff>` — ADRs aceitas no intervalo
5. (se suspeita paralela) `whats-active` ([ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md))

Resultado da consulta deve aparecer **dentro do handoff** numa seção `## Estado MCP no momento do fechamento` — prova de que a consulta aconteceu, não promessa.

### 4. Atualizar `memory/how-trabalhar.md` §"Ao terminar uma sessão"

Passo 2 muda de:

> Apender em `memory/08-handoff.md` com novo estado narrativo

Pra:

> Criar `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md` (append-only — NUNCA sobrescrever existente). Atualizar índice em `memory/08-handoff.md` com link no topo. **Antes** do Write: chamar tools MCP conforme checklist em SKILL.md memory-sync.

### 5. Migração do handoff atual

Mover conteúdo atual de `memory/08-handoff.md` (sessão noite-2 2026-05-10 cycle higiene + FSM) para `memory/handoffs/2026-05-10-2230-cycle-higiene-pivot-fsm.md` preservando integralmente. Substituir `08-handoff.md` pelo novo template-índice.

### 6. Hook bloqueador (P2 — dormente, ativar se houver reincidência)

`.claude/hooks/block-handoff-overwrite.ps1` que bloqueia `Edit` em `memory/08-handoff.md` se diff toca qualquer linha que não seja:
- adição no topo da lista "Últimos 5 handoffs"
- truncamento da última linha

E bloqueia `Write` direto em `memory/08-handoff.md` (forçando uso de Edit).

**Critério de ativação:** ≥1 incidente de overwrite acidental nos 30d seguintes. Antes disso, regra é cultural + skill — não enforce hard.

## Não-decidido (fora de escopo)

- **Tool MCP `handoff-fetch <slug>`** + `handoff-list` — útil mas não bloqueante. Vira US separada quando MCP server estiver pronto pra mais um endpoint (próximo cycle).
- **Auto-arquivamento** de handoffs >6 meses — só em review_trigger se diretório > 100 arquivos.
- **Sincronização cross-dev em tempo real** (Felipe e Wagner escrevendo handoff ao mesmo tempo) — `whats-active` ([ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md)) já cobre. Append-only com timestamp HHMM no nome previne colisão de arquivo.

## Consequências

### Positivas

- **Memória institucional preservada** — histórico de handoffs vira evidência auditável (princípio 7 Constituição v2)
- **Sessões paralelas seguras no plano narrativo** — cada uma cria arquivo próprio, zero conflito de overwrite
- **MCP-first vira hábito enforçado** — não dá pra escrever handoff sem antes ter snapshot do estado vivo
- **Análise post-mortem fica trivial** — `ls memory/handoffs/2026-05-*` lista tudo do mês
- **Convergência com [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)** — padrão dual SYNC_LOG/HANDOFF já validado em prototipo-ui sobe pra nível de projeto

### Negativas

- **Diretório `memory/handoffs/` cresce** — mitigado por review_trigger 100 arquivos
- **Índice em `08-handoff.md` exige manutenção manual** — aceitável (1 linha por sessão de fechamento); auto-update por hook é P3
- **Glob em `memory/handoffs/**` ficará mais frequente** — desprezível no Claude Code; MCP server já indexa via webhook GitHub
- **Sessões que não rodam tools MCP (offline)** — checklist degrada pra "documentar no handoff que rodou offline + qual fonte usou". Ainda melhor que silêncio.

### Neutras

- **Amends [ADR 0070](0070-jira-style-task-management-current-md-removed.md)** sem supersedir — 0070 continua válida (tasks no MCP, sessions append-only); só o handoff narrativo muda de modelo.

## Plano de implementação (1 PR — ≤200 linhas)

1. ADR 0130 (este arquivo) — registro da decisão
2. Mover `memory/08-handoff.md` (conteúdo atual) → `memory/handoffs/2026-05-10-2230-cycle-higiene-pivot-fsm.md`
3. Reescrever `memory/08-handoff.md` como índice (~30 linhas, template acima)
4. Atualizar `.claude/skills/memory-sync/SKILL.md` com checklist MCP-first obrigatório
5. Atualizar `memory/how-trabalhar.md` §"Ao terminar uma sessão" passo 2

Hook P2 fica como follow-up dormente.

## Referências

- [ADR 0027](0027-gestao-memoria-roles-claros.md) Gestão de memória — papéis claros (handoff em 3 lugares era problema; agora 1 lugar com regras claras)
- [ADR 0040](0040-policy-publicacao-claude-supervisiona.md) Policy publicação — "atualizar handoff" continua Claude-pode-direto, só muda o COMO
- [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) Zero auto-mem privada — esta ADR estende o princípio (append-only no plano narrativo dentro do git)
- [ADR 0070](0070-jira-style-task-management-current-md-removed.md) Jira-style tasks — base que esta ADR emenda (sem supersedir)
- [ADR 0091](0091-daily-brief.md) Daily Brief — fonte do "estado vivo" que handoff deve consultar antes de escrever
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 — princípio 7 transparência + princípio 4 loop fechado fundamentam append-only narrativo
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) Cowork loop formalizado — precedente do padrão dual SYNC_LOG (append-only) / HANDOFF (sobrescrito)
- [ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md) Paralelismo sessões — `whats-active` é o pré-check antes de escrever handoff quando suspeita irmã ativa
