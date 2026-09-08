---
id: requisitos-repair-briefing
module: Repair
status: shared-infra
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Repair (verdade destilada)

## Estado atual
Ordens de serviço como infraestrutura compartilhada entre verticais (`OficinaAuto`, `ComunicacaoVisual`, `Vestuario`). O Kanban `ProducaoOficina` e o FSM Pipeline de OS (13 estágios, ADR 0143) estão operacionais. O SPEC saiu do estado de placeholder: US-REPA-001 segue `_pendente_`, mas US-REPA-003/004/005 documentam telas vivas com âncora `**Testado em:**` (lane Verticais · Pest MySQL). O job `Pest Repair` vem da matriz `modules-pest.yml`; o enforcement dele é o que estiver em `governance/required-checks-baseline.json` — e foi por não bloquear merge que o vermelho do `Wave18RepairSaturationTest` (`Call to undefined method Container::basePath()`, run 31040822015 de 2026-08-05: 3 failed, 65 skipped, 80 passed) rodou sem segurar nada até o conserto em #6240 (2026-08-25); a matriz dispara por path de qualquer módulo dela, por isso o vermelho aparecia em PR alheio.

## Capacidades
- JobSheet (OS) com criação, edição e impressão.
- Kanban com colunas fixas (Recepção, Diagnóstico, Aguardando peças, Em execução, Pronto) e `repair_statuses` configuráveis por business; drag-and-drop (US-REPAIR-PROD-4).
- Venda derivada da OS: faturamento pelo POS a partir do JobSheet (`sub_type=repair&job_sheet_id=`), com card `VendaDerivadaCard` no Kanban.
- FSM Pipeline de estágios (`FsmProcessoOsReparoPadraoSeeder`).
- Vocabulário genérico multi-vertical com personalização por negócio.
- Contrato executável em Pest Feature nas telas de OS e de cadastro (#6882/#6883/#6884/#6887), com E2E + a11y no Kanban `ProducaoOficina` (#6878); `JobSheet/Index` segue sem `casos.md` — US-REPA-004 `_parcial_`.

## Gaps
- Top-5 da FICHA (US-REP-005..009): KPIs/dashboard, app mobile, comissão, catálogo, retention-purge.
- Configurações migradas para Inertia atrás da flag `repair_settings_index` (default OFF, #6779) e no PageHeader canon (#6814); o cutover da flag é decisão [W] — SPEC US-REPA-003 `_parcial_`.
- `repair:fsm:bulk-start` para iniciar OS legadas no FSM não existe (US-REP-FSM-006).
- O `base_path()` fora do bootstrap que quebrava o `Wave18RepairSaturationTest` foi corrigido em #6240 (2026-08-25); `SPEC.md` US-REPA-002 ainda diz `_pendente_` — SPEC atrás do código (precedência: teste > SPEC), correção pendente.

## Última mudança
2026-09-04..06 — onda MWART das Configurações (Inertia, #6779), `Settings/Index` no PageHeader canon (#6814), primeiro E2E + a11y do módulo (#6878), contrato executável das telas (#6882/#6883/#6884/#6887) e revogação de GUARDs fantasmas no charter `JobSheet/Index` (#6874). Antes: perf D-14 partial reload em `DeviceModels/Index` e `Repair/Index` (telas MWART/Inertia; #3901, 2026-07-06) e o draft de charter da OS (#4123, 2026-07-12).

## Proveniência (destilado de)

- audit `requisitos/Repair/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r3.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r3.md
- session `sessions/2026-09-05-raio-vazamento-variation-cross-tenant.md` (2026-09-05) — 2026-09-05-raio-vazamento-variation-cross-tenant.md
- handoff `handoffs/2026-08-08-1938-permissoes-classe-d-idioma-gate-before.md` (2026-08-08) — 2026-08-08-1938-permissoes-classe-d-idioma-gate-before.md
