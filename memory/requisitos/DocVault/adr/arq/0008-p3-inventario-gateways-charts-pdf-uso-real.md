# ADR ARQ-0008 (DocVault) · P3 inventário final — gateways, charts, PDF estão todos em uso

- **Status**: accepted
- **Data**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Relacionado**: ADR arq/0002 (Fase 2 Laravel 10 blockers)

## Contexto

ADR arq/0002 original classificou vários pacotes como **P3 — "remover se não usa"**:
- `knox/pesapal 1.5` (gateway pagamento África)
- `myfatoorah/laravel-package ^2.2` (gateway pagamento Kuwait/Arábia)
- `razorpay/razorpay 2.*` (gateway pagamento Índia)
- `stripe/stripe-php ^7.122` (gateway pagamento global — versão antiga)
- `srmklive/paypal ^3.0` (gateway PayPal)
- `aloha/twilio ^4.0` (SMS notifications)
- `consoletvs/charts ^6.5` (biblioteca de gráficos abandonada desde 2020)
- `mpdf/mpdf ^8.1` (gerador de PDF — conhecido problema com PHP 8.4)

Inventário empírico revelou: **todos em uso real**. Classificação original "remover se não usa" não se aplica. Este ADR documenta estratégia caso-a-caso.

## Inventário confirmado (via grep em 2026-04-23)

| Pacote | Uso real | Estratégia |
|---|---|---|
| `knox/pesapal` | 9 arquivos (controller próprio + config + rotas + views Superadmin) | **Manter** — gateway ativo |
| `myfatoorah/laravel-package` | 2 arquivos (controller + config) | **Manter** — gateway ativo |
| `razorpay/razorpay` | 4 arquivos (Superadmin subscription payment) | **Manter** — gateway ativo |
| `stripe/stripe-php` | 1 arquivo (SubscriptionController) | **Manter mas bumpar** — v7.122 é velho, v10+ existe |
| `srmklive/paypal` | 4 arquivos (config + controller + view) | **Manter** |
| `aloha/twilio` | Config + templates SMS | **Manter** — notificações |
| `consoletvs/charts` | 5 arquivos (CommonChart + 4 controllers com 13 usos) | **Substituir** — lib abandonada |
| `mpdf/mpdf` | 7 arquivos (util, 3 controllers core, 2 módulos) | **Substituir** — incompatibilidade PHP 8.4 |

## Decisão por item

### Gateways de pagamento (pesapal/myfatoorah/razorpay/paypal/stripe)

**Manter todos**. Decisão arquitetural do UltimatePOS é multi-gateway (cada mercado geográfico usa o seu). Remover quebra clientes internacionais.

**Ação única**: bumpar `stripe/stripe-php` de `^7.122` (2022) pra versão atual (v16+). Stripe mudou API, precisa testar SubscriptionController antes de ativar em produção. Criar ADR arq/0009 dedicado.

### SMS — aloha/twilio

**Manter**. Usado pra notificação de reparo pronto (ver ADR Repair TECH-0001), avisos de faturamento, etc. Versão ^4.0 ainda mantida.

### consoletvs/charts (abandonado 2020)

**Substituir**. Lib arquivada no GitHub, sem suporte Laravel 10+. Usos concentrados em 5 arquivos:
- `app/Charts/CommonChart.php` (wrapper vazio sobre Highcharts)
- `ReportController.php` (3 usos)
- `HomeController.php` (3 usos)
- `SuperadminController.php` (2 usos)
- `Modules/Repair/Utils/RepairUtil.php` (4 usos)

**Alternativas**:
- **Chart.js via CDN + Inertia props**: leve, padrão indústria, compat total.
- **ApexCharts**: bonito, responsivo, mas bundle maior.
- **Recharts (React)**: combina com migração Inertia.

**Recomendação**: Chart.js direto. Criar `CommonChart.php` wrapper novo que serializa dados pra `Inertia::share('charts', ...)` e frontend React renderiza. ADR arq/0010 dedicado.

### mpdf/mpdf

**Substituir** por `barryvdh/laravel-dompdf` (já instalado no projeto — `^2.2` no composer.json).

7 usos mapeados:
- `app/Utils/TransactionUtil.php` — recibos/nota fiscal
- `app/Http/Controllers/PurchaseOrderController.php` — PO PDF
- `app/Http/Controllers/LabelsController.php` — etiquetas
- `app/Http/Controllers/ContactController.php` — contas a receber/pagar
- `app/Http/Controllers/Controller.php` — base
- `Modules/Repair/Http/Controllers/JobSheetController.php` — ordens de serviço
- `Modules/Crm/Http/Controllers/LedgerController.php` — extrato CRM

Dompdf tem API diferente. Migração caso-a-caso. ADR arq/0011 dedicado.

## Consequências

**Positivas:**
- Nenhum gateway quebra — clientes internacionais preservados.
- 3 sub-ADRs propostos (arq/0009 Stripe bump, arq/0010 Charts substituição, arq/0011 mpdf→dompdf) mapeiam ações específicas.
- ADR arq/0002 fica com classificação honesta pós-investigação.

**Negativas:**
- Nenhum P3 resolvido automaticamente nesta rodada.
- 3 sub-ADRs exigem sessões dedicadas de migração.

**Trade-off consciente**: rigor na análise evita quebra em produção. Migração de lib = reescrita parcial, precisa planejamento.

## Update no ADR arq/0002

Reclassificação final dos 9 blockers originais:

| # | Blocker | Original | **Final após investigação** |
|---|---|---|---|
| 1 | nwidart/laravel-menus | P0 | **P0** (ADR arq/0003) |
| 2 | yajra/datatables | P0 | **~~P0~~ ✅ RESOLVIDO** (v10.11 aceita L9+L10, bumpado) |
| 3 | monolog v2→v3 | P0 | **~~P0~~ Transitivo automático** |
| 4 | arcanedev/support | P1 | **P1 bloqueado** (v10 só L10) |
| 5 | milon/barcode | P1 | **~~P1~~ ✅ RESOLVIDO** (v10 aceita L7-L10, bumpado) |
| 6 | spatie/laravel-ignition | P1 | **P1 bloqueado** (v2 só L10+) |
| 7 | shalvah/upgrader | P2 | **~~P2~~ Já removido** |
| 8 | unicodeveloper/paystack | P2 | **~~P2~~ ✅ RESOLVIDO** (removido) |
| 9 | openai-php/laravel | P3 | **~~P3~~ Já compatível L10.4+** |

**Novo status da Fase 2 Laravel 10:**

**Resolvidos** (5/9): yajra, monolog transitivo, shalvah, paystack, openai
**Bloqueado aguardando framework** (2/9): arcanedev/support, spatie/ignition
**P0 restante** (1/9): nwidart/laravel-menus (execução: ADR arq/0003)
**Rastreabilidade adicional** (este ADR): consoletvs/charts + mpdf + stripe bump = sub-projetos

## Alternativas consideradas

- **Remover gateways não-usados pelo cliente Wagner**: rejeitado — sistema comercial (UltimatePOS) vende pra clientes com gateways diferentes. Forkar pode gerar merge conflicts no upstream.
- **Manter tudo congelado até Laravel 14**: rejeitado — dívida rola sem limite.

## Sinais de conclusão

- [ ] ADR arq/0009: plano de bump `stripe/stripe-php v7 → v16+`
- [ ] ADR arq/0010: plano de substituição `consoletvs/charts → Chart.js` (5 arquivos)
- [ ] ADR arq/0011: plano de migração `mpdf → dompdf` (7 arquivos)
- [ ] `composer why consoletvs/charts` retorna vazio
- [ ] `composer why mpdf/mpdf` retorna vazio
