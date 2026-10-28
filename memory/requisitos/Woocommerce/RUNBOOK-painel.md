---
slug: woocommerce-runbook-painel
title: "Woocommerce — Runbook da tela Painel"
type: runbook
module: Woocommerce
tela: Woocommerce/Painel/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Painel do WooCommerce (`/woocommerce`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `woocommerce/playbook/01`.
> **Fonte de design:** alvo `governance/design/targets/woocommerce--index.alvo.json` (thread A1), medido sobre `prototipo-ui/cowork/Wagner/integra-extras.jsx` → `WooCommercePage`, aba Produtos.
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /woocommerce` (`WoocommerceController::index`) responde Inertia `Woocommerce/Painel/Index` **quando a flag
`useV2WoocommercePainel` está ligada** para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`woocommerce::woocommerce.index`, que é onde se sincroniza, reinicia e mapeia impostos.

## 1. Objetivo

Mostrar o estado da sincronização com a loja (pendentes, alterados, última sincronização) e a situação de cada produto,
no desenho do alvo da A1, sem chamar a API da loja e sem expor as chaves dela.

## 2. Pré-condições

- Acesso ao módulo: `superadmin` ou assinatura com `woocommerce_module` (`WoocommerceAuthorizationService::ensureModulo`).
- Lane `acessos-pest.yml` (MySQL) roda `Modules/Woocommerce/Tests/Feature/Painel*Test.php`.

## 3. Passo-a-passo

1. F2 — `PainelBaselineTest` trava a Blade hoje: os alertas de produtos pendentes e alterados contam só o negócio
   da sessão (98 × 99), com o número certo.
2. F3 — as contagens dos alertas saem do `index()` para `contagensDoPainel()`, usado pela Blade (alertas) e pela tela
   React (KPIs): uma fonte só. A lista de produtos sai de `produtosDoPainel()`, com os mesmos predicados.
3. F3 — `Woocommerce/Painel/Index.tsx` + charter + casos + `PainelContratoTest` (UC-WOOP-01..04).
4. F4 — smoke em biz=1 com a flag ligada.
5. F3-2 (2026-10-27) — `_components/SincronizarLoja.tsx`: sincronizar categorias, produtos (offset de 100 até
   `total_products = 0`) e pedidos, e reiniciar, pelos endpoints JSON da Blade. `pode` ganha `categorias` e `pedidos`;
   `ultimas` (datas por tipo) vem adiada. UC-WOOP-05/06. Mapa de impostos em `_components/MapaImpostos.tsx`: lê
   `/woocommerce/tax-rates-map` só ao abrir (taxas da loja vêm da API) e grava no `map-taxrates` de sempre. UC-WOOP-07/08.
6. F5 — cutover — decisão [W] 2026-10-27 ("pode destravar todos"), depois de mapear impostos e o detalhe do log.

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Herdados do PT-01 e do `AppShellV2`. Estados: carregando (props deferidas), loja não configurada (aviso com link para a
configuração), sem produto, busca sem resultado. 50 produtos por página.

## 9. DoD checklist

- [ ] `PainelBaselineTest` e `PainelContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas e divergências do protótipo

- **Sem chamada à loja.** A Blade chama `getTaxRates()` na API do WooCommerce a cada abertura e mostra "Unable to
  connect" se falhar. A tela React só lê o banco: "configurado" é ter URL da loja salva, não a loja responder.
- **Chaves da API nunca em prop.** `woocommerce_api_settings` tem consumer key e secret; a tela recebe só o booleano
  `configurado` (Tier 0 da thread 02).
- **Preço e estoque enviados** aparecem no protótipo; ficam fora (valor e estoque são regra mestre — decisão [W]).
- **Última sincronização por produto** não existe no ERP; a tela mostra a última sincronização de produtos do negócio.
- **Aba Pedidos** do protótipo não tem Blade correspondente; não vira aba. Log e Configuração são links para as telas
  de hoje (threads 02).

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
