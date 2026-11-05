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

**Total mapeado:** 255 arquivos em 20 papéis.

## Controllers — 23

- [AgendaController.php](../../../Modules/Fisio/Http/Controllers/AgendaController.php)
- [BibliotecaController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaController.php)
- [BibliotecaFavoritosController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaFavoritosController.php)
- [BibliotecaMidiaController.php](../../../Modules/Fisio/Http/Controllers/BibliotecaMidiaController.php)
- [ConfigAgendamentoController.php](../../../Modules/Fisio/Http/Controllers/ConfigAgendamentoController.php)
- [ConfiguracoesController.php](../../../Modules/Fisio/Http/Controllers/ConfiguracoesController.php)
- [DataController.php](../../../Modules/Fisio/Http/Controllers/DataController.php)
- [EnvioWhatsappController.php](../../../Modules/Fisio/Http/Controllers/EnvioWhatsappController.php)
- [FolhaProgramaController.php](../../../Modules/Fisio/Http/Controllers/FolhaProgramaController.php)
- [InstallController.php](../../../Modules/Fisio/Http/Controllers/InstallController.php)
- [OnboardingController.php](../../../Modules/Fisio/Http/Controllers/OnboardingController.php)
- [PacientesController.php](../../../Modules/Fisio/Http/Controllers/PacientesController.php)
- [PacotesController.php](../../../Modules/Fisio/Http/Controllers/PacotesController.php)
- [PainelController.php](../../../Modules/Fisio/Http/Controllers/PainelController.php)
- [PortalAgendaController.php](../../../Modules/Fisio/Http/Controllers/PortalAgendaController.php)
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

## Services — 42

- [AdesaoCalculator.php](../../../Modules/Fisio/Services/AdesaoCalculator.php)
- [AgendaDaSemana.php](../../../Modules/Fisio/Services/Agenda/AgendaDaSemana.php)
- [AgendaDoPaciente.php](../../../Modules/Fisio/Services/Agenda/AgendaDoPaciente.php)
- [AgendaService.php](../../../Modules/Fisio/Services/Agenda/AgendaService.php)
- [CompatibilidadeEspera.php](../../../Modules/Fisio/Services/Agenda/CompatibilidadeEspera.php)
- [AparelhosDoPortal.php](../../../Modules/Fisio/Services/AparelhosDoPortal.php)
- [AtencaoDetector.php](../../../Modules/Fisio/Services/AtencaoDetector.php)
- [BibliotecaFacetas.php](../../../Modules/Fisio/Services/BibliotecaFacetas.php)
- [CheckinDoPortal.php](../../../Modules/Fisio/Services/CheckinDoPortal.php)
- [ConfiguracaoClinica.php](../../../Modules/Fisio/Services/ConfiguracaoClinica.php)
- [Consentimentos.php](../../../Modules/Fisio/Services/Consentimentos.php)
- [DiaLocal.php](../../../Modules/Fisio/Services/DiaLocal.php)
- [EditorProgramaPayload.php](../../../Modules/Fisio/Services/EditorProgramaPayload.php)
- [FisioAcesso.php](../../../Modules/Fisio/Services/FisioAcesso.php)
- [FolhaPrograma.php](../../../Modules/Fisio/Services/FolhaPrograma.php)
- [JogoService.php](../../../Modules/Fisio/Services/JogoService.php)
- [LaudoEvolucao.php](../../../Modules/Fisio/Services/LaudoEvolucao.php)
- [LimiarDorCalculator.php](../../../Modules/Fisio/Services/LimiarDorCalculator.php)
- [LinguagemAnvisa.php](../../../Modules/Fisio/Services/LinguagemAnvisa.php)
- [MedalhasCalculator.php](../../../Modules/Fisio/Services/MedalhasCalculator.php)
- [MetaSemanal.php](../../../Modules/Fisio/Services/MetaSemanal.php)
- [MidiaExercicio.php](../../../Modules/Fisio/Services/MidiaExercicio.php)
- [MissoesCalculator.php](../../../Modules/Fisio/Services/MissoesCalculator.php)
- [NivelCalculator.php](../../../Modules/Fisio/Services/NivelCalculator.php)
- [SaldoDoPacote.php](../../../Modules/Fisio/Services/Pacotes/SaldoDoPacote.php)
- [PainelFisio.php](../../../Modules/Fisio/Services/PainelFisio.php)
- [PortalTokenService.php](../../../Modules/Fisio/Services/PortalTokenService.php)
- [PreAnamneseDoPortal.php](../../../Modules/Fisio/Services/PreAnamneseDoPortal.php)
- [ProntuarioExportacao.php](../../../Modules/Fisio/Services/ProntuarioExportacao.php)
- [CatalogoQuestionarios.php](../../../Modules/Fisio/Services/Questionarios/CatalogoQuestionarios.php)
- [PontuacaoQuestionario.php](../../../Modules/Fisio/Services/Questionarios/PontuacaoQuestionario.php)
- [RespostaInvalidaException.php](../../../Modules/Fisio/Services/Questionarios/RespostaInvalidaException.php)
- [ResponsavelPaciente.php](../../../Modules/Fisio/Services/ResponsavelPaciente.php)
- [SequenciaCalculator.php](../../../Modules/Fisio/Services/SequenciaCalculator.php)
- [SessaoDoPortal.php](../../../Modules/Fisio/Services/SessaoDoPortal.php)
- [AgendaDeEnvio.php](../../../Modules/Fisio/Services/Whatsapp/AgendaDeEnvio.php)
- [CanalDaClinica.php](../../../Modules/Fisio/Services/Whatsapp/CanalDaClinica.php)
- [EnvioWhatsappFisio.php](../../../Modules/Fisio/Services/Whatsapp/EnvioWhatsappFisio.php)
- [EnviosFalhosDoDia.php](../../../Modules/Fisio/Services/Whatsapp/EnviosFalhosDoDia.php)
- [LinkDoPortalPorToken.php](../../../Modules/Fisio/Services/Whatsapp/LinkDoPortalPorToken.php)
- [MensagensFisio.php](../../../Modules/Fisio/Services/Whatsapp/MensagensFisio.php)
- [XpDoDiaCalculator.php](../../../Modules/Fisio/Services/XpDoDiaCalculator.php)

## Models / Entities — 23

- [Appointment.php](../../../Modules/Fisio/Entities/Appointment.php)
- [AppointmentType.php](../../../Modules/Fisio/Entities/AppointmentType.php)
- [BookingSettings.php](../../../Modules/Fisio/Entities/BookingSettings.php)
- [Checkin.php](../../../Modules/Fisio/Entities/Checkin.php)
- [EscopoBibliotecaFisio.php](../../../Modules/Fisio/Entities/Concerns/EscopoBibliotecaFisio.php)
- [SemExclusaoDeDadoClinico.php](../../../Modules/Fisio/Entities/Concerns/SemExclusaoDeDadoClinico.php)
- [Consent.php](../../../Modules/Fisio/Entities/Consent.php)
- [Desafio.php](../../../Modules/Fisio/Entities/Desafio.php)
- [Exercise.php](../../../Modules/Fisio/Entities/Exercise.php)
- [ExerciseFavorite.php](../../../Modules/Fisio/Entities/ExerciseFavorite.php)
- [GameLedger.php](../../../Modules/Fisio/Entities/GameLedger.php)
- [Pacote.php](../../../Modules/Fisio/Entities/Pacote.php)
- [PacoteLancamento.php](../../../Modules/Fisio/Entities/PacoteLancamento.php)
- [Patient.php](../../../Modules/Fisio/Entities/Patient.php)
- [PortalDevice.php](../../../Modules/Fisio/Entities/PortalDevice.php)
- [PortalToken.php](../../../Modules/Fisio/Entities/PortalToken.php)
- [Program.php](../../../Modules/Fisio/Entities/Program.php)
- [ProgramItem.php](../../../Modules/Fisio/Entities/ProgramItem.php)
- [Protocol.php](../../../Modules/Fisio/Entities/Protocol.php)
- [Questionnaire.php](../../../Modules/Fisio/Entities/Questionnaire.php)
- [Record.php](../../../Modules/Fisio/Entities/Record.php)
- [ScheduleBlock.php](../../../Modules/Fisio/Entities/ScheduleBlock.php)
- [WaitlistEntry.php](../../../Modules/Fisio/Entities/WaitlistEntry.php)

## Observers — 1

- [VendaDoPacoteObserver.php](../../../Modules/Fisio/Observers/VendaDoPacoteObserver.php)

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

## Migrations (schema) — 14

- [2026_10_18_000001_create_fisio_tables.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000001_create_fisio_tables.php)
- [2026_10_18_000002_add_synonyms_to_fisio_exercises.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000002_add_synonyms_to_fisio_exercises.php)
- [2026_10_18_000003_add_responsible_user_to_fisio_patients.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000003_add_responsible_user_to_fisio_patients.php)
- [2026_10_18_000006_add_favoritos_e_equipamento_biblioteca_fisio.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000006_add_favoritos_e_equipamento_biblioteca_fisio.php)
- [2026_10_18_000081_add_crisis_to_fisio_checkins.php](../../../Modules/Fisio/Database/Migrations/2026_10_18_000081_add_crisis_to_fisio_checkins.php)
- [2026_10_19_000071_create_fisio_portal_devices.php](../../../Modules/Fisio/Database/Migrations/2026_10_19_000071_create_fisio_portal_devices.php)
- [2026_10_19_000090_seed_biblioteca_global_fisio.php](../../../Modules/Fisio/Database/Migrations/2026_10_19_000090_seed_biblioteca_global_fisio.php)
- [2026_10_19_000095_add_client_key_to_fisio_checkins.php](../../../Modules/Fisio/Database/Migrations/2026_10_19_000095_add_client_key_to_fisio_checkins.php)
- [2026_10_19_000120_add_missoes_desafio_jogo_fisio.php](../../../Modules/Fisio/Database/Migrations/2026_10_19_000120_add_missoes_desafio_jogo_fisio.php)
- [2026_11_04_000301_create_fisio_agenda_tables.php](../../../Modules/Fisio/Database/Migrations/2026_11_04_000301_create_fisio_agenda_tables.php)
- [2026_11_04_000302_create_fisio_agenda_regras_tables.php](../../../Modules/Fisio/Database/Migrations/2026_11_04_000302_create_fisio_agenda_regras_tables.php)
- [2026_11_04_000400_create_fisio_packages.php](../../../Modules/Fisio/Database/Migrations/2026_11_04_000400_create_fisio_packages.php)
- [2026_11_04_000500_create_fisio_settings.php](../../../Modules/Fisio/Database/Migrations/2026_11_04_000500_create_fisio_settings.php)
- [2026_11_04_000510_create_fisio_pre_anamneses.php](../../../Modules/Fisio/Database/Migrations/2026_11_04_000510_create_fisio_pre_anamneses.php)

## Seeders — 2

- [FisioBibliotecaSeeder.php](../../../Modules/Fisio/Database/Seeders/FisioBibliotecaSeeder.php)
- [biblioteca-l0.php](../../../Modules/Fisio/Database/Seeders/biblioteca-l0.php)

## Config — 1

- [config.php](../../../Modules/Fisio/Config/config.php)

## Views (Blade) — 3

- [folha-programa.blade.php](../../../Modules/Fisio/Resources/views/pdf/folha-programa.blade.php)
- [laudo-evolucao.blade.php](../../../Modules/Fisio/Resources/views/pdf/laudo-evolucao.blade.php)
- [prontuario.blade.php](../../../Modules/Fisio/Resources/views/pdf/prontuario.blade.php)

## Telas (Inertia/React) — 11

- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/Index.tsx)
- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx)
- [Agendamento.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Agendamento.tsx)
- [Whatsapp.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Whatsapp.tsx)
- [Onboarding.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Onboarding.tsx)
- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx)
- [Pacote.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Pacote.tsx)
- [Show.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx)
- [Painel.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.tsx)
- [Index.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Protocolos/Index.tsx)
- [Portal.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx)

## Componentes / apoio de tela — 24

- [SemanaHoras.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/SemanaHoras.tsx)
- [CamposFacetas.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/CamposFacetas.tsx)
- [FavoritoBotao.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/FavoritoBotao.tsx)
- [FiltrosFacetas.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/FiltrosFacetas.tsx)
- [OrigemContato.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/_components/OrigemContato.tsx)
- [Agendar.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Agendar.tsx)
- [Checkin.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Checkin.tsx)
- [Configuracoes.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Configuracoes.tsx)
- [Constancia.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Constancia.tsx)
- [Consultas.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Consultas.tsx)
- [Entrada.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Entrada.tsx)
- [Hoje.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Hoje.tsx)
- [Instalar.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Instalar.tsx)
- [PreAnamnese.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/PreAnamnese.tsx)
- [AvaliacaoEstruturada.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/AvaliacaoEstruturada.tsx)
- [EditorPrograma.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/EditorPrograma.tsx)
- [EvolucaoPaciente.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/EvolucaoPaciente.tsx)
- [ExportarProntuarioPdf.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/ExportarProntuarioPdf.tsx)
- [FiltroResponsavel.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/FiltroResponsavel.tsx)
- [LinkPortal.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/LinkPortal.tsx)
- [MidiaExercicio.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/MidiaExercicio.tsx)
- [PerfilClinicoCampos.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/PerfilClinicoCampos.tsx)
- [Questionarios.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/Questionarios.tsx)
- [ResponsavelPaciente.tsx](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/ResponsavelPaciente.tsx)

## Charters (lei da tela) — 11

- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/Index.charter.md)
- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.charter.md)
- [Agendamento.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Agendamento.charter.md)
- [Whatsapp.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Whatsapp.charter.md)
- [Onboarding.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Onboarding.charter.md)
- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.charter.md)
- [Pacote.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Pacote.charter.md)
- [Show.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.charter.md)
- [Painel.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.charter.md)
- [Index.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Protocolos/Index.charter.md)
- [Portal.charter.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.charter.md)

## Casos (contrato UC) — 11

- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/Index.casos.md)
- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.casos.md)
- [Agendamento.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Agendamento.casos.md)
- [Whatsapp.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Configuracoes/Whatsapp.casos.md)
- [Onboarding.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Onboarding.casos.md)
- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.casos.md)
- [Pacote.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Pacote.casos.md)
- [Show.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.casos.md)
- [Painel.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Painel.casos.md)
- [Index.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Protocolos/Index.casos.md)
- [Portal.casos.md](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md)

## Testes (Pest) — 60

- 45 em [Modules/Fisio/Tests/Feature/](../../../Modules/Fisio/Tests/Feature)
- 2 em [Modules/Fisio/Tests/Support/](../../../Modules/Fisio/Tests/Support)
- 13 em [Modules/Fisio/Tests/Unit/](../../../Modules/Fisio/Tests/Unit)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 20

- [LinkDoPortal.php](../../../Modules/Fisio/Contracts/LinkDoPortal.php)
- [CheckinFactory.php](../../../Modules/Fisio/Database/Factories/CheckinFactory.php)
- [ExerciseFactory.php](../../../Modules/Fisio/Database/Factories/ExerciseFactory.php)
- [PatientFactory.php](../../../Modules/Fisio/Database/Factories/PatientFactory.php)
- [ProgramFactory.php](../../../Modules/Fisio/Database/Factories/ProgramFactory.php)
- [DadoClinicoImutavelException.php](../../../Modules/Fisio/Exceptions/DadoClinicoImutavelException.php)
- [PacoteIndisponivelException.php](../../../Modules/Fisio/Exceptions/PacoteIndisponivelException.php)
- [SinalDeAlertaNotification.php](../../../Modules/Fisio/Notifications/SinalDeAlertaNotification.php)
- [semana.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/Agenda/_components/semana.ts)
- [facetas.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/_components/facetas.ts)
- [agendaPortal.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/agendaPortal.ts)
- [portal.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/portal.ts)
- [avaliacao.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/avaliacao.ts)
- [avisoQr.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/avisoQr.ts)
- [condicoes.ts](../../../Modules/Fisio/Resources/js/Pages/Fisio/_components/condicoes.ts)
- [fisio.php](../../../Modules/Fisio/Resources/lang/pt/fisio.php)
- [README.md](../../../Modules/Fisio/Tests/README.md)
- [composer.json](../../../Modules/Fisio/composer.json)
- [module.json](../../../Modules/Fisio/module.json)
- [SCOPE.md](../../../memory/requisitos/Fisio/SCOPE.md)
