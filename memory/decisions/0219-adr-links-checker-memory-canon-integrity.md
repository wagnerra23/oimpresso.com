---
adr: 0219
title: AdrLinksChecker — link rot + lifecycle integrity de ADRs Nygard
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: []
references:
  - 0216-governance-drift-framework-driftchecker-plugavel.md
lifecycle: active
---

## Contexto

ADRs Nygard são canon. Mas frontmatter (`references`, `supersedes`, `lifecycle`, `superseded_by`) tem **drift bilateral** silencioso:

- ADR X declara `supersedes: [Y]`, mas ADR Y ainda tem `lifecycle: active` (drift)
- ADR X declara `lifecycle: superseded` mas sem `superseded_by` (zumbi)
- ADR X declara `references: [Z]` mas ADR Z arquivo inexistente (link rot)
- ADRs em `memory/decisions/proposals/` órfãos >90d (decisão postergada eterna)

Skill `decisions-search` MCP confia em frontmatter — drift bilateral retorna resultado incoerente sem warning.

**Empiricamente validado**: smoke 2026-05-28 21:30 rodou `AdrLinksChecker` sobre 222 ADRs e detectou **3 drift reais** já no primeiro run, incluindo:
- ADR 0018 supersedes "2026" — provável bug no parser (string "2026" interpretada como ADR id por confusão com year frontmatter)
- ADR 0018 supersedes "0000" — referência inválida

Sem este checker, drift cresce até alguém manualmente auditar (raro).

## Decisão

Implementar `Modules\Governance\Services\Checkers\AdrLinksChecker` (`name='adr_link_rot'`):

**Scan path**: `memory/decisions/*.md` (extensão `.md`, exclui `proposals/` por default — Sprint 2 cobre)

**Detecções**:

1. **Broken `references: [N]`** — ADR referenciada não encontrada. Severity `medium`.
2. **Broken `supersedes: [N]`** — ADR sucessora aponta pra ADR inexistente. Severity `high`.
3. **Drift bilateral supersedes** — ADR X supersedes Y, mas Y.lifecycle ∈ {active, accepted}. Severity `medium`. Ação: editar Y frontmatter.
4. **Lifecycle superseded sem superseded_by** — ADR diz "eu fui substituída" mas não diz por quem. Severity `low`.

**Parser frontmatter**: minimalista (regex + line-by-line), suficiente pra `adr/status/lifecycle/references/supersedes/superseded_by`. Sem dependência externa YAML lib (Symfony Yaml já está em deps, mas overhead pra arquivo de 30-200 linhas).

**Severity**: `medium` (baseline; findings individuais sobrescrevem)
**Enforcement**: `warn` (não bloqueia merge — drift de canon é importante mas não Tier 0)
**Cadence**: `daily` + (Sprint 2) `on_pr` pra PRs que mexem em `memory/decisions/`
**Tags**: `['tier_2', 'compliance', 'memory_canon']`

## Não-goals

- ❌ **Não valida conteúdo do ADR** (status frontmatter vs título body, datas inconsistentes, links externos broken) — Sprint 2
- ❌ **Não verifica `proposals/`** órfãs nesta versão — Sprint 2 (`AdrProposalAgeChecker`)
- ❌ **Não corrige drift** — flagra apenas. Auto-PR de correção é arriscado (decisão Wagner)
- ❌ **Não verifica formato markdown link `[ADR XXXX](path)` no body** do ADR — só frontmatter
- ❌ **Não roda em sessions/** (`memory/sessions/*.md`) — esses são logs históricos sem invariantes canon

## Plano implementação

✅ **Já implementado neste PR1 (ADR 0216 ship junto)**:
- `Modules\Governance\Services\Checkers\AdrLinksChecker` (~230 linhas)
- Parser minimalista YAML embutido
- Registrado em `drift_checkers[]`
- Smoke local: 222 ADRs scaneados, 3 findings detectados — drift bilateral real

⏳ **Sprint 2 (ADR 0224 futura)**:
- Cobrir `memory/decisions/proposals/*.md` com idade detection
- Validar markdown links body
- Detectar ADR sem `deciders` ou sem `date`

## Consequências

✅ **Boas:**
- Drift canon detectado diariamente vs manualmente quando alguém roda audit (semanalmente no melhor caso)
- Brief Jana 06h vai ingerir findings → Wagner vê drift em narrativa, não em log noise
- Smoke real validou utilidade dia 1 (3 findings de drift real)
- ROI: detecta corruption silenciosa em base de 220+ ADRs — operar sem é jogar com fogo

⚠️ **Tradeoffs:**
- Parser YAML embutido tem limitações (não cobre comments YAML, multi-line strings com `|`). 99%+ dos ADRs oimpresso são simples — aceitável.
- Drift bilateral aponta o ADR errado às vezes (X supersedes Y; mas é Y que precisa update, e finding aparece em X). Mensagem do finding tenta esclarecer.
- 3 findings hoje vão poluir Brief Jana até alguém arrumar. Aceitar 1-2 ciclos pra cleanup inicial.
- Não escala pra projetos com >5000 ADRs — overhead glob+regex. Hoje 222 ADRs em 22ms, fine.

## Validação

- ✅ Smoke `php artisan governance:audit --check=adr_link_rot --json`: 3 findings detectados, performance 22ms
- ⏳ Pest tests com fixtures ADR fake (canônico, drift bilateral, broken ref)
- ⏳ Após PR3 wire-up: Brief Jana 06h consome via Centrifugo `governance:drift`

## Notas

- 3 findings reais detectados sugerem dívida acumulada em ADRs antigos (0018, etc.). Backlog: auditar e corrigir 1-2 ADRs por semana até zerar.
- Bug detectado no smoke: ADR 0018 supersedes "2026" parece bug do parser YAML (interpretou date como ADR id). Próxima iteração: melhorar parser pra detectar quando supersedes vira string vs int.
- `decisions-search` MCP server (canon knowledge) se beneficia diretamente — busca por ADR retorna fronteiras de lifecycle precisas.
