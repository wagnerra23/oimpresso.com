# WhatsApp Module — Audit Log

> Histórico append-only de auditorias do módulo via skill `module-completeness-audit` Tier B (criada 2026-05-10).
>
> Format §7 da SKILL.md `module-completeness-audit`: cada entrada é seção H2 `## YYYY-MM-DD HH:MM — Whatsapp — full module audit (via /comparativo)` com:
> - Checks rodados: N ✅ / N 🟡 / N ❌ in-scope
> - Decisão Wagner: ...
> - Tasks criadas: US-WA-XXX..XXX
> - Próxima ação: ...
>
> ⛔ APPEND-ONLY — nunca editar entradas antigas (Tier 0 governança). Para retificar, criar entrada nova com `## YYYY-MM-DD HH:MM — Whatsapp — retificação de YYYY-MM-DD`.

---

## 2026-05-10 16:00 — Whatsapp — full module audit (via /comparativo)

- **Checks rodados:** 14 ✅ / 2 🟡 / 8 ❌ in-scope
- **Decisão Wagner:** aprovou criar 12 tasks (4 P0 + 4 P1 + 4 P2 + 2 P3)
- **Tasks criadas:** US-WA-041..052
- **Próxima ação:** aguarda CYCLE-04 alocar P0+P1
- **Capterra score:** 46.5/59 (78%)

## 2026-05-12 19:00 — Whatsapp — CYCLE-07 closure (16 PRs em 1 dia, 78% → 91%)

> Backfill 2026-05-15 14:00 — entrada não-apendada na época por gap de governança D-3 (corrigido).

- **Checks rodados:** 4 gaps P0 fechados, 4 gaps P1 fechados, 1 P2 fechado, 2 diferenciais únicos criados
- **PRs deployed (16):**
  - **P0:** #710 (SLA policies + escalation), #711 (métricas conversation dashboard /atendimento/metricas), #714 (CSAT pós-resolução), #644 (ChannelUserAccess UI), #665 (whatsapp:register-permissions cmd), #069 (canal=fila isolation)
  - **P1:** #707 (mídia outbound preview-then-send), #708/#682 (auto-link Contact CRM by phone), #709 (macros + quick replies CRUD), #716 (mídia inbound processada)
  - **P2:** #715 (HSM botões interativos + list messages), #720 (UI dialog HSM), #719 (A/B testing variants macros)
  - **Diferenciais únicos:** #696/#698 (LID resolution custom + backfill — workaround pré-Baileys 7.x), #699 (anti-ban middleware jitter+typing+warmup 12 vitest)
- **Tabelas criadas (5 sem AUDIT entry na época):** `csat_responses`, `whatsapp_conversation_metricas`, `sla_policies`, `macros`, `whatsapp_lid_pn_map`
- **Capterra score:** **46.5/59 → 53.4/59 (78% → 91%)** em 1 dia (COMPARATIVO-MERCADO-2026-05-12-v2.md)
- **Decisão Wagner:** "merge em sequência" — 16 PRs admin merge
- **Tasks criadas implícitas:** US-WA-093 (LID Resolver — não-spec'ada formalmente, virou D-1 órfã)
- **Próxima ação:** CYCLE-08 multi-phone UI completa (PR3+PR4 US-WA-040)

## 2026-05-13 23:30 — Whatsapp — Saga daemon WhatsApp recovery (11 PRs noite)

> Backfill 2026-05-15 14:00 — handoff [2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md](../../handoffs/2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md).

- **Trigger:** daemon Baileys CT 100 falhou silenciosamente em produção biz=1 — webhooks 404/429 burst, conversation history sync incompleto, channels presos em status `disconnected`
- **PRs deployed (11):**
  - **Daemon:** #685 (auto-reconnect ao boot), #686 (self-healing health-probe + reconnect-and-import), #687/#688/#692 (video/webm + fromMe + mime normalize), #695 (Baileys 6.7.9 → 6.7.18), #700 (UI Conectar com label), #816 (circadian anti-ban), #817 (healthcheck 503 zombie >30min)
  - **History sync:** #823 (canônico recupera ~90d), #827 (anti-burst sequential 500ms + 404 retryable), #828 (async dispatchAfterResponse), #831 (fila persistente database), #832 (gating biz_id Importar Histórico)
  - **Reconciler:** #822 (auto-purge antes connect), #824 (reconciler cron 5min + reset cmd), #815 (sync Laravel→daemon ao deactivate)
  - **Hardening:** #821 (5 fixes pós-merge + 14 regression tests), #814 (display_identifier unique cross-business)
  - **Observability:** #834 (Onda 1 Grafana + replay protection HMAC), #835 (Onda 2 OTel tracing + backpressure + Prometheus)
- **Decisão Wagner:** sequencial — Wagner monitor 30 min/noite + admin merge granular
- **Lição central:** daemon CT 100 precisa observability + self-healing + reconciler — invisible failures sangraram cliente real (R-WA cliente cancelou contrato 2026-05-11 mensagem perdida → US-WA-066 polling fallback 5s instalado defense-in-depth)
- **Próxima ação:** OTel métricas observam 7d antes de decidir migrar Baileys 7.x

## 2026-05-14 22:00 — Whatsapp — Estado-da-arte auto-cadastro contact (benchmark 38/100)

> Backfill 2026-05-15 14:00 — session [2026-05-14-arte-auto-cadastro-contact-whatsapp.md](../../sessions/2026-05-14-arte-auto-cadastro-contact-whatsapp.md).

- **Trigger:** Wagner pediu "aprenda com algum especialista, pesquise, avalie os melhores e compare com o nosso" durante incident cross-contact 2026-05-14
- **Método:** agent `estado-da-arte` spawnado — benchmark 8 concorrentes × 12 dimensões (Intercom, Front, Take Blip, HubSpot, Twilio Conversations, Zendesk SC, Octadesk, Crisp)
- **Vencedores identificados:**
  - **Twilio Conversations** — Identity Resolution proativa + exact phone match
  - **Intercom** — merge automático opt-in + default cria lead novo (não atribui pra contato existente sem confirmação)
  - **Zendesk SC** — incident público BR 2023 idêntico (cross-contact por phone match aproximado) → política oficial NÃO matcham por phone
- **Nota oimpresso: 38/100** — top 10 gaps priorizados por impacto × esforço (ADR 0106 recalibrado)
- **Decisão Wagner:** virou roadmap canon. NÃO virou US no SPEC ainda (D-8 aberto)
- **Tasks NÃO criadas:** 10 gaps catalogados mas nenhum entrou no SPEC formal — gap governança a corrigir
- **Próxima ação:** Wagner aloca P0+P1 dos top 3 (target ~60-70/100 em 3 PRs)

## 2026-05-15 07:00 — Whatsapp — Maratona anti-cross-contact + Baileys 7.x + deploy Hostinger (8 PRs)

> Backfill 2026-05-15 14:00 — handoff [2026-05-15-0700-whatsapp-maratona-fechamento-8prs-baileys7x-deploy-hostinger.md](../../handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-8prs-baileys7x-deploy-hostinger.md).

- **Trigger:** Wagner re-pareou canal Baileys 6.7.9 prod biz=1 → 81 msgs caíram no contato errado (cross-contact LID) por 3 falhas combinadas
- **PRs deployed (8 sequenciais, todos admin merge curl):**
  - **#854** anti-cross-contact P0 (Linker suffix-8 + Resolver bloqueia source=manual sem webhook prévio + Persister consulta resolver no history-sync)
  - **#855** schema 3-identifiers (lid + phone_e164 + bsuid em conversations + whatsapp_conversations)
  - **#856** observer backfill conversations órfãs quando LID resolve
  - **#857** backup daily auth_state Baileys CT 100 + runbook restore
  - **#858** MetaCloudDriver parseInboundWebhook stub canary (preparação biz=99)
  - **#863** migração Baileys 6.7.18 → 7.0.0-rc11 ESM-only + getPNForLID nativo + 5 Pest tests + RUNBOOK 272 linhas canary biz=99
  - **#864** 10 testes Pest regression E2E + convention anti-regressão incident 2026-05-14
  - **#866** hotfix migration whatsapp_conversations schema diferente prod (customer_phone, sem `after()`)
- **Recovery operacional:** DELETE 83 msgs + conv #37 + UPDATE lid_pn_map id=1 NULL (backup JSON 132KB preservado em storage/app/backups/). Estado biz=1: 0 msgs/0 convs pós-recovery.
- **Decisão Wagner crítica:** **CORTOU 3× Baileys 6.7.9** ("passe pra Baileys 7, 3ª vez informado, se reclamar de novo vai ser muito desagradável"). Feedback canon instalado em `memory/reference/feedback-baileys-7x-decisao-irreversivel.md` + proibição Tier 0 em `memory/proibicoes.md` §Comportamento Claude.
- **ADRs criados:** [0146](../../decisions/0146-contact-lid-canonico-pk-refactor.md) (originalmente 0145, renumerada por colisão com ADR 0145 IA Administradora — sessão paralela)
- **Diferenciais novos:**
  - Schema 3-identifiers — defense-in-depth contra qualquer cross-contact futuro (concorrentes BR não fazem)
  - Anti-cross-contact regression suite (10 Pest convention + E2E) — catalogado pra hook futuro
- **Capterra score (estimado):** ~91% (recuperação pós-incident; Baileys 7.x daemon pendente Wagner deploy CT 100)
- **Próxima ação:** Wagner manual `tailscale ssh root@ct100-mcp 'docker compose build/up'` → canary 7d biz=99 → biz=1 prod

## 2026-05-15 14:00 — Whatsapp — Governance backfill + RUNBOOK Caixa Unificada v4 (esta entrada)

- **Trigger:** Wagner pediu "leia o backlog, quero entender o módulo inteiro e saber os desvios cometidos e quantos % estamos perto da conclusão"
- **Checks rodados:** 13 desvios catalogados (D-1..D-13), 6 dimensões % calculadas, drift bidirecional SPEC detectado
- **Estado consolidado:**
  - Operacional PME (P0+P1): ~95%
  - Capterra top-mercado: ~91% (53.4/59)
  - Diferencial competitivo ERP-nativo: ~100%
  - Cobertura SPEC formal: ~43% (20 done / 47 spec'adas)
  - Documentação canon: ~60%
  - Deploy/ops: ~90% (Baileys 7.x daemon pendente Wagner)
- **Desvios fechados nesta sessão:**
  - D-3 (este AUDIT-LOG backfill 5 entries)
  - D-1 (SPEC sync — apender 13 US-WA-* órfãs próxima task)
  - D-11 (CAPTERRA-INVENTARIO refresh 78%→91%)
  - D-12 (F1 RUNBOOK Caixa Unificada v4 — escopo inicial errado, Wagner cortou, redesign-only com 2 filas via config static + Pest 7/7 verde)
- **F2 deliverável Caixa Unificada v4:** `config/whatsapp.queues` 2 filas (Comercial+Financeiro) + `InboxController::deriveQueueFromTags` + 2 props derivadas + 7 Pest verde
- **Decisão Wagner:** "atacar D-1+D-3+D-11 governance antes de feature nova"
- **Próxima ação:** após governance fechado, decisão Wagner entre F3 redesign vs US-WA-040 P3+P4 multi-phone UI vs D-8 auto-cadastro contact roadmap
