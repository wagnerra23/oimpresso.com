---
slug: 0137-modules-oficinaauto-qualificada
number: 137
title: "Modules/OficinaAuto qualificada — sinal confirmado por 2 de 4 candidatos OfficeImpresso saudáveis"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-11"
module: null
quarter: 2026-Q2
tags: [arquitetura, modular, vertical, oficina-auto, modules-oficina, qualified-signal, ADR-0105, ADR-0121]
supersedes: []
supersedes_partially: []
amends: [0121]
superseded_by: []
amended_by: [0194]
related: [0105-cliente-como-sinal-guiar-sem-mandar, 0121-oimpresso-modular-especializado-por-vertical, 0136-sells-grade-avancada-modo-toggle, 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada]
pii: false
review_triggers:
  - "Modules/OficinaAuto ter <2 clientes pagantes após 12m de scaffold (candidato lifecycle: historical)"
  - "Modules/OficinaAuto schema multi-placa (PLACA + PLACA_SECUNDARIA) usado por <30% dos clientes da vertical (revisar — pode estar over-engineered)"
  - "Sinal de NOVO caso oficina muito diferente de Vargas/Martinho (ex: oficina mecânica geral, não caçamba) chegar — pode exigir 2º sub-vertical"
---

# ADR 0137 — Modules/OficinaAuto qualificada

> **⚠️ AMENDADO por [ADR 0194](0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (2026-05-26):** Este ADR classificou Martinho como sub-vertical 3 "locação caçamba container CNAE 4581-4/00". Wagner descobriu em 2026-05-26 que **Martinho é sub-vertical 4 mecânica pesada caminhão basculante CNAE 4520-0/01** (loja peça hidráulica + oficina autorizada, Capivari de Baixo/SC). Sub-vertical 3 (locação container) fica como hipótese sem cliente real ancorado — schema preservado nullable. Demais decisões deste ADR (schema multi-placa, FSM 3/5 estados, importer Firebird, ativação Vargas+Martinho) permanecem válidas — só o vocabulário do domínio Martinho foi corrigido.

## Contexto

[ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) (modular especializado por vertical) classificou `Modules/OficinaAuto` como **⏸️ aguardando sinal qualificado** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — backlog só recebe item se cliente paga + reporta OU métrica detecta drift).

Em sessão 2026-05-11 (heatmap source-first 4 clientes OfficeImpresso legacy + correções Wagner), descobrimos:

- **Vargas** (`Cliente_874398`) = **oficina GRANDE de recapagem de caçamba de caminhão** ([perfil](../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md)) — 1.064 veículos cadastrados, PLACA 80% + PLACA2 20% + CHASSI2 8% (cavalo+reboque)
- **Martinho** (`Cliente_731814`) = **loja peça hidráulica + oficina autorizada caminhão basculante** (correção [ADR 0194](0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — pré-correção dizia "oficina de caçambas avulsas") ([perfil](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)) — 91 veículos de CLIENTES, PLACA 96% sem multi-placa

**2 de 4 candidatos OfficeImpresso saudáveis** amostrados = **50% do sample** são oficina. Sinal qualificado obtido conforme matriz [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — clientes pagantes em produção há anos com volume operacional relevante.

## Decisão

**`Modules/OficinaAuto` passa de ⏸️ aguardando sinal pra 🟡 em construção.** Tabela em [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) §"Módulos verticais — estado" é amendada:

| Módulo | CNAE | Status anterior | **Status novo** | Cliente piloto |
|--------|------|-----------------|-----------------|----------------|
| Modules/Vestuario | 4781-4/00 | ✅ em produção | ✅ em produção | ROTA LIVRE |
| Modules/ComunicacaoVisual | 1813-0/01 | 🟡 em construção | 🟡 em construção | Extreme + Gold |
| **Modules/OficinaAuto** | **4520-0/01 / 2212-9/00 / 4581-4/00** | **⏸️ aguardando sinal** | **🟡 em construção** ✨ | **Vargas + Martinho** |
| Outros | — | 🔒 backlog ADR feature-wish | 🔒 backlog | — |

CNAEs cobertos por OficinaAuto (amendado [ADR 0194](0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)):
- **4520-0/01** — Serviços de manutenção e reparação mecânica de veículos automotores (oficinas gerais + **mecânica pesada caminhão basculante · Martinho LIVE prod sub-vertical 4**)
- **2212-9/00** — Recapagem de pneumáticos (Vargas — recapagem caçamba caminhão · sub-vertical 2 V1)
- **4581-4/00** — Locação de veículos (sub-vertical 3 hipotético locação caçamba container · **sem cliente real ancorado** pós-ADR-0194 · schema preservado nullable pra futuro)

## Escopo arquitetural do Modules/OficinaAuto V0

Pré-requisitos pra primeiro PR de scaffold ([skill `criar-modulo`](../../.claude/skills/criar-modulo/SKILL.md) + checklist 8 peças):

### Modelos essenciais

1. **`vehicles`** table — modelo `Vehicle` em `Modules/OficinaAuto/Entities/Vehicle.php`:
   - `id` PK
   - `business_id` FK ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) Tier 0 global scope obrigatório)
   - `contact_id` FK (dono do veículo)
   - `plate` VARCHAR(10) NOT NULL — placa principal
   - `secondary_plate` VARCHAR(10) NULL — **cavalo+reboque** (Vargas case)
   - `chassis` VARCHAR(30) NULL
   - `secondary_chassis` VARCHAR(30) NULL — reboque com chassi próprio
   - `manufacture_year` SMALLINT NULL
   - `model_year` SMALLINT NULL
   - `renavam` VARCHAR(11) NULL
   - `vehicle_type` ENUM('caminhao','cavalo','semi_reboque','cacamba_estacionaria','automovel','motocicleta','outro')
   - `engine`, `mileage_at_entry`, `fuel_type`, `color` (opcionais)
   - `notes` TEXT
   - `legacy_id` VARCHAR(20) NULL — preserva CODIGO Firebird (`EQUIPAMENTO_VEICULO.CODIGO`)
   - `created_at`, `updated_at`, `deleted_at` (soft delete)
   - **Indexes:** `(business_id, plate)`, `(business_id, contact_id)`, `(business_id, legacy_id)`

2. **`service_orders`** table — modelo `ServiceOrder`:
   - `id`, `business_id`, `transaction_id` FK (link com sells/transactions UltimatePOS — uma OS gera 1 venda)
   - `vehicle_id` FK pra `vehicles`
   - `mileage_at_service` INT — KM no momento da entrada
   - `status` ENUM (definidos por FSM — [ADR 0129](0129-state-machine-canonica-fsm-rbac.md))
   - `entered_at`, `expected_completion`, `completed_at`, `delivered_at`
   - `notes`

### FSM canônica da OS

Aproveitar [ADR 0129](0129-state-machine-canonica-fsm-rbac.md) (State Machine canônica) com **2 processos por business**:
- **OS Simples** (Martinho): 3 estados — `aberta` → `em_servico` → `concluida`
- **OS Complexa** (Vargas, futuro): 5 estados — `aberta` → `orcamento` → `aprovada` → `em_producao` → `concluida` → `entregue`

Importer legacy mapeia `VENDA_ESTAGIO` Firebird → estado FSM correspondente.

### UI base (Inertia/React, segue ADR 0104 MWART)

- **`Modules/OficinaAuto/Resources/views/Pages/Vehicles/Index.tsx`** — lista veículos
- **`.../Vehicles/Create.tsx`** + **`.../Vehicles/Show.tsx`**
- **`.../ServiceOrders/Index.tsx`** — Kanban opcional (Wagner descobriu `Controller.Producao.Kanban.pas` no Delphi — feature reusável) OU lista padrão
- **Sidebar entry:** "Veículos" + "OS Oficina" sob grupo SIDEBAR_GROUPS.oficina (criar grupo novo no `resources/js/Components/cockpit/Sidebar.tsx`)

### Permissions

- `oficinaauto.access` — acessar módulo
- `oficinaauto.vehicle.view` / `create` / `update` / `delete`
- `oficinaauto.service_order.view` / `create` / `update` / `delete`
- `oficinaauto.service_order.delete` (Wagner only)

## Critério de validação V0 → V1

`Modules/OficinaAuto` é considerado V0 quando o piloto funciona end-to-end com **Martinho** (caso simples — PLACA única, FSM 3 estados, 91 veículos importados). Critério de promoção pra V1:

- [ ] Martinho importado (91 veículos legacy → MySQL)
- [ ] 1 OS criada manualmente via UI nova
- [ ] Pest tests verdes (vehicle CRUD + FSM + multi-tenant isolation)
- [ ] Smoke biz=1 sem regressão
- [ ] Canary 7 dias com Martinho sem incidente

Após V0 sólido, V1 expande pra **Vargas** (cavalo+reboque + multi-item OS — sinal Q5 itens/venda 3.08).

## US relacionadas (já no SPEC ou criar)

| Status | US | Onde |
|--------|----|-----|
| Já existe (criar tasks-create) | US-OFICINA-001 — Scaffold módulo (8 peças) | criar SPEC `Modules/OficinaAuto` |
| Já existe | US-SELL-028 — Schema multi-placa em Modules/OficinaAuto ([SPEC Sells](../requisitos/Sells/SPEC.md) §US-SELL-028) | aproveitar |
| Nova | US-OFICINA-002 — Importer Firebird `EQUIPAMENTO_VEICULO` → `vehicles` Laravel | criar |
| Nova | US-OFICINA-003 — FSM OS Simples (3 estados) + Complexa (5 estados) | criar |
| Nova | US-OFICINA-004 — UI Kanban OS Vargas (futuro V1) | criar |

## Pré-requisitos satisfeitos

- ✅ **Sinal qualificado** (ADR 0105) — 2 clientes pagantes Vargas + Martinho documentados
- ✅ **CNAE definidos** — 4520 / 2212 / 4581 cobrem 3 sub-verticais
- ✅ **Schema canônico definido** — multi-placa nullable, FSM 3/5 estados
- ✅ **Importer source identificado** — `EQUIPAMENTO_VEICULO` Firebird (mapping em [_MAPPING/TELA-LISTA-VENDAS.md](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md) §9.4)
- ✅ **Cliente piloto V0 definido** — Martinho (caso simples)
- ✅ **Bridge Delphi → oimpresso.com existente** ([Controller.OImpresso.pas](../../.claude/skills/officeimpresso-source-analysis/SKILL.md)) — sync de Pessoas/Vendas já implementado, falta sync de Equipamento (gap pra outro PR)

## Consequências

✅ **Sinal qualificado materializado** — 2 clientes pagantes ancoram justificativa
✅ **Schema multi-placa decidido com evidência** (Vargas + Martinho) — não over-engineering
✅ **Modelo "Asaas-like" viável** — Martinho continua usando Delphi + ganha cloud oimpresso.com em paralelo (sync via bridge OImpresso existente)
✅ **Reusa infra existente:** FSM canônica (ADR 0129), Multi-tenant Tier 0 (ADR 0093), MWART process (ADR 0104), criar-modulo skill, multi-tenant-patterns skill
✅ **Posicionamento comercial claro:** competir com Mecânico/Auto Manager/Lokoz/Pneustore (oficinas) — diferencial Jana IA + memória persistente

⚠️ **Risco — Vargas é outlier:** schema multi-placa (PLACA_SECUNDARIA) atende Vargas mas não simplifica modelo pra cliente automotive comum. Mitigação: nullable, default null — não polui Martinho/futuros clientes simples.

⚠️ **Risco — escopo creep:** "Modules/OficinaAuto" pode atrair pedido "põe estoque de peça, agenda de mecânico, integração com fornecedor" antes da V0 funcionar. Mitigação: V0 = só CRUD Veículo + OS Simples + import legacy; tudo mais aguarda V1+ com sinal qualificado novo.

⚠️ **Risco — sub-verticais distintas no mesmo módulo:** Vargas é CNAE 2212 (recapagem · sub-vertical 2 V1), Martinho é **CNAE 4520 mecânica pesada caminhão basculante** (sub-vertical 4 LIVE pós-[ADR 0194](0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)). Sub-vertical 3 hipotético CNAE 4581 (locação caçamba container) sem cliente real ancorado. Pode no futuro precisar splitting em `Modules/OficinaRecapagem` + `Modules/LocacaoCacamba` se cliente real de locação container aparecer. Decisão preliminar: **ficar em 1 módulo** (V0 Martinho cobre 4520; V1 Vargas adiciona 2212; 4581 hipótese preservada nullable).

## Implementação imediata (próximo PR sugerido)

1. **PR: Scaffold `Modules/OficinaAuto`** seguindo skill `criar-modulo`:
   - 8 peças obrigatórias + 3 rotas admin Install + `Route::has()` pra link público condicional
   - Migration `vehicles` + `service_orders` com FK + global scope ADR 0093
   - Model `Vehicle` + `ServiceOrder` com soft delete
   - Permissions registradas no UI Roles
   - DataController hook pra sidebar (entry "Veículos" + "OS Oficina")
   - Service Provider + Routes + Module.json
   - SPEC.md inicial com US-OFICINA-001..004

2. **PR seguinte: importer legacy Martinho**:
   - Artisan command `officeimpresso:import-vehicles {business_id} {firebird_dsn}`
   - Conecta no Firebird, lê `EQUIPAMENTO_VEICULO`, mapeia campos, popula `vehicles`
   - Smoke biz=1 (Wagner faz import dos 16k veículos toy em WR2 pra calibrar performance — biz=1 always safe)

## Refs

- [ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md) — ADR mãe (oimpresso modular vertical) — **amends pra atualizar tabela de status**
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0136](0136-sells-grade-avancada-modo-toggle.md) — Sells Grade Avançada (origem US-SELL-028 schema multi-placa)
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — processo MWART
- [ADR 0129](0129-state-machine-canonica-fsm-rbac.md) — FSM canônica
- [Perfil Vargas](../research/clientes-legacy-officeimpresso/02-vargas-recapagem/01-perfil.md) — sinal 1
- [Perfil Martinho](../research/clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md) — sinal 2
- [_ANALISE-CROSS-CLIENTE.md](../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md) — análise comparativa que materializa o sinal
- [_MAPPING/TELA-LISTA-VENDAS.md §9.4](../research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md) — schema EQUIPAMENTO_VEICULO Firebird (origem)
- Skill [criar-modulo](../../.claude/skills/criar-modulo/SKILL.md) — receita scaffold
- Skill [multi-tenant-patterns](../../.claude/skills/multi-tenant-patterns/SKILL.md) — Tier A enforcement
