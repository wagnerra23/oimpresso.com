# Sprint 6 — Sistema Charter-Capterra (foundation + tooling + capterra v2)

> **Status:** ✅ ADR 0101 aprovada 2026-05-07. Sprint S6 agora aguarda só Cycle 03 fechar pra começar.
>
> **Driver:** Wagner aprova fase a fase; Sonnet/Opus executa por fase.
>
> **Origem:** Onda C+ do plano de organização — sessão de diagnóstico de degradação 2026-05-07.

---

## Objetivo

Operacionalizar o **Sistema Charter-Capterra** ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md)): 2 níveis × 3 eixos × 4 contratos × 5 camadas de segurança. Entrega **4 fases** independentes de ~1 semana cada (~39h total) — cada fase termina com um marco utilizável.

---

## Pré-requisitos

- [x] ADR 0101 aprovada por Wagner ✅ 2026-05-07
- [ ] Cycle 03 fechado (smoke fiscal SEFAZ + boleto→NFe55 prod) — não pode competir
- [ ] Cycle 04 alocado pra S6 (~39h spread em 4 cycles de 7d, OU 1 cycle longo de 28d)

---

## As 4 fases

### F1 — Foundation (sem 1, ~12h)

**Marco:** 5 charters Tier A vivos + Pest GUARD rodando em CI.

| Arquivo | Tipo | Esforço |
|---|---|---|
| `01-template-charter.md` | template canon | 1h |
| `02-charter-fetch-tool.md` | spec tool MCP | 2h |
| `03-charter-pest-runner.md` | spec teste Pest | 2h |
| `04-five-charters-prod.md` | 5 charters Tier A escritos | 5h |
| `05-ci-gate-charter.md` | GitHub Action workflow | 2h |

**5 telas Tier A propostas** (em prod, com KPI claro):

1. `/repair/dashboard` (DONE em CYCLE-02; rascunho de charter já existe — bom 1º caso)
2. `/repair/jobsheet` (cockpit pattern, alta interação)
3. `/financeiro/extrato` (multi-tenant Tier 0 sensível)
4. `/sells/create` (cliente ROTA LIVRE — alta sensibilidade, 99% volume)
5. `/ads/admin/skills` (DONE em CYCLE-02; admin Superadmin)

### F2 — Tooling (sem 2, ~10h)

**Marco:** `charter:health` daily 06:00 BRT alertando drift.

| Arquivo | Tipo | Esforço |
|---|---|---|
| `06-charter-audit-command.md` | artisan command | 3h |
| `07-charter-write-skill.md` | skill + agent | 3h |
| `08-skill-charter-first-ativacao.md` | promove Tier A | 1h |
| `09-charter-health-cron.md` | scheduler 06:00 BRT | 2h |
| `10-ratchet-baseline.md` | dívida aceita | 1h |

### F3 — Capterra v2 (sem 3, ~11h)

**Marco:** RecurringBilling re-auditado em 3 eixos (features + ux + automação).

| Arquivo | Tipo | Esforço |
|---|---|---|
| `11-skill-comparativo-v2.md` | spec evolução skill | 3h |
| `12-capterra-ficha-v2-template.md` | template 3 eixos | 1h |
| `13-conversao-5-fichas.md` | RB+Fin+NfeBrasil+Repair+Project | 4h |
| `14-inventario-v2-recurringbilling.md` | 1ª prova de conceito | 2h |
| `15-fim-f3-checkpoint.md` | checkpoint | 1h |

### F4 — Performance Testing + Automação (sem 4, ~6h)

**Marco:** dashboard `/copiloto/admin/qualidade` mostra 6 métricas verdes em ratchet baseline.

| Arquivo | Tipo | Esforço |
|---|---|---|
| `16-six-metrics-spec.md` | spec M1–M6 | 1h |
| `17-pest-aggregators.md` | M1+M2+M3 testes Pest | 2h |
| `18-goal-drift-detector.md` | M4 (mcp_audit_log query + alerta) | 1h |
| `19-charter-evolve-skill.md` | L2 propose (skill + agent worktree) | 1h |
| `20-postmortem-s6-baseline.md` | medição inicial + alvos 30d | 1h |

**6 métricas (Pest agregadores + dashboard `/copiloto/admin/qualidade`):**

| # | Métrica | Pest test | Alvo |
|---|---|---|---|
| M1 | Token /sessão (charter vs sem) | `TokenEconomyTest` | -50% |
| M2 | Charter Pest GUARD pass rate | CI agg | ≥95% |
| M3 | Charter coverage Tier A | `charter:audit` | ≥80% |
| M4 | Goal drift rate (sessões fora do scope) | `GoalDriftTest` | <5% |
| M5 | Drift detector latency (PR→alerta) | GH Action timing | <2 min |
| M6 | Anti-hallucination ratchet (Non-Goals violados em prod) | `charter:health` daily | ≤baseline |

**3 níveis automação (cresce em compromisso):**

- **L1 — Detect** (~3h, 80% pronto via F2-F3): `charter:health` daily + alerta drift + skill `comparativo` v2
- **L2 — Propose** (~6h): skills `charter-evolve` + `capterra-evolve` geram PR draft pra Wagner aprovar — **nunca auto-merge**
- **L3 — Self-improve** (~12h, futuro pós-S6): loop completo Capterra → US → agent worktree → charter → métrica → fecha. Só P2/P3 + rollback automático se métrica piorar

---

## Métricas de sucesso

(mede 30 dias após F3 — fonte ADR 0101 §Métricas)

| Métrica | Como mede | Alvo |
|---|---|---|
| Charters Tier A escritos | `find resources/js/Pages -name "*.charter.md"` | ≥10 |
| Cobertura Pest GUARD por charter | linhas charter / linhas test | ≥80% |
| `charter:health` alertas/dia | `mcp_audit_log` | <2 |
| Token médio /sessão tela com charter | `mcp_audit_log` agg | -50% vs sem charter |
| Skill `charter-first` ativações/sessão tela | hook telemetry | ≥70% |
| Capterra v2 fichas convertidas | grep `automation_targets:` em fichas | 5/5 |
| Drift detectado em PR antes de merge | CI logs | ≥90% dos charter violations |

---

## Riscos

- 🔴 **Charter Tier A sem GUARD = falsa segurança.** Mitigação: F1 entrega obrigatoriamente 5 charters + 5 baterias de Pest test (sem teste = charter não merge)
- 🟠 **`charter:health` em prod pode flapear** se métrica for sensível demais. Mitigação: ratchet baseline aceita dívida na ativação; ajuste em F3
- 🟡 **Skill `charter-write` (gerar draft de charter via agent) pode produzir charter ruim.** Mitigação: Wagner sempre revisa antes de merge; skill é assistente, não autor
- 🟡 **Capterra v2 com 3 eixos triplica curadoria de FICHA** — Wagner pode resistir. Mitigação: F3 entrega templates+migration script; eixos UX+Auto inicialmente vazios, preenchidos sob demanda
- 🟢 **Token economia pode não materializar** se sessões raramente tocam telas com charter. Mitigação: monitorar e ajustar Tier A coverage
- 🟡 **F4 métricas podem flapar** sem baseline (M4/M6 dependem de telemetria histórica). Mitigação: F4 começa coletando 7d antes de ativar alertas; ratchet conservador
- 🟡 **L3 self-improve pode regredir** se métrica piorar e rollback falhar. Mitigação: L3 fica fora de S6 (futuro), só ativa após L1+L2 provarem ROI

---

## Rollback

Se algo quebrar pós-rollout (por fase):

### F1 falha
1. `git revert` dos charters em `resources/js/Pages/**/*.charter.md`
2. Disable do GitHub Action `.github/workflows/charter-gate.yml`
3. ADR 0101 vira `status: paused`, lessons documenta motivo

### F2 falha
1. Skill `charter-first` volta pra Tier A dormente (frontmatter)
2. Cron `charter:health` removido do scheduler
3. F1 segue funcionando standalone (charters + Pest manual)

### F3 falha
1. Skill `comparativo` revert pra v1.0 (só features)
2. Fichas v2 ficam como `*.v2.md` paralelo até estabilizar
3. F1+F2 seguem funcionando

### F4 falha
1. Pest aggregators viram `skip()` (não falham CI)
2. Dashboard `/copiloto/admin/qualidade` mostra colunas vazias
3. Skill `charter-evolve` desativada (volta sendo proposta humana)
4. F1+F2+F3 seguem funcionando

---

## Decisões pendentes pra Wagner ANTES de começar

> Estas precisam estar resolvidas antes do passo 1 da F1.

- [x] Aprovar [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) ✅ 2026-05-07
- [ ] Confirmar 5 telas Tier A propostas em F1 (ou substituir alguma)
- [ ] Decidir alocação: F1+F2+F3+F4 em **4 cycles distintos** (CYCLE-04, 05, 06, 07) OU **1 cycle longo de 28d**?
- [ ] Definir owner default por charter (Wagner? Felipe pra `/sells/create`? Maíra pra `/repair/*`?)
- [ ] Aceitar ratchet baseline ou exigir charters limpos desde F1 (impacta esforço em ~3×)

---

## Próximo passo concreto

**ADR 0101 ✅ aprovada 2026-05-07.** Próximo passo: aguardar CYCLE-03 fechar (smoke fiscal SEFAZ + boleto→NFe55), depois Sonnet/Opus preenche os 20 arquivos numerados (rascunhos) por fase. Wagner revisa e dá OK por arquivo. Implementação começa em CYCLE-04.

📌 **Importante:** os 20 arquivos `.md` numerados (F1-F4) ainda são esqueletos a preencher. Wagner dirige fase a fase; Sonnet/Opus aguarda ordem explícita.
