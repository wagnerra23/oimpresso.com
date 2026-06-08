---
slug: 0156-module-grade-v3-errata-otel-helper-na-justified
number: 0156
title: "module-grade-v3 errata — D9.a regex inclui OtelHelper canônico + ratifica na_justified D6-D9 backward-compat"
type: adr
status: accepted
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
accepted_at: 2026-05-16
review_at: 2026-05-23
module: Governance
quarter: 2026-Q2
tags: [governance, errata, observability, na-justified, backward-compat]
supersedes: []
supersedes_partially: [0155]
superseded_by: []
related: [0155, 0154, 0153, 0094]
pii: false
review_triggers:
  - Quando OTel SDK consolidar e D6.b virar hard check (também trigger 0155)
  - Quando D9.a precisar separar `OtelHelper` (canônico oimpresso) vs `OpenTelemetry::*` (SDK direto) — split em sub-métricas
  - Se >3 módulos abusarem do canal `na_justified` ampliado pra escapar D6-D9 (sinal de gaming)
---

# ADR 0156 — module-grade-v3 errata: D9.a regex inclui OtelHelper canônico + ratifica `na_justified` D6-D9 backward-compat

## Contexto

[ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) (`module-grade-v3` — 4 sub-dimensões novas D6-D9 + reweight + gate CI) foi aceita 2026-05-16. Durante implementação Fase 1 (`ModuleGradeServiceV3` lado a lado com V2), agent paralelo encontrou **2 ambiguidades** que não estavam cobertas explicitamente no texto da 0155 e resolveu via heurística pragmática. Esta errata ratifica formalmente as 2 resoluções (ou Wagner aprova variante).

### Ambiguidade 1 — D9.a regex incompleto

ADR 0155 §"D9 Observability" linha tabela D9.a especifica:

> `grep "OpenTelemetry" Modules/<X>/Services/**/*.php` OU `grep "otel_span" Modules/<X>/**/*.php` OU `grep "Tracer::" Modules/<X>/**/*.php` — pelo menos 1 span manual instrumentado

Implementação atual em [`Modules/Governance/Services/ModuleGradeService.php`](../../Modules/Governance/Services/ModuleGradeService.php) linha 902:

```php
if (preg_match('/\b(OpenTelemetry|otel_span|Tracer|StartSpan|tracer\(\))/i', $content)) {
```

**Problema:** o padrão CANÔNICO oimpresso pra instrumentation NÃO é OpenTelemetry SDK direto — é a facade `App\Util\OtelHelper` (ver [`app/Util/OtelHelper.php`](../../app/Util/OtelHelper.php)):

```php
// Padrão canônico oimpresso (zero-cost no-op quando SDK ausente)
OtelHelper::spanBiz('sells.fsm.execute_action', fn () => $svc->run(), ['action_key' => $action]);
OtelHelper::span('jana.embed', ['business_id' => $bizId], fn () => $emb->embed($text));
```

`OtelHelper` é o wrapper oficial Tier 0 multi-tenant (resolve `business_id` automaticamente, fail-safe sem SDK). Módulos novos que adotam o padrão canônico (Sells FSM, Jana embeddings, Whatsapp daemon) **pontuam ZERO em D9.a** — inverso da intenção da rubrica, que era recompensar instrumentation.

### Ambiguidade 2 — `na_justified` chaves D6-D9 (v2 vs v3)

ADR 0155 tem dois trechos potencialmente conflitantes:

- **§"N/A v2 backward-compat":** *"Service v3 lê frontmatter SPEC `na_justified: [d6.a, d7.c]` e exclui sub do cálculo + normaliza peso restante pro denominador (mesma lógica v2)"* — sugere que chaves D6-D9 dentro de `na_justified` (v2) funcionam.
- **§"Fase 1 Service v3 dual-mode":** *"frontmatter SPEC suporta `na_justified` (v2) E `na_justified_v3` (v3) — coexistem"* — sugere distinção formal entre 2 canais separados.

Não fica claro: **chaves D6-D9 em `na_justified` (canal v2) são aceitas ou exigem migrar pra `na_justified_v3`?**

Implementação atual em `ModuleGradeService::loadNaJustified()` (linhas 1247-1292) escolheu **back-compat ampliado permissivo:**

- Chaves D6-D9 em `na_justified` (v2) **continuam funcionando** — Service merge ambos canais
- **Soft-deprecation log** sugere migrar pra `na_justified_v3` (canal preferencial)
- Limite anti-gaming (3 N/A total por módulo — ADR 0154) **aplica à união** dos 2 canais

Cenário Pest da Fase 1 já valida esse comportamento.

## Decisão

Aceitar formalmente as 2 resoluções de implementação como **errata oficial da ADR 0155**. ADR 0155 permanece intacta (append-only) — esta errata é `supersedes_partially: [0155]` apenas pros 2 pontos específicos abaixo.

### Errata 1 — D9.a regex inclui `OtelHelper` canônico

Regex D9.a atualizada (substitui texto literal da tabela D9 em ADR 0155):

```regex
\b(OpenTelemetry|otel_span|Tracer|StartSpan|tracer\(\)|OtelHelper::span(?:Biz)?)
```

Adição: `OtelHelper::span` e `OtelHelper::spanBiz` (alternância opcional `(?:Biz)?`).

**Cobertura resultante:**

| Padrão detectado | Caso de uso típico |
|---|---|
| `OpenTelemetry` (namespace) | SDK direto — código que `use OpenTelemetry\...` |
| `otel_span` | Helper legacy (caso ainda exista) |
| `Tracer`, `StartSpan` | API OTel direta |
| `tracer()` | Helper function global (se houver) |
| `OtelHelper::span` | **Padrão canônico oimpresso (facade zero-cost)** |
| `OtelHelper::spanBiz` | **Variante multi-tenant Tier 0 do canônico** |

**Justificativa do canônico ter prioridade conceitual:**

1. **`OtelHelper` é o wrapper oficial Tier 0** — automaticamente injeta `business_id` ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)), evita boilerplate em N módulos
2. **Zero-cost no-op quando SDK ausente** — overhead < 1µs (config check + class_exists). Módulos podem instrumentar agora mesmo sem custo de produção, ganhando pontos D9.a
3. **Compat backwards SDK direto** — quem usa `OpenTelemetry` namespace direto continua pontuando (alternância `|` no regex)

### Errata 2 — `na_justified` D6-D9 backward-compat ampliada (ratificada)

Ratificar formalmente o comportamento permissivo da implementação atual.

**Tabela canônica de canais — onde declarar `na_justified` por chave** (referência rápida pro time MCP entrando):

| Chave | Canal preferencial | Canal alternativo | Comportamento Service |
|---|---|---|---|
| **D1-D5** (`multi_tenant`, `pest`, `doc`, `arquit`, `cliente`) | `na_justified` (v2) | — | Silencioso, sem warning (ADR 0154 idem) |
| **D6-D9** (`perf`, `lgpd`, `sec`, `obs`) | `na_justified_v3` | `na_justified` v2 (back-compat ampliada) | Em v2: `Log::info` soft-deprecation; em v3: silencioso |
| **Qualquer chave** (D1-D9) em **ambos canais** | Merge união + dedup — `na_justified_v3` ganha em colisão | — | Anti-gaming limit 3 N/A total (união v2 ∪ v3) — ADR 0154 |
| **D1-D5** em `na_justified_v3` | Aceito (back-compat reverso) | `na_justified` v2 (canônico histórico) | Silencioso — sem warning (igual D1-D5 em v2) |

> **Regra mnemônica:** "chaves antigas vivem em canal antigo silenciosas; chaves novas (D6-D9) preferem canal novo (`na_justified_v3`), mas o velho ainda aceita com aviso." A política completa por caso está abaixo.

Casos detalhados:

| Caso | Comportamento |
|---|---|
| `na_justified: [d1.a, d5]` (chaves v1/v2 só) | ✅ Funciona idêntico ADR 0154 |
| `na_justified: [d6.a, d7.c]` (chaves D6-D9 no canal v2) | ✅ Funciona + **soft-deprecation log** sugere migrar pra `na_justified_v3` |
| `na_justified_v3: [d6.a, d8.b]` (canal v3 dedicado) | ✅ Canal preferencial — sem warning |
| Ambos canais declarados (v2 + v3) | ✅ Merge união, dedup, aplicar limite 3 total |
| Total N/A (união v2 ∪ v3) > 3 | ❌ Excedentes ignoradas + warning ([ADR 0154](0154-module-grade-v2-na-justificado.md) anti-gaming) |

**Política de migração (não-bloqueante):**

- Módulos existentes que já declararam `na_justified: [d6.x]` durante implementação Fase 1 continuam válidos
- PRs subsequentes podem migrar gradualmente pra `na_justified_v3` (canal preferencial)
- Sunset do canal v2 pra D6-D9 só será decidido em ADR futura — quando 100% dos módulos tiverem migrado OU quando coleta drift indicar gaming concentrado no canal v2

**Justificativa do permissivo:**

1. **Backward-compat real** — alguns módulos já tinham `na_justified` antes da v3 entrar; forçar split do dia pra noite gera churn em SPEC.md de N módulos
2. **Soft-deprecation log** preserva sinal de migração futura sem quebrar nada hoje
3. **Limite anti-gaming (3 N/A total)** continua valendo — ampliar canal NÃO amplia volume permitido
4. **Lei de Postel** — "be liberal in what you accept" (leitura permissiva) + "conservative in what you send" (sugestão de canal v3 nos logs)

## Consequências

### Positivas

- ✅ **D9.a mede o canônico real** — módulos que adotam `OtelHelper` (Sells FSM, Jana, Whatsapp) deixam de ser injustamente penalizados; pontuação reflete a instrumentation que existe em prod
- ✅ **Compatibilidade total com SDK direto** — alternância `|` no regex preserva quem usa OpenTelemetry namespace direto
- ✅ **`na_justified` permissivo evita churn** — SPECs existentes não precisam re-escrita imediata
- ✅ **Log de deprecation futuro guia migração gradual** — sem big-bang
- ✅ **Anti-gaming preservado** — limite 3 N/A (ADR 0154) ainda enforced sobre união v2 ∪ v3
- ✅ **Append-only respeitado** — ADR 0155 não é editada; errata via ADR nova com `supersedes_partially`

### Negativas

- ❌ **Regex `OtelHelper::span(?:Biz)?` é específico do oimpresso** — outros projetos copiando a rubrica precisam adaptar (mas isso já era verdade da ADR 0155 inteira)
- ❌ **`na_justified` ampliado pode mascarar gaps reais** — se módulo declarar `d6.a, d6.b, d6.c` todos N/A, perde sinal D6 Performance. Mitigado por: limite 3 total + audit drift
- ❌ **Soft-deprecation log poluindo console** se muitos módulos usarem o canal v2 — aceitável: severidade `info`, dedupable

### Mitigações

| Risco | Mitigação |
|---|---|
| Gaming via canal v2 ampliado | Limite 3 N/A total (união) — Service enforça em `loadNaJustified` |
| Falso positivo D9.a (string `OtelHelper::span` em comentário) | Aceito — overhead trivial. Regex case-sensitive `OtelHelper::span` evita matches incidentais ("opentelemetry::span" lowercase pega via `|i` flag separada) |
| Drift na cobertura — códigos novos não adotam `OtelHelper` | Skill `inertia-defer-default` já força padrões similares (Tier B auto-trigger). Futuro: criar skill `otel-helper-canonico` (Tier B) auto-triggered ao editar Service/Job |
| Sunset eventual do canal v2 pra D6-D9 | Decidido em ADR futura quando >80% módulos migraram; trigger registrado nos `review_triggers` |

## Test plan

**Pest cenários cobertos (em `tests/Feature/Governance/ModuleGradeV3Test.php` já em desenvolvimento Fase 1):**

| Teste | Fixture | Asserção |
|---|---|---|
| `it d9a detects otel_helper_span_canonical` | Service com `OtelHelper::span('foo', [...], fn() => ...)` | D9.a = 4 (full) |
| `it d9a detects otel_helper_spanBiz_canonical` | Service com `OtelHelper::spanBiz('foo', fn() => ...)` | D9.a = 4 (full) |
| `it d9a detects opentelemetry_sdk_direct` | Service com `use OpenTelemetry\API\Globals;` + spanBuilder | D9.a = 4 (full, compat) |
| `it d9a zero when no otel signal` | Service sem nenhum padrão OTel | D9.a = 0 |
| `it na_justified_v2_accepts_d6d9_with_warning` | SPEC com `na_justified: [d6.a]` | D6.a marcado N/A + log soft-deprecation emitido |
| `it na_justified_v3_canonical_no_warning` | SPEC com `na_justified_v3: [d6.a]` | D6.a marcado N/A + sem warning |
| `it na_justified_v2_v3_union_dedup` | SPEC com `na_justified: [d6.a, d7.c]` + `na_justified_v3: [d7.c, d8.b]` | União = [d6.a, d7.c, d8.b] (3 itens, dentro limite) |
| `it na_justified_union_exceeds_limit_warns` | SPEC com `na_justified: [d6.a, d7.c]` + `na_justified_v3: [d8.b, d9.a]` (4 total) | 1 excedente ignorada + warning anti-gaming |

**Smoke test manual:**

> Flags reais do command (ver [`ModuleGradeCommand.php`](../../Modules/Governance/Console/Commands/ModuleGradeCommand.php) linhas 28-33): `--all`, `--json`, `--detail`, `--evolve`. NÃO existe `--version` — Service v3 é o engine atual unificado (back-compat absorvida via `na_justified` + `na_justified_v3`).

1. Rodar `php artisan module:grade Sells --detail` — confirmar D9.a detecta `OtelHelper::spanBiz` em `Modules/Sells/Services/ExecuteStageActionService.php` (FSM canon)
2. Rodar `php artisan module:grade Governance --detail` — confirmar `na_justified` legacy continua funcionando se ainda existir
3. Rodar `php artisan module:grade --all --json` — confirmar baseline JSON íntegro pós-rollout do regex `OtelHelper`
4. Ler log `storage/logs/laravel.log` — confirmar soft-deprecation entry quando D6-D9 aparecem em `na_justified` (v2)

## Referências

**ADRs:**
- [ADR 0155 — module-grade-v3 sub-dimensões + gate CI](0155-module-grade-v3-sub-dimensoes-gate-ci.md) (errata parcial supersedes)
- [ADR 0154 — module-grade-v2 N/A justificado](0154-module-grade-v2-na-justificado.md) (limite 3 N/A herdado)
- [ADR 0153 — module-grade-v1 rubrica 5 dim](0153-module-grade-rubrica-v1.md) (base original)
- [ADR 0094 — Constituição v2 (loop fechado por métrica)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0093 — Multi-tenant Tier 0 (OtelHelper::spanBiz injeta business_id)](0093-multi-tenant-isolation-tier-0.md)

**Código canônico:**
- [`app/Util/OtelHelper.php`](../../app/Util/OtelHelper.php) — facade zero-cost (linhas 17-81)
- [`Modules/Governance/Services/ModuleGradeService.php`](../../Modules/Governance/Services/ModuleGradeService.php) — método `dim9Observability` (linha 892+) + `loadNaJustified` (linha 1247+)

**Proibições relevantes:**
- [memory/proibicoes.md](../proibicoes.md) §"Memória/governança" — ADRs CANON append-only (motivo desta errata existir como ADR nova, não edit de 0155)

---

**Wagner aprova → status `proposed` → `accepted`. Sem aprovação Wagner explícita, regex e back-compat permissiva NÃO viram canônicas formais (mas implementação Fase 1 já validou comportamento empiricamente — risco baixo de rollback).**
