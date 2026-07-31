---
id: resources-js-pages-kb-index-casos
casos: "KB V3 — browser do acervo canon · /kb"
irmaos: "Index.charter.md (lei · v1.0) · Index.v2.casos.md (tela irmã, kb_nodes) · SDD-tela-kb-unificado-v1.0.md (§6 CU-KB)"
tecnica: "Caso de uso = narrativa + critério de aceite verificável, derivado do §6 do SDD (nunca do .tsx)"
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (KB · MySQL)"
status_tela: "viva-dado-real (GET /kb → KbController@index → mcp_memory_documents; 1ª vez que a tela ganha contrato executável)"
---

# Casos de uso — `/kb` (KB V3 · browser do acervo canon)

> **Status por UC:** ✅ passa (provado por teste verde) · 🧪 escrito, aguarda veredito da lane ·
> ⬜ não verificado · 🔴 predição de vermelho (o teste mede um defeito conhecido) · ⚠️ contrato em disputa.
>
> **De onde estes UC vêm:** do **§6 do [SDD do módulo](../../../../memory/requisitos/KB/SDD-tela-kb-unificado-v1.0.md)**
> (`CU-KB-01..08`), que por sua vez triangula charter + SPEC + código. **Nenhum UC foi derivado do
> `Index.tsx`** — derivar do código produz teste tautológico ([proibicoes §5 2026-06-05](../../../../memory/proibicoes.md)).
>
> **Fontes que NÃO existem neste módulo (declarado, não omitido):** não há Blade legada
> (`Modules/KB/Resources/views/` não existe; 0 commits de adição em `git log --all --diff-filter=A`
> sobre os globs de view — repo completo) e não há `ANTI-REGRESSAO-*` Delphi (os 2 do repo são do
> Produto). Logo **não há contrato de paridade** aqui: todo UC é ancorado em *canon + código*.
>
> **Onde os números vivem:** este contrato **não guarda contagem de acervo**. O dono do número é a
> query (`KbController::buildKpisPayload` sobre `mcp_memory_documents`) — [proibicoes §5 2026-07-17](../../../../memory/proibicoes.md),
> a lápide que nasceu nesta mesma família de telas.
>
> **Força do veredito:** a lane é `PHP / Pest (KB · MySQL)` (`.github/workflows/kb-pest.yml`).
> Consultado o dono único (`governance/required-checks-baseline.json`), ela **não é required** →
> **advisory**: reprova visível, **não bloqueia merge**. E o arquivo de teste destes UC **ainda não
> está na allowlist** da lane (ela é catraca-por-prova-verde: só entra o que já passou). Enquanto não
> entrar, o veredito é estruturalmente pendente — dito aqui, não escondido.

---

## Rastreabilidade UC → CU → US

> Tabela de rastreabilidade lida pela porta viva `node scripts/governance/requisitos-status.mjs KB`
> (a coluna 1 é o id — é o que a torna rastreabilidade, e não menção em prosa).

| UC | CU no SDD | US no SPEC | Teste que o cita |
|---|---|---|---|
| UC-KB-01 | CU-KB-01 | — (tela sem US · `MEM-KB-1`/ADR 0053) | `KbIndexContratoTest.php` I1/I1b/I1c |
| UC-KB-02 | CU-KB-02 | — | `KbIndexContratoTest.php` I2/I2b |
| UC-KB-03 | CU-KB-03 | — | `KbIndexContratoTest.php` I3/I3b |
| UC-KB-04 | CU-KB-04 | — | `KbIndexContratoTest.php` I4/I4b/I4c |
| UC-KB-05 | CU-KB-05 | — | `KbIndexContratoTest.php` I5/I5b |
| UC-KB-06 | CU-KB-08 | — | `KbIndexContratoTest.php` I6/I6b/I6c |
| _(sem UC)_ | CU-KB-06 | — | `[BACKLOG]` histórico — UI desligada |
| _(sem UC)_ | CU-KB-07 | — | `[BACKLOG]` KPI — contrato em disputa ([W]) |
| _(sem UC)_ | CU-KB-09 | US-KB-006 | `[BACKLOG]` grafo — fachada |
| UC-KBV2-01 | CU-KB-10 | US-KB-002 | `Index.v2.casos.md` (tela irmã — UC-KBV2-01..13) |

> **A coluna "US" vazia não é lacuna** — é o achado: a tela `/kb` nunca teve US
> (SPEC §2, nota de 2026-07-28). Criar uma é decisão de [W].

---

## UC-KB-01 — Listar o acervo com filtro, busca e paginação
Status: 🧪 (`Modules/KB/Tests/Feature/KbIndexContratoTest.php` — I1/I1b/I1c)
Wagner autenticado abre `/kb` e recebe a lista paginada do acervo canon, com os filtros que mandou
ecoados de volta e um documento soft-deletado ainda visível (é tela de governança — some da lista
seria esconder o que a LGPD mandou marcar, não apagar). Âncora: SDD `CU-KB-01` · charter §Goals 1/3 ·
`KbController@index` → `buildDocsPayload` (re-localizável por `git grep -n "buildDocsPayload"`).
**Pronto quando:** `/kb` responde 200 com a página Inertia `kb/Index`; um documento seedado aparece na
resposta; filtrar por um `type` que ele não tem faz o documento sumir; e um documento com
`deleted_at` preenchido continua alcançável na listagem.

## UC-KB-02 — O conteúdo respeita o mesmo escopo que a lista `[must][T0]`
Status: 🔴 **predição de vermelho** (`KbIndexContratoTest.php` — I2/I2b) — *predição minha, não veredito;
quem decide é a lane*
Um documento marcado `admin_only` (ou com `scope_required` que o usuário não possui) é filtrado da
**lista** por `McpMemoryDocument::scopeAcessiveisPara`. O contrato é que o **conteúdo** obedeça à mesma
regra: quem não pode ver na lista não pode ler pelo slug. Hoje `KbController@show` não repete o filtro —
varredura contada (`git grep -n "acessiveisPara" -- '*.php'`, **sem** `head_limit`): **13 linhas em 8
arquivos**, e dentro do `KbController` há **1 único site**, em `buildDocsPayload`. Âncora: docblock de
`scopeAcessiveisPara` (*"filtra por `scope_required` vs Spatie permissions do user"*) +
[ADR 0053](../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md) · SDD `CU-KB-02` / §9 D-1(a).
**Pronto quando:** para um documento que **não aparece na lista** do usuário, `GET /kb/{slug}/show`
**não entrega o corpo dele** — o texto sensível não sai na resposta. O contrato é *"o conteúdo não
aparece"*, **não** *"a resposta é 403"*: 403, 404 ou payload filtrado satisfazem igualmente, porque a
escolha do remédio é de [W] (§9 D-1(a) do SDD) e um assert por status reprovaria arbitrariamente dois
fixes legítimos.

## UC-KB-03 — Ler o documento no preview
Status: 🧪 (`KbIndexContratoTest.php` — I3/I3b)
Clicar num item (ou `j`/`k`+Enter) abre o leitor com o corpo markdown do documento e a metadata de
proveniência (tipo, módulo, sha do git, PII redigida, contagem de revisões). Quando o documento não tem
`git_path`, **não** existe link pro GitHub — link quebrado é pior que link ausente. Âncora: SDD
`CU-KB-03` · charter §Goals 2 · `KbController@show`.
**Pronto quando:** `GET /kb/{slug}/show` de um documento acessível devolve 200 e o **corpo** do
documento chega ao cliente; e um documento sem `git_path` **não** carrega nenhuma URL do GitHub na
resposta.

## UC-KB-04 — Soft-delete LGPD exige confirmação no SERVIDOR `[LGPD]`
Status: 🧪 (`KbIndexContratoTest.php` — I4/I4b/I4c)
"Esquecer este doc" é operação sensível: a UI pede digitar `CONFIRMO`, mas quem **garante** é o
servidor — um cliente adulterado não pode apagar por engano. E o delete é *soft*: o registro
permanece no banco para auditoria, recuperável. Âncora: SDD `CU-KB-04` · SPEC §8 (LGPD) ·
`KbController@softDelete` (validação `required|in:CONFIRMO`).
**Pronto quando:** `DELETE /kb/{slug}` **sem** o campo de confirmação (ou com valor diferente) é
rejeitado por validação **e o documento continua não-deletado**; com a confirmação correta o documento
passa a contar como deletado **sem sumir da tabela** (continua recuperável).

## UC-KB-05 — Restaurar documento deletado `[LGPD]`
Status: 🧪 (`KbIndexContratoTest.php` — I5/I5b)
O soft-delete é reversível em 30 dias — restaurar devolve o documento à lista. Restaurar algo que não
está deletado não pode responder "ok" (sucesso falso é a classe de bug que o projeto já catalogou:
ação que afirma conclusão sem ter ocorrido). Âncora: SDD `CU-KB-05` · SPEC §8 · `KbController@restore`
(`onlyTrashed()->firstOrFail()`).
**Pronto quando:** um documento deletado volta a não-deletado após `POST /kb/{slug}/restore`; e a
mesma chamada sobre um documento **não** deletado não devolve sucesso.

## UC-KB-06 — Abrir a tela é leitura pura (auth, sem escrita, sem Job)
Status: 🧪 (`KbIndexContratoTest.php` — I6/I6b/I6c)
Abrir `/kb` não muda nada: nenhuma linha escrita no acervo, nenhum Job enfileirado, nenhuma chamada de
IA (o RAG só roda na ação explícita "Perguntar ao KB"). E visitante anônimo nunca vê a tela — a stack
`auth` barra antes. Âncora: SDD `CU-KB-08` · charter §Non-Goals (a tela não edita) · paridade direta
com `UC-KBV2-01/03/04` da tela irmã, que compartilha o **mesmo** Controller e o mesmo construtor.
**Pronto quando:** GET anônimo em `/kb` não devolve 200 nem 500; a contagem de documentos é idêntica
antes e depois de um GET autenticado; e com a fila fingida nenhum Job é despachado no render.

---

## Backlog — decidido/medido, ainda sem contrato executável

> **Por que não são UC numerados:** um `## UC-` aqui é contrato que o `casos-gate` (required, G-2)
> cobra teste citando o id. Os três abaixo ou dependem de **decisão de [W]** sobre qual é o
> comportamento certo, ou testam código que ainda não existe. Escrever UC agora = contrato que
> bloqueia o merge de quem for atendê-lo ([proibicoes §5 2026-07-16](../../../../memory/proibicoes.md)).
> Ficam **prosa visível sem gate**, com o file/símbolo pronto pra quem construir.

**[BACKLOG] KPI e selects de filtro contam o que o usuário não pode abrir** (SDD `CU-KB-07` / §9 D-1(b))
`buildKpisPayload` roda 5 agregações **cruas** (`count`, `onlyTrashed`, PII, `groupBy type`,
`groupBy module`) enquanto a lista passa por `acessiveisPara`. Efeito: o cabeçalho promete N documentos
e a lista entrega M<N, e os selects de Tipo/Módulo nomeiam módulos cujos documentos o usuário não abre.
**Duas leituras, nenhuma com fonte canon:** *(a)* KPI é métrica do acervo da plataforma (repo-wide,
ADR 0053) e a assimetria é intencional; *(b)* KPI deve espelhar o visível. **Vira UC quando [W] decidir
qual é o contrato** — não escolho o vencedor por conta própria (é produto, não fato).

**[BACKLOG] Histórico de revisões: endpoint vivo, botão desligado** (SDD `CU-KB-06`)
`GET /kb/{slug}/history` responde (até 50 revisões ordenadas por `changed_at` + o `current`), mas a UI
mostra `{n} versões` num botão `disabled` com `title="Em breve (O11)"`. É capacidade paga e não
entregue. **Vira UC quando** o botão ligar (ou quando [W] decidir remover a promessa da UI) — hoje um
teste travaria só o backend, deixando a promessa quebrada intacta na tela.

**[BACKLOG] `/kb/graph` é fachada** (SDD `CU-KB-09` / §9 D-3)
A rota é uma closure `Inertia::render('kb/Graph')` **sem props** e `/kb/graph/data` devolve
`{nodes:[],edges:[],kpis:null}` hardcoded → a tela cai em `_lib/mockGraphData.ts` com badge "modo
mock". O `anchor-lint` já acusa (*"US-KB-006 wired porém NÃO-SERVIDO — 0 hits"*). **Vira UC quando**
existir um Controller do KB servindo `kb_nodes`/`kb_edges` com `business_id` scope — construir isso é
decisão de produto de [W], não conserto de agente.

---

> **Divergência aberta levada a [W] (não corrigida por mim):** o `Index.charter.md` §Restrições Tier 0
> declara *"`business_id` global scope na query do Controller"*. Esta tela lê `mcp_memory_documents`,
> que é **repo-wide por decisão** ([ADR 0053](../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md);
> o docblock do model diz literalmente *"REPO-WIDE: ADR 0053 docs canon do git são da plataforma, não
> per-business"*). Obedecer a letra do charter quebraria o desenho; o isolamento real desta tela é
> `scope_required` + `admin_only` (UC-KB-02). **Restrição declarada é INTENÇÃO — só [W] reescreve**
> ([proibicoes §Precedência](../../../../memory/proibicoes.md)). Registrado no SDD §9 D-4.
