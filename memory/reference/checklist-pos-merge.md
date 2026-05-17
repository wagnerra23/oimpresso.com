# Checklist pós-merge canônico

> **Por que existe:** 8 passos pós-merge estavam dispersos em ADR 0070, ADR 0130, ADR 0164, skill `tela-smoke-pos-merge`, skill `brief-update`, skill `memory-sync` e workflow GHA. Este doc consolida em 1 página com fontes cruzadas + estado real (auditado 2026-05-17 — workflow GHA com 5/5 runs success). Wagner pediu doc unificado pra time MCP (Felipe/Maiara/Eliana/Luiz) não precisar caçar.

## Quando aplicar

Após **qualquer PR mergeado em `main`** que toque código produtivo. PRs puramente docs/chore/test pulam itens 2, 4, 5 (ver exceções inline).

## Os 8 passos

### 1. CI verde + deploy

Aguardar GitHub Actions workflow `Deploy` concluir success. Se falhar:
- **Rollback via PR revert**, nunca force-push em `main` (proibido — `memory/proibicoes.md` §Código)
- Hostinger prod biz=1 — quick-sync SSH é fallback ([reference/deploy-recovery-patterns.md](deploy-recovery-patterns.md) §2.1 "tela não aparece pós-merge")

### 2. Smoke pós-merge (semi-automático)

**SE** o PR tocou `resources/js/Pages/**/*.tsx`:

Workflow [.github/workflows/screen-smoke-after-merge.yml](../../.github/workflows/screen-smoke-after-merge.yml) detecta automaticamente, comenta no PR e cria notice. **Mas o smoke real (browser MCP screenshot + console errors + perf metrics) ainda exige ação:**

| Caminho | Quem | Quando |
|---|---|---|
| `/admin/screen-review` UI (Tailscale) | Wagner manual | Imediato pós-merge se quiser ver |
| Cron daily 09:00 BRT | Sistema | Batch das pendentes |
| `gh workflow run screen-smoke-after-merge.yml -f screen_path=<Mod>/<Tela>` | Qualquer dev | Force re-smoke |

**Skill `tela-smoke-pos-merge` (Tier B)** executa o smoke real quando Wagner pede explícito. Pré-flight obrigatório antes:
- Ler `<Tela>.charter.md` (UX targets/latência alvo)
- Ler `<Tela>.review.md` anterior (baseline)
- Ler `<Modulo>/UI-CATALOG.md` (contexto)
- Verificar Vaultwarden item `screen-smoke/wagner-prod-readonly` existe

Output: `<Tela>.review.md` round N + `<Tela>.smoke-log.md` + `<Modulo>/UI-CATALOG.md` + entry `mcp_alertas` notificando Wagner.

Wagner decide no round N: `decisão: approved | rejected | iterate`. Só `approved` marca `<Tela>.charter.md` `status: live`.

**Tier 0:** screenshots passam por `PiiRedactor` (CPF/CNPJ/email/fone) ANTES de salvar — ADR 0093. Usa biz=99 fake por padrão, não biz=4 ROTA LIVRE — [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md).

Detalhes: [`.claude/skills/tela-smoke-pos-merge/SKILL.md`](../../.claude/skills/tela-smoke-pos-merge/SKILL.md) + [ADR 0164](../decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) + [memory/requisitos/Admin/SCREEN-REVIEW-RUNBOOK.md](../requisitos/Admin/SCREEN-REVIEW-RUNBOOK.md).

### 3. `tasks-update <ID> status:done`

Para cada US `Refs: <ID>` mencionada no commit/PR:

```
tasks-update task_id:US-XXX-NNN status:done
```

Via tool MCP — **nunca markdown**. CURRENT.md/TASKS.md foram removidos em 2026-05-04 ([ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)).

Webhook GitHub também auto-detecta `(?i)(refs|fixes|closes|resolves)\s+<KEY>-\d+` em commits/branches e atualiza `mcp_tasks` automaticamente, **mas** confirma via `my-work` no fim — falha silenciosa é possível.

### 4. BRIEFING.md atualizado (skill `brief-update` Tier B)

**SE** o PR alterou capacidade/UX/score Capterra/gap/diferencial do módulo:

Skill `brief-update` auto-ativa e atualiza `memory/requisitos/<Modulo>/BRIEFING.md` (1 página executiva, ≤150 linhas). Lê COMPARATIVO + CAPTERRA-INVENTARIO + AUDIT-LOG + SPEC + últimos handoffs e regenera.

**Skip legítimo:** PR `chore(...)`/`test(...)`/`refactor(...)` pequeno sem impacto user-visible, hotfix <30min, docs-only.

**Preferido:** commit `+ briefing update` no MESMO PR (não criar PR separado). Se PR já mergeou, abre PR pequeno `docs(<modulo>): atualiza BRIEFING pós-#NNN`.

**Hoje:** 34 BRIEFINGs canônicos existem (1 por módulo) — [`memory/requisitos/*/BRIEFING.md`](../requisitos/). Template em [`memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md`](../requisitos/_DesignSystem/BRIEFING-TEMPLATE.md).

Detalhes: [`.claude/skills/brief-update/SKILL.md`](../../.claude/skills/brief-update/SKILL.md).

### 5. Handoff append-only (apenas em fechamento de chapter)

**Nem todo merge exige handoff novo.** Handoff é pra fechamento de **chapter narrativo** — mega-feature, marco, pivot, sessão grande (5+ PRs). Merge de PR isolado normalmente NÃO gera handoff.

**SE** for fechamento de chapter ([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)):

#### 5a. MCP-first OBRIGATÓRIO antes do Write

Ordem exata — capturar resultado pra incluir no handoff:

1. `cycles-active` — cycle + goals + drift detectado
2. `my-work` — tasks DOING/REVIEW reais
3. `sessions-recent limit:3` — handoffs irmãs nas últimas horas
4. `decisions-search since:<data-último-handoff>` — ADRs aceitas no intervalo
5. (suspeita paralela) `whats-active` — [ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)

#### 5b. Criar arquivo novo (append-only — nunca editar depois)

```
memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md
```

**Deve ter** seção `## Estado MCP no momento do fechamento` com snapshot dos 4 outputs MCP — **prova, não promessa**.

#### 5c. Atualizar índice `memory/08-handoff.md`

Adicionar **1 linha no topo** da lista "Últimos handoffs" apontando pro arquivo novo.

⚠️ **Drift detectado 2026-05-17:** ADR 0130 §2 diz "truncar 5 mais recentes", mas o índice atual tem ~35 entradas. Convenção evoluiu de facto pra histórico mais longo no índice. Wagner decide se formaliza emenda ou trunca.

### 6. Session log (`memory/sessions/`)

Criar `memory/sessions/YYYY-MM-DD-<slug>.md` descrevendo o **trabalho cronológico** (o que foi feito, decisões tomadas, comandos executados).

**Diferença vs handoff:** session log conta o trabalho; handoff conta o estado interpretativo pro próximo Claude. Ambos podem coexistir num mesmo dia.

Glob duplicação antes de criar: `memory/sessions/YYYY-MM-DD-*.md` — se já existir similar do dia, EDITA em vez de criar novo ([proibicoes.md §Memória/governança](../proibicoes.md)).

### 7. ADR nova (se decisão arquitetural)

Se o PR introduziu padrão novo, tecnologia nova, ou mudou convenção canônica:

Criar `memory/decisions/NNNN-<slug>.md` no formato Nygard (status/contexto/decisão/consequências). Append-only — **nunca editar accepted**. Mudança = nova ADR com `supersedes: [N]`.

CI `governance-gate.yml` Job 1 bloqueia merge de PR que edite ADR canon com status `M`/`R*`. CONSTITUTION editada exige label `constitution-amendment` + `audit-*.md` no mesmo PR (§10.4 Cascade Review).

Lifecycle: [memory/decisions/_INDEX-LIFECYCLE.md](../decisions/_INDEX-LIFECYCLE.md).

### 8. `git push` memory/ (skill `memory-sync` Tier B)

Webhook GitHub→MCP só sincroniza após `git push`. Time (Felipe/Maiara/Eliana/Luiz) só enxerga via tools MCP (`decisions-search`, `memoria-search`) depois disso.

Skill `memory-sync` automatiza via comando `/sync-mem`:
1. Detecta pendências em `memory/`, `MEMORY.md`, `TEAM.md`, `CLAUDE.md`, `DESIGN.md`, `INFRA.md`
2. Agrupa em commit semântico
3. Push → webhook MCP em <60s

**Não usar:**
- `git add -A` ou `git add .` — pode pegar `.env`/credenciais
- `--no-verify` — gitleaks/pre-commit existem por razão
- Commitar código (`Modules/`, `app/`, `resources/`) junto — esses seguem fluxo PR normal

Detalhes: [`.claude/skills/memory-sync/SKILL.md`](../../.claude/skills/memory-sync/SKILL.md).

## Tabela de gatilhos por tipo de PR

| Tipo PR | Passos obrigatórios | Passos puláveis |
|---|---|---|
| `feat(<mod>)` toca Modules + Pages | 1-8 todos | — |
| `fix(<mod>)` bug crítico | 1, 2, 3, 6, 8 | 4 (skip se não muda capacidade), 5 (skip se merge isolado), 7 (skip se não tem ADR) |
| `refactor(<mod>)` sem impacto user-visible | 1, 3, 6, 8 | 2, 4, 5, 7 |
| `chore(<mod>)` deps/CI/lint | 1, 8 | 2, 3, 4, 5, 6, 7 |
| `test(<mod>)` test-only | 1, 8 | 2, 3, 4, 5, 6, 7 |
| `docs(<mod>)` docs canon | 1, 6, 8 | 2, 3, 4, 5, 7 |
| `hotfix(<mod>)` <30min emergency | 1, 3, 8 | 2 (smoke depois), 4-7 |

## Cycle drift — passo zero implícito

Se brief diário mostrar **0% PRs alinhados com cycle ativo** (cycle drift detectado — aprendizado retro CYCLE-01), avaliar `cycles-close --rollover` + `cycles-create` novo. Não é bloqueador, é sinal de pivot estratégico não-registrado.

Hoje (2026-05-17): brief mostra CYCLE-06 com drift 38/38 PRs 0% alinhados. Wagner consciente — pivot operacional Martinho + FSM + Jana V2 em curso.

## Estado real auditado 2026-05-17

| Peça | Status |
|---|---|
| Workflow `screen-smoke-after-merge.yml` | ✅ 5/5 runs success últimas horas |
| Skill `tela-smoke-pos-merge` | ✅ ativa Tier B + 100+ `.review.md` criados |
| Skill `brief-update` | ✅ ativa Tier B + 34 BRIEFINGs canônicos |
| Skill `memory-sync` | ✅ ativa Tier B + checklist MCP-first em SKILL.md |
| ADR 0070 / 0130 / 0164 | ✅ accepted canon |
| Índice `08-handoff.md` truncamento | ⚠️ drift de facto (35 entradas vs ADR 0130 "truncar 5") — Wagner decide |
| Doc canônico unificado checklist | ✅ este arquivo (criado 2026-05-17) |

## Anti-patterns proibidos

- ⛔ **Editar handoff antigo** — append-only ADR 0130
- ⛔ **Pular `brief-fetch` no início de sessão** — Tier A bloqueador
- ⛔ **Criar session log sem `Glob`/`Grep` antes** — duplicação proibida
- ⛔ **Editar ADR canon com status `accepted`** — append-only, criar nova com `supersedes`
- ⛔ **`tasks-update` em markdown** — CURRENT.md/TASKS.md REMOVIDOS (ADR 0070)
- ⛔ **Screenshot prod sem auto-mask PII** — viola Tier 0 ADR 0093
- ⛔ **Smoke biz=4 ROTA LIVRE sem justificativa charter** — usar biz=99 fake (ADR 0101)

## Refs canônicas

- [ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style tasks (passos 3, 7)
- [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) — Handoff append-only + MCP-first (passo 5)
- [ADR 0164](../decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) — Screen Review PDCA (passo 2)
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (PII redactor passo 2)
- [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) — biz=99 não biz=4 em smoke
- [memory/how-trabalhar.md §"Ao terminar uma sessão"](../how-trabalhar.md) — protocolo geral
- [memory/proibicoes.md](../proibicoes.md) — Tier 0 IRREVOGÁVEIS

**Atualizado:** 2026-05-17 — auditoria pós-merge cross-fontes consolidada num doc canônico unificado (pedido Wagner sessão `sharp-shannon-c7ae87`).
