---
id: requisitos-recurringbilling-configuracoes-index-visual-comparison
title: "Comparação design × produção — RecurringBilling/Configuracoes/Index"
module: RecurringBilling
tela: RecurringBilling/Configuracoes/Index
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `RecurringBilling/Configuracoes/Index`

> Fonte: `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx` (sha256 `49104ea2d640…`, commit `99f169a688`), vista `rb-config`.
> Medido em **2026-11-23**, produção `oimpresso.com` em `business_id=1`, tema claro nos dois lados, 1280×900,
> sem banner, tour ou diálogo aberto. Método, canário e o que ficou fora: [Index-visual-comparison.md §Medição 2026-11-23](Index-visual-comparison.md).
> Snapshots e veredito da máquina: [`governance/design/targets/medidas/RecurringBilling--Configuracoes--Index/`](../../../governance/design/targets/medidas/RecurringBilling--Configuracoes--Index/).

Reproduz: `node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/RecurringBilling--Configuracoes--Index/prod.json governance/design/targets/medidas/RecurringBilling--Configuracoes--Index/design.json --check --contrato governance/design/contracts/recorrente-configuracoes.contract.json`

## Veredito: **DIVERGE** — 2 divergências reais

Substitui a medida de 2026-09-18, que comparou esta tela contra a vista Assinaturas. D0 OK: 11 de 11 cópias em produção, 10 de 11 no protótipo.

| Dim | Campo | Produção | Protótipo | Leitura |
|---|---|---|---|---|
| D4 | título | 24px / 600 | 22px / 700 | **real** — PageHeader compartilhado |
| D9 | texto da régua e da NFe | identificadores técnicos na copy: `past_due → fail`, "detail drawer" (régua) e `fiscal_type` (NFe) | português: "a assinatura passa para “falhou” e pede ação manual no detalhe da assinatura" | **real** — a sonda achou `snakeCru` nas regiões `dunning` (2) e `nfe` (1) |
| D2 | layout | ok | ok | IGUAL |
| D6 | cor | ok | ok | IGUAL (fraco) |

Sem KPI nem tabela nesta tela: D2 de KPI e D4 de linha não se aplicam.
