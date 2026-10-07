---
slug: relatorios-runbook-grupos-clientes
title: "Relatórios — Runbook do relatório Grupos de clientes"
type: runbook
module: Relatorios
tela: Relatorios/GruposClientes/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Grupos de clientes

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`customer_group`,
> tabela grupo × total vendido) · 3º relatório da thread.

## 1. Objetivo

`GET /reports/customer-group?tela=nova` responde Inertia `Relatorios/GruposClientes/Index` com as linhas da
mesma consulta do DataTable da Blade (vendas finais somadas por grupo). Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `contacts_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/GruposClientesReportPageTest.php`: linhas por três caminhos (o JSON do
   DataTable da Blade, as props da Page e a soma direta em `transactions`) e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaGrupoDeClientes()`, chamada pelos dois ramos. O ramo
   `tela=nova` vem antes do `ajax()` (Inertia manda `X-Requested-With`).
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando as linhas com a Blade no mesmo período.

## 4. Divergências declaradas

- Nenhum total no rodapé: a Blade não tem, e a tela não inventa.

## 5. Falta para o cutover (F5 — decisão [W])

Aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
