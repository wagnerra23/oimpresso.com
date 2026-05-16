# Dossiê estado-da-arte KB IA enterprise 2026 — gaps vs oimpresso

> **Autor:** Agent D (audit-senior-expert) spawn paralelo sessão 2026-05-15
> **Trigger:** Wagner declarou "módulo mais importante pra IA — visualização sobre meus dados" + autorizou implementação ONDA 1-5
> **Tempo:** ~85min · **WebSearch:** 23 buscas · **WebFetch:** 0
> **Output original:** inline na resposta do agent (não criou .md por regra harness sobre report files). Esta cópia é a persistência pelo parent agent pra time MCP futuro consultar.

---

## TL;DR (5 linhas)

O Bench Cowork v5 (~9,55-9,70/10) está **bem calibrado** vs Notion/Confluence/Guru/Slab/Stonly/Intercom, MAS **subestima** 4 ameaças que entraram fortes em 2025-Q4 e 2026-Q1: (i) **Glean** virou middleware de IA enterprise ($7,2B / $200M ARR / 100M+ agent actions ano / entrou LATAM via Algorithia em out/2024); (ii) **Notion MCP server** (jan/2026) + Workers/Agents transformaram Notion em plataforma de agente full-stack; (iii) **Omie** (jun/2025) e **Conta Azul** (ago/2025) já lançaram IA conversacional em PT-BR pra PME brasileira — janela "ninguém faz em pt-BR" encolheu de ~24 pra ~12-18 meses; (iv) **ServiceNow+Moveworks** ($2,85B, dez/2025) consolidou stack agentic enterprise. Janela oimpresso ainda existe (verticalização gráfica + integração ERP nativa profunda + custo zero) mas precisamos fechar **3 gaps P0-P1** em até 6 meses: (1) **ACL-aware RAG com query-time enforcement**, (2) **Content-gap suggestions automáticas / stale detection** (content health AI), (3) **Voice interface PT-BR via WhatsApp pro KB** (Omie já tem, Larissa/Mateus PCP usam áudio naturalmente). E 2 P2 pra Bench v3.

---

## Correção factual aplicar no CAPTERRA-FICHA atual

> ⚠️ A `CAPTERRA-FICHA.md` (commit `7ec785bfb`) menciona "Guru pós ServiceNow acquisition 2024" — **INCORRETO**. ServiceNow comprou **Moveworks** ($2,85B, dez/2025). Guru segue independente.

Patch sugerido: substituir referência no CAPTERRA + adicionar Moveworks como concorrente novo na seção "Análise de risco competitivo".

---

## Concorrentes Q4-2024 → 2026: o que mudou

### 1. Glean

- **Posicionamento 2026:** virou camada de **intelligence middleware** (não mais "Google for enterprise") — sob TODA interface IA, age como permissions-aware knowledge graph + agentic engine sobre 100+ conectores SaaS. Pivô estratégico explícito em fev/2026 (TechCrunch).
- **Features-chave 2026:** Agentic Engine 2, Canvas co-authoring, **Personal Graph** (modela trabalho real do funcionário — projetos ativos, colaboração, padrões), Glean Apps + Actions + APIs (build agents customizados), line-by-line citations contra alucinação, **permissions-aware retrieval** (filtra por ACL em query-time).
- **Diferencial vs Bench Cowork:**
  - Ganha: **knowledge graph com personal dimension + activity tracking** (oimpresso só tem grafo estático ADR-supersedes); **agentic actions** (oimpresso é leitura/RAG, sem agente executor); **100+ conectores nativos**.
  - Perde: **integração ERP profunda** (Glean lê SaaS via conector raso, não SOMOS o ERP); **fit pt-BR/gráfica**; **custo** (US$40-65/user/mês + Work AI add-on US$15).
- **Preço:** US$40-50/u/mês base + ~US$15 Work AI. First-year US$300k-US$1M.
- **Integração ERP BR:** ❌ nenhum conector Bling/Tiny/Conta Azul/Omie publicado.
- **IA model:** multi-model agnóstico (GPT-4/Claude/proprietário grounding).
- **Vis-grafo:** ❌ knowledge graph é **interno** (powering RAG), não há UI Cytoscape-like pro user explorar visualmente. **Esse é gap deles, vantagem nossa.**
- **Captação:** Series F US$150M / $7,2B valuation jun/2025; US$200M ARR (dobrou em 9 meses).
- **Roadmap 2026:** LATAM via Algorithia (parceria out/2024), 27 países, expansão internacional acelerada.

### 2. Mem.ai

- Memory-first AI thought partner; captura passiva + auto-organização (zero pastas/tags manuais).
- Mem 2.0 (early 2025); Smart Tags automáticos; Mem Chat (RAG sobre próprias notas); Related Notes contextuais.
- Diferencial: ganha em **captura passiva ambient**; perde em **ERP integration**, **multi-tenant Tier 0**, **grafo visual**, **personas operacionais**.
- ~US$10/mês individual; integração ERP BR ❌; sem vis-grafo público.

### 3. Notion AI 2026

- De wiki pra plataforma agente em 18 meses.
- **Custom Agents** background (Slack mention / DB row trigger / weekly schedule); **Workers** (JS/Python custom code execution); **Q&A** sobre workspace; context window 50 páginas (era 20); **Notion MCP server oficial** (jan/2026) — Claude/ChatGPT/Cursor escrevem em Notion direto.
- Ganha em **editor (9.8)**, **agentes custom**, **plataforma MCP server oficial**. Perde em **ERP nativo**, **verticalização**, **multi-tenant business_id Tier 0**, **fit gráfica**, **custo** ($10-30/user/mês).
- Vis-grafo ❌ (grafo de páginas é hierárquico, não visual).

### 4. Stonly 2026

- Decision tree + AI agent assist + conversational AI bot pra suporte.
- Empate técnico em **decision tree** (Cowork 10,0 já fechou via editor visual + biblioteca + histórico); Stonly ganha em **integração ticketing nativa**; perde em **ERP**, **editor blocos**, **trilhas**, **grafo visual**, **fit pt-BR**.
- Free 400 views/mês; Small Business $249/mês (5 seats); Enterprise custom.

### 5. Guru AI Answers (CORREÇÃO: não acquired por ServiceNow)

- **ServiceNow comprou Moveworks** ($2,85B, dez/2025), NÃO Guru. Guru segue independente.
- AI Assist contextual, Knowledge Agents proativos, verificação por dono, browser extension.
- $25/seat/mês com mínimo 10 seats = $250/mês mínimo.

### 6. Microsoft Copilot SharePoint M365 2026

- Sleeping giant — KB embarcado no M365 com 400M+ enterprise seats.
- **AI in SharePoint** (era Knowledge Agent set/2025, rebranded 2026) — extrai/aplica metadata automaticamente; **Work IQ**; **Authoritative sources** admins designam SharePoint sites; ground prompts em SharePoint lists via `/sites`.
- Ganha em escala, integração M365 nativa, maturidade ACL/governance. Perde em fit pt-BR/PME, ERP BR ZERO, custo ($30/u/mês Copilot M365).
- Vis-grafo ❌.

### 7. ClickUp Brain / Asana AI / Linear AI

- ClickUp Brain 2026: $5-7/u/mês add-on; Knowledge Manager Q&A; AI Notetaker; Multi-Model Access (GPT-5, Claude Opus 4.1, o3 toggle).
- Linear Docs (beta): keyboard-first, sem KB-purpose-built ainda.
- Ganha em integração PM; perde em grafo visual, ERP nativo, personas operacionais.

### 8. Atlassian Rovo 2026

- GenAI/agents nativos em Jira+Confluence.
- Rovo Search + Chat + Agents + **Studio** (build agents no-code); credit multiplier; Rovo Dev (agentes pra dev teams).
- $0 incremental em planos Premium/Enterprise Cloud; standalone $20/u/mês add-on.

### 9. Pinecone Assistant / Vectara — RAG-as-a-Service

- Infra layer pra builds próprios; não é produto KB final.
- Pinecone $0,33/GB/mês storage + $8,25/1M reads + $2/1M writes; Standard plan $50/mês mínimo.
- Mercado total: RAG enterprise $1,94B (2025) → $9,86B (2030) CAGR 38,4%.
- **Relevância oimpresso:** alternativa ao Meilisearch hybrid se Meili escalar mal. Hoje [ADR 0035](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) Meili+Ollama dá conta; revisitar quando >10k docs/business.

### 10. Intercom Fin AI 2026

- AI agent customer support deflection.
- **Knowledge Hub** centraliza help center + internal + external; **Content Gap Suggestions** (Fin escaneia tickets não-resolvidos + flag artigos faltando, ranqueado por impacto); 45+ languages auto-detect.
- $0,99/resolution (volume $0,59 enterprise 10k+/mês).
- **Content Gap Suggestions é estado-da-arte 2026** — Cowork NÃO tem isso. **Gap real pro oimpresso.**

### 11. BR — Omie / Conta Azul / Bling / Tiny

- **Omie (jun/2025):** primeiro ERP do mundo operável 100% via WhatsApp (texto + áudio); IA conversacional gratuita inclusa; emite NFe, controla estoque, lança financeiro via voz. Roadmap: agente conversacional generativo completo + crédito embarcado.
- **Conta Azul (ago/2025):** IA generativa pra contadores PME; WhatsApp captura recibos via foto → lança automático; testou 6 meses em 100 contabilidades antes de GA.
- **Tiny (pós Olist acquisition 2024):** AI agents pra conversar com dados, gerar relatórios + sales insights via prompts. R$ [redacted Tier 0]-90/mês.
- **Bling:** sem lançamento IA público em 2026 identificado.

**Implicação:** janela "ninguém faz IA em pt-BR pra PME ERP" **FECHOU em jun-ago/2025**. Diferencial passa a ser **ERP+KB-curado+Jana** triplet, não "ERP+IA".

### 12. BR vertical gráfica — Mubisys / Zênite / Calcgraf

Legacy 30+ anos. **Nenhum tem IA pública anunciada em 2026.** Janela **gráfica BR + IA + KB** segue totalmente aberta (~24+ meses lá).

---

## 5 dimensões NOVAS pro Bench v3 (além das 16+2 atuais)

| # | Dimensão | Escala 0-10 | Score oimpresso estimado | Racional |
|---|---|---|---|---|
| **19** | **ACL-aware query-time enforcement** | 0=ignora ACL; 10=permission-graph nativo nó-a-nó | **6,0 atual / 9,0 alvo** | `business_id` global scope dá tenant-level; **falta** row-level por documento. Glean=10, MS Copilot=9, Notion=7. **P0 antes RAG sair pra time MCP** |
| **20** | **Content gap / stale auto-detection** | 0=manual; 10=AI suggestion ranqueada | **3,0 atual / 9,0 alvo** | Intercom Fin=10, Slite=9, Stonly=8, Notion=7, Cowork=3. Gartner: 70% falhas chatbot = stale knowledge. **P1** ONDA 6-7 |
| **21** | **Voice/áudio PT-BR pro KB** | 0=só texto; 10=multimodal voz+foto+texto | **4,0 atual / 9,0 alvo** | Omie áudio bi-direcional. Whisper PT-BR 92%+ acc. Mateus PCP mãos sujas não digita. **P0 estratégico / P1 timing** |
| **22** | **Ambient/proactive agent** | 0=puramente reativo; 10=ambient listening + push | **3,0 atual / 8,0 alvo** | Cresta, Moveworks, Glean Agentic Engine 2. **P1-P2** ONDA 7+ |
| **23** | **Personal Graph** | 0=tudo igual; 10=ranking personalizado por behavior+role+team | **2,0 atual / 7,0 alvo** | Glean líder absoluto. Cowork tem `kb_favorites` (manual) mas zero modelagem de atividade. **P2-P3** |

### Score Bench v3 projetado

| Bench | # Dimensões | Score nosso | Score nosso (target após gaps P0-P1) |
|---|---|---|---|
| v2 (16 dim) | 16 | 9,40 | — |
| v5 (18 dim) | 18 | 9,55-9,70 | — |
| **v3 (23 dim)** | 23 | **~8,70-8,85** | **~9,30-9,40** (após fechar dim 19+20+21) |

Não é piora: é re-calibração honesta. Bench v2/v5 era **incompleto** pra estado-da-arte 2026.

---

## 3-5 gaps reais remanescentes após ONDA 1-5

### Gap 1 — ACL-aware RAG (P0)

- **Sintoma:** `kb_nodes` tem `business_id` global scope. Mas dentro de business, TODA pergunta IA retorna TODO conhecimento. Quando Felipe/Maiara/Eliana/Luiz entrarem no MCP, Eliana faz "qual nosso runway?" e o RAG vaza session log de finanças que era só pro Wagner.
- **Concorrentes que cobrem:** Glean (líder), MS Copilot M365, ServiceNow+Moveworks, Notion. Cowork NÃO cobre.
- **Impacto:** bloqueio adoção time MCP + risco LGPD.
- **Esforço:** ~12-16h. Adicionar `kb_node_visibility` enum (`public_biz` / `role:admin` / `role:author` / `user:wagner`) + filtro pre-retrieval no `KbRagService`.
- **Recomendação:** **P0 IMEDIATO**, dentro de ONDA 4.

### Gap 2 — Content gap suggestions + stale auto-detection (P1)

- **Sintoma:** `kb_node_versions` registra histórico mas ninguém detecta "esse artigo não é tocado há 6 meses + foi citado por RAG 47x = stale alto-risco".
- **Concorrentes:** Intercom Fin Content Gap Suggestions (estado-da-arte), Slite, Stonly, Notion AI hub.
- **Impacto:** Gartner 70% falhas chatbot = stale. RAG pode citar ADR superseded → resposta errada.
- **Esforço:** ~16-24h. Job cron daily `KbContentHealthScan` + `kb_search_queries` table + UI dashboard `/kb/admin/health`.
- **Recomendação:** **P1** ONDA 6-7.

### Gap 3 — Voice/áudio PT-BR pro KB (P0 estratégico / P1 timing)

- **Sintoma:** Mateus PCP mãos sujas + Larissa balcão precisam áudio. Omie já fez pra dados ERP; nós só texto.
- **Concorrentes:** Omie WhatsApp ERP, n8n RAG WhatsApp PT-BR, Whisper PT-BR 92%+ acc.
- **Impacto:** Fit pt-BR + PME + gráfica fica frágil sem isso.
- **Esforço:** ~24-36h. Integrar com `Modules/Whatsapp`: bot recebe áudio → Whisper → `KbRagService::ask` → resposta texto+resumo voltam por WhatsApp. **Alavanca Baileys 7.x já decidido.**
- **Recomendação:** **P0 estratégico / P1 timing** — entre ONDA 5 e ONDA 6.

### Gap 4 — Citações estruturadas com confiança/probabilidade (P1)

- **Sintoma:** RAG planejado retorna citações mas sem score de confiança nem flag "parafraseado vs cópia exata".
- **Concorrentes:** Glean (line-by-line), Notion AI 2026, Mem.ai. Stonly/Guru parcialmente.
- **Esforço:** ~8-12h. Já dentro do KbRagService planned: adicionar `confidence_score` + `exact_match_offset` por citação.
- **Recomendação:** **P1**, incluir desde ONDA 4. Custo trivial agora; caro retrofit depois.

### Gap 5 — Mobile responsivo gracioso <1100px (P2)

- Bench v2 catalogou. Cowork v5 mitigou via "imprimir SOP balcão".
- Larissa fora do balcão (visita cliente, conferência fornecedor) precisa.
- **Esforço:** ~16h. Layout single-column <1100px + PWA install + IndexedDB offline cache top 20 artigos favoritados.
- **Recomendação:** **P2** ONDA 6+. Importante quando 2º vertical pegar.

---

## Janela de oportunidade BR PME — RE-VALIDAÇÃO

**Estimativa anterior:** ~18-24 meses até players globais portarem.

**RE-VALIDAÇÃO 2026-05-15:**

- **Encolheu pra ~12-18 meses** porque:
  - Glean entrou LATAM via Algorithia em out/2024 (público); $150M Series F jun/2025 financia expansão internacional acelerada.
  - Notion MCP server lançou jan/2026.
  - **Omie/Conta Azul/Tiny já lançaram IA conversacional PT-BR em 2025-Q3** — não somos pioneiros conceituais.
- **Continua aberta pra:**
  - **Vertical gráfica BR** — Mubisys/Zênite/Calcgraf sem movimento IA público. Janela ~24+ meses lá.
  - **KB curado + integração ERP nativa + custo zero** — ninguém oferece este triplet em pt-BR.

**Conclusão:** estratégia "ERP+KB+IA verticalizado pra gráfica" viável **se shipar até Q4/2026**. Glean genérico não compete em gráfica/CTP/calibragem ICC.

---

## Recomendações acionáveis (5, ordem impacto × esforço)

| # | Ação | Esforço | Impacto | Quando |
|---|---|---|---|---|
| **1** | **Adicionar Gap 1 (ACL-aware RAG) como P0 dentro de ONDA 4** — bloqueia liberação RAG ao time MCP sem ele. `kb_node_visibility` enum + pre-retrieval filter. | 12-16h | **Alto** (destrava time MCP + LGPD) | ONDA 4 |
| **2** | **Promover Bench v3 com 23 dimensões** após Cowork medir 5 novas. Score honesto cai pra ~8,70 mas reflete realidade 2026. | 3-4h Cowork | **Médio** (calibra expectativa) | Próximo handoff Cowork |
| **3** | **Antecipar Gap 3 (Voice WhatsApp KB) entre ONDA 5 e 6** — alavanca Baileys 7.x já em migração + Whisper PT-BR maduro. | 24-36h | **Alto-estratégico** (diferencial real vs Glean/Notion em 12 meses) | Pós-ONDA 5 |
| **4** | **Adicionar Gap 4 (Citações com confiança) DENTRO da ONDA 4** — apenas ~8-12h marginais; retrofit depois custa 3x mais. | 8-12h | **Médio** (anti-alucinação) | ONDA 4 |
| **5** | **Re-avaliar Glean ameaça em Q4/2026** — pesquisar então se Glean tem conector Bling/Tiny/Conta Azul/Omie BR. | 2h pesquisa | **Médio** (vigilância competitiva) | Outubro/2026 |

### Surpresa estratégica

**Notion MCP server (jan/2026) muda o jogo silenciosamente.** Qualquer dev BR conecta Notion ao Claude Code/ChatGPT em pt-BR — PME contratando dev freelance pode ter KB Notion+IA em 1 semana. Nosso vetor de defesa NÃO é "ter KB-IA" (todo mundo terá) — é **"ter KB-IA-ERP-VERTICALIZADO-PT-BR-CURADO-ZERO-CUSTO"**. O quintet inteiro. Cortando qualquer um (especialmente verticalização gráfica + curadoria por Wagner), viramos commodity vs Notion+MCP.

---

## Sources consolidadas

(23 WebSearches realizadas pelo Agent D; principais URLs categorizados)

**Glean:** Series F press release · TechCrunch land grab fev/2026 · Knowledge graph guide · LATAM Algorithia · Hallucination grounding · Doubling ARR Futurum · Pricing CheckThat

**Mem.ai:** productivitystack 2026 · Notion AI comparison

**Notion AI:** eesel review 2026 · Notion MCP developers docs · 2026-01-20 release notes · Q&A introduction

**Stonly:** G2 reviews · Tools for humans

**Guru:** Pricing 2026 (xpay.sh) · Featurebase pricing

**ServiceNow+Moveworks:** Official press release dez/2025 · CX Today

**MS Copilot SharePoint:** March/April 2026 release notes · Azure ACL RBAC docs

**Atlassian Rovo:** Product page · eesel pricing breakdown · Constellation GA

**ClickUp Brain:** Pricing · Dupple 2026

**Intercom Fin:** Complete guide 2026 myaskai · Builts review

**Pinecone/Vectara:** Pinecone pricing · Enterprise RAG Atlan

**BR:** Omie WhatsApp ERP startups.com · Conta Azul IA generativa convergenciadigital · Tiny vs Bling 2026 · Spryx micro-saas-ia BR

**Trends:** AI Ready Data 2026 linesncircles · Slite knowledge mgmt panel · Hallucination crisis courts PlatinumIDS · ANPD LGPD 2026 sandbox PME

**Voice/RAG PT-BR:** SocialHub áudio WhatsApp 2026 · n8n RAG WhatsApp workflow 4827

**Field offline:** Docsie 2026 offline SOP · Salesforce mobile offline 2026

---

## Ação tomada pelo parent agent após receber dossier

- [x] Salvar dossier completo neste session log (Agent D pulou criação .md por regra harness)
- [ ] **PRÓXIMO:** SendMessage ao Agent F (RAG service) incorporando ACL-aware (Gap 1) + citações com confidence (Gap 4) ANTES dele finalizar
- [ ] **PRÓXIMO:** Editar `memory/requisitos/KB/CAPTERRA-FICHA.md` corrigindo "Guru pós ServiceNow" → "ServiceNow+Moveworks separado de Guru"
- [ ] **PRÓXIMO:** Aplicar feedback do Agent D no SCHEMA-DB-V1 — adicionar coluna `visibility` em `kb_nodes` (após Agent A entregar pra evitar conflito)
- [ ] **PRÓXIMO:** Voice/áudio WhatsApp PT-BR vira nova US-KB-XXX P0 estratégico — registrar no backlog após ONDA 5
- [ ] **PRÓXIMO:** Bench v3 (23 dimensões) entra próximo handoff Cowork — Wagner avisa [CC]

---

**Validação Wagner:** este dossier muda recomendações P0/P1 das ONDAS 4 e 5+. Re-validar antes de aceitar ADR 0149 final.
