---
module: Configuracoes
status: em-construcao
status_nota: "Telas de configuração do núcleo (Impressoras, Código de barras, Locais) migrando de Blade para React, uma por vez, atrás de flag."
updated_at: "2026-10-07"
owner: W
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
lifecycle: ativo
---

# BRIEFING — Configurações (núcleo) · 🟡 em construção

> **Não é módulo `Modules/<X>`.** São as telas de configuração do núcleo UltimatePOS (`app/Http/Controllers/`),
> agrupadas porque a decisão D1 ([W] 2026-10-06) fez de Configurações **uma tela com abas**, como o protótipo
> `configuracoes-cadastros.jsx`. Cada aba mantém a própria URL e a própria permissão.

## Estado (thread `sistema/playbook/04`)

| Aba | Rota | Controller | Permissão | Flag do caminho React | RUNBOOK |
|---|---|---|---|---|---|
| Impressoras | `/printers` | `PrinterController` | `access_printers` | `useV2ConfiguracoesImpressoras` | `RUNBOOK-impressoras.md` |
| Código de barras | `/barcodes` | `BarcodeController` | `barcode_settings.access` | `useV2ConfiguracoesCodigoBarras` | `RUNBOOK-codigo-barras.md` |
| Locais comerciais | `/business-location` | `BusinessLocationController` | `business_settings.access` | `useV2ConfiguracoesLocais` | `RUNBOOK-locais.md` |

Cada aba passa por F1/F2 (RUNBOOK + paridade + Pest baseline) e F3 (Page atrás da flag, default desligada). O estado de
cada uma é o dos PRs da thread, não desta tabela. As abas da thread 05 (Esquemas de fatura, Impostos, Tipos de serviço)
entram aqui quando ela começar. Cutover de qualquer aba é decisão [W].
