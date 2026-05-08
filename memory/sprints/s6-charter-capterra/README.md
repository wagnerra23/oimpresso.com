# Sprint 6 — Sistema Charter-Capterra (foundation + tooling + capterra v2)

> **Status:** 📝 RASCUNHO. Pré-requisito: [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) aprovada por Wagner.
>
> **Driver:** Wagner aprova ADR 0101; Sonnet/Opus executa por fase.
>
> **Origem:** Onda C+ do plano de organização — sessão de diagnóstico de degradação 2026-05-07.

---

## Objetivo

Operacionalizar o **Sistema Charter-Capterra** ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md)): 2 níveis × 3 eixos × 4 contratos × 5 camadas de segurança. Entrega 3 fases independentes de 1 semana cada — cada fase termina com um marco utilizável.

---

## Pré-requisitos

- [ ] ADR 0101 aprovada por Wagner ("aceita")
- [ ] Cycle 03 fechado (smoke fiscal SEFAZ + boleto→NFe55 prod) — não pode competir
- [ ] Cycle 04 alocado pra S6 (~33h spread em 3 cycles de 7d, OU 1 cycle longo de 21d)

---

## As 3 fases

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
| `15-postmortem-s6.md` | métricas | 1h |

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

---

## Decisões pendentes pra Wagner ANTES de começar

> Estas precisam estar resolvidas antes do passo 1 da F1.

- [ ] Aprovar [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md) (esta governa S6)
- [ ] Confirmar 5 telas Tier A propostas em F1 (ou substituir alguma)
- [ ] Decidir alocação: F1+F2+F3 em **3 cycles distintos** (CYCLE-04, 05, 06) OU **1 cycle longo de 21d**?
- [ ] Definir owner default por charter (Wagner? Felipe pra `/sells/create`? Maíra pra `/repair/*`?)
- [ ] Aceitar ratchet baseline ou exigir charters limpos desde F1 (impacta esforço em ~3×)

---

## Próximo passo concreto

**Quando Wagner aprovar [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md):** Sonnet/Opus preenche os 15 arquivos numerados (rascunhos). Wagner revisa e dá OK por arquivo. Implementação começa em CYCLE-04 (após CYCLE-03 fechar).

📌 **Importante:** este dossier NÃO está pronto pra execução. Os 15 arquivos `.md` abaixo serão esqueletos vazios até Wagner aprovar. Wagner dirige; Sonnet/Opus aguarda ordem explícita.
