---
slug: 0154-module-grade-v2-na-justificado
number: 0154
title: "Rubrica `module-grade-v2` — regra N/A justificado pra dimensões inaplicáveis por design"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-16
module: Governance
quarter: 2026-Q2
tags: [governance, qualidade, audit, dashboard, rubrica, na-justificado, dim-5-pesos-100]
supersedes: []
supersedes_partially: [0153]
superseded_by: []
related: [0093, 0094, 0101, 0105, 0143, 0153]
pii: false
review_triggers:
  - Quando média projeto bater 75+ (rubrica v2 saturou — precisa v3)
  - Quando mais de 5 módulos declararem N/A em ≥3 dimensões (sinal que rubrica precisa mais granularidade)
  - Quando dimensão D6 LGPD/Performance/ROI for incluída de fato
  - Quando Service detectar tentativa de gaming (módulo declara N/A sem ADR referenciada)
---

# ADR 0154 — Rubrica `module-grade-v2` — regra N/A justificado pra dimensões inaplicáveis por design

## Contexto

[ADR 0153](0153-module-grade-rubrica-v1.md) aceita e LIVE em prod desde 2026-05-16. Comando `php artisan module:grade --all` roda diário 06:00 BRT. Primeira execução real revelou problema sistemático:

**Módulos perdem pontos por design intencional, não por gap real.**

Casos catalogados (execução prod 2026-05-16):

| Módulo | Dim perdida | Razão real | Score injustamente penalizado |
|---|---|---|---|
| **Governance** | D4.b FSM | Módulo de governança não tem state machine — gere ADRs/grades, não fluxo de negócio | −5 pts |
| **Governance** | D5 biz=4 ROTA LIVRE | Wagner-only por design (Constituição enforcer cross-tenant) — ROTA LIVRE NÃO USA | −12 pts (cai de 15 pra 3) |
| **Brief** | D3.a SPEC US-XXX | Brief é MCP tool consumida via `brief-fetch`, não tem US cliente — não cabe SPEC formato Jira | −5 pts |
| **TeamMcp** | D5 biz=4 | Tokens MCP servem time interno (W/M/F/L/E), não cliente externo — D5 não aplica | −12 pts |
| **ADS** | D5 biz=4 | Dormente per [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) até cliente reportar dor automation | −12 pts |
| **Superadmin** | D5 biz=4 | Cross-tenant intencional (Constituição Art. 6 — superadmin opera fora multi-tenant) | −12 pts |
| **Connector** | D4.b FSM | Connector é gateway sync entre Modules (sem fluxo de negócio próprio) | −5 pts |
| **MemCofre** | D5 biz=4 | Cofre de senhas é per-user, não cliente externo | −12 pts |

**Resultado prático:** Governance pontuou 85 (Excelente) mas seria 95+ se D4.b+D5 fossem N/A justificado. ADS pontuou 45 (Médio) por motivo dormente declarado em ADR canon — penalidade dupla.

Conversa Wagner 2026-05-16 catalogou: "tem dimensão que NÃO se aplica por design — não devia descer a nota". Rubrica v1 não tem mecanismo pra essa distinção. Resultado: ranking distorcido (módulos infraestruturais ficam abaixo de módulos cliente-facing por design, não por qualidade).

## Decisão

Aceitar **`module-grade-v2`** como evolução incremental da rubrica v1. Mantém 5 dimensões + pesos 100. Adiciona **regra N/A justificado** + opção de D6 bônus.

### Regra principal: N/A justificado documentado

Módulo pode declarar dimensões inaplicáveis via **frontmatter `SPEC.md`**:

```yaml
---
module: Governance
na_justified:
  D4.b:
    razao: "Módulo de governança não tem state machine — gere ADRs/grades, não fluxo de negócio"
    adr_ref: 0094  # Constituição v2 — Governance enforcer
  D5:
    razao: "Wagner-only cross-tenant por design — Constituição Art. 6 superadmin"
    adr_ref: 0094
---
```

`ModuleGradeService::gradeModule()` lê SPEC.md e, se encontrar `na_justified[X]`, atribui **score máximo da dimensão** (não zero, não pula).

Output breakdown mostra explicitamente:

```
D4. Maturidade arquitetura: 15/20
  ✓ D4.a Services ratio 0.4: 6/6
  ✓ D4.b FSM: N/A justificado (Governance ADR 0094 — sem state machine)
  ✓ D4.c Inertia ratio: 5/5
  ✓ D4.d AuditLog+OTel: 4/4
```

### Restrições anti-gaming

1. **Máximo 3 dimensões N/A por módulo.** 4+ vira erro `ModuleGradeException::TooManyNaDimensions` (módulo provavelmente não devia existir ou rubrica não cabe).
2. **`adr_ref` obrigatório.** Service valida que o ADR existe em `memory/decisions/` E que seu frontmatter `module:` referencia o módulo OU é Constituição (0094) OU está na lista `related`. Sem ADR válida → fallback score zero (não N/A).
3. **Auditável em PR.** Skill `module-completeness-audit` (Tier B) checa que `na_justified` no SPEC.md tem ADR válida.
4. **`ModuleGradeService::audit($module)`** lista N/A declaradas + ADRs + datas — Wagner enxerga drift se módulo abusa.

### Mecânica completa

1. Service carrega `memory/requisitos/<X>/SPEC.md` → parse frontmatter YAML
2. Pra cada dimensão D1.a..D5, checa se está em `na_justified`:
   - **Sim + ADR válida** → score = max da sub-dimensão, marcado `na_justified` no breakdown
   - **Sim + ADR inválida/ausente** → score zero + warning log + telemetry `module.grade.na.invalid_adr`
   - **Não** → coleta automática normal (grep/find/file_exists)
3. Soma pondera normal (mesmos pesos 30/20/15/20/15)
4. Output: nota total + breakdown 5 dims + N/A justificadas listadas separadas + gaps reais top 3

### D6 opcional (bônus Constituição v2 — não obrigatório v2)

`module-grade-v2` **NÃO** adiciona D6 como dimensão obrigatória — fica explicitamente postergada pra v3 (quando dados objetivos existirem). Mas Service expõe **bonus opcional +5 pts** quando módulo satisfaz:

- (a) Tem ADR mãe própria (lifecycle `ativo` + status `accepted`)
- (b) ZERO violation no último `governance-gate.yml` 30d
- (c) Atualizou `BRIEFING.md` nos últimos 30d

Bonus não normaliza pra 100 — adiciona até 105. Visualmente mostra "92 + 5 bonus = 97 (Excelente+)". Anti-gaming: bonus computa só se todas 3 condições verdade (não parcial).

### Comparativo das opções consideradas

| Opção | Mecânica | Pros | Cons |
|---|---|---|---|
| **A. N/A score máximo (RECOMENDADA)** | SPEC.md declara N/A; Service atribui max da dim | Simples, audit trail explícito via ADR, anti-gaming via limite 3 + ADR-ref obrigatória | Módulo "ganha" pontos por não fazer algo — precisa boa documentação |
| **B. Peso elastico (redistribui)** | Dim N/A → peso redistribuído pras outras dims proporcional | Total sempre 100, dim N/A não conta | Difícil interpretar score absoluto; comparação entre módulos vira opaca (mesma nota 80 mas com pesos efetivos diferentes) |
| **C. D6 bônus +10 Compliance** | Adiciona dimensão nova totalizando 110 → normalizada 100 | Cobre Constituição v2 explicitamente | Não resolve problema N/A; ainda penaliza módulos por dim inaplicável; mexe nos pesos v1 (rompe append-only) |

**Recomendação: opção A (N/A score máximo) com limite 3 dims + ADR-ref obrigatória.** Opção C entra parcialmente como **bonus opcional** sem afetar pesos v1.

## Justificativa

**Por que opção A:**

- **Audit trail natural** — toda N/A tem ADR canon referenciada. Felipe/Maiara veem WHY direto no breakdown sem perguntar Wagner.
- **Backward-compat 100%** — módulos sem `na_justified` no SPEC.md rodam idêntico ao v1. Sem breaking change.
- **Anti-gaming via 3 caminhos** — limite 3 dims (estrutural) + ADR válida (audit) + skill `module-completeness-audit` (process gate).
- **Simples mental model** — "se NÃO se aplica, declara com ADR; senão coleta normal". Wagner explicou em 1 frase.

**Por que NÃO opção B (peso elastico):**

- Quebra comparabilidade — score 80 vira opaco (peso efetivo varia por módulo). Goal CYCLE "média 41 → 60" perde sentido.
- Implementação complexa — redistribuir pesos exige normalização per-módulo, difícil debugar.
- Não tem audit trail — dim "desaparece" do breakdown sem ADR justificando.

**Por que NÃO opção C como dim obrigatória v2:**

- Rompe contrato append-only v1 (pesos mudariam). ADR 0153 explicitamente diz "v2 vira ADR 0154 append-only" — append, não rewrite.
- Dados objetivos D6 (governance-gate violations, BRIEFING mtime) ainda em coleta — viraria manual primeiro.
- Postergar D6 obrigatória pra v3 quando médio projeto bater 70+ é honesto.
- **Como bonus opcional sim** — porque não muda denominador (100), só permite valor "Excelente+" pra módulos modelo.

**Quando reabrir v2:**

- Se >5 módulos declararem 3+ N/A (rubrica não cabe — precisa dim split ou v3)
- Se gaming detectado (módulo declara N/A sem real motivo — Service deve flagar)
- Se média projeto saturar em 75+ — D6 LGPD/Performance entra obrigatória v3

## Consequências

**Positivas:**

- **Ranking justo** — Governance/ADS/Brief/TeamMcp/Superadmin/MemCofre/Connector deixam de ser injustamente penalizados (~8 módulos saem de "Médio" pra "Bom/Excelente")
- **Conversa fica honesta** — "ADS pontua 45 porque dormente" vira "ADS pontua 75 com D5 N/A justificado (dormente per ADR 0105)" — mostra a decisão, não o sintoma
- **Audit trail forte** — toda N/A linka ADR. Compliance Constituição v2 §5 SoC brutal atendido
- **Onboarding melhor** — Felipe vê "Governance 95 (Excelente)" e entende WHY pelas notas N/A — não precisa Wagner explicar
- **Goal CYCLE fica mensurável de novo** — "média 41 → 65" vira realista após reranking
- **Bonus D6 opcional** dá pista de evolução futura sem comprometer hoje

**Negativas / Trade-offs:**

- **Carga inicial Wagner** — declarar N/A em 8+ SPEC.md módulos (~30min one-shot). Mitigação: batch task `governance:bootstrap-na-justified` que sugere N/A baseado em padrões detectados (D4.b FSM em módulos infra, D5 em módulos cross-tenant) — Wagner aprova batch
- **Risco gaming** — se enforcement falhar, módulo pode declarar N/A spuriously. Mitigação: limite 3 + ADR válida obrigatória + skill audit
- **Service mais complexo** — parse YAML SPEC.md + validar ADR ref + logging telemetry. ~100 LOC adicional. Mitigação: testes Pest cobrindo edge cases (ADR ausente, ADR inválida, 4+ N/A, ADR errada de módulo)
- **`SPEC.md` vira load-bearing pra Grade Service** — módulo sem SPEC.md continua funcionando (sem N/A possível) mas perde flexibilidade. Aceita-se — incentivo a criar SPEC.md

**Riscos mitigados:**

- ⛔ "Ranking distorcido por design intencional" → N/A justificado resolve
- ⛔ "Gaming via N/A sem motivo" → limite 3 + ADR validation + audit em PR
- ⛔ "Drift entre Service e SPEC.md" → telemetry `module.grade.na.invalid_adr` + cron daily flag se inválido
- ⛔ "Breaking change v1 → v2" → backward-compat 100% (módulo sem na_justified roda v1 idêntico)

## Plano de migração v1 → v2

### Fase 1 — Service backward-compat (Sprint atual, ~4h)

1. Adicionar `parseNaJustified(string $module): array` em `ModuleGradeService` — lê SPEC.md frontmatter
2. Adicionar `validateNaAdr(int $adrNumber, string $module): bool` — checa ADR existe + module match
3. Modificar `gradeDimension(string $dim, ...)` pra consultar N/A antes de coletar
4. Output JSON inclui campos `na_justified_dims[]` + `na_justified_total_pts`
5. Pest: `ModuleGradeNaJustifiedTest` cobrindo (a) N/A válida atribui max, (b) ADR inválida fallback zero, (c) 4+ N/A throws, (d) backward-compat sem na_justified roda v1 idêntico

### Fase 2 — Batch SPEC.md bootstrap (Wagner aprova, ~1h)

1. Comando `php artisan governance:bootstrap-na-justified [--dry-run]` detecta candidates baseado em heurística:
   - Módulos infra-only (sem `current_stage_id` em tabelas) → sugere `D4.b: N/A FSM` ref Constituição
   - Módulos cross-tenant (Superadmin/Governance/TeamMcp) → sugere `D5: N/A cliente externo` ref 0094
   - Módulos dormentes per [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) → sugere `D5: dormente`
2. Output: diff proposto pra SPEC.md de cada módulo. Wagner aprova batch.
3. Aplica via PR único `chore(governance): bootstrap na_justified em SPEC.md de 8 modulos`

### Fase 3 — UI drill-down v2 (próximo cycle, ~2h)

1. `/governance/module-grades/{module}` mostra N/A justificadas separadas em "Dimensões N/A (não penalizadas)" com ADR linkada clickable
2. Tabela `/governance/module-grades` adiciona coluna "N/A count" pra detectar abuso visual
3. Sparkline 90d continua igual (score histórico)

### Fase 4 — Habilitar D6 bonus opcional (decisão futura)

Após 30d uso v2 estável, Wagner decide se ativa bonus +5. Trivial — Service já calcula, é só `config('governance.grade.bonus_enabled', false)`.

## Test plan

| Cenário | Assertion |
|---|---|
| Módulo sem SPEC.md | Roda v1 idêntico (score igual ao baseline) |
| SPEC.md sem `na_justified` | Roda v1 idêntico |
| `na_justified: { D4.b: { razao: "...", adr_ref: 0094 } }` | D4.b atribui 5/5 + breakdown mostra "N/A justificado (ADR 0094)" |
| `na_justified` com ADR inexistente (0999) | D4.b atribui 0 + warning log + telemetry incrementa |
| `na_justified` com ADR de outro módulo (0143 FSM Sells em SPEC Governance) | D4.b atribui 0 a menos que ADR seja Constituição (0094) ou em `related` |
| 4+ dims em `na_justified` | Throws `ModuleGradeException::TooManyNaDimensions` |
| Bonus condições (a)+(b)+(c) verdade | Score += 5 (máximo 105) |
| Bonus condições parcial (só a+b) | Sem bonus |
| Cross-tenant isolation | Tests biz=1 vs biz=99 não interferem (Service é per-module, não per-business) |
| `audit($module)` | Lista todas N/A com ADR ref + data + razão extraída |

## Referências

- [ADR 0153](0153-module-grade-rubrica-v1.md) — Rubrica v1 (mãe — parcialmente superseded por v2)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (D1 pesa máximo)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Governance enforcer — referência padrão pra N/A em módulos infra)
- [ADR 0101](0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1 nunca cliente
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado (D5 dormente padrão)
- [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline canônico (D4.b — N/A em módulos infra)
- Execução `module:grade --all` 2026-05-16 — evidência empírica do problema
- `memory/sessions/2026-05-15-inventario-pest-coverage.md` — auditoria origem
- `memory/handoffs/2026-05-16-0000-pest-cobertura-34de34-modulos-waves-AB.md` — handoff prévio

---

**Próxima ação Wagner:** revisar opção recomendada (N/A score máximo, limite 3, ADR-ref obrigatória) e plano de migração 4 fases. Ao marcar `status: accepted` → Fase 1 Service implementa em ~4h + Pest cover edge cases + PR canônico.
