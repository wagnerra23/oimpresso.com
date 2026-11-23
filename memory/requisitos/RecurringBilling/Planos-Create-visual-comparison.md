---
id: requisitos-recurringbilling-planos-create-visual-comparison
title: "Comparação design × produção — RecurringBilling/Planos/Create"
module: RecurringBilling
tela: RecurringBilling/Planos/Create
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `RecurringBilling/Planos/Create`

> Fonte: `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx` (sha256 `49104ea2d640…`, commit `99f169a688`), drawer `novo` da vista `rb-planos` ("Novo plano").
> Medido em **2026-11-23**, produção `oimpresso.com` em `business_id=1`, tema claro nos dois lados, 1280×900,
> sem banner, tour ou diálogo aberto. Método, canário e o que ficou fora: [Index-visual-comparison.md §Medição 2026-11-23](Index-visual-comparison.md).
> Snapshots e veredito da máquina: [`governance/design/targets/medidas/RecurringBilling--Planos--Create/`](../../../governance/design/targets/medidas/RecurringBilling--Planos--Create/).

Reproduz: `node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/RecurringBilling--Planos--Create/prod.json governance/design/targets/medidas/RecurringBilling--Planos--Create/design.json --check-drawer`

## Veredito: **DIVERGE** — página × drawer, mais 7 grupos de forma

Primeira medição desta tela. O charter declara `related_prototype: n/a`; a medida usa o drawer que o protótipo mapeia para ela em `ROTAS["rb-planos"].drawers`. Produção é página inteira em `/recurring-billing/planos/novo`; o protótipo é drawer de 560px sobre a lista. Os papéis do drawer foram medidos contra o contêiner da página de produção. Das 135 propriedades comparadas, 68 batem; o resto vira as linhas abaixo.

| Grupo | Produção | Protótipo | Leitura |
|---|---|---|---|
| contêiner | página, 1020px de largura, fundo `--muted`, padding 24 | drawer 560px, borda esquerda, sombra | **real** — decisão página × drawer (parte "Decidir" do gap) |
| título do formulário | 24px, -0.6px de tracking | 14px | **real** |
| rótulo de seção | 14px / 600, cor do texto | 10.5px / 500, caixa alta, tracking 0.84px, cinza | **real** |
| seção | card com anel e sombra, padding 20, raio 12 | borda 1px, padding 12×14, raio 9, grid com gap 12 | **real** |
| campo | 38px de altura, raio 12, fundo transparente | 32px, raio 6, fundo branco | **real** |
| botão salvar | 36px, raio 12, 14px, com ícone | 32px, raio 7, 12.5px, sem ícone | **real** |
| rodapé | 44px, sem borda | 60px, borda superior, padding 18 | **real** |
| fonte do botão salvar | IBM Plex Sans | **Arial** | **defeito do protótipo** — o `<button>` do drawer não herda a fonte |


A linha `D4 título` que o `--check` acusa (24px/600 × 22px/700) compara o h1 da página de produção com o h1 da lista **atrás** do drawer; o título equivalente é o do formulário, na tabela acima.
