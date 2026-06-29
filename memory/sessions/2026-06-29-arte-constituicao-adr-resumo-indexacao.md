---
title: Estado-da-arte — gerir/resumir/indexar Constituição + ADRs pra agentes de codificação IA
date: '2026-06-29'
topic: context engineering de ruleset always-on + gestão/indexação de ADRs pra agentes IA
agent: estado-da-arte
authors: [C]
status: dossiê decisório — atualizado pós-verificação adversarial + medição (fix PR #3383)
---

# Estado-da-arte: Constituição + ADRs como contexto pra agentes IA

> ⚠️ **ERRATA 2026-06-29 (pós-verificação adversarial + medição empírica).** A recomendação original deste doc — **"comece pelo G1: aposentar `_INDEX-LIFECYCLE.md`"** — foi **DERRUBADA**. Apuração forense (`wc -l`/`ls|grep` + leitura de código, 10 agentes):
> - **G1 cai.** `_INDEX-LIFECYCLE.md` é índice **vivo** — consumido por ≥10 arquivos (o teste `AdrNumberCollisionTest` falha o CI, `IndexReconciler` ADR 0237, gate `memory-health.mjs`). Deletar/aposentar quebra CI. **NÃO fazer.** O `total_adrs: 119` stale é subcampo **sem leitor**; o subcampo com leitores (`numbering_collisions`) está **exato (14)**. Cura = rodar `jana:reconcile --heal`, não matar o arquivo.
> - **Os números deste doc estavam CERTOS** (728 linhas always-on; 36 ADRs inline; disco 317/301). A crítica posterior que os contestou (553/76/224) é que estava errada — erro de contagem do Claude.
> - **A pergunta original ("resumo que indexa melhor") foi MEDIDA** (N=30, busca real): `recall@5 = 90%` (a busca *acha*), mas **53% dos snippets caem em chunk posicional cego**. A alavanca real **não é o índice manual nem o always-on — é o `extrairSnippet` do `decisions-search`**. Fix implementado: resumo prioriza `summary` curado > seção `## Decisão`/`## Contexto` > snippet legado.
> - Frente 2 em aberto (não resolvida pelo fix): ranking — 10% not-found por confound de corpus (recusadas + sub-ADRs afogam o alvo Tier-0).


> **Resposta direta às 2 perguntas do Wagner, no topo:**
>
> 1. **"Compare com os melhores"** → O estado-da-arte 2026 (Anthropic, AGENTS.md/Linux Foundation, Log4brains) diz: ruleset always-on **mínimo de alto sinal** + **ponteiros, não conteúdo inline** + descoberta just-in-time. O oimpresso **acerta a arquitetura** (CLAUDE.md curto + @imports + `.claude/rules/` path-scoped + índice ADR gerado + status normalizado no leitor) mas **viola a própria régua na prática**: o contexto sempre-on real é **728 linhas** (não ~95) e cita **36 ADRs inline por número** — a exata superfície de drift que você sentiu.
>
> 2. **"Como posso melhorar a minha?"** → Sua intuição ("resumo indexa melhor") **está certa, e há paper que prova** (EMNLP 2024). Mas o ganho não é "resumir as ADRs" — é **(a)** parar de citar ADR-por-número no contexto always-on (trocar número→nome-estável), **(b)** desinchar `proibicoes.md` (272 linhas) movendo detalhe pra `.claude/rules/` path-scoped, e **(c)** aposentar o índice manual `_INDEX-LIFECYCLE.md` (drift: declara 119 ADRs, real são 315) pelo gerado que você **já tem pronto**. Recomendação no fim: comece pelo (c) — consolidar, não evoluir.

---

## TL;DR

Pergunta do Wagner: "constituição + ADRs deviam ser um **resumo que indexa melhor**". Estado-da-arte (Anthropic context-engineering, AGENTS.md, Log4brains) + verificação adversarial + **medição empírica N=30 na busca real** convergiram: a busca **acha** a ADR (`recall@5 = 90%`), mas **53% dos snippets caem em chunk posicional cego**. A alavanca não é o índice manual (`_INDEX-LIFECYCLE`, que a busca nem lê) nem o always-on — é o `extrairSnippet` do `decisions-search`. Fix landado no [PR #3383](https://github.com/wagnerra23/oimpresso.com/pull/3383): resumo prioriza `summary` curado > seção `## Decisão`/`## Contexto` > snippet legado. Ver **ERRATA** acima para o que do corpo original caiu (a recomendação "comece pelo G1" foi derrubada).

## 1. PESQUISA — como os melhores fazem (2026)

| Player | Como resolve | Por que é referência |
|---|---|---|
| **Anthropic — Context Engineering** | Ruleset always-on = "smallest set of high-signal tokens". Agente carrega **identificadores** (paths/links/queries) e busca o detalhe **just-in-time** via tool; pré-carregar tudo causa **context rot** (acurácia cai com janela grande). "Right altitude": específico o suficiente pra guiar, flexível pra não ser frágil. | Dona do Claude Code; define o paradigma. [fonte](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents) |
| **Anthropic — Agent Skills (progressive disclosure)** | Metadata mínima no contexto diz **quando** usar; corpo do skill vive em arquivo e consome **zero token** até ser invocado por bash. Filesystem como memória sob demanda. | Mesmo princípio aplicado a capacidades, não só regras. [fonte](https://www.anthropic.com/engineering/equipping-agents-for-the-real-world-with-agent-skills) |
| **AGENTS.md (Linux Foundation, dez/2025)** | 1 arquivo raiz always-on cross-tool (30+ agentes). Root = sempre ativo; **subdir AGENTS.md só ativa quando o agente trabalha naquela pasta** (escopo por path). Cursor `.mdc` com glob frontmatter = mesma ideia. | Padrão de indústria (OpenAI/Google/AWS sob Agentic AI Foundation). Valida o modelo "global mínimo + path-scoped". [fonte](https://agents.md/) · [comparativo](https://codersera.com/blog/agents-md-vs-claude-md-vs-cursor-rules-comparison-2026/) |
| **Log4brains / adr-tools (docs-as-code)** | ADR **imutável — só o status muda** (deprecated/superseded). Índice **gerado** a partir dos arquivos → "documentação nunca fica desatualizada". Supersessão atômica via link. | Referência canônica de gestão de ADR; modelo que o oimpresso já segue no gerador. [fonte](https://github.com/thomvaill/log4brains) |
| **Dense X Retrieval / PageIndex (EMNLP 2024 + 2025)** | Indexar por **unidade fina e autocontida (proposição)** supera passagem/chunk em recall e generalização. PageIndex: "similaridade ≠ relevância" em docs longos+fixos → árvore de sumários (tipo índice/ToC) recupera melhor que chunk bruto. | **Evidência empírica direta** pra tese do Wagner. [paper](https://arxiv.org/abs/2312.06648) · [PageIndex](https://github.com/VectifyAI/PageIndex) |

**Síntese da Fase 1 (3 princípios que importam aqui):**
- **P-A — Always-on = ponteiro, não conteúdo.** Diga ao agente *como achar*, não *tudo que existe*. Citar ADR-por-número inline é o anti-padrão: vira conteúdo que drifa.
- **P-B — Escopo por path > tudo global.** Detalhe de área vive em arquivo que só carrega quando se toca a área (`.claude/rules/`, subdir AGENTS.md).
- **P-C — Índice gerado, fonte única.** Manual sempre drifa; gere do conteúdo, normalize status no leitor, mantenha append-only.

---

## 2. COMPARA — oimpresso hoje vs estado-da-arte (verificado no código)

| Dimensão | Estado-da-arte (Fase 1) | oimpresso hoje (file:line) | Distância |
|---|---|---|---|
| **Arquitetura do ruleset** | Global mínimo + path-scoped + skills sob demanda | TEM os 3: `CLAUDE.md` + `@imports` + `.claude/rules/*.md` (11 rules) + skills Tier A/B/C | **curta** — desenho é estado-da-arte |
| **Peso real do always-on** | "Smallest high-signal set"; consenso <300 linhas root | **728 linhas** sempre-on (`CLAUDE.md` 110 + 5 imports: `proibicoes.md` **272**, `how-trabalhar.md` 159, `what` 85, `regras-time` 65, `why` 37) | **longa** — 7× a régua que o próprio doc declara ("≤100 linhas") |
| **Ponteiro vs conteúdo inline** | Carregar identificadores, não conteúdo | **36 ADRs distintas citadas inline por número** no always-on (14 só no CLAUDE.md). É exatamente o que drifa quando ADR muda status/é superseded | **longa** — esta é a dor literal do Wagner |
| **Índice de ADR gerado** | Gerado da fonte, status normalizado no leitor (Log4brains) | `scripts/governance/adr-index-generate.mjs` → `_INDEX-GENERATED.md`: **315 arquivos, 275 ativos, 14 colisões + supersessão auto-detectadas**, modos dry/`--write`/`--check`. Append-only intacto (ADR 0257) | **curta** — já é estado-da-arte, **pronto** |
| **Índice manual legado** | Aposentar manuais; 1 fonte | `_INDEX-LIFECYCLE.md` ainda vivo, frontmatter `total_adrs: 119` / `unique_numbers: 116` enquanto o gerado conta **315/299**. **Drift escancarado** | **longa** — coexistência de 2 índices, 1 mentindo |
| **Filtro de busca de ADR (drift?)** | — | **BOM:** `DecisionsSearchTool.php:76` usa scope `porStatusAtivo()` sobre `mcp_memory_documents` (lê status do próprio doc), **NÃO** o índice manual. Logo a *busca* não drifa; só a *descrição* do tool (linha 26 ainda diz "90+ ADRs" — são 315) e o índice humano | **média** — mecanismo ok, rótulos mentem |
| **Indexação semântica de docs longos** | Proposição/sumário > chunk bruto (EMNLP 2024) | MCP tem FULLTEXT + Meilisearch hybrid embedder sobre doc inteiro. ADRs longas+fixas indexam como blocão | **média** — funciona, mas não usa sumário-por-ADR |
| **Frescor do índice** | — | **BOM:** `McpIndexFreshnessChecker.php` (sentinela daily) já alarma se índice MCP defasa do git memory/ | **curta** — já tem guarda |

**Honestidade:** o oimpresso **supera o mercado** em 3 pontos — gerador de índice já feito, sentinela de frescor, e filtro de status que lê da fonte (não do índice). O gap **não é** falta de ferramenta; é **régua não-aplicada**: o always-on inchou 7× e cita ADR-por-número, e o índice manual velho não foi desligado.

---

## 3. AVALIA — o que está faltando, rankeado

| Gap | Impacto | Esforço (IA-pair, ADR 0106 10x) | Pré-req? |
|---|---|---|---|
| **G1 — Aposentar `_INDEX-LIFECYCLE.md`; gerado vira fonte única** | **alto** (mata o drift 119→315; tool MCP e humano passam a ver o mesmo número) | **~30min** — gerador já existe; trocar consumidores + lápide no manual | não — gerador pronto |
| **G2 — Desinflar always-on: número→nome-estável nas citações de ADR** | **alto** (mata a dor literal — ADR muda status e o contexto não mente mais) | **~1-2h** — trocar "[ADR 0093]" por slug-âncora estável tipo "multi-tenant-tier-0"; números viram detalhe que o gerador resolve | não |
| **G3 — Mover detalhe de `proibicoes.md` (272 linhas) pra `.claude/rules/` path-scoped** | **alto** (corta ~metade do always-on; cada Tier 0 detalhado só carrega ao tocar a área) | **~2-3h** — muitas proibições já têm rule-par (calculo-valor, migrations); mover o resto e deixar só o índice de 1-linha no always-on | não, mas pareia com G2 |
| **G4 — Sumário-por-ADR de 1-3 proposições pro índice semântico** | **médio** (recall melhor na busca de ADR longa; valida tese Wagner) | **~2-4h** — gerar campo `summary` no `--write` do gerador + indexar o sumário, não o doc inteiro | depende de G1 (gerador como dono) |
| **G5 — Corrigir rótulos mentirosos ("90+ ADRs" em `DecisionsSearchTool.php:26`)** | **baixo** (cosmético, mas é claim sem evidência) | **~10min** | não |

### Veredito sobre "resumo indexa melhor" (pergunta do Wagner)

**É VERDADE — condicionalmente, e com paper.** [Dense X Retrieval, EMNLP 2024](https://arxiv.org/abs/2312.06648) prova que indexar por **unidade fina e autocontida (proposição)** supera chunk/passagem em recall e generalização. [PageIndex](https://github.com/VectifyAI/PageIndex) reforça pra docs longos+fixos: "similaridade ≠ relevância" — sumário/árvore recupera melhor que o blocão. **Mas atenção à condição:** o ganho é **resumo COMO unidade de índice/recall** (G4), não "encurtar a ADR" — a ADR fica íntegra (append-only); o sumário é uma *projeção indexável* derivada dela. E o ganho **maior e mais barato** não é no índice semântico — é **não inflar o always-on com números de ADR** (G2/G3): aí "resumo" = um índice de 1-linha por tema que aponta, em vez de 36 números que drifam.

---

## Recomendação final

**Comece pelo G1 — alto-impacto, ~30min, sem pré-req bloqueante.** O gerador (`scripts/governance/adr-index-generate.mjs`) já produz a fonte única correta (315/275 ativos, colisões/supersessão); só falta **desligar o `_INDEX-LIFECYCLE.md` legado** (que mente 119) e apontar os consumidores humanos pro gerado. É CONSOLIDAR (ligar o que já existe), não EVOLUIR. Mata o drift que o Wagner sentiu, sem tocar em nada append-only.

Sequência depois (todas sem pré-req entre si exceto G4): G1 → G2 → G3 → G4 → G5. G2+G3 juntos derrubam o always-on de 728 pra ~350 linhas e eliminam os 36 números inline.

**Próxima ação concreta hoje:** rodar `node scripts/governance/adr-index-generate.mjs --check` pra confirmar que o gerado bate com os arquivos, e abrir 1 ADR curta "índice gerado vira fonte única; `_INDEX-LIFECYCLE.md` recebe lápide apontando pro gerado" — escopo de 1 PR, ~30min IA-pair.

---

## Sources
- [Anthropic — Effective context engineering for AI agents](https://www.anthropic.com/engineering/effective-context-engineering-for-ai-agents)
- [Anthropic — Agent Skills / progressive disclosure](https://www.anthropic.com/engineering/equipping-agents-for-the-real-world-with-agent-skills)
- [AGENTS.md spec](https://agents.md/) · [comparativo 2026](https://codersera.com/blog/agents-md-vs-claude-md-vs-cursor-rules-comparison-2026/)
- [Log4brains](https://github.com/thomvaill/log4brains)
- [Dense X Retrieval (EMNLP 2024) — proposições](https://arxiv.org/abs/2312.06648) · [PageIndex — vectorless/árvore](https://github.com/VectifyAI/PageIndex)
- Código verificado: `CLAUDE.md` (110 ln) + imports (728 ln total, 36 ADRs inline) · `memory/proibicoes.md` (272 ln) · `scripts/governance/adr-index-generate.mjs` + `memory/decisions/_INDEX-GENERATED.md` (315/275) vs `_INDEX-LIFECYCLE.md` (`total_adrs: 119`) · `Modules/Jana/Mcp/Tools/DecisionsSearchTool.php:26,76` · `Modules/Governance/Services/Checkers/McpIndexFreshnessChecker.php`
