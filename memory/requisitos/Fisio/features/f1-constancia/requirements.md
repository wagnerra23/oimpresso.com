---
id: requisitos-fisio-features-f1-constancia-requirements
feature: f1-constancia
module: Fisio
us: ["US-FISIO-001", "US-FISIO-002", "US-FISIO-003", "US-FISIO-004", "US-FISIO-005", "US-FISIO-006", "US-FISIO-007", "US-FISIO-008", "US-FISIO-009", "US-FISIO-010", "US-FISIO-011", "US-FISIO-012"]
parent_plan: fisio-f1-constancia
sdd: []
related_cus: []
screens: []
created: "2026-10-18"
---

# Requirements — F1 Constância do módulo Fisio

> `screens:` fica vazio até as Pages existirem (US-FISIO-002 em diante); os contratos de tela
> (`casos.md`) já estão escritos e entram com o esqueleto.

> **US-mãe:** [US-FISIO-001](../../SPEC.md) · **Sinal (ADR 0105 + emenda ADR 0368):** admissão explícita de [W] na [ADR 0437](../../../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) (aceita 2026-10-18), com as três peças: verificar (grade contra Physitrack, Kynesia, Vedius, ZenFisio), pesquisar (6 especificações em `etapas/`) e plano (este trio).

## User story

**Como** fisioterapeuta autônomo ou de consultório pequeno, que hoje manda exercício por foto no WhatsApp
**Quero** montar o programa domiciliar em um minuto, mandar pelo WhatsApp e ver quem fez, quem está com dor e quem vai abandonar
**Para** aumentar a adesão do paciente aos exercícios de casa (30–50% não fazem hoje) e intervir antes do abandono

## Clarifications (fase Clarify — Spec Kit 2026)

- **2026-10-17** — P: produto separado ou módulo do oimpresso? → R: módulo ([W]: *"Gostaria que fosse módulo se não for ficar ruim"*).
- **2026-10-18** — P: qual driver de WhatsApp? → R: `whatsmeow`, pelo módulo `Whatsapp` existente ([W]: *"whatsmeow será o driver"*); risco de banimento aceito com as mitigações da ADR 0437 §13.1.
- **2026-10-18 (emenda do mesmo dia)** — P: um driver só? → R: não; os dois, à escolha de cada clínica na tela de configurações do WhatsApp ([W]: *"As duas, acho que depende da escolha do fisioterapeuta"*). Canais: `meta_cloud` (recomendado), `whatsmeow` (com aceite de risco registrado por clínica) e envio manual `wa.me`. ADR 0437 §13.1.
- **2026-10-18** — P: quem revisa o conteúdo clínico? → R: Dra. Dejeane, fisioterapeuta contratada.
- **Aberta** — busca por texto na biblioteca entra na F1? (ADR 0437 §14, decisão nº 3). Até decidir, a F1 entrega filtro por região e equipamento.

## Acceptance criteria (EARS — ADR 0306 importou de Kiro)

- **AC-1** — O SISTEMA DEVE escopar por `business_id` toda leitura e escrita do módulo. _Prova: `FisioMultiTenantTest` (tenant 98 × 99, ADR 0358)._
- **AC-2** — SE a empresa não tem o pacote `fisio_module`, ENTÃO O SISTEMA DEVE esconder menu e responder 403/404 em toda rota `/fisio/*`. _Prova: `FisioModuloVisibilidadeTest`, `FisioModuloContratoTest`._
- **AC-3** — O SISTEMA DEVE gravar prontuário só por inclusão: evolução assinada carimbada com nome e CREFITO, correção por adendo, nenhum UPDATE/DELETE em registro assinado, nenhum `forceDelete` em dado clínico; "excluir paciente" bloqueia. _Prova: `FisioProntuarioTest`, `FisioPacienteCrudTest`, varredura em `FisioModuloContratoTest`._
- **AC-4** — QUANDO o profissional adiciona ao programa um exercício cuja contraindicação cruza com um marcador do paciente, O SISTEMA DEVE avisar (409 na primeira tentativa), exigir justificativa e gravar quem confirmou — nunca bloquear. _Prova: `FisioProgramaEditorTest`._
- **AC-5** — QUANDO o paciente abre o link do portal, O SISTEMA DEVE exigir PIN com bloqueio após tentativas, e consentimento de teleatendimento versionado antes de mostrar qualquer dado. _Prova: `FisioPortalAcessoTest`._
- **AC-6** — QUANDO o paciente envia o check-in, O SISTEMA DEVE gravá-lo de forma idempotente por dia e criar um registro de telemonitoramento no prontuário (Res. COFFITO 619/2025). _Prova: `FisioCheckinTest`._
- **AC-7** — SE a dor é ≥ limiar do programa ou há sinal de alerta, ENTÃO O SISTEMA DEVE mostrar ao paciente só a instrução escrita pelo profissional, notificar o profissional e não quebrar a sequência; o portal nunca expõe série histórica de dor. _Prova: `FisioPortalSegurancaClinicaTest`._
- **AC-8** — O SISTEMA DEVE dar XP só por cumprir o prescrito e por registrar o dia (inclusive com dor alta), sem XP por excesso nem duplicado, com nível e sequência derivados do ledger. _Prova: `FisioJogoTest`, `NivelCalculatorTest`, `SequenciaCalculatorTest`._
- **AC-9** — O SISTEMA DEVE enviar no máximo um lembrete por paciente por dia, pelo `ChannelDriverFactory` do módulo `Whatsapp`, só com consentimento de WhatsApp e canal conectado, e alertar o profissional quando o canal cair. _Prova: `FisioWhatsappJobTest`._
- **AC-10** — O SISTEMA DEVE calcular adesão 7d e a fila de atenção (dor subindo, dor alta, sem registro há ≥ 4 dias, sinal de alerta) com o motivo escrito. _Prova: `FisioPainelTest`, `AdesaoCalculatorTest`, `AtencaoDetectorTest`._
- **AC-11** — O SISTEMA DEVE oferecer só questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) com o texto da versão brasileira validada e a referência na tela. _Prova: pontuação conferida contra a publicação, Pest por instrumento._

## Fora de escopo

- Câmera, nota por câmera e prescrição sugerida por IA (F2/F4; SaMD classe II — ADR 0437 §5).
- Agenda, teleconsulta, pacotes, cobrança, NFS-e e Receita Saúde (F3 — `etapas/04-f3-operacao.md`).
- Questionários com licença paga (ODI, NDI, KOOS, QuickDASH, WOMAC).

## Referências

- SPEC: [SPEC.md](../../SPEC.md) (US-FISIO-001..012) · ADR: [0437](../../../../decisions/0437-modulo-fisio-vertical-fisioterapia.md) · Execução: [HANDOFF-F1.md](../../HANDOFF-F1.md) · Etapas: [01-biblioteca](../../etapas/01-biblioteca.md), [02-f1-constancia](../../etapas/02-f1-constancia.md)
