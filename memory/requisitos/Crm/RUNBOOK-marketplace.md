---
slug: crm-runbook-marketplace
title: "Crm — Runbook da tela Marketplace B2B"
type: runbook
module: Crm
tela: Crm/Marketplace/Index
owner: F
status: ativo
last_validated: "2026-11-27"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Marketplace B2B do CRM (`/crm/b2b-marketplace`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
> **Origem:** triagem do Grupo 2. Decisões [F] 2026-11-27: ligar o Marketplace B2B (*"Sim, pode ligar. Preciso ver
> a tela em produção para poder testar"*) e fazê-lo em React (*"Tem que ligar a tela nova em react, não em blade"*).
> **Fonte de design:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Marketplace()` (rota `crm-marketplace`).
> **Valor/estoque:** nada. A tela guarda credenciais do marketplace e importa leads.

## Estado final esperado

`config('constants.enable_b2b_marketplace')` passa a `true`. `GET /crm/b2b-marketplace`
(`CrmMarketplaceController::index`) responde Inertia `Crm/Marketplace/Index` quando a flag `useV2CrmMarketplace`
está ligada (default ligado); com `?classico=1`, a Blade `crm::marketplace.index`. A tela mostra a configuração da
integração Exporters India (chave, e-mail da conta, usuários a quem o acompanhamento é atribuído, fonte dos leads),
salva pelo mesmo `POST /crm/save-marketplace` e importa leads pelo mesmo `GET /crm/import-leads`.

## 1. Objetivo

Ligar a integração que estava desligada para todas as empresas e trocar a Blade pela tela do protótipo.

## 2. Pré-condições

- Só Admin do negócio (`Util::is_admin`) e só com `enable_b2b_marketplace` — mesma trava nas três ações.
- `crm_marketplaces` com `HasBusinessScope` (o `first()` já é do negócio da sessão).

## 3. Passo-a-passo

1. **Onda 1 (este PR):** RUNBOOK + config ligada + ramo Inertia + Page + charter + casos + teste (98 × 99) +
   endurecimento do `save()` e do `importLeads()` (§11).
2. Depois do merge: aba "Marketplace B2B" no topo do CRM.

## 9. DoD checklist

- [x] RUNBOOK + paridade (abaixo)
- [x] Config ligada + ramo Inertia + Page + charter + casos + teste (UC-CRMMKT-01..05)
- [ ] Aba no CRM
- [ ] Smoke biz=1 em produção (configurar e importar com uma conta real do marketplace)

## 10. Paridade com a Blade

| # | Blade hoje | React (Onda 1) |
|---|---|---|
| 1 | Não-Admin ou integração desligada → 403 | mesmo portão, antes do ramo |
| 2 | Formulário: chave, e-mail, usuários atribuídos (vários), fonte | idem |
| 3 | Salvar → `POST save-marketplace`, volta para a tela | idem |
| 4 | Importar leads → `GET import-leads`, volta com o aviso | idem |

## 11. Endurecimento (muda também para a Blade)

- **`save()` validava nada.** Passa a exigir chave e e-mail, e a aceitar só usuários **do negócio da sessão** em
  "atribuído a" e só fonte (`category_type = source`) **do negócio**. Sem isso, a importação criaria leads e
  acompanhamentos atribuídos a usuários de outra empresa (Tier 0, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **`importLeads()` devolvia ao usuário o arquivo, a linha e a mensagem da exceção.** Passa a devolver uma mensagem
  genérica; o detalhe segue no log, já redigido pelo `PiiRedactor`.
- Sem integração configurada, `importLeads()` lançava erro em `null->site_key`: passa a avisar "configure antes".

## 12. Pegadinhas

- Exporters India é um marketplace da Índia; a tela não promete outros marketplaces (o Blade grava sempre
  `marketplace = exportersindia`).
- `assigned_users` é array (cast) e alimenta `CrmContact::createNewLead`.

## 13. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
