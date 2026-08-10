---
date: "2026-08-10"
time: "19:19"
slug: scorecard-orfao-e-a-catraca-que-nao-ve-delecao
tldr: "Pedido era apagar 1 scorecard órfão (gap #22). As pré-condições do próprio pedido viraram o achado: a catraca de nota monta o universo com readdirSync do lado do PR, então DELETAR um scorecard é fuga silenciosa dela — provado por bite-test (nota 80→70 morde; deleção sai verde). 3 PRs mergeados. Não armei defesa: o conserto óbvio reprovaria toda remoção legítima de tela. Par candidato derivável registrado."
prs: [5536, 5542, 5545]
us: []
next_steps:
  - "Decidir se arma a defesa da catraca (par candidato pronto; hoje advisory, FP não medido)"
  - "Decidir kb-index-v2.yaml: slug não casa com o path que ele declara (kb/Index.v2.tsx)"
related_adrs:
  - 0344-two-strikes-cobre-processo
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
---

# Handoff — o scorecard órfão, e a catraca que não vê deleção

## O pedido, e por que ele mudou de tamanho

O gap #22 da `AUDIT-GAPS-2026-08-10.md` pedia uma coisa pequena: apagar `memory/governance/scorecards/screens/jana-painel.yaml`, scorecard de uma tela que não existe desde 06/08.

O pedido vinha com **três pré-condições explícitas** — *confirme que remover não faz a catraca acusar regressão; se ela tiver baseline próprio, re-keye no mesmo PR; rode antes e depois e cole os dois resultados*. Cumprir a primeira foi o que abriu a sessão: para saber se a catraca acusaria, tive que ler **como** ela mede. E ela mede de um jeito que a torna cega ao que eu ia fazer.

## O achado

`scripts/qa/screen-grades-ratchet.mjs` monta o universo com `readdirSync` dos arquivos **presentes no PR** (L57) e, para cada um, busca a base com `git show origin/main:<path>` (L45). Arquivo deletado **nunca entra no laço**. `files.length` (L74) é **só impresso**, nunca comparado.

O docblock (L8-9) promete *"robusto contra burla: compara sempre vs `origin/main` (não vs o `baseline_anterior` do próprio arquivo, que o PR poderia baixar junto)"* — e **é**, contra aquele vetor. Contra **apagar o arquivo**, não.

Bite-test em fixture isolada, reproduzido nesta sessão (a primeira rodada foi do adversário; refiz porque registrar medição alheia como minha é o que o ledger pune):

| caso | rc | saída |
|---|---:|---|
| BOA — nada muda | 0 | `2 telas · 🔻 0 regrediram` |
| RUIM — nota `80→70` | **1** | `🔻 a.yaml: 80 → 70 (-10)` ← **morde** |
| DELEÇÃO — `b.yaml` (nota 74) sai | **0** | `1 telas · 🔻 0 regrediram` ← **invisível** |

O caso RUIM é o controle positivo: prova que o instrumento funciona, e que o silêncio do caso 3 é o buraco, não avaria da fixture.

**É LC-11 (7→8), 4ª instância de presence-gate já em produção** — e confirma o predicado que a própria entrada da classe vinha prescrevendo: apareceu ao confrontar a **SAÍDA** do mecanismo com a **FONTE** que ele diz cobrir, nunca relendo o código, porque a lógica parece correta linha a linha.

## Por que NÃO armei a defesa

O conserto óbvio — *"scorecard sumiu do diff → bloqueia"* — **reprovaria toda remoção legítima de tela**, e telas são removidas com frequência (5 commits recentes deletam scorecard junto com a tela: #5390, #5088, #5135). Seria a família de guard sintático que o §5 já matou 5× (allowlist-de-pasta · `@scope` · vocabulário 130 FP · `toHaveKey` 100% FP · `toContain`).

Pular o deletado é **correto** quando a tela morreu — foi exatamente o que tornou esta deleção segura. O defeito é o instrumento não distinguir *tela removida* de *scorecard apagado pra escapar de nota que caiu*.

Somado: gate é **advisory** ([ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md) — nota de tela é quality, não Tier-0), FP não medido no corpus, e a [ADR 0344](../decisions/0344-two-strikes-cobre-processo.md) manda 1ª ocorrência **consertar, não codificar**.

**Par candidato registrado** (derivável, pra 2ª ocorrência nascer com trabalho pronto): o scorecard declara `path:`. Sumiu o YAML **e** o `.tsx` daquele path → legítimo. Sumiu o YAML **e** o `.tsx` continua vivo → vetor de fuga. Predicado determinístico, sem ler prosa.

## O que entrou

| PR | O quê | CI |
|---|---|---|
| [#5536](https://github.com/wagnerra23/oimpresso.com/pull/5536) | Remove o scorecard órfão | 98 pass · 0 fail |
| [#5542](https://github.com/wagnerra23/oimpresso.com/pull/5542) | Lápide §5 + LC-11 7→8 | 99 pass · 0 fail |
| [#5545](https://github.com/wagnerra23/oimpresso.com/pull/5545) | Gap #22 resolvido + errata | 99 pass · 0 fail |

**296 checks, zero falha.** Os três mergeados por [W]. Verificado em `main` por **conteúdo**, não por status: `jana-painel.yaml` ausente (controle positivo `jana-index.yaml` presente), `Ocorrências: 8`, lápide e errata presentes.

Enquadramento corrigido no caminho: **não era faxina, era dívida do [#5357](https://github.com/wagnerra23/oimpresso.com/pull/5357)**, que removeu `.tsx` + charter + casos e esqueceu o scorecard.

## O adversário derrubou 3 coisas minhas

[W] pediu adversário depois do meu primeiro veredito. Ele refutou:

1. **"1 referenciador"** — grepei só o slug. A união (`jana-painel` + `Jana/Painel` + `Painel.tsx` + `Pages/Jana/Painel`) dá **16 arquivos**. A conclusão sobreviveu, mas por motivo que eu não tinha medido: só 3 não são prosa, e os 3 caem (um é `Financeiro/Painel.tsx`, outro é comentário, outro é lido só por `file_exists()`).
2. **"Nenhum baseline com lista/contagem"** — falso. Existem dois (`screen-grades-baseline-2026-05-30.json`, que cita `Jana/Painel`, e `screen-coverage-baseline.json`). Nenhum precisa re-key.
3. **"Warn falso" no Check B** — impreciso. É verdadeiro-positivo com remediação errada: o check não distingue *tela mudou* de *tela foi deletada*.

E fechou minha lacuna real: eu tinha rodado **3 de ~7** consumidores. Ele rodou o resto — `memory-health` nos dois modos do required, `vital-signs`, `mv-metabolismo`, `prototipo-readiness`, `screen-coverage --selftest/--check`, todos rc=0.

**Mas o relatório dele também era hipótese.** Verifiquei sozinho as duas afirmações críticas: a árvore **estava** suja (`screen-coverage-baseline.json`), porém o `writeFileSync` está atrás de `if (flags.has('--json'))` (L520) e eu rodei `--report` — **a escrita foi dele**; e a base **estava** 7 commits atrás, confirmado com `fetch` próprio.

## Erros meus, registrados

- **Grep estreito** (LC-08): reportei 1 referenciador tendo medido só o slug.
- **Ia "consertar" doc que não tinha defeito**: preparei linha de runbook avisando que `--json` grava o baseline — o docblock **já documenta em dois lugares** (`stdout: read-only, sem efeito colateral` / `--json: escreve...`), e `npm run screen-coverage:report` não passa a flag. O adversário rodou `--json` apesar de estar escrito. Peguei antes de publicar; escrever seria propagar claim alheia sem verificar a fonte.
- **Perguntei em vez de registrar**: entreguei os 3 achados como *"quer que eu abra PR?"*. [W] cortou — *"caramba eu tenho opção de escolha aqui?"*. A regra já estava no §5 2026-07-27: **o registro é do agente; [W] decide só soberania** (apagar alarme, promover gate a required, podar capacidade). Nenhum dos três era soberania.
- **`rc=$?` depois de pipe** e **grep casando o NOME do check**: a linha `memory-health (enforce — fail-class bloqueia)` apareceu no meu filtro de falhas com status `pass`. Refiz filtrando a coluna 2.

## Achado colateral — declarado, não tratado

Sobra **1 de delta**: 192 `.yaml` no disco × 191 telas cobertas. **Não é órfão de path morto** — varri os 192, todos os `path:` existem. É **slug**: `kb-index-v2.yaml` deriva `kb-index.v2` do próprio `path:` (`kb/Index.v2.tsx`), então o mapa de cobertura não o casa. Outro módulo, e o conserto implica renomear arquivo ou tela.

## Nota de método que vale carregar

O `main` andou **três vezes** durante a sessão (0 → 7 → 2 → 2 commits atrás), sem ninguém mexer na minha branch. Medi a base antes de cada write e antes de cada PR. E o `git stash list` mostrou pilha de **outras sessões** — não encostei (§5 2026-07-27: a pilha é estado global do repo, não do worktree).

Duas leituras de instrumento que quase viraram fato: `git ls-tree` devolve **rc=0 com saída vazia** quando nada casa (diferente de `grep`), então a prova de ausência ali é a saída vazia **contra controle positivo**; e `git grep` contra `origin/main` deu resposta diferente do grep na worktree, porque o doc que referencia o arquivo tinha acabado de ser commitado.

## Estado MCP no momento do fechamento

⚠️ **Não medido — o servidor MCP estava fora do ar.** As três chamadas do checklist falharam:

- `cycles-active` → `MCP error -32603: Bridge fetch error: fetch failed`
- `my-work` → `Server Oimpresso MCP — Wagner unavailable`
- `decisions-search` → `Server Oimpresso MCP — Wagner unavailable`

Registro isso como **ausência de medição, não como ausência de pendência**. O único estado conhecido é o brief que o hook `SessionStart:resume` entregou, **datado**: Brief #494, gerado ~91 min antes do fechamento — 5 HITL pendentes [W], 672 US não atribuídas (520 sem dono), SDD composta 55,2, migration aging e PRs aguardando review sem nada crítico. Isso é retrato de ~18:00Z, não do fechamento.

Nada nesta sessão criou ou moveu task no MCP.

## Próximo passo

Os dois itens abertos são decisão [W], estão nos `next_steps` do frontmatter, e nenhum bloqueia trabalho: armar (ou não) a defesa da catraca, e o `kb-index-v2`.
