---
id: requisitos-kb-briefing
module: KB
status: parcial
status_nota: "backend LIVE em prod (bridge 15-em-15min + schema + CRUD, biz=1); /kb/v2 serve kb_nodes reais desde 2026-07-17; classificação por categoria RESOLVIDA em 2026-07-29 (1628/1628 de biz=1 classificados; o classificador existia desde 07-17 mas nada o invocava, e 2 subcategorias nunca chegaram ao banco) e o bridge passou a classificar no fill (#5017); SEGUEM abertos: o leitor não mostra o corpo (body_blocks é invariante Tier 0 — vem do JOIN com mcp_memory_documents) e /kb/graph é fachada (closure sem props, /kb/graph/data hardcoded vazio)"
updated_at: "2026-07-29"
distilled_by: "reconciliação MEDIDA contra prod (CT 100 oimpresso-mcp, biz=1, 2026-07-29): §Bloqueador + §Estado real + status_nota reescritos com recibo de query. Corrige a afirmação 'auto_match tem ZERO leitores em PHP', que era falsa desde 2026-07-17 e permaneceu 12 dias — ela mandou uma sessão inteira na direção errada antes de ser medida. O resto do BRIEFING segue com a foto de 2026-07-17."
owner: W
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
  - 0093-multi-tenant-isolation-tier-0
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0101-tests-business-id-1-nunca-cliente
lifecycle: ativo
piloto: "Wagner / governança (biz=1) — dono do acervo; biz=4 (Larissa/vestuário) só 3 articles"
---

# KB Unificado — BRIEFING (estado consolidado 1 página)

**Última atualização:** 2026-07-17 — reescrito pra realidade **medida** no banco de produção (CT 100). A versão de 2026-05-16 dizia "ONDA 0+1+2+4+5 LIVE" e descrevia uma persona ("Larissa operadora gráfica") que **não existe no acervo real**. Este briefing corrige as duas mentiras.

**Owner:** [W] Wagner · **Persona real:** **Wagner / governança (biz=1)** — o acervo é 99,8% documento de governança (ADR / session / reference / spec). A "operadora de gráfica" era ficção do corpus mock.

**Status honesto:** `parcial`.
- ✅ **Backend LIVE em prod (biz=1):** schema `kb_*`, `KbBridgeFromMcpJob` populando `kb_nodes` a cada 15 min, taxonomia seeded, CRUD de artigo editável, permissions.
- ✅ **`/kb/v2` SAIU do mock (2026-07-17).** `KbController@indexV2` serve `kb_nodes` reais (+ categorias, subcategorias, `business.name`) e `KbIndexV2ContractTest` V5/V6 travam o payload. ⚠️ *A linha anterior deste briefing dizia "roda MOCK / falta o Controller `indexV2`" — ficou **stale** por 11 dias; reconciliado em 2026-07-28 pelo `sdd-from-source` contra `Modules/KB/Http/routes.php` + `KbController::indexV2`.*
- 🟡 **O leitor da V2 ainda não mostra o corpo** do documento bridgeado: `KbBridgeFromMcpJob` copia **metadata, não `body_blocks`** (limite declarado no docblock do `indexV2`). Título + excerpt, não o texto.
- 🔴 **`/kb/graph` continua fachada:** a rota é closure `Inertia::render('kb/Graph')` **sem props** e `/kb/graph/data` devolve `{nodes:[],edges:[],kpis:null}` hardcoded → cai em `_lib/mockGraphData.ts`. Nenhum Controller do KB a serve.
- ✅ **Categoria: RESOLVIDO em 2026-07-29.** A lista por categoria servia vazia porque **100% do acervo de biz=1 estava com `category_id` NULL** — não porque faltasse classificador. ⚠️ *As linhas anteriores diziam "falta o classificador — **zero leitores em PHP**"; era **stale**. O `KbAutoClassifierService` + `kb:classify` existem desde 2026-07-17 ([#4465](https://github.com/wagnerra23/oimpresso.com/pull/4465)); o que faltava era **invocação** (`git grep "kb:classify"` no repo inteiro não achava schedule nem chamador) e **2 subcategorias que nunca chegaram ao banco**.* Ver §Bloqueador pro recibo.
- 🟡 **A tela `/kb` (V3, o browser do acervo canon) ganhou seu 1º contrato executável em 2026-07-28** — antes disso não tinha nenhum. Ver [SDD](SDD-tela-kb-unificado-v1.0.md) + [`Index.casos.md`](../../../resources/js/Pages/kb/Index.casos.md).

---

## O que é

`Modules/KB/` = o **leitor consultável do conhecimento canônico da empresa**: ADRs, session logs, charters, runbooks, briefings, specs — os documentos de governança que já vivem no git e que o `KbBridgeFromMcpJob` copia (read-only) pra dentro de `kb_nodes` em produção. A tela alvo é `/kb/v2` (`kb/Index.v2.tsx`), tri-pane Cockpit V2 (categorias · lista · leitor).

**Não é** um "browser de SOPs de gráfica com dados inventados". Esse enquadramento (persona Larissa operacional, corpus Roland VS-540) era **mock** e sai — [W] 2026-07-17: *"eu quero os dados, mas com o design do KB"*.

## Estado real — pronto vs gap

| Peça | Estado | Onde |
|---|---|---|
| Schema `kb_nodes` + taxonomia (`kb_categories`/`kb_subcategories` com `auto_match` seeded) | ✅ pronto, seeded em biz=1 e biz=4 | `Modules/KB/Database` |
| Bridge git→banco (`KbBridgeFromMcpJob`) rodando em prod | ✅ LIVE (15/15 min) | `Modules/KB/Jobs/KbBridgeFromMcpJob.php` |
| CRUD de artigo editável + versões + permissions | ✅ existe | `Modules/KB/Http` / `Entities` |
| Global scope `business_id` (Tier 0) | ✅ provado (governança não vaza pro cliente) | `BelongsToBusinessTrait` |
| Troca de empresa (herda o tenant) | ✅ via `CompanyPicker` na Sidebar | `resources/js/Components/cockpit/Sidebar.tsx` |
| **Tela `/kb/v2` servindo o dado** | ✅ **serve `kb_nodes` reais** desde 2026-07-17 | `KbController@indexV2` / `Index.v2.tsx` |
| **Classificador `auto_match` → `category_id`** | ✅ **existe (2026-07-17) E é invocado (2026-07-29)** | `Services/KbAutoClassifierService` · `kb:classify` · chamado no fill pelo `KbBridgeFromMcpJob` |
| **Corpo do documento no leitor (`body_blocks`)** | 🔴 **não chega à tela** — invariante Tier 0, ver §Gap aberto | `KbNodeObserver` / `Index.v2.tsx` |
| Template de categorias por vertical | 🟡 **D6 ABERTA — [W] decide** | charter §3 |

## Os números do acervo — **este briefing NÃO os guarda** (fato derivado não se restateia)

> **Lei aplicada** (proibições §5, 2026-07-17): documento canônico não repete número que **outro sistema** sabe melhor. O dono do tamanho do acervo é o **banco** (`kb_nodes`), não este arquivo.
>
> - **O recibo datado de record** (total · por tipo · `category_id` NULL · árvore) vive no **[charter §3 do `Index.v2.tsx`](../../../resources/js/Pages/kb/Index.v2.charter.md)** — medição em prod (CT 100 `oimpresso-mcp`, biz=1), com sistema + data + query declarados.
> - **Pra re-medir** (o número envelhece à vista — se a data incomodar, re-rode, não edite):
>   ```sql
>   SELECT type, COUNT(*) FROM kb_nodes WHERE business_id = ? GROUP BY type;
>   SELECT COUNT(*) FROM kb_nodes WHERE category_id IS NULL;
>   ```
>   Roda no **CT 100** (`tailscale ssh root@ct100-mcp "docker exec oimpresso-mcp php artisan tinker"`), **nunca no CI** — o CI não tem o banco de governança.
>
> **Não invente esses números aqui nem em terceiro doc** — aponte pro charter §3 ou re-meça.

## Bloqueador — dois níveis, sem maquiar

> ⚠️ **Esta seção descrevia dois "níveis" que NÃO existiam mais.** O "Nível 0 — a tela nem lê o banco" já era falso desde 2026-07-17 (o próprio topo deste briefing o corrigia, mas a seção seguia intacta). A "causa-raiz — `auto_match` tem ZERO leitores em PHP" era falsa desde o mesmo dia. Ambas ficam registradas aqui como **erro corrigido**, não apagadas.

**O que era verdade (medido, 2026-07-29):** o filtro ancora em `n.category_id === cat.id` e **100% do acervo de biz=1 estava com `category_id` NULL** — logo, clicar qualquer categoria dava 0 linhas.

```
-- CT 100 (docker exec oimpresso-mcp), banco de prod, 2026-07-29
SELECT COUNT(*) FROM kb_nodes WHERE business_id = 1;                        -- 1628
SELECT COUNT(*) FROM kb_nodes WHERE business_id = 1 AND category_id IS NULL;-- 1628
```

**A causa real eram DUAS, e nenhuma era "falta o classificador":**

1. **Zero invocação.** `KbAutoClassifierService` + `kb:classify` existem desde 2026-07-17 ([#4465](https://github.com/wagnerra23/oimpresso.com/pull/4465)), com 7 testes e registro no `KBServiceProvider`. Mas `git grep "kb:classify"` no repo inteiro não achava **nenhum** schedule ou chamador — o `--apply` era manual e nunca tinha rodado. Mecanismo correto, ninguém puxava o gatilho.
2. **Drift de seed.** As subcategorias `reference` e `comparativo` tinham regra `auto_match` no `KbSubcategoriesSeeder` desde 2026-07-17 (decisão [W] registrada em comentário no próprio seeder) mas **nunca chegaram ao banco de biz=1** — logo 404 nós (376 `reference` + 28 `comparativo`) eram reportados como *"nenhuma regra casa"*, o que parecia dívida de taxonomia e era drift prod↔git.

**Resolvido em 2026-07-29** — recibo do dry-run, com a projeção batendo exata:

| momento | classificáveis | sem casa |
|---|---:|---:|
| antes | 1224 | 404 (`reference` 376 + `comparativo` 28) |
| após re-rodar o `KbSubcategoriesSeeder` (idempotente, 18→20 subcats) | 1628 | **0** |
| após `kb:classify --business=1 --apply` | **1628 classificados** | 0 NULL |

Smoke em prod: a lateral saiu de `Governança 0` para `Governança 1589` (1628 − 39 `status=deleted`), com ADR 500 · Session 557 · Referência 366 · Briefing 79 · Spec 59 · Comparativo 19, e o filtro por categoria responde.

**Para não decair:** o `KbBridgeFromMcpJob` roda a cada 15 min criando nós novos, e `bridgeDocument()` nunca setou `category_id` — sem intervenção a lista voltaria a esvaziar sozinha. O Job passou a chamar o `KbAutoClassifierService` no fim do run ([#5017](https://github.com/wagnerra23/oimpresso.com/pull/5017)), com bite-test e controle negativo.

**Segue aberto (não é sobre categoria):** `KbArticleService` recebe `->integer('category')` mas a tela manda slug → filtra por 0 em silêncio. Não bloqueia a lista (o filtro por categoria é client-side sobre `props.nodes`), mas é uma via morta a fechar.

## Taxonomia — 1 KB com filtro, dois eixos ([W] 2026-07-17)

Já **seeded** no banco (não inventar, não revogar seeder). Contagens exatas no charter §3 — aqui só a forma:

- **Eixo 1 — `Governança` (interno, igual pra todo business):** uma categoria cujas **subcategorias são os tipos de documento** (ADR · Session · Charter · Runbook · Briefing · Spec), cada uma com `auto_match` por `type` já gravado. É o eixo que a tela serve hoje. Não vaza pro cliente (global scope; ver charter §3 + UC-05: governança de biz=1 não aparece pra outro business).
- **Eixo 2 — conteúdo do cliente (template POR VERTICAL):** Produção · Equipamentos · Pré-impressão · Atendimento · Fiscal · Sistema · Pessoas. Seeded **idêntico** em biz=1 e biz=4 — e todo de **gráfica**, o que é ficção pra biz=4 (vestuário). **D6 ABERTA:** [W] define as categorias por vertical (ou o agente propõe gráfica/vestuário/oficina e [W] corta). Não bloqueia o Eixo 1.

**Invariante:** a lateral é a árvore `kb_categories → kb_subcategories` **do business**; o tipo do documento é subcategoria de `Governança`, não a categoria raiz. (A antiga formulação "categoria = `kb_nodes.type`" foi **derrubada** — achatava a árvore e apagava o eixo do cliente.)

## Indicador da empresa ativa (NOVO-A) + categoria vazia oculta (NOVO-B)

- **NOVO-A** ([W]: *"qual KB que o cliente está filtrando? isso deveria estar ao lado do buscar"*): ao lado da busca vai um **RÓTULO** da empresa ativa — leitura do `ativa` do `CompanyPicker` (rodapé da Sidebar). **Não é seletor de eixo** e **não é seletor de empresa** (o KB não tem um; herda o tenant via `SetSessionData` → global scope). Cliente normal (Larissa) nunca troca — ela **é** a única empresa dela.
- **NOVO-B** (achado da medição): a lateral **só mostra categoria com ≥1 documento** pra empresa ativa. Medido: biz=4 tem a categoria "Governança" seeded com **0 documentos** → categoria fantasma. Regra elimina a promessa de conteúdo que o multi-tenant nunca deixa aparecer.

## Inviolabilidades Tier 0 (sem ADR mãe nova é proibido)

- `business_id` global scope em TODAS as tabelas `kb_*` — provado que governança (biz=1) não vaza pro cliente ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- `kb_nodes` bridge canônico (`is_editable=false`) NUNCA versiona local — vem só do git ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)).
- IA/RAG roteia via `Modules/Jana/Ai/` ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) — não criar provider novo.
- Pest biz=1 canônico + cross-tenant biz=99 fictício; **nunca biz=4** (ROTA LIVRE prod) em teste que escreve ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- `Index.v2.tsx` segue MWART 5 fases ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) + gate visual screenshot [W] antes do merge ([ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)).

## Decisões [W] já tomadas (input, não pergunta)

- **D1** — a tela serve os DOCUMENTOS REAIS (não SOP inventado).
- **D3** — 1 KB com filtro, dois eixos (Governança = tipos de doc via `auto_match`; + conteúdo do cliente). **Não** é "categoria = type".
- **NOVO-A / NOVO-B** — ver seção acima.

## Decisão ABERTA (bloqueia o backfill, não a leitura de governança)

- **D6** — template de categorias por vertical (Eixo 2). Seeded hoje é tudo gráfica; biz=4 é vestuário. [W] define ou corta uma proposta. Enquanto aberta, o classificador do Eixo 1 (Governança) já pode ser construído — é o que a tela serve.

## Arquivos canônicos relacionados (ler ANTES de tocar código)

- [Index.v2.charter.md](../../../resources/js/Pages/kb/Index.v2.charter.md) — **lei da tela + recibo do acervo (§3)** · charter_version 3, DRAFT aguardando [W].
- [SPEC.md](SPEC.md) — US-KB-001 (o "ver" dos ADRs) e demais US.
- [SCHEMA-DB-V1.md](SCHEMA-DB-V1.md) — contrato das tabelas `kb_*`.
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — benchmark de mercado.
- `Modules/KB/Jobs/KbBridgeFromMcpJob.php` — o bridge que popula `kb_nodes` (e onde o `category_id` **não** é setado hoje).
- `resources/js/Pages/kb/Index.v2.tsx` — a tela (hoje MOCK) + `Modules/KB/Http/routes.php` (rota sem props).

## Riscos (re-validar mensalmente)

- **R1** Tela declarada "pronta" enquanto serve MOCK — mitigado por este briefing + smoke real pós-merge (R1 do protocolo).
- **R2** Backfill de `category_id` toca 1 nó por vez sob global scope — job usa `withoutGlobalScopes()` + `business_id` explícito (bridge já faz assim).
- **R3** Multi-tenant leak via bridge — mitigado por `business_id` global scope (KbNode via BelongsToBusinessTrait; prova em charter §3 + UC-05).
- **R4** Restatement de número de acervo em doc canônico (drift) — mitigado pela lei §5: apontar pro charter §3 / re-medir, nunca copiar o número.