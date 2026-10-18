# Handoff — Módulo Fisio · F1 "Constância" · para Claude Code

> Cole este arquivo como primeira mensagem de uma sessão Claude Code no repo `oimpresso.com`.
> **ADR 0437 aceita por [W] em 2026-10-18**, incluindo a seção "Fisio" na sidebar (§3.3).
> **Revisão clínica:** Dra. Dejeane (responsável técnica). Todo PR que toca biblioteca, protocolo, contraindicação, sinal de alerta ou texto ao paciente leva a revisão dela anotada.

## O que a F1 entrega, em uma frase

O fisioterapeuta monta o programa de exercícios em um minuto e manda pelo WhatsApp; o paciente abre um link, faz os exercícios do dia, ganha XP e sobe de nível; o fisioterapeuta vê no painel quem está fazendo, quem está com dor e quem vai abandonar.

## Fora da F1 (não antecipe, nem com coluna "para depois")

Câmera, nota por câmera, prescrição sugerida por IA, ditado por voz, pacotes/cobrança/NFS-e, agenda, questionários bloqueados por licença (ODI, NDI, KOOS, QuickDASH, WOMAC).

## Leia antes de escrever qualquer linha

1. `memory/decisions/0437-modulo-fisio-vertical-fisioterapia.md` — inteira. As §4 a §8 são regra, não sugestão.
2. `DESIGN.md` §3, §6 a §9 e `memory/requisitos/_DesignSystem/MANUAL-IDENTIDADE.md`.
3. ADR 0039 (shell), 0253 (primitivos), 0264 (casos-gate), 0306 + `_TEMPLATE_FEATURE/BRIEFING.md` (trio SDD), 0358 (doutrina de teste), 0368 (funil).
4. **Precedente para copiar:** link público da OficinaAuto (`Public/AprovacaoOsController`, `AprovacaoPublica.tsx`, `EnviarLinkAprovacaoWhatsappJob`).
5. Design: canvas "Prescreve Pro" (telas Painel, Evolução, Biblioteca; e no paciente Hoje, Como foi hoje, Jornada, Nota e nível). O canvas usa cor literal; no código, **só token**.
6. Suíte de testes TDD que acompanha este handoff (`fisio-tests/`, ver README dela). Os testes chegam escritos antes do código: implemente até ficarem verdes, sem afrouxar asserção.

## Material que acompanha este handoff

- `memory/requisitos/Fisio/etapas/01-biblioteca.md` e `02-f1-constancia.md`: detalham a F1 (biblioteca, WhatsApp, check-in, jogo com números, painel de risco), com tarefas próprias (B-xx e as da F1) que refinam T-04 a T-10 abaixo.
- `03` a `06`: etapas futuras (câmera, operação, IA/questionários, lançamento). Leia só para não fechar porta; não implemente.
- `Modules/Fisio/Tests/`: 17 arquivos Pest (138 `it()`, 166 casos) e os 5 `casos.md`; as 4 factories voltam com os models na US-FISIO-003 (PHPStan não aceita factory de model inexistente). O `README.md` da suíte lista as suposições de nome; confirme cada uma antes de escrever o código e ajuste o teste **só** se a suposição for de nome, nunca de regra.

### Ajustes de conciliação (resolva no T-01)

1. **XP:** os testes leem `config('fisio.xp.*')`. Use como padrão os números de `02-f1-constancia.md` (100 XP por dia prescrito: 70 pelos exercícios, 30 pelo registro; teto semanal); os testes sobrescrevem os valores nas fixtures.
2. **Contraindicação:** os testes esperam 409 na primeira tentativa e gravação com quem confirmou. Acrescente a **justificativa escrita** (coluna `contraindication_justification`) e grave-a no prontuário, como pede a ADR §13.2. É aviso, não bloqueio.
3. **Busca por texto na biblioteca:** entra na F1 só se [W] aceitar a decisão nº 3 da ADR §14; caso contrário, fica o filtro por região e equipamento.
   **Decidido — [W] 2026-10-18: a busca por texto ENTRA na F1** (aceita a decisão nº 3 da ADR §14). Implementada na US-FISIO-004 como LIKE em nome e sinônimos, sem motor externo; FULLTEXT, facetas e registro de busca sem resultado seguem na B-07 de `etapas/01-biblioteca.md`.
4. **WhatsApp:** reusar o módulo `Whatsapp` existente com o driver **`whatsmeow`** (decisão de [W], ADR §13.1). O Fisio nunca chama o whatsmeow direto: passa pelo `ChannelDriverFactory`, para que trocar para `meta_cloud` seja só configuração. Implemente as mitigações da §13.1: um número por clínica, teto por hora com intervalo aleatório, só paciente com consentimento, alerta ao profissional quando o canal cair, e o link `wa.me`/portal copiável como alternativa.

## Regras duras (Tier 0)

- `business_id` em toda tabela e todo escopo.
- Paciente **é** um `contact`. O Fisio guarda só o perfil clínico 1:1.
- **Prontuário (Res. COFFITO 414/2012):** evolução por atendimento carimbada com nome, CREFITO, data e hora; correção só por adendo; nada de UPDATE em evolução assinada.
- **Guarda de 20 anos (Lei 13.787/2018):** "excluir paciente" = bloquear; `SoftDeletes` não basta se algum job limpar a tabela; nenhum `forceDelete` em dado clínico.
- **Telemonitoramento (Res. COFFITO 619/2025):** cada check-in do portal gera um registro de evolução assíncrona no prontuário, com modalidade e canal.
- **Consentimentos separados e versionados** (`fisio_consents`): teleatendimento, WhatsApp, imagem, marketing, pesquisa/IA. Teleatendimento é condição para o portal funcionar; os outros são independentes. Revogação pelo portal.
- **Anvisa (não virar SaMD):** o software **não** decide conduta para o paciente. Com dor alta ou sinal de alerta, o paciente vê só a instrução estática escrita pelo profissional; o alerta vai para o profissional. Gráfico de dor só na visão do profissional.
- **Jogo:** XP por cumprir o prescrito e por registrar o dia (inclusive dor alta); nada por exceder o prescrito; sequência conta dias prescritos; sem ranking entre pacientes.
- Zero cor crua, zero `text-[Npx]`, primitivos de layout, componentes shared antes de criar novo. PT-BR em toda a UI.
- Menu "Fisio" só para business com o módulo ativo.

## Modelo de dados (ajuste ao padrão do repo)

| tabela | campos principais |
|---|---|
| `fisio_patients` | business_id, contact_id (único por business), main_complaint, goals (json), sessions_per_week (1–7), status, condition_flags (json: protese_quadril_posterior, protese_quadril_anterior, pos_op_manguito, pos_lca, gestante, osteoporose, risco_queda, hipertensao_nao_controlada…), blocked_at |
| `fisio_records` | business_id, patient_id, kind (`avaliacao`, `evolucao`, `telemonitoramento`, `adendo`), body (json), modality, channel, signed_by, signed_crefito, signed_at, amends_id — **append-only** |
| `fisio_consents` | business_id, patient_id, purpose, version, granted_at, revoked_at, ip, channel |
| `fisio_exercises` | business_id (nulo = global), name, region, type, pose, steps (json), default_sets, default_reps, default_hold_s, times_per_day, safety_notes (json), contraindication_flags (json), needs_support (bool), is_custom |
| `fisio_protocols` | business_id, name, region, items (json), notes — modelo da clínica, lógica visível |
| `fisio_programs` | business_id, patient_id, phase, status, origin (`manual`/`protocolo`), stop_instruction (texto do profissional), pain_alert_threshold, pain_alert_mode (`absoluto`/`sobre_basal`, padrão `sobre_basal` em protocolos de dor crônica como fibromialgia), crisis_items (json: versão de crise escrita pelo profissional), created_by, starts_at |
| `fisio_program_items` | program_id, exercise_id, sets, reps, hold_s, times_per_day, notes, position |
| `fisio_checkins` | business_id, patient_id, program_id, date, done_item_ids (json), pain (0–10), effort, note, red_flags (json) — **único por paciente + data** |
| `fisio_game_ledger` | business_id, patient_id, date, reason, xp — razão é enum fechado; sem UPDATE |
| `fisio_questionnaires` | business_id, patient_id, instrument (`eva`, `rmdq`, `psfs`, `lefs`, `spadi`), answers (json), score, answered_at |
| `fisio_portal_tokens` | business_id, patient_id, token_hash, pin_hash, attempts, locked_until, expires_at, revoked_at |

Nível e sequência são **derivados** do ledger e dos check-ins, nunca digitados.

## Telas

| tela | padrão | conteúdo |
|---|---|---|
| `Fisio/Painel` | dashboard | KPIs (adesão média 7d, pacientes ativos, dor média), fila "precisa de atenção" (dor subiu ≥2 e persiste, dor ≥7, sem registro há ≥4 dias, sinal de alerta), tabela com adesão, dor, nível e sequência |
| `Fisio/Pacientes/Index` | PT-01 Lista | busca, filtros, J/K, N novo |
| `Fisio/Pacientes/Show` | Cockpit + Apps Vinculados | abas Evolução, Programa, Prontuário, Questionários; painel direito: contato, consentimentos, diário |
| `Fisio/Biblioteca/Index` | grade | busca, região, exercício próprio, protocolos-modelo |
| `Fisio/Public/Portal` | público, mobile-first | consentimento → PIN → Hoje (selo de nível, barra de XP, exercícios) → Como foi hoje (dor, esforço, sinais de alerta, comentário) → Jornada (nível, missões, trilha, medalhas) |

Cada tela nova leva `.charter.md` e `casos.md` (ADR 0264).

## Tarefas (ordem por dependência)

| id | tarefa | blocked_by | DoD |
|---|---|---|---|
| T-00 | ~~Confirmar ADR 0437 `aceito`~~ | — | **feito em 2026-10-18** |
| T-01 | `sdd:init` do módulo + trio `features/f1-constancia/` + US no SPEC | T-00 | `feature-lint --check` verde |
| T-02 | Esqueleto `Modules/Fisio`: ativação por business, permissões, menu condicionado | T-01 | business sem módulo não vê menu nem rota |
| T-03 | Migrations, models, scopes, append-only de `fisio_records` e `fisio_game_ledger` | T-02 | isolamento entre 2 businesses; UPDATE em registro assinado falha |
| T-04 | Seed da biblioteca (38 exercícios) com `times_per_day`, avisos de respiração/apoio e `contraindication_flags` | T-03 | revisão da Dra. Dejeane anotada no PR |
| T-05 | Pacientes, prontuário (avaliação + evolução + adendo), marcadores de condição | T-03 | campos mínimos da Res. 414 obrigatórios |
| T-06 | Editor de programa + protocolos-modelo + alerta de contraindicação ao prescrever | T-04, T-05 | marcador do paciente dispara aviso no exercício certo |
| T-07 | Portal: token + PIN + lockout, consentimentos, check-in com sinais de alerta, registro de telemonitoramento | T-06 | testes de PIN, expiração, revogação, consentimento, idempotência |
| T-08 | Jogo: ledger de XP, nível, sequência por dias prescritos, missões, medalhas, modo pausa com dor ≥ limiar | T-07 | testes das regras da ADR §6 |
| T-09 | Lembrete diário e envio do programa por WhatsApp: mensagens do Fisio + job idempotente chamando o módulo `Whatsapp` (driver `whatsmeow`) pelo `ChannelDriverFactory`, com teto por hora e intervalo aleatório | T-07 | um lembrete por paciente por dia; sem disparo para business sem módulo, sem canal conectado ou sem consentimento; canal caído gera alerta ao profissional |
| T-10 | Painel e evolução: adesão, dor, fila de atenção, nível | T-08 | números batem com os check-ins de teste |
| T-11 | Questionários liberados (EVA, RMDQ, PSFS, LEFS, SPADI) com texto BR e referência na tela | T-05 | pontuação conferida com a referência publicada |
| T-12 | Fecha o loop: âncora `Implementado em:` da US no SPEC | T-01…T-11 | `verificado@sha7` |

## Não faça

- Não crie cor, fonte ou componente "do Fisio". Faltou token: pare e proponha ADR.
- Não guarde vídeo, foto ou áudio do paciente na F1.
- Não mostre ao paciente conduta calculada pelo software ("pare", "troque o exercício") — só a instrução escrita pelo profissional.
- Não gere programa por IA, nem atrás de flag.
- Não implemente questionário bloqueado por licença.
- Não altere o menu de quem não tem o módulo.
