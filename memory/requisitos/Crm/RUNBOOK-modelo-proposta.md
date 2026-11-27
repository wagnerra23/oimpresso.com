---
slug: crm-runbook-modelo-proposta
title: "Crm — Runbook da tela Modelo de proposta"
type: runbook
module: Crm
tela: Crm/ModeloProposta/Index
owner: F
status: ativo
last_validated: "2026-11-27"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Modelo de proposta do CRM (`/crm/proposal-template`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
> **Origem:** triagem do Grupo 2, decisão [F] 2026-11-27: *"Tem que ligar a tela nova em react, não em blade."*
> **Fonte de design:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Modelo()` (rota `crm-modelo`).
> **Valor:** o modelo é e-mail (assunto, corpo, cc, bcc, anexos). `crm_proposal_templates` não tem coluna de
> valor; nada aqui calcula nem grava valor. O `{{valor}}` do protótipo é texto do corpo, não campo.

## Estado final esperado

`GET /crm/proposal-template` (`ProposalTemplateController::index`) responde Inertia `Crm/ModeloProposta/Index`
quando a flag `useV2CrmModeloProposta` está ligada para o negócio (default ligado, decisão [F]); com `?classico=1`
segue a Blade `crm::proposal_template.index`. A tela mostra o modelo da empresa (assunto, cc, bcc, corpo em texto,
anexos e data de atualização) com Editar · Exibir · Mandar, ou o aviso "nenhum modelo" com Adicionar.

## 1. Objetivo

Trocar a Blade (caixa com o assunto e três botões) pela tela React do protótipo, sem mudar quem vê o quê.

## 2. Pré-condições

- Leitura: módulo `crm_module` na assinatura, ou superadmin (mesma trava do `index()`).
- Editar e Adicionar: `crm.add_proposal_template`. Exibir e Mandar: `crm.access_proposal`.
- Um modelo por empresa: `ProposalTemplate::where('business_id', …)->first()`. Anexos em `media` (morph).

## 3. Passo-a-passo

1. **Onda 1 (este PR):** RUNBOOK + ramo Inertia atrás da flag + Page + charter + casos + teste de contrato
   (98 × 99). Editar, Exibir, Mandar e Adicionar abrem as telas Blade do mesmo controller.
2. **Onda 2:** editar o modelo dentro da tela React (assunto, corpo, cc, bcc, anexos), substituindo `getEdit`.
3. **Onda 3:** criar o modelo e mandar a proposta em React (`create` e `send`).

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS, cabeçalho `CrmCabecalho` (mesmo das demais telas do CRM). Estados: com modelo · sem modelo (com e
sem `crm.add_proposal_template`). Uma coluna abaixo de 768px. `data-contract`: `crm-modelo`.

## 9. DoD checklist

- [x] RUNBOOK + paridade (abaixo)
- [x] Ramo Inertia atrás da flag + Page + charter + casos + teste (Onda 1, UC-CRMMOD-01..05)
- [ ] Smoke biz=1 em produção
- [ ] Ondas 2 e 3 (edição, criação e envio em React)

## 10. Paridade com a Blade (o que a Onda 1 preserva)

| # | Blade hoje | React (Onda 1) |
|---|---|---|
| 1 | Sem `crm_module` e sem superadmin → 403 | mesmo portão, antes do ramo |
| 2 | Com modelo: mostra o **assunto** | assunto + cc + bcc + corpo em texto + anexos + atualizado em |
| 3 | Botão Editar só com `crm.add_proposal_template` | idem (`pode_editar`) |
| 4 | Exibir e Enviar só com `crm.access_proposal` | idem (`pode_mandar`); rótulo "Mandar" do protótipo |
| 5 | Sem modelo e com `crm.add_proposal_template` → "Adicionar" | idem; sem a permissão, só o aviso |
| 6 | Sem modelo → "Nenhum modelo encontrado" | mesmo aviso |

## 11. Pegadinhas

- **Corpo é HTML** do editor (a Blade usa `{!! !!}` no `view`). A tela recebe só o texto, como no drawer de
  Propostas (`ProposalController::detalheProposta`): fim de bloco vira quebra de linha, nada de HTML cru.
- **Escopo:** o modelo é lido sempre pelo `business_id` da sessão; nunca por id vindo da request.
- Inertia v3 manda `X-Requested-With`; o `index()` não tem ramo DataTable, então não há o que engolir a visita.
- O protótipo edita inline; a Onda 1 leva à Blade de edição. Não se inventa endpoint JSON nesta onda.

## 12. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
