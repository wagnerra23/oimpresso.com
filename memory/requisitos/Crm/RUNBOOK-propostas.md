---
slug: crm-runbook-propostas
title: "Crm — Runbook da tela Propostas"
type: runbook
module: Crm
tela: Crm/Propostas/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Propostas do CRM (`/crm/proposals`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `crm/playbook/14`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Propostas()` (rota `crm-propostas`).
> Alvo medido: `governance/design/targets/crm--propostas--index.alvo.json` (A2, #9255) — seções header · nav · toolbar · grade.
> **Valor:** a proposta é e-mail (assunto, corpo, cc, bcc, anexos). `crm_proposals` não tem coluna de valor; nada aqui
> calcula nem grava valor.

## Estado final esperado

`GET /crm/proposals` (`ProposalController::index`) responde Inertia `Crm/Propostas/Index` **quando a flag
`useV2CrmPropostas` está ligada** para o negócio; desligada (default) ou com `?classico=1`, segue a Blade
`crm::proposal.index`. A lista mostra contato, assunto, quem enviou, data e quantos anexos; o detalhe abre num
drawer PT-02 (assunto, cc, bcc, corpo, anexos).

## 1. Objetivo

Trocar a Blade (DataTable + modal `proposal.show`) pela tela React sem mudar quem vê o quê.

## 2. Pré-condições

- Módulo `crm_module` na assinatura (ou superadmin) — é a única trava de leitura do `index()` e do `show()`.
- Botão "Enviar" só aparece com modelo cadastrado (`ProposalTemplate` do negócio) **e** `crm.access_proposal`.
- Tabelas `crm_proposals` (`business_id`, `contact_id`, `sent_by`, `subject`, `body`, `cc`, `bcc`) e `media`
  (morph `model_type = Proposal`).

## 3. Passo-a-passo

1. **F1 (este PR):** RUNBOOK + [`propostas-parity.md`](./propostas-parity.md).
2. **F2:** `CrmPropostasBaselineTest` trava a Blade de hoje (98 × 99, só as próprias para quem não é `Admin#`,
   detalhe do vizinho não abre, botão Enviar condicionado).
3. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable) + Page + charter +
   casos + contrato `crm-propostas`. Enviar e Modelo de proposta seguem no `ProposalTemplateController` (Blade).
4. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS, PageHeader canon. Estados: lista com propostas · vazio · sem modelo (sem botão Enviar). Tabela rola
abaixo de 768px. `data-contract`: `crm-propostas`, `crm-toolbar` (os do alvo).

## 9. DoD checklist

- [x] RUNBOOK + paridade (F1)
- [ ] Pest baseline tenant 98 × 99 (F2)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos + contrato (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção da Blade `proposal/index` (F5, decisão [W])

## 10. Pegadinhas e divergências

- **Escopo da lista:** quem não é `Admin#<negócio>` vê só as que **ele** enviou (`sent_by`). O `show()` da Blade
  **não** aplica esse filtro — qualquer usuário do negócio abre qualquer proposta pelo id. O drawer da F3 lê com o
  mesmo escopo da lista (mesma regra da D5 para acompanhamentos: o detalhe segue a leitura); a Blade fica como está.
- **Corpo é HTML** (`{!! $proposal->body !!}` na Blade, vem do editor do modelo). O drawer não injeta HTML cru:
  sanitiza antes de renderizar (ou mostra como texto). Mudança de render, não de dado.
- **`store()` sem modelo:** `$proposal_template->media` em template nulo lança exceção, cai no `catch` e devolve
  "algo deu errado". Fora do escopo das telas (o envio é Blade) — registrado, não consertado aqui.
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita (§5 2026-09-08).
- O protótipo tem coluna "Anexos" e kebab com Ver/Reenviar/Excluir anexo. A Blade só tem "Ver". Reenviar e excluir
  anexo não existem no controller → ficam fora da F3 (não se inventa ação sem endpoint).
- "Modelo de proposta" e "Mandar" do protótipo levam às telas do `ProposalTemplateController` (fora do prefixo).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
