---
date: "2026-08-11"
hour: "11:09 UTC"
topic: "Gap #44 jana_sugestoes decidido (manter declarado), e a revisão adversarial que achou 8 defeitos no canon recém-escrito — incluindo uma frase que mudava a decisão de quem lê"
authors: [C]
prs: [5534, 5538, 5543, 5555, 5557, 5558]
outcomes:
  - "Gap #44 decidido [W]: (c) manter declarado — mas 2 premissas do próprio pedido caíram na medição (a fachada de usuário não existe; o FormRequest não era evidência de desenho)"
  - "3 adversários read-only acharam 8 defeitos MEUS em canon já mergeado; todos re-medidos por mim antes de corrigir (errata #5555)"
  - "O pior foi FRASE, não número: 'o elo faltante tem dono' — COP-010 é título hardcoded sem owner, e convertia 'nunca construído' em 'já encaminhado'"
  - "US-COPI-004 refutada como completa: derivei da âncora do SPEC 3 parágrafos depois de provar que a âncora vizinha mentia"
  - "O conserto do #5538 tinha 5 buracos, um deles 10 linhas acima na mesma função (schema inexistente saía exit 0, calando 4 required)"
  - "validate.mjs: selftest 16 → 31 asserts, mutação 4/4 derrubando o assert exato"
  - "LC-08 78 → 79; MCP caiu na sessão, então as 3 pendências ficaram como chips, não em mcp_tasks"
related_adrs: ["0070-jira-style-task-management-current-md-removed", "0105-cliente-como-sinal-guiar-sem-mandar", "0344-two-strikes-cobre-processo"]
---

# Gap #44 decidido, e a errata da errata

**TL;DR** — [W] pediu decisão sobre `jana_sugestoes` (lida/aceita/rejeitada pela UI, e nada a
preenche). Decidido **(c) manter declarado**. Duas premissas do próprio pedido caíram na medição.
Depois, 3 adversários read-only acharam **8 defeitos meus** no canon que eu tinha acabado de
escrever. 6 PRs mergeados.

## O que foi feito, em ordem

1. **Verifiquei o pedido antes de agir.** Zero escritores: confirmado (`rc=1`, controle positivo
   17 arquivos). Mas a "fachada de usuário" **não existe** — `Chat.tsx:352` passa `null` a
   `belowThread` e `AssistantUiChat.tsx:452` o renderiza **nu** ⇒ zero nós no DOM. E o
   `StoreSugestaoRequest` **não era evidência de desenho**: nasceu na *"Wave 18 MEGA — 21 agents
   Opus rumo meta 97.75"*, docblock `"D8.c (Wave 18 SATURATION)"`. O produtor **nunca existiu**.

2. **Medi produção** (Hostinger, `tinker`): `jana_sugestoes = 0` · `jana_metas = 0`, com controle
   positivo `jana_conversas = 18` · `jana_mensagens = 121`. A Jana é usada; Metas não.

3. **Recomendei (c)**, [W] aprovou, e fiz 3 higienes (âncora do SPEC, deleção do FormRequest
   morto, `SCOPE.md` declarando schema-à-frente).

4. **[W]: "corrija"** → o `validate.mjs` **saía verde sem ter medido nada** (#5538).

5. **[W]: "adversario"** → 3 agentes read-only com escopos disjuntos.

6. **[W]: "pode fazer o 2 e o 3"** → ledger (#5557) + 5 buracos da máquina (#5558).

## Os 8 defeitos (em canon mergeado)

| # | defeito | por que importa |
|---|---|---|
| 1 | *"o elo faltante **tem dono**: `COP-010`"* | **falso** — `'title' => string` hardcoded, sem owner, prefixo do `TASKS.md` legado |
| 2 | `"17 arquivos"` citando comando que dá **4** | 3 docs da leva se contradizendo |
| 3 | `"0 commits em 6383"` sem pathspec | sem ele dá **2** — *os próprios docs* |
| 4 | *"`US-COPI-004` COMPLETA"* | derivada da âncora do SPEC **3 parágrafos depois de provar que a vizinha mentia** |
| 5 | `08-handoff.md` afirmando a "fachada" | é o índice que toda sessão lê primeiro |
| 6 | trava normativa em presente, sem data | no dia da 1ª meta ela mente e segue sendo lei |
| 7 | `STUB` ancorado no método | está no docblock da **classe** |
| 8 | números de prod sem comando/host | irreproduzíveis |

**Único APPROVE limpo:** `PaymentGateway/SCOPE.md` — 4/4 paths, 11/11 drivers 1:1.

## O conserto do #5538 tinha 5 buracos

Um deles **10 linhas acima, na mesma função**: schema inexistente saía `[SKIP] + exit 0` — um typo
no `matrix.schema` **cala 4 contexts required** em silêncio. Some-se: `modoArquivos` com nada
avaliado saía 0; o **4º estado** (`changed-files.txt` vazio = diff que falhou, porque o step usa
`2>/dev/null || true`) **era canonizado pelo meu próprio selftest** como *"verde legítimo"*;
roteador com 3 de 9 famílias testadas (anular o charter passava **16/16**); e o header afirmando
*"FONTE ÚNICA"* quando o roteamento tem 2 implementações (9 vs 7).

O 4º estado **não virou hard-fail**: em produção não foi observado (merge ref; 3 runs, distância
0/0/0) e mexeria em job que sustenta 4 required sem FP medido.

## Onde eu errei, e o padrão

Três erros meus foram pegos **pelo CI ou por [W]**, não por mim:

- `rc=$?` **depois de pipe** — reportei `exit=0` num `catalog-graph` **vermelho**; o `tail -3`
  cortou a linha do `DRIFT`. É a lápide §5 2026-08-08, **reincidida na mesma sessão em que eu a
  citei ao [W]**.
- Usei a âncora do SPEC como prova **depois** de provar que âncoras mentem.
- Quase li `grep rc=0, contagem 2` como *"o main já declarou"* — eram **comentários**; só o parse
  YAML respondeu. Esse eu peguei a tempo.

**O denominador comum não é descuido: é medir a fonte parecida em vez da certa.** E em todos os
casos o instrumento devolveu um **número plausível** — plausível é o que atravessa revisão.

Duas coisas se pagaram sozinhas: o conserto do #5538 **me protegeu na mesma sessão** (rodando o
validate sem `changed-files.txt`, recebi `exit=2 ⛔ NÃO MEDIDO` em vez do verde falso), e o
`memory-schema-guard` barrou este próprio arquivo com `authors: ["claude"]` inválido.

## Pendências

MCP caiu na sessão ⇒ as 3 estão como **chips locais**, não em `mcp_tasks` (ADR 0070):

1. **DECISÃO [W]** — diff vazio deve falhar? O caminho **(b)** (tirar o `|| true` do workflow)
   conserta a causa, não o sintoma.
2. Unificar roteamento 9 × 7 famílias. ⚠️ Se a solução for "PHP chama o `.mjs`", medir antes se há
   `node` no host do cron (§5 2026-08-08).
3. `CONTRACTS.md:362-366` do PaymentGateway — 5 endpoints fantasma, herdados de outra sessão.
