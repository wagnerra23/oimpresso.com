# Como trabalhar — protocolo de sessão

## Caminho preferido: tools MCP (sempre antes de Read filesystem)

| Pergunta | Tool MCP |
|---|---|
| **"Estado consolidado do projeto" (CHAME PRIMEIRO)** | **`brief-fetch`** (skill `brief-first` Tier A always-on) |
| "O que estou fazendo hoje?" | `my-work` (redundante se brief carregou) |
| "Tem algo na minha caixa?" | `my-inbox` |
| "Estado do cycle ativo" | `cycles-active` |
| "Goals do cycle batendo?" | `cycle-goals-track cycle:current` |
| "Backlog do módulo X" | `tasks-list module:X` |
| "Detalhe da task COPI-123" | `tasks-detail task_id:COPI-123` |
| "Tasks novas sem owner/prio" | `triage` |
| "Velocity / burndown" | `dashboard-velocity` / `dashboard-burndown` |
| "Qual ADR fala sobre X?" | `decisions-search query:"X"` (default só ativas) |
| "Ler ADR completa" | `decisions-fetch slug:"0094-constituicao-v2-7-camadas-8-principios"` |
| "Últimas sessões" | `sessions-recent limit:5` |
| "Fato do business sobre Y" | `memoria-search query:"Y"` |
| "Quanto eu consumi?" | `claude-code-usage-self` |

UI humana: `/copiloto/admin/memoria` lista 352+ docs com filtros + preview markdown render + git_sha→GitHub.

## Fallback: filesystem (se sem MCP conectado)

1. **Brief diário fica em** `mcp_briefs` table (consulta SQL como fallback)
2. **ADRs canon:** `memory/decisions/*.md` (ler `_INDEX-LIFECYCLE.md` primeiro)
3. **Sessões:** `memory/sessions/YYYY-MM-DD-*.md`
4. **SPECs por módulo:** `memory/requisitos/<Mod>/SPEC.md`

## Disciplina de contexto

- **`/compact`** após cada feature mergeada/validada — comprime histórico mantendo essencial
- **`/clear`** ao trocar escopo (ex: terminou Jana, vai mexer em Ponto) — começa limpo
- **Plan mode** (Shift+Tab×2) pra mudanças não-triviais
- **`/continuar`** pra retomar sessão sem re-explorar repo do zero (chama `cycles-active` + `my-work` + handoff + último session log)

## Transferir trabalho entre sessões (nuvem ↔ local)

Sessão claude.ai/code (nuvem) e Claude Code local não se enxergam — git é a ponte. Caminho canônico: **bridge branch via GitHub device flow** (sem PAT no chat, sem chunks copiados). Ver [`memory/how-bridge-cloud-local.md`](how-bridge-cloud-local.md).

## Paralelização N agents na mesma worktree

Pattern comprovado em FSM canon (3 waves × 4-5 agents) + Wave A (5 agents) + Wave B (1 agent) 2026-05-12.

**Pré-requisitos pra spawnar N agents simultâneos sem conflito:**

1. **Áreas isoladas obrigatórias** — cada agent recebe lista de pastas permitidas no prompt; sem overlap entre agents. Ex: `Modules/ComunicacaoVisual/` (agent 1) vs `Modules/OficinaAuto/` (agent 2) vs `memory/requisitos/Garantia/` (agent 3).
2. **Zero git ops nos agents** — agents NÃO fazem `git commit/push/branch`. Só `Write/Edit`. Parent coleta no final.
3. **Prompt agent com regra Tier 0 "comparar e não duplicar"** — lista concreta de módulos referência a LER antes de criar (ex: `Modules/<MaisRecente>`, `Modules/<EmProd>`, `Modules/<SharedInfra>`). Comprovado: ComVis V0 agent pulou `cv_orcamentos` (legacy `comvis_orcamentos` existe) + reusou Sprint 1 (module.json/Providers/Charter) — economizou ~6 entregas duplicadas.
4. **Restrições Tier 0 IRREVOGÁVEIS no prompt** — `business_id` global scope, Pest cross-tenant biz=1 vs biz=99, PT-BR, convenções nomenclatura. Sem isso agent inventa.
5. **Pré-reqs ROADMAP da Fase N checados ANTES de disparar** — cada ROADMAP lista pré-reqs Wagner sign-off na entrada de cada fase. Disparar agent sem checar = retrabalho ou decisões assumidas erradas. Conservador: pedir Wagner desbloquear primeiro.

**Consolidação parent (eu) após agents terminarem:**

```bash
# Stash all + branches fresh por domínio:
git stash push -u -m "wave-X-all-agents"
git checkout -B claude/<dominio-1> origin/main
git stash pop  # restaura tudo
git add <subset-dominio-1>  # seletivo
git commit -F - <<'EOF'
...
EOF
git push -u origin claude/<dominio-1>
gh pr create ...

# Próximo domínio:
git checkout -B claude/<dominio-2> origin/main  # untracked files persistem
git add <subset-dominio-2>
git commit + push + PR
```

**Diferente do handoff frustrado** [2026-05-11-1830-paralelizacao-omnichannel-frustrada.md](handoffs/2026-05-11-1830-paralelizacao-omnichannel-frustrada.md) — naquele caso agents tentavam git ops em worktree filha (morriam). Aqui agents só Write/Edit, parent faz git ops.

## Skills auto-ativáveis

Arquivos em `.claude/skills/<nome>/SKILL.md` ativam por contexto. Ver tier no frontmatter (convenção interna [ADR 0095](decisions/0095-skills-tiers-convencao-interna.md)):

- **Tier A** (always-on): brief-first, mcp-first, multi-tenant-patterns, commit-discipline
- **Tier B** (auto-trigger por description): ~9 skills (ads-decision-flow, criar-modulo, migrar-modulo, etc)
- **Tier C** (slash command): cockpit-runbook, oimpresso-stack (one-time), proxmox-docker-host

Lista completa + decisões em [memory/sprints/s3-constituicao/03-skills-audit.md](sprints/s3-constituicao/03-skills-audit.md).

## Ao terminar uma sessão

1. **Registrar via tools MCP** — `tasks-update <ID> status:done` ao fechar; `tasks-comment <ID>` se em progresso; `tasks-create` se for trabalho novo
2. **Handoff append-only** ([ADR 0130](decisions/0130-handoff-append-only-mcp-first.md)):
   - **ANTES** de escrever, rodar checklist MCP-first OBRIGATÓRIO: `cycles-active` + `my-work` + `sessions-recent limit:3` + `decisions-search since:<data-último-handoff>` (+ `whats-active` se suspeita paralela — [ADR 0119](decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
   - Criar **arquivo novo** em `memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md` (NUNCA sobrescrever existente nem editar handoff antigo — append-only)
   - Incluir seção `## Estado MCP no momento do fechamento` com snapshot da consulta (prova, não promessa)
   - Atualizar índice em `memory/08-handoff.md` adicionando 1 linha no topo da lista "Últimos handoffs" (truncar 5º)
3. **Criar session log** em `memory/sessions/YYYY-MM-DD-*.md` descrevendo o que foi feito (sessions/ ≠ handoffs/ — session log conta o trabalho, handoff conta o estado pro próximo)
4. **Se decisão arquitetural nova**, criar ADR em `memory/decisions/NNNN-slug.md`

## SSH Hostinger (flaky — sempre warm-up + retry)

```bash
# 1) Warm-up (5 hits curl IPv4)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

# 2) SSH robusto (auto-mem reference_hostinger_analise.md)
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'CMD'
```

Sem warm-up, primeiro try quase sempre dá `Connection timed out`.

## SSH CT 100 (Tailscale)

```bash
tailscale ssh root@ct100-mcp 'CMD'
```

Primeira sessão pede re-auth via URL (Wagner aprova manualmente). Próximos comandos passam direto.

Detalhes em `memory/requisitos/Infra/RUNBOOK-acesso-ct100.md`.
