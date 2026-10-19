---
id: requisitos-fisio-spec
module: Fisio
version: "1.0"
last_updated: "2026-10-19"
owner: wagner
status: rascunho
status_nota: "ativo desde 2026-10-18 (ADR 0437 aceita); F1 no main, atrás do pacote fisio_module; em 2026-10-19 só US-FISIO-011 (texto dos questionários) e US-FISIO-012 seguem abertas"
type: spec
scope: vertical (fisioterapia, autônomo e consultório pequeno)
related_adrs:
  - 0437-modulo-fisio-vertical-fisioterapia
  - 0093-multi-tenant-isolation-tier-0
  - 0306-strangler-spec-anchored-reconstrucao-sdd
  - 0368-funil-admissao-feature-pesquisa-propoe-w-admite
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0264-governanca-executavel-trio-dominio-e2e
---

# SPEC — Fisio (fisioterapia: programa domiciliar, constância e prontuário)

> **Decisão:** [ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) — aceita por [W] em 2026-10-18.
> **Responsável técnica do conteúdo clínico:** Dra. Dejeane (fisioterapeuta).
> **Como executar:** [`HANDOFF-F1.md`](HANDOFF-F1.md) (ordem, regras duras, modelo de dados) e as especificações por etapa em [`etapas/`](etapas/).
> **Convenção do ID:** `US-FISIO-NNN`. As US abaixo são a F1 "Constância"; F2 a F4 entram depois, cada uma admitida pelo funil da ADR 0368.

## User Stories — F1 Constância

### US-FISIO-001 · Trio SDD da F1 e US no SPEC — **P0**

**Implementado em:** _pendente_ — o trio está em `memory/requisitos/Fisio/features/f1-constancia/` e `npm run feature:lint:check` sai sem erro (2026-10-19), mas nenhum teste declara `@covers-us US-FISIO-001`; por ser documento, a âncora fecha junto com a US-FISIO-012

> owner: wagner · priority: p0 · estimate: 2h · status: doing · type: story
> `npm run sdd:init -- Fisio/f1-constancia --us US-FISIO-001 ...` e `feature-lint --check` verde. Concilia os 4 ajustes do HANDOFF (XP por config, justificativa de contraindicação, busca na F1 condicionada, WhatsApp pelo `ChannelDriverFactory`).

**DoD:** trio `requirements.md` · `plan.md` · `tasks.md` em `features/f1-constancia/` com as US-FISIO-001..012 ligadas, e `npm run feature:lint:check` sem erro.

### US-FISIO-002 · Esqueleto `Modules/Fisio` (ativação por empresa, permissões, menu condicionado) — **P0**

**Implementado em:** `Modules/Fisio/module.json` · `Modules/Fisio/Http/Controllers/DataController.php` (pacote `fisio_module`, permissões `fisio.*`) · `Modules/Fisio/Providers/FisioServiceProvider.php` · verificado@7e6237c (2026-10-19, esqueleto do #9536)

> owner: — · priority: p0 · estimate: 4h · status: done · type: story
> blocked_by: US-FISIO-001. DoD: empresa sem o pacote não vê menu nem rota (`FisioModuloVisibilidadeTest`, `FisioModuloContratoTest`). `ModuleUtil` pelo container.

**DoD:** pacote `fisio_module` nasce desligado e permissões `fisio.*` sem concessão padrão; empresa sem o pacote ou usuário sem `fisio.access` não vê o menu nem abre `/fisio`; sem hardcode de business e sem `forceDelete` em dado clínico — `FisioModuloVisibilidadeTest`, `FisioModuloContratoTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioModuloVisibilidadeTest.php` · `Modules/Fisio/Tests/Feature/FisioModuloContratoTest.php` (este estava sem lane até 2026-10-19; entrou na `verticais-pest`)

### US-FISIO-003 · Schema, models e append-only de prontuário e ledger do jogo — **P0**

**Implementado em:** `Modules/Fisio/Database/Migrations/2026_10_18_000001_create_fisio_tables.php` · `Modules/Fisio/Entities/` · verificado@b9a7a58 (2026-10-18, #9537)

> owner: — · priority: p0 · estimate: 6h · status: done · type: story
> blocked_by: US-FISIO-002. DoD: isolamento 98×99 (`FisioMultiTenantTest`); UPDATE/DELETE em registro assinado falha (`FisioProntuarioTest`); nenhum `forceDelete` em dado clínico.

**DoD:** as 11 tabelas `fisio_*` com `business_id` e escopo entre 98 e 99; trigger recusa UPDATE/DELETE em `fisio_records` assinado e em `fisio_game_ledger`; nenhum `forceDelete` em dado clínico — `FisioEsquemaContratoTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioEsquemaContratoTest.php`

### US-FISIO-004 · Biblioteca semeada e revisada pela responsável técnica — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/BibliotecaController.php` · rotas `fisio.biblioteca.*` · tela `Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx` · seed `Modules/Fisio/Database/Seeders/FisioBibliotecaSeeder.php` + `Modules/Fisio/Database/Seeders/biblioteca-l0.php` (38 exercícios, aprovados sem ressalvas pela responsável técnica, Dra. Djenane Detroz, CREFITO 18.341-F, em 2026-10-19), semeado no deploy por `Modules/Fisio/Database/Migrations/2026_10_19_000090_seed_biblioteca_global_fisio.php` — veredito pela lane Verticais (`FisioBibliotecaTest`, `FisioBibliotecaFavoritosTest`, `BibliotecaL0Test`)

> owner: — · priority: p0 · estimate: 4h · status: done · type: story
> blocked_by: US-FISIO-003. 38 exercícios com `times_per_day`, avisos de respiração/apoio e `contraindication_flags`. DoD: revisão da Dra. Dejeane anotada no PR. Detalhe: `etapas/01-biblioteca.md`.

**DoD:** a biblioteca global (`business_id` nulo) aparece para toda clínica com o módulo e o exercício próprio só para a dona; exercício próprio grava com a empresa da sessão e `is_custom`; global responde 403 à edição e próprio de outra empresa 404; filtro por região e busca por texto em nome e sinônimos (busca na F1 aceita por [W] em 2026-10-18, ADR 0437 §14 nº 3) — `FisioBibliotecaTest`; favorito por profissional na empresa (favoritar um global não o altera), filtros de posição e equipamento das listas fechadas de `config('fisio.biblioteca.*')` combinados com região, busca e "só favoritos", e posição/equipamento no exercício próprio (comparativo Physitrack, 2026-10-18) — `FisioBibliotecaFavoritosTest`. Seed dos 38 com as listas fechadas — `BibliotecaL0Test` (UC-FBI-11).

**Mídia do exercício próprio (parcial, comparativo Physitrack):** foto JPG/PNG/WebP ou vídeo MP4 curto no exercício da clínica, guardado pelo backbone `Modules/Arquivos` (ADR 0123/0214) sob a empresa da sessão e servido por rota com checagem de empresa; global recusa (403); tipo, tamanho e declaração "não mostra paciente" no servidor; remoção reversível só da dona — `Modules/Fisio/Http/Controllers/BibliotecaMidiaController.php`, `Modules/Fisio/Services/MidiaExercicio.php`, `FisioBibliotecaMidiaTest` (UC-FBI-05..07). Fica para depois: transcodificação, quadros-chave e PDF do exercício (`etapas/01-biblioteca.md` §3.6–3.7).

**Lint de linguagem Anvisa (etapa 01 B-06):** exercício próprio, protocolo (editar e salvar do programa) e instruções do programa (instrução de parada, versão de crise, observação do exercício) com termo proibido da ADR 0437 §5 voltam 422, com mensagem em PT-BR que diz o termo e o motivo; a lista é `config('fisio.linguagem_anvisa.termos_proibidos')`, cópia literal da ADR — `Modules/Fisio/Services/LinguagemAnvisa.php`, `FisioLinguagemAnvisaTest` (UC-FBI-12).

**Testado em:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php` · `Modules/Fisio/Tests/Feature/FisioBibliotecaFavoritosTest.php` · `Modules/Fisio/Tests/Feature/FisioBibliotecaMidiaTest.php`

### US-FISIO-005 · Pacientes e prontuário conforme Res. COFFITO 414/2012 — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/PacientesController.php` · `Modules/Fisio/Http/Controllers/RegistrosController.php` · rotas `fisio.pacientes.*` (`Modules/Fisio/Routes/web.php`) · telas `Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx` e `Show.tsx` (+ charters) · exportação em PDF `Modules/Fisio/Http/Controllers/ProntuarioPdfController.php` + `Modules/Fisio/Services/ProntuarioExportacao.php` (UC-FPS-19..20) — veredito pela lane Verticais (`FisioPacienteCrudTest`, `FisioProntuarioTest`, `FisioProntuarioPdfTest`)

> owner: — · priority: p0 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-003. Paciente é `contact`; avaliação, evolução e adendo carimbados com nome e CREFITO; marcadores de condição. DoD: `FisioPacienteCrudTest`, `FisioProntuarioTest`.

**DoD:** paciente criado sobre um `contact` da empresa, um por contato, meta 1–7 e marcadores da lista fechada; excluir só bloqueia; avaliação/evolução/adendo carimbados com usuário, CREFITO e hora do servidor; avaliação exige os campos mínimos da Res. 414; registro assinado responde 405 a PUT/DELETE; o prontuário exporta em PDF (registros assinados em ordem cronológica, carimbados) e cada exportação fica no `activity_log` — `FisioPacienteCrudTest`, `FisioProntuarioTest`, `FisioProntuarioPdfTest`. Melhoria do comparativo (cadastro): contato apagado recusado, contato escolhido por busca no servidor e contato criado na hora sem duplicar celular — `FisioPacienteContatoTest` (UC-FPI-05..07).

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php` · `Modules/Fisio/Tests/Feature/FisioProntuarioTest.php` · `Modules/Fisio/Tests/Feature/FisioProntuarioPdfTest.php` · `Modules/Fisio/Tests/Feature/FisioPacienteContatoTest.php`

**Ampliação 2026-10-18 · fisioterapeuta responsável e transferência (feito).** Cada paciente tem um responsável, usuário da mesma empresa com acesso ao Fisio; quem cadastra é o responsável. A lista mostra e filtra por responsável ("Meus pacientes") e a ficha transfere para outro profissional da empresa. Cada transferência fica no `activity_log` (quem, de quem, para quem, quando) e nada se apaga. Transferir exige `fisio.editar`; destino de outra empresa ou sem acesso ao Fisio responde 422. Pacientes cadastrados antes da coluna ficam sem responsável até alguém transferir. Fora: notificações e comparação entre profissionais. Referência: a Physitrack permite compartilhar e transferir clientes entre profissionais. Casos UC-FPI-20 e UC-FPS-30..32.
Implementado em `Modules/Fisio/Http/Controllers/ResponsavelController.php` · `Modules/Fisio/Services/ResponsavelPaciente.php` · migration `Modules/Fisio/Database/Migrations/2026_10_18_000003_add_responsible_user_to_fisio_patients.php` · componentes `Modules/Fisio/Resources/js/Pages/Fisio/_components/ResponsavelPaciente.tsx` e `FiltroResponsavel.tsx`. Testado em `Modules/Fisio/Tests/Feature/FisioPacienteResponsavelTest.php`.

### US-FISIO-006 · Editor de programa, protocolos-modelo e aviso de contraindicação — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/ProgramasController.php` · `Modules/Fisio/Http/Controllers/ProtocolosController.php` · `Modules/Fisio/Services/EditorProgramaPayload.php` · rotas `fisio.pacientes.programas.store`, `fisio.programas.*`, `fisio.protocolos.*` (`Modules/Fisio/Routes/web.php`) · tela `Modules/Fisio/Resources/js/Pages/Fisio/_components/EditorPrograma.tsx` montada na ficha (`Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx`) — veredito pela lane Verticais (`FisioProgramaEditorTest`)

> owner: — · priority: p0 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-004, US-FISIO-005. Aviso nunca bloqueia; exige justificativa que vai ao prontuário (ADR 0437 §13.2). DoD: `FisioProgramaEditorTest`.

**DoD:** programa nasce ativo, `origin=manual`, `created_by` da sessão, limiar padrão 7, com instrução de parada obrigatória; novo ativo encerra o anterior; item herda a dose padrão e vai para o fim; dose validada sem tocar outro item; reordenar exige a lista exata; remover recompacta sem tocar check-in; exercício de outra clínica recusado; contraindicação responde 409 sem gravar e, confirmada, grava quem/quais/quando e a justificativa no item e um registro novo no prontuário; protocolo-modelo aplicado vira `origin=protocolo`, modelo global só se copia (editar = 403) — `FisioProgramaEditorTest`. Conteúdo clínico (exercícios e protocolos globais) aguarda a Dra. Dejeane: sem seed, o editor funciona com exercícios da clínica e mostra estado vazio.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioProgramaEditorTest.php`

### US-FISIO-007 · Portal do paciente (link + PIN, consentimentos, check-in, telemonitoramento) — **P0**

**Implementado em:** `Modules/Fisio/Services/PortalTokenService.php` · `Modules/Fisio/Services/Consentimentos.php` · `Modules/Fisio/Services/CheckinDoPortal.php` · `Modules/Fisio/Services/AparelhosDoPortal.php` · `Modules/Fisio/Http/Controllers/PortalController.php` · `Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx`

> owner: — · priority: p0 · estimate: 12h · status: done · type: story
> blocked_by: US-FISIO-006. Token HMAC + PIN com lockout (padrão OficinaAuto); consentimentos versionados; check-in idempotente com sinais de alerta; cada check-in vira registro de telemonitoramento (Res. COFFITO 619/2025). DoD: `FisioPortalAcessoTest`, `FisioCheckinTest`, `FisioPortalSegurancaClinicaTest`.

**Corte US-FISIO-007a (feito, 2026-10-18) · link + PIN e consentimentos.** A clínica gera na ficha um link do portal (token assinado com o business dentro, guardado só em hash, 30 dias, gerar outro revoga o anterior) e um PIN de 4 dígitos mostrado uma vez; 5 PINs errados travam por 30 min. As rotas públicas `/fisio/p/{token}` ficam fora do middleware de admin, com sessão própria do paciente amarrada ao link e escopo pelo business do token: paciente bloqueado, de outra clínica ou de clínica sem o pacote cai no mesmo estado vazio. Consentimentos separados e carimbados (versão da config, IP, canal, data): `teleatendimento` obrigatório para ver o programa, `whatsapp` e `camera` opcionais, revogáveis pelo portal. Implementado em `Modules/Fisio/Services/PortalTokenService.php` · `Modules/Fisio/Services/Consentimentos.php` · `Modules/Fisio/Http/Controllers/PortalController.php` · `Modules/Fisio/Http/Controllers/PortalLinkController.php` · tela mínima `Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.tsx` e botão `Modules/Fisio/Resources/js/Pages/Fisio/_components/LinkPortal.tsx` na ficha. Testado em `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php` e `Modules/Fisio/Tests/Feature/FisioPortalLinkTest.php`. Falta (US-FISIO-007b): gravar o check-in, o telemonitoramento, sinais de alerta e modo pausa (`FisioCheckinTest`, `FisioPortalSegurancaClinicaTest`) e a tela desenhada.

**Corte US-FISIO-007b (2026-10-19) · telas do paciente e check-in.** "Como foi hoje" grava `fisio_checkins` uma vez por dia (reenvio atualiza; UNIQUE no banco), só com itens do programa ativo, dor 0–10, esforço fechado, observação até 280 caracteres e sinais da lista fechada; o 1º envio do dia gera o `fisio_records` `telemonitoramento` (assíncrono, canal portal, assinado pelo sistema, ligado ao check-in por `checkin_id`) e reenvio com relato diferente gera `adendo` (`Modules/Fisio/Services/CheckinDoPortal.php`). Depois de gravar dispara `CheckinRegistrado` (o JogoService lança só a diferença positiva). Sinal de alerta novo notifica o profissional do programa e o responsável (`Modules/Fisio/Notifications/SinalDeAlertaNotification.php`, sino do sistema) e o painel já o põe na fila de atenção. Dor ≥ limiar do programa ou sinal de alerta: resposta e tela só com a `stop_instruction`. "Lembrar este aparelho por 30 dias" com cookie httpOnly cifrado e `fisio_portal_devices` (só hash, ligado ao token, revogado com o link) em `Modules/Fisio/Services/AparelhosDoPortal.php`. "Hoje estou em crise" só com a versão de crise escrita pelo profissional. Telas Entrada, Hoje e Check-in em `Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/`. Testado em `Modules/Fisio/Tests/Feature/FisioCheckinTest.php`, `Modules/Fisio/Tests/Feature/FisioJogoTest.php`, `Modules/Fisio/Tests/Feature/FisioPortalSegurancaClinicaTest.php` e `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`.

**Corte T-13 parte 1 (2026-10-19) · app instalável** (etapa 02 §3.6). Manifesto por link em `GET /fisio/p/{token}/manifest.webmanifest` (`PortalController::manifest`, público, sem o nome da clínica), ícones `public/img/fisio-portal-*.png`, service worker `public/sw-fisio-portal.js` (escopo `/fisio/p/`) que guarda a tela do dia só quando o servidor manda `X-Fisio-Offline: guardar` — o que só acontece em aparelho lembrado — e apaga a cópia nos outros casos; convite de instalação em `Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Instalar.tsx`. Testado em `Modules/Fisio/Tests/Feature/FisioPortalAppTest.php` (UC-FPP-26..28). Falta (parte 2): fila offline do check-in; push para quem instalou fica para decisão (biblioteca nova).

**DoD:** link inválido, adulterado, expirado ou revogado é o mesmo estado vazio; PIN com 5 tentativas e trava de 30 min; consentimento de teleatendimento antes de qualquer dado, opcionais separados e carimbados; check-in uma vez por dia (reenvio atualiza), só itens do programa ativo, dor 0–10 e esforço fechado; cada dia vira 1 telemonitoramento e reenvio diferente vira adendo; sinal de alerta notifica o profissional e entra na fila de atenção; dor ≥ limiar ou sinal de alerta mostra só a instrução do profissional; portal sem série de dor; "lembrar este aparelho" cai com outro link, prazo, revogação ou link novo; crise só com versão escrita pelo profissional; nada vaza entre clínicas — `FisioPortalAcessoTest`, `FisioPortalLinkTest`, `FisioCheckinTest`, `FisioJogoTest`, `FisioPortalSegurancaClinicaTest`, `FisioPortalAparelhoTest`. Contrato: `Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md` (UC-FPP-01..25).

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php` · `Modules/Fisio/Tests/Feature/FisioPortalLinkTest.php` · `Modules/Fisio/Tests/Feature/FisioCheckinTest.php` · `Modules/Fisio/Tests/Feature/FisioPortalSegurancaClinicaTest.php` · `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`

### US-FISIO-008 · Jogo de constância (XP, nível, sequência, missões, pausa por dor) — **P1**

**Implementado em:** `Modules/Fisio/Services/JogoService.php` · `Modules/Fisio/Services/XpDoDiaCalculator.php` · `Modules/Fisio/Services/LimiarDorCalculator.php` · `Modules/Fisio/Services/NivelCalculator.php` · `Modules/Fisio/Services/SequenciaCalculator.php` · evento `Modules/Fisio/Events/CheckinRegistrado.php` disparado por `Modules/Fisio/Services/CheckinDoPortal.php` → `Modules/Fisio/Listeners/LancarXpDoCheckin.php` · números em `config('fisio.jogo')` · verificado@7e6237c (2026-10-19, #9564 + #9567). Escudos, missões, desafio e medalhas (T-08c) ficam fora desta âncora, no backlog do contrato.

> owner: — · priority: p1 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-007 (só a ligação com o portal). Regras da ADR 0437 §6 e números de `etapas/02-f1-constancia.md`.

**DoD:** XP só pelo prescrito — `xp.exercicio_concluido` × (vezes feitas ÷ prescritas, cada item até `times_per_day`) e `xp.checkin` sempre, inclusive com dor alta, ambos × `normalizacao_dias_base` ÷ `sessions_per_week`; item fora do programa e vez a mais valem 0; reenvio lança só a diferença positiva e nada é estornado (ledger append-only); teto semanal; dor ≥ limiar ou sinal de alerta pausa o jogo sem quebrar a sequência e põe o paciente na fila de atenção; em `sobre_basal` o limiar é a dor de base da paciente + margem; dia de crise conta para a sequência; nível derivado do ledger, sem coluna e sem ranking; evento de outra clínica ou paciente bloqueado não lança — `FisioJogoServicoTest`, `FisioJogoTest`, `XpDoDiaCalculatorTest`, `LimiarDorCalculatorTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest`. Contrato: `memory/requisitos/Fisio/_telas/jogo-constancia.casos.md` (UC-FJG-01..05).

**Testado em:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php` · `Modules/Fisio/Tests/Feature/FisioJogoTest.php` · `Modules/Fisio/Tests/Unit/XpDoDiaCalculatorTest.php` · `Modules/Fisio/Tests/Unit/LimiarDorCalculatorTest.php` · `Modules/Fisio/Tests/Unit/NivelCalculatorTest.php` · `Modules/Fisio/Tests/Unit/SequenciaCalculatorTest.php`
### US-FISIO-009 · Lembrete diário e envio do programa por WhatsApp (canal escolhido pela clínica) — **P0**

**Implementado em:** `Modules/Fisio/Jobs/EnviarProgramaWhatsappJob.php` · `Modules/Fisio/Jobs/EnviarLembreteDiarioJob.php` · `Modules/Fisio/Console/LembretesCommand.php` (`fisio:lembretes`, agendado) · `Modules/Fisio/Http/Controllers/EnvioWhatsappController.php` (rota `fisio.programas.enviar`: fila ou `wa.me` manual sem canal ativo) · `Modules/Fisio/Services/Whatsapp/EnvioWhatsappFisio.php` · `Modules/Fisio/Services/Whatsapp/AgendaDeEnvio.php` · link pelo contrato `Modules/Fisio/Contracts/LinkDoPortal.php` (portal real do US-FISIO-007a) · verificado@7e6237c (2026-10-19, #9566). Envio pelo módulo `Whatsapp` existente, sem canal, driver nem tabela próprios do Fisio (decisão [W] 2026-10-19: "o WhatsApp já está pronto no sistema"); a escolha do canal, o aceite de risco do `whatsmeow` e o alerta de canal caído são do módulo `Whatsapp`.

> owner: — · priority: p0 · estimate: 6h · status: done · type: story
> blocked_by: US-FISIO-007. Pelo módulo `Whatsapp` via `ChannelDriverFactory` (nunca um driver direto), com o canal lido da configuração da clínica: `meta_cloud`, `whatsmeow` (exige aceite de risco registrado) ou envio manual `wa.me`; mitigações de banimento da ADR 0437 §13.1 quando o canal for `whatsmeow`. DoD: `FisioWhatsappJobTest`; canal caído gera alerta ao profissional.

**DoD:** link primeiro e PIN depois, nunca na mesma mensagem; no máximo 1 lembrete e 1 envio de programa por paciente por dia (chave persistida); só com consentimento de WhatsApp; silêncio das 21h às 7h e teto por hora; sem canal ativo, `wa.me` manual; nada sai para outra clínica — `FisioWhatsappJobTest`, `AgendaDeEnvioTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioWhatsappJobTest.php` · `Modules/Fisio/Tests/Unit/AgendaDeEnvioTest.php`

### US-FISIO-010 · Painel, evolução e fila de atenção — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/PainelController.php` · `Modules/Fisio/Services/PainelFisio.php` · `Modules/Fisio/Services/AdesaoCalculator.php` · `Modules/Fisio/Services/AtencaoDetector.php` · rota `fisio.painel` · tela `Modules/Fisio/Resources/js/Pages/Fisio/Painel.tsx` · aba Evolução `Modules/Fisio/Resources/js/Pages/Fisio/_components/EvolucaoPaciente.tsx` · verificado@7e6237c (2026-10-19, #9553; o UC-FPN-05 voltou a rodar inteiro com o portal do #9567)

> owner: — · priority: p0 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-008. Adesão 7d, dor, nível; fila de abandono separada da fila de alerta clínico. DoD: `FisioPainelTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest`.

**DoD:** painel com adesão de 7 dias, dor e nível derivados dos check-ins; fila de abandono separada da fila de alerta clínico; paciente bloqueado fora da tabela; nada da outra clínica no painel nem na evolução — `FisioPainelTest`, `FisioEvolucaoTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPainelTest.php` · `Modules/Fisio/Tests/Feature/FisioEvolucaoTest.php` · `Modules/Fisio/Tests/Unit/AdesaoCalculatorTest.php` · `Modules/Fisio/Tests/Unit/AtencaoDetectorTest.php`

### US-FISIO-011 · Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) — **P1**

**Implementado em:** _pendente_ — parcial: pontuação (`Modules/Fisio/Services/Questionarios/PontuacaoQuestionario.php`), catálogo, `Modules/Fisio/Http/Controllers/QuestionariosController.php` e a seção na ficha existem; o texto literal dos itens da versão brasileira validada não está transcrito (`texto: null`) e aguarda a Dra. Dejeane. A âncora fecha com os enunciados.

> owner: — · priority: p1 · estimate: 6h · status: doing · type: story
> blocked_by: US-FISIO-005. Texto da versão brasileira validada, referência na tela; pontuação conferida com a publicação. ODI, NDI, KOOS, QuickDASH e WOMAC bloqueados até licença.

**DoD:** resposta gravada com a pontuação calculada no servidor (RMDQ soma de "sim", PSFS média, LEFS soma, SPADI subescalas ÷50/÷80 e total ÷130), `score` do corpo ignorado; bloqueados por licença e respostas incompletas recusados; escrita exige `fisio.editar`, outra empresa 404; histórico na ficha por prop deferida; referência bibliográfica na tela; o sistema não interpreta a pontuação. Pendente fora do código: texto literal dos itens da versão BR validada (revisão da Dra. Dejeane) — `FisioQuestionarioTest`, `PontuacaoQuestionarioTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioQuestionarioTest.php` · `Modules/Fisio/Tests/Unit/PontuacaoQuestionarioTest.php`

### US-FISIO-012 · Fecha o loop da F1 — **P1**

**Implementado em:** _pendente_ — em 2026-10-19 só falta a US-FISIO-011 (texto dos questionários, depende da Dra. Dejeane); 001 a 010 já têm âncora.

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: US-FISIO-001..011. Âncoras `Implementado em:` com `verificado@sha7` (ADR 0273).
