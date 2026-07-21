---
name: "SUPERFÍCIE — Fiscal"
description: "Índice GERADO dos artefatos do módulo Fiscal reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Fiscal
---

# 🗺️ Superfície de código — Fiscal

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Fiscal --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Fiscal/**` + `resources/js/Pages/Fiscal/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 71 arquivos em 11 papéis.

## Controllers — 11

- [AcoesController.php](../../../Modules/Fiscal/Http/Controllers/AcoesController.php)
- [CockpitController.php](../../../Modules/Fiscal/Http/Controllers/CockpitController.php)
- [ConfigController.php](../../../Modules/Fiscal/Http/Controllers/ConfigController.php)
- [DataController.php](../../../Modules/Fiscal/Http/Controllers/DataController.php)
- [DfeController.php](../../../Modules/Fiscal/Http/Controllers/DfeController.php)
- [EventosController.php](../../../Modules/Fiscal/Http/Controllers/EventosController.php)
- [InstallController.php](../../../Modules/Fiscal/Http/Controllers/InstallController.php)
- [NfeCockpitController.php](../../../Modules/Fiscal/Http/Controllers/NfeCockpitController.php)
- [NfseCockpitController.php](../../../Modules/Fiscal/Http/Controllers/NfseCockpitController.php)
- [PaletteSearchController.php](../../../Modules/Fiscal/Http/Controllers/PaletteSearchController.php)
- [SpedController.php](../../../Modules/Fiscal/Http/Controllers/SpedController.php)

## Services — 1

- [SpedIcmsIpiGeneratorService.php](../../../Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php)

## Events / Listeners — 1

- [InvalidaCockpitCacheListener.php](../../../Modules/Fiscal/Listeners/InvalidaCockpitCacheListener.php)

## Console / Commands — 2

- [CertHealthCheckCommand.php](../../../Modules/Fiscal/Console/Commands/CertHealthCheckCommand.php)
- [HabilitarBusinessCommand.php](../../../Modules/Fiscal/Console/Commands/HabilitarBusinessCommand.php)

## Providers — 2

- [FiscalServiceProvider.php](../../../Modules/Fiscal/Providers/FiscalServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Fiscal/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Fiscal/Routes/api.php)
- [web.php](../../../Modules/Fiscal/Routes/web.php)

## Telas (Inertia/React) — 7

- [Cockpit.tsx](../../../resources/js/Pages/Fiscal/Cockpit.tsx)
- [Config.tsx](../../../resources/js/Pages/Fiscal/Config.tsx)
- [Dfe.tsx](../../../resources/js/Pages/Fiscal/Dfe.tsx)
- [Eventos.tsx](../../../resources/js/Pages/Fiscal/Eventos.tsx)
- [Nfe.tsx](../../../resources/js/Pages/Fiscal/Nfe.tsx)
- [Nfse.tsx](../../../resources/js/Pages/Fiscal/Nfse.tsx)
- [Sped.tsx](../../../resources/js/Pages/Fiscal/Sped.tsx)

## Componentes / apoio de tela — 12

- [CmdKPalette.tsx](../../../resources/js/Pages/Fiscal/_components/CmdKPalette.tsx)
- [EventosDrawer.tsx](../../../resources/js/Pages/Fiscal/_components/EventosDrawer.tsx)
- [FxShell.tsx](../../../resources/js/Pages/Fiscal/_components/FxShell.tsx)
- [InutilizacaoModal.tsx](../../../resources/js/Pages/Fiscal/_components/InutilizacaoModal.tsx)
- [NFSeDrawer.tsx](../../../resources/js/Pages/Fiscal/_components/NFSeDrawer.tsx)
- [NotaDrawer.tsx](../../../resources/js/Pages/Fiscal/_components/NotaDrawer.tsx)
- [NotaDrawerV2.tsx](../../../resources/js/Pages/Fiscal/_components/NotaDrawerV2.tsx)
- [SavedViewsChips.tsx](../../../resources/js/Pages/Fiscal/_components/SavedViewsChips.tsx)
- [SendToContabilDrawer.tsx](../../../resources/js/Pages/Fiscal/_components/SendToContabilDrawer.tsx)
- [WriteOffAuditoriaCard.tsx](../../../resources/js/Pages/Fiscal/_components/WriteOffAuditoriaCard.tsx)
- [DrawerBase.tsx](../../../resources/js/Pages/Fiscal/_components/_shared/DrawerBase.tsx)
- [linkify.tsx](../../../resources/js/Pages/Fiscal/_lib/linkify.tsx)

## Charters (lei da tela) — 7

- [Cockpit.charter.md](../../../resources/js/Pages/Fiscal/Cockpit.charter.md)
- [Config.charter.md](../../../resources/js/Pages/Fiscal/Config.charter.md)
- [Dfe.charter.md](../../../resources/js/Pages/Fiscal/Dfe.charter.md)
- [Eventos.charter.md](../../../resources/js/Pages/Fiscal/Eventos.charter.md)
- [Nfe.charter.md](../../../resources/js/Pages/Fiscal/Nfe.charter.md)
- [Nfse.charter.md](../../../resources/js/Pages/Fiscal/Nfse.charter.md)
- [Sped.charter.md](../../../resources/js/Pages/Fiscal/Sped.charter.md)

## Casos (contrato UC) — 7

- [Cockpit.casos.md](../../../resources/js/Pages/Fiscal/Cockpit.casos.md)
- [Config.casos.md](../../../resources/js/Pages/Fiscal/Config.casos.md)
- [Dfe.casos.md](../../../resources/js/Pages/Fiscal/Dfe.casos.md)
- [Eventos.casos.md](../../../resources/js/Pages/Fiscal/Eventos.casos.md)
- [Nfe.casos.md](../../../resources/js/Pages/Fiscal/Nfe.casos.md)
- [Nfse.casos.md](../../../resources/js/Pages/Fiscal/Nfse.casos.md)
- [Sped.casos.md](../../../resources/js/Pages/Fiscal/Sped.casos.md)

## Testes (Pest) — 19

- 19 arquivos em [Modules/Fiscal/Tests/Feature/](../../../Modules/Fiscal/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
