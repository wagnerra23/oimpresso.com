---
id: requisitos-recurringbilling-planos-index-visual-comparison
title: "Comparação design × produção — RecurringBilling/Planos/Index"
module: RecurringBilling
tela: RecurringBilling/Planos/Index
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `RecurringBilling/Planos/Index`

> Fonte: `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx` (sha256 `49104ea2d640…`, commit `99f169a688`), vista `rb-planos`.
> Medido em **2026-11-23**, produção `oimpresso.com` em `business_id=1`, tema claro nos dois lados, 1280×900,
> sem banner, tour ou diálogo aberto. Método, canário e o que ficou fora: [Index-visual-comparison.md §Medição 2026-11-23](Index-visual-comparison.md).
> Snapshots e veredito da máquina: [`governance/design/targets/medidas/RecurringBilling--Planos--Index/`](../../../governance/design/targets/medidas/RecurringBilling--Planos--Index/).

Reproduz: `node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/RecurringBilling--Planos--Index/prod.json governance/design/targets/medidas/RecurringBilling--Planos--Index/design.json --check --contrato governance/design/contracts/recorrente-planos.contract.json`

## Veredito: **DIVERGE** — 3 divergências reais, 1 não conclusiva

Substitui a medida de 2026-09-18, que comparou esta tela contra a vista Assinaturas. D0 OK: 16 de 16 cópias em produção, 15 de 16 no protótipo.

| Dim | Campo | Produção | Protótipo | Leitura |
|---|---|---|---|---|
| D4 | título | 24px / 600 | 22px / 700 | **real** — PageHeader compartilhado |
| D4 | valor do KPI | 24px | 22px | **real** |
| D6 | pílula de status (col. 5) | com ponto, sem borda | sem ponto, com borda | **real** |
| D8 | ação da linha (col. 6) | link `<a>` para a página de edição | `<button>` que abre o drawer | consequência da decisão página × drawer (parte "Decidir" do gap) |
| D2 | linhas do filtro de ciclo | 2 | 1 | não conclusivo — em produção o papel casou rótulo + lista, no protótipo os chips |
| D2 | blocos de texto (col. 0) | 2 | 3 | depende do dado (nome do plano × mock) |
| D6 | cor | ok | ok | IGUAL (fraco) |
| D8 | alinhamento do KPI | ok | ok | IGUAL |
| D9 | formato de texto | só produção tem `data-contract` | — | não medido |
