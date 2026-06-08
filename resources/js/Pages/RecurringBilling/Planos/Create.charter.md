---
page: /recurring-billing/planos/novo
component: resources/js/Pages/RecurringBilling/Planos/Create.tsx
owner: wagner
status: live
last_validated: 2026-05-17
parent_module: RecurringBilling
related_adrs: [0093, 0094, 0101, 0104, 0107, 0110]
tier: A
charter_version: 1
sidebar_group: fin (FINANCEIRO)
---

# Page Charter — /recurring-billing/planos/novo (Criar Plano · v1)

## Mission

Formulário pra cadastrar novo plano de assinatura (nome / slug / valor / ciclo / trial / fiscal) — submete POST `/recurring-billing/planos` e redireciona pra Index com flash de sucesso.

## Goals — Features (faz)

- AppShellV2 layout
- Header `Novo plano · cobrança recorrente` + breadcrumb Voltar
- Form com campos: name (required), slug (auto-derivado de name se vazio), descricao_curta (opcional), description (textarea), valor (BRL input), ciclo (select com 5 opções), ciclo_dias (input só quando ciclo=custom), trial_days (0-90), ativo (checkbox default true), fiscal_type (select 3 opções), fiscal_cfop (só quando NFe), fiscal_servico (só quando NFS-e)
- `useForm()` Inertia (`router.post` via `form.post`)
- Validation errors inline por campo (vermelho com mensagem)
- Submit redirect `Planos.index` com flash success
- Botões: Salvar (primary violet) + Cancelar (volta pra Index)

## Non-Goals — Features (NÃO faz)

- ❌ Wizard multi-step — é um form único
- ❌ Preview de cobrança simulada — Onda futura
- ❌ Importar plano de template — Onda futura
- ❌ Upload de imagem do plano — Onda futura

## UX Targets

- p95 first-paint < 800ms (sem fetch caro, só form vazio)
- Erros server-side aparecem dentro de 1 frame após submit
- Tab navigation funciona em todos os campos
- Required fields marcados com `*`

## UX Anti-patterns

- ❌ Modal — canon = página dedicada
- ❌ JS validation client-side custom (duplicaria FormRequest) — só required HTML5 mínimo, server fala palavra final
- ❌ Auto-submit ao mudar campo — sempre clique explícito

## Endpoints

| Método | Rota | Retorna |
|---|---|---|
| GET | `/recurring-billing/planos/novo` | Inertia render `Planos/Create` props `{defaults}` |
| POST | `/recurring-billing/planos` | redirect Index + flash success ou 422 com erros |
