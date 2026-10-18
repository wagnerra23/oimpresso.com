---
name: "SUPERFÍCIE — Fisio"
description: "Índice GERADO dos artefatos do módulo Fisio reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Fisio
---

# 🗺️ Superfície de código — Fisio

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Fisio --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Fisio/**` + `resources/js/Pages/Fisio/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 69 arquivos em 15 papéis.

## Controllers — 5

- [BibliotecaController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaController.php)
- [DataController.php](../../../Modules/Fisio/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Fisio/Http/Controllers/InstallController.php)
- [PacientesController.php](../../../Modules/Fisio/Http/Controllers/PacientesController.php)
- [RegistrosController.php](../../../Modules/Fisio/Http/Controllers/RegistrosController.php)

## Middleware — 1

- [FisioLiberado.php](../../../Modules/Fisio/Http/Middleware/FisioLiberado.php)

## Services — 1

- [FisioAcesso.php](../../../Modules/Fisio/Services/FisioAcesso.php)

## Models / Entities — 13

- [Checkin.php](../../../Modules/Fisio/Entities/Checkin.php)
- [EscopoBibliotecaFisio.php](../../../Modules/Fisio/Entities/Concerns/EscopoBibliotecaFisio.php)
- [SemExclusaoDeDadoClinico.php](../../../Modules/Fisio/Entities/Concerns/SemExclusaoDeDadoClinico.php)
- [Consent.php](../../../Modules/Fisio/Entities/Consent.php)
- [Exercise.php](../../../Modules/Fisio/Entities/Exercise.php)
- [GameLedger.php](../../../Modules/Fisio/Entities/GameLedger.php)
- [Patient.php](../../../Modules/Fisio/Entities/Patient.php)
- [PortalToken.php](../../../Modules/Fisio/Entities/PortalToken.php)
- [Program.php](../../../Modules/Fisio/Entities/Program.php)
- [ProgramItem.php](../../../Modules/Fisio/Entities/ProgramItem.php)
- [Protocol.php](../../../Modules/Fisio/Entities/Protocol.php)
- [Questionnaire.php](../../../Modules/Fisio/Entities/Questionnaire.php)
- [Record.php](../../../Modules/Fisio/Entities/Record.php)

## Providers — 2

- [FisioServiceProvider.php](../../../Modules/Fisio/Providers/FisioServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Fisio/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Fisio/Routes/web.php)

## Migrations (schema) — 2

- [2026_10_18_000001_create_fisio_tables.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000001_create_fisio_tables.php)
- [2026_10_18_000002_add_synonyms_to_fisio_exercises.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000002_add_synonyms_to_fisio_exercises.php)

## Seeders — 1

- [FisioBibliotecaSeeder.php](../../../Modules/Fisio/Database/Seeders/FisioBibliotecaSeeder.php)

## Config — 1

- [config.php](../../../Modules/Fisio/Config/config.php)

## Telas (Inertia/React) — 3

- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx)
- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx)
- [Show.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx)

## Componentes / apoio de tela — 1

- [PerfilClinicoCampos.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/PerfilClinicoCampos.tsx)

## Charters (lei da tela) — 3

- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.charter.md)
- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.charter.md)
- [Show.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.charter.md)

## Casos (contrato UC) — 5

- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.casos.md)
- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.casos.md)
- [Show.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md)
- [Painel.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.casos.md)
- [Portal.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md)

## Testes (Pest) — 19

- 14 em [Modules/Fisio/Tests/Feature/](../../../Modules/Fisio/Tests/Feature)
- 1 em [Modules/Fisio/Tests/Support/](../../../Modules/Fisio/Tests/Support)
- 4 em [Modules/Fisio/Tests/Unit/](../../../Modules/Fisio/Tests/Unit)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 11

- [CheckinFactory.php](../../../Modules/Fisio/Database/Factories/CheckinFactory.php)
- [ExerciseFactory.php](../../../Modules/Fisio/Database/Factories/ExerciseFactory.php)
- [PatientFactory.php](../../../Modules/Fisio/Database/Factories/PatientFactory.php)
- [ProgramFactory.php](../../../Modules/Fisio/Database/Factories/ProgramFactory.php)
- [DadoClinicoImutavelException.php](../../../Modules/Fisio/Exceptions/DadoClinicoImutavelException.php)
- [condicoes.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/condicoes.ts)
- [fisio.php](../../../Modules/Fisio/Resources/lang/pt/fisio.php)
- [README.md](../../../Modules/Fisio/Tests/README.md)
- [composer.json](../../../Modules/Fisio/composer.json)
- [module.json](../../../Modules/Fisio/module.json)
- [SCOPE.md](../../../memory/requisitos/Fisio/SCOPE.md)
