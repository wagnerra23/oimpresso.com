---
title: "Estado da arte — governança de conhecimento anti-drift: correção do FATO vs frescor por idade"
topic: "estado-da-arte governança de conhecimento — fato vs frescor"
date: "2026-07-04"
kind: session
agent: estado-da-arte
tags: [governanca, conhecimento, anti-drift, fact-consistency, contradiction-detection, docs-as-code, agent-memory]
related_adrs: ["0256", "0258", "0264", "0270", "0275", "0298", "0314", "0316"]
---

# Estado da arte — governança de conhecimento numa base operada por agentes de IA

> Pergunta do Wagner: as máquinas anti-drift do oimpresso nagam por **idade**, não por **correção**. Vale construir um *fact-consistency checker* (buraco #1) e/ou um *contradiction detector* (buraco #2)? Como o SOTA faz isso sem virar teatro?
>
> Método: Fase 1 pesquisa limpa (sem ler memory/) → Fase 2 compara com a máquina real → Fase 3 gaps por impacto×esforço. Data 2026-07-04.

---

## Fase 1 — PESQUISA (como os melhores fazem em 2026)

| Player / paper | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Dosu — "Score Documentation Freshness in CI"** (2025) | 3 checks determinísticos (git-age delta doc×fonte, TTL por frontmatter, **symbol-drift**: extrai `` `Symbol` `` do doc e confere se ainda existe no código-fonte) → score 0-100. Zona cinza (35-64) roteia pra **Claude com verdict `STILL_ACCURATE`/`DRIFTED`/`NEEDS_HUMAN_REVIEW`**, rodando `continue-on-error: true` (assist, não gate). | Referência do padrão "determinístico morde + LLM assiste". **Admite explicitamente o mesmo buraco #1:** *"the pipeline detects drift, not correctness"* — doc fresco descrevendo comportamento errado passa liso. |
| **UnderstandingData — "Doc Drift Detection in CI"** (2025) | Lê o **diff do PR** e cruza com docs via mapa em `CLAUDE.md`; Claude responde "esta página referencia código mudado? descreve comportamento modificado?". Gate de ruído por role do autor + label `skip-docs-check` + instrução explícita "pule bugfix/refactor interno". | Prova que o SOTA de detecção de **mismatch fato×código** já é LLM-semântico ancorado no **diff**, não em idade. É exatamente a forma do buraco #1 — e mesmo assim roda como PR-comment advisory, não bloqueio. |
| **Contradiction Detection in RAG (arXiv 2504.00180, 2025)** | LLM como validador de contexto: detecta contradição binária + classifica tipo + segmenta qual doc conflita. Números medidos: **Claude-3 Sonnet+CoT = 71% acc, 95,1% precisão, 56,6% recall.** Hierarquia de dificuldade: par-a-par ~83-89% (fácil) → condicional/negação (médio) → **auto-contradição num único doc 0,6-45,6% (muito difícil)**. | O dado empírico que calibra o buraco #2. **Alta precisão / baixo recall** = LLM é conservador: quando aponta contradição, geralmente é real (bom pra gate). Mas pega ~metade → é triagem, não garantia. Concordância humana entre anotadores só 74% → a tarefa é intrinsecamente difícil. |
| **Zep / Graphiti — temporal knowledge graph** (2024-2026) | Grafo bitemporal: cada fato carrega *event time* (quando foi verdade) + *ingestion time* (quando o agente viu). Fato novo que contradiz um antigo **invalida a aresta anterior sem apagá-la** (`valid_from`/`valid_to`). Dedup de entidade no write. | Referência de "como resolver contradição sem perder informação". O append-only-com-supersede do oimpresso É a versão em git disto — a arquitetura já está certa. |
| **Mem0 (v3, abr/2026) / Letta (MemGPT)** | Mem0: loop extract→consolidate→retrieve com **dedup + fact-merge no write** (store não cresce linear). Letta: LLM é o gerente de memória, paginando entre contexto vivo e arquivo, consolidação dirigida por agente. | Estado da arte de "memória agêntica auto-consolidante". Relevante como *anti-padrão de custo*: consolidação automática por LLM é cara e não-determinística — o oposto do que o oimpresso pode pôr no CI. |

**Consenso da indústria ("knowledge as a product", Bloomfire/Atlassian/Anthropic 2025-26):** *"funde a camada de verdade antes da camada de agente"* — invista em lifecycle, dedup, versão e **resposta com citação** ANTES de plugar agentes, porque *"agentes tratam conhecimento stale/conflitante como verdade e repetem o erro em escala"*. Todo mundo concorda que **frescor por idade é o piso, não o teto**; ninguém em 2026 tem um *fact-consistency checker* determinístico e barato — o estado da arte para correção-do-fato é **LLM-como-juiz ancorado no código/diff, rodando advisory**.

---

## Fase 2 — COMPARA com a máquina do oimpresso

Li `scripts/governance/memory-health.mjs` (Checks A-R + N/O), os sentinelas (`knowledge-drift`, `mcp-drift-sentinel`, `protection-drift`), ADR 0270 (ciclo de vida), 0316 (tombstone), 0298 (teto de gates). Retrato do prompt confere.

| Dimensão (emergiu da Fase 1) | Estado-da-arte 2026 | oimpresso hoje | Distância |
|---|---|---|---|
| **Frescor por idade** (TTL, git-age) | Dosu: TTL frontmatter + git-age delta | Check D, S, R (meia-vida por `decided_at`), H (✓lido>14d), B (scorecard stale) | **oimpresso À FRENTE** — TTL por meia-vida de ADR + determinismo CI (sem `Date.now`) é mais disciplinado que o SOTA |
| **Symbol drift** (símbolo citado sumiu do código) | Dosu: extrai `` `sym` `` e confere no fonte | `briefing-code-staleness.mjs` (BRIEFING×código do módulo) + dominio-gate (enum×dicionário) | **Empatado / à frente no domínio** — o dominio-gate é um symbol-check *required* mais forte que o de qualquer player |
| **Contradição sem-perda** (fato novo invalida antigo) | Graphiti bitemporal invalida aresta | Append-only + `supersedes`/tombstone (ADR 0316) + índice gerado (0258) | **Empatado** — git-com-supersede = a versão versionada do bitemporal. Arquitetura certa |
| **Anti-proliferação / teto** | — (indústria não tem) | Check M (`promote_by` obrigatório, ADR 0298/0275) + "required = só Tier-0" (0314) | **oimpresso MUITO À FRENTE** — ninguém no SOTA tem lei anti-teatro de gate |
| **Correção do FATO (buraco #1)** | Dosu/UnderstandingData: LLM-juiz no diff, advisory, admitem furo | **ZERO** — nenhuma máquina lê o *conteúdo* pra ver se o fato bate com o código | **LONGA** — mas o SOTA também não resolve determinístico; a fronteira é LLM-advisory |
| **Detecção de contradição doc×doc (buraco #2)** | arXiv: LLM-juiz 95% prec/57% recall, advisory | **ZERO máquina** — achado por auditoria humana (`05-preferences` vs "pergunte antes") | **LONGA** — SOTA existe mas é probabilístico (recall ~57%) |
| **Piles em limbo (buraco #3)** | Lifecycle/expiração de conteúdo (SSOT) | **PARCIAL** — 0270 conceitua decaimento; Check K pega session-log órfão. Mas `proposals/` (65 drafts hoje) e homônimo `dominio/`+`dominios/` **não são medidos** | **CURTA** — mecanicamente trivial de fechar |

**Veredito honesto da máquina:** o oimpresso está **à frente do SOTA público** em frescor-por-idade, anti-teatro e symbol-drift de domínio. Está **atrás (junto com todo mundo)** em correção-do-fato e contradição — porque essa fronteira **não tem solução determinística barata em 2026**; o melhor que existe é LLM-advisory. Nada da máquina atual está over-engineered para o problema que resolve; o risco de over-engineering está em **construir os buracos #1/#2 como gate** (recall 57% + não-determinismo = teatro garantido).

### Validação dos 3 buracos

1. **Buraco #1 (fato fresco-mas-errado) — CONFIRMADO e é fronteira da indústria.** Dosu admite textualmente *"detects drift, not correctness"*. Não é falha do oimpresso; é o limite de 2026. A única defesa real é **symbol/fact-anchoring** (o dominio-gate já faz isso pro domínio) + LLM-juiz advisory pro resto.
2. **Buraco #2 (contradição doc×doc) — CONFIRMADO, resolúvel só probabilisticamente.** Números: LLM pega ~57% (recall), erra pouco quando aponta (95% precisão). Auto-contradição num doc só é quase intratável (0,6-45%). Serve como **triagem advisory de par-a-par**, jamais como gate.
3. **Buraco #3 (limbo) — CONFIRMADO e SUBESTIMADO no impacto, mas trivial no esforço.** Medido agora: **65 drafts em `proposals/`** sem sinal de decaimento; **`dominio/` E `dominios/` coexistem** (homônimo). Isto é o buraco mais barato de fechar e o único 100% determinístico.

---

## Fase 3 — AVALIA (gaps rankeados por impacto × esforço)

Esforço em IA-pair (ADR 0106: 10x humano). Todos respeitam: `promote_by` em advisory (0275), "required=só Tier-0" (0314), determinismo (sem `Date.now`/`Math.random`), anti-teatro.

| Gap | Impacto | Esforço IA-pair | Pré-req? | Vira gate? |
|---|---|---|---|---|
| **Check P — limbo de `proposals/`** (draft >N dias sem virar aceito/recusado = 🟡; homônimo `dominio`+`dominios` = 🟡) | médio | **~30 min** | não | advisory c/ `promote_by`, determinístico |
| **Check Q — contradição de PARES ancorados** (só pares declarados: `contradicts:` no frontmatter, OU canon vs doc que se-diz-canon do mesmo tema) via LLM-juiz **advisory** | alto | ~3-4 h | Check M ok | advisory `continue-on-error`, NUNCA required (recall 57%) |
| **Check R' — fact-anchor estendido** (o padrão do dominio-gate aplicado a claims verificáveis: "React 18"→confere `package.json`; "N ADRs"→conta `decisions/`; "MemCofre"→alias-map SRS) | alto | ~2-3 h | tabela de âncoras | **pode ser required por claim** — é determinístico onde a âncora existe |
| Contradição doc×doc geral (não-ancorada, N² pares) via LLM | baixo (ruído) | ~1 dia | — | ❌ não construir — recall 57% × N² = teatro caro |
| Memória agêntica auto-consolidante (Mem0/Graphiti runtime) | baixo | semanas | — | ❌ não construir — o git-com-supersede já é isto, versionado e determinístico |

### O insight que separa "máquina que morde" de "teatro"

O buraco #1 tem **duas metades** que a indústria mistura e o oimpresso pode separar:

- **Fato verificável-por-âncora** ("React 18", "95+ ADRs", "MemCofre"): existe uma fonte-de-verdade mecânica (`package.json`, `ls decisions/`, alias-map). Isto é **determinístico e vira gate** — é o dominio-gate generalizado. **Esta metade não é fronteira; é engenharia.** É onde o ROI real está.
- **Fato verificável-só-por-julgamento** ("este parágrafo descreve o comportamento errado do FSM"): sem âncora mecânica → só LLM-juiz → advisory, recall parcial, irredutivelmente probabilístico. **Esta metade é fronteira** e deve ficar advisory pra sempre.

Os 3 erros que motivaram a pergunta (React 18/19, MemCofre/SRS, 95/230 ADRs) são **todos da primeira metade** — todos têm âncora mecânica. Logo o buraco #1 real do oimpresso **NÃO precisa de LLM**: precisa de um **Check R' de fact-anchor** que estende o padrão dominio-gate a um punhado de claims de alto tráfego (versões de stack, contagens auto-atualizáveis, nomes renomeados via alias-map). Determinístico, barato, morde de verdade.

---

## Recomendação final

**Comece pelo Check R' (fact-anchor determinístico) — alto-impacto, baixo-esforço, sem pré-req bloqueante, e evita a armadilha do teatro.** Ele resolve a metade real do buraco #1 (os 3 erros citados eram todos ancoráveis) sem tocar em LLM, então roda determinístico e pode até ser required por-claim onde a âncora é sólida. É o dominio-gate — que já funciona e é *required* — generalizado pra claims de stack/contagem/renomeação na camada de entrada.

Faça o **Check P (limbo)** junto no mesmo PR: é ~30 min, 100% determinístico, e fecha o buraco #3 (65 proposals + homônimo `dominio`/`dominios`) de graça.

**Deixe o Check Q (contradição LLM) para depois e sempre advisory** — recall 57% + não-determinismo = só ganha o direito de existir como triagem `continue-on-error` com `promote_by`, nunca gate. **Não construa** contradição doc×doc geral nem memória auto-consolidante runtime: o primeiro é teatro caro (N²×57%), o segundo já existe como git-append-only-com-supersede.

**Próxima ação hoje:** montar a **tabela de âncoras do Check R'** — 1 linha por claim verificável de alto tráfego: `padrão-no-doc → fonte-de-verdade-mecânica`. Sementes: `React \d+ → package.json (react)`, `PHP \d → composer.json`, `\d+ ADRs? → count(decisions/*.md)`, `MemCofre → alias-map (SRS)`, `Laravel [\d.]+ → composer.json`. Escopo inicial = camada de entrada (CLAUDE.md, what/why/how-oimpresso, README, GUIA-DO-SISTEMA). Aí implementa como Check P/R' em `memory-health.mjs` (mesmo padrão ratchet dos Checks C/L/N), registra no `gates-registry.json` com `terminal:advisory` + `promote_by`, e roda `--update-baseline`.
