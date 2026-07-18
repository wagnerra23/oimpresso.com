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
| "Qual ADR fala sobre X?" | `decisions-search query:"X"` (default só ativas) |
| "Ler ADR completa" | `decisions-fetch slug:"0094-constituicao-v2-7-camadas-8-principios"` |
| "Últimas sessões" | `sessions-recent limit:5` |
| "Fato do business sobre Y" | `memoria-search query:"Y"` |
| "Quanto eu consumi?" | `claude-code-usage-self` |

UI humana: `/copiloto/admin/memoria` lista os docs sincronizados (contagem viva lá) com filtros + preview markdown render + git_sha→GitHub.

## Mapa de arquivos por tela — é COMANDO, não arquivo (derivado, não apodrece)

> "Onde está o mapa de quais arquivos cada tela tem (charter? casos? teste? scorecard?)" — **não é um `.md`**. Um mapa escrito à mão apodrece no dia (lei-mãe [ADR 0256](decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md): *derivado+enforçado sobrevive; escrito+lembrado apodrece*). O mapa é **derivado, recalculado da árvore** por estas portas vivas:

| Camada (por tela) | Porta viva | Gate |
|---|---|---|
| charter · e2e · scorecard · a11y | `npm run screen-coverage:report` (`scripts/qa/screen-coverage-map.mjs`) | `screen-coverage-gate` **required** |
| casos / trio | `npm run casos:report` (`scripts/casos-coverage-guard.mjs`) | `casos-gate` **required** |
| prontuário por módulo (não por tela) | `memory/governance/vital-signs.json` (nightly `mv-metabolismo.yml`) | advisory |
| UI humana por tela | `/admin/screen-review` (charter/ux_targets/status/grade) | — |

Session logs `*-mapa-telas-projeto.md` (e os números embutidos em qualquer session log) são **fósseis datados** — snapshot honesto do dia, **history**, nunca "o mapa atual". Se um número datado incomodar, **re-rode o comando** — não edite o número ([lápide §5 2026-07-17](proibicoes.md)).

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

## Pedido de tela/feature — onde a palavra do dono vira máquina (verificado 2026-07-16)

Canal de entrada REAL do [W] = **chat** (linguagem natural; o agente materializa US/tasks downstream — verificação adversarial 7 agentes, [session 2026-07-16](sessions/2026-07-16-adversario-ponto-entrada-us-uc.md)). **US e UC não são canal de pedido** — SPEC é o elo mais fraco da precedência (e `_pendente_` já conta como coberto no anchor-lint); UC sem teste quebra o casos-gate required (lápide §5 em [proibicoes.md](proibicoes.md)). Os slots onde o pedido vira máquina:

| [W] informa | Onde | Força |
|---|---|---|
| `(Mod/Tela, PT-0X)` | `node scripts/governance/criar-tela.mjs` | carimba trio + stub e2e citando UC; passa `pt-conformance` por construção |
| **Non-Goals + Automation Anti-hooks** | `<Tela>.charter.md` | cada item vira Pest GUARD (CI); `charter-write` é proibida de inferir — só [W] preenche |
| `## Contrato visual` (copy literal + ordem) | charter + `prototipo-ui/contrato/` | gate `contrato-de-tela` (always-run; required = passo 4 pendente, [W] admin-only) |
| `[BACKLOG] <frase>` sem id | `<Tela>.casos.md` | prosa visível sem gate — vira UC quando ganhar teste que o cite |

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

### Agents canônicos do projeto (`.claude/agents/`)

Subagents Opus invocados via Task tool ou linguagem natural. **Experimentais** até ADR mãe aprovada — uso real-world calibra antes de promoção.

| Agent | Função | Quando invocar | Output |
|---|---|---|---|
| **`estado-da-arte`** | Pesquisa os melhores em 2026 + compara com o que oimpresso tem + avalia gaps por impacto×esforço | "Faça o estado da arte de X" / "Como os melhores fazem Y" / "/estado-da-arte X" | `memory/sessions/YYYY-MM-DD-arte-<slug>.md` |
| **`coordenador-paralelo`** | Formaliza pattern de Waves desta seção. Recebe problema → research + inventário → decomposição em N waves isoladas → spawn N sub-agents general-purpose paralelos → consolidação | "Coordene em paralelo X" / "Decomponha em waves" / "Faça em paralelo sem invadir outras áreas" | `memory/sessions/YYYY-MM-DD-coord-<slug>.md` + plano executável + git consolidação |
| **`whatsapp-doctor`** | SRE de plantão do daemon Baileys CT 100. Diagnóstico (daemon + DB Hostinger + logs) + recovery (purge banned, reconnect zombie, force fallback Meta) + auditoria anti-ban (warmup 7d, jitter, rate limit, circadian, contact graph) + post-mortem. Compatível com [runbook canônico](requisitos/Whatsapp/runbooks/_archive/baileys-troubleshoot-ban.md) + [ADR 0096 emenda 4](decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) | "WhatsApp parou" / "device_removed" / "tá banido?" / alarme `whatsapp_baileys_ban_detected_total` ≥ 3 cross-tenant / "vou parear canal novo, faz seguro" | `memory/sessions/YYYY-MM-DD-whatsapp-incident-<slug>.md` + comandos recovery executados |
| **`capterra-senior`** | Auditor SÊNIOR módulo-agnóstico — pesquisa profunda 10-15 concorrentes globais + BR (5-7 WebSearch por dimensão crítica, 25-50 buscas totais modo Opus sustained), compara em 3 eixos features/UX/automação (ADR 0101) com 15-20 capacidades P0-P3, avalia código `Modules/<X>/` + SPEC + ADRs, calcula nota 0-100 ponderada (P0=4, P1=2, P2=1, P3=0.5). Gera CAPTERRA-FICHA.md no formato canônico (10 seções) + session log expandido. Fluxo: `capterra-senior` (FICHA) → `/comparativo` (INVENTARIO + batch tasks) → Wagner aprova | "Capterra do módulo X" / "compare meu módulo Y com os melhores e dá nota" / "estado-da-arte profundo do módulo Z" / "/capterra-senior <Modulo>" / "pesquise os líderes mundiais de {módulo}" | `memory/requisitos/<Modulo>/CAPTERRA-FICHA.md` + `memory/sessions/YYYY-MM-DD-capterra-<modulo>.md` |

Diferenças no espectro:
- `estado-da-arte` entrega CONHECIMENTO (1 doc decisório, domínio qualquer)
- `coordenador-paralelo` entrega EXECUÇÃO (N artefatos/PRs)
- `whatsapp-doctor` entrega OPERAÇÃO (recovery + audit + post-mortem do daemon vivo)
- `capterra-senior` entrega FICHA CANÔNICA de módulo inteiro + nota 0-100 + pesquisa expandida (modo Opus sustained 5-7 WebSearch por dimensão crítica — diferente de `estado-da-arte` que é genérico curto)

Histórico: criados 2026-05-13 sessão crazy-euclid-b68bb7. Dogfood do `estado-da-arte` sobre própria decisão de design pegou 3 P0 fatais (Reflexion paper validado empiricamente N=1). Ver [`memory/sessions/2026-05-13-agents-canonicos-meta-degradacao.md`](sessions/2026-05-13-agents-canonicos-meta-degradacao.md).

## Reconhecer degradação de sessão (Claude)

Sinais observáveis que indicam Claude entrou em modo subótimo (sessão de 2026-05-13 catalogou):

1. **Pulou `brief-fetch` no início** — trabalhou com dados parciais via `my-work`/`tasks-list`. Não chamou Tier A obrigatório.
2. **Inflou design 2+ vezes consecutivas** após Wagner cortar — re-inflar com "versão refinada" é não-ouvir disfarçado de iteração. Anti-pattern.
3. **Gerou 3+ arquivos novos em ≤2h sessão** sem checar duplicação com `Glob`/`Grep` em `memory/`.
4. **Tom inflado falso-confiante** — usar "P0 fatal", "consultor brabo", "auto-derrota" sobre premissas não validadas.
5. **Esqueceu TodoWrite** em tarefas multi-step (≥3 passos).
6. **Skill auto-ativável Tier A não disparou** — sinal que matcher de description está degradado ou skill ausente.

**Ações mitigatórias imediatas (qualquer pessoa pode pedir):**

- `brief-fetch` agora — reseta ground truth (~3k tokens)
- Wagner corta + pede silêncio até próximo turno — Claude para de propor
- `Glob memory/sessions/YYYY-MM-DD-*` antes de criar session log — detecta duplicação
- `/compact` quando >10 turnos acumulados — comprime histórico
- `/clear` se sessão saturou e não dá pra recuperar (nuclear — pós-feature/reunião)

**Quem detecta:** Wagner detectou 3x na sessão 2026-05-13. Claude pode aprender a auto-detectar via heurísticas (TODO futuro: hook que conta arquivos criados/2h ou propostas cortadas).

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
