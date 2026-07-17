---
page: /kb/v2
component: resources/js/Pages/kb/Index.v2.tsx
controller: Modules\KB\Http\Controllers\KbController@indexV2
route: kb.v2
status: draft
owner: wagner
parent_module: KB
related_us: [US-KB-001]
persona_principal: Wagner / governança (1440px desktop)
persona_secundaria: Larissa / operacional (1280px balcão) — só quando existir SOP escrito à mão
charter_version: 4
charter_at: 2026-07-17
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central # proposta
  - 0039-ui-chat-cockpit-padrao
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0093-multi-tenant-isolation-tier-0
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
related_prototype: prototipo-ui/cowork/kb-page.jsx
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/kb-page.jsx
  blueprint_screenshot_approval: pendente (gate F1.5)
  derived_screens: [Index.v2]
  divergence_from_blueprint: "tri-pane sidebar+lista+leitor (port direto JSX→TSX)"
---

# Charter — `kb/Index.v2.tsx` · v4 **DRAFT (aguarda [W])**

> **O que mudou da v1.0 (2026-05-16):** a v1.0 descrevia uma tela de **SOPs de gráfica com dados
> inventados** ("fallback MOCK_NODES" era Goal 7). [W] 2026-07-17: *"eu quero os dados, mas com o
> design do KB"*. A v2.0 passou a descrever a MESMA tela servindo os **documentos canônicos reais**.
> O desenho não muda; a fonte de dados muda — e é isso que a torna verdadeira.
>
> ### 🟢 O que a v4 ACRESCENTA (2026-07-17 — [W] decidiu 2 pontos de design, medidos antes de escrever)
>
> Duas decisões novas, ambas ancoradas em `SELECT` no banco de produção (recibo §3), nenhuma regride
> o que a v3 já acertou:
>
> 1. **Indicador da empresa ativa ao lado da busca (NOVO-A)** — [W]: *"qual KB que o cliente está
>    filtrando? isso deveria estar ao lado do buscar"*. É **rótulo**, não seletor: a medição provou
>    que governança não vaza entre business (`adr` biz=1 = 498, biz=4 = 0), logo não há "eixo" pra
>    trocar. §2-bis.
> 2. **Categoria vazia não aparece na lateral (NOVO-B)** — a medição achou biz=4 com a categoria
>    `Governança` (id 16) **seeded com 0 documentos**. Categoria fantasma promete conteúdo que o
>    multi-tenant nunca deixa aparecer. Regra: lateral só mostra categoria com ≥1 doc pra empresa
>    ativa. Goal 9 + Anti-hook.
>
> ### 🔴 O que as revisões v2/v3 corrigem da v2.0 (mergeada com erro em #4393/#4396)
>
> A v2.0 foi escrita **medindo o disco** (`git ls-files`) pra descrever uma tela que **lê o banco**.
> Todos os números da §3 estavam errados (3.016 → o número real é o do banco, recibo §3; "237
> contratos de tela" → **0**; "152 receitas" → **11**), e o invariante *"categoria = `kb_nodes.type`"*
> **achatava a árvore e apagava o eixo do cliente**. A v2.1 mede `kb_nodes` e adota a taxonomia que
> **já está seeded** ([W]: *"1 KB com esse filtro"*). Três coisas que só apareceram por medir o lugar
> certo:
>
> 1. **A quase totalidade dos nós tem `category_id` NULL** (recibo §3) → a tela nasceria **vazia**.
> 2. **O `auto_match` já existe como dado e tem ZERO leitores** → falta o classificador, não o modelo.
> 3. **O gate NÃO reprovaria o Controller** → a ordem da §8-bis estava invertida e custaria 3
>    aprovações [W] onde 1 basta.
>
> Achado por revisão adversarial (3 lentes + debate + juiz, 2026-07-17), confirmado por `SELECT`.

---

## 1. Em uma frase

**O leitor dos documentos da empresa, num layout de três colunas** — categorias à esquerda, lista de
documentos no meio, documento aberto à direita — com busca instantânea e `⌘K`.

> **A US que esta tela atende — [US-KB-001](../../../../memory/requisitos/KB/SPEC.md):** *"Como Wagner
> governance, **quero ver** os ADRs do projeto como nós navegáveis, **para** consultar dependências
> sem grep cego no filesystem."* O **backend dela já está ✅ LIVE** (o `KbBridgeFromMcpJob` popula
> `kb_nodes` em prod há meses) — **o que falta é o "ver"**. Esta tela É o ver. Sem ela, a US está
> entregue pela metade: o dado existe, ninguém enxerga.
>
> A tela também **encosta** em US-KB-004 (trilhas) e US-KB-005 (troubleshooter) — os diálogos existem
> na header —, mas o contrato aqui é a **001**; as outras têm charter próprio quando saírem do mock.

## 1-bis. O nome muda junto (achado do encaixe no template, [W] aprovou o mockup)

| | Hoje | Vira |
|---|---|---|
| **Título** | "Procedimentos Operacionais Padrão" | **"Base de conhecimento"** |
| **Menu / breadcrumb** | Conhecimento › **SOPs** | Conhecimento › **Documentos** |
| **Subtítulo** | "18 SOPs · … · **MOCK (Agent A pendente)**" | "«N» documentos · **atualizado há «N» min**" |

> **Por quê:** "SOP" (procedimento operacional) só fazia sentido com o corpus de gráfica. Servindo
> ADR/sessão/charter, o nome vira mentira de rótulo — a pessoa lê "Procedimentos" e encontra decisão
> de arquitetura. E o subtítulo troca o **aviso de que é falso** pelo **frescor do bridge**, que é o
> número que passa a importar quando o dado é real. **O «N» do subtítulo é lido vivo** (o `COUNT`
> escopado por business) — o charter não o guarda (mesma lei da §3).
>
> ⚠️ **Toca o menu do ERP** (`Modules/KB/Http/Controllers/DataController.php`) — é produto, [W] confirma.

## 2. O que a tela mostra (isto é o contrato)

Os **documentos canônicos que já existem** no repositório e que um robô (`KbBridgeFromMcpJob`) copia
pra dentro do banco **a cada 15 minutos, hoje, em produção**. São os documentos de verdade da empresa:
decisões de arquitetura, registros de sessão, contratos de tela, receitas operacionais, resumos de
módulo, especificações.

**Não mostra SOP inventado.** O corpus de gráfica (Roland VS-540 / HP Latex) que a tela exibe hoje é
ficção e **sai** — a persona "operadora de gráfica" não existe no cliente real (o piloto biz=4 é loja
de **vestuário**).

## 2-bis. O indicador da empresa ativa (ao lado da busca) — **NOVO-A** ([W] 2026-07-17)

Ao lado da caixa de busca, um rótulo curto: **"Buscando em: «empresa ativa»"** (ex.: *Buscando em:
Larissa Comércio de Artigos do Vestuário*). Responde à pergunta que [W] fez olhando o mockup —
*"qual KB que o cliente está filtrando? isso deveria estar ao lado do buscar"*.

**É rótulo, não controle.** Três coisas que a distinguem, todas medidas:

1. **Não é seletor de empresa.** A troca de empresa **já existe e vive fora do KB** — no
   `CompanyPicker` no topo do rodapé da Sidebar (`resources/js/Components/cockpit/Sidebar.tsx`, def.
   ~L342; a corrente é `businesses.find((b) => b.ativa)`, L353). O KB **herda** esse tenant via
   `SetSessionData` → `session('user.business_id')` → global scope do `BelongsToBusinessTrait`.
   Trocar de empresa lá re-escopa **toda** query do KB automaticamente. Duplicar um seletor dentro
   do KB criaria um **segundo oráculo** de "empresa ativa" divergente do canônico da Sidebar —
   proibido (é a mesma doença de restatear o que outro sistema já sabe, §3).

2. **Não é seletor de eixo.** Não existe um botão pra "ver governança" vs "ver conteúdo do cliente",
   porque **governança não vaza entre business**. Prova medida (recibo §3): `adr` em biz=1 = **498**,
   em biz=4 = **0**; biz=4 (Larissa) só enxerga `article` = 3. O global scope Tier 0 (ADR 0093)
   garante que Larissa nunca vê nenhum dos 1.412 nós de governança de biz=1 — não há eixo a
   selecionar. O rótulo só **nomeia** o recorte que o global scope já impôs.

3. **Cliente normal nunca troca.** Larissa **é** a única empresa dela (biz=4). O rótulo é informação,
   não convite a mexer.

> **Onde o rótulo lê a empresa:** do mesmo `ativa` que o `CompanyPicker` expõe (a empresa da sessão),
> não de uma prop nova inventada no KB. Um valor, uma fonte.

## 3. As categorias (painel esquerdo) — **o charter NÃO guarda número** (v3, mantida)

> ### ⚖️ Lei aplicada aqui: *fato derivado não se restateia — aponta pro dono, ou carrega recibo*
>
> **O charter não repete número que outro sistema sabe melhor.** A população da tela é `kb_nodes`,
> filtrada por `business_id`. **O dono do número é o banco** — não este arquivo.
>
> **Por que a regra existe (o caso que a pariu, 2026-07-17):** a v2.0 escreveu "3.016 documentos"
> à mão, contando `git ls-files` (**o disco**), pra descrever uma tela que **lê o banco**. Não foi
> alucinação: o agente rodou uma tool de verdade e reportou fielmente — **o oráculo é que era o
> errado**. O número entrou com o selo mais alto de confiança ("saída direta de tool") sendo saída
> direta de **outro sistema**. Os 3 documentos do trio ficaram **coerentes entre si** e divorciados
> do mundo; a v2 corrigiu os números — mas **manteve o lugar onde mentir**. A v3 **removeu o lugar**.

### Onde perguntar (o dono)

```sql
-- população da tela (o que a lateral conta), por business:
SELECT type, COUNT(*) FROM kb_nodes WHERE business_id = ? GROUP BY type;
-- o bloqueador (ver abaixo):
SELECT COUNT(*) FROM kb_nodes WHERE category_id IS NULL;
-- a árvore da lateral (e o que fica vazio — ver NOVO-B):
SELECT c.slug, c.label, COUNT(n.id) AS docs
  FROM kb_categories c
  LEFT JOIN kb_nodes n ON n.category_id = c.id AND n.business_id = c.business_id
 WHERE c.business_id = ?
 GROUP BY c.id;
SELECT s.slug, s.label, s.auto_match FROM kb_subcategories s;
```

Onde roda: **CT 100** (`tailscale ssh root@ct100-mcp` → `docker exec oimpresso-mcp php artisan
tinker`), lendo o banco de produção. **Nunca no CI** — o CI não tem o banco de governança; um "gate"
que fingisse medir isso lá seria teatro.

### 🧾 Recibo (não é afirmação — é medição datada, com o sistema declarado)

| campo | valor |
|---|---|
| **sistema medido** | `kb_nodes` no banco de **produção** `u906587222_oimpresso` (host srv1818.hstgr.io), lido via CT 100 container `oimpresso-mcp` |
| **quando** | 2026-07-17 |
| **quem** | [CC], via `tailscale ssh ct100-mcp → docker exec oimpresso-mcp php artisan tinker` |
| **origin/main** | `aaed49e156` |
| **total** | **1.415** (biz=1: 1.412 · biz=4: 3) |
| **por tipo** | `adr` 498 · `session` 456 · `reference` 366 · `spec` 62 · `comparativo` 19 · `runbook` 11 · `article` 3 |
| **`category_id` NULL** | **1.412 de 1.415** (= exatamente todo o biz=1) |
| **biz=4 vê** | só `article` = 3, todos **categorizados** (producao 1 · equipamentos 1 · fiscal 1) |
| **governança não vaza** | `adr` biz=1 = 498 · biz=4 = **0** (prova do global scope Tier 0) |
| **árvore** | 16 categorias (8+8) · 36 subcategorias (18+18) — mesmo seed em biz=1 e biz=4 |

> **O recibo envelhece — e tem que envelhecer à vista.** Ele diz *"em 17/07 o banco respondia
> isso"*, não *"a tela tem 1.415 documentos"*. A segunda forma é a que apodrece calada. Se a data
> incomodar quem lê, **re-rode a query** — não edite o número. (O "1.408/1.405" que a v3 carregava
> ficou stale: o acervo sincroniza vivo pelo webhook GitHub→MCP; a medição fresca deu 1.415/1.412.)
>
> **Gap de ingestão** (some da lateral, PR próprio): parte do acervo do **disco** não chega ao
> **banco**. O tamanho do gap tem dois donos distintos — o disco (`git ls-files 'memory/**/*.md' | wc -l`)
> e o banco (o recibo acima) — então **não é um número que este charter guarda**: re-meça os dois lados
> quando precisar. O que é estrutural (não conjuntural, logo pode ficar aqui): o `by type` do recibo
> **não lista** `charter`, `briefing` nem `handoff` porque chegam **0** — `KbBridgeFromMcpJob` não os
> coleta (`charter`/`briefing` vivem fora de `memory/`; `handoff` não está em `bridgeableTypes()`;
> `runbook` é descartado pelo basename `RUNBOOK-*.md`). **Esses são fatos de CÓDIGO** (o que o coletor
> aceita), verificáveis no `KbBridgeFromMcpJob` — não contagens. **Não invente número de gap em outro
> doc — re-meça os dois donos.**

### 🧊 O bloqueador real: **`category_id` NULL na quase totalidade** (ver recibo)

`Index.v2.tsx:147` filtra `n.category_id === cat.id`. Com o campo nulo em 1.412 de 1.415 (recibo),
**toda categoria de biz=1 renderiza zero linhas** — a tela nasceria **vazia**, e o CI passaria
**100% verde** (os testes olham a *prop*, que vem cheia; o pixel não olha a tela; o único teste que
descrevia o estado de dado era revogado no mesmo commit). Não é detalhe de implementação: é **o**
item. (biz=4 é a exceção que confirma: os 3 `article` têm `category_id` → 3 categorias com 1 doc.)

### A taxonomia REAL — dois eixos, 1 KB com filtro ([W] 2026-07-17)

**Já está seeded no banco.** Não precisa inventar nem revogar seeder nenhum:

**Eixo 1 — `Governança` (interno, igual pra todo business):** uma categoria, com os **tipos de
documento** como subcategorias, cada uma já sabendo se classificar:

| Subcategoria | Regra JÁ gravada em `kb_subcategories.auto_match` |
|---|---|
| ADR (Decisão Arquitetural) | `{"field":"type","op":"=","value":"adr"}` |
| Session Log | `type = session` |
| Page Charter | `type = charter` |
| Runbook operacional | `type = runbook` |
| Briefing executivo | `type = briefing` |
| Spec / US-XXX-NNN | `type = spec` |

**Eixo 2 — conteúdo do cliente (template POR VERTICAL):** `Produção` (Plotter/Impressão ·
Corte/Acabamento · Instalação) · `Equipamentos` · `Pré-impressão` · `Atendimento` · `Fiscal` ·
`Sistema` · `Pessoas`. Hoje **16 categorias / 36 subcategorias** seeded (8 por business, biz=1 e 4).

> **Invariante v2.1 (substitui o da v2.0):** a lateral é a árvore `kb_categories` →
> `kb_subcategories` **do business**, e o tipo do documento é **subcategoria de `Governança`** —
> não é a categoria raiz.
>
> **A v2.0 dizia "categoria = `kb_nodes.type`". DERRUBADO** — achatava tudo em 8 categorias de tipo
> e **apagava o eixo do cliente inteiro**. Pior: matava o `auto_match` citando o exemplo errado dele
> (`field:'equip'`, do corpus falso) quando o mecanismo **certo** (`field:'type'`) já estava seeded.
> Matei o mecanismo certo pelo exemplo errado. ([W] 2026-07-17: *"tipo de documento, e outro por
> tipo de conteúdo do cliente? eu quero dois KB ou 1 com esse filtro"* → **1 KB com filtro**: são a
> mesma árvore, `Governança` é uma categoria como as outras.)

> ### ⚠️ O gap NÃO é o modelo — é que **`auto_match` tem ZERO leitores**
>
> A regra existe como **dado** e nenhuma linha de PHP a lê. `KbBridgeFromMcpJob::bridgeDocument()`
> preenche 9 campos e **nenhum deles é `category_id`**. Por isso 1.412 nós sem categoria: não falta
> taxonomia, falta **o classificador que aplica a taxonomia**.
>
> **Fechar isso = (a)** serviço que lê `auto_match` e escreve `category_id`; **(b)** backfill dos
> 1.412; **(c)** o bridge passa a classificar no fill; **(d)** corrigir `KbArticleService:49`
> (`$request->integer('category')` espera int, a tela manda **slug** → `where('category_id',0)` →
> zero **em silêncio**). Isso é **pré-requisito do Controller**, não paralelo. **A equipe especifica
> o classificador; não o implementa aqui** (gated em D6).

> ### 🔴 Decisão [W] ABERTA — o template por vertical
>
> As 7 categorias de cliente estão seeded **idênticas** em biz=1 e biz=4, e são todas de **gráfica**
> (Plotter · Corte · Pré-impressão). Mas **biz=4 é a ROTA LIVRE — vestuário**. É a mesma ficção do
> corpus mock, agora na taxonomia. O eixo `Governança` é o único igual pra todos (é interno).
>
> **Pendente:** [W] define as categorias por vertical, ou o agente propõe um jogo (gráfica ·
> vestuário · oficina) e [W] corta. **Não bloqueia** o eixo `Governança` (que é o que a tela serve
> hoje: 1.412 documentos, todos de governança).

## 4. O que dá pra fazer (Goals)

1. **Ler** um documento inteiro no painel direito, sem recarregar a página.
2. **Navegar por categoria** — clicar em "Decisões" e ver só os ADRs, com a contagem certa.
3. **Buscar** por título/etiqueta/autor, com resposta enquanto digita.
4. **`⌘K`** abre a paleta de comando; `Esc` fecha; `/` foca a busca.
5. **Favoritar** um documento e ele continuar favorito depois.
6. **Ver o que está velho** — o painel de saúde mostra o que precisa de revisão.
7. **Seguir link entre documentos** — um ADR que cita outro abre com um clique.
8. **1280px sem barra de rolagem horizontal** (Larissa, balcão).
9. **Ver, ao lado da busca, em qual empresa está buscando** (NOVO-A) — rótulo lido da empresa ativa
   da sessão, casado com o `CompanyPicker` da Sidebar; a troca de empresa continua sendo lá, não no KB.
10. **A lateral só mostra categoria que tem documento pra empresa ativa** (NOVO-B) — categoria seeded
    mas vazia (ex.: `Governança` de biz=4, 0 docs) **não aparece**; não promete o que o multi-tenant
    nunca deixa entregar.

## 5. O que a tela NÃO faz (Non-Goals)

> [W] aprova esta lista. Cada item vira teste.

- **Não edita** documento canônico. Eles vêm do git — a fonte é o repositório, e a tela é **leitura**.
  Editar aqui criaria duas verdades. (Documento escrito à mão, editável, é outro assunto — §7.)
- **Não substitui o `/kb` atual** sem decisão explícita — ver §7.
- **Não tem seletor de empresa próprio** — a troca é no `CompanyPicker` da Sidebar; o KB só **exibe**
  qual é a ativa (NOVO-A).
- Não faz CRUD de trilhas/troubleshooters (charters próprios).
- Não carrega 1000+ documentos sem virtualização.
- Não sincroniza em tempo real.

## 6. O que a tela NUNCA pode fazer (Anti-hooks — viram teste que bloqueia merge)

- **NUNCA mostrar documento de outro business** (multi-tenant Tier 0 — ADR 0093).
- **NUNCA mostrar categoria sem documento pra empresa ativa** (NOVO-B) — categoria fantasma promete
  conteúdo que o global scope nunca libera (medido: `Governança` seeded em biz=4 com 0 docs). A
  lateral filtra por `docs ≥ 1`.
- **NUNCA renderizar um seletor de empresa dentro do KB** (NOVO-A) — o indicador é rótulo de leitura;
  a troca vive no `CompanyPicker` da Sidebar. Dois seletores = dois oráculos divergentes.
- **NUNCA afirmar sucesso de uma ação que não aconteceu.** Se o botão não persiste, o aviso diz que
  é demonstração. (Isto está aqui porque **aconteceu**: 4 botões respondiam *"Artigo re-verificado e
  marcado como fresco"* sem gravar nada — ver `casos.md` UC-KBV2-10.)
- NUNCA escrever no banco ao abrir a tela (ler é ler).
- NUNCA disparar Jobs, e-mail, WhatsApp ou IA ao abrir — IA só na ação explícita "Perguntar ao KB".
- NUNCA registrar PII em log/auditoria.
- NUNCA usar cor crua (`bg-blue-100`) no lugar de token do Design System.

## 7. Decisões [W] — estado em 2026-07-17

| # | Decisão | Status |
|---|---|---|
| **D1** | "Os dados" = os documentos canônicos (ADR/session/charter/…) | ✅ **RESPONDIDA** — [W]: *"eu quero os dados, mas com o design do KB"*, sobre o mockup que exibia ADR 0340/0339 reais. |
| **D3** | **O nível da taxonomia** | ✅ **RESPONDIDA (v2.1)** — [W] 2026-07-17: *"tipo de documento, e outro por tipo de conteúdo do cliente? eu quero dois KB ou 1 com esse filtro"* → **1 KB com filtro, 2 eixos**: `Governança` (tipos de documento como subcategorias) + categorias de conteúdo do cliente. **Já seeded** (§3). A v2.0 lia isto como "categoria = type" e estava **errada** — [W] aprovou o *mockup*, não o achatamento. |
| **NOVO-A** | **Indicador da empresa ativa ao lado da busca** | ✅ **DECIDIDA (v4)** — [W] 2026-07-17: *"qual KB que o cliente está filtrando? isso deveria estar ao lado do buscar"*. Confirmado por medição como **rótulo** (não seletor): governança não vaza (`adr` biz=4 = 0), a seleção de empresa vive no `CompanyPicker` da Sidebar e o KB herda via sessão. §2-bis + Goal 9 + Anti-hook. |
| **NOVO-B** | **Categoria vazia não aparece na lateral** | ✅ **DECIDIDA (v4)** — achado da medição 2026-07-17: biz=4 tem `Governança` (id 16) seeded com **0 docs**. Regra: lateral só mostra categoria com ≥1 doc pra empresa ativa (biz=4 mostraria só producao/equipamentos/fiscal). Goal 10 + Anti-hook. |
| **D5** | ~~"Diversos: 639" visível na lateral~~ | ⚰️ **SEM OBJETO (v2.1)** — os "639 sem tipo" eram artefato da contagem no **disco**. No banco **todo nó tem `type`**; o que não tem é `category_id` (1.412/1.415) — e isso não é uma linha da lateral, é o **bloqueador** (§3). O princípio de [W] (dívida à vista, não escondida) sobrevive **melhor**: a tela não abre até classificar. |
| **D2** | **O `/kb` de hoje continua, ou a V2 toma o lugar?** | 🔴 **ABERTA** — o `/kb` tem **histórico de versões, soft-delete e filtro de PII** que a V2 **não tem**. Cutover sem isso **perde função**. Enquanto indefinido: **coexistem** (`/kb` legado · `/kb/v2` novo) — é o estado atual e não bloqueia o Controller. |
| **D4** | As **68 cores cruas** → tokens (gate visual ADR 0114) | 🔴 **ABERTA** — mudança visual, PR separado, não bloqueia o Controller. **Mas bloqueia a baseline definitiva:** contratar a tela no visreg congela em pixel o que existir na hora (§8-bis). |
| **D6** | **O template de categorias por vertical** | 🔴 **ABERTA (v2.1)** — as 7 categorias de cliente são de **gráfica** e estão seeded igual em biz=4, que é **vestuário**. [W] define por vertical, ou o agente propõe e [W] corta. **Não bloqueia** o eixo `Governança` — que é 100% do que a tela serve hoje. É onde o **classificador** (§3) entra: a equipe o especifica, não implementa até esta decisão. |

## 8-bis. O caminho até a tela viva — **v2.1 (a ordem da v2.0 estava errada)**

> **Errata:** a v2.0 mandava **contratar a tela no visreg PRIMEIRO**, alegando que o gate reprovaria
> o Controller. **Falso, e medido errado** — rodei `classifyFile`, não `validateExecution`. O gate
> só morde em diff **Page-only**: um PR com Controller vira `scope: global` (`ui-impact.mjs:283`
> não cobra `uncovered` no global). Consequência: eu ia te pedir **3 aprovações F1.5** (baseline do
> mock → re-aprovar com dado real → re-aprovar pós-cores) onde **1 basta** — e a primeira congelaria
> em pixel a tela **mock**, virando lixo por construção.

**Ordem v2.1 — o dado antes do render:**

| # | Passo | Por quê nesta ordem |
|---|---|---|
| **1** | **Classificador** (`auto_match` → `category_id`) + **backfill** dos 1.412 + fix do `fill()` do bridge + fix slug↔int (`KbArticleService:49`) | Sem isto a tela abre **vazia**. É o item, não o acessório. |
| **2** | **Controller `indexV2`** (injeta `nodes` + `categorias` **já filtradas por `docs ≥ 1`** (NOVO-B) + `empresaAtiva` (NOVO-A)) + revogar `UC-KBV2-06` **no mesmo commit** (ele asserta `missing('nodes')` → deixaria o CI vermelho *por ter funcionado*) | Só faz sentido depois que há categoria pra filtrar. |
| **3** | **Toasts** — com a tela viva, os 4 `toast.success` mentirosos deixam de ser inofensivos | ([#4365](https://github.com/wagnerra23/oimpresso.com/pull/4365) foi fechado; o de-risk volta aqui) |
| **4** | **Cores (D4)** → tokens | mudança visual, PR próprio |
| **5** | **Contratar no visreg 1× no estado final** + **F1.5 [W]** | 1 aprovação, sobre a tela que fica. **Com data de corte** — senão "quando estabilizar" nunca chega e nada força (todo PR de onda vira `global` e passa). |

> **⚠️ O plano da v2.0 shipava tela vazia a VERDE.** Composto: `category_id` NULL ⇒ render vazio ×
> `scope:global` ⇒ o pixel não olha × os testes da §8 assertam **prop** (que vem cheia) × o passo 2
> revoga o único teste que descrevia o estado de dado. **Por isso o §8 ganhou 2 travas** (abaixo):
> um caso que conta **linhas renderizadas** (não prop, não `COUNT`) e **smoke real com screenshot**
> (R1) como último passo — a ordem não termina em "cores", termina em **abrir a tela e ver**.

## 8. Como se prova que está pronto (contrato executável)

Estes são os testes que nascem deste charter — sem eles, `status` não vira `live`:

```php
it('serve documentos REAIS do business (nunca MOCK_NODES quando há dado)')
it('isola por business_id — biz=1 não vê documento de biz=99')   // Tier 0
it('a lateral é a árvore kb_categories→kb_subcategories do business')   // v2.1: NÃO "categoria=type"
it('cada categoria RENDERIZA linhas > 0 com dado real')               // v2.1: linhas, não prop/COUNT
it('categoria sem documento NÃO renderiza na lateral')                // NOVO-B: docs ≥ 1
it('o rótulo de busca mostra a empresa ATIVA da sessão')              // NOVO-A: lê business_id da sessão
it('o KB NÃO renderiza um seletor de empresa próprio')                // NOVO-A: troca é na Sidebar
it('não escreve no banco ao abrir (GET é leitura)')
it('não dispara Job/IA ao abrir')
it('nenhuma ação afirma sucesso sem persistir')                   // UC-KBV2-10
it('abre em 1280px sem scroll horizontal')                        // visual/manual
// + R1 OBRIGATÓRIO: smoke real em prod com screenshot ANTES de declarar pronto (v2.1)
```

**Como o escritor de `casos.md` deriva os 2 casos novos (v4):**

- **NOVO-A → UC "rótulo espelha a sessão":** montar a request com dois businesses distintos e assertar
  que o rótulo "Buscando em: «X»" bate com `session('user.business_id')` de cada um (biz=4 → nome da
  Larissa; biz=1 → o business de governança). Caso-par negativo: **a tela não expõe controle de troca
  de empresa** — nenhum `<select>`/dropdown de business no DOM do KB (a troca é responsabilidade do
  `CompanyPicker` da Sidebar, testado lá). Âncora do fato: `adr` biz=4 = 0 no recibo (nada a "trocar").
- **NOVO-B → UC "categoria vazia some":** com biz=4 (que tem `Governança` seeded e **0** docs nela),
  assertar que a categoria `Governança` **não** aparece na lista da lateral, e que producao/
  equipamentos/fiscal (1 doc cada) **aparecem**. A asserção conta **categorias renderizadas**, não a
  prop bruta `kb_categories` (que traz as 8, incluindo as vazias). Cross-check: a soma de docs das
  categorias visíveis = total do business (biz=4 → 3). O teste usa biz=4/biz=99 fictício, **nunca
  escreve em biz=4 real** (Tier 0).

## 9. Comparáveis canônicos

- **Notion** (tri-pane + leitor) — referência de layout
- **Obsidian** (cross-link, grafo) — referência de navegação entre documentos
- **Linear** (⌘K, densidade) — referência de atalhos
- Excluídos: Confluence (peso enterprise), Wiki.js (sem paleta), Outline (sem grafo)

## 10. Refs

- Blueprint Cowork: `prototipo-ui/cowork/kb-page.jsx`
- Casos (contrato executável): [`Index.v2.casos.md`](Index.v2.casos.md)
- V3 atual (docs canônicos, dado real): [`Index.charter.md`](Index.charter.md)
- Switcher de empresa (NOVO-A): `resources/js/Components/cockpit/Sidebar.tsx` (`CompanyPicker`, ~L342)
- Isolamento (NOVO-A/NOVO-B): `Modules/KB/Entities/Concerns/BelongsToBusinessTrait.php` (global scope)
- Bridge que popula o acervo: `Modules/KB/Jobs/KbBridgeFromMcpJob.php` (cron 15min, `app/Console/Kernel.php`)
- [ADR 0110 — Cockpit V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) · [ADR 0114 — gate visual](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [ADR 0093 — Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft v1.0 — port Cowork, tela **mock-first** (Goal 7 = "fallback MOCK_NODES"). Nunca saiu de draft; gate visual nunca fechou. |
| 2026-07-17 | [CC] | **v4 (design + medição fresca)** — [W] decidiu 2 pontos: **NOVO-A** (indicador da empresa ativa ao lado da busca — *rótulo*, não seletor: governança não vaza, `adr` biz=4 = 0 medido; a troca vive no `CompanyPicker` da Sidebar) e **NOVO-B** (categoria vazia não aparece — biz=4 tem `Governança` seeded com 0 docs). Ambas viram Goal (9/10) + Anti-hook + teste (§8). §7 registra as duas como DECIDIDAS; **D6 segue ABERTA**. §3 **mantém** a lei "aponta pro dono + recibo" (não voltou a número solto); o **recibo foi re-medido** no banco de produção `u906587222_oimpresso` via CT 100 (2026-07-17, `aaed49e156`): total 1.415, `category_id` NULL 1.412, `adr` biz=1 498/biz=4 0 — o "1.408/1.405" da v3 ficou stale (acervo sincroniza vivo). Nenhuma linha de código escrita: o classificador (§3) segue gated em D6. |
| 2026-07-17 | [CC] | **v3 (subtração)** — a v2.1 corrigiu os números **mantendo o lugar onde mentir**. A v3 **remove o lugar**: o charter deixa de guardar contagem e passa a **apontar o dono** (a query em `kb_nodes`) + **recibo datado** (query + resultado + data + sistema declarado). Aplica a lei *"fato derivado não se restateia"* — generalização da lápide 2026-07-16 (*"aponta pro dono, não restateia"*, escrita 24h antes do erro, mas só pra enforcement) e da regra Tier 0 "claim sem evidência" (que exige recibo, mas só cobria prod). Diagnóstico do erro por pesquisa de estado-da-arte ([session](../../../../memory/sessions/2026-07-17-arte-artefatos-por-tela.md)): **não foi alucinação — foi ORÁCULO ERRADO** (mediu o disco pra descrever tela que lê o banco); o mercado inteiro (Spec Kit · Kiro · Tessl · Drift) ancora doc↔código e **ninguém ancora doc↔dado**. É **subtração** (ADR 0271/0314): o doc escreve MENOS. |
| 2026-07-17 | [CC] | **v2 (errata)** — a v2.0 mediu o **disco**; a tela lê o **banco**. §3 refeita com `SELECT` no CT 100: 1.408 nós (não 3.016), `runbook` 11 (não 152), `charter`/`briefing`/`handoff` **0** (gap de ingestão). Invariante "categoria = type" **derrubado** — [W] decidiu **1 KB com filtro / 2 eixos**: `Governança` (tipos como subcategorias, `auto_match` já seeded) + conteúdo do cliente. Exposto o bloqueador real: **~1.4k sem `category_id`** ⇒ tela vazia, e o `auto_match` com **zero leitores**. §8-bis reordenada (dado antes do render; 1 F1.5 no fim, não 3) + §8 ganha teste de **linhas renderizadas** e **R1**. D5 sem objeto; **D6 nova** (template por vertical). Origem: adversário 3×"emenda antes do código". |
| 2026-07-17 | [CC] | **v2.0** — [W]: *"quero os dados, mas com o design do KB"*. Reescrito pro acervo **real** (o bridge já popula `kb_nodes` em prod). Categorias = os `type` do dado (mata o classificador-por-equipamento da v1.0). Persona "operadora de gráfica" removida (não existe no cliente). Anti-hook novo: ação não afirma o que não fez. §7 lista as decisões [W] que bloqueiam `live`. **Aguarda [W]** — nenhum código escrito até D1 ser respondida. |
