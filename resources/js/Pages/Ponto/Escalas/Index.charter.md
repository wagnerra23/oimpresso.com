---
page: /ponto/escalas
component: resources/js/Pages/Ponto/Escalas/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-005]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/escalas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/EscalaController@index` (rota `ponto.escalas.index`, permissão `ponto.access`). Lista de escalas (padrões de jornada) do business.

---

## Mission
O gestor vê todas as escalas (padrões de jornada) cadastradas — nome, código, tipo, cargas diária/semanal, flag de banco de horas e quantidade de turnos — e navega para criar uma nova ou editar existente. É o índice do CRUD de escalas.

---

## Goals — Features (faz)
- Lista paginada (20/pág) de escalas com contagem de turnos.
- Colunas: nome, código, tipo (badge), carga/dia, carga/semana, BH, turnos.
- Atalho "Nova escala" (`/ponto/escalas/create`) e "Editar" por linha (`/ponto/escalas/{id}/edit`).
- Empty state com CTA de criar a primeira escala.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita inline — edição é na tela Form.
- ❌ Não gerencia turnos aqui — só mostra a contagem.
- ❌ Não lista escala de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não exclui escala nesta lista (rota destroy existe no resource, mas a UI não expõe — confirmar com Wagner).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Paginação usa partial reload (`only: ['escalas']`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling.
- ❌ Não muta dados em GET (só leitura/navegação).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Definir se exclusão de escala (destroy) entra na UI e com quais guardas
