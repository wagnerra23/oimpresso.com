---
date: "2026-07-29"
time: "14:10 UTC"
slug: kb-categoria-classificada-backfill
tldr: "A /kb/v2 servia a lista por categoria vazia: 1.628 de 1.628 nós de biz=1 com category_id NULL. O diagnóstico herdado ('falta o classificador — auto_match tem ZERO leitores') era FALSO desde 07-17 e ficou 12 dias em 2 docs canônicos. Causas reais: invocação zero + drift de seed. Backfill em prod com go do [W]: 1.628/1.628. #5017 e #5021 mergeados."
prs: [5017, 5021]
decided_by: [W]
next_steps:
  - "body_blocks no leitor — RESOLVIDO por sessão paralela no #5018 (o corpo vem do JOIN com mcp_memory_documents; a invariante Tier 0 body_blocks=NULL segue de pé)"
  - "/kb/graph continua fachada: closure Inertia::render sem props + /kb/graph/data hardcoded {nodes:[],edges:[],kpis:null}. Feature nova, escopo de Onda"
  - "KbArticleService:49 — $request->integer('category') espera int e a tela manda slug → where('category_id',0) → filtra por 0 EM SILÊNCIO. Via morta, não bloqueia a lista (o filtro é client-side)"
  - "FLAKY na lane KB (não é de ninguém desta sessão): kbActAsUser às vezes produz usuário que leva 403 em /kb/v2. Cai em testes DIFERENTES a cada run (V4 no #5017, V7 no #5008), mesmo 403≠200, mesmo placar 1 failed/14 skipped/101 passed. Suspeito: #4853 (27/jul, permissões jana.*). Vai seguir avermelhando PRs de terceiros até alguém atacar"
  - "#5015 é o único PR aberto com falha em gate REQUIRED (Casos-coverage · ratchet)"
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
  - 0093-multi-tenant-isolation-tier-0
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0101-tests-business-id-1-nunca-cliente
---

# A tela não precisava de código novo — precisava que alguém puxasse o gatilho

O chip de entrada desta sessão dizia, sobre o gap #1 do KB:

> Falta o **classificador** que lê `auto_match` — hoje com **zero leitores em PHP**.

Era falso. `KbAutoClassifierService` + `KbClassifyCommand` existem desde **2026-07-17** ([#4465](https://github.com/wagnerra23/oimpresso.com/pull/4465)), com 7 testes — incluindo cross-tenant biz=1 vs biz=99 — e registro no `KBServiceProvider:132`. A frase tinha sido escrita no `BRIEFING.md` e copiada para o `Index.v2.charter.md`, e ficou **12 dias** no ar.

O custo não foi teórico: eu comecei esta sessão recomendando "construir o classificador" como o trabalho de maior valor. Só mudou quando rodei `git grep "auto_match" origin/main -- '*.php'` antes de escrever a primeira linha.

## As duas causas reais

| # | causa | como se manifestava |
|---|---|---|
| 1 | **invocação zero** | `git grep "kb:classify"` no repo inteiro não achava schedule nem chamador. `--apply` era manual e nunca tinha rodado |
| 2 | **drift de seed** | `reference`/`comparativo` tinham regra `auto_match` no `KbSubcategoriesSeeder` desde 07-17 (decisão [W] registrada em comentário no próprio seeder) mas nunca chegaram ao banco de biz=1 → 404 nós reportados como *"nenhuma regra casa"* |

A (2) é a mais traiçoeira: o comando os classificava como **"dívida de taxonomia, não erro"** — uma mensagem correta para o caso genérico, que aqui escondia drift prod↔git.

## Recibo (CT 100 `oimpresso-mcp`, banco de prod, biz=1, 2026-07-29)

```sql
SELECT COUNT(*) FROM kb_nodes WHERE business_id = 1;                        -- 1628
SELECT COUNT(*) FROM kb_nodes WHERE business_id = 1 AND category_id IS NULL;-- 1628
```

| momento | classificáveis | sem casa |
|---|---:|---:|
| antes | 1224 | 404 (`reference` 376 + `comparativo` 28) |
| após re-rodar o `KbSubcategoriesSeeder` (idempotente, 18→20 subcats) | 1628 | **0** |
| após `kb:classify --business=1 --apply` | **1628** | 0 NULL |

Smoke em prod: lateral saiu de `Governança 0` → **`Governança 1589`** (1628 − 39 `status=deleted`), com ADR 500 · Session 557 · Referência 366 · Briefing 79 · Spec 59 · Comparativo 19. Filtro por categoria responde.

Nenhum INSERT ad-hoc: rodei o seeder que já existia. Isso **removeu** drift prod↔git em vez de criar.

## O que ficou travado por desenho, e não é bug

`body_blocks` **não** foi tocado. A invariante `is_editable=false ⇒ body_blocks IS NULL` é Tier 0 ([ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) e o `KbNodeObserver:33` lança `DomainException`. O próprio Observer aponta a saída — *"deve vir do JOIN com `mcp_memory_documents`"*. Uma sessão paralela fechou isso no [#5018](https://github.com/wagnerra23/oimpresso.com/pull/5018) pelo caminho certo, e **fundiu** a errata deste handoff no BRIEFING em vez de sobrescrever.

## Três erros meus, catalogados

1. **`exit code` do wrapper ≠ do comando.** A notificação de background disse `exit code 0`; o arquivo tinha `exit=137` (SIGKILL) — meu próprio `timeout 600` matou o `--apply` com 738/1628 gravados. Nada corrompeu porque o classificador só toca `category_id IS NULL` e retoma. Re-disparei destacado (`docker exec -d`).
2. **Medi UI antes da hidratação.** Cliquei na categoria logo após `navigate` e o estado não mudou — quase reportei o filtro como quebrado. Com a página pronta, filtra.
3. **Comparei lanes com `paths-filter` diferentes.** Afirmei que "Compras/Estoque/Ponto passam em outras branches, logo o #5008 as quebrou". Falso: essas lanes só rodam quando o PR toca os paths delas, e o header do `estoque-pest.yml` declara `ProdutoShowContratoTest`/`ProdutoIndexContratoTest` como **failing-first por desenho**. Nem #5008 nem #5018 quebraram nada — o #5018 até melhorou (15 → 11 falhas). É a mesma armadilha que eu tinha alertado no início da sessão ("verde de lane com paths-filter não é verde").

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 8 tasks, todas em REVIEW (US-COPI-123 p0 · US-TR-309/310 · US-PG-008 · US-PROD-027 · US-INFRA-023 · US-TR-305/306)
- PRs abertos ao fechar: **#5008** (4 lanes advisory vermelhas) · **#5014** (101 verdes, limpo) · **#5015** (⚠️ `Casos-coverage · ratchet` REQUIRED vermelho) · **#5018** (115 verdes, 1 lane advisory)
- Mergeados por [W] nesta janela: #5016, #5017, #5019, #5020, #5021, #5022, #5023, #5024
