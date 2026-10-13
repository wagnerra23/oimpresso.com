---
title: "RUNBOOK — Relatórios do CRM (Blade → Inertia)"
module: Crm
tela: Crm/Relatorios/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Migração MWART /crm/reports → Crm/Relatorios/Index

> Thread `crm/playbook/13` · processo [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md).
> Fonte de design: `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Relatorios()` (rota `crm-relatorios`).
> Alvo medido: `governance/design/targets/crm--relatorios--index.alvo.json` (thread A2) — 6 seções: header · nav · filtros · por_usuario · por_contato · conversao.

## 1. Tela
- **Legacy:** Blade `crm::reports.index` com 3 DataTables (`report_javascripts.blade.php`) e o modal `leads_to_customer_details`.
- **Inertia (F3):** `Modules/Crm/Resources/js/Pages/Crm/Relatorios/Index.tsx` (+ charter + casos ao lado).
- **Controller:** `Modules\Crm\Http\Controllers\ReportController` — `index` + 4 endpoints ajax:
  `followUpsByUser` (`/crm/follow-ups-by-user`), `followUpsContact` (`/crm/follow-ups-by-contact`),
  `leadToCustomerConversion` (`/crm/lead-to-customer-report`), `showLeadToCustomerConversionDetails` (`/crm/lead-to-customer-details/{user_id}`).

## 2. Estado final esperado
`GET /crm/reports` responde Inertia `Crm/Relatorios/Index` **só quando a flag `useV2CrmRelatorios` está ligada** para o negócio.
Desligada (default), segue a Blade. Os 4 endpoints ajax continuam servindo a Blade sem mudança.

## 3. Fatias (1 PR por fase, ≤300 linhas)
| fase | o quê | estado |
|---|---|---|
| F1 | este RUNBOOK + [`relatorios-parity.md`](./relatorios-parity.md) | este PR |
| F2 | `CrmRelatoriosBaselineTest` (tenant 98 × 99) na lane `verticais-pest` | pendente |
| F3 | ramo Inertia atrás da flag + Page (filtros · por usuário · por contato · conversão + drawer "quem foi convertido") + charter + casos + contrato | pendente |
| F4 | smoke biz=1 com a flag só para biz=1 | pendente |
| F5 | cutover — decisão [W] | — |

## 4. Escopo da F3 (só leitura)
- As três matrizes viajam como props **deferidas**, montadas pelas mesmas consultas dos endpoints (status de `Schedule::statusDropdown()` + "Outros" + total).
- Filtros: intervalo de datas e categoria — aplicados à matriz por usuário, como na Blade.
- Conversão: clicar no usuário abre drawer PT-02 com os contatos convertidos por ele (mesma consulta do `showLeadToCustomerConversionDetails`).
- Os links "Ver" da matriz por usuário levam à lista de Acompanhamentos com os mesmos filtros (`status`, datas, `assigned_to`, categoria).

## 5. Multi-tenant (Tier 0)
- `index` exige `is_admin` do negócio da sessão.
- Todas as consultas partem do `business_id` da sessão (`users.business_id` / `contacts.business_id`).
- ⚠️ Os 4 endpoints ajax **não** checam permissão nenhuma — só que a chamada é ajax. Ver paridade §Achados (C1). A Page da F3 não depende deles.

## 6. Pegadinhas
- **Inertia × DataTables:** decidir pelo `X-Inertia`, não por `ajax()` (§5 2026-09-08).
- A chave de status é `cancelled` (código) — o protótipo escreve `canceled`.
- O protótipo nomeia a permissão `crm.view_reports`; o código usa `is_admin`. A F3 segue o código.
- As datas do filtro passam por `Util::format_date` para montar o link "Ver"; a consulta usa o valor cru.

## 7. Teste
`Modules/Crm/Tests/Feature/CrmRelatorios*Test.php`, lane `verticais-pest` (MySQL), tenant 98 (99 como "outro negócio"). Nunca local.

## 8. Rollback
Flag OFF devolve a Blade. F1/F2 não tocam código de produção.
