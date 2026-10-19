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

**Total mapeado:** 159 arquivos em 19 papéis.

## Controllers — 16

- [BibliotecaController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaController.php)
- [BibliotecaFavoritosController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaFavoritosController.php)
- [BibliotecaMidiaController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaMidiaController.php)
- [DataController.php](../../../Modules/Fisio/Http/Controllers/DataController.php)
- [EnvioWhatsappController.php](../../../Modules/Fisio/Http/Controllers/EnvioWhatsappController.php)
- [InstallController.php](../../../Modules/Fisio/Http/Controllers/InstallController.php)
- [PacientesController.php](../../../Modules/Fisio/Http/Controllers/PacientesController.php)
- [PainelController.php](../../../Modules/Fisio/Http/Controllers/PainelController.php)
- [PortalController.php](../../../Modules/Fisio/Http/Controllers/PortalController.php)
- [PortalLinkController.php](../../../Modules/Fisio/Http/Controllers/PortalLinkController.php)
- [ProgramasController.php](../../../Modules/Fisio/Http/Controllers/ProgramasController.php)
- [ProntuarioPdfController.php](../../../Modules/Fisio/Http/Controllers/ProntuarioPdfController.php)
- [ProtocolosController.php](../../../Modules/Fisio/Http/Controllers/ProtocolosController.php)
- [QuestionariosController.php](../../../Modules/Fisio/Http/Controllers/QuestionariosController.php)
- [RegistrosController.php](../../../Modules/Fisio/Http/Controllers/RegistrosController.php)
- [ResponsavelController.php](../../../Modules/Fisio/Http/Controllers/ResponsavelController.php)

## Middleware — 1

- [FisioLiberado.php](../../../Modules/Fisio/Http/Middleware/FisioLiberado.php)

## Services — 27

- [AdesaoCalculator.php](../../../Modules/Fisio/Services/AdesaoCalculator.php)
- [AparelhosDoPortal.php](../../../Modules/Fisio/Services/AparelhosDoPortal.php)
- [AtencaoDetector.php](../../../Modules/Fisio/Services/AtencaoDetector.php)
- [BibliotecaFacetas.php](../../../Modules/Fisio/Services/BibliotecaFacetas.php)
- [CheckinDoPortal.php](../../../Modules/Fisio/Services/CheckinDoPortal.php)
- [Consentimentos.php](../../../Modules/Fisio/Services/Consentimentos.php)
- [DiaLocal.php](../../../Modules/Fisio/Services/DiaLocal.php)
- [EditorProgramaPayload.php](../../../Modules/Fisio/Services/EditorProgramaPayload.php)
- [FisioAcesso.php](../../../Modules/Fisio/Services/FisioAcesso.php)
- [JogoService.php](../../../Modules/Fisio/Services/JogoService.php)
- [LimiarDorCalculator.php](../../../Modules/Fisio/Services/LimiarDorCalculator.php)
- [MetaSemanal.php](../../../Modules/Fisio/Services/MetaSemanal.php)
- [MidiaExercicio.php](../../../Modules/Fisio/Services/MidiaExercicio.php)
- [NivelCalculator.php](../../../Modules/Fisio/Services/NivelCalculator.php)
- [PainelFisio.php](../../../Modules/Fisio/Services/PainelFisio.php)
- [PortalTokenService.php](../../../Modules/Fisio/Services/PortalTokenService.php)
- [ProntuarioExportacao.php](../../../Modules/Fisio/Services/ProntuarioExportacao.php)
- [CatalogoQuestionarios.php](../../../Modules/Fisio/Services/Questionarios/CatalogoQuestionarios.php)
- [PontuacaoQuestionario.php](../../../Modules/Fisio/Services/Questionarios/PontuacaoQuestionario.php)
- [RespostaInvalidaException.php](../../../Modules/Fisio/Services/Questionarios/RespostaInvalidaException.php)
- [ResponsavelPaciente.php](../../../Modules/Fisio/Services/ResponsavelPaciente.php)
- [SequenciaCalculator.php](../../../Modules/Fisio/Services/SequenciaCalculator.php)
- [AgendaDeEnvio.php](../../../Modules/Fisio/Services/Whatsapp/AgendaDeEnvio.php)
- [EnvioWhatsappFisio.php](../../../Modules/Fisio/Services/Whatsapp/EnvioWhatsappFisio.php)
- [LinkDoPortalPorToken.php](../../../Modules/Fisio/Services/Whatsapp/LinkDoPortalPorToken.php)
- [MensagensFisio.php](../../../Modules/Fisio/Services/Whatsapp/MensagensFisio.php)
- [XpDoDiaCalculator.php](../../../Modules/Fisio/Services/XpDoDiaCalculator.php)

## Models / Entities — 15

- [Checkin.php](../../../Modules/Fisio/Entities/Checkin.php)
- [EscopoBibliotecaFisio.php](../../../Modules/Fisio/Entities/Concerns/EscopoBibliotecaFisio.php)
- [SemExclusaoDeDadoClinico.php](../../../Modules/Fisio/Entities/Concerns/SemExclusaoDeDadoClinico.php)
- [Consent.php](../../../Modules/Fisio/Entities/Consent.php)
- [Exercise.php](../../../Modules/Fisio/Entities/Exercise.php)
- [ExerciseFavorite.php](../../../Modules/Fisio/Entities/ExerciseFavorite.php)
- [GameLedger.php](../../../Modules/Fisio/Entities/GameLedger.php)
- [Patient.php](../../../Modules/Fisio/Entities/Patient.php)
- [PortalDevice.php](../../../Modules/Fisio/Entities/PortalDevice.php)
- [PortalToken.php](../../../Modules/Fisio/Entities/PortalToken.php)
- [Program.php](../../../Modules/Fisio/Entities/Program.php)
- [ProgramItem.php](../../../Modules/Fisio/Entities/ProgramItem.php)
- [Protocol.php](../../../Modules/Fisio/Entities/Protocol.php)
- [Questionnaire.php](../../../Modules/Fisio/Entities/Questionnaire.php)
- [Record.php](../../../Modules/Fisio/Entities/Record.php)

## Jobs — 2

- [EnviarLembreteDiarioJob.php](../../../Modules/Fisio/Jobs/EnviarLembreteDiarioJob.php)
- [EnviarProgramaWhatsappJob.php](../../../Modules/Fisio/Jobs/EnviarProgramaWhatsappJob.php)

## Events / Listeners — 2

- [CheckinRegistrado.php](../../../Modules/Fisio/Events/CheckinRegistrado.php)
- [LancarXpDoCheckin.php](../../../Modules/Fisio/Listeners/LancarXpDoCheckin.php)

## Console / Commands — 1

- [LembretesCommand.php](../../../Modules/Fisio/Console/LembretesCommand.php)

## Providers — 2

- [FisioServiceProvider.php](../../../Modules/Fisio/Providers/FisioServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Fisio/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Fisio/Routes/web.php)

## Migrations (schema) — 6

- [2026_10_18_000001_create_fisio_tables.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000001_create_fisio_tables.php)
- [2026_10_18_000002_add_synonyms_to_fisio_exercises.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000002_add_synonyms_to_fisio_exercises.php)
- [2026_10_18_000003_add_responsible_user_to_fisio_patients.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000003_add_responsible_user_to_fisio_patients.php)
- [2026_10_18_000006_add_favoritos_e_equipamento_biblioteca_fisio.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000006_add_favoritos_e_equipamento_biblioteca_fisio.php)
- [2026_10_18_000081_add_crisis_to_fisio_checkins.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000081_add_crisis_to_fisio_checkins.php)
- [2026_10_19_000071_create_fisio_portal_devices.php](../../../Modules/Fisio/Database/Migrations/2026_10_19_000071_create_fisio_portal_devices.php)

## Seeders — 1

- [FisioBibliotecaSeeder.php](../../../Modules/Fisio/Database/Seeders/FisioBibliotecaSeeder.php)

## Config — 1

- [config.php](../../../Modules/Fisio/Config/config.php)

## Views (Blade) — 1

- [prontuario.blade.php](../../../Modules/Fisio/Resources/views/pdf/prontuario.blade.php)

## Telas (Inertia/React) — 5

- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx)
- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx)
- [Show.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx)
- [Painel.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.tsx)
- [Portal.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx)

## Componentes / apoio de tela — 17

- [CamposFacetas.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/CamposFacetas.tsx)
- [FavoritoBotao.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/FavoritoBotao.tsx)
- [FiltrosFacetas.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/FiltrosFacetas.tsx)
- [OrigemContato.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/_components/OrigemContato.tsx)
- [Checkin.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Checkin.tsx)
- [Entrada.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Entrada.tsx)
- [Hoje.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Hoje.tsx)
- [Instalar.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Instalar.tsx)
- [EditorPrograma.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/EditorPrograma.tsx)
- [EvolucaoPaciente.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/EvolucaoPaciente.tsx)
- [ExportarProntuarioPdf.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/ExportarProntuarioPdf.tsx)
- [FiltroResponsavel.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/FiltroResponsavel.tsx)
- [LinkPortal.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/LinkPortal.tsx)
- [MidiaExercicio.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/MidiaExercicio.tsx)
- [PerfilClinicoCampos.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/PerfilClinicoCampos.tsx)
- [Questionarios.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/Questionarios.tsx)
- [ResponsavelPaciente.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/ResponsavelPaciente.tsx)

## Charters (lei da tela) — 5

- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.charter.md)
- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.charter.md)
- [Show.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.charter.md)
- [Painel.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.charter.md)
- [Portal.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.charter.md)

## Casos (contrato UC) — 5

- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.casos.md)
- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.casos.md)
- [Show.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md)
- [Painel.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.casos.md)
- [Portal.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md)

## Testes (Pest) — 36

- 25 em [Modules/Fisio/Tests/Feature/](../../../Modules/Fisio/Tests/Feature)
- 2 em [Modules/Fisio/Tests/Support/](../../../Modules/Fisio/Tests/Support)
- 9 em [Modules/Fisio/Tests/Unit/](../../../Modules/Fisio/Tests/Unit)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 15

- [LinkDoPortal.php](../../../Modules/Fisio/Contracts/LinkDoPortal.php)
- [CheckinFactory.php](../../../Modules/Fisio/Database/Factories/CheckinFactory.php)
- [ExerciseFactory.php](../../../Modules/Fisio/Database/Factories/ExerciseFactory.php)
- [PatientFactory.php](../../../Modules/Fisio/Database/Factories/PatientFactory.php)
- [ProgramFactory.php](../../../Modules/Fisio/Database/Factories/ProgramFactory.php)
- [DadoClinicoImutavelException.php](../../../Modules/Fisio/Exceptions/DadoClinicoImutavelException.php)
- [SinalDeAlertaNotification.php](../../../Modules/Fisio/Notifications/SinalDeAlertaNotification.php)
- [facetas.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/facetas.ts)
- [portal.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/portal.ts)
- [condicoes.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/condicoes.ts)
- [fisio.php](../../../Modules/Fisio/Resources/lang/pt/fisio.php)
- [README.md](../../../Modules/Fisio/Tests/README.md)
- [composer.json](../../../Modules/Fisio/composer.json)
- [module.json](../../../Modules/Fisio/module.json)
- [SCOPE.md](../../../memory/requisitos/Fisio/SCOPE.md)
