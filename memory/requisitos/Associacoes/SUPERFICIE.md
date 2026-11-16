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

**Total mapeado:** 51 arquivos em 14 papéis.

## Controllers — 8

- [AssociacoesController.php](../../../Modules/Associacoes/Http/Controllers/AssociacoesController.php)
- [AssociadasController.php](../../../Modules/Associacoes/Http/Controllers/AssociadasController.php)
- [DataController.php](../../../Modules/Associacoes/Http/Controllers/DataController.php)
- [DocumentosAssociacaoController.php](../../../Modules/Associacoes/Http/Controllers/DocumentosAssociacaoController.php)
- [InstallController.php](../../../Modules/Associacoes/Http/Controllers/InstallController.php)
- [PainelDaRedeController.php](../../../Modules/Associacoes/Http/Controllers/PainelDaRedeController.php)
- [PortalAssociadaController.php](../../../Modules/Associacoes/Http/Controllers/PortalAssociadaController.php)
- [PortalLinkController.php](../../../Modules/Associacoes/Http/Controllers/PortalLinkController.php)

## Middleware — 1

- [AssociacoesLiberado.php](../../../Modules/Associacoes/Http/Middleware/AssociacoesLiberado.php)

## Services — 6

- [AssociacoesAcesso.php](../../../Modules/Associacoes/Services/AssociacoesAcesso.php)
- [Consentimentos.php](../../../Modules/Associacoes/Services/Consentimentos.php)
- [DocumentosDaAssociacao.php](../../../Modules/Associacoes/Services/DocumentosDaAssociacao.php)
- [PendenciaDiretoria.php](../../../Modules/Associacoes/Services/PendenciaDiretoria.php)
- [PendenciasDaRede.php](../../../Modules/Associacoes/Services/PendenciasDaRede.php)
- [PortalAssociadaService.php](../../../Modules/Associacoes/Services/PortalAssociadaService.php)

## Models / Entities — 6

- [Associacao.php](../../../Modules/Associacoes/Entities/Associacao.php)
- [Associada.php](../../../Modules/Associacoes/Entities/Associada.php)
- [ConsentimentoAssociada.php](../../../Modules/Associacoes/Entities/ConsentimentoAssociada.php)
- [DocumentoAssociacao.php](../../../Modules/Associacoes/Entities/DocumentoAssociacao.php)
- [PortalAssociada.php](../../../Modules/Associacoes/Entities/PortalAssociada.php)
- [Representante.php](../../../Modules/Associacoes/Entities/Representante.php)

## Providers — 2

- [AssociacoesServiceProvider.php](../../../Modules/Associacoes/Providers/AssociacoesServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Associacoes/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Associacoes/Routes/web.php)

## Migrations (schema) — 2

- [2026_11_13_000001_create_assoc_tables.php](../../../Modules/Associacoes/Database/Migrations/2026_11_13_000001_create_assoc_tables.php)
- [2026_11_13_000060_create_assoc_portal_tokens_table.php](../../../Modules/Associacoes/Database/Migrations/2026_11_13_000060_create_assoc_portal_tokens_table.php)

## Config — 1

- [config.php](../../../Modules/Associacoes/Config/config.php)

## Telas (Inertia/React) — 4

- [Associadas.tsx](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Associadas.tsx)
- [Index.tsx](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Index.tsx)
- [Membro.tsx](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.tsx)
- [Painel.tsx](../../../resources/js/Pages/Associacoes/Painel.tsx)

## Componentes / apoio de tela — 1

- [FichaAssociacao.tsx](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/_components/FichaAssociacao.tsx)

## Charters (lei da tela) — 4

- [Associadas.charter.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Associadas.charter.md)
- [Index.charter.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Index.charter.md)
- [Membro.charter.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.charter.md)
- [Painel.charter.md](../../../resources/js/Pages/Associacoes/Painel.charter.md)

## Casos (contrato UC) — 4

- [Associadas.casos.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Associadas.casos.md)
- [Index.casos.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Index.casos.md)
- [Membro.casos.md](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/Public/Membro.casos.md)
- [Painel.casos.md](../../../resources/js/Pages/Associacoes/Painel.casos.md)

## Testes (Pest) — 6

- 6 em [Modules/Associacoes/Tests/Feature/](../../../Modules/Associacoes/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 5

- [tipos.ts](../../../Modules/Associacoes/Resources/js/Pages/Associacoes/_components/tipos.ts)
- [associacoes.php](../../../Modules/Associacoes/Resources/lang/pt-BR/associacoes.php)
- [composer.json](../../../Modules/Associacoes/composer.json)
- [module.json](../../../Modules/Associacoes/module.json)
- [SCOPE.md](../../../memory/requisitos/Associacoes/SCOPE.md)
