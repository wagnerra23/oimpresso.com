---
slug: crm-runbook-configuracoes
title: "Crm — Runbook da tela Configurações do CRM"
type: runbook
module: Crm
tela: Crm/Configuracoes/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Configurações do CRM (`/crm/settings`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `crm/playbook/15`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Config()` (rota `crm-config`).
> Alvo medido: `governance/design/targets/crm--config--index.alvo.json` (A2, #9255) — seções header · nav · campos · acoes.
> **Segredo:** nenhum. As duas chaves (`enable_order_request`, `order_request_prefix`) são opção de negócio,
> não credencial — o molde "só de escrita" do #9217 não se aplica aqui. **Valor:** nenhum.

## Estado final esperado

`GET /crm/settings` (`CrmSettingsController::index`) responde Inertia `Crm/Configuracoes/Index` **quando a flag
`useV2CrmConfiguracoes` está ligada** para o negócio; desligada (default) ou com `?classico=1`, segue a Blade
`crm::settings.index`. A tela grava pelo MESMO `POST /crm/update-settings` da Blade (sem endpoint novo).

## 1. Objetivo

Trocar o formulário Blade pelo React sem mudar quem pode abrir, o que grava nem onde grava.

## 2. Pré-condições

- Abrir e gravar: `superadmin`, **ou** `crm_module` na assinatura **e** papel `Admin#<negócio>`. Qualquer outro → 403.
- Coluna `business.crm_settings` (texto JSON). Lida por `CrmUtil::getCrmSettings` e, fora desta tela, pelo
  `OrderRequestController` (prefixo do pedido), pelo `DataController` e pelo middleware `ContactSidebarMenu`
  (`enable_order_request` liga o menu de pedido no portal do contato).

## 3. Passo-a-passo

1. **F1 (este PR):** RUNBOOK + [`configuracoes-parity.md`](./configuracoes-parity.md).
2. **F2:** `CrmConfiguracoesBaselineTest` trava a Blade de hoje (403 para quem não é `Admin#`, gravação só no
   `business` da sessão, checkbox ausente = desligado, o JSON gravado tem só as duas chaves).
3. **F3:** `index()` ganha o ramo Inertia atrás da flag + Page + charter + casos + contrato `crm-config`. O form
   posta no `update-settings` de hoje.
4. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS, PageHeader canon. Estados: carregado · gravando · gravado (toast). `data-contract`: `crm-config`.

## 9. DoD checklist

- [x] RUNBOOK + paridade (F1)
- [ ] Pest baseline tenant 98 × 99 (F2)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos + contrato (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção da Blade `settings/index` (F5, decisão [W])

## 10. Pegadinhas e divergências

- **`updateSettings` sobrescreve o JSON inteiro** com `only(['order_request_prefix'])` + `enable_order_request`
  quando marcado. Hoje nenhum outro código grava `crm_settings`, então não há chave a perder — mas qualquer chave
  nova nesse JSON exige mudar para merge antes de entrar.
- **Checkbox desmarcado não é enviado** pelo form HTML: ausência = desligado. A tela nova manda o campo só quando
  ligado (ou o backend continua lendo `has()`), para não ligar por engano com `enable_order_request=0`.
- `is_admin(auth()->user())` sem `business_id` usa o `business_id` do usuário — o mesmo da sessão no login normal.
- `business_id` vem da sessão, nunca do form: um `business_id` no corpo do POST é ignorado.
- O protótipo traz o placeholder `SO-` e o texto de ajuda do checkbox; a Blade mostra a ajuda num tooltip.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
