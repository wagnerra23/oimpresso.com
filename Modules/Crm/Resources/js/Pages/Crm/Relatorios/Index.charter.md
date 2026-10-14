---
id: modules-crm-pages-crm-relatorios-index-charter
page: /crm/reports
component: Modules/Crm/Resources/js/Pages/Crm/Relatorios/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 1
---

# Page Charter — /crm/reports (DRAFT · relatórios do CRM, atrás da flag)

> **Status:** draft. Nasce na thread Crm/13 (`ReportController@index`, Blade → Inertia), atrás da
> flag `useV2CrmRelatorios` (default OFF). Casos: [`Index.casos.md`](Index.casos.md) · contrato:
> `governance/design/contracts/crm-relatorios.contract.json` · RUNBOOK:
> `memory/requisitos/Crm/RUNBOOK-relatorios.md`. Non-Goals e Anti-hooks além dos abaixo são de [W].

## Mission

Dar ao dono do negócio **a visão do funil comercial**: quantos acompanhamentos cada usuário e cada
contato têm por status, e quem converteu lead em cliente — com o detalhe de quem foi convertido.

## Goals — Features (faz, nesta fase)

- Matriz "Acompanhamentos por usuário": status de `Schedule::statusDropdown()` + Outros + total.
- Matriz "Acompanhamentos por contatos", ordenada pelo total (100 primeiros).
- "Leads convertidos em cliente" por usuário; clicar abre o drawer com os convertidos, mais recente primeiro.
- Filtros: intervalo de datas e categoria de acompanhamento, aplicados à matriz por usuário (como na Blade).
- Portão **igual ao da Blade**: só o admin do negócio da sessão.

## Non-Goals (nesta fase)

- ❌ Trocar `is_admin` por `crm.view_reports` (achado C3): decisão do [W].
- ❌ Aplicar os filtros às matrizes por contato e conversão (achado C2): a Blade não aplica.
- ❌ Ligar a flag em produção: cutover do [W].

## Anti-hooks

- ❌ Montar consulta própria: as matrizes saem das mesmas consultas dos endpoints da Blade.
  Guardas: UC-CRMREL-02, 04 e 05.
- ❌ Mostrar dado de outro negócio no detalhe da conversão pelo id do usuário. Guarda: UC-CRMREL-05.
- ❌ Abrir a tela para quem não é admin. Guarda: UC-CRMREL-01.
