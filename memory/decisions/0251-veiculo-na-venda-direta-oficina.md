---
slug: 0251-veiculo-na-venda-direta-oficina
number: 251
title: "Veículo na venda direta de oficina (transactions.vehicle_id) — extensão da Integração Vendas × Oficina"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-05"
accepted_at: "2026-06-05"
accepted_via: "Wagner pediu a feature ('vamos colocar veículo na venda') e escolheu o schema via AskUserQuestion nesta sessão: opção 'Sim, vehicle_id em transactions' (coluna nullable + FK, mesmo padrão de source/os_ref). Sequência confirmada: feature numa branch nova do fresh main."
module: sells
quarter: 2026-Q2
tags: [sells, oficinaauto, vehicle, cross-module, multi-tenant, tier-0, schema, kb-9.75]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related: [0192-auto-faturar-os-venda-jobsheet-observer, 0093-multi-tenant-isolation-tier-0]
---

# ADR 0251 — Veículo na venda direta de oficina (`transactions.vehicle_id`)

## Contexto

Numa oficina, ao vender peça/serviço, é preciso saber **qual veículo** está em
atendimento. Hoje o veículo (`Modules/OficinaAuto`, tabela `vehicles`, multi-placa
cavalo+reboque) só é selecionado **na OS** (`service_orders.vehicle_id` NOT NULL).
A OS depois vira venda via `transaction_sell_line_id` ([ADR 0192](0192-auto-faturar-os-venda-jobsheet-observer.md)).

Lacuna (Wagner, 2026-06-05): a **venda direta de balcão de oficina** — vender uma
peça/serviço rápido **sem abrir a OS Kanban completa** — não tem onde registrar o
veículo. As 8 telas de venda (`/sells/*`) não têm campo de veículo (confirmado:
ausente em todo código e em todo o histórico do git). O protótipo Cowork
(`prototipo-ui/vendas-page.jsx`) chegou a desenhar "Veículo (placa)" mas nunca foi
pra produção.

## Decisão

1. **Coluna `transactions.vehicle_id`** (BIGINT UNSIGNED, **nullable**, FK →
   `vehicles.id` `ON DELETE SET NULL`). Liga 1 venda a 1 veículo. Pareia com
   `source`/`os_ref` (ADR 0192): aquela cobre a venda **derivada** de OS; esta a
   venda **direta**. `vehicle_id` único (1 veículo por venda) — não pivot.
   - FK guardada por `Schema::hasTable('vehicles')` (degrada gracioso se OficinaAuto
     não instalado num ambiente); índice composto `(business_id, vehicle_id)` Tier 0.
2. **Seletor na `/sells/create`** consome `contact.vehicles[]` (eager-load guardado
   por `Schema::hasTable`, mesmo padrão do `vehiclesCountMap` já existente no
   `getCustomers`), exibe a **plaquinha `MercosulPlate`** (promovida de OficinaAuto
   pra `@/Components/shared`).
3. **Cadastro rápido sem perder a venda** — `QuickAddVehicleSheet` (espelha
   `QuickAddCustomerSheet`: fetch direto + CSRF, não Inertia router) POST num branch
   JSON de `VehicleController::store` com `contact_id` pré-preenchido; o veículo novo
   entra selecionado.
4. **Gate multi-tenant** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)): toda a
   UI de veículo só aparece quando o módulo **OficinaAuto está habilitado** pro
   business — vestuário (ROTA LIVRE biz=4) **não vê** (zero ruído), exatamente como
   o tipo-de-serviço.
5. **Consulta** (`Sells/Index` + `Sells/Show`) exibe a `MercosulPlate` quando a venda
   tem `vehicle_id`.

## Consequências

- **Positivas:** venda direta de oficina vira completa; reusa 100% do que existe
  (vehicles, MercosulPlate, ClienteVeiculosController, padrão QuickAdd); zero
  breaking change retroativo (coluna NULL nas vendas legadas/vestuário).
- **Tier 0 preservado:** `Vehicle` já tem global scope por `business_id`; o índice e
  a FK respeitam o isolamento; eager-load guardado.
- **Custo:** +1 coluna nullable + 1 FK + 1 índice em `transactions`; nenhuma tabela
  nova. A emissão fiscal do veículo (se algum dia entrar em NF-e) fica fora de escopo
  aqui (pareia com o gatilho cMun que ADR 0192/US-SELL-044 trata).
- **Reversível:** `down()` dropa FK + índice + coluna sem perda de dados.

## Alternativas descartadas

- **Tabela ponte `transaction_vehicle`** (N veículos/venda): overkill — 1 venda
  direta = 1 veículo em atendimento. Se algum dia precisar N, migra-se sem perder a
  coluna (vira denormalização do "principal").
- **Forçar passar pela OS sempre:** rejeitado — a dor é justamente a venda rápida de
  balcão sem o peso da OS Kanban.
