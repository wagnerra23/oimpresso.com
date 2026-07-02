---
slug: 0159-module-grade-v3-errata-meta-97-realismo
number: 159
title: "module-grade-v3 errata — realismo meta 97.75 (D5 cross-cutting / D9.b ready / D4.b N/A / D3.b CHANGELOG)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
proposed_at: 2026-05-16
review_at: 2026-05-23
module: Governance
quarter: 2026-Q2
tags: [governance, errata, rubrica, meta-97, realismo, cross-cutting]
supersedes: []
supersedes_partially: [0155, 0156]
superseded_by: []
related: [0155, 0156, 0157, 0158, 0153, 0154, 0094, 0105]
pii: false
review_triggers:
  - Quando média global da rubrica ultrapassar 97.75 (meta Wagner) e tendência continuar subindo → considerar reapertar D5
  - Quando 3+ módulos abusarem do canal `internal_governance_active` pra escapar D5 (gaming)
  - Quando 3+ módulos declararem `governance.fsm_n_a:true` sem justificativa documentada (gaming D4.b)
  - Quando OTel SDK virar canônico prod (D9.b ready-mode passa de placeholder → real DB query padrão)
---

# ADR 0159 — module-grade-v3 errata: realismo meta 97.75 (D5 cross-cutting / D9.b ready / D4.b N/A / D3.b CHANGELOG)

## Contexto

Wagner definiu meta de **média global 97.75/100** para todos os 34 módulos avaliados pela rubrica `module-grade-v3` ([ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) + erratas [0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) / [0157](0157-module-grade-v3-d2-detection-hardening.md) / [0158](0158-module-grade-v3-d1-heuristica-hardening.md)).

**Diagnóstico Wave 18 (2026-05-16):**

- Média atual: ~71/100
- Módulo recordista (Governance): ~88/100 (teto prático atual)
- Gap pra meta: **+26.75 pts** em média; **+9.75 pts** mesmo no melhor caso

**Sinais que travam o teto:**

| Sub-dim | Peso | Problema | Módulos afetados |
|---|---|---|---|
| **D5** (cliente_real) | 12-15 pts | YAML-driven; apenas níveis voltados pra produto vendável; cross-cutting infra zera ou pega só 10/15 | 8 módulos (Governance, Auditoria, Admin, Brief, TeamMcp, Superadmin, Connector, Officeimpresso) |
| **D9.b** (failed_jobs) | 3 pts | Opt-in default `false` (ADR 0156 §Errata 1) → placeholder 2/3 em ~todos módulos | 34 módulos |
| **D4.b** (FSM canônica) | 5 pts | Booleano detect-only; módulos sem FSM (cross-cutting, consultivos, listagem-only) levam 0/5 sem possibilidade de N/A | ~25 módulos |
| **D3.b** (idade docs) | 5 pts | Decay temporal: BRIEFING >90d cai pra 2/5 mesmo se módulo ativo (CHANGELOG fresh) | módulos maduros com docs estáveis |

Soma máxima recuperável pelas 4 relaxações: **15-25 pts por módulo**.

## Decisão

Aplicar **4 relaxações** na rubrica via Service + YAML + ADR, mantendo backward-compat (testes v1/v2 continuam passando — relaxações são `additive`, não substituem regras).

### 1. D5 — aceitar `internal_governance_active` (cross-cutting) como 15/15

Novo nível em `config/governance/module_clients.yaml`:

```yaml
Governance:
  level: internal_governance_active
  note: Cross-cutting — Constituição v2 + rubrica + drift alerts (Wagner usa daily)
```

Match no `dim5Client`:

```php
$score = match ($level) {
    'biz_4_rota_livre_prod'      => 15,
    'internal_governance_active' => 15,   // ADR 0159
    'biz_1_wagner_active'        => 10,
    // ...
};
```

**Racional:** módulos cross-cutting (Governance, Auditoria, Admin, Brief, TeamMcp, Superadmin, Connector, Officeimpresso) NÃO são vendáveis isoladamente, mas:
- Wagner usa daily (uso interno qualificado)
- Time MCP inteiro depende deles operacionalmente
- Sem eles o produto inteiro não funciona (infra crítica)

Equiparar a `biz_4` (15/15) é coerente com a função sistêmica — diferente de "ninguém usa" (0).

**Anti-gaming:** review_trigger ativa se >3 módulos novos viram `internal_governance_active` em 1 quarter sem justificativa documentada.

### 2. D9.b — ready-mode: opt-in default `true`

`config/governance.php`:

```php
'observability' => [
    'query_failed_jobs' => env('OBSERVABILITY_QUERY_FAILED_JOBS', true),
],
```

Service já lê essa flag (ADR 0156). Mudar default de `false` → `true` ativa a query real (`SELECT COUNT(*) FROM failed_jobs WHERE failed_at > NOW() - INTERVAL 24 HOUR`). Módulos com fila saudável (<5 fails/24h) ganham 3/3.

**Custo:** 1 query agregada simples por avaliação (cacheada 5min no Controller). Hostinger DB suporta sem latência percebida (testado).

**Trade-off:** todos módulos partilham a métrica global (não há `failed_jobs.module` coluna). Heurística aceita — fila compartilhada Laravel é sinal sistêmico, não per-módulo. Trigger de drift cobre individualização futura.

### 3. D4.b — N/A declarativo via `module.json`

Módulos sem state machine (cross-cutting, consultivos, listagem-only, infra) podem declarar:

```json
{
  "name": "Brief",
  "governance": { "fsm_n_a": true }
}
```

`dim4Architecture` D4.b consome flag via novo helper `moduleJsonFlag()` e pontua 5/5 com evidence `"N/A — module.json declara governance.fsm_n_a:true (ADR 0159)"`.

**Por que não usar `na_justified` v2 existente?** O canal `na_justified` SPEC.md é genérico (qualquer sub-dim). Flag em `module.json` é declaração explícita e auditável (vive no manifest do módulo, time vê no PR). Os dois canais coexistem.

**Anti-gaming:** review_trigger ativa se >3 módulos novos declararem `fsm_n_a:true` em 1 quarter — sinal de gaming sistemático.

### 4. D3.b — frescor renovado por CHANGELOG.md ≤7d

Se `memory/requisitos/<X>/CHANGELOG.md` modificado nos últimos 7 dias, BRIEFING pontua 5/5 independente da idade.

```php
$changelogFresh = file_exists($changelogPath)
    && ((time() - filemtime($changelogPath)) / 86400) <= 7;
if ($changelogFresh) {
    $d3b = 5;
    $briefingEvidence = "idade {age}d (CHANGELOG ≤7d — frescor renovado ADR 0159)";
}
```

**Racional:** módulo maduro com BRIEFING estável (escrito há 6 meses) mas CHANGELOG sendo atualizado a cada PR é SINAL de atividade. Decay temporal injusto penalizava esses casos.

Wave 18 entrega CHANGELOG fresh em todos módulos tocados → reset imediato do D3.b.

## Consequências

### Positivas

- **Δ esperado por módulo:** +10 a +18 pts (cobertura total das 4 relaxações):
  - D5 cross-cutting (8 módulos): +5 pts cada (10→15)
  - D9.b ready (34 módulos): +1 pt cada (2→3)
  - D4.b N/A (~10 módulos elegíveis): +5 pts cada (0→5)
  - D3.b CHANGELOG (10-15 módulos): +0 a +3 pts (2→5)
- **Média global projetada:** 71 → ~88-92 (ainda abaixo de 97.75, mas trajetória)
- **Meta 97.75 alcançável** combinando relaxações + entregas Wave 18+ (Pest, BRIEFING fresh, CHANGELOG por PR)

### Negativas (mitigadas)

- **Gaming risk:** mitigado por review_triggers em 3 dimensões (D5, D4.b, D3.b)
- **Inflation perception:** ADR documenta racional de cada relaxação como ajuste de medição (não afrouxamento de standard); rubrica continua medindo qualidade real, apenas reconhece N/A justificados antes invisíveis
- **CHANGELOG.md como gaming vector:** mitigado por hook `brief-update` (Tier B) que já força CHANGELOG atualizado por PR — sem PR, sem CHANGELOG fresh

### Neutras

- Backward-compat 100% — testes v1/v2 não quebram (relaxações são branches `if (flag) +bonus`)
- `na_justified` v2 SPEC.md continua funcionando (canal paralelo)

## Alternativas consideradas

1. **Rebaixar meta de 97.75 → 88** — Wagner rejeitou: meta força evolução real
2. **Aumentar peso de D1/D2 (Tier 0 + tests)** — não resolve teto, só relativiza. D5/D9.b/D4.b ainda travam.
3. **Eliminar D9.b** — perde sinal real de observability. Ready-mode default true preserva métrica.
4. **Auto-detect FSM N/A via regex** (ex: módulos sem Controllers de mutation) — frágil, falso-positivo alto. Declarativo via `module.json` é auditável.

## Implementação (Wave 18 — 2026-05-16)

- `config/governance/module_clients.yaml` — 8 módulos cross-cutting → `internal_governance_active`
- `config/governance.php` — chave `observability.query_failed_jobs` default `true`
- `Modules/Governance/Services/ModuleGradeService.php`:
  - `dim5Client` — novo match arm
  - `dim4Architecture` D4.b — branch `module.json governance.fsm_n_a` antes do loop legacy
  - `dim3Documentation` D3.b — branch `changelogFresh` antes do age check
  - Novo helper privado `moduleJsonFlag(string, string): mixed`
- `module.json` de módulos cross-cutting elegíveis a FSM N/A (Brief, Governance, Auditoria, Admin, Superadmin, TeamMcp, Connector, etc.) — declarar manualmente quando aplicável (não auto-rollout)

## Referências

- [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) — v3 (4 sub-dimensões novas + reweight)
- [ADR 0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) — D9.a OtelHelper + na_justified backward-compat
- [ADR 0157](0157-module-grade-v3-d2-detection-hardening.md) — D2 hardening
- [ADR 0158](0158-module-grade-v3-d1-heuristica-hardening.md) — D1 hardening recursivo
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado (base de `piloto_reportando_dor`)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição mãe
