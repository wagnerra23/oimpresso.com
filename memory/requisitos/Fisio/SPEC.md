---
id: requisitos-fisio-spec
module: Fisio
version: "1.0"
last_updated: "2026-11-11"
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

**Corte T-13 parte 2 (2026-10-19) · fila offline do check-in** (etapa 02 §3.6). Sem rede, o check-in fica no aparelho lembrado (`portal.ts`, `localStorage` por link, um item por dia) e é reenviado ao abrir a tela do dia e quando a internet volta. Cada envio leva `chave` (gravada em `fisio_checkins.client_key`, migration `2026_10_19_000095`): a mesma chave de novo responde sucesso sem gravar (`CheckinDoPortal::registrar`). `PortalController::checkin` recusa `dia` diferente de hoje (422 `dia_passou`) e envio `offline` de aparelho não lembrado (403); a tela recebe `guardar_offline`. Testado em `Modules/Fisio/Tests/Feature/FisioPortalFilaOfflineTest.php` (UC-FPP-30..32). Check-in de ontem enviado hoje se perde: aceitá-lo é decisão [W] (mexe na sequência).

**DoD:** link inválido, adulterado, expirado ou revogado é o mesmo estado vazio; PIN com 5 tentativas e trava de 30 min; consentimento de teleatendimento antes de qualquer dado, opcionais separados e carimbados; check-in uma vez por dia (reenvio atualiza), só itens do programa ativo, dor 0–10 e esforço fechado; cada dia vira 1 telemonitoramento e reenvio diferente vira adendo; sinal de alerta notifica o profissional e entra na fila de atenção; dor ≥ limiar ou sinal de alerta mostra só a instrução do profissional; portal sem série de dor; "lembrar este aparelho" cai com outro link, prazo, revogação ou link novo; crise só com versão escrita pelo profissional; nada vaza entre clínicas — `FisioPortalAcessoTest`, `FisioPortalLinkTest`, `FisioCheckinTest`, `FisioJogoTest`, `FisioPortalSegurancaClinicaTest`, `FisioPortalAparelhoTest`. Contrato: `Modules/Fisio/Resources/js/Pages/Fisio/Public/Portal.casos.md` (UC-FPP-01..25).

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPortalAcessoTest.php` · `Modules/Fisio/Tests/Feature/FisioPortalLinkTest.php` · `Modules/Fisio/Tests/Feature/FisioCheckinTest.php` · `Modules/Fisio/Tests/Feature/FisioPortalSegurancaClinicaTest.php` · `Modules/Fisio/Tests/Feature/FisioPortalAparelhoTest.php`

### US-FISIO-008 · Jogo de constância (XP, nível, sequência, missões, pausa por dor) — **P1**

**Implementado em:** `Modules/Fisio/Services/JogoService.php` · `Modules/Fisio/Services/XpDoDiaCalculator.php` · `Modules/Fisio/Services/LimiarDorCalculator.php` · `Modules/Fisio/Services/NivelCalculator.php` · `Modules/Fisio/Services/SequenciaCalculator.php` · evento `Modules/Fisio/Events/CheckinRegistrado.php` disparado por `Modules/Fisio/Services/CheckinDoPortal.php` → `Modules/Fisio/Listeners/LancarXpDoCheckin.php` · números em `config('fisio.jogo')` · verificado@7e6237c (2026-10-19, #9564 + #9567). T-08c, parte com fonte: missões do pool prescrito, desafio semanal e 10 das 12 medalhas em `Modules/Fisio/Services/MissoesCalculator.php` · `Modules/Fisio/Services/MedalhasCalculator.php` · `Modules/Fisio/Entities/Desafio.php` · rota `fisio.portal.desafio` · tela `Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/Constancia.tsx` (UC-FJG-06..09, UC-FPP-33..34). Escudos ficam no backlog do contrato.

> owner: — · priority: p1 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-007 (só a ligação com o portal). Regras da ADR 0437 §6 e números de `etapas/02-f1-constancia.md`.

**DoD:** XP só pelo prescrito — `xp.exercicio_concluido` × (vezes feitas ÷ prescritas, cada item até `times_per_day`) e `xp.checkin` sempre, inclusive com dor alta, ambos × `normalizacao_dias_base` ÷ `sessions_per_week`; item fora do programa e vez a mais valem 0; reenvio lança só a diferença positiva e nada é estornado (ledger append-only); teto semanal; dor ≥ limiar ou sinal de alerta pausa o jogo sem quebrar a sequência e põe o paciente na fila de atenção; em `sobre_basal` o limiar é a dor de base da paciente + margem; dia de crise conta para a sequência; nível derivado do ledger, sem coluna e sem ranking; evento de outra clínica ou paciente bloqueado não lança — `FisioJogoServicoTest`, `FisioJogoTest`, `XpDoDiaCalculatorTest`, `LimiarDorCalculatorTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest`. Contrato: `memory/requisitos/Fisio/_telas/jogo-constancia.casos.md` (UC-FJG-01..09).

**Corte da T-08c (2026-10-19):** entra o que a etapa 02 §3.4 escreveu — 3 missões por dia do pool prescrito (registro do dia; fazer o exercício X; todas as séries do exercício Y), +10 cada até 100 de bônus por semana; desafio semanal opcional na segunda (n−1 ou n dos n dias, +40 uma vez; recusar não tira XP, decisão append-only em `fisio_game_desafios`); 10 medalhas derivadas do histórico — `FisioJogoConstanciaTest`, `MissoesCalculatorTest`, `MedalhasCalculatorTest`. Fica de fora, sem fonte, decisão [W]: (1) card "Você sabia?" e (2) missão "Veja a dica do profissional", que esperam texto de dica escrito ou aprovado pela responsável técnica; (3) missão "no horário combinado" e medalha "Horário combinado 10 vezes", que esperam o campo de horário combinado por paciente e a hora do check-in; (4) medalha "Programa concluído" e modo manutenção na alta, que esperam um estado de alta no programa distinto de `encerrado`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioJogoServicoTest.php` · `Modules/Fisio/Tests/Feature/FisioJogoTest.php` · `Modules/Fisio/Tests/Feature/FisioJogoConstanciaTest.php` · `Modules/Fisio/Tests/Unit/MissoesCalculatorTest.php` · `Modules/Fisio/Tests/Unit/MedalhasCalculatorTest.php` · `Modules/Fisio/Tests/Unit/XpDoDiaCalculatorTest.php` · `Modules/Fisio/Tests/Unit/LimiarDorCalculatorTest.php` · `Modules/Fisio/Tests/Unit/NivelCalculatorTest.php` · `Modules/Fisio/Tests/Unit/SequenciaCalculatorTest.php`
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

## User Stories — Lote 5 · entrada (canvas Prescreve Pro, 2026-11-04)

> Dezena 050 reservada ao Lote 5 (combinado entre as sessões dos lotes; Lote 4 usa 020..029).

### US-FISIO-050 · Primeira configuração da clínica (Onboarding, 5 passos) — **P1**

**Implementado em:** `Modules/Fisio/Http/Controllers/OnboardingController.php` · `Modules/Fisio/Resources/js/Pages/Fisio/Onboarding.tsx` · `Modules/Fisio/Services/ConfiguracaoClinica.php` (tabela `fisio_settings`) · `Modules/Fisio/Services/Whatsapp/CanalDaClinica.php`

> owner: wagner · priority: p1 · estimate: 4h · status: done · type: story
> Protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/Onboarding.dc.html`. Perfil (nome que o paciente vê e CREFITO, por profissional), forma de atendimento PF/PJ (só guardada — o uso fiscal é do Lote 4), canal do WhatsApp com a mesma regra de Configurações › WhatsApp (QR exige aceite de risco), protocolos marcados e conclusão. Foto do perfil fica de fora.

**DoD:** cada passo grava só o dele; perfil por usuário; QR sem aceite não grava; protocolo de outra empresa descartado; sem `fisio.editar` 403; 98×99 — `FisioOnboardingTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioOnboardingTest.php`

### US-FISIO-051 · Pré-anamnese no portal antes da primeira consulta — **P1**

**Implementado em:** `Modules/Fisio/Services/PreAnamneseDoPortal.php` · `Modules/Fisio/Http/Controllers/PortalController.php` (rota `fisio.portal.pre-anamnese`) · `Modules/Fisio/Resources/js/Pages/Fisio/Public/_components/PreAnamnese.tsx` · tabela `fisio_pre_anamneses`

> owner: wagner · priority: p1 · estimate: 4h · status: done · type: story
> Lote 5 (dezena 050). Protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/PacientePreAnamnese.dc.html`. Onde dói, há quanto tempo, dor agora, o que piora/melhora e 5 sinais de alerta da lista fechada do check-in, com o mesmo texto. Sinal marcado avisa pelo sino o profissional do programa e o responsável (sem nenhum dos dois, quem cadastrou o contato) — decisão [W] 2026-11-04 "Sim, faça assim". Regiões e faixas de tempo são rascunho do protótipo, pendentes da revisão da responsável técnica.

**DoD:** uma resposta por paciente; fora das listas 422; mesmos portões do portal; sinal avisa, sem sinal não — `FisioPreAnamneseTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPreAnamneseTest.php`

## User Stories — F3 Operação: agenda

> Admitida por pedido direto de [W] em 2026-11-04 (levar sidebar e telas do canvas Prescreve Pro para produção, lote 3), que vale como decisão ([ADR 0382](../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)). Regras e dados: [`etapas/04-f3-operacao.md`](etapas/04-f3-operacao.md) §3.2 (A1–A21). Fonte visual: `prototipo-ui/cowork/Wagner/prototipos/fisio/` (`Agenda`, `ConfigAgendamentoOnline`, `PacienteAgendar`, `PacienteAgendaConfirmar`, `PacienteConsultas`) e a pesquisa `ComparativoAgendamento`. `PacienteSerieConfirmar` não é agenda (é a confirmação de repetições da câmera, F2).
>
> **Reusar × criar (F3-T01, só a parte da agenda), avaliado em 2026-11-04:** o `bookings` do núcleo (`database/migrations/2018_06_13_133705_create_bookings_table.php`, `app/Restaurant/Booking.php`) é reserva de mesa (mesa, garçom, local, três status) e depende do módulo `booking` do restaurante; não tem profissional, tipo de atendimento, duração, confirmação, lista de espera nem faltas. A agenda da Oficina (`oficina_agendamentos`) é por veículo e já registrou a mesma decisão. A agenda de instalação da Comunicação Visual é por equipe e veículo. Decisão: tabelas próprias `fisio_*`, como a etapa F3 previa. Cobrança (sinal por Pix, pacote, recibo) fica para o lote do Financeiro: aqui só o gancho (`deposit_percent` desligado). Lembrete e confirmação saem pelo módulo `Whatsapp`, como na US-FISIO-009.

### US-FISIO-013 · Schema da agenda: tipos, atendimentos, bloqueios, lista de espera e regras da clínica — **P0**

**Implementado em:** _pendente_ — schema e models no PR do lote 3; a âncora fecha com as regras da US-FISIO-014.

> owner: — · priority: p0 · estimate: 4h · status: doing · type: story
> Tabelas `fisio_appointment_types`, `fisio_appointments`, `fisio_schedule_blocks`, `fisio_waitlist`, `fisio_booking_settings`, todas com `business_id` indexado + FK. Atendimento não é apagado (sai por status); o recado e a nota do paciente ficam só na clínica.

**DoD:** as 5 tabelas com `business_id`; sob a sessão da 98 cada model enxerga só a 98 e a 99 só a 99; atendimento não é apagado pelo Eloquent; uma linha de regras por empresa — `FisioAgendaEsquemaTest`.

### US-FISIO-014 · Regras da agenda: conflito, status, falta, cancelamento e lista de espera — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: US-FISIO-013. A1 (conflito de profissional), A4 (encaixe com confirmação), A5 (bloqueio), A7 (vaga liberada aponta os compatíveis da lista de espera), A8 (prazo de cancelamento; falta e cancelamento tardio só **sugeridos**, o profissional confirma), A10 (status; "realizado" exige evolução assinada), A21 (bloqueio do app depois de N faltas sem aviso, reversível pelo profissional).

### US-FISIO-015 · Tela Agenda da clínica e configuração do agendamento pelo app — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-014. `/fisio/agenda` (semana por profissional, legenda de status, resumo do dia, vaga liberada e lista de espera, nova sessão em 3 campos, bloquear horário) e `/fisio/agenda/configuracoes` (ligar o app, quem pode marcar, tipos liberados, horários abertos, regras). Item "Agenda" no menu do Fisio.

### US-FISIO-016 · Agenda no portal do paciente: marcar, minhas consultas, confirmar, cheguei — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 10h · status: todo · type: story
> blocked_by: US-FISIO-015. A9 (horários livres dentro das regras), A7 ("me avise se abrir vaga"), A6 (confirmar / remarcar / não vou poder ir pelo link da mensagem), A19 ("Cheguei" a partir de 1 h antes), A20 (recado ao marcar; nota 1–5 privada), remarcar e cancelar até o prazo da política, bloqueio por faltas. Dependentes (A16) e sinal por Pix (A17) entram depois, com o consentimento do responsável legal e com o Financeiro.

## F3 · Dinheiro — mapa de donos (F3-T01, lote 4 do protótipo Prescreve Pro)

> Origem: [W] 2026-11-04 pediu levar o protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/` para produção em lotes. Este é o lote 4: `Financeiro.dc.html` (aba "Pacote e cobrança" da ficha), `FiscalFila.dc.html` (documentos fiscais do dia) e `Planos.dc.html`. Regra de [W] para o lote: **nenhum financeiro paralelo**. O Fisio mostra e dispara; título, nota e assinatura ficam nos módulos donos. A F3 ainda não passou pelo funil da ADR 0368 (F3-T00); estas US registram o levantamento F3-T01 e o que o lote 4 constrói sem escrever valor.

### Varredura do núcleo (medida em 2026-11-04 sobre `origin/main` 3583e435c2, repo inteiro com `git grep`)

| pergunta | resposta medida | consequência para o Fisio |
|---|---|---|
| Quem cria `fin_titulos` fora do próprio Financeiro? | 7 chamadas `Titulo::create(` fora de teste e seeder: 5 dentro de `Modules/Financeiro` (Unificado, Impostos, Dda, TituloAuto, listener de cobrança paga), 1 em `Modules/PaymentGateway` (importação Inter), 1 em `app/Http/Controllers/Api/App`. Não há serviço público para outro módulo criar título; cada um repete numeração e campos. | O Fisio **não** chama `Titulo::create`. O único caminho já ligado de ponta a ponta para empresa cliente é a **venda do núcleo**: `transactions` → `TituloAutoService::sincronizarDeVenda` cria o título; `transaction_payments` → `registrarPagamento` dá a baixa. |
| O RecurringBilling grava título no Financeiro? | 0 ocorrências de `fin_titulos` ou `Titulo` em `Modules/RecurringBilling`. A fatura recorrente vive em `rb_invoices`; o valor `recurring` do enum `fin_titulos.origem` só é lido. | Mensalidade de paciente pelo RecurringBilling não aparece no Financeiro hoje. Lacuna do núcleo, não do Fisio. |
| A NFS-e emite o grupo IBS/CBS? | 0 arquivos de `Modules/NFSe` (fora teste) citam IBS ou CBS. Controle: a mesma busca acha 10 em `Modules/NfeBrasil`. A emissão se liga à venda por `nfse_emissoes.transaction_id`. | Destaque de IBS/CBS na NFS-e é obrigatório desde 01/10/2026 (etapa F3 §3.5). Dívida do núcleo NFSe, que bloqueia a emissão real para clínica CNPJ. |
| Existe Receita Saúde no código? | 0 arquivos `.php`, `.ts` ou `.tsx`. | É o único pedaço fiscal que o Fisio guarda (etapa F3 §3.7), e só a marca de emissão. O valor fica no `transaction_payments`. |
| A empresa diz se atende como pessoa física ou jurídica? | Não há campo no núcleo (`nfe_business_configs.regime` não tem PF). O lote 5 cria `fisio_settings` com a chave `atendimento.tipo` (`pf` ou `pj`). | O lote 4 lê essa chave; sem ela, a tela mostra as duas abas e não esconde Pix Automático. |
| CPF de quem paga | `contacts.cpf_cnpj` existe (migração `2026_05_21_140000`). | A fila do Receita Saúde lê o CPF do contato pagador; sem CPF, o recibo fica "falta CPF do pagador". |
| De onde vêm os planos do `Planos.dc.html`? | É o preço do **produto Fisio para o profissional**, não do paciente. A venda de plano do oimpresso é o Superadmin (`packages` com `fisio_module` em `package_details`), cobrada pelo PaymentGateway com `origem_type='subscription_license'`. | Dono é o **Superadmin**, não o RecurringBilling. O Fisio não tem tela de planos. |

### Mapa tela → módulo dono

| tela do protótipo | bloco | dono | o Fisio guarda | o Fisio faz |
|---|---|---|---|---|
| `Financeiro.dc.html` | "Pacote atual · 6 / 10" e "Sessões do pacote" | **Fisio** | `fisio_packages` (sessões, validade, `transaction_id`) e `fisio_package_ledger` append-only, sem valor em dinheiro | mostra o saldo derivado e consome ou estorna sessão |
| `Financeiro.dc.html` | "Cobrança" (títulos pago e a gerar) | **Financeiro** (`fin_titulos` da venda) | `transaction_id` no pacote | lê os títulos pelo model do Financeiro, filtrados por empresa e pela venda do pacote |
| `Financeiro.dc.html` | "Renovar pacote" e meios (Pix Automático só CNPJ) | **Sells** (venda) + **Financeiro** (`LinkDePagamentoDoTitulo`) + **RecurringBilling** (mensalidade e Pix Automático) | nada | abre a venda e o link de pagamento do núcleo |
| `Financeiro.dc.html` | "Fiscal · NFS-e autorizada" | **NFSe** (`nfse_emissoes` por `transaction_id`) | nada | mostra o status |
| `FiscalFila.dc.html` | aba "Clínica (CNPJ)": NFS-e do dia | **NFSe** | nada | lista as emissões do dia das vendas a pacientes, com "Corrigir" levando ao contato |
| `FiscalFila.dc.html` | aba "Autônomo (CPF)": fila do Receita Saúde | **Fisio** (`fisio_health_receipts`, só marca) sobre `transaction_payments` | id do pagamento, número do recibo, quem marcou e quando | monta a fila do dia e marca "enviado" |
| `FiscalFila.dc.html` | "Baixar o lote do dia" | **Fisio** | nada | só depois do layout oficial (US-FISIO-024) |
| `Planos.dc.html` | planos e adicional NFS-e | **Superadmin** (`packages`) + **PaymentGateway** (`subscription_license`) | nada | nada; preço é decisão de [W] |

**Menu (sem tocar em `DataController.php`).** Proposta: não criar item "Financeiro" dentro do Fisio. O pacote é uma aba da ficha do paciente e o dinheiro já tem o item Financeiro do ERP. A fila fiscal, no protótipo, aparece com o item **Fiscal** do ERP ativo. Ela entra como sub-item do Fisio ("Fiscal do dia") só se [W] preferir; menu é decisão dele.

### US-FISIO-020 · Pacote de sessões com saldo derivado de ledger append-only — **P0**

**Implementado em:** _pendente_ — lote 4, PR de schema.

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: nenhum. Etapa F3 §3.4.2 e F3-T08 (parte sem dinheiro). `fisio_packages` (business_id, patient_id, sessões contratadas, validade, `transaction_id` nulo até a venda existir) e `fisio_package_ledger` (crédito na abertura, débito por sessão, estorno por lançamento inverso). Nenhuma coluna de valor em dinheiro.

**DoD:** saldo = soma do ledger, nunca coluna editável; ledger sem UPDATE nem DELETE (trigger, como `fisio_game_ledger`); débito além do saldo recusado; validade vencida bloqueia débito e a extensão fica registrada; pacote de outra empresa não aparece nem aceita débito (98×99).

### US-FISIO-021 · Tela "Pacote e cobrança" da ficha do paciente — **P0**

**Implementado em:** _pendente_ — lote 4, PR de tela.

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: US-FISIO-020. Fonte de design `prototipo-ui/cowork/Wagner/prototipos/fisio/Financeiro.dc.html`. Página própria em `/fisio/pacientes/{id}/pacote` (a barra de abas da ficha é do lote 1). Só leitura de dinheiro: títulos e nota vêm do Financeiro e do NFSe pela venda do pacote.

**DoD:** mostra saldo, sessões do ledger, títulos da venda do pacote e status da NFS-e; empresa sem venda ligada mostra estado vazio sem erro; Pix Automático só aparece para `atendimento.tipo = pj`; paciente de outra empresa 404; nenhum valor gravado pela tela.

### US-FISIO-022 · Vender e renovar pacote pela venda do núcleo — **P0**

**Implementado em:** _pendente_ — depende de aprovação de [W] (regra mestre de valor).

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: US-FISIO-020, contato e produto pré-preenchidos na tela de venda (núcleo Sells), aprovação de [W] com dupla confirmação do cálculo e tabela antes→depois (`memory/proibicoes.md`, regra mestre). "Renovar pacote" abre a venda do núcleo com o paciente e o serviço do pacote; ao finalizar, a venda é ligada ao pacote (`transaction_id`) e o crédito de sessões entra no ledger. Parcelado = parcelas da venda; mensalidade = RecurringBilling; Pix e cartão = `LinkDePagamentoDoTitulo` do Financeiro.

**DoD:** nenhum `Titulo::create` no Fisio; o título nasce do `TituloAutoService`; o valor do pacote só existe na venda; venda cancelada estorna o crédito de sessões por lançamento inverso.

### US-FISIO-023 · Fila fiscal do dia: NFS-e (CNPJ) e Receita Saúde (CPF) — **P0**

**Implementado em:** _pendente_ — lote 4, PR da fila fiscal.

> owner: — · priority: p0 · estimate: 5h · status: todo · type: story
> blocked_by: nenhum. Fonte de design `FiscalFila.dc.html`. Vale para qualquer venda do núcleo cujo cliente é paciente do Fisio (pacote ou sessão avulsa), então funciona antes da US-FISIO-022. NFS-e: leitura de `nfse_emissoes` do dia. Receita Saúde: um item por `transaction_payments` do dia de venda a paciente; `fisio_health_receipts` guarda só `payment_id`, status (`pendente`, `enviado`, `retificar`), número do recibo, quem marcou e quando. Valor e data vêm do pagamento.

**DoD:** pagamento sem CPF do pagador aparece "falta CPF do pagador"; marcar enviado é idempotente; pagamento de outra empresa não aparece nem aceita marca (98×99); nenhuma cópia de valor em tabela do Fisio.

### US-FISIO-024 · Lote do Receita Saúde no layout oficial — **P1**

**Implementado em:** _pendente_ — bloqueado por fonte externa.

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: layout do Manual do Receita Saúde v2.1 (perguntas 24 e 25), ainda não obtido (etapa F3 §3.6, F3-T12). O botão "Baixar o lote do dia" do protótipo fica desligado, com o aviso do próprio protótipo, até lá.

**DoD:** arquivo aceito na importação oficial com 3 recibos de teste.

### US-FISIO-025 · Planos do produto Fisio no Superadmin — **P1**

**Implementado em:** _pendente_ — preço e nomes são decisão de [W].

> owner: wagner · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: decisão de [W] sobre preço, limite de 5 pacientes no plano grátis e desconto de estudante (o protótipo marca "preços de lançamento, a validar no piloto"). Fonte de design `Planos.dc.html`. Os planos são `packages` do Superadmin com `fisio_module`; a cobrança é `subscription_license` do PaymentGateway. O limite de pacientes do plano grátis é regra do Fisio lida do pacote, nunca hardcode por empresa.

**DoD:** empresa no plano grátis não cadastra o 6º paciente; trocar de plano não depende de deploy; o adicional NFS-e liga o módulo NFSe pelo pacote.

### Lacunas do núcleo (viram tarefa do módulo dono, não do Fisio)

1. **NFSe**: grupo IBS/CBS ausente (obrigatório desde 01/10/2026) e preset de fisioterapia (item 4.08, cTribNac 04.08.02, NBS 1.2301.92.00, redução de 60%).
2. **Financeiro**: falta serviço público para outro módulo criar título a receber; hoje há 7 criadores com a mesma lógica repetida.
3. **RecurringBilling**: fatura recorrente de empresa cliente não vira título no Financeiro.
4. **Sells**: a tela de venda não aceita contato e produto pré-preenchidos, que é o que "Renovar pacote" precisa.

## User Stories — Laudo biopsicossocial (fibromialgia)

> Pedido de [W] em 2026-11-11. Base legal: Lei 15.176/2025, que criou o art. 1º-C na Lei 14.705/2023: a equiparação da pessoa com fibromialgia à pessoa com deficiência depende de avaliação biopsicossocial por equipe multiprofissional (art. 2º da Lei 13.146/2015). Em Santa Catarina o laudo segue o modelo aprovado pela Deliberação 500/CIB/2026 da SES-SC (Nota Técnica Conjunta SES/FCEE nº 001/2026, versão 1.0 de 26/10/2026), usado para a Carteira de Identificação da Pessoa com Fibromialgia (CIPF). O laudo é emitido pela Atenção Primária do município e a carteira segue o fluxo da FCEE; o Fisio gera o laudo no layout oficial com o que a clínica avaliou.

### US-FISIO-060 · Laudo de avaliação biopsicossocial no modelo SES-SC — **P1**

**Implementado em:** _pendente_ — em construção por partes: matriz IFBr-M (`Modules/Fisio/Services/Laudo/MatrizIfbrm.php`), esquema e imutabilidade (`Modules/Fisio/Database/Migrations/2026_11_11_000700_create_fisio_laudos_bps.php`, `Modules/Fisio/Entities/LaudoBiopsicossocial.php`, `Modules/Fisio/Entities/LaudoBiopsicossocialEquipe.php`); escrita por rota (`Modules/Fisio/Http/Controllers/LaudoBiopsicossocialController.php`: criar, preencher, equipe, finalizar e nova versão); apoio da fisioterapia (`Modules/Fisio/Services/Laudo/ApoioFisioterapia.php`) e PDF no layout oficial (`Modules/Fisio/Services/Laudo/LaudoBiopsicossocialDocumento.php`, `Modules/Fisio/Resources/views/pdf/laudo-biopsicossocial.blade.php`); tela na ficha (`Modules/Fisio/Resources/js/Pages/Fisio/_components/LaudoBiopsicossocial.tsx`, props `laudo_bps_dominios` e `laudos_bps` em `Modules/Fisio/Http/Controllers/PacientesController.php`, protótipo `prototipo-ui/cowork/Wagner/prototipos/fisio/LaudoBiopsicossocial.dc.html`). O apoio já mostra os questionários de fibromialgia da etapa 07 (P-03). Falta o smoke em staging.

> owner: — · priority: p1 · estimate: 14h · status: doing · type: story
> blocked_by: nenhum para o formulário, a matriz, a equipe e o apoio de dor e adesão. Os questionários de fibromialgia (FIQR-Br, ACR 2016/FSQ, PGIC, sentar e levantar em 30 s) entram como apoio quando a etapa 07 (P-03) estiver no `main`.

**DoD:** formulário campo a campo do modelo SES-SC: identificação (paciente, nascimento, diagnóstico por laudo ACR 2016 com data, unidade de APS, impedimentos, CID, natureza); matriz IFBr-M com 8 domínios e 30 itens (100/75/50/25) e total igual à soma do menor valor de cada domínio, calculado pelo sistema; fatores ambientais e fatores pessoais e psicológicos; conclusão com três perguntas Sim/Não e observação, marcadas só pela equipe; equipe com no mínimo dois profissionais de áreas distintas, um deles médico, validada antes de finalizar; local e data obrigatórios. Laudo finalizado não muda, correção é laudo novo. O PDF segue o layout oficial e cada geração grava uma linha no `activity_log` só com ids. A tela diz que a emissão da carteira segue o fluxo da FCEE, sem prometer resultado. A empresa 99 não vê nada da 98 — `MatrizIfbrmTest`, `FisioLaudoBpsEsquemaTest`, `FisioLaudoBpsEscritaTest`, `FisioLaudoBpsApoioTest`, `FisioLaudoBpsPdfTest`, `FisioLaudoBpsTelaTest`.

**Testado em:** `Modules/Fisio/Tests/Unit/MatrizIfbrmTest.php` · `Modules/Fisio/Tests/Feature/FisioLaudoBpsEsquemaTest.php` · `Modules/Fisio/Tests/Feature/FisioLaudoBpsEscritaTest.php` · `Modules/Fisio/Tests/Feature/FisioLaudoBpsApoioTest.php` · `Modules/Fisio/Tests/Feature/FisioLaudoBpsPdfTest.php` · `Modules/Fisio/Tests/Feature/FisioLaudoBpsTelaTest.php`

## User Stories — Exames e receitas do paciente

> Pedido de [W] em 2026-11-11: anexar exames e receitas e preencher os campos pela foto, com transcrição pela IA conferida pelo profissional, e o envio pelo portal. A IA só transcreve; nada interpreta dado clínico (ADR 0437 §5 e proposta da ADR 0450). Protótipo: `prototipo-ui/cowork/Wagner/prototipos/fisio/ExamesReceitas.dc.html` e `PacienteEnviarExame.dc.html`.

### US-FISIO-063 · Exames e receitas por foto, com transcrição sugerida e conferida — **P1**

**Implementado em:** _pendente_ — em construção por partes. Parte 1 (anexos na ficha): `Modules/Fisio/Database/Migrations/2026_11_11_000830_create_fisio_patient_documents.php`, `Modules/Fisio/Entities/DocumentoPaciente.php`, `Modules/Fisio/Services/DocumentosDoPaciente.php`, `Modules/Fisio/Http/Controllers/DocumentosPacienteController.php`, seção `Modules/Fisio/Resources/js/Pages/Fisio/_components/ExamesReceitas.tsx` na ficha. Faltam: lista de medicamentos estruturada, transcrição pela IA e envio pelo portal.

> owner: — · priority: p1 · estimate: 16h · status: doing · type: story

**DoD:** (1) na ficha, anexar foto ou PDF com tipo (exame de imagem, laboratorial, laudo médico, receita, outro), data e observação; o arquivo vai para o `Modules/Arquivos` (ADR 0123) e só sai pela rota que confere a empresa; o documento não se apaga, tirar da lista exige motivo e grava quem tirou. (2) Lista de medicamentos estruturada (nome, dose, frequência, desde, até), com o texto "Medicamentos em uso" da avaliação funcionando como hoje. (3) Transcrição pela IA a partir da foto, desligada por empresa até ser ligada: na receita, medicamento, dose, data e médico/CRM; no exame, tipo, data e conclusão transcrita literalmente; cada campo nasce como sugestão e o profissional confirma ou corrige antes de gravar, com quem confirmou registrado; a IA nunca interpreta. (4) Envio pelo portal com consentimento próprio; chega como pendente de conferência e só entra no prontuário quando alguém da clínica aceita. A empresa 99 não vê nada da 98, inclusive no download — `FisioDocumentosPacienteTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioDocumentosPacienteTest.php` (UC-FER-01..07).

**Contrato:** casos de uso UC-FER-* no arquivo `_telas/exames-e-receitas.casos.md` desta pasta.

## User Stories — Associação de fibromialgia (dados sociais e relatório agregado)

> Contexto: o cliente atende pessoas com fibromialgia por uma associação que busca recursos em editais, emendas parlamentares e convênios com o município, e precisa mostrar quem é atendido, quanto e se melhora. [W] aprovou em 2026-11-11 os dados sociais opcionais no cadastro e o relatório agregado sem identificação.

### US-FISIO-061 · Dados sociais do paciente, todos opcionais — **P1**

**Implementado em:** `Modules/Fisio/Services/Social/DadosSociais.php` (listas fechadas, regras, validação do CNS e gravação) · `Modules/Fisio/Http/Controllers/DadosSociaisController.php` (rota `fisio.pacientes.dados-sociais.update`) · `Modules/Fisio/Entities/PatientSocial.php` · `Modules/Fisio/Database/Migrations/2026_11_11_000800_create_fisio_patient_social.php` · seção na ficha `Modules/Fisio/Resources/js/Pages/Fisio/_components/DadosSociais.tsx` (props `opcoes_sociais` e `dados_sociais` em `Modules/Fisio/Http/Controllers/PacientesController.php`)

> owner: — · priority: p1 · estimate: 6h · status: done · type: story

Como recepção ou fisioterapeuta da associação, quero registrar o perfil social do paciente sem obrigar nenhuma resposta, para a associação mostrar quem atende.

**DoD:** nascimento, município, UF e bairro moram no contato (`contacts.dob`, `city`, `state`, `neighborhood` — o mesmo endereço do cadastro de clientes); sexo, gênero, raça/cor no padrão do IBGE, cartão SUS com dígito verificador, renda familiar em faixas de salário mínimo, NIS/CadÚnico, situação de trabalho (trabalhando, afastado pelo INSS, aposentado, desempregado, outro), escolaridade, diagnóstico informado (CID, quem e quando), tempo de sintomas, comorbidades, como chegou (UBS/CAPS, indicação, procura própria) e situação da carteira de fibromialgia (CIPF) moram em `fisio_patient_social`, 1:1, com "prefiro não informar" onde cabe. Nenhum campo é obrigatório. Seção própria e recolhida na ficha, com a finalidade escrita na tela, editável só com `fisio.editar`. Dado sensível (LGPD art. 11): não vai ao portal nem à exportação da pesquisa; o `activity_log` guarda só os nomes dos campos alterados. A empresa 99 não vê nem grava nada da 98 — `FisioDadosSociaisTest` (UC-FSO-01..05).

**Testado em:** `Modules/Fisio/Tests/Feature/FisioDadosSociaisTest.php`
