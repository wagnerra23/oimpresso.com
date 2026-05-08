# 04 — 5 charters Tier A em prod

> **Lista canônica de telas que ganham Page Charter em F1.**
> Tier A = produção, alto tráfego, KPI claro, ROI imediato em governança.

---

## Os 5 charters Tier A

| # | Tela | Path Inertia | Status charter | Owner |
|---|---|---|---|---|
| 1 | `/repair/dashboard` | `resources/js/Pages/Repair/Dashboard/Index.tsx` | ✅ existe ([ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md)) | wagner |
| 2 | `/repair/job-sheet` | `resources/js/Pages/Repair/JobSheet/Index.tsx` | ✅ F1 (este sprint) | wagner |
| 3 | `/financeiro/extrato/{id}` | `resources/js/Pages/Financeiro/Extrato/Index.tsx` | ✅ F1 | wagner |
| 4 | `/repair/status` | `resources/js/Pages/Repair/Status/Index.tsx` | ✅ F1 (stub) | wagner |
| 5 | `/financeiro/contas-bancarias` | `resources/js/Pages/Financeiro/ContasBancarias/Index.tsx` | ✅ F1 (stub) | wagner |

Substituições do plano original:
- ~~`/sells/create`~~ → vetado em F1 (Blade legacy do UltimatePOS, fora de Inertia ainda — vira Tier C em F2)
- ~~`/ads/admin/skills`~~ → tela não tem `.tsx` (módulo ADS sem Inertia ainda) — vira Tier B futuro

---

## Critérios de qualidade pra cada charter

Charter Tier A precisa atender 8 testes:

1. ✅ Frontmatter completo (10 chaves)
2. ✅ Mission ≤ 1 frase
3. ✅ ≥3 Goals + ≥3 Non-Goals
4. ✅ ≥3 UX Targets quantitativos
5. ✅ ≥3 UX Anti-patterns
6. ✅ ≥3 Automation Hooks ou Anti-hooks
7. ✅ ≥4 Métricas vivas (Pest tests referenciados)
8. ✅ Owner real ativo no time

Stub aceitável em F1: critérios 1-3 + esqueleto de 4-7. Completam em F2.

---

## Critérios de seleção Tier A (referência)

Toda tela Tier A precisa:
- Estar em prod (deployed em main)
- Ter telemetria (mcp_audit_log captura)
- Ter dono identificado (não órfão)
- Ter pelo menos 1 incidente registrado OU ser cliente-crítica (ROTA LIVRE, biz=4)

Tier B = produção mas sem incidente histórico
Tier C = legacy Blade, em sunsetting

---

## Critério de aceite F1

- [ ] 5 charters em paths corretos (`*.charter.md` ao lado de `*.tsx`)
- [ ] Frontmatter válido pra todos
- [ ] 2 charters completos (jobsheet + extrato), 3 stubs estruturados (dashboard já é completo)
- [ ] CI gate (`05-ci-gate-charter.md`) detecta os 5 sem erro
- [ ] Pest GUARD ([03](03-charter-pest-runner.md)) Tier 1 passa pra os 5
