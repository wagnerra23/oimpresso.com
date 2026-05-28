# Reavaliação arquitetural do oimpresso à luz do Claude Opus 4.8 (1M context)

> **Sessão:** 2026-05-28 · especialista `estado-da-arte`
> **Escopo:** meta-arquitetura de **como o Claude trabalha no projeto** (skills, hooks, brief, MWART, ADRs de processo, tiered cost). **NÃO** features do ERP.
> **Método:** Fase 1 pesquisa limpa (web, sem ler memory/) → Fase 2 comparação com o que existe → Fase 3 gaps + roadmap.

---

## 1. Tese central

O Claude Opus 4.8 com **1M de contexto, instruction-following muito melhor, agentic loop nativo e subagentes/compaction de fábrica** invalida ou atenua **três premissas estruturais** sobre as quais boa parte da máquina de governança do oimpresso foi erguida em maio/2026: (a) contexto é escasso e caro — daí o brief de 3k tokens e o `mcp-first`; (b) o modelo "esquece" e desobedece — daí a multiplicação de hooks bloqueadores e skills Tier A always-on; (c) precisamos rotear pra modelos baratos pra sobreviver ao custo — premissa do tiered cost. Nenhuma dessas premissas é falsa hoje, mas **as três encolheram de "lei física" para "otimização de margem".** O que era muleta de modelo fraco virou peso morto de manutenção. **A direção certa não é jogar fora — é rebaixar de `block` pra `advisory`, de `always-on` pra `auto-trigger`, e de "enforce mecânico" pra "confiar + medir drift".** O que NÃO muda: nada que proteja tenant/LGPD/append-only — isso nunca foi sobre limitação de modelo.

Nuance que a própria Anthropic vive em 2026: o anúncio do 1M GA diz "summarization e context clearing não são mais necessários", mas o paper *Effective harnesses for long-running agents* diz "compaction isn't sufficient — curated context still wins". **Conciliação: com 1M dá pra carregar muito mais, mas curar continua valendo — só que o orçamento de curadoria subiu 5x.** O oimpresso curou pra um teto de 200k; está com a régua errada.

---

## 2. Pesquisa — estado-da-arte 2026 (Fase 1, sem contaminação)

5 referências, mecanismo concreto:

| Player / fonte | Como resolve o problema (mecanismo) | Por que é referência |
|---|---|---|
| **Anthropic — 1M context GA (Opus/Sonnet 4.6+, mar/2026)** | $5/$25 por M tokens em **toda** a janela (sem multiplier de long-context); MRCR v2 78,3% @1M. "O trabalho de engenharia, summarization lossy e context clearing que long-context exigia não é mais necessário." | Define o piso econômico: não há mais penalidade por carregar contexto grande. |
| **Anthropic — Effective harnesses for long-running agents (nov/2025)** | Two-agent (Initializer + Coding), filesystem-as-memory (feature-list JSON + progress file + git history), "compaction isn't sufficient", guardrails contra one-shotting. | Contrapeso ao "carregue tudo": curadoria estruturada (arquivo, não prosa) ainda ganha em sessões longas. |
| **Anthropic — Claude Agent SDK / Managed Agents (Q2/2026)** | Subagent orchestration **nativa** (lead delega a especialistas em paralelo, contexto isolado, resultado volta ao lead), **compaction automática**, agent loop (gather→act→verify), skills = slash commands unificados. | Orquestração multi-agente e compaction deixaram de ser "build your own". |
| **Anthropic — hooks vs CLAUDE.md (best practices 2026)** | CLAUDE.md é **advisory** (~80% de aderência); hooks são **determinísticos/100%**. Regra: só vira hook o que precisa rodar *sempre sem exceção* (format, lint, security). Comportamento → CLAUDE.md/skill. | Critério canônico pra decidir o que merece ser hook bloqueador. |
| **Pricing / model routing 2026 (CloudZero, Finout, BenchLM)** | Tiers Haiku $1/$5 · Sonnet $3/$15 · Opus $5/$25; cache -90%, batch -50%. "A pergunta não é se Opus vale $25 a mais — é se 1 run Opus bate 2-5 retries Sonnet." Roteamento vale por **custo por unidade resolvida**, não por tarifa de token. | Mostra quando rotear ainda compensa (runtime 24/7) e quando não (trabalho senior pontual). |

**Dimensões que emergiram:** (1) contexto — curar vs carregar; (2) enforcement — block vs advisory; (3) orquestração — manual vs nativa; (4) custo — rotear vs Opus direto; (5) memória — prosa resumida vs filesystem estruturado.

---

## 3. Comparação — estado-da-arte × oimpresso hoje (Fase 2)

Tabela mestra. CONSOLIDAR = manter como está / unificar; SIMPLIFICAR = reduzir peso; EVOLUIR = trocar por mecanismo novo; APOSENTAR = remover.

| # | Decisão de governança | Por que foi criada | Premissa de modelo (2026-05) | Estado com Claude 4.8 | Veredito |
|---|---|---|---|---|---|
| 1 | **Daily Brief L7 ~3k tokens** ([0091](../decisions/0091-daily-brief.md)/[0097](../decisions/0097-brief-model-gpt4o-mini-supersede-parcial-0091.md)) | "15-30k tokens/sessão de onboarding × 30 sessões/dia = 500k desperdício" | Janela ~200k → contexto é escasso | Com 1M, 30k de onboarding é 3% da janela. Brief vira **conveniência de UX** (estado consolidado pro humano), não economia crítica. Custo $0,72/mês é trivial. | **SIMPLIFICAR** — manter como produto-pro-Wagner, soltar a régua de 3500 tokens (pode ser 8-10k, mais rico) |
| 2 | **`mcp-first` Tier A** (Read/Glob/Grep → warning) | Forçar tool MCP barata antes de filesystem caro | Tokens de exploração caros | Exploração de filesystem agora é barata e o agentic loop do 4.8 já faz busca eficiente. O warning vira ruído. | **SIMPLIFICAR** — rebaixar pra advisory puro / Tier B |
| 3 | **7+ skills Tier A always-on** (brief-first, mcp-first, multi-tenant, commit-discipline, mwart-process, wagner-protocol-enforce, +) | "Não confiar em 'lembrar'" — instruction-following imperfeito ([0095](../decisions/0095-skills-tiers-convencao-interna.md), [0168](../decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md)) | Modelo esquece regra entre turnos | 4.8 segue instrução bem melhor; 7 skills × 500-3k tokens no SessionStart é overhead fixo. Algumas são genuinamente Tier 0 (multi-tenant); outras são lembrete que o modelo já internaliza do CLAUDE.md. | **SIMPLIFICAR** — manter 2-3 Tier 0 reais; rebaixar resto pra auto-trigger (description) |
| 4 | **~12 hooks PreToolUse Write/Edit/Bash** (8 em Write/Edit + 5 Bash) | Defesa em profundidade contra erro do modelo | Modelo erra path, desobedece, alucina claim | Mistura legítima: `block-automem`, `block-destructive`, `pii-redactor`, `block-routes-string-legacy`, `block-bom`, `block-merge-markers` são determinísticos-corretos (devem ficar block). `charter-validate`, `modulo-preflight`, `mcp-first` JÁ são advisory. `block-claim-without-evidence` é semântico — frágil como regex. | **CONSOLIDAR os determinísticos** + **APOSENTAR/advisory os semânticos** |
| 5 | **MWART 5 fases + 3 camadas enforcement** ([0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md)) | Bugs recorrentes em migração Blade→Inertia; "falhas não são aceitáveis, sem 2 caminhos" | Modelo repete os mesmos 6 gotchas | Os gotchas são técnicos (Persistent Layout, Ziggy, format_date +3h, tokens vs cor crua) — 4.8 com GOTCHAS.md em contexto evita quase todos. As 5 fases continuam **bom processo de engenharia** (flag, baseline test, canary), mas as 3 camadas de enforcement (skill+hook+CI) pra mesma regra é redundância de 2026-05. | **SIMPLIFICAR** — manter 5 fases como playbook; colapsar enforcement pra 1 camada (CI gate) + GOTCHAS.md em contexto |
| 6 | **Tiered cost — ADS Brain A/B** ([0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) princ. 2, ads-decision-flow) | Sobreviver a custo de modelo caro 24/7 | Opus/Sonnet caros pra rodar em loop | **Distinguir dois "tiered cost":** (a) ADS runtime do PRODUTO (Ollama local $0 vs Sonnet on-demand pro WhatsApp/Cobradora) — **continua válido**, é feature paga 24/7 ([0145](../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md)); (b) routing de COMO Claude trabalha — aqui Opus 4.8 direto + cache 90% já é barato pro volume do projeto. | **MANTER ADS produto · CONSOLIDAR meta-routing** (não rotear o trabalho do Claude) |
| 7 | **Brief gerado por gpt-4o-mini** ([0097](../decisions/0097-brief-model-gpt4o-mini-supersede-parcial-0091.md)) | -92% custo vs Sonnet | Anthropic caro pra geração estruturada | Continua o trade-off certo (geração estruturada barata). Não é muleta de modelo fraco — é economia legítima de OPEX. | **CONSOLIDAR** (intocado) |
| 8 | **`charter-first` / charter-validate** (dormente S4) | Contratos vivos lidos no diff; SPECs apodrecem | Contexto escasso → não dá pra reler spec inteira | Premissa "não cabe a spec inteira" caiu com 1M. Charter ainda tem valor como **contrato explícito**, mas o medo que o motivou (contexto caro) sumiu. Já está advisory/dormente — ok. | **SIMPLIFICAR** — manter como doc opcional, não promover a Tier A bloqueante |
| 9 | **PROTOCOLO WAGNER SEMPRE — 11 regras Tier A** ([0168](../decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md)) | "Não é justo eu sempre pedir a mesma coisa" — modelo esquece preferências | Sem memória persistente de preferência cross-session | 4.8 segue um protocolo bem-escrito com muito mais fidelidade. As regras são boas; o **mecanismo de 3 artefatos** (PROTOCOLO+skill+agent) pra cada regra é o overhead. R1 (smoke real) e R10 (aprovação) são Tier 0; resto o modelo respeita lendo 1 doc. | **SIMPLIFICAR** — 1 doc de protocolo em contexto; aposentar a tripla redundância por regra |
| 10 | **~200 ADRs, meta ≤30 ativas** ([0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) métricas) | Single source of truth append-only | Contexto escasso → precisa decisions-search pra não carregar tudo | O append-only e o histórico são **corretos e atemporais**. Mas a meta "≤30 ativas" foi calibrada pra um modelo que não conseguia navegar 200 docs — 4.8 navega bem via search. O drift (200 vs meta 30) não é mais crise de contexto, é higiene documental. | **CONSOLIDAR append-only · SIMPLIFICAR a meta numérica** (medir relevância, não contagem) |
| 11 | **`force-r12-closing-signal` / `memory-pending` / Stop hooks** | Garantir handoff/encerramento ([0130](../decisions/0130-handoff-append-only-mcp-first.md)) | Modelo "esquece" de fechar sessão | 4.8 é melhor em fechar loop. Hooks de Stop/closing-signal viram cinto-e-suspensório. | **SIMPLIFICAR** — advisory |
| 12 | **Orquestração multi-agente manual** (coordenador-paralelo, spawn manual de subagentes) | Não havia orquestração nativa | SDK sem subagentes/compaction | Agent SDK 2026 traz subagent orchestration + compaction nativos. Parte do scaffolding manual tem alternativa de fábrica. | **EVOLUIR** — migrar pro padrão nativo onde aplicável |
| 13 | **Governance DriftChecker framework** ([0216](../decisions/0216-governance-drift-framework-driftchecker-plugavel.md), 0217-0223) | Unificar 14 checkers dispersos | (não é sobre limitação de modelo) | Padrão Inventory→Check→Score→Remediate→Audit é estado-da-arte (Backstage/OPA/Drata). Independe do modelo. | **CONSOLIDAR** (intocado — recém-feito e correto) |
| 14 | **Recalibração 10x IA-pair** ([0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) | Estimates pré-IA irreais | Velocidade observada 15-20x | 4.8 acelera ainda mais o codável. O fator pode estar **conservador** hoje. | **EVOLUIR** — revisitar fator (10x→15x?) com telemetria nova |

---

## 4. Top 5 simplificações (rankeadas por impacto × esforço)

> Esforço em IA-pair (ADR 0106: 10x humano). Todas são meta-arquitetura, baixo risco de produto.

| # | Simplificação | Impacto | Esforço IA-pair | Pré-req |
|---|---|---|---|---|
| **1** | **Triar os ~12 hooks: classificar cada um em `block` (determinístico-correto) vs `advisory` (semântico/lembrete) e rebaixar os semânticos.** Manter block: automem, destructive, pii-redactor, routes-string-legacy, bom, merge-markers, mwart F1-gate. Rebaixar a advisory: claim-without-evidence, mcp-first (já é), charter-validate (já é), modulo-preflight (já é), closing-signal. | **Alto** — tira atrito de toda sessão, reduz falsos bloqueios | ~1,5h | Nenhum |
| **2** | **Rebaixar skills Tier A always-on de 7+ pra 2-3 Tier 0 reais.** Mantém: multi-tenant-patterns, commit-discipline. Rebaixa a auto-trigger (B): mcp-first, brief-first (vira sugestão), mwart-process, wagner-protocol-enforce (vira 1 doc lido on-demand). | **Alto** — corta overhead fixo de SessionStart, deixa contexto pro trabalho | ~1h | #1 (alinhar com hooks) |
| **3** | **Soltar a régua do Brief de 3500 → ~8-10k tokens e reposicionar como "estado-pro-Wagner", não "economia de tokens".** Com 1M, brief mais rico (mais EM VOO, mais decisões) vale mais que brief enxuto. | **Médio** — melhora qualidade do onboarding sem custo real | ~0,5h | Nenhum |
| **4** | **Colapsar enforcement MWART de 3 camadas (skill+hook+CI) pra 1 (CI gate) + GOTCHAS.md em contexto.** As 5 fases continuam playbook. | **Médio** — remove redundância, mantém a rede de segurança no merge | ~1h | #1 |
| **5** | **Trocar a meta "≤30 ADRs ativas" por métrica de relevância/link-rot** (já temos AdrLinksChecker 0219). Para de tratar 200 ADRs como crise de contexto. | **Médio** — alinha governança com capacidade real do 4.8 | ~0,5h | DriftChecker (já existe) |

---

## 5. Top 3 evoluções (capacidades novas que o projeto ainda NÃO explora)

| # | Evolução | O que muda | Esforço IA-pair |
|---|---|---|---|
| **1** | **Adotar subagent orchestration + compaction nativos do Agent SDK** no lugar de spawn manual de coordenador-paralelo. Lead delega a especialistas em paralelo com contexto isolado; compaction automática em sessões longas. | Menos scaffolding custom; sessões longas (migração de módulo inteiro) ficam viáveis sem perder estado. | ~3h (piloto em 1 fluxo, ex: cascade-review) |
| **2** | **Filesystem-as-memory estruturado pra trabalho long-running** (padrão do harness paper: feature-list JSON + progress file + git history) em vez de prosa de handoff. Já temos `08-handoff.md` — evoluir pra estado estruturado machine-readable. | Recuperação de contexto determinística entre sessões; menos "reconstruir o que eu estava fazendo". | ~2h |
| **3** | **Skills compostas + prompt caching agressivo (90%).** Com 1M e cache, dá pra ter skills que carregam dossiês inteiros (RUNBOOK + GOTCHAS + ADRs do módulo) cacheados, em vez de fragmentos. Repensar tiering como "o que cacheio" e não "o que corto". | Contexto rico por baixo custo; o trade-off muda de "economizar tokens" pra "maximizar cache hit". | ~2h |

---

## 6. O que MANTER intocado (NÃO é sobre limitação de modelo)

Estes existem por razões de negócio/segurança/legais — independem da geração do Claude. **Não tocar nesta reavaliação:**

- **Multi-tenant Tier 0 IRREVOGÁVEL** ([0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) — `business_id` global scope. Hook `block` permanece block. Vazar tenant = P0 sempre.
- **LGPD / PII** — `pii-redactor`, Audit Card cliente-visível ([0145](../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md)), ANPD NT 12/2025. Determinístico-obrigatório.
- **Append-only ADRs** ([0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [0028](../decisions/0028-adrs-numeracao-monotonica.md)) — histórico de decisão é atemporal. Manter; só a *meta numérica* de "ativas" é negociável.
- **`block-automem` / ZERO auto-mem privada** ([0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — conhecimento canônico vai pro git, visível ao time. Block.
- **`block-destructive`** — rm -rf / DROP / força-bruta git. Block.
- **ADS Brain A/B como runtime do PRODUTO** ([0145](../decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md)) — Ollama local $0 pro WhatsApp/Cobradora 24/7 é economia de OPEX real, não muleta de modelo de desenvolvimento. Manter.
- **Segredos em Vaultwarden, runtime CT100/Hostinger** ([0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)) — infra, não modelo.
- **R1 smoke real + R10 aprovação humana** ([0168](../decisions/0168-protocolo-wagner-sempre-tier-A-irrevogavel.md)) — disciplina de qualidade que nenhum modelo dispensa.

---

## 7. Roadmap CONSOLIDAR vs EVOLUIR — próximas ADRs propostas

| ADR proposta | Tipo | Conteúdo | Depende de |
|---|---|---|---|
| **0224 — Classificação hooks block vs advisory (4.8-aware)** | SIMPLIFICAR | Triar os 12 hooks; rebaixar os semânticos; documentar critério "hook só pra determinístico-obrigatório" (best-practice Anthropic 2026) | — |
| **0225 — Recalibração skills Tier A pós-4.8** | SIMPLIFICAR | 7+ → 2-3 Tier 0; resto vira auto-trigger; emenda parcial 0095/0168 (append-only, `supersedes_partially`) | 0224 |
| **0226 — Brief v2: estado rico (1M-aware)** | SIMPLIFICAR | Solta régua 3500→~8-10k; reposiciona como UX-pro-Wagner; emenda 0091/0097 | — |
| **0227 — MWART enforcement single-layer** | SIMPLIFICAR | 3 camadas → CI gate + GOTCHAS em contexto; mantém 5 fases; emenda 0104 | 0224 |
| **0228 — Piloto subagent orchestration nativo** | EVOLUIR | Migrar 1 fluxo (cascade-review ou migração de módulo) pro padrão Agent SDK nativo; medir antes de generalizar | 0225 |

Sequência: 0224 → 0225 (juntas, são a maior economia de atrito) → 0226 (independente, pode ir em paralelo) → 0227 → 0228 (depende de aprender com 0225).

---

## 8. Recomendação concreta

**Comece pela ADR 0224 (triagem de hooks block vs advisory)** — alto impacto (tira atrito de *toda* sessão de *todo* dev do time MCP), baixo esforço (~1,5h IA-pair), sem pré-req bloqueante, e usa critério canônico já validado pela própria Anthropic ("hook só pra determinístico-obrigatório; comportamento vai pra CLAUDE.md/skill"). É a mudança que mais devolve agência ao 4.8 sem arriscar nada Tier 0 — os hooks de proteção (multi-tenant, automem, destructive, pii) permanecem block; só os semânticos/lembrete viram advisory.

**Próxima ação hoje:** rodar um inventário dos 12 hooks PreToolUse marcando cada um como `[determinístico-obrigatório → fica block]` ou `[semântico/lembrete → advisory]`, usando como evidência o exit-code real de cada script (vários já fazem `exit 0` advisory — ex: `charter-validate`, `modulo-preflight`, `mcp-first`). Esse inventário É o corpo da ADR 0224.
