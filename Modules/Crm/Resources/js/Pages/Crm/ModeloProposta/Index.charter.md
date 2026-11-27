---
id: modules-crm-pages-crm-modeloproposta-index-charter
page: /crm/proposal-template
component: Modules/Crm/Resources/js/Pages/Crm/ModeloProposta/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
runbook: memory/requisitos/Crm/RUNBOOK-modelo-proposta.md
owner: felipe
status: draft
last_validated: "2026-11-27"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 2
---

# Page Charter — /crm/proposal-template (DRAFT · modelo de proposta)

> **Status:** draft. Onda 1 do [RUNBOOK](../../../../../../../memory/requisitos/Crm/RUNBOOK-modelo-proposta.md)
> (`ProposalTemplateController@index`, Blade → Inertia), atrás da flag `useV2CrmModeloProposta`, ligada por
> padrão por decisão [F] 2026-11-27. Casos: [`Index.casos.md`](Index.casos.md). Non-Goals e Anti-hooks além dos
> abaixo são do dono.

## Mission

Mostrar o **modelo único de proposta da empresa** (assunto, cc, bcc, corpo, anexos) e levar a quem pode a
editar, exibir ou mandar a proposta a partir dele.

## Goals — Features (faz, nesta fase)

- Com modelo: assunto, anexos, cc, bcc, corpo em texto e "atualizado em".
- Sem modelo: o aviso "Nenhum modelo de proposta cadastrado"; "Adicionar" só com `crm.add_proposal_template`.
- Editar só com `crm.add_proposal_template`; Exibir e Mandar só com `crm.access_proposal` (as regras da Blade).
- `?classico=1` abre a Blade.

## Non-Goals (não faz, nesta fase)

- ❌ Editar, criar ou mandar dentro desta tela — Ondas 2 e 3; os botões abrem as telas Blade do mesmo controller.
- ❌ Mostrar o corpo como HTML: a tela recebe só o texto (o HTML vem do editor e não é injetado cru).

## Automation Anti-hooks

- ❌ Ler o modelo por id vindo da request: é sempre o da empresa da sessão (`business_id`).
