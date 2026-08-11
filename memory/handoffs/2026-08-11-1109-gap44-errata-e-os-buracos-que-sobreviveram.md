---
date: "2026-08-11"
time: "11:09"
slug: "gap44-errata-e-os-buracos-que-sobreviveram"
tldr: "Decisão do gap #44 (manter jana_sugestoes declarada) mais 6 PRs mergeados — e a revisão adversarial achou 8 defeitos MEUS no canon que eu tinha acabado de escrever, incluindo uma frase que mudava a decisão de quem lê."
decided_by: ["W"]
prs: [5534, 5538, 5543, 5555, 5557, 5558]
us: []
next_steps:
  - "Decisão [W]: diff vazio no validate.mjs deve falhar? (3 caminhos; o (b) conserta a causa no workflow)"
  - "Unificar roteamento arquivo->schema: validate.mjs 9 famílias vs JanaValidateMemoryCommand 7"
  - "CONTRACTS.md:362-366 do PaymentGateway declara 5 endpoints -> PaymentGatewayController@* inexistentes"
  - "Registrar as 3 acima em mcp_tasks quando o MCP voltar (hoje só existem como chips locais)"
related_adrs: ["0070-jira-style-task-management-current-md-removed", "0105-cliente-como-sinal-guiar-sem-mandar", "0344-two-strikes-cobre-processo"]
---

# Gap #44 decidido, e os buracos que sobreviveram ao conserto

## O pedido e a decisão

Entrada: decidir o gap #44 do `AUDIT-GAPS-2026-08-10` — `jana_sugestoes` é lida, aceita e
rejeitada pela UI, e **nada** a preenche. Três opções: (a) construir o produtor,
(b) remover a superfície, (c) manter declarado.

**Decisão [W]: (c) manter declarado.**

Mas **duas premissas do pedido não sobreviveram à medição**:

1. **Não existe "fachada de usuário".** `Chat.tsx:352` passa `null` a `belowThread` quando a
   lista é vazia, e `AssistantUiChat.tsx:452` o renderiza **nu**, sem wrapper — zero nós no DOM.
   Os botões vivem dentro do `PropostaCard`, instanciado só pelo `.map`. **A urgência do pedido
   inteiro vinha dessa premissa.**
2. **O `StoreSugestaoRequest` não era evidência de desenho.** Nasceu em
   `f46a6864082 "Wave 18 MEGA — 21 agents Opus rumo meta 97.75"`, docblock `"D8.c (Wave 18
   SATURATION)"` — artefato de wave perseguindo **nota**, validando um fluxo manual que ninguém
   pediu e nenhuma rota expunha.

E o que o pedido não tinha: **o produtor nunca existiu** (`git log -S … -- '*.php'` → 0 commits),
não é regressão. A cadeia está completa menos **um elo** — `SuggestionEngine::sugerir()` se
autodeclara STUB no docblock da classe, devolve o array e não grava; o `ChatController` injeta o
engine e nunca o chama.

## Os 6 PRs (todos MERGED)

| PR | o que |
|---|---|
| #5534 | `jana_sugestoes` declarada schema-à-frente + `StoreSugestaoRequest` morto deletado |
| #5538 | `validate.mjs` parou de sair VERDE sem ter medido nada (cego ≠ limpo) |
| #5543 | `contains[]` sem lastro 6→0 + 12 arquivos CNAB reais declarados |
| #5555 | **errata dos 8 defeitos** que a revisão adversarial achou |
| #5557 | LC-08 78 → 79 |
| #5558 | 5 buracos que **sobreviveram** ao conserto do #5538 |

## O que a revisão adversarial pegou (3 agentes read-only, escopos disjuntos)

Vereditos: `SPEC.md` **REJECT** · `Jana/SCOPE.md` **REJECT** · `AUDIT-GAPS` **REVIEW** ·
`PaymentGateway/SCOPE.md` **APPROVE** (o único limpo).

**O mais grave foi uma FRASE, não um número.** Escrevi *"o elo faltante **tem dono**: task
`COP-010`"* e usei como argumento central da recomendação. Medido: 4 hits, 3 são meus próprios
docs; o único real é `'title' => 'COP-010 …'`, string **hardcoded** num array de backfill,
`status: backlog`, **sem owner**, prefixo do `TASKS.md` legado (o MCP usa `COPI-NNN`).
Não é id consultável. Nas palavras do adversário: *é a frase que converte "nunca construído" em
"já encaminhado"*.

**O erro de método mais feio:** afirmei `US-COPI-004` **completa** derivando da âncora do SPEC —
**três parágrafos depois de provar que a âncora vizinha mentia** — e ela carregava o mesmo
`verificado@dd3ed7c` desacreditado. Re-medida: a mecânica confere, mas é **inalcançável**
(o funil não existe), tem **zero teste** do fluxo e o **DoD falha em 2 de 3** (Horizon em
`dont-discover`; redirect vai pra `metas.show`). Meu *"remover mataria capacidade testada"* era
falso **nas duas metades**.

Mais: `"17 arquivos"` citando um comando que dá **4**; `"0 commits em 6383"` sem pathspec (sem ele
dá **2** — os próprios docs); `rc=$?` **depois de pipe**, reportando `exit=0` num `catalog-graph`
que estava **vermelho** (quem pegou foi o CI); `STUB` ancorado no método quando está na classe;
trava normativa em presente sem data; e o índice `08-handoff.md` ainda afirmando a "fachada"
que eu tinha retratado.

## E o conserto do #5538 tinha 5 buracos (PR #5558)

O #5538 fechou **um** fail-open. Sobreviviam outros — um **10 linhas acima, na mesma função**:

- **schema inexistente** saía `[SKIP] + exit 0`. Um typo no `matrix.schema` **cala 4 contexts
  required** em silêncio. Agora `exit 2`.
- **`modoArquivos`**: nada avaliado saía 0 — *"pedi pra validar N, nenhum foi avaliado"*
  indistinguível de *"tudo conforme"*. Agora `exit 2`.
- **4º estado**: `changed-files.txt` presente e **vazio** é o que o workflow produz quando o
  `git diff` falha (`2>/dev/null || true`), e **o selftest do #5538 canonizava isso como "verde
  legítimo"**. Consertei um fail-open e travei outro no mesmo PR.
- **Roteador**: 3 de 9 famílias testadas — anular o charter deixava o selftest **16/16 verde**.
- Header afirmava *"FONTE ÚNICA"*: o **schema** é único, o **roteamento** não (9 vs 7).

Selftest **16 → 31** asserts; mutação **4/4** derrubando o assert exato.

## Estado MCP no momento do fechamento

⚠️ **O servidor MCP caiu durante a sessão** e não voltou. `cycles-active`, `my-work`,
`decisions-search` e `tasks-create` ficaram **indisponíveis** — as ferramentas saíram da lista de
deferred com aviso explícito de desconexão.

Consequência que importa: as 3 pendências abaixo **não** foram registradas em `mcp_tasks`
(ADR 0070). Existem hoje como **chips locais** desta sessão (não persistem entre reinícios do app)
e como declarações no código mergeado. Quem retomar: criar as 3 no MCP.

Fallback usado: `git ls-files memory/handoffs/2026-08-1*` para conferir duplicação antes de criar
este arquivo (5 handoffs de 08-10, nenhum deste tema).

## Pendências

1. **DECISÃO [W]** — diff vazio no `validate.mjs` deve falhar? Não armei: em produção **não foi
   observado** (o `actions/checkout` usa o merge ref; 3 runs, distância 0/0/0), e mexeria em job
   que sustenta 4 required sem FP medido. O caminho **(b)** — tirar o `|| true` do step do
   workflow — provavelmente é o certo, porque conserta a **causa**.
2. Unificar roteamento `validate.mjs` (9 famílias) × `JanaValidateMemoryCommand` (7).
   ⚠️ Cuidado: se a solução for "o PHP chama o `.mjs`", medir antes se há `node` no host do cron
   (§5 2026-08-08).
3. `CONTRACTS.md:362-366` do PaymentGateway — 5 endpoints `→ PaymentGatewayController@*`
   inexistentes. Herdado de outra sessão.

## Lição perene desta sessão

Os 8 defeitos têm **um denominador comum**, e não é descuido: **medir a fonte parecida em vez da
certa**. `git log` sem pathspec; um comando citado com o número de outro; a âncora do SPEC usada
como prova logo depois de eu provar que âncoras mentem; `rc=$?` depois de pipe. Em todos, o
instrumento devolveu um **número plausível** — e plausível é o que atravessa revisão.

O que funcionou como defesa: **adversário read-only com escopo disjunto**, e **re-medir cada
achado dele** antes de corrigir (não herdar número de agente).
