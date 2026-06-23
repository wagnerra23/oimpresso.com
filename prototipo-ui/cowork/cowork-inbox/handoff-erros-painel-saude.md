<!-- cowork: target: prototipo-ui/handoffs/erros-painel-saude.md -->
---
handoff_id: erros-painel-saude
tela: Plataforma/SaudeOperacional
files: [app/Support/Errors/HealthMetricsService.php, app/Http/Controllers/HealthDashboardController.php, resources/js/Pages/Saude/Index.tsx]
created_by: CC
audited_against: 0f98814eb4f8
---
## Onda E-6 (Fase 3) — Painel de saúde: os 6 indicadores

**Depende de:** E-2 (grupos), E-3 (auto-resolução), E-5 (incidentes/recorrência). **Objetivo:** a tela
que diz **se o sistema está silenciosamente quebrando antes de doer**. Lê dado real, sem fantasma.

**§10.4:** validar contra o `main`; main vence. **Espelhar `ScorecardBuilderService`/`ForjaMcpService`**
(read-only, Facts, OTel span) — não inventar.

### Design — os 6 indicadores (do Plano Sustentável)
1. **Interrupções do fundador / semana** (S0 que cortaram a "vida normal") — deve cair → ~0.
2. **Alertas / semana** — flat mesmo com tickets ↑.
3. **% auto-resolvido** (de `AutoResolver`) — sobe.
4. **Taxa de deflexão** (status/UX vs ticket aberto) — sobe.
5. **Recorrência** (de `RecurrenceGuard`) — cai → 0.
6. **MTTR de S0/S1** (de `incidents`) — estável/cai.
- `HealthMetricsService` agrega `error_groups + incidents + mcp_audit_log`. Cada métrica com sparkline
  e seta de tendência (sobe/cai/flat) — verde quando vai na direção saudável.
- Pode morar como aba na Forja (lado construtor) OU `/saude` admin. Permissão superadmin.

### NÃO FAZER
- ❌ Métrica fantasma (só Facts reais). ❌ Dado de tenant (é governança repo-wide). ❌ Cor crua (tokens DS v6).

### PRONTO QUANDO
- Os 6 indicadores leem dado real e mostram tendência; carrega via defer (sem travar 1º paint).

> Cowork read-only no git — DESIGN; código é PR revisado do [CL].
