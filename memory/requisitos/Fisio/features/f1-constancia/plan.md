---
id: requisitos-fisio-features-f1-constancia-plan
feature: f1-constancia
module: Fisio
---

# Plan — F1 Constância do módulo Fisio

## Status vivo

- **status:** proposto
- **owner:** W
- **criado:** 2026-10-18 · **reviewed_at:** 2026-10-18 · **próxima-revisão:** 2026-11-17
- **cycle:** off-cycle · **execução:** `parent_plan=fisio-f1-constancia` — tasks MCP ainda não criadas
- **gate-de-saída (DoD):** AC-1..AC-11 provados pela suíte Pest da F1 rodando verde no CI MySQL, e US-FISIO-001..012 com âncora `verificado@sha7`
- **kill-condition:** se o piloto de 8 semanas (`etapas/06-lancamento.md`) não atingir ativação ≥ 70% e retenção de profissionais ≥ 65%, replanejar antes da F2
- **verdade-viva:** este documento

## Decisões técnicas

| # | Decisão | Por quê (1 linha) | Âncora |
|---|---|---|---|
| D1 | Paciente = `contact` + `fisio_patients` 1:1 | não duplicar cadastro de pessoa | ADR 0437 §3.1 |
| D2 | `fisio_records` e `fisio_game_ledger` append-only | Res. COFFITO 414/2012 e Lei 13.787 (20 anos) | ADR 0437 §4 |
| D3 | Portal por token HMAC + PIN com lockout | padrão provado em produção na OficinaAuto | `Public/AprovacaoOsController` |
| D4 | WhatsApp pelo `ChannelDriverFactory` (driver `whatsmeow`) | decisão de [W]; troca para `meta_cloud` vira configuração | ADR 0437 §13.1 |
| D5 | Contraindicação avisa e pede justificativa, nunca bloqueia | bloquear por diagnóstico é SaMD (P68/P70) | ADR 0437 §13.2 |
| D6 | Nível e sequência derivados, nunca coluna editável | done-ness derivada, não digitada | ADR 0302 |
| D7 | Testes escritos antes do código (17 arquivos, 166 casos) | TDD; suposição de nome pode mudar, regra não | `Modules/Fisio/Tests/README.md` |

## Plug-points (comparar e NÃO duplicar — inventário ANTES de criar símbolo)

| Onde | O que já existe | Como esta feature encaixa |
|---|---|---|
| `Modules/OficinaAuto` (`Public/AprovacaoOsController`, `EnviarLinkAprovacaoWhatsappJob`) | link público com HMAC + PIN e envio por WhatsApp | imita o padrão no portal do paciente |
| `Modules/Whatsapp` (`ChannelDriverFactory`, driver whatsmeow, comandos de reconciliação/health) | envio e saúde de canal | o Fisio só chama o envio e lê a saúde do canal |
| `Modules/Producao` (`DataController`, `InstallController`) | esqueleto de módulo novo, pacote do superadmin | molde do esqueleto do Fisio (com `ModuleUtil` pelo container) |
| `contacts` | cadastro de pessoa | perfil clínico 1:1 |
| `AppShellV2`, `PageHeader`, `DataTable`, `KpiCard`, `StatusBadge`, primitivos de layout | shell e componentes do DS v6 | telas do profissional sem componente novo |

## Design

- **(a) Dados tocados:** `fisio_patients`, `fisio_records`, `fisio_consents`, `fisio_exercises`, `fisio_protocols`, `fisio_programs`, `fisio_program_items`, `fisio_checkins`, `fisio_game_ledger`, `fisio_questionnaires`, `fisio_portal_tokens` (todas novas); `contacts` (leitura).
- **(b) Contratos:** rotas `/fisio/*` (profissional) e `/fisio/p/{token}/*` (portal); jobs `EnviarProgramaWhatsappJob`, `EnviarLembreteDiarioJob`; comando `fisio:lembretes`; notificação `SinalDeAlertaNotification`.
- **(c) Interação novo↔existente:** `Checkin → JogoService (ledger) → AtencaoDetector → Painel`; `fisio:lembretes → EnviarLembreteDiarioJob → ChannelDriverFactory (whatsmeow)`.

## Riscos Tier-0

- [x] **Multi-tenant (ADR 0093):** toda tabela com `business_id`; jobs recebem `$businessId`; provado por `FisioMultiTenantTest`.
- [x] **REGRA MESTRE valor/estoque:** N/A na F1 (sem cobrança nem estoque; cobrança é F3).
- [x] **PII/LGPD:** dado de saúde (art. 11); consentimentos versionados; link do portal sem dado clínico na URL/mensagem; nada de CPF em log.
- [x] **Tela (ADR 0264):** 5 telas novas, cada uma com `casos.md` (já escritos) e charter a criar.
- [x] **Runtime (ADR 0062):** Feature tests precisam de MySQL com tenant 98 → CT 100 / CI MySQL; Unit roda em qualquer lane.

## Alternativas descartadas

- Produto separado com marca própria — perde o núcleo (ADR 0437 §10).
- Login com senha para o paciente na F1 — atrito; link + PIN já provado.
- Ranking entre pacientes — dado de saúde não é competição.
- Prescrição por IA na F1 — SaMD classe II mesmo com aprovação humana (P57).
