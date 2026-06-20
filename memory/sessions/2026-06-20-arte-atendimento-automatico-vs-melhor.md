# Estado-da-arte — Atendimento automático (AI support) vs o melhor do mundo 2026

> **Agente:** `estado-da-arte` · **Data:** 2026-06-20 · **Módulo:** `Modules/Whatsapp` (Caixa Unificada V4) + `Modules/ADS` + `Modules/Jana`
> **Método:** Fase 1 pesquisa limpa (sem memória) → Fase 2 comparação com código real → Fase 3 gaps + sobrevivência.
> **Aviso de calibragem:** estimativas IA-pair seguem ADR 0106 (fator 10x + margem 2x). PII Tier 0 redacted.

---

## Achado-âncora (lê isto primeiro)

**O bot conversacional que responde o cliente NÃO existe em código.** Validei linha a linha:

- `DispatchToJanaBot.php` (linha 108) ainda é um bloco `// SPRINT 3:` comentado. Ele detecta phone + marca `bot_handling=true` + loga, mas **não chama `decide()` nem dispara resposta**.
- Não existe nenhum caminho `sender_kind='bot'` em `Modules/Whatsapp/Jobs/*` — só `human` (UI) e `system` (templates/CSAT/notificações). Grep confirmou.
- O `COMPARATIVO-MERCADO-2026-05-12-v2.md` afirma "auto-handoff bot Jana → humano JÁ IMPLEMENTADO" — **isso está stale/aspiracional**. O listener existe; a geração de resposta não.

**Correção de premissa importante:** o `Modules/ADS/PolicyEngine` (ALLOW_BRAIN_A / REQUIRE_BRAIN_B / REQUIRE_HUMAN_REVIEW / BLOCK_ALWAYS) **não governa respostas ao cliente**. É um firewall de **ações de código** do Jana/Claude Code — os event types são `env_production`, `db_schema_change`, `pii_direct_exposure`, `billing_financial_flow`. Não há classificador de intenção de mensagem de cliente nem confidence de resposta. Tratar o ADS como "guardrail conversacional" é erro de categoria. Quando o bot de cliente nascer, ele precisa de um **PolicyEngine conversacional próprio** (espelhando o padrão, não reusando as listas).

**Tradução:** o oimpresso tem uma suíte de **atendimento humano-assistido Chatwoot-tier muito forte** (inbox, SLA, macros A/B, CSAT, customer-memory, transcrição de áudio, anti-ban, LID, ERP nativo). O que **falta** é a camada que define "estado-da-arte 2026": o **AI agent que resolve sozinho** (deflection real). Hoje o `deflection_pct` existe como coluna mas mede ~0 porque nada deflete automaticamente além de templates transacionais.

---

## FASE 1 — Os melhores do mundo (pesquisa 2026)

| Player | O que é / como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Intercom Fin** | "Fin AI Engine" refina o input antes do LLM (reescreve a pergunta, injeta contexto), valida a saída antes de mandar; **Custom Answers** têm prioridade sobre AI Answers (respostas estruturadas + ações). Guardrails = defensive prompting anti prompt-injection + human handoff + usage limits. | Resolution rate reportado 65-76% (real-world honesto: ~42-50%); **garantia de performance de US$1M a 65%**. Escala 8.000+ clientes / 40M+ conversas. |
| **Decagon** | **Agent Operating Procedures (AOP):** time de negócio escreve regras em linguagem natural que "compilam em lógica executável de agente"; engenharia mantém integrações + guardrails. Ações end-to-end via Stripe/Shopify/Salesforce (refund, update order, verificar identidade, criar ticket) **sem escalar**. "Watchtower" = QA always-on em produção; dashboard de experimentos (deflection experiment vs control). | 50-80% resolução autônoma; casos: Duolingo 80% deflection, Rippling 38%→50%, NG.CASH 13%→70%. Valuation US$4,5B (jan/2026). Define o padrão "agentic = age, não só responde". |
| **Sierra** (Bret Taylor) | **Guardrails determinísticos** pra lógica crítica de negócio + filtros de tópico; **brand persona** define voz/limites. Tooling de qualidade: **Explorer** (análise de conversa), **Experiments** (A/B), **Monitors** (detecção de anomalia), **Voice Sims** (teste pré-deploy), **Workspaces** (version control de agente). | US$150M ARR em <2 anos; foco Fortune 500. Referência em **ciclo de vida do agente** (versionar, simular, monitorar) — não só na resposta. |
| **Zendesk Resolution Platform** | Treinado em ~20B interações de ticket; **Resolution Learning Loop** captura insight de cada conversa e fecha lacunas de KB em tempo real. Respostas generativas ancoradas em KB/help-center. Handoff escala com contexto completo intacto. **Analyst Copilot** + aba "Contact reasons" mostra onde o AI vai bem/mal por fonte de conhecimento. **Pricing por resolução verificada**, não por seat. | Padrão de mercado em **ingestão de KB + learning loop + analytics de qualidade por fonte**. ~20% automação no mês 1 → ~70% após refino de KB. |
| **Gorgias** (e-commerce) / **Agentforce** (Salesforce) | Gorgias: ações nativas Shopify (order tracking, returns); cobra **por resolução** ($0,90-1,00) — automação real 26-56% (marketing diz "até 60%"). Agentforce: ações via Flex Credits (por tarefa: update record, summarize case); cobrava $2/conversa resolvida ou não (pushback de mercado). | Mostram a economia honesta do setor: **outcome-based pricing** vira a régua; e automação real fica em 30-60%, não nos 80% do marketing. |

**Mecanismos que definem o estado-da-arte (síntese):** (1) **ações agênticas** governadas — o agente executa tarefas no sistema, não só responde; (2) **ingestão de conhecimento** automática (site/docs/KB) com learning loop; (3) **guardrails determinísticos + eval contínua** (LLM-as-judge online, simulação pré-deploy, A/B de comportamento); (4) **handoff com contexto completo**; (5) **analytics de qualidade por fonte de conhecimento** + **outcome pricing** (pago por resolução).

Fontes citadas no fim do doc.

---

## FASE 2 — Comparação item a item com o oimpresso (código real)

### O que o oimpresso REALMENTE tem (validado em código, não em doc)

Inventário muito mais rico do que o brief sugeria. Tudo abaixo é código shipado, não proposto:

- **SLA engine real** — `Services/Sla/SlaEnforcer.php`: 3 triggers (`first_inbound_no_reply`, `open_aging`, `awaiting_human_aging`), 3 actions (notify Centrifugo / reassign / set_status), idempotência por lock, dry-run, OTel span, Tier 0 por WHERE explícito. **Bate Chatwoot/Zendesk.**
- **Macros com A/B** — `Services/Macros/MacroVariantPicker.php` (weighted random CSPRNG) + `MacroVariantResponseTracker` + `MacroExecutor` (send + add_tag + set_status + assign_user). **A/B de macro é coisa que Sierra cobra caro.**
- **CSAT pós-resolução** — `Services/Csat/CsatDispatcher.php` + `CsatResponseParser` (regex 1-5/estrelas/emoji), idempotência 24h. **Paridade.**
- **Customer memory** — `Services/CustomerMemory/CustomerMemoryRebuilder.php` + enrich Firebird/Officeimpresso; campos de inferência IA (`sentimento_score`, `churn_risk_score`, `temas_recorrentes`) **declarados mas não populados** ("Onda 3"). Esqueleto de Customer 360.
- **Transcrição de áudio inbound** — `Services/Audio/WhisperTranscriber.php`. Diferencial; poucos BR-PME têm.
- **Anti-ban + LID resolution** — diferenciais únicos (Baileys/whatsmeow governado). Nenhum BSP oficial precisa, mas sustenta o modelo não-oficial barato.
- **Eval harness Jana (interno) state-of-the-art** — `Modules/Jana/Tests/Feature/Ai/HallucinationEvalTest.php` (100 golden questions, 6 categorias, strict mustContain/mustNotContain), `JanaRagasCiCommand` + `RagasEvalCITest` + `jana-gold-set.json` + `Modules/KB/Tests/Feature/KbRagasEvalTest.php`. **Isto é nível Sierra/Fin** — mas aponta pro copiloto interno e KB, **não** pro bot de cliente.
- **ADS** — PolicyEngine + DecisionRouter + PatternLearning (Wilson score) + GovernanceRulesService + AutoTaskGenerator + Planner. Maquinaria séria de **governança de ações de código**.

> ⚠️ Nota de drift documental: `ARCHITECTURE.md` ainda descreve Baileys/§16 daemon Node como plano; o código real já usa `WhatsmeowDriver` (Go whatsmeow) + `Channel` entity. A doc está ~1 geração atrás. Não corrigi (fora do escopo deste agente), mas registro pra curadoria.

### Tabela comparativa por dimensão

| Dimensão (emergiu da Fase 1) | Estado-da-arte 2026 | oimpresso hoje (código real) | Distância |
|---|---|---|---|
| **Geração de resposta automática ao cliente** | Fin/Decagon/Zendesk respondem 40-80% sozinhos | **Inexistente.** `DispatchToJanaBot` é placeholder `// SPRINT 3`; zero `sender_kind='bot'` | **longa** |
| **Ações agênticas** (executar tarefa, não só responder) | Decagon faz refund/update order; Agentforce update record | Macros executam add_tag/assign/set_status (atendente dispara); ERP nativo permitiria 2ª via boleto/status OS — **mas nada via bot** | **longa** (potencial enorme via ERP) |
| **Ingestão de conhecimento (KB/site/docs)** | Zendesk learning loop; Fin lê help-center | KB module + RAGAS eval existem (interno Jana); **bot de cliente não tem fonte de KB ligada** | **média** (peças existem, não conectadas) |
| **Guardrails de resposta** | Sierra determinístico + brand persona; Fin defensive prompting | PolicyEngine é firewall de **código**, não conversacional. PII redaction (`PiiRedactor`) forte. Sem guardrail de tom/escopo de resposta | **longa** |
| **Eval/regressão de respostas do bot** | LLM-as-judge online + simulação pré-deploy + A/B comportamental | Harness RAGAS + 100 golden + hallucination strict **existe pro Jana interno**; **não aplicado a respostas de cliente** | **curta** (scaffolding pronto, falta apontar) |
| **Handoff humano com contexto** | Contexto completo, regras de escala | `status=awaiting_human` + `assigned_user_id` + SLA `awaiting_human_aging` + Centrifugo notify. Contexto = thread visível. **Bom.** | **curta** |
| **SLA / escalation** | Zendesk/Chatwoot policies | `SlaEnforcer` completo (3 triggers/3 actions) | **paridade / supera PME** |
| **Macros / quick replies + A/B** | Sierra Experiments | `MacroExecutor` + `MacroVariantPicker` weighted + tracker | **paridade / supera** |
| **CSAT / qualidade percebida** | padrão | `CsatDispatcher` + parser | **paridade** |
| **Customer 360 / memória** | unificado, com sinais | `CustomerMemory` com esqueleto; inferências IA não populadas | **média** |
| **Omnichannel** | Zendesk/Chatwoot: email+IG+FB+SMS+voz | WhatsApp-first (Meta Cloud + Z-API + whatsmeow). Outros canais fora de escopo | **média** (deliberada) |
| **Analytics de qualidade por fonte** | Zendesk "contact reasons" por KB source | `whatsapp_conversation_metricas` (deflection_pct, p50/p95, custo) — bom em **volume/custo**, fraco em **qualidade de resposta** | **média** |
| **Observabilidade (OTel GenAI)** | gen_ai.* spans, custo/token, multi-turn trace | OTel `whatsapp.*` + spans por serviço (SLA/CSAT/memory). **Sem** convenção `gen_ai.*` nem trace multi-turn de raciocínio (porque não há bot) | **média** |
| **Outcome pricing (pago por resolução)** | Fin/Zendesk/Gorgias | N/A — pricing é por plano ERP. Não é gap de produto, é de modelo | **n/a** |
| **ERP nativo transacional** | nenhum (Decagon integra via API) | **Diferencial único** — ledger Financeiro, NFe, OS Repair nativos | **supera o mercado** |

**Honestidade:** em **atendimento humano-assistido**, o oimpresso está em paridade Chatwoot-tier e **supera** todos os ERPs BR (Bling/Tiny/Omie/Conta Azul têm zero). Em **atendimento automático de verdade (AI agent)**, está na **estaca zero funcional** — tem toda a infra de borda (inbox, fila, handoff, eval interno, ERP) mas **falta o motor central**.

---

## FASE 3 — O que está faltando, rankeado (impacto × esforço)

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-req bloqueante? | Plug-point provável |
|---|---|---|---|---|---|
| 1 | **Ligar a geração de resposta do bot** (substituir o `// SPRINT 3` por chamada real ao copiloto Jana, gravar `sender_kind='bot'`, gate de confidence) | **alto** | ~6-10h | Não (Jana SDK + ContextoNegocio já existem) | `Listeners/DispatchToJanaBot.php:108` → novo `Services/Bot/BotReplyService` + `SendWhatsappMessageJob(sender_kind=bot)` |
| 2 | **PolicyEngine conversacional** (intent classifier + 4 outcomes de RESPOSTA: responder / responder com Brain B / escalar humano / bloquear) — espelha ADS mas pra cliente | **alto** | ~8-12h | Depende de #1 | novo `Modules/Whatsapp/Services/Bot/ReplyPolicy` (NÃO reusar `ADS/PolicyEngine` — categoria diferente) |
| 3 | **Eval contínua de respostas do bot** (apontar o harness RAGAS/golden-set existente pra respostas de cliente; gate de regressão no CI) | **alto** | ~4-6h | Depende de #1 (precisa de saídas reais) | reusar `Jana/.../RagasEvalCITest` + novo `whatsapp-bot-gold-set.json` + gate em CI |
| 4 | **Ingestão de KB do business** (FAQ/site/docs do tenant viram fonte ancorada do bot) | **alto** | ~10-16h | Não, mas potencializa #1 | `Modules/KB` + driver Meilisearch hybrid já existe → expor por `business_id` ao BotReplyService |
| 5 | **Ações agênticas via ERP** (bot resolve: 2ª via boleto, status OS, Pix copia-e-cola) — o diferencial que ninguém tem | **alto** | ~12-20h (por ação, incremental) | Depende de #1 + #2 (guardrail é obrigatório p/ ação financeira) | tool-calling do copiloto → `RecurringBilling`/`Repair` services; **billing = REQUIRE_HUMAN_REVIEW no guardrail** |
| 6 | **Analytics de qualidade por fonte/intent** (não só volume/custo: resolution rate real, por que escalou, qual KB falhou) | **médio** | ~4-6h | Depende de #1 + #6 ter dados | `whatsapp_conversation_metricas` + nova `bot_reply_outcomes` |
| 7 | **Populando inferências de Customer Memory** (sentimento, churn_risk, temas) — "Onda 3" já modelada | **médio** | ~6-8h | Não | `CustomerMemoryRebuilder` (campos já existem, falta job de inferência) |
| 8 | **Convenção OTel `gen_ai.*` + trace multi-turn** do bot (custo/token/raciocínio por resposta) | **médio** | ~3-5h | Depende de #1 | `OtelHelper` + spans no BotReplyService |
| 9 | **Drift doc:** `ARCHITECTURE.md` §16 Baileys vs whatsmeow real | **baixo** (governança) | ~1h | Não | curadoria, fora deste agente |

### Recomendação concreta

**Comece pelo #1 + #3 juntos (resposta do bot atrás de feature-flag + eval gate desde o primeiro commit).** É alto-impacto, esforço médio, e **sem pré-req bloqueante** — Jana SDK, ContextoNegocio (3 ângulos faturamento), `PiiRedactor`, fila, handoff e o harness RAGAS já existem. É literalmente conectar peças que já estão na bancada. Fazer #3 **junto** com #1 (não depois) garante que o bot nasce com catraca de regressão — evita o anti-padrão "subiu bot, qualidade apodreceu sem ninguém ver".

**Não faça primeiro:** #5 (ações agênticas) é o diferencial mais sexy mas **exige** o guardrail #2 maduro antes — ação financeira sem firewall conversacional é P0 de risco (e cruza `billing_financial_flow` BLOCK_ALWAYS do ADS).

**Próxima ação hoje:** criar SPEC curta `US-WA-BOT-001 — resposta automática gate Brain A` definindo: (a) o BotReplyService que substitui o bloco `DispatchToJanaBot.php:108`, (b) os 4 outcomes do ReplyPolicy conversacional, (c) o golden-set inicial `whatsapp-bot-gold-set.json` (20 perguntas reais de Larissa/oficina/gráfica, placeholders Tier 0), (d) feature-flag `whatsapp.bot.reply_enabled` default off + canary biz=1. Aprovação Wagner antes de codar.

---

## A RÉGUA — Scorecard "atendimento automático estado-da-arte"

Pesos somam 100. Métrica objetiva por dimensão. Nota = 0-100.

| Dimensão | Peso | Como medir (métrica objetiva) | Nota oimpresso | Justificativa (1 linha) |
|---|---:|---|---:|---|
| **Resolução autônoma (deflection real)** | 22 | % conversas fechadas só por bot sem humano (`deflection_pct` com bot ligado) | **8** | Coluna existe, mede ~0; bot não responde (placeholder). |
| **Ações agênticas** | 14 | nº de tipos de tarefa que o bot executa sozinho com sucesso | **5** | Macros existem mas atendente dispara; bot executa zero. |
| **Ingestão de conhecimento** | 12 | fontes de KB do tenant ancoradas + freshness; RAGAS faithfulness | **30** | KB + RAGAS existem (Jana interno); não ligados ao cliente. |
| **Guardrails de resposta** | 12 | % respostas que passam por gate (PII/tom/escopo) + taxa de bloqueio correto | **35** | PII redaction forte; sem guardrail conversacional (PolicyEngine é de código). |
| **Eval/regressão contínua** | 12 | existe golden-set + gate CI que barra deploy se qualidade cai | **45** | Harness RAGAS+hallucination de 1ª linha existe, mas mira Jana interno, não bot cliente. |
| **Handoff humano** | 8 | % escalações com contexto completo + tempo até pega humana (SLA) | **75** | `awaiting_human` + assign + SLA aging + Centrifugo — sólido. |
| **Analytics de qualidade** | 8 | resolution rate por intent/fonte + root-cause de escalação | **40** | Métricas de volume/custo boas; qualidade de resposta ausente. |
| **Observabilidade (OTel GenAI)** | 6 | spans `gen_ai.*` com custo/token/multi-turn | **45** | OTel `whatsapp.*` maduro; sem convenção gen_ai nem trace de raciocínio. |
| **Omnichannel** | 6 | nº de canais com paridade de features | **40** | WhatsApp-first forte; outros canais fora de escopo deliberado. |

**Nota-resumo ponderada: ~28/100.**

> Leitura honesta: o **28** mede *atendimento automático* (AI agent). Não confunda com o **~91% / 53,4 de 59** da CAPTERRA-FICHA — aquele score mede *suíte de atendimento omnichannel humano-assistido*, onde o oimpresso é de fato excelente. São duas réguas. Nesta (a régua "AI agent 2026"), o oimpresso é **infra de borda de classe mundial sem o motor**. A boa notícia: 4 das 9 dimensões já têm scaffolding alto (eval 45, KB 30, OTel 45, handoff 75) — o salto do #1+#3 move a nota mais rápido do que parece.

> **module:grade-v4 não rodou:** `vendor/autoload` do checkout principal está com classmap stale apontando pra `oimpresso-tmm-grade` ausente (mesma classe de incidente catalogado em MEMORY.md — deploy/composer interrompido). Não reparei (fora do escopo `estado-da-arte`; só escrevo em `memory/sessions/`). Recomendo `composer dump-autoload` no checkout principal antes da próxima medição.

---

## COMO SOBREVIVE NO TEMPO (durabilidade, não só "bom hoje")

Cruzando com o framework **Knowledge Survival do projeto (ADR 0256 — catraca + sentinela + gate + cadência)** e os gates existentes (`module-grades-gate`, screen-qa catracas, `jana:health-check`):

| # | Item de sobrevivência | Status no oimpresso | O que falta pra ser durável |
|---|---|---|---|
| 1 | **Eval de resposta como CATRACA** | Harness RAGAS/golden existe (Jana) | Estender ao bot de cliente + **gate CI que barra deploy** se faithfulness/resolution cai abaixo do baseline. Sem isso, qualidade do bot apodrece silenciosa. **(crítico)** |
| 2 | **SENTINELA de deflection drift** | `jana:health-check` tem 5 checks SQL; `whatsapp.conversations.deflection_rate` é gauge OTel | Adicionar check #6: alarme se `deflection_pct` cai >X% semana-a-semana OU se escalação humana sobe — sinal de KB apodrecendo ou modelo degradando. **(crítico)** |
| 3 | **Conhecimento que alimenta o bot não apodrece** | KB module + Meilisearch; freshness checker existe (`DesignDocsFreshnessChecker` é doc-only) | Learning loop estilo Zendesk: toda escalação humana vira sinal ("o que o bot não soube") → fila de revisão de KB. Cadência de curadoria do KB por business. **(alto)** |
| 4 | **Guardrail imutável + versionado** | ADS PolicyEngine é "só muda via PR Wagner" (bom padrão a copiar) | ReplyPolicy conversacional deve nascer com a mesma regra append-only + teste que falha se alguém afrouxa o gate de `billing_financial_flow` no bot. **(alto)** |
| 5 | **Observabilidade de custo por resposta** | OTel `whatsapp.cost.centavos`; custo_brain_b no health-check | Span `gen_ai.*` por resposta com tokens/custo + alarme de custo/conversa — Decagon/Fin vivem disso. Evita "bot ficou caro sem ninguém notar". **(médio)** |
| 6 | **Cadência de revisão** | Síntese semanal Jana existe (`SinteseSemanalCommand`) | Incluir no ritual semanal: resolution rate real, top-5 intents que escalaram, custo/conversa, drift de eval. Fechar o loop por métrica (Princípio duro #4). **(médio)** |

**Os 3-5 itens de sobrevivência mais críticos (ordem):**
1. **Eval de resposta do bot como gate CI** (item #1) — nasce junto com o bot ou não nasce.
2. **Sentinela de deflection/escalação drift no `jana:health-check`** (item #2).
3. **Learning loop: escalação humana → revisão de KB** (item #3) — é o que mantém o bot bom em 6 meses.
4. **ReplyPolicy append-only com teste anti-afrouxamento** (item #4) — durabilidade do guardrail.

---

## Fontes de mercado (2026)

- Intercom Fin — automation rate, Fin AI Engine, Custom Answers, guardrails: [intercom.com/help Fin AI Engine](https://www.intercom.com/help/en/articles/9929230-the-fin-ai-engine), [intercom.com/help automation rate](https://www.intercom.com/help/en/articles/13533623-fin-ai-agent-automation-rate), [fin.ai pricing comparison](https://fin.ai/learn/ai-customer-service-agent-pricing-comparison), [callsphere.ai 67% resolution](https://callsphere.ai/blog/vw1b-intercom-fin-ai-67-percent-resolution)
- Decagon — AOP, ações agênticas, Watchtower, deflection: [eesel.ai/blog/decagon](https://www.eesel.ai/blog/decagon), [sacra.com/c/decagon](https://sacra.com/c/decagon/), [ai2.work Decagon $4.5B](https://ai2.work/blog/decagon-hits-4-5b-valuation-as-ai-support-agents-scale-2026)
- Sierra — guardrails determinísticos, Explorer/Experiments/Monitors/Voice Sims: [eesel.ai/blog/sierra-ai](https://www.eesel.ai/blog/sierra-ai), [myaskai.com sierra 2026](https://myaskai.com/blog/sierra-ai-complete-guide-2026)
- Zendesk — Resolution Platform, Learning Loop, KB ingestion, contact reasons: [cmswire.com Relate 2026](https://www.cmswire.com/customer-experience/zendesk-unveils-autonomous-ai-workforce-at-relate-2026/), [zendesk.com AI knowledge base](https://www.zendesk.com/service/help-center/ai-knowledge-base/), [eesel.ai zendesk analytics](https://www.eesel.ai/blog/zendesk-ai-agent-usage-analytics)
- Gorgias / Agentforce — automação real e outcome pricing: [myaskai.com gorgias 2026](https://myaskai.com/blog/gorgias-automate-ai-agent-complete-guide-2026), [lindy.ai gorgias pricing](https://www.lindy.ai/blog/gorgias-pricing)
- Eval/observability — OTel GenAI, multi-turn, LLM-as-judge online: [opentelemetry.io GenAI observability](https://opentelemetry.io/blog/2026/genai-observability/), [opentelemetry.io AI agent observability](https://opentelemetry.io/blog/2025/ai-agent-observability/), [getmaxim.ai top 5 2026](https://www.getmaxim.ai/articles/top-5-tools-for-ai-agent-observability-in-2026/)

### Fontes internas cruzadas
- `Modules/Whatsapp/Listeners/DispatchToJanaBot.php` (placeholder `// SPRINT 3` confirmado, linha 108)
- `Modules/Whatsapp/Services/{Sla,Macros,Csat,CustomerMemory,Audio}/*` (suíte real shipada)
- `Modules/ADS/Services/PolicyEngine.php` (firewall de código, NÃO conversacional)
- `Modules/Jana/Tests/Feature/Ai/HallucinationEvalTest.php` + `JanaRagasCiCommand` + `Modules/KB/Tests/Feature/KbRagasEvalTest.php` (eval harness state-of-the-art, interno)
- `memory/requisitos/Whatsapp/{CAPTERRA-FICHA,COMPARATIVO-MERCADO-2026-05-12-v2,ARCHITECTURE}.md`
- ADR 0093 (Tier 0), ADR 0106 (10x), ADR 0256 (Knowledge Survival)
