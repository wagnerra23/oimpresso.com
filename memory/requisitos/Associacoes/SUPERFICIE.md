---
name: "SUPERFÍCIE — Associacoes"
description: "Índice GERADO dos artefatos do módulo Associacoes reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Associacoes
---

# 🗺️ Superfície de código — Associacoes

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Associacoes --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Associacoes/**` + `resources/js/Pages/Associacoes/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 30 arquivos em 14 papéis.

## Controllers — 4

- [AssociacoesController.php](../../../Modules/Associacoes/Http/Controllers/AssociacoesController.php)
- [DataController.php](../../../Modules/Associacoes/Http/Controllers/DataController.php)
- [DocumentosAssociacaoController.php](../../../Modules/Associacoes/Http/Controllers/DocumentosAssociacaoController.php)
- [InstallController.php](../../../Modules/Associacoes/Http/Controllers/InstallController.php)

## Middleware — 1

- [AssociacoesLiberado.php](../../../Modules/Associacoes/Http/Middleware/AssociacoesLiberado.php)

## Services — 3

- [AssociacoesAcesso.php](../../../Modules/Associacoes/Services/AssociacoesAcesso.php)
- [DocumentosDaAssociacao.php](../../../Modules/Associacoes/Services/DocumentosDaAssociacao.php)
- [PendenciaDiretoria.php](../../../Modules/Associacoes/Services/PendenciaDiretoria.php)

## Models / Entities — 5

- [Associacao.php](../../../Modules/Associacoes/Entities/Associacao.php)
- [Associada.php](../../../Modules/Associacoes/Entities/Associada.php)
- [ConsentimentoAssociada.php](../../../Modules/Associacoes/Entities/ConsentimentoAssociada.php)
- [DocumentoAssociacao.php](../../../Modules/Associacoes/Entities/DocumentoAssociacao.php)
- [Representante.php](../../../Modules/Associacoes/Entities/Representante.php)

## Providers — 2

- [AssociacoesServiceProvider.php](../../../Modules/Associacoes/Providers/AssociacoesServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Associacoes/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Associacoes/Routes/web.php)

## Migrations (schema) — 1

- [2026_11_13_000001_create_assoc_tables.php](../../../Modules/Associacoes/Database/Migrations/2026_11_13_000001_create_assoc_tables.php)

## Config — 1

- [config.php](../../../Modules/Associacoes/Config/config.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Index.tsx)

## Componentes / apoio de tela — 1

- [FichaAssociacao.tsx](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/_components/FichaAssociacao.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Index.charter.md)

## Casos (contrato UC) — 1

- [Index.casos.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Index.casos.md)

## Testes (Pest) — 3

- 3 em [Modules/Associacoes/Tests/Feature/](../../../Modules/Associacoes/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 5

- [tipos.ts](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/_components/tipos.ts)
- [associacoes.php](../../../Modules/Associacoes/Resources/lang/pt-BR/associacoes.php)
- [composer.json](../../../Modules/Associacoes/composer.json)
- [module.json](../../../Modules/Associacoes/module.json)
- [SCOPE.md](../../../memory/requisitos/Associacoes/SCOPE.md)
