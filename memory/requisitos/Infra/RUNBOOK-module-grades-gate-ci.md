---
slug: runbook-module-grades-gate-ci
title: "RUNBOOK — Module Grades Gate CI (anti-regressão module-grade v3)"
type: runbook
authority: canonical
lifecycle: ativo
owner: W
last_updated: 2026-05-16
last_validated: "2026-05-16"
related_workflow: .github/workflows/module-grades-gate.yml
related_baseline: governance/module-grades-baseline.json
related_service: Modules/Governance/Services/ModuleGradeService.php
related_command: Modules/Governance/Console/Commands/ModuleGradeCommand.php
related_skill: .claude/skills/module-grades-gate/SKILL.md
related_adrs:
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0156-module-grade-v3-errata-otel-helper-na-justified
  - 0154-module-grade-v2-na-justificado
  - 0153-module-grade-rubrica-v1
  - 0094-constituicao-v2-7-camadas-8-principios
charter_adr: 0155
pii: false
---

# RUNBOOK — Module Grades Gate CI

Workflow [`.github/workflows/module-grades-gate.yml`](../../../.github/workflows/module-grades-gate.yml) é o **gate anti-regressão da rubrica `module-grade-v3`** ([ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md)). Bloqueia merge em DOIS cenários (Wave 2 endurecimento 2026-05-16):

1. **Regressão** — nota de QUALQUER módulo diminuiu vs `governance/module-grades-baseline.json`
2. **Módulo novo sem aprovação** — entry presente no current ausente na baseline e sem label de aprovação Wagner

Complementar (não substitui) ao [`RUNBOOK-governance-gate-ci.md`](RUNBOOK-governance-gate-ci.md) — aquele cobre **outro workflow** (`governance-gate.yml`: ADR/handoff append-only, PII scan, cascade review). Este aqui é exclusivo do **`module-grades-gate.yml`** (rubrica notas v3).

Para guia rápido orientado a tarefa (rodar local, override por label, reconciliar rename), ver a skill complementar [`module-grades-gate`](../../../.claude/skills/module-grades-gate/SKILL.md). Este RUNBOOK é o canon formal/exaustivo.

## §1. Missão

Garantir **cultura "subir nota, nunca baixar"** + **entrada rastreável de módulo novo** na rubrica. Sem este gate, regressões silenciosas e módulos críticos (Admin/Connector/Arquivos/Essentials) entram sem nota mínima — gap P1 catalogado na auditoria 2026-05-16.

Time MCP entra em breve. Hook local pode ser pulado (`--no-verify`); CI é a **fonte de verdade**.

## §2. Quando dispara

Workflow é triggado em pull_request com `types: [opened, synchronize, reopened, labeled, unlabeled]` ([`.github/workflows/module-grades-gate.yml`](../../../.github/workflows/module-grades-gate.yml) linha 14-15). Triggers `labeled`/`unlabeled` permitem re-rodar o gate **sem novo push** após aplicar/remover label de override.

`timeout-minutes: 10` (linha 24) — composer install + `php artisan module:grade --all` em ~3-4min típico.

## §3. Comportamento — 4 status detectáveis

A step "Comparar com baseline" classifica cada módulo via delta `current - baseline` e renderiza tabela markdown no comentário do PR.

| Emoji | Status | Significado | Bloqueia merge? | Override |
|---|---|---|---|---|
| 🔻 | `down` | Nota diminuiu (delta negativo) | **Sim** | Label `module-grades-allowed-regression` |
| ✨ | `new` | Módulo presente no current, ausente na baseline | **Sim (Wave 2)** | Label `module-grades-new-module-allowed` |
| ⚠️ | `removed` | Módulo presente na baseline, ausente no current | Não (apenas reporta) | — (tratar via reconciliação rename §7) |
| 🟢 | `up` | Nota aumentou (delta positivo) | Não | — (atualizar baseline pós-merge — §6) |
| ⚪ | `eq` | Nota igual (delta zero) | Não | — |

Combinações Wave 2 (workflow comenta título específico — ver `actions/github-script@v7` step linhas 219-289):

- "❌ regressão + módulo novo sem aprovação" — resolver ambos
- "⚠️ override ativo + módulo novo sem aprovação" — aplicar segunda label
- "✨ módulo(s) novo(s) aprovado(s)" — lembrar atualizar baseline pós-merge
- "✅ all clear" — merge livre

## §4. Override responsável (2 labels)

### Label `module-grades-allowed-regression` (regressão)

Permite merge mesmo com nota baixando. **Exige justificativa em comentário/ADR no mesmo PR.**

**Quando aplicar:**

- ✅ Refactor estrutural justificado (ex: remove código deprecated quebra cobertura Pest temporariamente)
- ✅ Legacy cleanup descobre tests fantasma que viviam inflando D2 sem rodar de verdade
- ✅ Tradeoff perf vs segurança documentado em ADR (ex: rate-limit mais agressivo quebra throughput dimension D6 mas sobe D8)
- ✅ Migração de framework altera dimensão D4 arquitetura por design

**Quando NÃO aplicar:**

- ❌ "Esconder bug que baixou D1 multi-tenant" — risco Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- ❌ "Pressa pra merge sem fixar a regressão" — atualizar baseline conscientemente (§6) é melhor (deixa trilha audit)
- ❌ "Não tenho tempo" — nunca é justificativa válida pra Tier 0

### Label `module-grades-new-module-allowed` (módulo novo — Wave 2, gap P1 fechado)

Aprova entrada de módulo novo na rubrica. **Sem ela, módulo novo bloqueia merge.**

**Quando aplicar:**

- ✅ Módulo novo intencional (criado via skill `criar-modulo`, scaffold completo `module.json` + Providers + SPEC.md)
- ✅ Módulo crítico retroativo (Admin/Connector/Arquivos/Essentials passando silencioso na v3 inicial)
- ✅ Reconciliação rename `Antigo→Novo` no mesmo PR (key antigo removido + key novo adicionado simultaneamente — ver §7)

**Quando NÃO aplicar:**

- ❌ Entrada acidental (scan pegou dir espúrio, ex.: `Modules/vendor/` ou `Modules/legacy_backup/` — não são módulos reais) — **remover/renomear** em vez de aplicar
- ❌ Módulo embrião sem code real (pasta vazia, só `module.json` esqueleto) — esperar MVP antes de entrar na rubrica
- ❌ Tentativa de burlar gate adicionando "shadow module" pra fazer regressão de outro virar `new`

Após aplicar QUALQUER label, escreva **1 comentário no PR** com a justificativa. Vira evidência audit pro time MCP que entra em breve.

## §5. Operar local antes de push

Ver o que CI vai ver, antes de gastar minuto de runner GitHub:

```bash
# Output JSON cru (mesmo formato que step "Gerar grades atuais do PR head" gera)
php artisan module:grade --all --json > /tmp/grades-current.json

# Tabela colorida ranqueada (visual humano)
php artisan module:grade --all

# Drill-down 1 módulo com breakdown 9 dimensões + sub-checks
php artisan module:grade Repair --detail

# Shortcut composer (alias pra `php artisan module:grade --all --json`)
composer module-grades-check
```

> ⚠️ Use `--detail` **NUNCA** `--verbose` (Symfony reserved — colide com flag default `-v`/`-vv`/`-vvv`). Detalhe em [`.claude/rules/commands.md`](../../../.claude/rules/commands.md).

### Diff local vs baseline (espelha o que CI faz)

```bash
# 1. JSON current
php artisan module:grade --all --json > /tmp/current.json

# 2. Comparar via PHP inline (mesma lógica step "Comparar com baseline")
php -r '
  $baseline = json_decode(file_get_contents("governance/module-grades-baseline.json"), true);
  $current = json_decode(file_get_contents("/tmp/current.json"), true);
  $currentMap = [];
  foreach ($current as $g) { $currentMap[$g["module"]] = (int) $g["score"]; }
  foreach ($baseline["modules"] as $name => $base) {
    $now = $currentMap[$name] ?? null;
    if ($now === null) { echo "⚠️ removed: $name (base $base)\n"; continue; }
    $delta = $now - $base;
    if ($delta < 0) echo "🔻 down: $name $base→$now ($delta)\n";
    elseif ($delta > 0) echo "🟢 up: $name $base→$now (+$delta)\n";
  }
  foreach ($currentMap as $name => $now) {
    if (!array_key_exists($name, $baseline["modules"])) echo "✨ new: $name (now $now)\n";
  }
'
```

## §6. Atualizar baseline conscientemente

**Princípio:** baseline é snapshot autorizado das notas em prod. Atualização é decisão consciente, NÃO automática.

**Quando atualizar:**

- ✅ Pós-merge feature que sobe nota dum módulo → fixar novo patamar (anti-regressão "sobe junto")
- ✅ Pós-merge módulo novo aprovado → entry definitiva substituindo nota inicial
- ✅ Pós-merge rename drift fix → key trocada (ver §7)
- ❌ **NUNCA** atualizar baseline pra "passar gate" sem subir nota real — gaming detectável via diff PR (Wagner vê drop suspeito)

**Disciplina canônica:** **1 PR = 1 intent** (skill `commit-discipline` Tier A). PR de baseline update é dedicado, separado da feature que justificou:

```
1. PR feature: merge primeiro (gate passa com label override OU sem regressão)
2. PR baseline-update: dedicado, contém SÓ governance/module-grades-baseline.json
   Título canônico: chore(governance): atualizar baseline module-grades pós-merge <slug-feature>
   Wagner aprova explicitamente
```

**Comando regenerar baseline:**

```bash
# Rodar contra main (ou branch que reflete prod)
git checkout main
composer install --no-interaction

# Gerar JSON cru
php artisan module:grade --all --json > /tmp/new-baseline-raw.json

# Converter pro formato baseline.json (chave modules: nome → score)
php -r '
  $data = json_decode(file_get_contents("/tmp/new-baseline-raw.json"), true);
  $modules = [];
  foreach ($data as $g) { $modules[$g["module"]] = (int) $g["score"]; }
  ksort($modules);
  $baseline = [
    "generated_at" => gmdate("c"),
    "baseline_version" => "v3.1",
    "rubric_adr" => "0155",
    "notes" => "Snapshot regenerado pós-merge <feature>.",
    "modules" => $modules,
  ];
  file_put_contents("governance/module-grades-baseline.json", json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
'

# Commit dedicado + PR
git checkout -b chore/baseline-update-<slug>
git add governance/module-grades-baseline.json
git commit -m "chore(governance): atualizar baseline module-grades pós-merge <slug>"
git push -u origin chore/baseline-update-<slug>
gh pr create --title "chore(governance): atualizar baseline module-grades pós-merge <slug>" \
             --body "Regenerado contra main pós #<pr-feature>. Sem regressão esperada."
```

## §7. Reconciliar drift rename/split

Caso real catalogado v3.1 (2026-05-16): `PontoWr2` → `Ponto` (dir renomeado fisicamente), `Project` → `ProjectMgmt` (promovido módulo próprio ADR 0070). Renames quebram baseline porque key antigo some do `current`.

**Procedimento canônico drift fix (single PR — preferido):**

```bash
# 1. Renomear dir físico
git mv Modules/<Antigo> Modules/<Novo>

# 2. Atualizar module.json campo "name"
# Editar Modules/<Novo>/module.json: "name": "<Novo>"

# 3. Atualizar baseline JSON
# Editar governance/module-grades-baseline.json:
#   - Remover key "<Antigo>"
#   - Adicionar key "<Novo>" com MESMA nota (rename ≠ regressão)
#   - Adicionar entry em "renames" map com reason + renamed_at

# 4. Confirmar Service encontra o novo path
php artisan module:grade <Novo> --detail

# 5. (opcional) ADR de drift fix se decisão arquitetural justifica
# Ex: memory/decisions/NNNN-rename-pontowr2-ponto.md
```

**2 avisos críticos:**

1. **Rename ≠ regressão.** Gate aceita se `score_novo == score_antigo` sob key novo. Se nota baixar no rename (ex: SPEC quebrada pelo path novo), tratar como regressão normal (corrigir OU `module-grades-allowed-regression`).

2. **Rename + mesmo PR.** Se você fizer rename split em 2 PRs (remove antigo PR1, adiciona novo PR2), PR2 dispara `new` e exige label `module-grades-new-module-allowed`. Preferência: rename + baseline update no MESMO PR — gate vê substituição limpa, sem `new` nem `removed`.

## §8. Troubleshooting

### "Build falha: regrediu" — fluxo de resolução

1. Identificar dimensão que caiu: `php artisan module:grade <Modulo> --detail` (output mostra D1-D9 + sub-checks com evidence)
2. Comparar com baseline: dimensão exata onde nota baixou
3. Decidir:
   - **Corrigir código** (preferido — sobe nota de verdade)
   - **Atualizar baseline** (se drop legítimo + justificável — PR dedicado §6)
   - **Aplicar label `module-grades-allowed-regression`** (se tradeoff aceito + ADR justificativa)

### "Build falha: módulo novo sem label"

1. Identificar módulo `new` no comentário do PR
2. Decidir intenção:
   - **Intencional** (módulo legítimo) → aplicar label `module-grades-new-module-allowed` + atualizar baseline pós-merge (§6)
   - **Acidental** (scan pegou pasta vendor/backup) → remover/renomear o dir físico em Modules/

```bash
# Aplicar label
gh pr edit <pr-number> --add-label "module-grades-new-module-allowed"
```

### "Label aplicada mas build ainda falha"

Provável typo na label OU GH Actions usando snapshot pré-label. Ações:

```bash
# 1. Confirmar nome exato (case-sensitive)
gh pr view <pr-number> --json labels

# 2. Listar labels disponíveis no repo
gh label list

# 3. Force re-run do workflow
gh workflow run module-grades-gate.yml --ref claude/<slug>
# OU empurrar commit vazio pra re-trigger
git commit --allow-empty -m "ci: re-trigger module-grades-gate"
git push
```

Triggers `labeled`/`unlabeled` no workflow (linha 15) DEVERIAM re-rodar automaticamente após aplicar label — se não rodou, é race condition GH (raro). Re-run manual resolve.

### "Pest local OK mas CI falha"

Causas comuns:

1. **`composer.lock` diff** — CI usa lock exato, local pode ter rodado `composer update` sem commit
2. **PHP version diff** — local PHP 8.4.x vs CI 8.4.y, regex `module:grade` heurística pode pegar comportamento diff
3. **`.env` diff** — CI roda `cp .env.example .env` + `key:generate` (workflow linhas 50-55). Se sua local tem env custom afetando ModuleGradeService, falsifica resultado
4. **Caches stale** — local com `bootstrap/cache/services.php` antigo. Limpar:

```bash
php artisan optimize:clear
php artisan module:grade --all --json > /tmp/grades-current.json
```

### "Step 'Comparar com baseline' tem `continue-on-error: true` — devo confiar no fail?"

`continue-on-error: true` (linha 90) permite a step rodar até o fim e popular `outputs` mesmo se `php -r` der erro. O bloqueio efetivo vem das DUAS steps finais:

- "Falhar build se regressão sem override" (linhas 300-304)
- "Falhar build se módulo novo sem override" (linhas 306-310)

Ou seja: `compare` reporta, `Falhar build` decide. Se você ver compare warn mas build verde, é o esperado em first-run (`first_run=true`).

## §9. Comandos artisan relacionados

| Comando | Função | Cron? |
|---|---|---|
| `php artisan module:grade {nome}` | Avalia 1 módulo | — |
| `php artisan module:grade --all` | Avalia todos (tabela ranqueada) | — |
| `php artisan module:grade --all --json` | Output machine-readable (CI usa este) | — |
| `php artisan module:grade {nome} --detail` | Breakdown 9 dimensões + sub-checks | — |
| `php artisan module:grade {nome} --evolve` | Batch de tasks-create sugeridas | — |
| `composer module-grades-check` | Alias `--all --json` | — |
| `php artisan jana:health-check` | 5 checks SQL governança (não inclui grades) | Daily 06:00 BRT |

Health check daily roda separado — ver [`app/Console/Kernel.php`](../../../app/Console/Kernel.php). Gate de grades roda **on-demand por PR**, não cron.

## §10. Métricas saúde do gate

Wagner monitora periodicamente (sem dashboard formal ainda — futuro Fase 5 [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md)):

| Métrica | Como medir | Threshold saudável |
|---|---|---|
| PRs bloqueados por regressão/mês | `gh pr list --search "label:module-grades-allowed-regression"` | ≤ 2/mês (mais que isso = rubrica satura) |
| Overrides ativos no histórico | `gh label list` + cross-check PRs mergeados com label | Cada override tem comentário/ADR justificativa |
| Cumulativo de módulos com regressão histórica | git log de `governance/module-grades-baseline.json` mostrando deltas negativos | Idealmente zero — todo drop foi corrigido + baseline atualizado pra cima |
| Falsos positivos heurística | Issues abertas com tag `module-grades-gate-false-positive` | ≤ 1/cycle (sinal de heurística degradada — review trigger ADR 0155) |

**Review trigger ADR 0155** (frontmatter): se gate CI bloquear 3+ PRs/semana por falsos positivos, auto-detect heurística degradou — abrir ADR de calibração v3.x.

## §11. Referências

**Workflow + artefatos:**

- [`.github/workflows/module-grades-gate.yml`](../../../.github/workflows/module-grades-gate.yml) — workflow CI (~311 linhas, Wave 2 LIVE)
- [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) — baseline canônica v3.1
- [`Modules/Governance/Services/ModuleGradeService.php`](../../../Modules/Governance/Services/ModuleGradeService.php) — engine de avaliação
- [`Modules/Governance/Console/Commands/ModuleGradeCommand.php`](../../../Modules/Governance/Console/Commands/ModuleGradeCommand.php) — command CLI

**ADRs canônicas:**

- [ADR 0155 — module-grade v3 + gate CI](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) **(parent)**
- [ADR 0156 — errata v3 (OtelHelper + na_justified D6-D9)](../../decisions/0156-module-grade-v3-errata-otel-helper-na-justified.md) (D9.a regex inclui OtelHelper canônico + back-compat permissiva `na_justified` D6-D9)
- [ADR 0154 — v2 N/A justificado](../../decisions/0154-module-grade-v2-na-justificado.md) (backward-compat N/A em D6-D9)
- [ADR 0153 — module-grade v1](../../decisions/0153-module-grade-rubrica-v1.md) (origem rubrica)
- [ADR 0094 — Constituição v2 (princípio 4: loop fechado por métrica)](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md) (D1)
- [ADR 0101 — Tests biz=1 nunca cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (D2)

**Skill complementar (guia rápido orientado a tarefa):**

- [`.claude/skills/module-grades-gate/SKILL.md`](../../../.claude/skills/module-grades-gate/SKILL.md) — Tier C slash command `/module-grades-gate`

**Skills auto-ativáveis relacionadas:**

- `avaliar-modulo` (Tier B) — cálculo individual + batch tasks evolução
- `comparativo-do-modulo` (Tier B) — gaps vs mercado
- `module-completeness-audit` (Tier B) — checklist binário antes de `done`
- `inertia-defer-default` (Tier B) — força D6.a durante Edit
- `commit-discipline` (Tier A) — força PII redactor (D7.a)
- `multi-tenant-patterns` (Tier A) — força D1

**Runbook irmão (gate diferente):**

- [`RUNBOOK-governance-gate-ci.md`](RUNBOOK-governance-gate-ci.md) — workflow `governance-gate.yml` (ADR append-only, PII scan, cascade review) — NÃO é o mesmo workflow deste runbook
