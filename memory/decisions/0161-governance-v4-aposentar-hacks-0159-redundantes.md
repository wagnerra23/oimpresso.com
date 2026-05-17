---
slug: 0161-governance-v4-aposentar-hacks-0159-redundantes
number: 0161
title: "Governance v4 — aposentar 3 dos 4 hacks ADR 0159 redundantes com Scoped Scorecards"
type: adr
status: accepted
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
proposed_at: 2026-05-16
review_at: 2026-08-16
module: Governance
quarter: 2026-Q2
tags: [governance, deprecation, rubrica, v4, scoped-scorecards, hacks]
supersedes: []
supersedes_partially: [0159]
superseded_by: []
related: [0159, 0160, 0155, 0156, 0157, 0158, 0153, 0154, 0094]
pii: false
review_triggers:
  - Quando `governance.v4_enabled=true` virar default em prod (Hostinger biz=1 + biz=4) → remover branches v3 hack do Service (dead code cleanup)
  - Quando OTel collector ativo CT 100 → resolver o 4º hack (D9.b ready-mode) que ainda permanece
  - Quando >3 módulos cross-cutting forem reclassificados manualmente pra outro bucket → revalidar mapeamento bucket→pesos
---

# ADR 0161 — Governance v4: aposentar 3 dos 4 hacks ADR 0159 redundantes com Scoped Scorecards

## Contexto

[ADR 0159](0159-module-grade-v3-errata-meta-97-realismo.md) (Wave 18, 2026-05-16) introduziu **4 hacks emergenciais** na rubrica `module-grade-v3` pra acomodar heterogeneidade entre módulos vendáveis (Vestuario, ComunicacaoVisual) e módulos cross-cutting infra (Governance, Auditoria, Admin, Brief, TeamMcp, Superadmin, Connector, Officeimpresso). O alvo: viabilizar meta global 97.75/100 sem castigar módulos cuja função sistêmica é diferente de "produto vendável".

Os 4 hacks:

1. **D5 `internal_governance_active`** — novo nível em `module_clients.yaml` equivale a `biz_4` em pontuação (15/15)
2. **D4.b `governance.fsm_n_a:true`** — flag em `module.json` zera o castigo de FSM ausente
3. **D3.b CHANGELOG ≤7d** — frescor renovado por CHANGELOG sobrescreve decay de BRIEFING >90d
4. **D9.b ready-mode** — `observability.query_failed_jobs` default `true` ativa query real `failed_jobs` agregada

[ADR 0160](0160-governance-v4-scoped-scorecards-bucket-aware.md) (Wave 19) introduziu **Scoped Scorecards bucket-aware**: cada módulo é classificado em um bucket (`vendable_product`, `cross_cutting_infra`, `vertical_specialization`, `consultive_listing`, `external_integration`) e o score-as-code aplica pesos D1..D9 específicos por bucket. Bucket `cross_cutting_infra` por padrão tem peso D5 alto sem precisar do truque YAML.

**Diagnóstico Wave 24 (2026-05-16):**

Com Scoped Scorecards (v4) ativo, **3 dos 4 hacks viraram redundantes**:

| Hack ADR 0159 | Substituído por (v4) | Redundante? |
|---|---|---|
| D5 `internal_governance_active` | Bucket `cross_cutting_infra` com peso D5 próprio (não precisa nivelar com biz_4) | ✅ SIM |
| D4.b `governance.fsm_n_a:true` | Bucket-aware: cross_cutting/consultive/external removem D4.b do scorecard OU integram flag ao bucket | ✅ SIM (flag continua válida, semântica integrada) |
| D3.b CHANGELOG ≤7d | Dimensões bucket-specific V5.c/C4.b/F5.c medem frescor de forma específica por bucket | ✅ SIM |
| D9.b `observability.query_failed_jobs` ready-mode | Sem OTel collector ativo no CT 100, query agregada Hostinger ainda é o sinal disponível | ❌ NÃO (permanece) |

Continuar carregando os 3 hacks redundantes em v4 = código morto + risco gaming + complexidade desnecessária.

## Decisão

**Aposentar 3 dos 4 hacks ADR 0159** quando `governance.v4_enabled=true` (Scoped Scorecards ativo), mantendo back-compat em v3 (Service v3 legacy continua funcionando com hacks).

### Aposentadoria 1 — D5 `internal_governance_active` → bucket `cross_cutting_infra`

- **v3 (legacy):** match arm `'internal_governance_active' => 15` em `dim5Client()` permanece pra back-compat
- **v4 (ativo):** Service v4 ignora o nível; classificação por bucket determina peso D5 nativo
  - Módulos cross_cutting_infra recebem peso D5 conforme score-as-code do bucket (não via YAML hack)
  - YAML `module_clients.yaml` continua sendo fonte de verdade pra classificação de cliente real, mas categoria "internal" passa a ser propriedade do bucket, não nível do YAML
- **Migration path:** 8 módulos hoje classificados `internal_governance_active` migram pra bucket `cross_cutting_infra` no `bucket_assignments.yaml` (ADR 0160). YAML `module_clients.yaml` pode reverter pra `none` ou bucket-derived (sem perda)

### Aposentadoria 2 — D4.b `governance.fsm_n_a:true` semântica integrada ao bucket

- **v3 (legacy):** branch `if ($fsmNa) { $d4b = 5; }` em `dim4Architecture()` permanece pra back-compat
- **v4 (ativo):** flag `module.json governance.fsm_n_a:true` continua válida mas tratada como **declaração de bucket-fit**, não bonus pontual:
  - Buckets `cross_cutting_infra`, `consultive_listing`, `external_integration` por padrão removem D4.b do scorecard (peso 0)
  - Flag fica como sinal canônico documental no manifest (auditoria)
  - Wave próxima: validar que todos módulos cross-cutting com `fsm_n_a:true` migrados pra bucket correto (sem perda)

### Aposentadoria 3 — D3.b CHANGELOG ≤7d → V5.c/C4.b/F5.c bucket-specific

- **v3 (legacy):** branch `if ($changelogFresh) { $d3b = 5; }` em `dim3Documentation()` permanece pra back-compat
- **v4 (ativo):** dimensões bucket-specific medem frescor de forma adequada por tipo de módulo:
  - `vendable_product` → V5.c "Capterra refresh ≤ quarter"
  - `cross_cutting_infra` → C4.b "Constituição/ADR review ≤ quarter"
  - `external_integration` → F5.c "Contrato API refresh ≤ semestre"
- BRIEFING.md continua canônico per-módulo (regra "mexeu, registra"); decay temporal acoplado ao bucket (não regra única 7d global)

### Hack 4 PERMANECE — D9.b ready-mode até OTel collector ativo

- `observability.query_failed_jobs=true` continua sendo o sinal real disponível em Hostinger
- Sem OTel collector CT 100 (planejado wave próxima), não há alternativa estruturada
- Será resolvido em ADR futura quando OTel collector ativo expuser `module_health_p99` per-módulo

## Consequências

### Positivas

- **Limpeza conceitual:** v4 não acumula hacks de v3; Scoped Scorecards modela heterogeneidade nativamente
- **Anti-gaming:** menos canais de "escape" (3 hacks fechados); módulo só ganha pontuação coerente com bucket real
- **Manutenibilidade:** quando `governance.v4_enabled=true` virar default em prod, branches v3 viram dead code → cleanup PR simples
- **Auditoria documental preservada:** `module.json governance.fsm_n_a:true` permanece como sinal canônico (não some)

### Negativas (mitigadas)

- **Dual-mode complexidade temporária:** Service mantém duas trilhas (v3 com hacks + v4 sem hacks) até cleanup pós-canary. Mitigado por marcadores `@deprecated since v4 (ADR 0161)` explícitos
- **Migration manual módulo→bucket:** 8 módulos `internal_governance_active` precisam ser reclassificados em `bucket_assignments.yaml` (Wave 19). Mitigado: lista finita + documentada
- **YAML `module_clients.yaml` semântica muda em v4:** nível "internal" deixa de pontuar D5 diretamente. Mitigado: documentar em CHANGELOG Governance

### Neutras

- Back-compat 100%: v3 tests existentes continuam passando (branches hack ainda no código)
- Wagner aprova `governance.v4_enabled=true` em prod **canary CT 100 antes Hostinger** — rollout gradual
- Trigger de review monitora gaming: >3 módulos reclassificados manualmente sem justificativa = sinal

## Alternativas consideradas

1. **Remover hacks v3 imediatamente** — rejeitada: quebra Service em prod sem canary; viola "append-only código gradual"
2. **Manter hacks v3 também em v4 (cumulativo)** — rejeitada: v4 vira v3+v4, dobra superfície de gaming, anula benefício Scoped Scorecards
3. **Aposentar 4/4 hacks (incluindo D9.b)** — rejeitada: sem OTel collector ativo, D9.b perde sinal real; piora medição
4. **Marcar 3 hacks `@throws DeprecatedException` se chamados em v4** — rejeitada: melhor silenciar (no-op em v4) que quebrar code path; PHPDoc `@deprecated` é canal idiomático

## Implementação (Wave 24 — 2026-05-16)

- **`Modules/Governance/Services/ModuleGradeService.php`** — marcadores PHPDoc `@deprecated since v4 (ADR 0161)` nos blocos:
  - `dim5Client()` match arm `internal_governance_active` (linha ~833)
  - `dim4Architecture()` branch `$fsmNa` (linhas ~742-746)
  - `dim3Documentation()` branch `$changelogFresh` (linhas ~649-665)
- **`memory/decisions/_INDEX-LIFECYCLE.md`** — entry 0161 no Bloco 9; entry 0159 ganha anotação `(parcial 0160, 0161)`
- **Service v4 (Wave próxima):** quando `config('governance.v4_enabled')===true`, branches hack viram no-op (return null/skip); pesos vêm exclusivamente do bucket via `ScopedScorecardService` (ADR 0160)
- **CHANGELOG Governance** — registrar deprecação 3/4 hacks (regra "mexeu, registra")
- **Migration módulos→bucket** — Wave 19 (ADR 0160) — fora do escopo desta ADR

## Referências

- [ADR 0159](0159-module-grade-v3-errata-meta-97-realismo.md) — 4 hacks rubrica v3 (esta ADR aposenta 3/4)
- [ADR 0160](0160-governance-v4-scoped-scorecards-bucket-aware.md) — Scoped Scorecards bucket-aware (v4 base)
- [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) — v3 sub-dimensões + reweight
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 mãe (princípio §5 SoC brutal + §4 loop fechado por métrica)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal (D5 base)
