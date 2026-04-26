# Matriz Comparativa estilo Capterra/G2 — Stack completa de agente PHP/Laravel: Vizra ADK + Prism PHP + Mem0 + LangGraph + Letta + Zep + OMEGA (2026-04-26)

> **Assunto:** stack completa pra construir Copiloto comercializável. Cobre os **3 grupos** de pacote: (A) wrapper de LLM, (B) framework de agente/orquestração, (C) memória especializada. 7 players cobrindo do PHP-nativo (Vizra/Prism) até líder benchmark (OMEGA).
> **Data:** 2026-04-26
> **Autor:** Claude (sessão `dazzling-lichterman-e59b61`) sob direção do Wagner ("compare vizra até mem0")
> **Concorrentes incluídos:** Prism PHP, Vizra ADK, Mem0, LangGraph+LangMem, Letta, Zep, OMEGA — **7 players**
> **Decisão que vai sair daqui:** definir stack completa do Copiloto: qual wrapper de LLM, qual framework de agente, qual memória especializada. Resultado materializado em **ADR 0031 (MemoriaContrato + Mem0 default)** e **ADR 0032 (Vizra ADK + Prism PHP)**.
> **Companion docs:**
> - [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md) (foco em **Camada C apenas — memória**)
> - [sistemas_memoria_oimpresso_capterra_2026_04_26.md](sistemas_memoria_oimpresso_capterra_2026_04_26.md) (Camada A — dev memory, fora deste escopo)
> **Template usado:** [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md) v1.0

---

## ⚠️ Distinção crítica antes de tudo (3 grupos)

Frameworks de agente IA não são todos iguais — operam em camadas diferentes:

| Grupo | O que faz | Players | Equivalente humano |
|---|---|---|---|
| **A — Wrapper de LLM (low-level)** | Fala com provider (OpenAI/Anthropic/Gemini/Ollama). Streaming, tools, structured output | Prism PHP, openai-php/laravel | "API client tipado" |
| **B — Framework de agente (orquestração)** | Sessions, agent loop, tools registry, traces, eval | Vizra ADK, LangGraph, Letta, Vercel AI SDK | "Estado da conversa" |
| **C — Memória especializada (long-term)** | Semantic facts, vector search, graph, temporal validity | Mem0, Zep/Graphiti, LangMem | "Lembrar de você ano que vem" |

**Stack típica madura:** A + B + C (3 dependências distintas, cada uma boa no que faz).

**Estado do Copiloto hoje:**
- A: tenta usar `OpenAI\Laravel\Facades\OpenAI` mas pacote **NÃO está em composer.lock** → quebra fora de dry_run
- B: lógica caseira em `ChatController` + `OpenAiDirectDriver` — sem traces, sem assertions, sem registry de tools
- C: zero (Tier 1 de 10 — ver companion comparativo de Camada C)

---

## 1. TL;DR (5 frases)

1. **PHP/Laravel ganhou em 2025-2026 dois frameworks AI fortes: Prism PHP (wrapper LLM low-level, 3+ providers, tools, structured output) e Vizra ADK (framework de agente em cima do Prism, com Eloquent sessions/messages/traces, OpenAI-compatible API e dashboard).** Ambos OSS MIT, mantidos ativamente.
2. **Vizra ADK não compete com Mem0/Zep** — é grupo B (orquestração), enquanto Mem0/Zep são grupo C (memória especializada). Stack completa = Prism (A) + Vizra ADK (B) + Mem0 (C).
3. **Vizra ADK ainda não tem benchmark público de memória** (LongMemEval) e a memória nativa dele é **chat history flat em Eloquent** — equivalente Tier 1 do Copiloto atual. Pra Tier 6+ continua precisando integrar Mem0/Zep externamente.
4. **Prism PHP resolve o problema imediato** do `OpenAi\Laravel\Facades\OpenAI` quebrado: providers múltiplos com 1 interface, fácil trocar de OpenAI pra Claude/Gemini sem reescrever, **2-3 sprints** pra migrar driver atual.
5. **O dilema:** ir all-in Vizra ADK (substitui ChatController+OpenAiDirectDriver+OpenAiLaravelFacade num movimento, custo médio 4-6 sprints, ganha dashboard/traces/eval) vs ficar com código caseiro + só adotar Prism+Mem0 (custo baixo 2-3 sprints, mas perde infra de dashboard/eval que vai precisar mais cedo ou mais tarde).

---

## 2. Concorrentes incluídos (7 players cobrindo 3 grupos)

| Nome | URL | Grupo | Linguagem | Tier de mercado | Observação |
|---|---|---|---|---|---|
| **Prism PHP** | [prismphp.com](https://prismphp.com/) · [github](https://github.com/prism-php/prism) | **A** (wrapper LLM) | PHP/Laravel | Líder PHP | Anthropic+OpenAI+Ollama+Mistral+Bedrock built-in. Tools, multi-modal, structured output, testing fakes. Mantido ativamente em 2026 |
| **Vizra ADK** | [vizra.ai](https://vizra.ai/) · [github](https://github.com/vizra-ai/vizra-adk) · [packagist](https://packagist.org/packages/vizra/vizra-adk) | **B** (framework agente) | PHP/Laravel | Líder PHP/Laravel | Aaron Lumsden criador. Memória persistente Eloquent, web dashboard, OpenAI-compatible API, streaming, 20+ assertions, Vizra Cloud (managed) |
| **Mem0** | [mem0.ai](https://mem0.ai/) · [arXiv](https://arxiv.org/abs/2504.19413) | **C** (memória) | Python+TS (REST utilizável de PHP) | Líder adoção C | 21 frameworks integrados, -91% latência vs full-context, 3 hosting models |
| **LangGraph + LangMem** | [docs.langchain.com](https://docs.langchain.com/oss/python/langgraph/memory) | **B + C híbrido** | Python | Líder ecossistema | 3 tipos cognitivos × 2 escopos. Checkpointers ≠ memory |
| **Letta** (ex-MemGPT) | [letta.com](https://letta.com/) | **B + C híbrido** | Python | Premium long-running | 3 tiers OS-inspired (core/archival/recall). LongMemEval **83.2%** |
| **Zep / Graphiti** | [getzep.com](https://www.getzep.com/) | **C** (memória) | Python | Líder temporal | Temporal knowledge graph (validity windows). LongMemEval **71.2%** geral / **63.8%** temporal |
| **OMEGA** | [omegamax.co/compare](https://omegamax.co/compare) | **C** (memória) | Python managed | Líder benchmark | LongMemEval **95.4%** (GPT-4.1) — champion atual |

**3 grupos:**
- **PHP-nativo:** Prism PHP, Vizra ADK
- **Python-first com REST disponível:** Mem0, Zep, LangGraph, Letta
- **Managed-only:** OMEGA

---

## 3. Matriz Feature-by-Feature (40 features em 6 categorias)

**Legenda:** ✅ Tem completo · 🟡 Tem básico/limitado · ❌ Não tem · ❓ Não confirmado · N/A não se aplica

### Categoria 1 — Wrapper de LLM (Grupo A)

| Feature | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| Provider OpenAI | ✅ | ✅ (via Prism) | N/A | ✅ | ✅ | N/A | N/A |
| Provider Anthropic | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Provider Gemini | 🟡 | ✅ | N/A | ✅ | 🟡 | N/A | N/A |
| Provider Ollama (local) | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Switch entre providers sem reescrever app | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Streaming nativo | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Multi-modal (text+image) | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Structured output (schema validado) | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |

### Categoria 2 — Framework de agente (Grupo B)

| Feature | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| Agent loop (think → act → observe) | 🟡 | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Tools registry (registrar funções como tools) | ✅ | ✅ | N/A | ✅ | ✅ | N/A | N/A |
| Sessions persistentes (Eloquent ou similar) | ❌ (low-level) | ✅ (sessions/messages tables) | N/A | ✅ | ✅ | N/A | N/A |
| Traces (debug visual da execução) | 🟡 (logs) | ✅ (Vizra Cloud + dashboard) | N/A | ✅ (LangSmith) | ✅ | N/A | N/A |
| Multi-agent orquestração | ❌ | 🟡 | N/A | ✅ | ✅ | N/A | N/A |
| Eval & assertions builtin | 🟡 (testing fakes) | ✅ (20+ assertions) | N/A | ✅ | ✅ | N/A | N/A |
| OpenAI-compatible API exposta | ❌ | ✅ | N/A | 🟡 | ✅ | N/A | N/A |
| Web dashboard | ❌ | ✅ | ✅ (managed) | ✅ (LangSmith) | 🟡 | ✅ | ❓ |

### Categoria 3 — Memória especializada (Grupo C)

| Feature | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| Chat history flat persistido | ❌ | ✅ (Eloquent) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Vector embeddings + similarity | ❌ | 🟡 (depende de plugin) | ✅ (19 stores) | ✅ | ✅ | ✅ | ✅ |
| Knowledge graph (entities + relations) | ❌ | ❌ | ✅ (Mem0g) | 🟡 | 🟡 | ✅ (Graphiti) | ✅ |
| Temporal validity (valid_from/until) | ❌ | ❌ | 🟡 | 🟡 | 🟡 | ✅ (diferencial) | ✅ |
| Memory write/update/forget | ❌ | 🟡 (CRUD Eloquent) | ✅ | ✅ | ✅ (LLM-driven) | ✅ | ✅ |
| Hot path vs background updates | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❓ |
| Conflict resolution entre fatos | ❌ | ❌ | ✅ | 🟡 | ✅ | ✅ (temporal) | ✅ |
| Multi-tier (core/archival/recall) | ❌ | ❌ | ✅ (3 scopes) | 🟡 | ✅ (3 tiers) | 🟡 | ✅ |
| LongMemEval score público | ❓ | ❓ | ~67% | ❓ | **83.2%** | 71.2% | **95.4%** |

### Categoria 4 — Integração & operação

| Feature | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| **PHP/Laravel-nativo** | ✅ | ✅ | ❌ (Python+TS only) | ❌ | ❌ | ❌ | ❌ |
| Self-hosted OSS | ✅ (MIT) | ✅ (MIT) | ✅ | ✅ | ✅ | ✅ (Graphiti) | ❌ |
| Managed cloud | ❌ | ✅ (Vizra Cloud) | ✅ | 🟡 (LangSmith) | ✅ | ✅ | ✅ |
| MCP support | ❓ | ❓ | ✅ (local MCP) | ❓ | ❓ | ❓ | ❓ |
| REST API (utilizável de PHP) | N/A (já é PHP) | ✅ | ✅ | 🟡 (LangServe) | ✅ | ✅ | ✅ |
| Composer/Packagist | ✅ (`prism-php/prism`) | ✅ (`vizra/vizra-adk`) | ❌ | ❌ | ❌ | ❌ | ❌ |
| Migrations Laravel | N/A | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Artisan commands | N/A | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Categoria 5 — Performance & escala

| Feature | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| Latência típica (p95) | <100ms wrapper overhead | <200ms agent loop | -91% vs full-context | ~50-200ms | ~100ms | ~30ms | ❓ |
| Custo de tokens vs full-context | 0% (wrapper) | 0-10% (com sessions) | -91% | -50% típico | -50% | -60% | ❓ |
| Escalabilidade (M+ sessões) | ❓ | 🟡 (Eloquent) | ✅ | 🟡 (depende store) | 🟡 | ✅ | ✅ |

### Categoria 6 — Específico oimpresso

| Feature | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| Roda na stack atual (L13.6 + PHP 8.4) | ✅ | ✅ | ✅ (REST) | ✅ (REST) | ✅ (REST) | ✅ (REST) | ✅ (REST) |
| Multi-tenant via business_id | 🟡 (manual) | 🟡 (manual) | 🟡 (custom user_id) | 🟡 | 🟡 | 🟡 | ❓ |
| Funciona com `session('user.business_id')` UltimatePOS | ✅ | ✅ | ✅ (via wrapper) | ✅ | ✅ | ✅ | ✅ |

**Total:** 40 features em 6 categorias. ✅

---

## 4. Notas estimadas (escala G2/Capterra 1-5)

| Critério | Prism PHP | Vizra ADK | Mem0 | LangGraph | Letta | Zep | OMEGA |
|---|---|---|---|---|---|---|---|
| **Ease of Use (pra dev PHP)** | 5 | 5 | 3 (REST) | 2 (Python) | 2 | 3 (REST) | 2 |
| **Customer Service / docs** | 4 | 4 (criador ativo no DEV) | 4 | 5 (LangChain) | 4 | 4 | 3 |
| **Features (no escopo do grupo)** | 4 | 4 | 5 (C) | 5 (B+C) | 5 | 5 (C) | 5 (C) |
| **Value for Money** | 5 (OSS MIT) | 5 (OSS MIT + Cloud opt) | 4 (managed paga) | 5 (OSS) | 4 | 4 | 2 (premium) |
| **Específico pro nicho oimpresso (PHP/Laravel)** | 5 | 5 | 2 | 1 | 1 | 2 | 1 |
| **Score total (média)** | **4.6** ⭐ | **4.6** ⭐ | **3.6** | **3.6** | **3.2** | **3.6** | **2.6** |

**Top 3 por score (PHP-friendly):** Prism PHP (4.6) e Vizra ADK (4.6) empatam, seguidos de Mem0/LangGraph/Zep (3.6).

> **Atenção:** o ranking inverte se a métrica é **profundidade de memória runtime**. Aí Vizra ADK cai pra Tier 1-2 (chat history flat); Mem0/Zep/Letta/OMEGA dominam. Por isso a stack completa é **Vizra ADK + Mem0**, não escolher um dos dois.

---

## 5. Top 3 GAPS críticos (Copiloto vs stack ideal Vizra+Prism+Mem0)

### GAP 1 — `OpenAi\Laravel\Facades\OpenAI` quebrado, sem fallback

**O que falta:** O driver atual [OpenAiDirectDriver.php](Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php) usa `OpenAI\Laravel\Facades\OpenAI` mas o pacote **não está no `composer.lock`** — Copiloto **só roda em `dry_run`**. Mudar pra Prism PHP resolve com 1 swap (Prism é multi-provider, fluent interface).
**Esforço:** Baixo (1-2 sprints) — `composer require prism-php/prism` + reescrever `OpenAiDirectDriver` como `PrismDriver` (já que Prism abstrai OpenAI/Anthropic/Gemini/Ollama).
**Impacto se não fechar:** Copiloto fica em fixtures pra sempre. Bloqueio direto pra US-COPI-* virar produto vendável.

### GAP 2 — Sem registry de tools nem agent loop estruturado

**O que falta:** O Copiloto deveria expor `getBusinessSnapshot()`, `criarMeta(...)`, `consultarApuracao(...)` como **tools** invocáveis pelo LLM. Hoje [ChatController@send](Modules/Copiloto/Http/Controllers/ChatController.php) chama `responderChat()` direto sem agent loop. Vizra ADK trata isso como first-class (registry de tools, agent loop think→act→observe, traces visuais).
**Esforço:** Médio (3-5 sprints) — adotar Vizra ADK como base do `ChatController` + reescrever fluxo como Vizra Agent + registrar 5-10 tools iniciais. Vizra trabalha **em cima** do Prism, então Prism (GAP 1) precede.
**Impacto se não fechar:** Cada nova capacidade do Copiloto exige código procedural feio. Não escala pra 10 tools, vira spaghetti. Sem traces, debugar é por logging.

### GAP 3 — Memória runtime Tier 1 (mesmo problema do comparativo Camada C)

**O que falta:** já documentado em [companion comparativo](copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md). Vizra ADK **não resolve** sozinho — só dá chat history flat. Pra Tier 6+, **Mem0 REST adapter** continua sendo a recomendação.
**Esforço:** Médio (3-4 sprints, depois de GAPs 1 e 2). Mesmo ranking de US-COPI-MEM-001 a 008 do Anexo A do companion.
**Impacto se não fechar:** Copiloto não personaliza, perde Larissa após 1 semana de uso. Inviabiliza tese de "ERP gráfico com IA" (ADR 0026).

---

## 6. Top 3 VANTAGENS reais (stack PHP-nativa madura)

### V1 — Composer install em 1 comando, sem container Python

**Por que é vantagem:** stack Python (LangGraph/Letta/Mem0 self-hosted) exige container Python ao lado do Laravel — duplica deploy, monitoring, scaling. **Vizra ADK + Prism = `composer require ... && php artisan vendor:publish --tag=...`**, zero infra extra. Em produção Hostinger compartilhada, isso é **deal-breaker** — Python self-hosted nem é viável no plano atual.
**Como capitalizar:** Vizra ADK como driver default (ADR 0032). Mem0 só como REST (não self-hosted), pra preservar simplicidade do deploy.
**Risco de erodir:** Médio — se demanda crescer e Hostinger virar limitação, vamos precisar VPS dedicado + container Python. Mas isso é problema de 2027.

### V2 — Eloquent models nativos pra sessions/messages/traces

**Por que é vantagem:** Vizra ADK gera tabelas Laravel (`vizra_sessions`, `vizra_messages`, `vizra_traces`) com Eloquent models. Conecta direto com `business_id` do UltimatePOS via Observer ou Scope. Mem0/Zep precisam **wrapper custom** pra integrar com schema multi-tenant.
**Como capitalizar:** stack Vizra ADK + Tenant Scope global no models, pra que toda query auto-filtre por `business_id`. Documentar em ADR.
**Risco de erodir:** Baixo — Eloquent é estável.

### V3 — Aaron Lumsden (criador Vizra) ativo na comunidade Laravel

**Por que é vantagem:** Vizra ADK é projeto novo (2026) mas mantido por dev visível ([blog](https://aaronlumsden.com/), DEV.to, Laravel-News). Issues respondidas. Roadmap público. Compare com `openai-php/laravel` (abandonado meses atrás — causa do nosso GAP 1).
**Como capitalizar:** abrir issue/PR contribuindo de volta — features que faltam (multi-tenant scope, ex.) podem ser pull requests, não forks.
**Risco de erodir:** Médio — projetos solo morrem. Estratégia mitigação: usar Vizra atrás de interface (ADR 0032) — se sumir, troca por outro framework B.

---

## 7. Posicionamento sugerido (4 caminhos)

| Caminho | Tese curta | Veredito |
|---|---|---|
| **A — All-in Vizra ADK + Prism PHP + Mem0 REST** | stack 3-camadas; Vizra orquestra, Prism fala LLM, Mem0 lembra. 4-7 sprints | ✅ **Recomendado** — combina ADRs 0031 e 0032 |
| **B — Só Prism PHP + memória caseira** | Prism resolve GAP 1 (~2 sprints). Memória continua Tier 1 | 🟡 minimum viable; deixa GAP 2-3 abertos |
| **C — Construir tudo do zero (sem Vizra/Prism/Mem0)** | Controle total, zero deps | ❌ reinventa roda. 12-15 sprints. Sem dashboard, sem traces. Não vale |
| **D — Aceitar Tier 1, focar features comerciais ADR 0026** | Pula IA, foca PricingFpv/CT-e | 🟡 contradiz "ERP com IA" — mas se faturamento estiver crítico, defensável |

**Recomendado: A — All-in Vizra ADK + Prism PHP + Mem0 REST.**

**Frase de posicionamento:**
> *"Stack completa de agente PHP/Laravel: Prism fala com LLM, Vizra orquestra agente+sessions+traces+eval, Mem0 (REST) faz memória semântica de longo prazo. Cada camada é abstraída por interface trocável. Composer install + 1 chave Mem0 = Tier 6-7 LongMemEval."*

---

## 8. Math do custo (caminho A vs alternativas)

Pressupostos:
- 1 sprint Wagner = ~80h código PHP
- Vizra ADK + Prism = OSS MIT, custo R$0/mês recorrente
- Mem0 managed: ~$25-300/mês dependendo de volume

**Caminho A (recomendado):**
- Sprint 1: `composer require prism-php/prism vizra/vizra-adk` + migrations + `MemoriaContrato` interface
- Sprint 2: reescrever `OpenAiDirectDriver` como `PrismDriver` em cima de Vizra Agent
- Sprint 3: registrar 5-10 tools (snapshot, metas CRUD, apuracao) + agent loop
- Sprint 4-5: integrar Mem0 REST como `Mem0RestDriver` da `MemoriaContrato`
- Sprint 6: traces + dashboard Vizra Cloud (opt) + eval/assertions
- Sprint 7: stress test, multi-tenant scope, LGPD opt-out
- **Total: 7 sprints** (~9-10 semanas no ritmo Wagner)
- **Custo recorrente:** $25-300/mês Mem0 + $0/mês Prism+Vizra
- **Tier final:** 6-7 (LongMemEval ~67% via Mem0; Vizra dá traces/eval gratuitamente)

**Caminho B (Prism + caseiro):**
- Sprints 1-2: Prism + reescrever driver
- **Total: 2 sprints**
- Continua Tier 1 em memória. Resolve só GAP 1.

**Caminho C (zero):**
- 12-15 sprints. Tier 3-4. Sem dashboard. Não vale.

**Caminho D (aceitar):**
- 0 sprints. Tier 1. Aposta que features ADR 0026 (PricingFpv/CT-e) compensam ausência de IA real.

**ROI de A vs B:**
- A custa **+5 sprints** mas entrega Tier 6-7 + dashboard/traces.
- B é minimum viable que destrava produto mas mantém GAPs 2 e 3 abertos.
- **Decisão sugerida:** ir pra A em fases. **Sprint 1-2 = caminho B materializado** (entrega imediata: Copiloto sai de fixtures). Depois decidir A vs ficar em B baseado em feedback de Larissa.

**Assunção não validada:** "Vizra ADK aguenta 100+ businesses no `vizra_sessions`" — sem benchmark real. Validar antes de sprint 4.

---

## 9. Recomendação concreta

### 3 ações prioritárias pra próximos 6 meses (em ordem)

1. **Materializar caminho B — Prism PHP swap (sprints 1-2).** Resolve GAP 1, destrava Copiloto pra deixar `dry_run`. **Materializado em ADR 0032.**
2. **Definir interface `MemoriaContrato`** + driver `Mem0RestDriver` (sprint 3-4). Permite trocar driver de memória depois sem reescrever app. **Materializado em ADR 0031.**
3. **Adotar Vizra ADK como camada de orquestração** (sprints 5-7). Substitui ChatController custom + ganha registry de tools + traces + eval/assertions. Decisão final do caminho A.

Máximo 3.

### O que NÃO fazer agora

- ❌ NÃO adotar LangGraph/Letta/Zep direto — exigem container Python que não temos.
- ❌ NÃO continuar usando `OpenAi\Laravel\Facades\OpenAI` "esperando upstream voltar" — projeto abandonado, recipe pra dívida técnica.
- ❌ NÃO construir vector store nem agent loop do zero — Vizra ADK + Mem0 cobrem isso e mais.
- ❌ NÃO mergear ADR 0031 e 0032 num só — são decisões separadas (memória vs orquestração) com possibilidade de evoluir independente.

### Métrica de fé (90 dias)

> *"Se em 90 dias (até 2026-07-25) Prism estiver em produção rodando o Copiloto sem `dry_run` E (idealmente) Vizra ADK estiver alimentando ChatController E Mem0 REST estiver lembrando de pelo menos 1 fato por user, **confirma caminho A**. Se Prism sozinho (caminho B) for suficiente — Larissa não perceber falta de memória —, **fica em B**. Se nada disso destravar uso real, **pivota pra D** (aceitar Tier 1 e focar comercial)."*

Gatilho de pivot mensurável: número de turnos de conversa do Copiloto em produção em 90d. <50 = D (Copiloto não tem demanda); 50-500 = B (suficiente); >500 = A (justifica investimento full).

---

## 10. Sources

### Externas
- [Vizra ADK GitHub](https://github.com/vizra-ai/vizra-adk)
- [Vizra.ai homepage](https://vizra.ai/)
- [Vizra ADK Architecture docs](https://docs.vizra.ai/concepts/architecture)
- [Laravel News: Vizra ADK feature](https://laravel-news.com/vizra-adk)
- [Aaron Lumsden DEV.to: Why I Built Vizra ADK](https://dev.to/aaronlumsden/why-i-built-an-ai-agent-framework-for-laravel-and-why-php-deserves-ai-too-3il3)
- [Aaron Lumsden personal site](https://aaronlumsden.com/)
- [Prism PHP homepage](https://prismphp.com/)
- [Prism PHP GitHub](https://github.com/prism-php/prism)
- [Laravel News: Prism is an AI Package for Laravel](https://laravel-news.com/prism-ai-laravel)
- [Mem0: Production-Ready AI Agents (arXiv 2504.19413)](https://arxiv.org/abs/2504.19413)
- [Mem0 GitHub](https://github.com/mem0ai/mem0)
- [State of AI Agent Memory 2026 (Mem0)](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [LangChain Memory docs](https://docs.langchain.com/oss/python/langgraph/memory)
- [Atlan: Best AI Agent Memory Frameworks 2026](https://atlan.com/know/best-ai-agent-memory-frameworks-2026/)
- [DEV: 5 AI Agent Memory Systems Compared (2026 Benchmark)](https://dev.to/varun_pratapbhardwaj_b13/5-ai-agent-memory-systems-compared-mem0-zep-letta-supermemory-superlocalmemory-2026-benchmark-59p3)
- [OMEGA Compare](https://omegamax.co/compare)

### Internas
- [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md)
- [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md) — companion (foco Camada C)
- [memory/decisions/0026-posicionamento-erp-grafico-com-ia.md](../decisions/0026-posicionamento-erp-grafico-com-ia.md)
- [memory/decisions/0031-memoriacontrato-mem0-default.md](../decisions/0031-memoriacontrato-mem0-default.md) (criado nesta sessão)
- [memory/decisions/0032-vizra-adk-prism-php-orquestracao.md](../decisions/0032-vizra-adk-prism-php-orquestracao.md) (criado nesta sessão)
- [Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php](../../Modules/Copiloto/Services/Ai/OpenAiDirectDriver.php)

---

## Checklist (template)

- [x] TL;DR cabe em 5 frases
- [x] Mín. 4 concorrentes incluídos (7)
- [x] 30+ features na matriz (40)
- [x] Notas escala 1-5 (todas estimadas — sem reviews G2/Capterra públicos pra Vizra/Prism/Mem0 nesse vertical)
- [x] **Exatamente 3 GAPS e 3 VANTAGENS**
- [x] **Mín. 3 caminhos de posicionamento** com veredito (4 caminhos)
- [x] Math (adaptado: custo de implementar caminho A vs B)
- [x] **3 ações prioritárias** em ordem
- [x] **Métrica de fé** com prazo (90d) e gatilho de pivot
- [x] Sources literais com URL (15 externas + 5 internas)
- [x] Companion docs linkados no frontmatter

**Score: 11/11 ✅**
