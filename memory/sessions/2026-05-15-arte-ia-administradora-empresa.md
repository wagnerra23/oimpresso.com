# Estado-da-arte 2026 — IA Administradora da Empresa (oimpresso)

> **Data:** 2026-05-15 · **Autor:** agent `estado-da-arte` · **Cliente:** Wagner
> **Escopo:** comparar onde Jana+ADS+FSM estão vs líderes 2026 em "agente IA que decide e executa administração", e propor próximo passo.
> **Tempo de pesquisa:** ~30min (9 WebSearches), 0 leituras `memory/` durante Fase 1 (limpa).

---

## 1. TL;DR

- **Oimpresso já tem 70% da espinha dorsal que líderes 2026 cobram caro:** Jana (memória persistente cross-sessão + MCP server) + ADS (Risk → Confidence → Policy → Router → Brain A/B + 4 níveis HITL) + FSM Pipeline (executor auditável real em prod biz=1). Salesforce Agentforce, SAP Joule e Microsoft Copilot Studio têm a MESMA topologia conceitual, com nomes diferentes.
- **Gap #1 (maior delta):** **ADS hoje roteia só atendimento WhatsApp — nunca foi conectado ao FSM Pipeline.** Líderes 2026 ganham porque o Router decide e o executor age **no mesmo trilho auditável**. Oimpresso tem os dois trilhos prontos mas desconectados. Conectar é trabalho de configuração + 1 sprint, não de invenção.
- **Gap #2 (compliance):** Art. 20 LGPD virou regulação real em 2026 (Nota Técnica 12/2025 ANPD) — toda decisão automatizada exige canal explícito de revisão humana + log de "decidido por IA". Oimpresso tem `mcp_dual_brain_decisions` (audit) mas não tem UI de cliente final ("essa cobrança foi decidida por IA, peça revisão aqui").
- **Gap #3 (case de uso maduro):** **Cobrança autônoma (AR/dunning agents)** é o caso mais commoditizado em 2026 — Daylit, Stuut, HighRadius, Kapittx entregam DSO -25%, collection +35%. Oimpresso tem Asaas + WhatsApp + FSM, falta amarrar como "Cobradora autônoma da Larissa".
- **Nota global de maturidade:** **62/100** (líderes 2026 ~80-85). Não estamos atrás na arquitetura — estamos atrás na **integração end-to-end de 1 caso administrativo real**.
- **Recomendação:** **NÃO** sair pesquisando agents framework novos. **Conectar ADS → FSM** num piloto único (Cobrança ROTA LIVRE) é alto-impacto-baixo-esforço com tudo pré-pronto.

---

## 2. Estado-da-arte 2026 — top 7 players

### 2.1 Salesforce Agentforce / Agentforce Operations (líder enterprise CRM-centric)

Atlas Reasoning Engine + Data Cloud. Em 2026 lançou **Agentforce Operations**, agentes back-office que rodam cross-sistema (email → ERP) com **cycle time -70%** em auditoria/onboarding e **80% das tarefas manuais eliminadas**. Mecanismo: agentes consomem **Flows + Apex + APIs existentes como "actions"** (não reinventam o sistema — viram orquestradores). Em junho 2026 fez 30 enhancements internos (LLM calls de 4→2, replace input safety checks LLM-based por regras determinísticas, HyperClassifier) → **-70% latência**. Referência porque **provou em escala que agentes só funcionam quando o ERP já tem ações tipadas e auditáveis** — não bolted-on.

### 2.2 Microsoft Copilot Studio + Dynamics 365 (líder enterprise produtividade-centric)

Wave 1/2026 entregou role-based agents (Sales Qualification, Customer Service Agent) integrados a Dynamics 365. Maio 2026: Copilot Studio virou **"agent control center"** com governança + AgentOps + workflows. Diferencial: **agent usage estimator unificado** — orçamento de "Copilot credits" cross-agente vira primeira-classe. Mecanismo de governança: cada agente é registrado, com owner explícito, escopos de dados, e métricas de consumo trackadas centralizadamente. Referência porque **trata "governança de N agents" como produto, não como afterthought** — onde Wagner já está caminhando com Modules/ADS skills+governance UI.

### 2.3 SAP Joule + Joule Studio (líder ERP-nativo)

SAP Sapphire 2026: "Autonomous Enterprise". **50+ Joule Assistants** orquestrando **200+ Joule Agents** especializados em finance/supply/procurement/HCM/CX. Mecanismo central: **SAP Knowledge Graph** — grafo de entidades de negócio + processos + relacionamentos cross-módulos que dá contexto estruturado ao agente. **Joule Studio** = IDE de agents (no-code + pro-code + AI). **Parceria com Anthropic** — Claude é um dos modelos foundation que rodam dentro do Joule. Referência porque **valida que ERP-nativo + grafo de conhecimento estruturado + LLM externo escolhido por task é o stack vencedor** (não treinar modelo próprio).

### 2.4 HubSpot Breeze Agents (líder SMB/PME — comparável direto a oimpresso)

3 agents GA em 2026: **Customer Agent, Prospecting Agent, Data Agent** + Content Agent em beta. Diferenciais 2026: (1) **Audit Cards** — timestamped record de cada AI action mostrando properties mudadas + dados que informaram a decisão; (2) **9 canais nativos** (chat, email, WhatsApp, SMS, Instagram, Telegram, LINE, Slack, FB Messenger) + voice beta; (3) Data Enrichment free pra standard fields. Pricing: gated em planos $450-3600/mo. Referência mais relevante pra oimpresso (mesmo target — PME) porque **prova que Audit Cards visíveis ao cliente final é diferencial vendável**, não overhead.

### 2.5 Cognition Devin 2.0/3.0 (líder agent autônomo single-domain)

Foco engenharia de software, mas a arquitetura é didática. Devin 3.0 (2026) faz **dynamic re-planning sem intervenção humana** quando bate em roadblock — é a **Reflection pattern** (Reflexion paper) em produção. Deploy enterprise: **VPC com código não saindo do boundary do cliente** + SAML SSO + audit logging + custom usage guarantees. Avaliação atual: $25B valuation. Referência porque **mostra que agente autônomo single-purpose com re-planning + audit chega a ROI defensável** — Wagner pode replicar a forma (não a função) num agent "Cobradora" oimpresso.

### 2.6 Lindy.ai / n8n+IA (líder workflow agents SMB)

Lindy = **agents conversacionais com memória + planning built-in**, time-to-value <1h pra primeira automação. n8n+IA = **visual workflow com agents wireados manualmente** (precisa criar memória/RAG na mão), tempo 40-80h, mas controle total. Tradeoff: **Lindy não te dá Tier 0 multi-tenant nem audit append-only** — é SaaS multi-customer com cross-pollination de aprendizado. n8n self-hosted dá controle mas exige montar tudo. Referência porque **ambos validam que PME não quer "construir agent" — quer "ligar agent num botão"** — UX que oimpresso ainda não tem.

### 2.7 Stuut / Daylit / HighRadius / Kapittx (líderes case-de-uso AR/Cobrança)

Categoria mais commoditizada de "agente administrativo autônomo" em 2026. Resultados típicos publicados: **DSO -25%, collection rate +35%, manual effort -80%, 95%+ auto-match em cash application, email reply rate ~50% (3x média indústria)**. Mecanismo: agente aprende voz da empresa + escolhe **canal ótimo (email/SMS/voz) + timing + tom + escalation** baseado em segmento de cliente + histórico + risco de delinquência (predito semanas antes). **Daylit** lançou em 2026 especificamente "AI Agents for AR" com cash intelligence real-time. Referência porque **é o caso administrativo mais provado em SaaS-de-prateleira** — replicar pra ROTA LIVRE/oimpresso é seguir receita conhecida, não pioneirismo.

### 2.8 (Bonus) Anthropic Agent SDK + Skills + Computer Use

Não é concorrente — é o stack que oimpresso já usa. Em 2026 ganhou: **Skills** (folders de instruções/scripts/recursos carregados dinamicamente), **Dreaming** (processo agendado que revisa sessions + memórias e extrai padrões — Memory Layer), e **Claude for Small Business** (lançado 2026-05-13). Referência porque **valida que o pattern "skills auto-load + memória curada + computer use" é o caminho da Anthropic** — oimpresso já tem skills+memória, falta Dreaming-equivalente.

### Concorrentes diretos BR (Bling/Tiny/Omie/ContaAzul)

Busca explícita não retornou nada concreto de agente autônomo desses 4 em 2026. Aparecem como **plataformas com APIs robustas que viram alvo de orquestração via n8n+IA externa** — não como players de agente nativo. **Isso é janela competitiva enorme do oimpresso**: chegar primeiro com agente nativo no ERP BR-PME (não bolted-on via Zapier) é diferencial defensável.

---

## 3. Comparativo oimpresso vs líderes (15 dimensões)

Notas: **0** = não existe · **1** = embrionário · **2** = funcional com gap · **3** = par com líderes 2026.

| # | Dimensão | Líder 2026 (ref) | Oimpresso hoje | Nota | Justificativa |
|---|---|---|---|---|---|
| 1 | Memória persistente cross-sessão | SAP Knowledge Graph + Anthropic Dreaming | Meilisearch hybrid + Ollama embedder + `copiloto_memoria_facts/metricas` + MCP server `mcp.oimpresso.com` | **3** | Topologia 1:1 com líderes; falta "Dreaming" (curadoria periódica) |
| 2 | Decisão escalada (Policy/Router) | Agentforce Atlas Reasoning + Copilot Studio governance | ADS: Risk → Confidence → Policy (4 outcomes) → Router (Brain A/B) | **3** | Firewall hardcoded `BLOCK_ALWAYS`/`REQUIRE_HUMAN_REVIEW` é par com líderes |
| 3 | Multi-agente coordenado (Planner+Executor+Reviewer) | HALO (arXiv:2505.13516), Devin 3.0 re-planning | ADS: BrainBAgent + PlannerAgent + ProjectDecomposerAgent + **ReviewerAgent** já existem | **2** | Tudo cabeado, falta fluxo end-to-end exercitado num caso real (rodam isolados) |
| 4 | HITL adjustável por confidence | Galileo, MIT (alerta de hallucination overconfidence) | 4 níveis HITL canônicos (L0/L1/L2/L3) em ARQ-0008 | **3** | Modelo é par; falta uso real (hoje só Wagner usa) |
| 5 | Auditabilidade append-only | Salesforce Agentforce audit, HubSpot Audit Cards | `mcp_dual_brain_decisions` + `sale_stage_history` FSM + ADR 0094 §"Confiabilidade com fallback" | **3** | Backend par com líderes; **falta UI cliente final** (Audit Card visível) |
| 6 | Multi-tenant isolation (Tier 0) | Nenhum líder 2026 trata como Tier 0 absoluto — geralmente é "feature de Enterprise plan" | `business_id` global scope IRREVOGÁVEL (ADR 0093) + Pest cross-tenant | **3+** | Oimpresso supera líderes SaaS multi-customer; ROTA LIVRE não vaza pra biz=1 |
| 7 | Execução real no ERP (não só sugestão) | Agentforce Operations (cross-system), SAP Joule (200+ agents executores) | **FSM Pipeline LIVE biz=1** (11 stages × 21 actions × audit) + side-effects (NFe, Asaas, WhatsApp) | **2** | Trilho pronto, **mas desconectado do ADS** — IA hoje não dispara FSM |
| 8 | Custo LLM tiered (Haiku/Sonnet/Opus) | Waterfall pattern (70-80% tráfego em smaller models) | Brain A (Ollama qwen local $0) + Brain B (claude-sonnet-4-6) + BriefDiarioAgent (gpt-4o-mini ~R$0.02/brief) | **3** | Já economiza onde líderes só começaram |
| 9 | Confiabilidade/fallback | Anthropic SDK reliability features | ADR 0094 §"Confiabilidade com fallback" + Brain A → Brain B escalation + HITL fallback | **2** | Documentado, exercitado em poucos paths |
| 10 | Observabilidade GenAI (OTel) | Galileo, Microsoft AgentOps, SAP Joule governance | OTel GenAI instrumentado + `mcp_briefs` custo trackado + `mcp_decisions` latência | **2** | Coleta sim, dashboard decisor não — sem drift detection ativo |
| 11 | Compliance BR (LGPD Art. 20 / CONFAZ / CLT) | Líderes EUA não fazem; players BR (Halk/Lefosse) só assessoram | **Counsel LGPD externo pendente** (Eliana estudando, não DPO ainda); CONFAZ via NFeBrasil já cobre; Tier 0 cobre PII | **1** | Backend técnico ok, **camada legal explícita do agente faltando** — risco regulatório real 2026 |
| 12 | Capability tokens / sandboxing | Computer Use sandboxed shell, Devin VPC | `PolicyEngine::BLOCK_ALWAYS` lista paths sensíveis; `Tool::name` enumerado em `ToolRegistry` | **2** | Existe, sem teste adversarial recente |
| 13 | Reflection / self-critique | Reflexion paper (validado por Wagner 2026-05-13 dogfood do `estado-da-arte` agent), Devin 3.0 re-planning | ReviewerAgent existe; loop de auto-revisão não está em produção contínua | **1** | Tem componente, falta orquestração |
| 14 | Memória episódica vs semântica | Distinção crítica em papers 2025-2026 | `copiloto_memoria_facts` (semântica) vs `copiloto_memoria_metricas` (episódica) — distinção existe mas nomenclatura não bate com literatura | **2** | Funciona, vocabulário não-canônico complica evolução |
| 15 | Voice/multimodal admin (PME 2026) | HubSpot Breeze voice beta; Salesforce voice agents enterprise | WhatsApp texto + foto via Baileys; voz não | **1** | Gap real pra Larissa (que prefere voz/foto a digitar) |

**Nota global ponderada (P0=4, P1=2, P2=1):**
- P0 dimensions (1, 2, 4, 5, 6, 7, 11): média 2.57 × 4 = 10.28
- P1 dimensions (3, 8, 9, 10, 12): média 2.20 × 2 = 4.40
- P2 dimensions (13, 14, 15): média 1.33 × 1 = 1.33
- Total = 16.01 / 26.0 máximo = **61.6/100** → **62/100**

Líderes 2026 ficariam em ~80-85 (poucos cravam 3 em **todas** as 15 — Agentforce Operations + SAP Joule mais perto).

---

## 4. Top 10 gaps por impacto × esforço

| # | Gap | Impacto | Esforço (IA-pair, fator 10x ADR 0106) | Pré-req |
|---|---|---|---|---|
| 1 | **ADS → FSM bridge:** quando Brain A/B aprova ação "transitar venda X de stage Y → Z" ou "cobrar fatura ID", chamar `ExecuteStageActionService` em vez de notificar Wagner | **Altíssimo** — desbloqueia TODOS os outros casos de uso | M (~16-24h IA-pair) | Nenhum bloqueante; FSM live, ADS live |
| 2 | **Piloto Cobrança Autônoma ROTA LIVRE:** agente "Cobradora Larissa" que escolhe canal (WhatsApp/email) + tom + timing + escalation via Asaas → bloqueio → HITL Wagner | **Alto** (DSO/cashflow Larissa + showcase comercial) | M (~20-30h IA-pair) | Gap #1 |
| 3 | **Audit Card visível ao cliente final:** UI em `/copiloto/*` mostrando "esta decisão foi tomada por IA — solicite revisão humana" (Art. 20 LGPD) | **Alto** (compliance + vendabilidade) | S (~4-8h IA-pair) | Nada — só UI sobre `mcp_dual_brain_decisions` |
| 4 | **Dreaming-equivalente** (cron semanal que curada memória, extrai padrões, registra em `mcp_decision_patterns`): hoje Pattern Learning existe (ARQ-0007) mas roda só semanal mecânico, não curatorial | **Médio** | S (~6-10h IA-pair) | Decisão de schema curado |
| 5 | **Counsel LGPD formalizado + DPO externo** pra agente autônomo (não pra DaaS Pilar 5 já descartado) | **Médio-alto** (risco regulatório) | L humano (Wagner contrata) | Wagner decide orçamento |
| 6 | **Drift detection GenAI no dashboard:** alerta quando custo Brain B sobe X% ou taxa REQUIRE_HUMAN_REVIEW dispara (sinal de drift de policy ou input) | **Médio** | S (~6h IA-pair) | OTel já coleta |
| 7 | **Voice admin (Larissa fala, IA age):** Whisper transcribe → BriefDiarioAgent style + FSM action | **Médio** (UX Larissa 1280px monitor + mãos ocupadas) | M (~12-20h IA-pair) | Gap #1 |
| 8 | **Reflection loop em produção:** ReviewerAgent revisa BrainBAgent output antes de executar, re-plan se score < threshold | **Médio** | S-M (~8-12h IA-pair) | Gap #1 |
| 9 | **Knowledge graph leve (ao estilo SAP):** entidades de domínio Vestuario (cliente, pedido, NFe, fatura, OS) + relações tipadas pra contextualizar agent — hoje agent consulta tools soltas | **Médio** (qualidade decisão) | L (~30-40h IA-pair) | Modelagem ADR nova |
| 10 | **Renaming `copiloto_memoria_metricas` → `_episodic_memory` e `facts` → `_semantic_memory`** alinhando ao vocabulário 2025-2026 (evita confusão de evolução) | **Baixo** | S (~2-4h IA-pair, migration + refactor) | ADR de nomenclatura |

---

## 5. Roadmap CONSOLIDAR vs EVOLUIR

### CONSOLIDAR (existente que precisa amarrar — 70% do trabalho)

1. **ADS Router conectado a FSM ExecuteStageActionService** (gap #1) — bridge service novo `Modules/ADS/Services/FsmActionBridge.php` que recebe `RoutingDecision` com action_key + subject_id e chama FSM.
2. **HITL UI cliente final** (gap #3) — view Inertia em `/copiloto/decisoes/{id}/revisao` reusando `mcp_dual_brain_decisions`.
3. **Pattern Learning L2 ativado real** (gap #4) — cron `ads:pattern-learn` já existe (`PatternLearningService.php`), exercitar com dados reais Brain A/B.
4. **Drift dashboard** (gap #6) — extender `/copiloto/admin/memoria` com 3 gráficos OTel.

### EVOLUIR (peças novas — 30%)

1. **Cobradora Larissa** (gap #2) — agente novo `Modules/Copiloto/Ai/Agents/CobradoraAgent.php` com tools (`ListarFaturasVencidasTool`, `EscolherCanalEnvioTool`, `AgendarEscalationTool`) + Charter próprio + ADR de visão.
2. **Voice admin** (gap #7) — pode esperar até CobradoraAgent provar caso 1.
3. **Knowledge graph** (gap #9) — só depois de 2-3 agents administrativos validarem necessidade real.

---

## 6. Casos de uso administrativos rankeados por maturidade SaaS-de-prateleira 2026

| Rank | Caso | Maturidade SaaS 2026 | Fit oimpresso (ROTA LIVRE) | Recomendação |
|---|---|---|---|---|
| 1 | **Cobrança/dunning autônomo** | 🟢 Alta (Stuut/Daylit/HighRadius) | 🟢 Altíssimo — Asaas + WhatsApp + FSM prontos | **Piloto #1** |
| 2 | **Auditoria diária (anomalia vendas/estoque/NFe)** | 🟡 Média (Agentforce Operations) | 🟢 Alto — BriefDiarioAgent já narra, falta acionar | Piloto #2 |
| 3 | **Triagem CRM inbound + qualificação** | 🟢 Alta (HubSpot Prospecting, Lindy) | 🟡 Médio — Vestuario não é CRM-pesado (Larissa atende direto) | Esperar ComVis |
| 4 | **Aprovação despesa <R$X** | 🟡 Média (Copilot Studio finance agents) | 🟡 Médio — fornecedor histórico ainda raso | Esperar gap #9 |
| 5 | **Sugestão reajuste preço** | 🟡 Média | 🟡 Médio — precisa série temporal vestuário | Backlog |
| 6 | **Decisão compra fornecedor (ruptura)** | 🟠 Baixa-média | 🟠 Baixo — Larissa tem pouco SKU | Backlog |
| 7 | **Folha + ponto (PontoWr2)** | 🟠 Baixa (CLT complexa) | 🟠 Baixo — Eliana(WR2) é cliente externa, não próprio uso | Não atacar |
| 8 | **Jurídico-admin (LGPD/contratos)** | 🟠 Baixa (alto risco) | 🔴 Bloqueado — sem DPO formal | Esperar gap #5 |

---

## 7. Riscos LGPD/CLT/CONFAZ específicos pra agente autônomo BR

1. **LGPD Art. 20** (Nota Técnica 12/2025 ANPD, abril 2026): decisão exclusivamente automatizada que afete interesse do titular (perfil, consumo, comportamento) exige (a) **informar que decisão foi automatizada** e (b) **canal de revisão humana**. Multa até 2% receita ou R$50M. **Impacto pra Cobradora:** cada mensagem de cobrança autônoma precisa rodapé "decisão automatizada — solicite revisão em X" + UI funcional dessa revisão (gap #3).
2. **CONFAZ SINIEF 07/2005 Art. 14**: número NFe usado oficialmente nunca é hard-deleted (ADR proibições já cobre). Agente que cancela NFe via FSM `CancelarVendaCascade` já segue regra — OK.
3. **CLT Art. 66 / Portaria MTP 671/2021**: PontoWr2 marcações são append-only (ADR 0094). Agente sugerindo "anular marcação Larissa" violaria — **bloquear na PolicyEngine BLOCK_ALWAYS** se acaso surgir.
4. **Banco Central / PIX (Lei 14.478/2022 + Resolução BCB 403/2024)**: cobrança via PIX/boleto autônoma exige consentimento prévio do pagador armazenado e revogável. Asaas é responsável regulatório, **mas oimpresso precisa registrar consentimento por contato** (`Contact::canReceiveWhatsappNotification()` já existe — extender).
5. **Risco específico agentes generativos**: MIT 2026 mostrou modelos são **34% mais confiantes quando alucinam**. Significa que confidence threshold sozinho não basta — **policy determinística + audit + HITL para casos de alto blast radius** (que ADS já faz — manter rigor).

---

## 8. Próximo passo recomendado (1 frase + ação concreta hoje)

**Recomendação:** começar pelo **Gap #1 (ADS → FSM bridge)** + **Gap #2 (Cobradora Larissa piloto)** combinados em **1 ADR de visão** + **1 sprint piloto biz=4 ROTA LIVRE**. Não pesquisar mais frameworks. Não evoluir Jana. Não inventar agent framework novo. Apenas **conectar 2 trilhos prontos num caso administrativo provado**.

**Ação concreta hoje (Wagner):**

1. Aprovar criação de **ADR de visão "0144-oimpresso-ia-administradora-pivot-ads-fsm.md"** que congela escopo: "ADS roteia ações administrativas pra FSM; primeiro caso = Cobradora ROTA LIVRE; sem novos frameworks até CYCLE-07."
2. Pedir ao agente `coordenador-paralelo` decomposição em 4-5 waves isoladas (FsmActionBridge service / CobradoraAgent + tools / HITL Audit Card UI / Pattern Learning ativação / Pest cross-tenant + smoke biz=4).
3. Pedir ao agente `capterra-senior` ficha completa do "módulo virtual Cobrança" (não existe Modules/Cobranca formal — usar Financeiro + Asaas como proxy) pra ter nota 0-100 calibrada antes do piloto.

**Não fazer agora:**

- ❌ Não promover ADS pra Tier A skill always-on (overhead de contexto).
- ❌ Não chamar Vizra/CrewAI/LangGraph (decisão ADR 0048 já fechou).
- ❌ Não escrever código antes do ADR de visão aprovado.
- ❌ Não atacar gaps #5/#7/#9 antes do piloto Cobradora rodar 30d em biz=4.

---

## Anexo — Sources consultadas (Fase 1, limpa)

- [Salesforce Agentforce 2026](https://www.salesforce.com/agentforce/) · [Agentforce Operations announcement](https://www.salesforce.com/news/stories/agentforce-operations-announcement/) · [8 Ways AI Agents Are Evolving in 2026](https://www.salesforce.com/blog/ai-agent-trends-2026/)
- [Microsoft 2026 Wave 1 release plans](https://www.microsoft.com/en-us/dynamics-365/blog/business-leader/2026/03/18/2026-release-wave-1-plans-for-microsoft-dynamics-365-microsoft-power-platform-and-copilot-studio-offerings/) · [Copilot Studio April 2026 update](https://www.microsoft.com/en-us/microsoft-copilot/blog/copilot-studio/new-and-improved-agent-governance-intelligent-workflows-and-connected-app-experiences/)
- [SAP Sapphire 2026 Autonomous Enterprise](https://news.sap.com/2026/05/sap-sapphire-sap-unveils-autonomous-enterprise/) · [Joule Studio announcement](https://news.sap.com/2026/05/new-joule-studio-enterprise-scale-agentic-development/)
- [HubSpot Breeze 2026 guide](https://www.onthefuze.com/hubspot-insights-blog/hubspot-breeze-ai-agents-2026) · [Breeze AI Agents official](https://www.hubspot.com/products/artificial-intelligence/breeze-ai-agents)
- [Cognition Devin 2.0 blog](https://cognition.ai/blog/devin-2) · [Devin 2026 enterprise review](https://aitoolsdevpro.com/ai-tools/devin-guide/)
- [Multi-agent orchestration patterns 2026](https://www.codebridge.tech/articles/mastering-multi-agent-orchestration-coordination-is-the-new-scale-frontier) · [HALO arXiv:2505.13516](https://arxiv.org/html/2601.13671v1)
- [Lindy vs n8n 2026](https://www.lindy.ai/blog/n8n-ai-agents) · [SMB Automation decision framework 2026](https://www.firstaimovers.com/p/smb-automation-platform-comparison-guide-2026)
- [Anthropic Agent Skills overview](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview) · [Equipping agents with Skills](https://www.anthropic.com/engineering/equipping-agents-for-the-real-world-with-agent-skills) · [Claude for Small Business](https://siliconangle.com/2026/05/13/anthropic-launches-claude-small-business-new-automation-workflows/)
- [ANPD Nota Técnica 12/2025 Art. 20](https://lefosse.com/noticias/inteligencia-artificial-anpd-publica-nota-tecnica-sobre-decisoes-automatizadas/) · [LGPD e Agentes de IA 2026](https://www.halk.io/blog/pt/lgpd-agentes-de-ia-compliance)
- [Daylit AI Agents for AR](https://financialit.net/news/artificial-intelligence/daylit-launches-ai-agents-accounts-receivable-bringing-autonomous) · [AI in AR 2026 checklist](https://www.kolleno.com/ai-agents-for-accounts-receivable-feature-checklist-for-finance-teams-in-2026/)
- [Galileo HITL guide](https://galileo.ai/blog/human-in-the-loop-agent-oversight) · [AI Agent Adoption 2026 (120+ data points)](https://www.digitalapplied.com/blog/ai-agent-adoption-2026-enterprise-data-points)

---

*Fim do doc. Próximo turno: Wagner decide se aprova ADR de visão + sprint piloto.*
