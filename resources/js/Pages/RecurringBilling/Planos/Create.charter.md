---
page: /recurring-billing/planos/novo
component: resources/js/Pages/RecurringBilling/Planos/Create.tsx
owner: wagner
status: live
last_validated: "2026-05-17"
parent_module: RecurringBilling
related_adrs: [93, 94, 101, 104, 107, 110]
tier: A
charter_version: 1
sidebar_group: fin (FINANCEIRO)
related_prototype: n/a (herda PT-02 Form/Drawer; segue o Padrao de Tela)  # 2026-09-09 [C]: declaração de PT herdado. A aba "planos" do `cobranca-recorrente-page.jsx:369` é um `<Placeholder>` de 3 elementos e o arquivo é porte REVERSO (`:2` "Reescreve a RecurringBilling do git") — mesmo veredito dos 3 charters irmãos anotados no #7099; e Create/Edit nem são abas daquele hub, então há menos ainda. Promover ancoraria a tela nela mesma (§5 2026-06-05). ⚠️ PT-02 sem template renderizado — decisão [W] em §5.7. medido 2026-09-09 pelas 3 pernas (repo inteiro com --hidden, projeto Cowork por ID via DesignSync.list_files, espelho). Ver memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md §5.3 + §5.7.
related_us: [US-RB-001]
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
