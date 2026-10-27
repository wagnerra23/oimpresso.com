---
slug: marketplaces-runbook-pedidos
title: "Marketplaces — Runbook da tela Pedidos"
type: runbook
module: Marketplaces
tela: Marketplaces/Pedidos
owner: W
status: rascunho
last_validated: "2026-10-26"
related_adrs:
  - '0439-ativacao-modulo-marketplaces-mercado-livre-primeiro'
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Pedidos dos marketplaces (`/marketplaces/pedidos`)

> **Tipo:** runbook reproduzível · F1 PLAN do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para tela **nova** (não há Blade a migrar).
> **Fonte de design:** não há protótipo de marketplace em `prototipo-ui/`. Pelo protocolo v2 ([ADR 0282](../../decisions/0282-protocolo-v2-colapso-ratificacao.md) + [ADR 0438](../../decisions/0438-artefato-substitui-cowork-como-superficie-do-prototipo.md)) a tela é gerada pelo designer-agente a partir do Padrão de Tela **PT-01 Lista**, carimbada por `criar-tela.mjs` e validada pelo CI visual.
> **US:** US-MKT-008 ([SPEC](SPEC.md)) · decisões [W] 2026-10-26 (local escolhido na conta).

## Estado final esperado

`GET /marketplaces/pedidos` (`PedidosController::index`) responde Inertia `Marketplaces/Pedidos` para empresa com o pacote
`marketplaces_module` e usuário com `marketplaces.access`. Sem pacote ou sem permissão: 403. O item "Marketplaces"
aparece no grupo COMERCIAL do sidebar pela mesma regra (`MarketplacesAcesso`).

## 1. Objetivo

Ver os pedidos que chegaram do Mercado Livre e se já viraram venda, e escolher em que local de venda cada conta
conectada joga as vendas — sem local escolhido, o pedido não vira venda.

## 2. Pré-condições

- Módulo `Marketplaces` instalado; pacote `marketplaces_module` ligado para a empresa no superadmin.
- Permissões `marketplaces.access` (ver) e `marketplaces.account.connect` (conectar conta e escolher local).
- App do Mercado Livre configurado no `.env` para o botão "Conectar Mercado Livre" funcionar (sem ele, a rota avisa e volta).

## 3. Passo-a-passo

1. Carimbo do PT-01: `node scripts/governance/criar-tela.mjs Marketplaces/Pedidos PT-01 --sem-prototipo "..." --rota marketplaces/pedidos`.
2. Backend: `PedidosController::index` (props `contas`, `locais` e `pedidos` deferidas) e `definirLocal` (PATCH, só local e conta da empresa).
3. Rotas em `Modules/Marketplaces/Routes/web.php` atrás de `MarketplacesLiberado`; menu em `DataController::modifyAdminMenu`.
4. Página `resources/js/Pages/Marketplaces/Pedidos.tsx`: contas no topo, filtros (número, com/sem venda), DataTable de pedidos.
5. Contrato: `Pedidos.casos.md` (UC-PEDIDO-00..04) citado por `Modules/Marketplaces/Tests/Feature/MarketplacesPedidosTelaContratoTest.php`.

## 4–8. Tokens · estados · responsividade · atalhos · contrato

- Só componentes do DS (`PageHeader`, `shared/DataTable`, `Badge`, `Button`, `Input`, `Skeleton`); sem cor crua.
- Estados: carregando (Skeleton por bloco deferido), vazio (sem conta / sem pedido), conta sem local (aviso na própria linha).
- Cabe em 1280 px; a linha da conta quebra em duas no estreito.
- Contrato de tela em `governance/design/contracts/marketplaces-pedidos.contract.json` (copy literal é decisão [W]).

## 9. DoD checklist

- [ ] Pest de contrato verde na lane verticais-pest (98 × 99).
- [ ] CI visual verde.
- [ ] Screenshot da tela em produção depois do deploy.
- [ ] [W] aprova o screenshot → charter sai de `draft`.

## 10. Pegadinhas

- O `select` de local grava na hora (PATCH com `only: ['contas']`); usuário sem `marketplaces.account.connect` vê o select desabilitado e o servidor recusa com 403.
- Pedido sem venda não guarda o motivo: a tela só diz "Ainda não virou venda". O motivo está no log (`marketplaces: pedido não virou venda`).

## 11. ADR de origem

[ADR 0439](../../decisions/0439-ativacao-modulo-marketplaces-mercado-livre-primeiro.md).
