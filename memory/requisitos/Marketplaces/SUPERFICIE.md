---
name: "SUPERFÍCIE — Marketplaces"
description: "Índice GERADO dos artefatos do módulo Marketplaces reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Marketplaces
---

# 🗺️ Superfície de código — Marketplaces

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Marketplaces --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Marketplaces/**` + `resources/js/Pages/Marketplaces/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 52 arquivos em 16 papéis.

## Controllers — 6

- [ContasController.php](../../../Modules/Marketplaces/Http/Controllers/ContasController.php)
- [ContasNuvemshopController.php](../../../Modules/Marketplaces/Http/Controllers/ContasNuvemshopController.php)
- [DataController.php](../../../Modules/Marketplaces/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Marketplaces/Http/Controllers/InstallController.php)
- [NotificacoesMercadoLivreController.php](../../../Modules/Marketplaces/Http/Controllers/NotificacoesMercadoLivreController.php)
- [PedidosController.php](../../../Modules/Marketplaces/Http/Controllers/PedidosController.php)

## Middleware — 1

- [MarketplacesLiberado.php](../../../Modules/Marketplaces/Http/Middleware/MarketplacesLiberado.php)

## Services — 8

- [ContasVendedor.php](../../../Modules/Marketplaces/Services/ContasVendedor.php)
- [CfopDoPedido.php](../../../Modules/Marketplaces/Services/Fiscal/CfopDoPedido.php)
- [IniciarPipelineMarketplace.php](../../../Modules/Marketplaces/Services/IniciarPipelineMarketplace.php)
- [MarketplacesAcesso.php](../../../Modules/Marketplaces/Services/MarketplacesAcesso.php)
- [DestinatarioMercadoLivre.php](../../../Modules/Marketplaces/Services/MercadoLivre/DestinatarioMercadoLivre.php)
- [MercadoLivreOAuth.php](../../../Modules/Marketplaces/Services/MercadoLivre/MercadoLivreOAuth.php)
- [NuvemshopOAuth.php](../../../Modules/Marketplaces/Services/Nuvemshop/NuvemshopOAuth.php)
- [PedidoParaVenda.php](../../../Modules/Marketplaces/Services/PedidoParaVenda.php)

## Models / Entities — 5

- [Anuncio.php](../../../Modules/Marketplaces/Entities/Anuncio.php)
- [ContaVendedor.php](../../../Modules/Marketplaces/Entities/ContaVendedor.php)
- [Marketplace.php](../../../Modules/Marketplaces/Entities/Marketplace.php)
- [PedidoMarketplace.php](../../../Modules/Marketplaces/Entities/PedidoMarketplace.php)
- [WebhookRecebido.php](../../../Modules/Marketplaces/Entities/WebhookRecebido.php)

## Jobs — 4

- [EmitirNfeDoPedidoJob.php](../../../Modules/Marketplaces/Jobs/EmitirNfeDoPedidoJob.php)
- [ProcessarNotificacaoMercadoLivreJob.php](../../../Modules/Marketplaces/Jobs/ProcessarNotificacaoMercadoLivreJob.php)
- [RenovarTokenContaJob.php](../../../Modules/Marketplaces/Jobs/RenovarTokenContaJob.php)
- [VirarVendaJob.php](../../../Modules/Marketplaces/Jobs/VirarVendaJob.php)

## Console / Commands — 1

- [RenovarTokensCommand.php](../../../Modules/Marketplaces/Console/RenovarTokensCommand.php)

## Providers — 2

- [MarketplacesServiceProvider.php](../../../Modules/Marketplaces/Providers/MarketplacesServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Marketplaces/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Marketplaces/Routes/web.php)

## Migrations (schema) — 4

- [2026_10_26_000001_create_mkt_tables.php](../../../Modules/Marketplaces/Database/Migrations/2026_10_26_000001_create_mkt_tables.php)
- [2026_10_26_000002_seed_mkt_marketplaces.php](../../../Modules/Marketplaces/Database/Migrations/2026_10_26_000002_seed_mkt_marketplaces.php)
- [2026_10_26_000003_add_location_id_to_mkt_account_credentials.php](../../../Modules/Marketplaces/Database/Migrations/2026_10_26_000003_add_location_id_to_mkt_account_credentials.php)
- [2026_10_27_000004_seed_mkt_nuvemshop.php](../../../Modules/Marketplaces/Database/Migrations/2026_10_27_000004_seed_mkt_nuvemshop.php)

## Seeders — 1

- [FsmProcessoVendaMarketplaceSeeder.php](../../../Modules/Marketplaces/Database/Seeders/FsmProcessoVendaMarketplaceSeeder.php)

## Config — 1

- [config.php](../../../Modules/Marketplaces/Config/config.php)

## Telas (Inertia/React) — 1

- [Pedidos.tsx](../../../resources/js/Pages/Marketplaces/Pedidos.tsx)

## Charters (lei da tela) — 1

- [Pedidos.charter.md](../../../resources/js/Pages/Marketplaces/Pedidos.charter.md)

## Casos (contrato UC) — 1

- [Pedidos.casos.md](../../../resources/js/Pages/Marketplaces/Pedidos.casos.md)

## Testes (Pest) — 10

- 10 em [Modules/Marketplaces/Tests/Feature/](../../../Modules/Marketplaces/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 5

- [marketplaces.php](../../../Modules/Marketplaces/Resources/lang/pt-BR/marketplaces.php)
- [EmitirNfeAoEnviar.php](../../../Modules/Marketplaces/SideEffects/EmitirNfeAoEnviar.php)
- [composer.json](../../../Modules/Marketplaces/composer.json)
- [module.json](../../../Modules/Marketplaces/module.json)
- [SCOPE.md](../../../memory/requisitos/Marketplaces/SCOPE.md)
