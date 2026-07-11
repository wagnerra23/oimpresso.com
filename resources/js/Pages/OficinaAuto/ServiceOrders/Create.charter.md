---
page: /oficina-auto/service-orders/create
component: resources/js/Pages/OficinaAuto/ServiceOrders/Create.tsx
related_prototype: n/a (sem protótipo Cowork — herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: live
last_validated: "2026-06-09"
parent_module: OficinaAuto
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0093-multi-tenant-isolation-tier-0
  - 0110-tipografia-canon-h1-subtitle
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0265-oficina-reparo-erradica-locacao
tier: A
charter_version: 3
---

# Page Charter — /oficina-auto/service-orders/create

> **Status:** live (V0). Formulário de abertura de OS de reparo — `order_type ∈ {mecanica, manutencao}`.
>
> **Sub-vertical 4 ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** Martinho biz=164 LIVE prod usa principalmente reparo de caminhão basculante (sub-vertical 4 mecânica pesada).
>
> **Erradicação de locação ([ADR 0265](../../../../../memory/decisions/0265-oficina-reparo-erradica-locacao.md) — 2026-06-09):** o domínio de locação foi erradicado do backend (migration + validação `in:manutencao,mecanica` + KPI + menu). O toggle de locação na UI — que o charter v2 mantinha "por compat" — foi **removido** do Create (a opção quebrava o submit, pois o backend passou a rejeitar `locacao`). Status deixou de ser campo do form: nasce `aberta` e quem move é o FSM (canon GUARD — nunca setar estágio manualmente).

## Mission

Permitir abertura rápida de OS pelo atendente em ≤ 30s — escolher vehicle (existente ou criar inline), order_type, dados mínimos do trabalho, e abrir em status `aberta` ou já avançar pra `orcamento` se manutenção complexa.

## Goals — Features (faz)

- Sheet lateral 720px (título "Nova Ordem de Serviço")
- Select `order_type`: mecânica (default — caminhão, roda o pipeline FSM) vs manutenção simples — **sem locação** (ADR 0265)
- Autocomplete vehicle por placa (Mercosul + legacy) — se não existe, link "Cadastrar veículo"
- Combobox contact (cliente) — Martinho atende caminhões de terceiros (sub-vertical 4 ADR 0194); renderiza quando o controller envia a prop `contacts`. Submete `contact_id` (exists escopado por business — Tier 0)
- Campos do trabalho: mileage_at_service, entered_at, expected_completion, notes + check-in de entrada (combustível/avarias)
- Status **não** é campo do form — nasce `aberta`; quem move é o FSM (ADR 0265)
- Submit → POST /oficina-auto/ordens-servico → redirect Show
- Multi-tenant Tier 0 — vehicle_id e contact_id devem pertencer ao business atual (server-side double-check)

## Non-Goals — Features (NÃO faz)

- Criar items (peças/serviços) — pós-criação na Edit page ou drawer
- Atribuir mecânico — fica na transição FSM `aberta → em_servico`
- Gerar NFe automática — fica em status `entregue`/`concluida` via Modules/NfeBrasil action

## UX Targets

- p95 submit response < 500ms
- Autocomplete vehicle responde < 150ms (debounce 250ms)
- Validação inline antes de submit (sem round-trip)
- Mobile-friendly (atendente usa tablet 1024px Vargas / mobile 360px Martinho)

## UX Anti-patterns

- Submit sem CSRF (canon = Inertia useForm)
- Campos sempre visíveis ignorando order_type (clutter)
- Autocomplete sem debounce (flood backend)
- Toast genérico "Erro" sem detalhe acionável

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php) (cria OS Simples + Complexa)
- [Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php) (vehicle autocomplete depende)

## Refs

- [SPEC.md US-OFICINA-001](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [RUNBOOK-create.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-create.md)
- [ADR 0137 §"Escopo arquitetural V0"](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)

## UCs cobertos (PRECISA TER · rastreável · §10.4 [CC])

> Casos de Uso ("A tela precisa:") amarrados a GUARD Pest `uc-<id>` via [`prototipo-ui/audit/uc-registry.json`](../../../../../prototipo-ui/audit/uc-registry.json).
> ✅ presente+travado (some o elemento = build vermelho) · 🟡 gap (acende no `protocol_freshness`).

- ✅ **UC-01** (`uc-01`) — check-in do veículo: busca por placa, chassi/renavam/hodômetro/combustível, fotos de entrada + relato do diagnóstico (`EntryCheckinFields`).

## Trilha do tempo

> Append-only (L-22 — não reescrever histórico).

- **2026-05-26** (v2) — refator Page fullscreen → Sheet 720px; toggle locação mantido "por compat".
- **2026-06-09** (v3) — sweep ADR 0265 no front ([sessão](../../../../../memory/sessions/2026-06-09-sweep-os-front-adr0265.md) · [avaliação CC](../../../../../prototipo-ui/AVALIACAO_OS_GIT_2026-06-09.md)): opção **Locação removida** do select (backend rejeita `in:manutencao,mecanica`); **select de Status removido** (FSM manda, nasce `aberta`); **combobox Cliente** via prop `contacts` + submete `contact_id` escopado. Goal "toggle locação" aposentado.
