<!-- cowork: target: prototipo-ui/handoffs/erros-aprender-guard.md -->
---
handoff_id: erros-aprender-guard
tela: Plataforma/ErrorHandling
files: [app/Support/Errors/RecurrenceGuard.php, app/Models/Incident.php, database/migrations/xxxx_create_incidents_table.php, .github/workflows/incident-guard.yml]
created_by: CC
audited_against: 0f98814eb4f8
---
## Onda E-5 (Fase 3 · Aprender) — Post-mortem vira guard; o erro não volta

**Depende de:** E-1, E-2. **Objetivo:** a Lei 1 do Plano — **toda classe de erro morre uma vez**. Um
S0/S1 só fecha quando produz um guard que impede a recaída. É o que faz o backlog encolher com o tempo.

**§10.4:** validar contra o `main`; main vence. **Integrar com a sentinela/gates que já existem** (não
criar stack nova).

### Design
- Tabela `incidents`: `dedup_key · severity · opened_at · resolved_at · mttr · guard_ref (obrigatório
  pra fechar S0/S1) · postmortem_url (ADR/sessão)`.
- **Fechamento exige `guard_ref`** — um teste/guard/regra que cobre aquela classe. Sem ele, o incidente
  não fecha (força o aprendizado).
- **`RecurrenceGuard`**: se um `dedup_key` já resolvido **reaparece** → o guard falhou → realerta como
  S1 marcado "recorrência" (a métrica "recorrência" do painel sai daqui).
- Post-mortem entra na **memória institucional** (ADR/sessão — o loop que já existe) via o caminho
  Cowork→Code, não à mão.
- Liga o guard na **sentinela/governance gates** existentes pra cobrar em CI.

### NÃO FAZER
- ❌ Fechar S0/S1 sem `guard_ref`. ❌ Stack de incidente nova fora do que existe. ❌ Numerar ADR (é [CL]/[W]).

### PRONTO QUANDO (Pest)
- S0/S1 não fecha sem `guard_ref`.
- `dedup_key` resolvido que reaparece → realerta "recorrência".
- `mttr` calculado por incidente; alimenta o painel (E-6).

> Cowork read-only no git — DESIGN; código é PR revisado do [CL].
