---
id: requisitos-recurringbilling-faturas-index-visual-comparison
title: "Comparação design × produção — RecurringBilling/Faturas/Index"
module: RecurringBilling
tela: RecurringBilling/Faturas/Index
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `RecurringBilling/Faturas/Index`

> Fonte: `prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx` (sha256 `49104ea2d640…`, commit `99f169a688`), vista `rb-faturas`.
> Medido em **2026-11-23**, produção `oimpresso.com` em `business_id=1`, tema claro nos dois lados, 1280×900,
> sem banner, tour ou diálogo aberto. Método, canário e o que ficou fora: [Index-visual-comparison.md §Medição 2026-11-23](Index-visual-comparison.md).
> Snapshots e veredito da máquina: [`governance/design/targets/medidas/RecurringBilling--Faturas--Index/`](../../../governance/design/targets/medidas/RecurringBilling--Faturas--Index/).

Reproduz: `node scripts/design/design-diff.mjs --compare governance/design/targets/medidas/RecurringBilling--Faturas--Index/prod.json governance/design/targets/medidas/RecurringBilling--Faturas--Index/design.json --check --contrato governance/design/contracts/recorrente-faturas.contract.json`

## Veredito: **DIVERGE** — 5 divergências reais

Substitui a medida de 2026-09-18, que comparou esta tela contra a vista Assinaturas. D0 OK: 23 de 26 cópias do contrato em produção, 26 de 26 no protótipo.

| Dim | Campo | Produção | Protótipo | Leitura |
|---|---|---|---|---|
| D4 | título | 24px / 600 | 22px / 700 | **real** — PageHeader compartilhado |
| D4 | valor do KPI | 24px | 22px | **real** |
| D2 | linhas do filtro | 4 linhas: status, gateway, período, busca, cada um na sua | 3: status e gateway na mesma linha, período abaixo, busca no cabeçalho da lista | **real** — a máquina contou 5 × 4 porque o contador "50 / N" de produção fica no bloco de filtros; tirando ele, sobra 1 linha a mais em produção |
| D2 | status no filtro | 5 (todas, pagas, pendentes, atrasadas, canceladas) | 9 (inclui estornadas, reembolsadas, chargeback) | **real** — capacidade nova do protótipo (L7) |
| D6 | pílula de status (col. 5) | sem borda | com borda | **real** |
| D6 | gateway (col. 6) | pílula | texto | **real** |
| — | CTA do cabeçalho | "Nova fatura · em breve", desabilitado | nenhum | **real** (DOM; é parte "Decidir" do gap) |
| D6 | cor | ok | ok | IGUAL (fraco: tokens de produção não resolvem na raiz) |
| D8 | alinhamento do KPI | ok | ok | IGUAL |
| D9 | formato de texto | só produção tem `data-contract` | — | não medido |
