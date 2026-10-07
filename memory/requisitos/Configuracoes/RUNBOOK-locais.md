---
slug: configuracoes-runbook-locais
title: "Configurações — Runbook da tela Locais comerciais"
type: runbook
module: Configuracoes
tela: Configuracoes/Locais/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Locais comerciais (`/business-location`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/04`, tela 3 de 3
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Locais()` (rota `cfg-locais`).
> **Decisão D1 ([W] 2026-10-06):** Configurações com abas; cada aba mantém URL e permissão próprias.

## Estado final esperado

`GET /business-location` (`BusinessLocationController::index`) responde Inertia `Configuracoes/Locais/Index` **quando a
flag `useV2ConfiguracoesLocais` está ligada** para o negócio; desligada, segue a Blade `business_location/index`.
Cadastrar e editar abrem drawer; ativar/desativar é ação da linha. Tudo grava pelos endpoints de sempre.

## 1. Objetivo

Trocar a Blade (DataTable + modais) pela aba React, sem mudar o que é gravado, a quota do pacote nem a permissão.

## 2. Pré-condições

- Permissão: `business_settings.access` (a mesma da Configuração da empresa — o legado não separa).
- A lista ainda filtra por `permitted_locations()`: sem `access_all_locations`, só os locais com permissão
  **direta** `location.<id>` no usuário (a do papel não conta — `$user->permissions`).
- Cadastrar exige assinatura ativa e quota de locais do pacote (`ModuleUtil::isQuotaAvailable('locations')`).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`locais-parity.md`](./locais-parity.md) + `LocaisBaselineTest`.
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: lista com ativo/inativo · vazio · busca sem resultado · drawer novo/editar · quota esgotada ·
aviso da última ação. Tabela rola abaixo de 768px. `/` busca · `n` novo. `data-contract`: `page-header`, `toolbar`,
`locais-table`, `vazio`, `local-form`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Smoke biz=1 (F4) · cutover e remoção das Blades `business_location/*` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- `destroy()` é vazio: não existe excluir local, só ativar/desativar. O protótipo já diz isso.
- `store()` cria a permissão `location.<id>`, que aparece nas Funções.
- `update()` de local de outro negócio não altera nada **e responde `success: true`** (o `where` acha 0 linhas). A tela
  nova só edita o que a lista trouxe; o resposta enganosa fica registrada, não muda aqui.
- `zip_code` é `char(7)` no schema: CEP com hífen (9 caracteres) não cabe.
- "Configurações de recibo" do protótipo é outra tela (`location_settings`, `LocationSettingsController`) — fora da thread.
- Formas de pagamento por local (`default_payment_accounts`) viajam como JSON e o controller faz `json_encode`.
- Inertia v3 manda `X-Requested-With`: sem `! $request->inertia()` o ramo DataTable engole a visita.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
