---
page: /ponto/escalas/create
component: resources/js/Pages/Ponto/Escalas/Form.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-005]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/escalas/create (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/EscalaController@create`/`@store`/`@edit`/`@update` (rotas `ponto.escalas.create`/`.store`/`.edit`/`.update`, permissão `ponto.access`). Formulário dual create/edit de escala (padrão de jornada).

---

## Mission
O gestor cria ou edita um padrão de jornada (escala): nome, código, tipo (FIXA/FLEXÍVEL/12x36/6x1/5x2), carga diária/semanal em minutos e flag de banco de horas. No modo edição, exibe (read-only por enquanto) os turnos por dia da semana já configurados.

---

## Goals — Features (faz)
- Formulário dual: cria (`POST /ponto/escalas`) ou edita (`PUT /ponto/escalas/{id}`), decidido por `escala` presente.
- Campos: nome (obrigatório), código, tipo (enum), carga diária (60–600 min), carga semanal (0–3600 min), permite banco de horas.
- No edit, listagem read-only dos turnos por dia da semana.
- Validação com feedback inline; toast de sucesso/erro.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não faz CRUD de turnos por dia — a UI de turnos é read-only (iteração futura).
- ❌ Não atribui a escala a colaboradores — atribuição é na tela do colaborador.
- ❌ Não cria escala em outro business — `business_id` injetado no store (Tier 0 multi-tenant).
- ❌ Não calcula apuração — só define o padrão de jornada.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- `store` injeta `business_id` e redireciona pro edit ("configure os turnos").
- Submit via `useForm.post`/`.put` conforme modo.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não salva sozinho — só no submit explícito.
- ❌ Não gera turnos padrão automaticamente ao criar.
- ❌ Não muta em GET.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Definir escopo da UI de CRUD de turnos (hoje read-only)
