---
slug: 0155-module-grade-v3-sub-dimensoes-gate-ci
number: 0155
title: "module-grade-v3 — 4 sub-dimensões novas (Performance/LGPD/Security/Observability) + reweight + gate CI anti-regressão"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
module: Governance
quarter: 2026-Q2
tags: [governance, qualidade, audit, dashboard, dim-9-pesos-100-normalizado, gate-ci, anti-regressao, performance, lgpd, security, observability]
supersedes: []
supersedes_partially: [0153, 0154]
superseded_by: []
related: [0093, 0094, 0101, 0105, 0143, 0153, 0154]
pii: false
review_triggers:
  - Quando média projeto bater 90+ (sinal v3 saturou — precisa v4 com financial/ROI dims)
  - Quando OTel exporter ficar 100% on em todos módulos (D6.b sai de placeholder pra hard check)
  - Quando 30%+ dos módulos baterem D6-D9 com nota perfeita (sinal pra elevar barra)
  - Se gate CI bloquear 3+ PRs/semana por falsos positivos (auto-detect heurística degradou)
---

# ADR 0155 — module-grade-v3: sub-dimensões Performance/LGPD/Security/Observability + gate CI anti-regressão

## Contexto

ADR 0153 (`module-grade-v1`, 5 dimensões × 100 pts) e ADR 0154 (`v2 N/A justificado` — backward-compat pra módulos que legitimamente não aplicam dimensão, ex Connector REST API → Governance 100/100 via N/A em D5 Cliente) estão **LIVE** desde 2026-05-15.

**Estado atual (média projeto 65.1):**
- v1 mediu o que estava operacional (multi-tenant, Pest, doc, arquitetura, sinal cliente)
- v2 corrigiu injustiça com módulos backend/infra (N/A justificado em vez de nota 0)
- Vários módulos batendo teto v1 (90-100) — rubrica saturando rápido. Sinal de que precisa subir a barra antes de virar trofeu inflado

**Gap detectado pela auditoria 2026-05-15→16:**

| Capacidade | Cobertura v1 | Realidade prod |
|---|---|---|
| Performance (Inertia::defer, p99 latency, N+1) | Zero | D-14 incident 2026-05-15 — Inbox 300ms→50ms via defer (-83%) ([proibicoes.md](../proibicoes.md) §"Sempre fazer") |
| LGPD compliance (PiiRedactor, LogsActivity, retention) | Zero | Pilar LGPD parado (Eliana estuda primeiro — [regras-time.md](../regras-time.md)). PiiRedactor existe mas adoção dispersa |
| Security (throttle, CSRF, FormRequest) | Zero | Tier 0 implícito mas nunca medido por módulo |
| Observability (OTel spans, failed_jobs) | Zero | OTel GenAI em produção via Jana ([ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md)) mas sub-utilizado fora Modules/Jana |

**Risco de não evoluir rubrica:** módulos viram "100/100" em v1, sensação de "pronto" colide com bugs reais de performance/segurança/LGPD em prod. Wagner aprovou Cenário Ambicioso 2026-05-16 — subir média alvo pra 80-83 via 4 sub-dimensões novas + gate CI anti-regressão.

**Princípio:** loop fechado por métrica (Constituição v2 princípio 4 — [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)). Métrica nova só entra se tem auto-detect heurístico viável (não exige humano olhar por módulo).

## Decisão

Aceitar **`module-grade-v3`** como rubrica canônica oficial, **substituindo parcialmente v1 (ADR 0153) e v2 (ADR 0154)**:

- **Adiciona 4 sub-dimensões novas** (D6-D9) cobrindo Performance, LGPD, Security, Observability
- **Reweight pesos** das 5 dimensões originais pra acomodar D6-D9 sem ultrapassar /100 conceitual
- **Mantém N/A justificado v2 backward-compat** — pode aplicar em D6-D9 também (ex: Connector D6.a N/A porque é REST API backend sem Inertia)
- **Gate CI `module-grades-gate.yml`** bloqueia merge se nota de qualquer módulo regrediu vs baseline checked-in

### Dimensões v3 (peso total 100, normalizado)

| Dim | Nome | Peso v1 | Peso v3 | Δ |
|---|---|---|---|---|
| D1 | Multi-tenant + Tier 0 | 30 | **25** | -5 |
| D2 | Pest + cobertura | 20 | **17** | -3 |
| D3 | Documentação (SPEC/RUNBOOK/charter/BRIEFING) | 15 | **12** | -3 |
| D4 | Arquitetura (Cockpit Pattern V2, defer, SoC) | 20 | **17** | -3 |
| D5 | Sinal qualificado de cliente | 15 | **12** | -3 |
| D6 | **Performance** (NOVO) | — | **10** | +10 |
| D7 | **LGPD + Compliance** (NOVO) | — | **10** | +10 |
| D8 | **Security** (NOVO) | — | **8** | +8 |
| D9 | **Observability** (NOVO) | — | **7** | +7 |
| **Total** | | **100** | **118** raw → **/100 normalizado** | — |

**Pesos somam 118 raw** — Service `ModuleGradeService` v3 reporta nota final dividindo por 1.18 (normalização linear pra /100). Decisão completa em §"Decisão final pesos" abaixo.

### D6 Performance (peso 10)

| Sub | Critério | Auto-detect | Pts |
|---|---|---|---|
| D6.a | `Inertia::defer` aplicado em props caras | `grep "Inertia::defer" Modules/<X>/Http/Controllers/**/*.php` — pelo menos 1 ocorrência por Controller que tem prop paginate/count/with eager | 4 |
| D6.b | p99 <500ms (queries Controller principal) | **Placeholder** se OTel exporter não está exportando spans pro módulo: score parcial 50% (1.5 pts) até virar hard check. Quando OTel ativo: query `mcp_observability.spans` por `module=X` últimas 24h → p99 ms | 3 |
| D6.c | Sem N+1 detectado (heurística estática) | Controller com `paginate(` E `->with(` (eager-load explícito) → +3 pts. Controller com `paginate(` SEM `->with(` E Model tem relations carregadas em accessor → -3 (penalidade N+1 provável) | 3 |

**Skill relacionada:** `inertia-defer-default` (Tier B) — força regra durante Edit ([proibicoes.md](../proibicoes.md) §"Sempre fazer").

### D7 LGPD + Compliance (peso 10)

| Sub | Critério | Auto-detect | Pts |
|---|---|---|---|
| D7.a | `PiiRedactor` aplicado em logs/exports/PR | `grep "PiiRedactor" Modules/<X>/**/*.php` + `grep "PiiRedactor" Modules/<X>/**/*.{tsx,jsx}` (frontend redact em export) | 4 |
| D7.b | `LogsActivity` em Models que tocam PII | `grep "use LogsActivity" Modules/<X>/Models/*.php` ou config `spatie/laravel-activitylog` declarando o Model | 3 |
| D7.c | Retention policy declarada | `module.json` campo `retention_days` OU `config/<modulo>.php` chave `retention_days` OU ADR específico do módulo declarando "retention X dias" | 3 |

**Counsel LGPD externo pendente** ([regras-time.md](../regras-time.md)) — D7 mede o que está implementado em código, não substitui pilares LGPD formais.

### D8 Security (peso 8)

| Sub | Critério | Auto-detect | Pts |
|---|---|---|---|
| D8.a | `throttle` middleware em routes sensíveis | `grep "throttle" Modules/<X>/Http/routes.php` OU `routes/web.php` (em routes que tocam o módulo) | 3 |
| D8.b | CSRF Inertia ativo (default Laravel) | Default Laravel = ON. Penalidade se aparecer `VerifyCsrfToken::except` listando routes do módulo → -2 pts. Padrão = 2 pts | 2 |
| D8.c | FormRequest validation em Controller actions | `grep "extends FormRequest" Modules/<X>/Http/Requests/*.php` — pelo menos 1 FormRequest no módulo. Bonus: Controller actions que NÃO usam `$request->validate()` inline (preferência por FormRequest dedicado) | 3 |

### D9 Observability (peso 7)

| Sub | Critério | Auto-detect | Pts |
|---|---|---|---|
| D9.a | OTel spans em Services | `grep "OpenTelemetry" Modules/<X>/Services/**/*.php` OU `grep "otel_span" Modules/<X>/**/*.php` OU `grep "Tracer::" Modules/<X>/**/*.php` — pelo menos 1 span manual instrumentado | 4 |
| D9.b | `failed_jobs` <5 nas últimas 24h pro módulo | Query `SELECT COUNT(*) FROM failed_jobs WHERE payload LIKE '%Modules\\\\<X>\\\\%' AND failed_at > NOW() - INTERVAL 24 HOUR`. <5 = 3 pts. 5-20 = 1.5 pts. >20 = 0 pts | 3 |

### Decisão final pesos: **Opção A — normalizado /100** (recomendada)

**Comparação:**

| Aspecto | Opção A (normalizado /100) | Opção B (raw /118) |
|---|---|---|
| Comparabilidade com v1/v2 históricas | ✅ Mantida (todas viraram /100) | ❌ Quebra continuidade |
| Comunicação Wagner/time | ✅ "Nota X/100" intuitivo | ❌ "Nota X/118" exige explicação |
| Dashboard/UI | ✅ Sem migration de queries existentes | ❌ Refactor de `mcp_module_grades.score_pct` |
| Reverso engineering humano (qual sub bombou?) | ⚠️ Precisa drill-down (não muda) | ⚠️ Mesmo gap |

**Decisão:** Opção A. `ModuleGradeService` v3 calcula nota raw (soma D1-D9 com pesos brutos 25+17+12+17+12+10+10+8+7=118) e normaliza dividindo por 1.18. Output final em `/100`. Detalhe raw acessível via `--detail` pra audit.

### Gate CI `module-grades-gate.yml` (workflow)

Workflow GitHub Actions novo em `.github/workflows/module-grades-gate.yml`:

```yaml
name: module-grades-gate
on:
  pull_request:
    branches: [main]

jobs:
  anti-regression:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP 8.4
        uses: shivammathur/setup-php@v2
        with: { php-version: 8.4 }
      - name: Composer install
        run: composer install --no-dev --no-interaction --prefer-dist
      - name: Run grades current branch
        run: php artisan module:grade --all --json > /tmp/grades-current.json
      - name: Load baseline
        run: cp memory/governance/module-grade-baseline.json /tmp/grades-baseline.json
      - name: Compare
        run: php artisan module:grade-diff /tmp/grades-baseline.json /tmp/grades-current.json
      - name: Check override label
        if: failure()
        run: |
          if [[ "${{ contains(github.event.pull_request.labels.*.name, 'module-grades-allowed-regression') }}" == "true" ]]; then
            echo "Override label aplicada — Wagner ciente da regressão."
            exit 0
          fi
          exit 1
```

**Regras gate:**
- Falha se **qualquer** módulo regrediu (nota_PR < nota_baseline)
- Override via label `module-grades-allowed-regression` (aplicada manualmente — vira comentário automático no PR linkando ADR justificativa)
- Baseline `memory/governance/module-grade-baseline.json` atualizado **manualmente via PR** (não auto). PR que muda baseline exige aprovação Wagner + nota descritiva no commit
- Comando novo `php artisan module:grade-diff <baseline> <current>` retorna exit code 1 se regressão, 0 se neutro/positivo

**Baseline JSON formato:**

```json
{
  "version": "v3",
  "generated_at": "2026-05-16T14:00:00-03:00",
  "modules": {
    "Whatsapp": { "score_pct": 83, "raw": 98.0, "d1": 25, "d2": 17, ... },
    "Jana":     { "score_pct": 76, "raw": 89.7, ... },
    ...
  }
}
```

### N/A v2 backward-compat (ADR 0154 estende pra D6-D9)

ADR 0154 N/A continua válido em v3. Aplicação a D6-D9:

| Caso | N/A válido em | Justificativa frontmatter `na_justified` |
|---|---|---|
| Connector (REST API backend) | D5 Cliente, D6.a Inertia::defer | "Connector não renderiza páginas Inertia — defer N/A. Sinal cliente medido em ADR 0015 via uptime gateway." |
| Superadmin (CLI/admin tier) | D5 Cliente, D7.c retention (config global) | "Superadmin não tem retention dedicado — segue global Laravel `auth.passwords`." |
| Spreadsheet (embrião pre-v0) | D6, D7, D8, D9 todas | "Embrião <20 pts v1 — N/A em sub-dims novas até MVP." |

Service v3 lê frontmatter SPEC `na_justified: [d6.a, d7.c]` e exclui sub do cálculo + normaliza peso restante pro denominador (mesma lógica v2).

## Consequências

### Positivas

- ✅ **Cobertura real-world**: 4 áreas críticas (perf/LGPD/sec/obs) agora visíveis na nota — antes invisíveis
- ✅ **Anti-trofeu**: rubrica não satura tão rápido — média projeto sai de 65.1 (v1) pra estimado 55-60 inicial v3 (antes de mitigation), com headroom de crescimento pra 80-83
- ✅ **Gate CI bloqueia regressão**: cultura "subir nota, nunca baixar" formal
- ✅ **N/A v2 preservado**: Connector/Superadmin/embriões não viram zona zero injusta
- ✅ **Auto-detect heurístico**: zero humano olhando código módulo a módulo — escala pra time MCP entrando ([proibicoes.md](../proibicoes.md) §"REGRA PRIMÁRIA")
- ✅ **D-14 Inertia::defer incident** ([proibicoes.md](../proibicoes.md)) virou métrica continuamente medida — não vai voltar a regredir silenciosamente

### Negativas

- ❌ **Drop inicial das notas** (média 65.1 → estimado 55-60) pode ser percebido como "perdi conhecimento" antes de Wagner explicar pra time MCP
- ❌ **D6.b OTel placeholder**: nota parcial enquanto OTel exporter não exporta — 50% dos pontos perdidos por padrão. Risco de virar item permanentemente meio-implementado
- ❌ **Gate CI pode bloquear PR legítimo** se heurística estática D6.c (N+1) ou D8.c (FormRequest) der falso positivo
- ❌ **Baseline manual** — esquecimento de atualizar baseline após PR aprovado gera ruído (gate falha em PR seguinte que herda do anterior)
- ❌ **Pesos /118 raw → /100 normalizado** — confusão potencial pra quem ler raw e esperar 100

### Mitigações

| Risco | Mitigação |
|---|---|
| Drop nota inicial percebido como ruim | Wagner publica session log + post no DSign anunciando: "v3 LIVE — base nova mede mais. Mais importante: ELA SOBE, não a v1 trofeu" |
| D6.b OTel placeholder permanente | Review trigger explícito no frontmatter: "Quando OTel exporter 100% on em todos módulos (D6.b sai de placeholder pra hard check)". Spawn task de instrumentação OTel em batch ASAP |
| Gate CI falso positivo | Label override `module-grades-allowed-regression` documentada no `.github/PULL_REQUEST_TEMPLATE.md`. PR template fala "se gate falhou, justificar OU corrigir" |
| Baseline esquecido | Comando `php artisan module:grade --all --update-baseline` rodado por Wagner pós-merge cycle. Hook futuro: workflow post-merge auto-detecta drift |
| /118 raw confusão | Output default `/100`. Raw só em `--detail`. Doc skill `avaliar-modulo` atualizada pra v3 explicando |

## Plano migração v2 → v3

**Não é big-bang.** ADR 0153 + 0154 continuam vivos como "v1 + v2 N/A" enquanto v3 entra. Service `ModuleGradeService` v3 nasce paralelo, switchable via flag.

**Fase 1 — Service v3 dual-mode (1 dia):**
- Implementar `ModuleGradeServiceV3` lado a lado com V2 existente
- Comando `php artisan module:grade {nome} --version=v3` (default ainda v2)
- Frontmatter SPEC suporta `na_justified` (v2) E `na_justified_v3` (v3) — coexistem
- Pest cobertura: cada sub D6-D9 com fixture módulo simulado

**Fase 2 — Backfill baseline (1 dia):**
- Wagner roda `php artisan module:grade --all --version=v3 --output=memory/governance/module-grade-baseline.json`
- PR commitando baseline + aceitando ADR 0155 (proposto → aceito)
- Snapshot fixed-in-time vira referência inicial

**Fase 3 — Gate CI ON (mesma sessão Fase 2):**
- Workflow `module-grades-gate.yml` ON
- Próximo PR não-relacionado serve de smoke test (rodar gate, validar exit code)

**Fase 4 — Default switch (após 7d sem incidente):**
- `--version=v3` vira default
- v2 continua acessível via `--version=v2 --legacy`
- Dashboard `/copiloto/admin/module-grades` mostra v3 default + tab v2 legacy comparativo

**Fase 5 — Sunset v2 (60d):**
- Após 60d default v3, comando `--version=v2 --legacy` vira deprecation warning
- 120d total — v2 removido. v1 ADR 0153 marcado `lifecycle: historical`, v2 ADR 0154 marcado `lifecycle: historical`. v3 ADR 0155 vira a única canônica ativa

## Test plan

**Pest fixtures (em `tests/Feature/Governance/ModuleGradeV3Test.php`):**

| Teste | Fixture | Asserção |
|---|---|---|
| `it computes d6_performance_full_score` | Módulo fake com `Inertia::defer` + sem N+1 + OTel ON p99 200ms | D6 total = 10 |
| `it penalizes n_plus_one_heuristic` | Módulo com `paginate(` sem `->with(` + accessor relations | D6.c = 0 |
| `it placeholders_otel_inactive` | OTel exporter desligado pro módulo | D6.b = 1.5 (50%) |
| `it computes d7_lgpd_full_score` | PiiRedactor + LogsActivity + retention_days declarado | D7 total = 10 |
| `it d8_throttle_detected` | Route com `->middleware('throttle:60,1')` | D8.a = 3 |
| `it d8_csrf_except_penalty` | `VerifyCsrfToken::except` listando route do módulo | D8.b = 0 |
| `it d9_otel_spans_detected` | Service com `Tracer::start()` | D9.a = 4 |
| `it d9_failed_jobs_query` | Mock failed_jobs com 3 entries 24h | D9.b = 3 |
| `it normalizes_to_100` | Módulo perfeito (raw 118) | score_pct = 100 (118/1.18) |
| `it na_v2_backward_compat_v3` | Connector com `na_justified_v3: [d5, d6.a]` | denominador ajustado, score válido |
| `it gate_ci_blocks_regression` | Baseline Whatsapp=83, current=81 | exit code 1 |
| `it gate_ci_allows_override` | Label `module-grades-allowed-regression` presente | exit code 0 |

**Smoke test manual pós-deploy:**
1. Rodar `php artisan module:grade Whatsapp --version=v3 --detail` — confirmar break-down 9 dims
2. Forçar regressão fake (rebaixar Pest cov em fixture) → push PR → confirmar gate CI bloqueia
3. Aplicar label override → confirmar gate passa

## Referências

**ADRs anteriores:**
- [ADR 0093 — Multi-tenant Tier 0 isolation](0093-multi-tenant-isolation-tier-0.md) (D1)
- [ADR 0094 — Constituição v2 (princípio 4: loop fechado por métrica)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0101 — Tests biz=1 nunca cliente](0101-tests-business-id-1-nunca-cliente.md) (D2)
- [ADR 0105 — Cliente como sinal qualificado](0105-cliente-como-sinal-guiar-sem-mandar.md) (D5)
- [ADR 0143 — FSM Pipeline LIVE prod biz=1](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0153 — module-grade-v1 (rubrica 5 dim)](0153-module-grade-rubrica-v1.md) (parent)
- [ADR 0154 — v2 N/A justificado](0154-module-grade-v2-na-justificado.md) (parent)

**Skills relacionadas:**
- `avaliar-modulo` (Tier B) — usuário interface da rubrica. Atualizar pra v3 default
- `inertia-defer-default` (Tier B) — força D6.a durante Edit
- `multi-tenant-patterns` (Tier A) — força D1
- `commit-discipline` (Tier A) — força D7.a (PII redactor em commits)
- `module-completeness-audit` (Tier B) — complementa rubrica antes de marcar US `done`

**Runbooks:**
- [memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md](../requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md) (D6.a)
- [memory/requisitos/Infra/RUNBOOK-governance-gate-ci.md](../requisitos/Infra/RUNBOOK-governance-gate-ci.md) (gate CI pattern reusable)

**Proibições relevantes:**
- [memory/proibicoes.md](../proibicoes.md) §"REGRA PRIMÁRIA — mexeu, registra"
- [memory/proibicoes.md](../proibicoes.md) §"Sempre fazer" — `Inertia::defer` default (D6.a)
- [memory/proibicoes.md](../proibicoes.md) §"Memória/governança" — ADRs CANON append-only

---

**Wagner aprova → status `proposto` → `aceito`. Sem aprovação Wagner explícita, v3 NÃO entra em prod nem gate CI ativa.**
