---
date: "2026-08-10"
hour: "19:19 UTC"
topic: "Gap #22 pedia apagar 1 scorecard órfão; as pré-condições do próprio pedido expuseram que a catraca de nota não vê deleção — deletar um scorecard é fuga silenciosa dela"
authors: [C]
prs: [5536, 5542, 5545]
outcomes:
  - "Scorecard órfão jana-painel.yaml removido: era dívida do #5357, que tirou .tsx + charter + casos e esqueceu o scorecard"
  - "Buraco da catraca provado por bite-test: nota 80→70 morde (rc=1); deletar o arquivo sai verde (rc=0)"
  - "LC-11 7→8 (4ª instância de presence-gate já em produção) + lápide §5, sem armar defesa: o conserto óbvio reprovaria toda remoção legítima de tela"
  - "Duas afirmações erradas do próprio gap #22 corrigidas append-only, apontando pro dono do número em vez de restatear"
  - "Achado colateral: kb-index-v2.yaml tem slug que não casa com o path que ele mesmo declara"
related_adrs:
  - 0344-two-strikes-cobre-processo
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Sessão — o scorecard órfão, e a catraca cega à deleção

## Como um pedido de 1 arquivo virou um achado de mecanismo

O gap #22 pedia apagar `memory/governance/scorecards/screens/jana-painel.yaml` — scorecard de `Pages/Jana/Painel.tsx`, tela removida em 06/08. O pedido trazia três pré-condições, e a primeira era *"confirme que remover uma entrada não faz a catraca acusar regressão — verifique se ela compara CONTAGEM ou conjunto por-tela"*.

Responder isso exigiu ler **como** a catraca mede, e é aí que a sessão mudou de assunto.

`scripts/qa/screen-grades-ratchet.mjs`:

- **L57** `readdirSync(DIR)` — o universo vem dos arquivos **presentes no PR**
- **L45** `git show origin/main:<path>` — a base é buscada por arquivo
- **L74** `files.length` — **só impresso**, nunca comparado

Logo: arquivo deletado nunca entra no laço, nenhuma comparação acontece, e a deleção é **invisível** para a catraca. Isso respondia a pré-condição (não haveria falso-vermelho) e simultaneamente revelava que o instrumento tem um vetor de burla que o próprio docblock diz cobrir.

## O bite-test

Rodado em fixture isolada — repo git temporário, `SCREEN_RATCHET_BASE_REF=base`:

```
1) BOA (nada muda)        → rc=0   "2 telas · 🔻 0 regrediram"
2) RUIM (nota 80→70)      → rc=1   "🔻 a.yaml: 80 → 70 (-10)"    ← MORDE
3) DELEÇÃO (b.yaml, 74)   → rc=0   "1 telas · 🔻 0 regrediram"   ← INVISÍVEL
```

O caso 2 é o controle positivo: o silêncio do caso 3 é buraco, não avaria da fixture.

A primeira rodada desse teste foi do adversário. **Refiz do zero** antes de registrar — registrar medição alheia como própria é exatamente o que o ledger pune.

## Por que não virou gate

O conserto óbvio (*"scorecard sumiu do diff → bloqueia"*) reprovaria **toda remoção legítima de tela**, e telas são removidas com frequência — 5 commits recentes deletam scorecard junto com a tela (#5390, #5088, #5135). É a família de guard sintático que o §5 já matou 5×: allowlist-de-pasta, `@scope`, vocabulário (130 FP), `toHaveKey` (100% FP), `toContain`.

Pular o deletado é **correto** quando a tela morreu — foi o que tornou esta deleção segura. O defeito é não distinguir *tela removida* de *scorecard apagado pra fugir de nota que caiu*.

Somado a isso: o gate é advisory (ADR 0314 — nota de tela é quality), o FP não foi medido no corpus real, e a ADR 0344 manda 1ª ocorrência consertar, não codificar.

**O que ficou no lugar do gate:** o par candidato, que é derivável e não precisa de heurística — o scorecard declara `path:`; sumiu o YAML **e** o `.tsx` → legítimo; sumiu o YAML **e** o `.tsx` vive → fuga.

## As três entregas

| PR | O quê | CI |
|---|---|---|
| [#5536](https://github.com/wagnerra23/oimpresso.com/pull/5536) | Remove o scorecard órfão | 98 pass · 0 fail |
| [#5542](https://github.com/wagnerra23/oimpresso.com/pull/5542) | Lápide §5 + LC-11 7→8 | 99 pass · 0 fail |
| [#5545](https://github.com/wagnerra23/oimpresso.com/pull/5545) | Gap #22 resolvido + errata | 99 pass · 0 fail |

Verificado em `main` por conteúdo, não por status de PR: arquivo ausente com controle positivo, `Ocorrências: 8`, lápide e errata presentes.

## A errata do próprio gap

Duas afirmações da linha do gap #22 caíram na medição, e foram corrigidas **append-only** (registradas, não apagadas):

**"Zero referenciadores (`rc=1`)"** — contra `origin/main` dá `rc=0`, e o referenciador **é o próprio documento**. O `rc=1` original provavelmente veio de grepar a worktree antes do doc existir.

**"193 scorecards = as 193 telas"** — nunca foram iguais; o contador de telas é outro sistema. Aqui apliquei §5 2026-07-17 em vez de trocar um número errado por outro escrito à mão: a linha passa a **apontar pro dono** (`npm run screen-coverage:report`) com recibo datado — `206 telas · SCORECARD 191/206 (92,7%)`.

## O adversário, e o que ele custou de correção

[W] pediu adversário depois do meu primeiro veredito. Derrubou três coisas minhas — o "1 referenciador" (grep só do slug; a união dá 16 arquivos), o "nenhum baseline com lista" (existem dois) e o "warn falso" do Check B (é verdadeiro-positivo com remediação errada). E fechou minha lacuna real: eu tinha rodado 3 de ~7 consumidores.

Mas o relatório dele **também** era hipótese. As duas afirmações críticas eu verifiquei sozinho: a árvore estava suja, porém o `writeFileSync` está atrás de `if (flags.has('--json'))` (L520) e eu rodei `--report` — **a escrita foi dele**; e a base estava 7 commits atrás, confirmado com `fetch` próprio.

## Erros meus

- **Grep estreito** (LC-08) — "1 referenciador" medindo só o slug.
- **Quase "consertei" doc sem defeito** — ia escrever runbook sobre o `--json` gravar baseline; o docblock **já documenta em dois lugares**, e o `npm run screen-coverage:report` não passa a flag. Peguei antes de publicar.
- **Perguntei em vez de registrar** — entreguei os 3 achados como *"quer que eu abra PR?"*, e [W] cortou: *"caramba eu tenho opção de escolha aqui?"*. A regra já estava no §5 2026-07-27 — o registro é do agente, [W] decide só soberania.
- **`rc=$?` depois de pipe** e **grep casando o nome do check** (`memory-health (enforce — fail-class bloqueia)` apareceu como falha estando `pass`).

## Notas de instrumento

- `git ls-tree` devolve **rc=0 com saída vazia** quando nada casa (diferente de `grep`) — prova de ausência ali é saída vazia **contra controle positivo**.
- O `main` andou **três vezes** durante a sessão sem ninguém tocar na minha branch. Base medida antes de cada write e de cada PR.
- `git stash list` mostrou pilha de outras sessões — não encostei (§5 2026-07-27: a pilha é do repo, não do worktree).
- MCP fora do ar no fechamento (`-32603 Bridge fetch error` nas três chamadas do checklist). Declarado como ausência de medição, não como ausência de pendência.

## Resíduo

Dois itens, ambos decisão [W], nenhum bloqueia trabalho: armar (ou não) a defesa da catraca, e o `kb-index-v2.yaml`, cujo slug não casa com o `path:` que ele mesmo declara.
