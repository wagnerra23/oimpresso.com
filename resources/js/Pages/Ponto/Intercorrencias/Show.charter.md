---
page: /ponto/intercorrencias/{id}
component: resources/js/Pages/Ponto/Intercorrencias/Show.tsx
related_prototype: n/a (tela de detalhe bespoke — ficha de intercorrência com ações por estado; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-001]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/intercorrencias/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/IntercorrenciaController@show` (rota `ponto.intercorrencias.show`) + `@submeter` / `@cancelar` (rotas `ponto.intercorrencias.submeter`/`cancelar`). Middleware `ponto.access`.

---

## Mission
Ficha detalhada de uma intercorrência: dados da ocorrência (colaborador, tipo, data/intervalo, justificativa, flags de apuração/banco de horas, solicitante) e as ações disponíveis conforme o estado do fluxo. Rascunhos podem ser editados/submetidos/cancelados; pendentes podem ser cancelados; aprovadas/rejeitadas mostram o desfecho e o responsável.

---

## Goals — Features (faz)
- Cabeçalho com código, badge de estado, badge de prioridade urgente e data de criação.
- Alerta de desfecho: motivo em REJEITADA, "aprovada por" em APROVADA.
- Card "Dados" com colaborador, tipo, data (dia-todo ou intervalo), justificativa, impacto na apuração, desconto de BH, solicitante.
- Ações por estado: Editar (rascunho), Submeter para aprovação (rascunho), Cancelar (rascunho/pendente) — com `confirm()` nas mutações.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não aprova/rejeita aqui — isso é do RH em Aprovações (esta tela é do solicitante).
- ❌ Não edita os campos inline — "Editar" leva a uma rota separada (`edit`, hoje Blade).
- ❌ Não aplica a intercorrência na apuração/banco de horas — isso ocorre no fluxo pós-aprovação.
- ❌ Não valida acesso cross-tenant explicitamente no `findOrFail` — depende do global scope do model. *(inferência/risco pendente de Wagner)*

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- `submeter` → `IntercorrenciaService::submeter` (transiciona rascunho → pendente).
- `cancelar` → `IntercorrenciaService::cancelar` (marca cancelada; ação declarada não reversível na UI).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Submeter/cancelar só acontecem por clique com confirmação — nunca automático.
- ❌ Nenhuma mutação em GET — as ações são POST.
- ❌ Não notifica o RH automaticamente ao abrir a ficha (submeter é o gatilho do fluxo).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar scope `business_id` no `show`/`submeter`/`cancelar` (risco cross-tenant)
- [ ] A rota `edit` ainda é Blade (`pontowr2::intercorrencias.edit`) — decidir migração MWART ou manter
- [ ] Smoke visual 1280/1440 (screenshot) nos estados rascunho/pendente/aprovada/rejeitada
