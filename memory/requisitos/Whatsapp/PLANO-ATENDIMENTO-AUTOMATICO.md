# Plano — Atendimento Automático (WhatsApp / Caixa Unificada)

> **Origem:** pedido [W] 2026-06-20 ("concluir o plano de atendimento automático — abrir suporte") + estado-da-arte [`sessions/2026-06-20-arte-atendimento-automatico-vs-melhor.md`](../../sessions/2026-06-20-arte-atendimento-automatico-vs-melhor.md) + estudo de ROI (este doc, §0).
> **Escopo:** atendimento automático no inbox (Caixa Unificada V4) — **triagem** (analisa/prioriza pro operador) e **resposta** (bot fala com o cliente) — + escalação humana ("abrir suporte"). Atendimento vive em `Modules/Whatsapp` (fusão KL-E2 2026-06-15); o cérebro de IA vive em `Modules/Jana` (o nome antigo "Copiloto" sobrevive só como pasta de docs `requisitos/Copiloto/`; o módulo de código foi renomeado pra Jana — ADR 0088/0092).
> **Invariante:** resposta automática ao cliente SEMPRE atrás de flag + guardrail + eval. Triagem é L1 (analisa, operador decide). Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) e PII redaction ([COMPLIANCE](COMPLIANCE.md) · [PII-REDACTION](PII-REDACTION.md)) inegociáveis. Fallback (princípio duro 8): IA fora → fila humana, nunca silêncio.
> **Status:** ver **`## Status vivo`** abaixo (fonte única do estado deste plano).

---

## Status vivo

<!-- catraca: não regride sem mudar status conscientemente · ADR 0294 -->
- **status:** ativo  <!-- proposto→ativo: [W] aprovou ordem ROI 2026-06-20 (merge #3064 + aceite ADR 0294). em-execução só quando houver task MCP parent_plan -->
- **owner:** W
- **criado:** 2026-06-20 · **reviewed_at:** 2026-06-20 · **próxima-revisão:** 2026-07-20
- **cycle:** off-CYCLE-08 (cycle ativo = receita/carteira legacy) · **execução:** `parent_plan=plano-atendimento-automatico` — US-WA-311 criada (backlog); E1/E3 (JANA Pro) + E4/E5 a criar
- **gate-de-saída (DoD):** E1+E3 com ≥5 clientes pagando JANA Pro (espelha gates da ADR 0140)
- **kill-condition:** mês 2 com < 2 conversões trial→pago → congela (review_triggers ADR 0140)
- **verdade-viva:** este doc

| Etapa | Task MCP (`parent_plan=plano-atendimento-automatico`) | Status | % |
|---|---|---|---|
| E1 JANA Pro MVP (brief operador) | US-COPI-201..205 | a criar | 0 |
| E2 Triagem no inbox | US-WA-311 | todo | 0 |
| E3 Cobrança Asaas + 5 beta | US-COPI-211..215 | a criar | 0 |
| E4 Bot responde cliente | US-WA-BOT-001 | adiada (§6) | 0 |
| E5 Entidade ticket | — | não agora (decisão §7) | 0 |

> Como evoluir este plano: **edita no lugar + bump `reviewed_at`** (plano é vivo, fonte única — [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)/0294). Pivô de decisão → ADR nova. Histórico = git.

---

## 0. Veredito ROI (2026-06-20) — a ordem corrigida

Cruzando esforço × risco × **monetização** × **sinal de cliente** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) com a meta do **CYCLE-08 (receita, MRR R$2.000)**: o maior ROI **não é o bot que responde o cliente** — é a **triagem/brief voltados pro operador (JANA Pro)**, que já é aceito ([ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md)), monetizável e quase pronto (o cérebro de triagem já existe como skill).

| Caminho | Esforço (IA-pair) | Risco | Cobra já? | Sinal | ROI |
|---|---|---|---|---|---|
| **E1. JANA Pro MVP** — brief diário + triagem pro operador (JANA-A) | ~16h | baixo (L1) | **sim** (pricing+Asaas prontos) | médio (dogfood + ADR aceito; **não** é sinal-0105 estrito) | 🟢 altíssimo |
| **E2. Triagem no inbox** — `ConversationOpened` → score + tag P1-P4 pro operador | ~10-12h | baixo (analisa, não responde) | sim (feature JANA Pro) | médio (deriva do E1) | 🟢 alto |
| **E3. Cobrança Asaas + 5 beta** (JANA-B) | ~16h | baixo | **é a receita** | forte (gera o sinal-0105: paga+reporta) | 🟢 alto |
| **E4. Bot responde o CLIENTE** (US-WA-BOT-001 + ReplyPolicy/eval) | ~18-28h | **alto** (erro ao cliente = marca + P0 financeiro) | indireto | **nenhum** | 🟡 médio — adiar |
| **E5. Entidade "ticket" dedicada** (Opção B) | alto | médio | não muda receita | nenhum | 🔴 não agora |

**3 razões de E1/E2 ganharem (ordem honesta):** (1) **risco baixo + reuso máximo** — L1 (operador no controle), reusa Vizra + Baileys + Asaas + skill `ticket-triage` já existentes; (2) **monetizável** — JANA Pro tem pricing + Asaas prontos ([ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md)); ⚠️ a MRR projetada (Beta ~R$745 mês 2, GA ~R$2.287 mês 3, margem ~92-94%) é receita SaaS **separada** da meta do CYCLE-08 (monetizar carteira legacy, MRR R$2.000) — **soma**, não "bate"; (3) **risco de mercado contido** — gated por conversão real (mês 2: 3/5 pagam ou congela). Nota: "dogfood/ADR aceito" são *baixa incerteza*, não os sinais-0105 estritos (paga+reporta / métrica / cliente pede).

**Por que adiar E4 (bot↔cliente):** maior teto (fecha o gap 28→ na régua de AI agent), mas resposta errada ao **cliente final** = dano de marca + risco P0 financeiro; exige guardrail conversacional + eval gate **antes** de canary; e **não há sinal** de que a Larissa queira bot falando com os clientes dela. Entra **depois** que E1/E3 provarem willingness-to-pay e o eval gate existir.

## 1. Estado honesto (o que existe × o que falta)

A **borda** de atendimento é de classe mundial; o **motor de IA conversacional ainda não responde**.

**✅ Em prod (suíte humano-assistida — COMPARATIVO ~91% do top):** SLA policies + escalation, macros com ações, CSAT, métricas de conversa (volume/custo/tempo-resposta), auto-link Contact CRM, mídia outbound, anti-ban, customer-memory, multi-número, tags (`ensureDefaultTags`), eval RAGAS interno, ERP nativo.

**✅ Cérebro de triagem (análise):** skill [`ticket-triage`](../../../.claude/skills/ticket-triage/SKILL.md) v0.1.0 — score 0-100, P1-P4, churn, ação sugerida. Mas é **L1, não escreve em DB**, é skill Claude-Code (não serviço runtime), e o hook "ao abrir conversa" é **futuro** (não plugado). Versão runtime planejada como `TicketsPriorizadosTool` (US-COPI-201) e `ConversationOpened → triage` (US-COPI-221).

**✅ Scaffolding do bot (triagem de gate, sem cérebro):** [`DispatchToJanaBot.php`](../../../Modules/Whatsapp/Listeners/DispatchToJanaBot.php) resolve phone, checa `bot_enabled`, override por contato ([ADR 0142](../../decisions/0142-notas-internas-sinal-treino-jana.md)), marca `bot_handling=true`, loga com PII redigida.

**❌ A resposta do bot NÃO existe:** bloco **placeholder** [`// SPRINT 3` (linhas 108-126)](../../../Modules/Whatsapp/Listeners/DispatchToJanaBot.php). Flag `whatsapp.bot.enabled` = default `false`.

**❌ Outros gaps:** guardrail conversacional próprio · eval contínua do bot · ingestão de KB · ações agênticas via ERP · métrica de deflection (`deflection_pct` é **só spec na ARCHITECTURE, não migrado** — precisa ser criado) · entidade "ticket" com lifecycle (hoje a conversa É a unidade — `status: open|awaiting_human|resolved|archived`).

> ⚠️ **O ADS PolicyEngine não é guardrail conversacional** — governa *ações de código* (`db_schema_change`/`pii_direct_exposure`/`billing_financial_flow`). O bot precisa de `ReplyPolicy` próprio (decisão aberta §7).

## 2. As duas réguas (não confundir)

| Régua | Mede | Nota |
|---|---|---|
| CAPTERRA / COMPARATIVO-MERCADO | suíte de atendimento humano-assistido | **~91%** |
| Scorecard "AI agent 2026" | atendimento *automático* de verdade | **~28/100** |

Scorecard AI-agent: resolução autônoma **8** · ações agênticas **5** · ingestão KB **30** · guardrails **35** · eval **45** · handoff **75** · analytics **40** · OTel **45** · omni **40**. *(`module:grade-v4 whatsapp` não rodou — classmap stale; `composer dump-autoload` antes.)*

## 3. Fluxo-alvo (estado final, não a ordem de build)

1. Inbound → `ProcessIncomingWebhookJob` → upsert msg/conversa.
2. **Triagem (E2)** → score + prioridade P1-P4 + ação sugerida, exibidos **pro operador** (analisa, não responde).
3. Bot (E4, futuro, atrás de flag) → `ReplyPolicy` decide: responder auto / rascunho assistido / escalar humano / no-op.
4. **"Abrir suporte"** = escalação → `status='awaiting_human'` + fila + SLA + protocolo (**Opção A**, conversa-como-chamado).
5. Atendente assume (`assigned_user_id`) → resolve → métricas (deflection / SLA / CSAT).

## 4. Roadmap ROI-first

| Etapa | Entrega | Onde | Esforço | Depende |
|---|---|---|---|---|
| **E1** | JANA Pro MVP — brief diário operador (JANA-A: US-COPI-201..205) | Jana | ~16h | — |
| **E2** | **Triagem no inbox** (US-WA-TRIAGE-001 — §5) | Whatsapp + Jana | ~10-12h | reusa cérebro do E1 |
| **E3** | Cobrança Asaas + 5 beta pagantes (JANA-B: US-COPI-211..215) | Jana/RB | ~16h | E1 |
| **E4** | Bot responde cliente (US-WA-BOT-001 — §6) + ReplyPolicy + eval gate | Whatsapp + Jana (ReplyPolicy próprio, **não** ADS) | ~18-28h | E3 + eval gate |
| **E5** | "Abrir suporte" rico / entidade-ticket (Opção B) | Whatsapp | alto | sinal de cliente |

E1/E2/E3 entregam receita SaaS (JANA Pro) com baixo risco — **complementam** a meta do CYCLE-08 (carteira legacy), não a substituem. E4 é o teto técnico — só quando houver sinal + segurança.

---

## 5. US de MAIOR ROI (detalhada) — US-WA-TRIAGE-001: triagem no inbox

**Título:** Ao abrir/chegar conversa, triar automaticamente e mostrar prioridade pro operador.
**Por quê:** é a *"triagem abrindo"* que você perguntou, na versão **barata + segura**: reaproveita o cérebro `ticket-triage`, é L1 (operador decide), zero risco de errar com o cliente, e é feature vendável do JANA Pro. ROI máximo por hora investida.

**Escopo (in):**
- `Modules/Jana/Services/Triage/TriageService.php` — porta runtime do que a skill `ticket-triage` descreve: 3 fontes (financeiro UPOS + histórico WhatsApp + giro) + 5 regras determinísticas + 1 chamada LLM barata (Brain A) pra sentimento/categoria → `{score 0-100, prioridade P1-P4, risco_churn, sugestao_acao, sla_sugerido_horas}`. **PII redigida antes do LLM.** (Esta é a base do `TicketsPriorizadosTool` do US-COPI-201 — construir aqui, reusar lá.)
- `Modules/Whatsapp/Listeners/TriageOnInbound.php` — escuta `WhatsappMessageReceived`; na **1ª mensagem inbound** de uma conversa (ou quando vira não-lida), chama `TriageService`, persiste resultado e publica no Centrifugo pro inbox atualizar.
- **Persistência (recomendo reuso, zero-schema):** grava a prioridade como **tag** (`whatsapp_tags` P1-P4 via `ensureDefaultTags`) + colunas leves nullable `triage_score`/`triage_suggestion` na conversa (1 migration idempotente). Alternativa só-tag se quiser zero migration.
- **UI:** badge de prioridade (P1 vermelho → P4 cinza) + tooltip "ação sugerida" na lista da Caixa Unificada. Ordenar fila por prioridade.

**Escopo (out):** responder o cliente (E4); criar entidade-ticket (E5); agente Enterprise event-driven completo (US-COPI-221 — esta US é o carve-out barato/precursor dele).

**Critérios de aceite:**
1. Conversa nova recebe `prioridade` + `score` + `sugestao_acao` em < 5s do inbound.
2. **L1 — nada é executado:** triagem só anota; não atribui, não responde, não resolve.
3. PII redigida antes da chamada LLM e em qualquer log (regressão do vetor `inbound_preview`).
4. Tier 0: `TriageService` e listener recebem/filtram `business_id`; teste cross-tenant não vaza.
5. Re-triagem idempotente (nova msg não duplica tag; faz update).
6. Falha do LLM → conversa entra na fila **sem** prioridade (degradado), nunca trava o inbound.
7. Operador vê o badge na lista e pode ordenar por prioridade.

**Testes (Pest, CT 100):** R-WA-TRIAGE-001..007 (1 por critério) + cross-tenant.

**Eval (leve):** golden-set `triage-gold-set.json` (~15 conversas reais anonimizadas Tier 0) com prioridade esperada; gate **advisory** (não bloqueia, porque é L1 advisório — operador corrige). Vira gate firme só se virar input de ação automática.

**Flag/rollout:** `copiloto.triage.inbox_enabled` (default off) → canary biz=1/biz=4 (ROTA LIVRE) → observar utilidade 7d (operador marca 👍/👎) → ligar geral.

**Estimate:** ~10-12h IA-pair (cérebro ~6-8h + listener+UI ~4h), +2x margem.

---

## 6. US ADIADA (E4) — US-WA-BOT-001: bot responde o cliente

> **Decisão ROI (§0):** adiada até E3 provar willingness-to-pay + eval gate existir. Mantida aqui pronta.

Substituir o placeholder [`DispatchToJanaBot:108-126`](../../../Modules/Whatsapp/Listeners/DispatchToJanaBot.php) por `BotReplyService` (Brain A + ContextoNegocio, PII redigida, `SendWhatsappMessageJob(sender_kind='bot')`), atrás de `whatsapp.bot.reply_enabled` (novo, separado do `whatsapp.bot.enabled` master) + canary `reply_canary_business_ids=[1]`. Governado por **`ReplyPolicy` próprio** (4 outcomes: responder / rascunho assistido / escalar humano / no-op) — **não** reusar o ADS PolicyEngine. Limiar de confiança (abaixo → não responde). Eval gate (golden-set ~20 perguntas reais Tier 0) **verde antes de expandir além do canary**. AC completos: ver histórico desta US (7 critérios) — flag respeitado, canary, PII, escalação `awaiting_human`, override por contato vence, Tier 0, sem PII em log. Estimate F1 ~6-10h + F2 (ReplyPolicy+eval) ~12-18h.

## 7. Decisões abertas (precisam de [W])

1. **"Abrir suporte" = A ou B?** **A (recomendada):** `status='awaiting_human'` + fila + SLA + protocolo na própria conversa (zero entidade nova). **B:** `support_tickets` dedicada (só se quiser helpdesk formal multicanal — ref *perfect-support-ticketing*). E2/E4 funcionam com A; B é E5 só com sinal.
2. **`ReplyPolicy` próprio vs reusar ADS** (E4): estado-da-arte recomenda **separado** → vira ADR nova.
3. **ADR 0267 (`whatsapp_queues`)** ainda `proposto` — aceitar pra habilitar roteamento automático (`dist`).

## 8. Como sobrevive no tempo

Framework: **[ADR 0256 — Knowledge Survival](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)** (6 princípios). Aplicado:
1. **Catraca de produto** — JANA Pro já nasce com os `review_triggers` da [ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md) + gates mês 2/6 + NPS: se o mercado não paga, o produto é cortado (sobrevive porque é testado por receita real).
2. **Eval como gate** — triagem (advisory) e bot (firme) com golden-set; sem isso a qualidade degrada silenciosa.
3. **Sentinela de drift** no `jana:health-check` — deflection/prioridade/escalação caindo = flag no Daily Brief.
4. **Learning loop** — toda correção do operador (👎 na triagem, escalação humano→bot) vira sinal de revisão ([ADR 0142](../../decisions/0142-notas-internas-sinal-treino-jana.md)).
5. **`ReplyPolicy`/limiar append-only** — só aperta, nunca afrouxa sem decisão consciente.

## 9. Cruzamento com JANA Pro (anti-duplicação)

Este plano é a **visão WhatsApp/inbox**; a triagem/brief **operador-facing** é o produto **JANA Pro** ([ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md) + [JANA-PRO-PRODUCT-PLAN](../Jana/JANA-PRO-PRODUCT-PLAN.md)). Mapa pra **não duplicar**:

| Este plano | = / alimenta | JANA Pro |
|---|---|---|
| E1 (brief operador) | **é** | Sprint JANA-A (US-COPI-201..205) |
| E2 `TriageService` | **base de** | `TicketsPriorizadosTool` (US-COPI-201) |
| E2 `ConversationOpened → tag` | **carve-out barato de** | US-COPI-221 (Enterprise event-driven) |
| E3 cobrança | **é** | Sprint JANA-B (US-COPI-211..215) |
| E4 bot↔cliente | **novo, WhatsApp-side** | — (não existe no JANA Pro) |

Regra: o cérebro de IA mora em `Modules/Jana`; o inbox (listener, badge, fila) mora em `Modules/Whatsapp`. (Os docs vivem em `requisitos/Jana/`; `requisitos/Copiloto/` virou só lápide-redirect em 2026-07-01.)

## 10. Referências

- ROI/estado-da-arte: [2026-06-20-arte-atendimento-automatico-vs-melhor.md](../../sessions/2026-06-20-arte-atendimento-automatico-vs-melhor.md)
- JANA Pro: [ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md) · [JANA-PRO-PRODUCT-PLAN](../Jana/JANA-PRO-PRODUCT-PLAN.md)
- Skill: [`ticket-triage`](../../../.claude/skills/ticket-triage/SKILL.md)
- Arquitetura/SPEC: [ARCHITECTURE.md §3.2](ARCHITECTURE.md) · [SPEC.md](SPEC.md) (US-WA-020/040/063/077)
- Mercado: [COMPARATIVO-MERCADO-2026-05-12-v2.md](COMPARATIVO-MERCADO-2026-05-12-v2.md) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)
- ADRs: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) · [0142](../../decisions/0142-notas-internas-sinal-treino-jana.md) · [0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) · [0267](../../decisions/0267-whatsapp-queues-tabela-filas-atendimento.md)
- Charter: [Index.charter.md](../../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md)
