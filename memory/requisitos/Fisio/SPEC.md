---
id: requisitos-fisio-spec
module: Fisio
version: "1.0"
last_updated: "2026-10-18"
owner: wagner
status: rascunho
status_nota: "ativo desde 2026-10-18 (ADR 0437 aceita); esqueleto de Modules/Fisio no PR #9536 (US-FISIO-002), sem schema nem telas ainda"
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

**Implementado em:** _pendente_ — trio entregue em features/f1-constancia/, âncora fecha com a US-FISIO-002

> owner: wagner · priority: p0 · estimate: 2h · status: todo · type: story
> `npm run sdd:init -- Fisio/f1-constancia --us US-FISIO-001 ...` e `feature-lint --check` verde. Concilia os 4 ajustes do HANDOFF (XP por config, justificativa de contraindicação, busca na F1 condicionada, WhatsApp pelo `ChannelDriverFactory`).

### US-FISIO-002 · Esqueleto `Modules/Fisio` (ativação por empresa, permissões, menu condicionado) — **P0**

**Implementado em:** _pendente_ — esqueleto no PR #9536 (rotas de negócio, schema e telas ainda não existem); âncora fecha com o CI verde

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: US-FISIO-001. DoD: empresa sem o pacote não vê menu nem rota (`FisioModuloVisibilidadeTest`, `FisioModuloContratoTest`). `ModuleUtil` pelo container.

### US-FISIO-003 · Schema, models e append-only de prontuário e ledger do jogo — **P0**

**Implementado em:** `Modules/Fisio/Database/Migrations/2026_10_18_000001_create_fisio_tables.php` · `Modules/Fisio/Entities/` · verificado@b9a7a58 (2026-10-18, #9537)

> owner: — · priority: p0 · estimate: 6h · status: done · type: story
> blocked_by: US-FISIO-002. DoD: isolamento 98×99 (`FisioMultiTenantTest`); UPDATE/DELETE em registro assinado falha (`FisioProntuarioTest`); nenhum `forceDelete` em dado clínico.

**DoD:** as 11 tabelas `fisio_*` com `business_id` e escopo entre 98 e 99; trigger recusa UPDATE/DELETE em `fisio_records` assinado e em `fisio_game_ledger`; nenhum `forceDelete` em dado clínico — `FisioEsquemaContratoTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioEsquemaContratoTest.php`

### US-FISIO-004 · Biblioteca semeada e revisada pela responsável técnica — **P0**

**Implementado em:** _pendente_ — parcial: `Modules/Fisio/Http/Controllers/BibliotecaController.php`, rotas `fisio.biblioteca.*` e tela `Modules/Fisio/Resources/js/Pages/Fisio/Biblioteca/Index.tsx` existem; o seed `Modules/Fisio/Database/Seeders/FisioBibliotecaSeeder.php` está vazio (conteúdo aguarda passos documentados e revisão da Dra. Dejeane). A âncora fecha com o seed.

> owner: — · priority: p0 · estimate: 4h · status: doing · type: story
> blocked_by: US-FISIO-003. 38 exercícios com `times_per_day`, avisos de respiração/apoio e `contraindication_flags`. DoD: revisão da Dra. Dejeane anotada no PR. Detalhe: `etapas/01-biblioteca.md`.

**DoD:** a biblioteca global (`business_id` nulo) aparece para toda clínica com o módulo e o exercício próprio só para a dona; exercício próprio grava com a empresa da sessão e `is_custom`; global responde 403 à edição e próprio de outra empresa 404; filtro por região e busca por texto em nome e sinônimos (busca na F1 aceita por [W] em 2026-10-18, ADR 0437 §14 nº 3) — `FisioBibliotecaTest`; favorito por profissional na empresa (favoritar um global não o altera), filtros de posição e equipamento das listas fechadas de `config('fisio.biblioteca.*')` combinados com região, busca e "só favoritos", e posição/equipamento no exercício próprio (comparativo Physitrack, 2026-10-18) — `FisioBibliotecaFavoritosTest`. **Falta para fechar:** o seed dos 38 exercícios, que depende de passos/dose/contraindicações escritos e da revisão da Dra. Dejeane anotada no PR.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php` · `Modules/Fisio/Tests/Feature/FisioBibliotecaFavoritosTest.php`

### US-FISIO-005 · Pacientes e prontuário conforme Res. COFFITO 414/2012 — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/PacientesController.php` · `Modules/Fisio/Http/Controllers/RegistrosController.php` · rotas `fisio.pacientes.*` (`Modules/Fisio/Routes/web.php`) · telas `Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx` e `Show.tsx` (+ charters) · exportação em PDF `Modules/Fisio/Http/Controllers/ProntuarioPdfController.php` + `Modules/Fisio/Services/ProntuarioExportacao.php` (UC-FPS-19..20) — veredito pela lane Verticais (`FisioPacienteCrudTest`, `FisioProntuarioTest`, `FisioProntuarioPdfTest`)

> owner: — · priority: p0 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-003. Paciente é `contact`; avaliação, evolução e adendo carimbados com nome e CREFITO; marcadores de condição. DoD: `FisioPacienteCrudTest`, `FisioProntuarioTest`.

**DoD:** paciente criado sobre um `contact` da empresa, um por contato, meta 1–7 e marcadores da lista fechada; excluir só bloqueia; avaliação/evolução/adendo carimbados com usuário, CREFITO e hora do servidor; avaliação exige os campos mínimos da Res. 414; registro assinado responde 405 a PUT/DELETE; o prontuário exporta em PDF (registros assinados em ordem cronológica, carimbados) e cada exportação fica no `activity_log` — `FisioPacienteCrudTest`, `FisioProntuarioTest`, `FisioProntuarioPdfTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php` · `Modules/Fisio/Tests/Feature/FisioProntuarioTest.php` · `Modules/Fisio/Tests/Feature/FisioProntuarioPdfTest.php`

### US-FISIO-006 · Editor de programa, protocolos-modelo e aviso de contraindicação — **P0**

**Implementado em:** `Modules/Fisio/Http/Controllers/ProgramasController.php` · `Modules/Fisio/Http/Controllers/ProtocolosController.php` · `Modules/Fisio/Services/EditorProgramaPayload.php` · rotas `fisio.pacientes.programas.store`, `fisio.programas.*`, `fisio.protocolos.*` (`Modules/Fisio/Routes/web.php`) · tela `Modules/Fisio/Resources/js/Pages/Fisio/_components/EditorPrograma.tsx` montada na ficha (`Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx`) — veredito pela lane Verticais (`FisioProgramaEditorTest`)

> owner: — · priority: p0 · estimate: 8h · status: done · type: story
> blocked_by: US-FISIO-004, US-FISIO-005. Aviso nunca bloqueia; exige justificativa que vai ao prontuário (ADR 0437 §13.2). DoD: `FisioProgramaEditorTest`.

**DoD:** programa nasce ativo, `origin=manual`, `created_by` da sessão, limiar padrão 7, com instrução de parada obrigatória; novo ativo encerra o anterior; item herda a dose padrão e vai para o fim; dose validada sem tocar outro item; reordenar exige a lista exata; remover recompacta sem tocar check-in; exercício de outra clínica recusado; contraindicação responde 409 sem gravar e, confirmada, grava quem/quais/quando e a justificativa no item e um registro novo no prontuário; protocolo-modelo aplicado vira `origin=protocolo`, modelo global só se copia (editar = 403) — `FisioProgramaEditorTest`. Conteúdo clínico (exercícios e protocolos globais) aguarda a Dra. Dejeane: sem seed, o editor funciona com exercícios da clínica e mostra estado vazio.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioProgramaEditorTest.php`

### US-FISIO-007 · Portal do paciente (link + PIN, consentimentos, check-in, telemonitoramento) — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 12h · status: todo · type: story
> blocked_by: US-FISIO-006. Token HMAC + PIN com lockout (padrão OficinaAuto); consentimentos versionados; check-in idempotente com sinais de alerta; cada check-in vira registro de telemonitoramento (Res. COFFITO 619/2025). DoD: `FisioPortalAcessoTest`, `FisioCheckinTest`, `FisioPortalSegurancaClinicaTest`.

### US-FISIO-008 · Jogo de constância (XP, nível, sequência, missões, pausa por dor) — **P1**

**Implementado em:** _pendente_

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-007. Regras da ADR 0437 §6 e números de `etapas/02-f1-constancia.md`. DoD: `FisioJogoTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest`.

Núcleo de cálculo (serviços puros, sem rota nem tela; a US segue pendente): `Modules/Fisio/Services/NivelCalculator.php`, `Modules/Fisio/Services/SequenciaCalculator.php`, `Modules/Fisio/Services/DiaLocal.php`, `Modules/Fisio/Services/MetaSemanal.php`.

### US-FISIO-009 · Lembrete diário e envio do programa por WhatsApp (driver whatsmeow) — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story
> blocked_by: US-FISIO-007. Pelo módulo `Whatsapp` via `ChannelDriverFactory` (nunca whatsmeow direto); mitigações de banimento da ADR 0437 §13.1. DoD: `FisioWhatsappJobTest`; canal caído gera alerta ao profissional.

### US-FISIO-010 · Painel, evolução e fila de atenção — **P0**

**Implementado em:** _pendente_

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: US-FISIO-008. Adesão 7d, dor, nível; fila de abandono separada da fila de alerta clínico. DoD: `FisioPainelTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest`.

Núcleo de cálculo (serviços puros, sem rota nem tela; a US segue pendente): `Modules/Fisio/Services/AdesaoCalculator.php`, `Modules/Fisio/Services/AtencaoDetector.php`.

### US-FISIO-011 · Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) — **P1**

**Implementado em:** _pendente_ — parcial: pontuação (`Modules/Fisio/Services/Questionarios/PontuacaoQuestionario.php`), catálogo, `Modules/Fisio/Http/Controllers/QuestionariosController.php` e a seção na ficha existem; o texto literal dos itens da versão brasileira validada não está transcrito (`texto: null`) e aguarda a Dra. Dejeane. A âncora fecha com os enunciados.

> owner: — · priority: p1 · estimate: 6h · status: doing · type: story
> blocked_by: US-FISIO-005. Texto da versão brasileira validada, referência na tela; pontuação conferida com a publicação. ODI, NDI, KOOS, QuickDASH e WOMAC bloqueados até licença.

**DoD:** resposta gravada com a pontuação calculada no servidor (RMDQ soma de "sim", PSFS média, LEFS soma, SPADI subescalas ÷50/÷80 e total ÷130), `score` do corpo ignorado; bloqueados por licença e respostas incompletas recusados; escrita exige `fisio.editar`, outra empresa 404; histórico na ficha por prop deferida; referência bibliográfica na tela; o sistema não interpreta a pontuação. Pendente fora do código: texto literal dos itens da versão BR validada (revisão da Dra. Dejeane) — `FisioQuestionarioTest`, `PontuacaoQuestionarioTest`.

**Testado em:** `Modules/Fisio/Tests/Feature/FisioQuestionarioTest.php` · `Modules/Fisio/Tests/Unit/PontuacaoQuestionarioTest.php`

### US-FISIO-012 · Fecha o loop da F1 — **P1**

**Implementado em:** _pendente_

> owner: wagner · priority: p1 · estimate: 1h · status: todo · type: story
> blocked_by: US-FISIO-001..011. Âncoras `Implementado em:` com `verificado@sha7` (ADR 0273).
