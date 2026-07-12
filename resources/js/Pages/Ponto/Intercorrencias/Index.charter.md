---
page: /ponto/intercorrencias
component: resources/js/Pages/Ponto/Intercorrencias/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-001]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/intercorrencias (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/IntercorrenciaController@index` (rota `ponto.intercorrencias.index`, middleware `ponto.access`). Lista as intercorrências do business com filtros por estado e tipo.

---

## Mission
Lista das intercorrências (ocorrências de ponto) do business — ausências, atestados, esquecimentos, horas extras autorizadas — com o estado do fluxo de aprovação de cada uma. Serve pro RH acompanhar o pipeline (rascunho → pendente → aprovada/rejeitada → aplicada) e abrir o detalhe de cada item.

---

## Goals — Features (faz)
- Lista paginada (25/página) ordenada por data e criação, do business.
- Filtros por estado e por tipo (`PageFilters` + chips ativos removíveis + reset) via partial reload (`only: ['intercorrencias','filtros']`).
- Colunas: código, colaborador (nome+matrícula), tipo (rotulado), data, estado (`StatusBadge`) + badge de prioridade urgente, criada (humanizado).
- Empty state distinto para "sem itens" vs "sem resultado do filtro".
- Botão primário "Nova" → `/ponto/intercorrencias/create`; link "Ver" por linha → `Show`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não aprova/rejeita/submete a partir da lista (essas ações vivem no `Show`/Aprovações).
- ❌ Não edita intercorrência inline.
- ❌ Não mostra intercorrências de outro tenant — scope por `business_id` da sessão.
- ❌ Não aplica efeito na apuração — é só visão de acompanhamento.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- Filtros aplicam partial reload seletivo, preservando estado/scroll.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh.
- ❌ Nenhuma mutação em GET — read-only.
- ❌ Filtrar não muda estado de nenhuma intercorrência.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) da lista + filtros + empty states
- [ ] Confirmar se a menção "submeter pelo app mobile" (empty state) reflete capacidade viva
