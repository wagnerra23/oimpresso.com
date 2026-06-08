# RUNBOOK MWART — Repair/Index

> **Tela:** `/repair/repair` · **Componente:** `resources/js/Pages/Repair/Index.tsx`
> **Wave:** W3-B6 Repair · **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/repair/index.blade.php` |
| Inertia branch | `RepairController::index()` linha ~448 (já existia em flag MWART-0001 — esta wave VALIDA/EXPANDE) |
| Flag | `MWART_REPAIR_INDEX` (já existente) |
| .tsx | `resources/js/Pages/Repair/Index.tsx` (já existente, Sprint 2/MWART-0001) |

## F1 PLAN

1. **JÁ MIGRADO**: Wave anterior (Sprint 2/MWART-0001 PR #100) entregou `Repair/Index.tsx`. Esta wave **VALIDA** + adiciona FSM hooks futuros + assegura pattern compliance.
2. Pattern reuse: blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` (listagem OS-tipo).
3. Listagem `transactions` filtrada por `sub_type='repair'` (NÃO confundir com JobSheet — Repair Index é a VENDA-de-reparo, JobSheet é a ORDEM-DE-SERVIÇO).

## F2 BASELINE

Pest existente `RepairIndexMwartTest.php` (242 linhas) já cobre.

## F3 CODE

Já implementado em `RepairController::index()` + `buildInertiaIndexData()`. Esta wave NÃO altera lógica — apenas:
- Confirma `Inertia::defer` em props caras (verify)
- Acrescenta `<JobSheetFsmPanel>` reusável (futuro)

## F4 QA

`RepairIndexMwartTest.php` 6 testes — todos passando.

## F5 CUTOVER

Já em canary biz=1 (Wave anterior).

## Riscos

- **R1 (BAIXO)** — sem mudanças, sem novo risco.

## Decisão

Esta wave **APENAS DOCUMENTA** que Repair/Index está MWART-compliant. Sem code-touch no Controller (preserva trabalho Wave anterior).
