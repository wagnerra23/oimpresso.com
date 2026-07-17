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
charter_version: 2.2
charter_at: 2026-07-17
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central # proposta
  - 0039-ui-chat-cockpit-padrao
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
related_prototype: ../../../prototipo-ui/cowork/kb-page.jsx
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/kb-page.jsx
  blueprint_screenshot_approval: pendente (gate F1.5)
  derived_screens: [Index.v2]
  divergence_from_blueprint: "tri-pane sidebar+lista+leitor (port direto JSX→TSX)"
---

# Charter — `kb/Index.v2.tsx` · v2.2 **DRAFT (aguarda [W])**

> **O que mudou da v1.0 (2026-05-16):** a v1.0 descrevia uma tela de **SOPs de gráfica com dados
> inventados** ("fallback MOCK_NODES" era Goal 7). [W] 2026-07-17: *"eu quero os dados, mas com o
> design do KB"*. A v2.0 passou a descrever a MESMA tela servindo os **documentos canônicos reais**.
> O desenho não muda; a fonte de dados muda — e é isso que a torna verdadeira.
>
> ### 🔴 O que a v2.1 corrige da v2.0 (mergeada com erro em #4393/#4396)
>
> A v2.0 foi escrita **medindo o disco** (`git ls-files`) pra descrever uma tela que **lê o banco**.
> Todos os números da §3 estavam errados (3.016 → **1.408** real; "237 contratos de tela" → **0**;
> "152 receitas" → **11**), e o invariante *"categoria = `kb_nodes.type`"* **achatava a árvore e
> apagava o eixo do cliente**. A v2.1 mede `kb_nodes` no CT 100 e adota a taxonomia que **já está
> seeded** ([W]: *"1 KB com esse filtro"*). Três coisas que só apareceram por medir o lugar certo:
>
> 1. **1.405 de 1.408 nós têm `category_id` NULL** → a tela nasceria **vazia** (§3).
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
| **Subtítulo** | "18 SOPs · … · **MOCK (Agent A pendente)**" | "3.016 documentos · **atualizado há N min**" |

> **Por quê:** "SOP" (procedimento operacional) só fazia sentido com o corpus de gráfica. Servindo
> ADR/sessão/charter, o nome vira mentira de rótulo — a pessoa lê "Procedimentos" e encontra decisão
> de arquitetura. E o subtítulo troca o **aviso de que é falso** pelo **frescor do bridge**, que é o
> número que passa a importar quando o dado é real.
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

## 3. As categorias (painel esquerdo) — **o charter NÃO guarda número** (v2.2)

> ### ⚖️ Lei aplicada aqui: *fato derivado não se restateia — aponta pro dono, ou carrega recibo*
>
> **O charter não repete número que outro sistema sabe melhor.** A população da tela é `kb_nodes`,
> filtrada por `business_id`. **O dono do número é o banco** — não este arquivo.
>
> **Por que a regra existe (o caso que a pariu, 2026-07-17):** a v2.0 escreveu "3.016 documentos"
> à mão, contando `git ls-files` (**o disco**), pra descrever uma tela que **lê o banco** (1.408).
> Não foi alucinação: o agente rodou uma tool de verdade e reportou fielmente — **o oráculo é que
> era o errado**. O número entrou com o selo mais alto de confiança ("saída direta de tool") sendo
> saída direta de **outro sistema**. Os 3 documentos do trio ficaram **coerentes entre si** e
> divorciados do mundo; a v2.1 corrigiu os números — mas **manteve o lugar onde mentir**. A v2.2
> **remove o lugar**.

### Onde perguntar (o dono)

```sql
-- população da tela (o que a lateral conta), por business:
SELECT type, COUNT(*) FROM kb_nodes WHERE business_id = ? GROUP BY type;
-- o bloqueador (ver abaixo):
SELECT COUNT(*) FROM kb_nodes WHERE category_id IS NULL;
-- a árvore da lateral:
SELECT slug, label FROM kb_categories WHERE business_id = ?;
SELECT s.slug, s.label, s.auto_match FROM kb_subcategories s;
```

Onde roda: **CT 100** (`docker exec oimpresso-mcp php artisan tinker`). **Nunca no CI** — o CI não
tem o banco de governança; um "gate" que fingisse medir isso lá seria teatro.

### 🧾 Recibo (não é afirmação — é medição datada, com o sistema declarado)

| campo | valor |
|---|---|
| **sistema medido** | `kb_nodes` @ CT 100 `oimpresso-mcp` (banco de governança, biz=1) |
| **quando** | 2026-07-17 |
| **quem** | [CC], via `php artisan tinker` |
| **total** | 1.408 (biz=1: 1.405 · biz=4: 3) |
| **por tipo** | `adr` 497 · `session` 451 · `reference` 365 · `spec` 62 · `comparativo` 19 · `runbook` 11 · `article` 3 |
| **`category_id` NULL** | **1.405 de 1.408** |
| **árvore** | 16 categorias · 36 subcategorias |

> **O recibo envelhece — e tem que envelhecer à vista.** Ele diz *"em 17/07 o banco respondia
> isso"*, não *"a tela tem 1.408 documentos"*. A segunda forma é a que apodrece calada. Se a data
> incomodar quem lê, **re-rode a query** — não edite o número.
>
> **Gap de ingestão medido no mesmo recibo** (some da lateral, PR próprio): o disco tem ~3.016 `.md`
> e o banco 1.408. `charter` e `briefing` chegam **0** (o coletor não varre fora de `memory/`);
> `runbook` chega 11 (descarta `RUNBOOK-*.md` pelo basename); `handoff` 0 (`bridgeableTypes()` não
> aceita). **Não invente esses números em outro doc — aponte pra cá ou re-meça.**

### 🧊 O bloqueador real: **`category_id` NULL na quase totalidade** (ver recibo)

`Index.v2.tsx:147` filtra `n.category_id === cat.id`. Com o campo nulo, **toda categoria renderiza
zero linhas** — a tela nasceria **vazia**, e o CI passaria **100% verde** (os testes olham a *prop*,
que vem cheia; o pixel não olha a tela; o único teste que descrevia o estado de dado era revogado no
mesmo commit). Não é detalhe de implementação: é **o** item.

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
> preenche 9 campos e **nenhum deles é `category_id`**. Por isso 1.405 nós sem categoria: não falta
> taxonomia, falta **o classificador que aplica a taxonomia**.
>
> **Fechar isso = (a)** serviço que lê `auto_match` e escreve `category_id`; **(b)** backfill dos
> 1.405; **(c)** o bridge passa a classificar no fill; **(d)** corrigir `KbArticleService:49`
> (`$request->integer('category')` espera int, a tela manda **slug** → `where('category_id',0)` →
> zero **em silêncio**). Isso é **pré-requisito do Controller**, não paralelo.

> ### 🔴 Decisão [W] ABERTA — o template por vertical
>
> As 7 categorias de cliente estão seeded **idênticas** em biz=1 e biz=4, e são todas de **gráfica**
> (Plotter · Corte · Pré-impressão). Mas **biz=4 é a ROTA LIVRE — vestuário**. É a mesma ficção do
> corpus mock, agora na taxonomia. O eixo `Governança` é o único igual pra todos (é interno).
>
> **Pendente:** [W] define as categorias por vertical, ou o agente propõe um jogo (gráfica ·
> vestuário · oficina) e [W] corta. **Não bloqueia** o eixo `Governança` (que é o que a tela serve
> hoje: 1.405 documentos, todos de governança).

## 4. O que dá pra fazer (Goals)

1. **Ler** um documento inteiro no painel direito, sem recarregar a página.
2. **Navegar por categoria** — clicar em "Decisões" e ver só os ADRs, com a contagem certa.
3. **Buscar** por título/etiqueta/autor, com resposta enquanto digita.
4. **`⌘K`** abre a paleta de comando; `Esc` fecha; `/` foca a busca.
5. **Favoritar** um documento e ele continuar favorito depois.
6. **Ver o que está velho** — o painel de saúde mostra o que precisa de revisão.
7. **Seguir link entre documentos** — um ADR que cita outro abre com um clique.
8. **1280px sem barra de rolagem horizontal** (Larissa, balcão).

## 5. O que a tela NÃO faz (Non-Goals)

> [W] aprova esta lista. Cada item vira teste.

- **Não edita** documento canônico. Eles vêm do git — a fonte é o repositório, e a tela é **leitura**.
  Editar aqui criaria duas verdades. (Documento escrito à mão, editável, é outro assunto — §7.)
- **Não substitui o `/kb` atual** sem decisão explícita — ver §7.
- Não faz CRUD de trilhas/troubleshooters (charters próprios).
- Não carrega 1000+ documentos sem virtualização.
- Não sincroniza em tempo real.

## 6. O que a tela NUNCA pode fazer (Anti-hooks — viram teste que bloqueia merge)

- **NUNCA mostrar documento de outro business** (multi-tenant Tier 0 — ADR 0093).
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
| **D5** | ~~"Diversos: 639" visível na lateral~~ | ⚰️ **SEM OBJETO (v2.1)** — os "639 sem tipo" eram artefato da contagem no **disco**. No banco **todo nó tem `type`**; o que não tem é `category_id` (1.405/1.408) — e isso não é uma linha da lateral, é o **bloqueador** (§3). O princípio de [W] (dívida à vista, não escondida) sobrevive **melhor**: a tela não abre até classificar. |
| **D2** | **O `/kb` de hoje continua, ou a V2 toma o lugar?** | 🔴 **ABERTA** — o `/kb` tem **histórico de versões, soft-delete e filtro de PII** que a V2 **não tem**. Cutover sem isso **perde função**. Enquanto indefinido: **coexistem** (`/kb` legado · `/kb/v2` novo) — é o estado atual e não bloqueia o Controller. |
| **D4** | As **68 cores cruas** → tokens (gate visual ADR 0114) | 🔴 **ABERTA** — mudança visual, PR separado, não bloqueia o Controller. **Mas bloqueia a baseline definitiva:** contratar a tela no visreg congela em pixel o que existir na hora (§8-bis). |
| **D6** | **O template de categorias por vertical** | 🔴 **ABERTA (v2.1)** — as 7 categorias de cliente são de **gráfica** e estão seeded igual em biz=4, que é **vestuário**. [W] define por vertical, ou o agente propõe e [W] corta. **Não bloqueia** o eixo `Governança` — que é 100% do que a tela serve hoje. |

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
| **1** | **Classificador** (`auto_match` → `category_id`) + **backfill** dos 1.405 + fix do `fill()` do bridge + fix slug↔int (`KbArticleService:49`) | Sem isto a tela abre **vazia**. É o item, não o acessório. |
| **2** | **Controller `indexV2`** + revogar `UC-KBV2-06` **no mesmo commit** (ele asserta `missing('nodes')` → deixaria o CI vermelho *por ter funcionado*) | Só faz sentido depois que há categoria pra filtrar. |
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
it('não escreve no banco ao abrir (GET é leitura)')
it('não dispara Job/IA ao abrir')
it('nenhuma ação afirma sucesso sem persistir')                   // UC-KBV2-10
it('abre em 1280px sem scroll horizontal')                        // visual/manual
// + R1 OBRIGATÓRIO: smoke real em prod com screenshot ANTES de declarar pronto (v2.1)
```

> **Atenção — dívida que precisa morrer junto:** hoje existe um teste **required** afirmando que a
> tela **não recebe dados** (`missing('nodes')`, UC-KBV2-06 da v1.0). Ele foi honesto na era-mock,
> mas hoje **proíbe a promoção**: ligar o Controller deixaria o CI vermelho *por ter funcionado*.
> Ele é revogado no mesmo PR que entrega o Controller — não antes, não depois.

## 9. Comparáveis canônicos

- **Notion** (tri-pane + leitor) — referência de layout
- **Obsidian** (cross-link, grafo) — referência de navegação entre documentos
- **Linear** (⌘K, densidade) — referência de atalhos
- Excluídos: Confluence (peso enterprise), Wiki.js (sem paleta), Outline (sem grafo)

## 10. Refs

- Blueprint Cowork: `prototipo-ui/cowork/kb-page.jsx`
- Casos (contrato executável): [`Index.v2.casos.md`](Index.v2.casos.md)
- V3 atual (docs canônicos, dado real): [`Index.charter.md`](Index.charter.md)
- Bridge que popula o acervo: `Modules/KB/Jobs/KbBridgeFromMcpJob.php` (cron 15min, `app/Console/Kernel.php`)
- [ADR 0110 — Cockpit V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) · [ADR 0114 — gate visual](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [ADR 0093 — Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft v1.0 — port Cowork, tela **mock-first** (Goal 7 = "fallback MOCK_NODES"). Nunca saiu de draft; gate visual nunca fechou. |
| 2026-07-17 | [CC] | **v2.2 (subtração)** — a v2.1 corrigiu os números **mantendo o lugar onde mentir**. A v2.2 **remove o lugar**: o charter deixa de guardar contagem e passa a **apontar o dono** (a query em `kb_nodes`) + **recibo datado** (query + resultado + data + sistema declarado). Aplica a lei *"fato derivado não se restateia"* — generalização da lápide 2026-07-16 (*"aponta pro dono, não restateia"*, escrita 24h antes do erro, mas só pra enforcement) e da regra Tier 0 "claim sem evidência" (que exige recibo, mas só cobria prod). Diagnóstico do erro por pesquisa de estado-da-arte ([session](../../../memory/sessions/2026-07-17-arte-artefatos-por-tela.md)): **não foi alucinação — foi ORÁCULO ERRADO** (mediu o disco pra descrever tela que lê o banco); o mercado inteiro (Spec Kit · Kiro · Tessl · Drift) ancora doc↔código e **ninguém ancora doc↔dado**. É **subtração** (ADR 0271/0314): o doc escreve MENOS. |
| 2026-07-17 | [CC] | **v2.1 (errata)** — a v2.0 mediu o **disco**; a tela lê o **banco**. §3 refeita com `SELECT` no CT 100: 1.408 nós (não 3.016), `runbook` 11 (não 152), `charter`/`briefing`/`handoff` **0** (gap de ingestão). Invariante "categoria = type" **derrubado** — [W] decidiu **1 KB com filtro / 2 eixos**: `Governança` (tipos como subcategorias, `auto_match` já seeded) + conteúdo do cliente. Exposto o bloqueador real: **1.405 sem `category_id`** ⇒ tela vazia, e o `auto_match` com **zero leitores**. §8-bis reordenada (dado antes do render; 1 F1.5 no fim, não 3) + §8 ganha teste de **linhas renderizadas** e **R1**. D5 sem objeto; **D6 nova** (template por vertical). Origem: adversário 3×"emenda antes do código". |
| 2026-07-17 | [CC] | **v2.0** — [W]: *"quero os dados, mas com o design do KB"*. Reescrito pro acervo **real** (o bridge já popula `kb_nodes` em prod). Categorias = os 8 `type` do dado (mata o classificador-por-equipamento da v1.0). Persona "operadora de gráfica" removida (não existe no cliente). Anti-hook novo: ação não afirma o que não fez. §7 lista as 4 decisões [W] que bloqueiam `live`. **Aguarda [W]** — nenhum código escrito até D1 ser respondida. |
