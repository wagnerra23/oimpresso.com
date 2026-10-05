---
title: "RUNBOOK — Acompanhamentos do CRM (Blade → Inertia)"
module: Crm
tela: Crm/Acompanhamentos/Index
owner: W
status: ativo
last_validated: "2026-10-05"
---

# RUNBOOK — Migração MWART /crm/follow-ups → Crm/Acompanhamentos/Index

## 1. Tela
- **Legacy:** Blade `crm::schedule.index` (DataTables + modais `schedule/create`, `edit`, recorrente, antecipado, log)
- **Inertia:** `Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index.tsx` (+ `_components/FormAcompanhamento.tsx`)
- **Controller:** `Modules\Crm\Http\Controllers\ScheduleController` — `index` (lista), `store`/`update`/`destroy` (escrita)
- **Saída de emergência:** `?classico=1` devolve a Blade (UC-CRMACO-07). Não há flag.

## 2. Fatias
| thread | o quê | estado |
|---|---|---|
| Crm/03 | lista, filtros, abas, contrato + trio | entregue (#8352) |
| Crm/07 PR-a | modal adicionar/editar + excluir com confirmação | este RUNBOOK |
| Crm/07 PR-b | recorrente, antecipado, log; rodapé por status/tipo; drawer de detalhe | pendente |

## 3. Pré-flight
- Charter `Index.charter.md` + casos `Index.casos.md` ao lado do `.tsx`.
- Âncora: `node scripts/design/ancora.mjs Crm/Acompanhamentos/Index` → `prototipo-ui/cowork/Wagner/crm-blade.jsx`.
- ADRs: 0093 (multi-tenant), 0104 (MWART), 0358 (tenant de teste 98).

## 4. Gravação (sem endpoint novo)
- O modal chama as rotas da Blade por `fetch` ajax: `POST /crm/follow-ups`, `PUT /crm/follow-ups/{id}`,
  `DELETE /crm/follow-ups/{id}`. Elas devolvem `{success, msg}` (não resposta Inertia), por isso não é `router.post`.
- Datas saem do `<input type="datetime-local">` em ISO; o `ScheduleService` converte com
  `Util::uf_datetime_input`, que aceita ISO e o formato da empresa que a Blade manda.
- A linha da lista traz `editar` com os valores crus do modal (UC-CRMACO-13).

## 5. Multi-tenant (Tier 0)
- `update`/`destroy` buscam escopados por `business_id` (+ "só os meus") → outro negócio = 404 (UC-CRMACO-10).
- `business_id`/`created_by` saem do payload do `update` (UC-CRMACO-09).
- `contact_id` validado contra o negócio da sessão nos dois FormRequests (UC-CRMACO-12).

## 6. Teste
- `Modules/Crm/Tests/Feature/CrmAcompanhamentosContratoTest.php`, lane `verticais-pest` (MySQL), tenant 98/99.
- Nunca local: CI ou CT 100.

## 7. Rollback
- `?classico=1` mantém a escrita inteira na Blade. Reverter o PR volta os botões para a Blade.
