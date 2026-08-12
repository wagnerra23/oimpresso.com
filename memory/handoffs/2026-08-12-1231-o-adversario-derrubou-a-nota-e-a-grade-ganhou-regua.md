---
date: "2026-08-12"
time: "12:31 UTC"
slug: o-adversario-derrubou-a-nota-e-a-grade-ganhou-regua
tldr: "Continuação do handoff de 2026-08-11 17:45. O adversário derrubou o 7,1 da memória — não por ser alto ou baixo, mas por não ser medição: escala sem rubrica (±0,5 de ruído), denominador trocado entre rodadas e evidência truncada em 200 chars. Os três consertos entraram. E o outcome (context_recall 0,42 contra âncora 0,85) passou a viajar ao lado da nota, em coluna que não contamina a média."
decided_by: [W]
prs: [5634, 5645, 5648]
us: []
next_steps:
  - "Ratificar a declaração do #5595 (flipar status: proposal + preencher as 44 classes que exigem julgamento) — segue sendo o único ato que fecha o incômodo original do [W]"
  - "Declarar o teto de contexto do §5 — o lapide-recheck imprime o número toda corrida e teto_declarado segue null DE PROPÓSITO (ato [W], não de agente)"
  - "Dar número canônico ao 2026-08-03-incorporar-boost-guidelines-skills.md (status: recusado) — precedente 0290-fidelity-lock-v0-recusado"
  - "Rodar a grade DEPOIS destes merges: será o primeiro retrato com rubrica + recibo + caveat + outcome. O 7,1 foi a última nota inconferível"
  - "Decidir os 108 testes sem lane (44 Modules/OficinaAuto/Tests + 64 tests/Feature/Cliente) — lanes deletadas em 27/07 por estarem vermelhas"
---

# O adversário derrubou a nota — e a grade ganhou régua

> Continuação direta de [`2026-08-11-1745`](2026-08-11-1745-a-bagunca-que-era-decisao-e-a-nota-da-memoria.md). Aquele fecha a auditoria da `memory/`; este fecha o que veio depois: o passe adversarial sobre a própria grade e os consertos que ele destravou.

## O veredito que reorganizou tudo

[W] perguntou *"a memória está nota 9?"*. O adversário (read-only, controles rodados antes de opinar: clone completo · `gate-selftest` 72/72 · `memory-health` 0 🔴) respondeu que **a pergunta não tem resposta com este instrumento** — e provou em três frentes:

| achado | evidência |
|---|---|
| **denominador trocado** | 08-08 = **7,7 com 3** fraquezas · 08-11 = **7,1 com 8** · interseção **1**. O ledger tem 13 (média 7,4). *"6,9 · 7,1 · 7,4 · 7,7 são todos a nota da memória hoje"* |
| **escala sem rubrica** | o campo era número livre. 4 fraquezas com o **mesmo veredito** receberam 9,0 · 7,5 · 6,0 · 6,0 — spread 3,5 ⇒ ruído ≈ **±0,5**. O `7,7→7,1` estava **dentro do ruído** |
| **evidência destruída** | truncada em 200/250 chars na persistência: as 8 entradas saíram com 202–249, **todas cortadas no meio da palavra**. Nenhuma das 8 notas era auditável pelo ledger |

E o golpe que fecha: a dimensão **tem** régua funcional — `context_recall_avg` **0,42** contra âncora **0,85–0,90** escrita no próprio `Jana/SPEC.md` — e ela ficou **fora do denominador**. Todas as 8 células perguntavam *"existe mecanismo?"*; nenhuma perguntava *"funciona?"*.

**Correção do que o handoff anterior afirmou:** eu disse *"7,1 não é queda de 7,5"*. Direção certa, baseline errado — o retrato anterior era **7,7 (08-08)**, e eu não percebi que ele carregava o caveat de denominador em 4 dimensões enquanto o de 08-11 não carregava em nenhuma.

## Os consertos que entraram

| PR | o quê |
|---|---|
| [#5634](https://github.com/wagnerra23/oimpresso.com/pull/5634) | **rubrica** — a escada que o projeto já aplicava em prosa (LIGUE A MÁQUINA item 2 · ADR 0275 · 0336 DR-2 · LC-11 · LC-13) vira escala 0-2/3-4/5-6/7-8/9-10, com bounds no schema. `null` vira resposta válida: *"não pontuável"* deixou de exigir número de conforto |
| [#5645](https://github.com/wagnerra23/oimpresso.com/pull/5645) | **outcome** em coluna própria — fase com 1 agente mecânico (`effort: low`) que lê fonte declarada e transcreve. **3 de 12** dimensões têm fonte; as 9 restantes ficam declaradamente sem |
| [#5648](https://github.com/wagnerra23/oimpresso.com/pull/5648) | re-cura do `adr-alias-map` (0101 deixou de colidir) + `_HOOKS-INDEX` regenerado |

O `#5619` (de um chip) trouxe a terceira perna: evidência íntegra, `incremento` persistido e **caveat de denominador derivado** do diff de ids.

## A regra que impede o pecado C9

Outcome grava em campo **próprio** e nunca soma, faz média ou deriva nota. Não é promessa em comentário: o assert *"as notas são IDÊNTICAS com e sem outcome"* prova que não vaza, e a mutação prova que o assert morde.

**Um dono por métrica** (regra 13): `context_recall` pertence a `qualidade-drift-ia-producao`. `memoria-conhecimento` fica **sem outcome próprio de propósito** — emprestar do vizinho seria a dupla contagem que o adversário flagrou na F2.

## O que precisou de mutação pra ser confiável

Três vezes um assert pareceu decorativo e **era a mutação que não tinha aplicado**:

- rubrica: 1ª mutação com regex escapado falhou em silêncio → `rc=0`. Refeita contando ocorrências (3→0) → `rc=1` no assert exato.
- outcome: duas mutações verificadas (regra C9 1→0; `context_recall_avg` 3→0), ambas derrubam.

**Conferir que a mutação aplicou é parte do teste.** Sem isso, *"o teste não pegou"* e *"o teste não rodou"* são indistinguíveis.

## Conflitos: 3, todos resolvidos preservando os dois lados

Índice de handoffs · `#5619` × `#5634` · e o teste de `reguas-workflow` (ambos os lados terminam **sem a chave de fechamento**, que vem do contexto compartilhado — a união exige fechar o primeiro bloco explicitamente). Em nenhum um `--ours` teria sido correto.

No `#5634` o `Dedup-ack` carrega a **prova** e a receita: merge de teste do `#5619` → união → **selftest combinado 80 asserts rc=0** com os 4 blocos vivos → `merge --abort`.

## Armadilhas novas (além das do handoff anterior)

- **`export` no meio do workflow quebra o carregamento** — o arquivo não é módulo ESM comum; só o `export const meta` do topo é tratado pelo runtime.
- **Crase dentro de template literal** termina a string — quebrou um prompt antes de rodar.
- **Serializar JSON com indent reformata o arquivo inteiro**: 115+/34- pra remover **uma** entrada, porque o original mantinha objetos em linha única. O sinal foi o **tamanho do diff**, não erro.
- **`git stash -u` em árvore limpa não cria entrada** — a pilha tinha 3 entradas de **outras sessões**. Não dei `pop`.
- **`rg -rn`** é lido como `-r n` (replace) e produz saída-lixo plausível.
- **String longa com crase/`$` via `bash -c "node -e ..."`** é interpretada pelo shell antes do node — usar a ferramenta de edição, não o shell.
- ⚠️ **Este arquivo sumiu entre a escrita e o commit** (primeira versão, ~12:31). Não achei causa — não estava no worktree nem no repo principal, e `git status` não o listava. Reescrito e commitado no mesmo passo. Lição operacional: **commitar logo após escrever**, não em lote.

## Estado MCP no fechamento

MCP **disponível** nesta sessão (diferente do handoff anterior, que teve que declarar o contrário):

- `cycles-active`: **nenhum cycle ativo** em COPI
- `my-work`: **8 tasks**, todas em REVIEW (US-TR-309/310/311 · US-PROD-027 · US-INFRA-023/048 · US-TR-305/306)
- `sessions-recent`: 4 logs indexados em 2026-08-12
- `decisions-search`: **ADR 0377** aceita hoje — append-only de ADR admite exceção por label `adr-body-edit-W` (Constituição v1.3.0)

## Os 7 chips

Todos executados em sessões frescas, 6 com PR mergeado: `catalog-graph` ADR→ADR · `hook-replay` 1→2 contratos · dossiê lê `MAQUINAS-INVENTARIO` · triagem das 3 lápides `revisar` · resíduo do ledger · e o combinado (`#5619`). O de medir o vão do gate de refutação encerrou **sem PR** — desfecho previsto pelo prompt, mas o resultado não chega a esta sessão.
