---
name: "SUPERFÍCIE — Compras"
description: "Índice GERADO dos artefatos do módulo Compras reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Compras
---

# 🗺️ Superfície de código — Compras

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Compras --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Compras/**` + `resources/js/Pages/Compras/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 23 arquivos em 10 papéis.

## Controllers — 3

- [ComprasController.php](../../../Modules/Compras/Http/Controllers/ComprasController.php)
- [DataController.php](../../../Modules/Compras/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Compras/Http/Controllers/InstallController.php)

## Requests (validação) — 1

- [ListarComprasRequest.php](../../../Modules/Compras/Http/Requests/ListarComprasRequest.php)

## Services — 1

- [ComprasService.php](../../../Modules/Compras/Services/ComprasService.php)

## Providers — 2

- [ComprasServiceProvider.php](../../../Modules/Compras/Providers/ComprasServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Compras/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Compras/Routes/web.php)

## Config — 1

- [config.php](../../../Modules/Compras/Config/config.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/Compras/Index.tsx)

## Componentes / apoio de tela — 3

- [AcoesDropdown.tsx](../../../resources/js/Pages/Compras/components/AcoesDropdown.tsx)
- [Drawer.tsx](../../../resources/js/Pages/Compras/components/Drawer.tsx)
- [VisibilidadeColunas.tsx](../../../resources/js/Pages/Compras/components/VisibilidadeColunas.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/Compras/Index.charter.md)

## Testes (Pest) — 9

- 9 arquivos em [Modules/Compras/Tests/Feature/](../../../Modules/Compras/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
