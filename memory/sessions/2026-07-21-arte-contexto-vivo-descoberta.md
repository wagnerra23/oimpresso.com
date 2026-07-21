---
date: 2026-07-21
hour: "18:00 BRT"
topic: "Estado-da-arte 2026 — manter BRIEFING/charter/casos VIVOS (não apodrecer) + descoberta por IA + melhores recursos Laravel"
authors: ["C"]
tags: [estado-da-arte, contexto-vivo, anti-apodrecimento, descoberta-ia, meilisearch, laravel-boost, pest-arch, adr-0256]
outcomes:
  - "Veredito: a dor não é falta de tecnologia — é ESPALHAR o padrão que já foi provado (#4601) + fechar 1 last-mile de descoberta. Você já tem o topo."
  - "5 gaps reais rankeados (4 CONSOLIDAR, 1 ADOTAR-situacional) — nenhum implementado, aguardam OK do [W]"
---

# Estado da arte — manter contexto VIVO e DESCOBRÍVEL pela IA (2026)

**Data:** 2026-07-21 · **Agente:** sessão dedicada `estado-da-arte` · **Modelo:** Opus 4.8
**Pedido [W] (textual):** *"meus briefing, charter, casos de uso não sobrevivem muito, apodrecem. não sei os arquivos de cada contexto, dificulta a busca da IA. não estou usando os melhores recursos do laravel."*
**Método:** 3 pesquisas paralelas (SDD/living-docs · descoberta-IA/RAG · Laravel 2026) = ~18 WebSearch/Fetch, **sem ler o repo**. Depois: **cada afirmação sobre o oimpresso verificada no repo VIVO** (`git show origin/main:<path>`, HEAD `6bc2c9d762`). Fontes ao fim.
**Irmão complementar (não duplicar):** [2026-07-17 — quais arquivos por TELA/MÓDULO](2026-07-17-arte-artefatos-por-tela.md) respondeu *"quais artefatos devem existir"*. **Este** responde as 3 outras perguntas: como mantê-los **vivos**, como a **IA os acha**, e quais **recursos Laravel** ajudam de verdade.

---

## TL;DR / Resumo executivo — veredito em 1 parágrafo (leia só isto se for ler uma coisa)

**Você NÃO está sem os melhores recursos. Você já possui as duas coisas que mais importam** — busca híbrida (keyword + embedding) via Meilisearch, e uma **porta-de-entrada gerada + doutrina anti-apodrecimento (ADR 0256)** que o mercado inteiro convergiu em 2026 e ninguém superou. A dor real tem 3 causas, e **nenhuma é "adotar tecnologia nova"**: (1) o padrão anti-apodrecimento que **funcionou** (BRIEFING-porta-de-entrada, PR #4601) existe **em 1 módulo só** — falta **espalhar**; (2) a busca da IA já tem o motor certo, mas o **último metro está desligado** — o backend sabe filtrar por `module`/`type`, mas a **ferramenta MCP que o agente usa não expõe esses filtros** (só `query`/`business_id`/`limit`); (3) "melhores recursos Laravel" = você já tem os certos (Boost dev-only, Scout, AGENTS.md fino) — o que falta é **consolidar** 9 guardas de arquitetura caseiras em `arch()` nativo e usar `casts()`+enums pra segurança de VALOR Tier-0. **Resumo honesto: 80% da cura é disciplina de ESPALHAR + 1 fio de descoberta a ligar, não compra de ferramenta.**

---

## O que JÁ está no TOPO (crédito honesto — verificado no repo)

| # | Você tem | Evidência no repo | Por que é topo-2026 |
|---|---|---|---|
| 1 | **Doutrina anti-apodrecimento** *"derivado+enforçado sobrevive; escrito+lembrado apodrece"* | [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) | O mercado convergiu **na sua direção**: Spec Kit, Kiro e Tessl todos tentam isso; **ninguém** resolveu "spec que se auto-atualiza". Você diagnosticou a doença antes deles nomearem. |
| 2 | **Busca híbrida** (BM25 + embedding) sobre o markdown canon | `meilisearch/meilisearch-php ^1.16`, `laravel/scout ^11.1`, embedders + `filterableAttributes` **codificados como config** (`jana:meilisearch-setup`), `mcp_memory_documents` FULLTEXT + `DocumentChunker` + `ContextualizerService` | "Alcançar híbrido primeiro" é o **piso** que todo guia 2026 recomenda. Você já passou dele. Pinecone reporta ~48% de ganho vs método único. |
| 3 | **Padrão porta-de-entrada** (ponteiro + recibo datado, não fato restatado) | [Produto/BRIEFING.md](../requisitos/Produto/BRIEFING.md) (PR #4601, aprovado por revisão adversarial) — frontmatter + §"Superfície de código" (lista os arquivos do contexto) + delega contagens aos geradores | É **literalmente** o SOTA "pointers > restated facts" (Fowler/augmentcode). Doc que APONTA não apodrece. |
| 4 | **Co-locação como sinal** (`.charter.md`/`.casos.md` ao lado do `.tsx`) + **mapa por-tela GERADO** | `scripts/qa/screen-coverage-map.mjs`, `prototipo-ui/ancora.mjs` | A fronteira (Anthropic, Sourcegraph) diz: *"convenção de pasta + gerador vence mapa escrito à mão"*. Você faz exatamente isso. |
| 5 | **Laravel Boost já adotado — do jeito CERTO** | `laravel/boost ^2.4` em **`require-dev`** (não prod → sem violar Hostinger runtime), `.mcp.json` na raiz, `AGENTS.md` = ponteiro fino `@CLAUDE.md` (NÃO deixou o Boost sobrescrever seu canon), [ADR 0034](../decisions/0034-laravel-ai-sdk-oficial-boost-mcp.md), `BoostToolAdapter` | Boost é o tooling canônico "ajudar IA a trabalhar em Laravel" (out/2025). Você o tem, e evitou a armadilha (deixar o gerador de guidelines colidir com o CLAUDE.md). |
| 6 | **Front-door sempre-carregado + regras path-scoped + grep agêntico** | `CLAUDE.md` + `@imports`, `.claude/rules/`, `brief-fetch` no SessionStart | A fronteira 2026 **removeu vector-search do agente** (Anthropic tirou do Claude Code em mai/2025) e voltou pro grep + doc-porta. Você já opera assim. |
| 7 | **Catracas de frescor + fato-âncora + registro de ideias mortas** | `charter-refs.mjs`, `briefing-code-staleness.mjs`, `agents-md-staleness.mjs`, `visual-comparison-staleness.mjs`, `memory-health.mjs` Check T (fact-anchor), `casos-gate` (ADR 0264), §5 de `proibicoes.md` | "Docs que falham o CI quando velhos" + "detecção no ponto de mudança" é o triângulo do living-docs. Você tem 5 sentinelas de staleness + fact-anchor determinístico. |
| 8 | **Destilador de verdade do módulo** (gera BRIEFING do código) | `jana:distill-module-truth` (`DistillModuleTruthCommand` + `DistillerModuloVerdade`) — BRIEFINGs com `distilled_at`/`distilled_by` (Crm, Financeiro, Governance, Jana) | Você já tem um **gerador** que produz o resumo do módulo a partir do código — a peça exata que impede o BRIEFING de apodrecer. |

> **Traduzindo:** dos ~9 mecanismos que os líderes 2026 usam pra manter contexto vivo e descoberto, **você tem 7-8 deles**. O que falta abaixo é quase todo **consolidação/espalhamento**, não adoção.

---

## As 3 dores do [W] — atacadas na raiz

### Dor (a) — "charter/briefing/casos apodrecem"

**Por que apodrecem (raiz, convergente em todas as fontes 2026):** doc é *escrito e lembrado* (prosa passiva que um humano precisa lembrar de atualizar); código é *executado e enforçado*. No instante em que os dois são mantidos **separados**, divergem — e **IA acelera o apodrecimento** porque gera código mais rápido do que alguém re-ancora o doc. Isto é **exatamente** a ADR 0256.

**Como os líderes impedem (e o que você já faz):**
- **Derivar, não escrever** (OpenAPI→docs; Stripe/Netflix/Uber): output regenerável não apodrece. → Você tem `distill-module-truth`, `system-map`, `screen-coverage`. ✅ parcial.
- **Teste-como-contrato** que falha o CI na divergência (Pact, Shopify Packwerk/Sorbet). → Você tem `casos-gate` (UC citado por teste). ✅
- **Catraca de frescor** (mtime do doc vs código). → Você tem 5 sentinelas. ✅
- **Dono segue o código** (o alerta vai pro AUTOR da mudança, no momento, com fix rascunhado). → **Você DETECTA mas não ROTEIA** ao autor com rascunho. 🟡 (Gap 4 abaixo — mas honesto: pra 5 pessoas, o Daily Brief compartilhado talvez baste.)
- **O que NINGUÉM resolveu:** spec que se auto-atualiza mid-sessão quando a implementação revela uma restrição nova. Tessl tentou (`// GENERATED FROM SPEC - DO NOT EDIT`) e **pivotou** em jan/2026. **Não persiga isso — é o hype não-resolvido de 2026.**

**A verdade incômoda:** o **antídoto já foi provado aqui** (BRIEFING-porta-de-entrada #4601). Ele existe em **1 módulo** (Produto). O resto dos 77 BRIEFINGs é prosa antiga, lápide, wish ou meio-destilado. **A cura de (a) é ESPALHAR o #4601 + derivar a §"Superfície de código" (hoje é lista à mão → pode apodrecer).** → Gaps 1 e 2.

### Dor (b) — "não sei os arquivos de cada contexto, dificulta a busca da IA"

Duas sub-dores distintas:

- **(b.i) "quais arquivos são deste contexto"** (a porta humana/agente). **Resolvido — em 1 módulo.** A §"Superfície de código" do `Produto/BRIEFING.md` lista Controllers, Models, Views, Pages, charters, casos do domínio, com links. É a resposta literal à sua frase. Falta: **espalhar** + **derivar** (senão a lista à mão apodrece — a própria doença de (a)). Não existe hoje gerador "módulo → seus arquivos" (o `system-map` faz módulo→BRIEFING+último-toque; o `screen-coverage` faz por-tela). → Gap 2.
- **(b.ii) "a busca da IA acha o contexto certo?"** O motor (Meilisearch híbrido) **já é topo** — não troque por reranker nem GraphRAG (ver "Hype" abaixo). O ganho real, **#1 em ROI segundo a pesquisa**, é **filtro por metadado**: o backend `buscarHybrid` **já aceita** `tipo`/`module` opcionais e o índice **já tem** `filterableAttributes` — mas a ferramenta MCP `memoria-search` (que o agente chama) expõe **só `query`/`business_id`/`limit`**. **O agente não consegue dizer "busque só SPECs de OficinaAuto" ou "só type=charter".** Último metro desligado. → Gap 1 (o de maior ROI).

### Dor (c) — "não estou usando os melhores recursos do Laravel"

**Honestamente: você está.** Boost (dev-only, `require-dev`), Scout, MCP, AGENTS.md fino — o conjunto canônico está lá. O que falta é **refinamento**, não adoção:
- **Pest `arch()` DSL** — você enforça arquitetura com **9 testes caseiros** (`tests/Feature/Architecture/*` — `NoHardcodeBusinessIdInModules`, `AppShellUsageGate`, etc). O `arch()->expect()` faria isso **nativo, mais curto, falhando o CI** ("toda Model de negócio usa BusinessScope", sufixos, camadas). O RESULTADO você já tem; é consolidação. → Gap 3.
- **`casts()`+enums (backed enum)** — segurança de VALOR/status auto-documentada, type-safe. Relevante ao incidente `num_uf` (R$ inflado ×100k). Padrão rot-resistente. → Gap 5.
- **Pennant** — flags de **canary/rollout/kill-switch** (o "canary 7d" que o CLAUDE.md já cita, hoje feito com `.env`/`config()`). **NÃO** substitui `package_details`+Spatie (isso é entitlement comercial, Tier-0). Preenche o buraco real do banido `if($business_id===N)`. → Gap 5.
- **Pint** (style gate CI) + **Rector** (sweeps de modernização por-módulo, com review — nunca big-bang) = infra barata. → Gap 5.
- **Scout / Laravel Prompts = pular** (Scout já resolvido no direto; Prompts é pra CLI bonita).

---

## GAPS reais, rankeados por IMPACTO × ESFORÇO

> Legenda: **CONSOLIDAR** = fechar/espalhar o que já existe · **ADOTAR** = trazer algo novo (com prova de que a dor existe aqui). Nada abaixo foi implementado — são **propostas** aguardando OK do [W].

### 🥇 GAP 1 — [CONSOLIDAR · impacto ALTO · esforço BAIXO] Expor filtros de metadado (`module`/`type`/`status`) na busca MCP + resolver todo hit pra `path §seção`
**Dor:** (b.ii). **Prova no repo:** `MemoriaSearchTool::schema()` expõe só `query`/`business_id`/`limit`; `buscarHybrid` já aceita `tipo`/`module`; `filterableAttributes` já configurados. **É o #1 ROI da pesquisa de descoberta** e é **fiação de último metro, não infra nova.** Ação: adicionar `module`/`type`/`status` ao schema das tools de busca (memoria/docs/decisions) + garantir que cada resultado retorne o **caminho + seção** (pra virar "leia `Modules/X/SPEC.md §US-XXX`", não um trecho órfão). **Faz PRIMEIRO.** *(Não é reranker, não é GraphRAG — é ligar o que já está construído.)*

### 🥈 GAP 2 — [CONSOLIDAR · impacto ALTO · esforço MÉDIO] Espalhar o BRIEFING-porta-de-entrada (#4601) + DERIVAR a §"Superfície de código"
**Dor:** (a) + (b.i). **Prova:** só `Produto/BRIEFING.md` tem o rewrite completo; a §"Superfície de código" é **lista à mão** (pode apodrecer). Ação em 2 tempos: **(2a)** um gerador `module-surface` (glob `Modules/X` + arquivos `app/**` que referenciam o módulo) que **produz/atualiza** a seção de arquivos — assim a porta-de-entrada **não pode apodrecer** (fecha a doença na raiz); **(2b)** espalhar o padrão módulo-a-módulo, **oportunístico** (quando o módulo já for tocado por trabalho real), **NUNCA big-bang** (§5 2026-07-12: tocar legado em massa acorda gates diff-aware). Prioriza os módulos VIVOS (Produto✅, Financeiro, Jana, Vendas, OficinaAuto, ComVis).

### 🥉 GAP 3 — [CONSOLIDAR · impacto MÉDIO · esforço BAIXO-MÉDIO] Dobrar as 9 guardas de arquitetura caseiras em Pest `arch()`
**Dor:** (c). **Prova:** zero uso de `arch()->expect()`; 9 testes bespoke em `tests/Feature/Architecture/`. Ação: expressar as regras estruturais como `arch()` nativo (naming, camadas, "Model de negócio usa BusinessScope", "Controller não toca DB"). **Manter** os testes de feature cross-tenant (o `arch()` raciocina sobre classes/namespaces — **não pega vazamento multi-tenant em runtime**). Ganho: menos código caseiro, regra mais legível, mesmo enforcement. *Honesto: o RESULTADO já é alcançado; é refino, não buraco.*

### GAP 4 — [ADOTAR-leve · impacto MÉDIO · esforço MÉDIO · ⚠️ cautela] Roteamento do alerta de staleness pro AUTOR + fix rascunhado
**Dor:** (a), 3ª perna do triângulo living-docs. **Prova:** você DETECTA (5 sentinelas + Daily Brief "CHARTERS APODRECENDO"), mas não ROTEIA ao autor da mudança com um rascunho de correção "1-clique". **⚠️ Cautela dupla:** (1) **NÃO é gate novo** — o §5 de `proibicoes.md` tem várias lápides contra gate-de-teatro (presença≠correção, campo auto-declarado, duplicar régua). É **roteamento + rascunho** sobre a detecção que já existe. (2) **Honestidade de escala:** o padrão "rotear ao autor" nasceu pra orgs grandes; **pra 5 pessoas o Daily Brief compartilhado pode já ser suficiente.** Só vale se o [W] sentir que apodrecimento passa batido hoje. **Menor prioridade.**

### GAP 5 — [ADOTAR-situacional · impacto BAIXO-MÉDIO · esforço BAIXO cada] Refinos Laravel: Pennant (canary) · `casts()`+enums (valor Tier-0) · Pint · Rector-por-módulo
**Dor:** (c). Cada um é independente e barato. **Pennant** preenche o buraco real de canary/kill-switch (hoje `.env`/`config()`; e mata a tentação do banido `if($business_id===N)`) — **escopar o resolver por `business_id`** e **nunca** virar 2º store de entitlement. **`casts()`+enums** = segurança de valor/status (relevante ao incidente `num_uf`). **Pint** = gate de estilo. **Rector** = sweeps de modernização por-módulo com review (nunca big-bang — espelha suas próprias lições). **Scout/Prompts = pular.**

---

## O que é HYPE / OVERKILL pra vocês (não fazer — economia de crédito e foco)

- **Reranker cross-encoder como próximo investimento.** Real, mas de valor marginal quando o alvo é "achar *o* arquivo" (poucos candidatos, o agente lê e segue no grep). Só considere se **medir** o doc certo caindo em rank 6-30 com frequência. Ajuste o `semantic-ratio`/ranking-rules do Meilisearch antes.
- **GraphRAG / knowledge graph** sobre o markdown. Forma errada de problema (docs de tópico-distinto, cross-links já curados à mão via `supersedes:`/`related_adrs:`), caro de construir/re-indexar. **Pular.** Se um dia precisar de query de relação: LightRAG (incremental), não Microsoft GraphRAG.
- **Spec-as-source com regeneração** (modelo `DO NOT EDIT` da Tessl). Não-determinístico, JS-only, closed beta — e a própria Tessl pivotou pra longe disso. É o **hype não-resolvido** de 2026.
- **Deixar o Boost gerar/sobrescrever guidelines** (`CLAUDE.md`/`AGENTS.md`). Colidiria com seu canon. Você já escolheu certo (AGENTS.md fino) — **mantenha assim**; use do Boost só as tools MCP dev (Search Docs, Schema, Tinker, Last Error), apontadas ao **CT 100 staging** (nunca prod/biz=4).
- **Re-construir infra de embedding mais pesada.** A fronteira foi na direção OPOSTA (menos pré-indexação, mais grep agêntico). Seu Meilisearch é um bom **seed**; não o transforme no cérebro inteiro da recuperação.

---

## Disciplina §5 (proibicoes 2026-07-16) — provei que a dor existe AQUI antes de propor

Nenhuma proposta importa solução cega. Cada gap está ancorado num **fato verificado no repo**: Gap 1 = `MemoriaSearchTool::schema()` sem `module`/`type` (visto); Gap 2 = só `Produto/BRIEFING.md` com o padrão completo + §"Superfície de código" à mão (visto); Gap 3 = zero `arch()`, 9 testes bespoke (visto); Gap 5-Pennant = o banido `if($business_id===N)` + canary manual (`.env`) que o próprio CLAUDE.md cita. **A recomendação-mãe é subtração + espalhamento, não compra.** E o crédito é honesto: em busca e anti-apodrecimento **você já está no topo** — o trabalho é ligar o último fio e espalhar a disciplina.

---

## Próximos passos (propostas — NÃO implementadas, aguardam [W])

1. **[W] escolhe a ordem.** Recomendação: **Gap 1** (1 sessão, ROI imediato na busca da IA) → **Gap 2a** (gerador de superfície, mata a raiz do apodrecimento) → **Gap 2b/3** (espalhar + arch, oportunístico).
2. Cada gap vira **US/task** própria (chat → materialização, per how-trabalhar §Pedido de tela/feature). Gaps 2/3 tocam `Modules/` → pré-flight obrigatório.
3. Gap 1 e 2a merecem **ADR proposta** curta (mudança de contrato de tool + gerador novo).

## Fontes (estado-da-arte 2026)

**SDD / living-docs:** [Spec Kit](https://github.com/github/spec-kit) · [Fowler — SDD 3 tools](https://martinfowler.com/articles/exploring-gen-ai/sdd-3-tools.html) · [AWS Kiro guide](https://www.developersdigest.tech/blog/aws-kiro-developer-guide-2026) · [Tessl — spec-centric](https://tessl.io/blog/from-code-centric-to-spec-centric/) · [Falconer — living documentation](https://falconer.com/guides/living-documentation) · [Fern — docs linting](https://buildwithfern.com/post/docs-linting-guide) · [Augmentcode — spec as source](https://www.augmentcode.com/guides/spec-as-source-of-truth-rebuildable-codebase)
**Descoberta / RAG:** [Anthropic — context engineering](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents) · [Vadim — Claude Code no indexing](https://vadim.blog/claude-code-no-indexing/) · [Sourcegraph — context engineering](https://sourcegraph.com/blog/context-engineering) · [DigitalApplied — hybrid search 2026](https://www.digitalapplied.com/blog/hybrid-search-bm25-vector-reranking-reference-2026) · [ZeroEntropy — reranking 2026](https://zeroentropy.dev/articles/ultimate-guide-to-choosing-the-best-reranking-model-in-2025/) · [TDS — do you need GraphRAG?](https://towardsdatascience.com/do-you-really-need-graphrag-a-practitioners-guide-beyond-the-hype/) · [agents.md](https://agents.md/)
**Laravel 2026:** [Scout](https://laravel.com/docs/12.x/scout) · [Meilisearch multitenancy](https://www.meilisearch.com/docs/guides/laravel_multitenancy) · [Pennant](https://laravel.com/docs/13.x/pennant) · [Boost — announcement](https://laravel.com/blog/announcing-laravel-boost) · [Boost docs](https://laravel.com/docs/13.x/boost) · [Pint](https://github.com/laravel/pint) · [Rector p/ upgrades](https://www.phparch.com/2025/10/stop-manual-refactoring-automate-your-php-upgrades-with-rector/) · [Pest architecture testing](https://www.honeybadger.io/blog/laravel-pest-architecture-testing/)
