---
date: "2026-07-17"
topic: "Grade parcial de reguas - observabilidade-agente re-pontuada pos-chips (6,5 para 7,0), padrao 7/9 repetido"
authors: [C]
prs: [4425, 4427, 4444, 4477]
outcomes:
  - "Dimensão observabilidade-agente re-pontuada 6,5 → 7,0 (+0,5 atribuível aos chips de hoje; regra 12 da skill) — rodada parcial adversarial (14 agentes, 2,0M tokens)"
  - "Padrão 7/9 repetido: 6 das 9 fraquezas da pesquisa foram refutadas/parciais no repo vivo — a metade 'observar' (traces/custo/painéis/drift) está ~8; a metade 'avaliar qualidade ONLINE sobre prod' é o gap real ~5"
  - "Bug do próprio script da grade: args.dimensoes como string virava 'undefined — undefined' e media a dimensão errada em silêncio (1,77M tokens gastos em governança por engano) — corrigido (#4477)"
  - "OBSERVABILITY.md destaleado: 6 das 7 linhas 'PLANEJADO' estão LIVE (nomes reais ≠ doc; verificado file:line) + 2 spans bônus não catalogados — o doc stale foi a causa de metade dos falso-negativos da grade"
  - "Indexação de observabilidade-agente no mapa JÁ existe: ADR 0333 (proposto) emenda a 0330 via supersedes_partially — NÃO criei paralelo (§5)"
related_adrs:
  - 0330-mapa-dos-niveis-estado-real-2026-07-constituicao
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0051-schema-proprio-adapter-otel-genai
  - 0132-langfuse-self-host-ct100
---

# Grade parcial — observabilidade-agente (6,5 → 7,0)

## TL;DR

re-pontuei a dimensão `observabilidade-agente` (baseline 6,5 na grade v2 de 2026-07-17) depois que os chips do heartbeat shiparam. Nota **7,0** (+0,5). O movimento é metade chip (o heartbeat fechou a fraqueza "zero heartbeat" nomeada) e metade correção de retrato (a verificação revelou que a dimensão estava sub-medida). O teto real (~7) é imposto pelo único gap dominante: **zero avaliação de qualidade ONLINE sobre a saída de produção** — tudo é batch. No caminho, achei e corrigi um bug no próprio script da grade.

## A nota (rodada parcial adversarial, base origin/main fresco)

- **Placar:** 0 acima-de-categoria · **1 à-frente-por-integração** · 0 empatada · 0 refutada.
- **Diferencial (DIFERENCIAL_SISTEMA):** `business_id` obrigatório em todo span + redação PII→sha256 default-ON provada por CI (#4427). A peça tem par pleno (OneUptime per-tenant OTel, fev/2026); o TODO (observabilidade do agente-codador aplicada a si mesmo, num ERP vertical multi-tenant BR vivo, com §5 recursivo) não tem peer publicado. Integração, não categoria.

## O padrão 7/9 se repetiu (6 de 9 fraquezas refutadas/parciais no repo vivo)

| Fraqueza da pesquisa | Veredito | Nota |
|---|---|---|
| 4 Agents não traçados | refutada (`LangfuseAgentTelemetryListener`, 2026-07-02) | 6,5 |
| 5 spans "planejados" | refutada — todos LIVE, doc stale | 8,0 |
| Sem baseline de drift | refutada (`jana-ragas-canary` + `observability:aggregate-daily`) | 8,5 |
| Painel vivo não entregue | refutada — 3 painéis vivos (`/admin` W10, `/admin/rag-quality`, `/admin/governance-v4`) | 8,0 |
| Custo depende do Langfuse | refutada — custo vem do DB (`CustosService`) | 7,0 |
| Sem push Slack/PagerDuty | parcial — detecta, não empurra | 7,0 |
| **Zero eval de qualidade ONLINE** | **gap real** — tudo batch | 5,0 |
| Alucinação só batch | gap real — `recordScore` existe, nunca invocado no caminho servido | 6,0 |
| **Loop trace→dataset não fecha** | gap real — 2 pontas existem, falta o meio + trava LGPD (biz=4 não vira fixture, ADR 0101) | 5,0 |

**Leitura:** a metade "observar o fluxo" está no teto; a metade "avaliar a qualidade em prod, ao vivo" é o gap. O baseline 6,5 sub-mediu porque `observabilidade-agente` **não aparece no mapa 0330** (`grep` = 0) — é re-sub-medida a cada rodada.

## Chips (o que ainda falta)

1. **Eval de qualidade ONLINE sobre amostra de prod** (LLM-judge inline) — a fiação `recordScore` já existe, é *wire* não batch novo. Alto.
2. **Spans `execute_tool` + waterfall aninhado** nos 4 Agents. Alto.
3. **Loop trace→dataset** — só com biz=1 dogfooding (biz=4 proibido, ADR 0101). Alto, gated.
4. **Push dos sinais já detectados** pra Slack/PagerDuty — só o transporte, NÃO detector novo (§5). Médio.
5. ~~**Religar o heartbeat em prod** (#4444 advisory/off)~~ — **REFUTADO em prod (ver §Verificação abaixo): o Langfuse já está ON, o heartbeat mede 456 traces/24h.** Chip morto.
6. **Destalear OBSERVABILITY.md** — FEITO nesta sessão (ver abaixo).

## Verificação em prod (2026-07-17, autorização [W] "pode ligar")

O [W] autorizou "ligar" (o wire do juiz inline + religar o heartbeat que a grade dizia off). Antes de tocar config de prod, verifiquei o oráculo real (o `.env` do Hostinger + o próprio health-check em prod) — **e a grade estava errada, pela 2ª vez pelo mesmo vício**:

- `.env` de prod: **`LANGFUSE_ENABLED=true`** + HOST/PUBLIC_KEY/SECRET_KEY setados (DISPATCH=`sync`). O Langfuse **NÃO está desligado**.
- `jana:health-check` em prod: `langfuse_trace_uptime_24h` = **ok=true, value=456, advisory=false** — *"456 traces recebidos pelo Langfuse em 24h"*. O heartbeat (shipado hoje, #4425) **está vivo e verde em prod**, medindo a fonte real.
- `custo_brain_b_24h` = zero (0 tokens Brain B/24h — consistente com o brief "Brain B 0/50"; os 456 traces vêm de brief/kb/retrieval, não do chat).

**Por que a grade errou:** os agentes inferiram "desligado" do *default* do `config/langfuse.php` (`env('LANGFUSE_ENABLED', false)`) sem checar o `.env` real de prod — a **lápide §5 de hoje** ("deduzir o que roda parseando código quando o runtime sabe") reincidindo dentro da própria grade. E eu repeti o erro ao sintetizar. Corrigido: o chip #5 morre; a "prova de fluxo" da dimensão está **ligada e funcionando**. **Consequência real:** o único "ligar" que sobra (o eval de qualidade ONLINE, chip #1) é **código**, não flag — e carrega decisões de produto ([W]): taxa de amostragem, custo do juiz LLM, threshold de alerta.

## Rejeitados → §5

- Detector de drift **novo** (duplica `jana-ragas-canary` + `custo_brain_b_24h`). O chip é o push, nunca novo detector.
- Loop trace→dataset com **biz=4** (LGPD/ADR 0101).
- **Emenda ADR nova pra "indexar observabilidade-agente no mapa"** — a ADR 0333 (proposto) JÁ faz isso via `supersedes_partially: [0330]`. Criar outra = paralelo (§5). A ação real é a **ratificação da 0333** (HITL Wagner), não um mecanismo novo.

## O bug do próprio script (mexeu, registra)

A 1ª tentativa de rodar a grade parcial passou `args.dimensoes: ["observabilidade-agente"]` (string), mas o script esperava `[{key, escopo}]`. `d.key`/`d.escopo` viraram `undefined` → o prompt do pesquisador ficou `SUA DIMENSÃO: undefined — undefined` → o agente se auto-curou medindo a dimensão **transversal** (governança executável) → **a grade mediu a dimensão errada por 1,77M tokens, com cara de completa**. É a mesma classe da truncagem silenciosa que este script já teve. Corrigido em #4477: o script resolve string→objeto do default (+ `log()` de aviso se a key não existe), e o escopo stale de observabilidade-agente foi atualizado.

## Destaleamento do OBSERVABILITY.md

A tabela "Spans canônicos PLANEJADOS" afirmava `não-live` sobre 6 spans que estão LIVE (verificado por mim, file:line — não pelo relato do agente): `jana.context.para_business` (ContextSnapshotService:21), `jana.brief_diario.snapshot` (BriefDiarioService:46), `jana.apuracao.run` (ApuracaoService:30), `jana.health.snapshot` (HealthSnapshotService:36), `jana.profile.distill` (ProfileDistiller:52), `jana.semantic_cache.buscar` (SemanticCacheService:59). **Os nomes reais diferiam dos do doc** (o doc dizia `jana.context.snapshot` etc). Achei 2 spans bônus não catalogados (`jana.contextual_retrieval.contextualize`, `jana.contextual.chunk`). Só `jana.ragas.judge` segue PLANEJADO (batch, ADR 0318). O doc stale foi a causa de metade dos falso-negativos da grade — corrigido com nomes reais + âncoras file:line.

## Nota de método

Rodada parcial (`args.dimensoes`) em worktree `origin/main` fresco (`_reguas-obs-20260717`). O agente `grade-final` bateu no limite de sessão (`grade: null`) — a síntese foi escrita à mão a partir dos 13 resultados no `journal.jsonl` (todos os agentes de pesquisa/refutação/integração/verificação completaram). A grade de **governança executável** (7,6) da rodada acidental fica como retrato de brinde, não parte desta.
