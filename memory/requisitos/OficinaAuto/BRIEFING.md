---
distilled_at: "2026-07-09"
distilled_by: "manual [CC] — re-verificação do destilado de 2026-07-01 (jana:distill-module-truth) contra o único evento novo do módulo: arquivamento do charter-ghost Os/Create como lápide L-22 (PR #4037, housekeeping — nenhuma capacidade/gap mudou)"
module: OficinaAuto
status: piloto
updated_at: "2026-07-09"
---

# BRIEFING — OficinaAuto (verdade destilada)

## Estado atual  
O módulo "OficinaAuto" é focado na gestão de **oficinas de manutenção e reparação de veículos automotores pesados**, operando sob CNAE 4520-0/01. Cliente piloto: Martinho Caçambas LTDA (biz=164, 42k+ registros importados; Discovery em 13/mai/2026, lifecycle piloto com meta 2026-Q3 na CAPTERRA-FICHA).

## Capacidades  
- **Infraestrutura modular**: scaffold nWidart próprio completo (tabelas `vehicles`/`service_orders`/`oa_*` com `business_id`), integrado ao core via FKs (`transactions`, `contacts`).
- **Schema multi-tenant**: suporte a veículos com múltiplas placas e gestão de ordens de serviço (OS).
- **Fluxo de trabalho flexível**: canônico para mecânica pesada e recapagem.
- **Auditorias e controles**: suporte auditável nas OS através de um sistema de timeline.
- **Multicliente e multi-vertical**: já abrange sub-verticais como mecânica pesada e recapagem.

## Gaps  
- **Aprovação por PIN/token** do cliente na OS (top gap CAPTERRA-FICHA).
- **Checklist visual de inspeção** com fotos por etapa.
- **Catálogo de peças**, apontamento **multi-mecânico** e histórico por veículo.

(gaps conforme CAPTERRA-FICHA — nota scoped 63/100, meta ≥85)

## Última mudança  
Em 2026-05-26 o domínio Martinho foi reclassificado de "locação caçamba" para **"mecânica pesada"** (ADR 0194); em 2026-06-09 a ADR 0265 **erradicou "locação" como conceito de domínio** (`order_type ∈ {manutencao, mecanica}` — regressão proibida catalogada). O módulo seguiu evoluindo até jul/2026 (ex.: OS tela única 2026-06-11, reconciliação de OS #3433, fix de routing #3488). Em 2026-07-09 o charter-ghost `Pages/OficinaAuto/Os/Create.charter.md` (decisão [W] 2026-06-30: "não construir", canon = `ServiceOrders/`) foi arquivado como lápide L-22 em `_arquivo/` — fecha o resíduo da reconciliação de OS; nenhuma capacidade mudou. Nota interna scoped (rubrica Capterra): 63/100, meta ≥85.

## Proveniência (destilado de)

- audit `requisitos/OficinaAuto/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- handoff `handoffs/2026-06-30-1720-import-bundle-comvis-deteccao-charter-mis-anchor.md` (2026-06-30) — 2026-06-30-1720-import-bundle-comvis-deteccao-charter-mis-anchor.md
- session `sessions/2026-06-13-auditoria-adversarial-sdd-f2b-floor.md` (2026-06-13) — 2026-06-13-auditoria-adversarial-sdd-f2b-floor.md
- handoff `handoffs/2026-06-11-1410-oficina-os-tela-unica-fila-rica-scroll.md` (2026-06-11) — 2026-06-11-1410-oficina-os-tela-unica-fila-rica-scroll.md
- session `sessions/2026-06-10-board-oficina-corte.md` (2026-06-10) — 2026-06-10-board-oficina-corte.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
- handoff `handoffs/2026-06-06-2156-design-system-revitalizacao.md` (2026-06-06) — 2026-06-06-2156-design-system-revitalizacao.md
- session `sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md` (2026-06-05) — 2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md
- session `sessions/2026-06-04-analise-tela-venda-vs-oficina.md` (2026-06-04) — 2026-06-04-analise-tela-venda-vs-oficina.md
