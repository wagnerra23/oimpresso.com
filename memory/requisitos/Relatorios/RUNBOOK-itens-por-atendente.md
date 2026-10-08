---
slug: relatorios-runbook-itens-por-atendente
title: "Relatórios — Runbook do relatório Equipe de serviço, aba itens por atendente"
type: runbook
module: Relatorios
tela: Relatorios/ItensPorAtendente/Index
owner: W
status: ativo
last_validated: "2026-10-08"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Equipe de serviço, aba "itens por atendente"

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`service_staff_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/service-staff-line-orders?tela=nova` responde Inertia `Relatorios/ItensPorAtendente/Index`: uma linha por
item vendido com atendente (data, venda, atendente, produto, quantidade, preço, desconto, imposto, preço com imposto,
total), da mesma consulta do DataTable da aba da Blade, 25 por página, e o rodapé da página como o da Blade. Sem
`?tela=nova`, o endpoint segue sendo só o JSON do DataTable.

## 2. Pré-condições

Permissão `sales_representative.view` — a da página da Blade (`getServiceStaffReport`). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/ItensPorAtendenteReportPageTest.php`: quantidade, desconto e total por três
   caminhos (o JSON do DataTable da Blade, as props da Page e a conta direta em `transaction_sell_lines`), a paginação e
   o isolamento 98 × 99.
3. **F3** — a consulta sai de `serviceStaffLineOrders()` para `consultaItensPorAtendente()`. O ramo `tela=nova` vem
   primeiro, com a permissão da página.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a aba da Blade (período padrão: mês corrente).

## 4. Divergências declaradas e achados

- A outra aba ("pedidos por atendente") vem do `SellController::index` (`/sells`), fora do `ReportController` — não entra
  nesta thread.
- **Achado (não corrigido, decisão da gerência):** o endpoint JSON `/reports/service-staff-line-orders` não confere
  permissão nenhuma (a página que o usa exige `sales_representative.view`) e não filtra pelos locais permitidos do
  usuário. Qualquer usuário logado do negócio lê os itens por atendente de todos os locais. O isolamento entre negócios
  (`business_id`) está certo. A tela nova já exige a permissão da página.

## 5. Falta para o cutover (F5 — decisão [W])

A outra aba, a decisão sobre o achado do §4, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
