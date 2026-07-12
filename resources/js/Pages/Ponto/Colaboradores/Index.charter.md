---
page: /ponto/colaboradores
component: resources/js/Pages/Ponto/Colaboradores/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-004]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/colaboradores (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ColaboradorController@index` (rota `ponto.colaboradores.index`, permissão `ponto.access`). Lista de colaboradores com configuração de ponto (nome/email do HRM UltimatePOS).

---

## Mission
O gestor localiza colaboradores para configurar seus parâmetros de ponto. A tela lista quem está cadastrado no HRM com matrícula, CPF, escala e flags de ponto/banco de horas, com busca por matrícula/nome/CPF e atalho para editar a configuração de cada um.

---

## Goals — Features (faz)
- Lista paginada (25/pág) de colaboradores.
- Busca com debounce (350ms) por matrícula, nome ou CPF (partial reload).
- Colunas: matrícula, nome/email, CPF, escala, flags "Ponto" e "BH".
- Atalho "Config" pro editar (`/ponto/colaboradores/{id}/editar`).
- Empty states distintos para "sem cadastro" e "busca sem resultado".

---

## Non-Goals — Features (NÃO faz)
- ❌ Não cadastra colaborador — cadastro é no HRM (UltimatePOS core).
- ❌ Não edita inline — edição é na tela Edit.
- ❌ Não lista colaborador de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não exporta CPF/PIS em massa — PII de colaborador (LGPD).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Busca dispara `router.get` com `only: ['colaboradores','search']` (partial reload, `replace`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling.
- ❌ Não muta dados em GET (só leitura/busca).
- ❌ Não sincroniza colaboradores do HRM sozinha — a lista reflete o que já existe no core.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar mascaramento de CPF na coluna (LGPD)
