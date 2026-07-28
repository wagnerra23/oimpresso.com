---
id: sessions-2026-07-28-sdd-kb-index
type: session
date: "2026-07-28"
topic: "SDD do módulo KB derivado do fonte (chip Onda 4 do passo 5) — tela-âncora kb/Index"
module: KB
owner: wagner
autor: "[CC] via agent sdd-from-source (ADR 0351)"
lifecycle: ativo
related_adrs:
  - 0351-sdd-from-source
  - 0053-mcp-server-governanca-como-produto
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
related_us: [US-KB-001, US-KB-002, US-KB-003, US-KB-004, US-KB-005, US-KB-006, US-KB-007]
---

# Sessão — SDD do módulo KB (chip Onda 4 do passo 5) · tela-âncora `kb/Index`

Chip de [`passo-5-sdd-por-modulo.md`](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
Alvo: **KB** — 3 telas, 7 US, lane própria `kb-pest.yml`, **sem SDD**. Começou por `kb/Index`.

## 1. Alvo e fontes resolvidas

| Fonte | Estado | Recibo |
|---|---|---|
| 1 · Documentação canon | ✅ | SPEC (7 US) · BRIEFING · SCHEMA-DB-V1 · 3 charters · ADR 0150 · ADR 0053 |
| 2 · React/Laravel vivo | ✅ | `routes.php` (3 grupos + alias `/sops`) · `KbController` · `KbArticleService` · `McpMemoryDocument` · 3 `.tsx` |
| 3 · Blade AdminLTE | ❌ **medido inexistente** | `Modules/KB/Resources/views/` não existe · `find` sobre views = 0 · `git log --all --diff-filter=A` = 0 commits (repo **completo**, `is-shallow=false`). O módulo **nasceu Inertia** (predecessor = `Pages/Copiloto/Admin/Memoria/Index.tsx`, React→React) |
| 4 · Delphi/Office Comercial | ❌ **gap declarado** | `find memory -iname "*ANTI-REGRESSAO*"` = 2 arquivos, ambos do Produto |

**Consequência declarada:** sem fontes 3 e 4, o contrato de paridade é mais fraco — todo CU nasce
ancorado em *canon + código*, nunca em memória de sistema antigo. Não inventei legado.

**Colisão de sessão:** `git log origin/main --since=2.days -- Modules/KB resources/js/Pages/kb` → 2
commits, nenhum de outra corrida SDD. O [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853)
(27/07) renomeou `copiloto.*`→`jana.*` nos testes do KB; meus testes já nascem com
`jana.mcp.memory.manage`, alinhados. Sem sobrescrita.

## 2. Artefatos tocados

| Arquivo | Ação |
|---|---|
| `memory/requisitos/KB/SDD-tela-kb-unificado-v1.0.md` | **novo** — §0–§11, 10 CU, 9 fluxos `F1..F9` |
| `resources/js/Pages/kb/Index.casos.md` | **novo** — 6 UC ancorados + 3 `[BACKLOG]` |
| `resources/js/Pages/kb/Graph.casos.md` | **novo** — 3 UC (piso da rota) + 2 `[BACKLOG]` |
| `Modules/KB/Tests/Feature/KbIndexContratoTest.php` | **novo** — 13 casos (I1..I6c) |
| `Modules/KB/Tests/Feature/KbGraphContratoTest.php` | **novo** — 5 casos (G1..G3b) |
| `memory/requisitos/KB/SPEC.md` | `**Critérios de aceite:**` ×7 · `**Testado em:**` ×5 · errata de âncora US-KB-006 · `_pendente_` US-KB-007 · nota "a tela `/kb` não tem US" |
| 5 testes existentes | `@covers-us US-KB-001..005` (com o escopo real de cada um declarado, sem overclaim) |
| `resources/js/Pages/kb/Graph.charter.md` | reconciliação **factual** (rota/controller) — intenção intocada |
| `memory/requisitos/KB/BRIEFING.md` | `status_nota` stale corrigido + `distilled_by` (redestilação **parcial**, declarada) |
| `memory/requisitos/KB/SUPERFICIE.md` | regenerado (`module-surface KB --write`, 147→151) |
| `memory/requisitos/KB/_STATUS-GENERATED.md` | **novo** (`requisitos-status KB --write`) |

**Não toquei** (área proibida): `scripts/**`, `governance/*.json`, `.github/**`, `proibicoes.md`,
`LICOES_CODE.md`, `08-handoff.md`. **Nenhuma lane criada** — o KB já tem a dele.

## 3. Veredito dos gates (Camada 3)

| Régua | Antes | Depois |
|---|---|---|
| `anchor-lint KB` cobertura | 85,7% | **100%** |
| `req_sem_aceite` (gate de entrada) | 6 | **0** |
| `req_sem_covering_test` | 6 | **1** (só US-KB-006 — o grafo fachada; honesto) |
| `dead_tests` / `testado_sem_covers` | 0 / 0 | **0 / 0** |
| `anchor-lint --check` | — | **exit 0** |
| Telas com `casos.md` (porta viva) | 1/3 | **3/3** |
| UC declarados / com teste que os cita | 18 / 14 | **27 / 23** |
| CU no SDD | 0 | **10** |
| `casos-coverage-guard --check` | — | **exit 0** |
| `requisitos-status KB --check` | — | **exit 0** |

⚖️ **Vocabulário:** não rodei teste nenhum (CT 100 · [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)).
Todo UC nasce 🧪 **sem veredito**. O único status forte que emiti é `UC-KB-02: 🔴 predição de vermelho`,
marcado **como predição**, não como fato.

**Força do veredito da lane** (consultado `governance/required-checks-baseline.json`, o dono único):
`PHP / Pest (KB · MySQL)` **não é required** → **advisory**. Está escrito nos dois `casos.md`.

## 4. Achados (varredura CONTADA)

| # | Achado | Varredura | Dono |
|---|---|---|---|
| A-1 | **`KbController@show` não repete o `acessiveisPara` da lista** → doc `admin_only`/`scope_required` some da lista mas é servido inteiro por slug. Vale também pra `history`/`softDelete`/`restore` | `git grep -n "acessiveisPara" -- '*.php'` **sem `head_limit`** = **13 linhas / 8 arquivos**; dentro do `KbController` **1 site**, em `buildDocsPayload` | **[W]** decide o remédio (403 × 404 × filtrar). Teste failing-first escrito (`I2`/`I2b`), assert de **comportamento** ("o corpo não sai"), não de status — travar status reprovaria 2 dos 3 fixes legítimos |
| A-2 | `buildKpisPayload` roda 5 agregações **cruas** enquanto a lista filtra → KPI e selects contam/nomeiam o que o usuário não abre | leitura do método + contraste com A-1 | **[W]** — `[BACKLOG]`, não virei UC: são 2 leituras plausíveis e nenhuma tem fonte canon |
| A-3 | **A tela-âncora não tinha contrato nenhum**, e o único teste que a citava está `->skip()` **e** asserta payload inexistente (`has('nodes')` × real `docs/filters/kpis/github_repo`) | `git grep "kb/Index'\|kb\.index\|'/kb'" Modules/KB/Tests` = 8 linhas | fechado neste run |
| A-4 | **`/kb/graph` é fachada** e a âncora do SPEC apontava Controller de **outro módulo** (`Admin/GraphController` é roteado só por `Modules/ADS/Routes/web.php`) | `git grep -n "GraphController" -- '*.php'` = **4 linhas**, nenhuma no KB | âncora corrigida; construir o Controller é **[W]** |
| A-5 | **A tela `/kb` não é coberta por US nenhuma** — descende de `MEM-KB-1`/ADR 0053, anterior à numeração | cabeçalho `@memcofre` do `Index.tsx` + leitura das 7 US | **[W]** — criar US é ato de produto |
| A-6 | `Graph.charter.md` declarava `route: kb.graph` (**não existe**; o real é `kb.graph.page`) e `controller: KbGraphController` (**classe inexistente**) | leitura de `routes.php` + o grep de A-4 | corrigido (FATO) + teste `G2` trava o nome real |
| A-7 | BRIEFING dizia *"a tela /kb/v2 roda 100% MOCK — o Controller nunca ligou o dado"* — **stale há 11 dias** (`indexV2` landou 17/07) | `KbController::indexV2` + `routes.php` + `KbIndexV2ContractTest` V5/V6 | corrigido (FATO) |

### Fora do meu diff — reporto, não conserto
- `casos-coverage-guard --check` acusa 2 UC órfãos em `ComunicacaoVisual/Index.casos.md`
  (`UC-CV-06`, `UC-CV-08`). Não é meu arquivo, não é minha área. **Não toquei.**
- `governance/anchor-entry-baseline.json` ainda grandfathera 12 chaves KB
  (`entry-aceite:US-KB-001..006` + `entry-teste:…`) que **acabaram de ser pagas**. Encolher o baseline
  é livre pela regra dele, mas é arquivo global proibido pro chip → **fica pro parent**.

## 5. Orçamento da corrida

| Item | Valor |
|---|---|
| Tool calls | **~80** (≈32 leitura/varredura · ≈26 escrita/edição · ≈14 gates · ≈8 investigação de régua) |
| Arquivos lidos por inteiro | 8 (`SPEC`, `Index.charter`, `routes.php`, `KbController`, `Index.tsx`, `Helpers.php`, `KbIndexV2ContractTest`, `Index.v2.casos.md`) |
| Varreduras contadas (sem `head_limit`) | 6 (`acessiveisPara` · `GraphController` · rotas V3 · ids CU/UC · `KBPrintSOP` · Blade histórica) |
| Artefatos criados | 6 · **editados** 10 |
| UC gerados | **9 ancorados** (6 Index + 3 Graph) · **5 `[BACKLOG]`** |
| CU gerados | 10 |
| Achados | 7 (todos com varredura contada + âncora de contrato) |
| **Reuso vs re-varredura (Fase 1.4)** | **Reusei** o contrato da tela irmã inteiro — `Index.v2.casos.md` (18 UC) virou `CU-KB-10` **por ponteiro**, sem reescrever 1 linha; e o padrão de teste do `KbIndexV2ContractTest` (skip-sqlite · `ensure_pages_exist=false` · `kbBootstrapSchema`) foi copiado, não redescoberto — isso sozinho evitou os 3 bloqueadores de lane que o #4725 levou uma corrida inteira pra achar. **Re-varri** (não dá pra reusar): a resolução de fonte por tela e os consumidores de cada rota |
| Gargalo | **Entender a régua, não o código.** ~14 tool calls foram gastas descobrindo *como* o `requisitos-status.mjs` liga CU→UC (`citadoComoAncora` exige tabela cuja **1ª coluna** é id, ou `**Âncora:**` em negrito, ou chave de frontmatter). Escrevi "Âncora:" em prosa e os 10 CU apareceram como lacuna; a correção foi 1 tabela. Custo: leitura de 4 trechos do script |

## 6. Lições de mecanismo (o que atrapalhou)

1. **A convenção de âncora CU→UC não está escrita onde o autor a lê.** Ela vive só no regex de
   `citadoComoAncora`. Um `casos.md` que cite `CU-KB-01` em prosa **não conta** — e o relatório diz
   *"CU sem UC"*, que soa como "falta escrever o caso", quando o caso existe e o **formato** é que
   não bate. Custou ~14 tool calls. Sugestão (não implementei — `scripts/**` é proibido pro chip):
   a mensagem da lacuna dizer *"…ou o UC existe mas não está numa tabela de rastreabilidade"*.
2. **A definição do agent manda usar `last_run_ci` com frase fixa; o gate G-5 exige `last_run` com
   data.** São campos diferentes e a definição não avisa. Pus os dois. Sem isso, seguir o agent ao pé
   da letra quebra o `casos-gate`.
3. **O `memory-schema` guard bloqueou o 1º Write deste session log** por falta de `topic:` —
   corretamente, e a mensagem já dizia o que fazer. Registro porque a **definição do agent** manda
   gravar session log e não cita o campo obrigatório: quem seguir o agent ao pé da letra bate no
   guard. Custo: 2 tool calls.
4. **A porta viva não distingue `[BACKLOG] declarado` de `esquecido`.** `CU-KB-06`/`CU-KB-07` ficam
   como "lacuna" mesmo estando explicitamente declarados como backlog com a razão (UI desligada ·
   contrato em disputa). **Não gamifiquei** — deixei aparecendo, porque sumir com eles seria maquiar.
   Mas vale registrar que a régua não tem como distinguir os dois casos hoje.
5. **A lane `kb-pest.yml` é catraca-por-prova-verde e o agent não roda teste** — logo todo teste que
   um chip escreve nasce, por construção, fora da lane. Isso **não é defeito do chip**; é o desenho
   da catraca. Mas significa que "fechar o módulo" nunca termina dentro de uma corrida só: sempre
   sobra o passo humano de rodar no CT 100 e ratchetear. Está declarado nos dois `casos.md` em vez de
   escondido.
