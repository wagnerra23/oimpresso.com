---
slug: woocommerce-runbook-configuracao
title: "Woocommerce — Runbook da tela Configuração da API"
type: runbook
module: Woocommerce
tela: Woocommerce/Configuracao/Index
owner: W
status: ativo
last_validated: "2026-10-14"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Configuração da API do WooCommerce (`/woocommerce/api-settings`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `woocommerce/playbook/02`.
> **Fonte de design:** alvo `governance/design/targets/woocommerce--api--index.alvo.json` (thread A1), medido sobre
> `prototipo-ui/cowork/Wagner/integra-extras.jsx` → `WooCommercePage`, aba Configuração.
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /woocommerce/api-settings` (`WoocommerceController::apiSettings`) responde Inertia `Woocommerce/Configuracao/Index`
**quando a flag `useV2WoocommercePainel` está ligada** para o negócio — a mesma flag do painel (thread 01), para o
módulo virar de uma vez só. Desligada (default), ou com `?classico=1`, segue a Blade `woocommerce::woocommerce.api_settings`.
O formulário salva no mesmo `POST /woocommerce/update-api-settings` (`updateSettings`).

## 1. Objetivo

Trocar a configuração da loja (endereço, local do estoque, sincronização automática, campos do produto, status dos
pedidos, webhooks) no desenho do alvo da A1, **sem que as chaves da loja cheguem à página**.

## 2. Pré-condições

- Permissão `woocommerce.access_woocommerce_api_settings` (`WoocommerceAuthorizationService::ensureAcao`).
- Lane `acessos-pest.yml` (MySQL) passa a rodar `Modules/Woocommerce/Tests/Feature/Configuracao*Test.php`.

## 3. O que a Blade faz hoje (lido em `WoocommerceController::apiSettings` / `updateSettings`)

- `apiSettings` decodifica `business.woocommerce_api_settings` (JSON) e entrega **tudo** à Blade, inclusive
  `woocommerce_consumer_key` (campo texto, em claro) e `woocommerce_consumer_secret` (campo senha, com o valor no HTML).
- Os 4 segredos de webhook (`woocommerce_wh_oc/ou/od/or_secret`) vêm das colunas de `business` e aparecem em campo
  texto. A Blade só preenche os 4 se `woocommerce_wh_oc_secret` estiver preenchido (a condição é a mesma nos 4 —
  comportamento atual).
- `updateSettings` grava `$request->except('_token')` inteiro como o JSON e copia os 4 segredos de webhook para as
  colunas. Campo de segredo enviado vazio **apaga** o segredo salvo.
- Grupos de preço vêm de `SellingPriceGroup` do negócio; locais, de `BusinessLocation::forDropdown` do negócio.

## 4. Passo-a-passo

1. F2 — `ConfiguracaoBaselineTest` trava a Blade e o salvar de hoje: a página lista grupos de preço só do negócio da
   sessão; o `POST` grava no negócio da sessão (o outro não muda) e campo de segredo vazio apaga o salvo.
2. F3 — ramo Inertia no `apiSettings`, com cada segredo trocado por um booleano (preenchido ou não).
3. F3 — `updateSettings` aceita `manter_segredos=1`, que só a tela React envia: com ele, segredo vazio **mantém** o
   salvo e o marcador não entra no JSON. Sem o marcador (Blade), nada muda.
4. F3 — `Woocommerce/Configuracao/Index.tsx` + charter + casos + `ConfiguracaoContratoTest`.
5. F4 — smoke em biz=1 com a flag ligada. F5 — cutover, decisão [W].

## 5. Tier 0 — segredos

- Nenhum dos 6 segredos (consumer key, consumer secret, 4 de webhook) vai em prop, nem com parte do valor: a tela
  recebe só se cada um está preenchido.
- Trocar é escrever um valor novo; deixar vazio mantém. A tela nova não apaga segredo (a Blade apaga).
- Nada de segredo em log: o `updateSettings` só loga a exceção.

## 6–8. Tokens · estados · responsividade

Herdados do PT de formulário e do `AppShellV2`. Estados: salvando, salvo (flash `status`), erro (flash `status` com
`success=0`).

## 9. DoD checklist

- [ ] `ConfiguracaoBaselineTest` e `ConfiguracaoContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas e divergências do protótipo

- **O protótipo não deixa trocar as chaves** ("quem configura é o administrador"). Aqui a tela é do administrador
  (`access_woocommerce_api_settings`): aceita troca, nunca mostra (índice do playbook §4).
- **Sincronização automática** no protótipo tem frequência (hora / 6 h / dia); o ERP só guarda ligado/desligado
  (`enable_auto_sync`). A tela mostra o que existe.
- **Testar conexão** do protótipo chama a loja: fica fora (a tela não chama a API, como o painel).
- **Mapa de impostos** e **redefinir** estão na Blade do painel, não nesta; seguem lá.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
