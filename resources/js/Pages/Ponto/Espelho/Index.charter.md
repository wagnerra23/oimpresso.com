---
page: /ponto/espelho
component: resources/js/Pages/Ponto/Espelho/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-007]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/espelho (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/EspelhoController@index` (rota `ponto.espelho.index`, middleware `ponto.access`). Lista colaboradores com controle de ponto ativo pra abrir o espelho mensal de cada um.

---

## Mission
Ponto de entrada do espelho de ponto: o RH escolhe um colaborador (dentre os que têm `controla_ponto` ativo e não desligados) e o mês de referência, pra então navegar ao espelho mensal detalhado. A tela em si é um índice de seleção — resume matrícula, nome, CPF e e-mail e leva ao `Show` já com o mês selecionado.

---

## Goals — Features (faz)
- Lista paginada (25/página) de colaboradores ativos do business, ordenada por matrícula.
- Seletor de mês de referência (`<input type="month">`) que propaga para os links "Ver {mes}".
- Link por linha pra `/ponto/espelho/{id}?mes=...` (abre o espelho mensal).
- Paginação via partial reload (`only: ['colaboradores','mes']`) — não recarrega a página inteira.
- Empty state quando não há colaborador com ponto ativo; skeleton via `<Deferred>` enquanto a lista carrega.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita marcações nem apuração — é só navegação/seleção (marcações são append-only, Portaria MTP 671/2021).
- ❌ A busca por matrícula/nome/CPF está **desabilitada** (`disabled`, "em breve") — não filtra a lista hoje.
- ❌ Não cruza tenants: só mostra colaboradores do `business_id` da sessão (scope multi-tenant). *(inferência pendente de Wagner)*
- ❌ Não gera PDF aqui (o PDF vive no `Show`).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 + PageHeader canon (ADR 0182).

---

## Automation hooks (faz)
- `Inertia::defer` na prop `colaboradores` — a query `paginate(25)` só roda quando o `<Deferred>` pede (RUNBOOK-inertia-defer-pattern).
- Troca de mês faz partial reload apenas de `mes` (a lista não depende do mês).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling nem auto-refresh.
- ❌ Nenhuma mutação em GET — a tela é read-only.
- ❌ Não dispara geração de relatório/PDF ao selecionar colaborador.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar se a busca "em breve" entra no escopo desta tela ou vira US separada
