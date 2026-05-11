# RUNBOOK — Pages OficinaAuto Edit (V0 scaffold)

> **Tipo:** RUNBOOK MWART (ADR 0104 §F1 PLAN) — V0 placeholder
> **Status:** scaffold
> **Refs:** ADR 0137, SPEC.md US-OFICINA-001

## Telas cobertas

1. `resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx` — edição de veículo
2. `resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx` — edição de OS

## Contrato (V0)

Mesmo contrato dos Create.tsx (mesmos campos), mas:
- Pré-preenche valores via prop `vehicle` / `order`
- Submit via PUT `/oficina-auto/veiculos/{id}` / `/oficina-auto/ordens-servico/{id}`
- Redirect on success: tela Show correspondente

## Validação backend

Igual ao Create — mesma lista de regras. Diferença: contact_id e legacy_id ausentes nas regras update (preservados).

## Não-goals V0

- ❌ NÃO audit log de mudanças (Sprint 4 — ADR 0094 §SoC brutal)
- ❌ NÃO histórico de versões
