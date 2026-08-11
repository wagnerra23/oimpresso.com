# 2026-08-11 — A `memory/` "bagunçada" que era decisão, e a primeira nota da dimensão memória

> Sessão iniciada por [W] apontando `memory/modulos` + `memory/requisitos`: *"ainda tem módulos que não existem mais, isso é ruim. tem mais alguma coisa quebrada?"*. Terminou em grade pontuada + adversário.
>
> Handoff: [`2026-08-11-1745-a-bagunca-que-era-decisao-e-a-nota-da-memoria.md`](../handoffs/2026-08-11-1745-a-bagunca-que-era-decisao-e-a-nota-da-memoria.md)

## O arco

O pedido parecia faxina. Virou quatro reversões de plano, cinco defeitos reais, e a primeira medição do que a própria memória custa.

## 1. As quatro vezes que a medição derrubou o plano

Esta é a parte reaproveitável. Em nenhuma delas a releitura de código teria salvado — só rodar/abrir.

| eu ia fazer | o que a medição mostrou | custo se tivesse obedecido |
|---|---|---|
| usar `SCOPE.md` pra separar área legítima de órfã | **0 de 72** pastas têm — ele mora em `Modules/<X>/` | gate construído sobre sinal inexistente |
| limpar "7 pastas mortas" | **4 têm conteúdo vivo** — `Dashboard` é o RUNBOOK da tela `/home`, `Copiloto` guarda o plano do Jana Pro, `LaravelAI` é a camada A da stack (ADR 0035) | apagar trabalho do [W] |
| consolidar `Copiloto`→`Jana`, `PontoWr2`→`Ponto`, `LaravelAI`→`Jana` | **já feito em 2026-06-15** (E1 · frente KL) e 07-01 — o que resta são lápides-redirect deliberadas, que existem pra não quebrar links append-only | desfazer decisão registrada |
| mover as 2 "ADRs presas em `proposals/`" | ambas declaram `promoted_to: 0345`, e a 0345 existe `aceito` | **duplicar a ADR 0345** |

E o mesmo padrão nos "31 arquivos soltos": **22 já são lápide/redirect** (lote documentado em `_TRIAGEM-IDENTIDADE-2026-06.md`), 8 são docs de área com prefixo `_`, 1 já está `deprecated`. Publiquei o contrário no [#5595](https://github.com/wagnerra23/oimpresso.com/pull/5595) e corrigi com ERRATA — tachado, não apagado — antes do merge.

**A lição de método:** contei arquivos sem ler o conteúdo, que é LC-08 cometido dentro da auditoria que cataloga LC-08.

## 2. O que estava quebrado de verdade

- **[#5589] `mv-metabolismo` rodava verde há 18 dias sem entregar.** Provado por consequência, não por leitura do YAML: `gh run view --json jobs` mostrava todos os steps `success` e **`Auto-PR` = `skipped`**. O PR só abria com batch proposto, então nas noites sem batch o `vital-signs.json` recém-gerado era descartado com o runner. O `cron-watchdog` dizia `✓ 0d/3d` porque mede *liveness* e *conclusion* — é o vão **"roda ≠ entrega"** que a §5 de 2026-07-29 deixou declarado como resíduo. Fix: split por natureza (batch mantém gate humano; snapshot derivado auto-mergeia como qualquer índice), **uma única** invocação da action, com o comportamento do `add-paths` verificado no README antes de escrever.
- **[#5590] 5 máquinas existiam e ninguém invocava** — 3 `.test.mjs` + 2 `--selftest` embutidos. Detector como juiz: `3 ÓRFÃO + 2 EMBUTIDO` → **🟢 zero** nas duas categorias.
- **[#5596] 6 refs de código apontavam pro stub** de 12 linhas em vez da verdade viva de 422 — uma delas pra **arquivo que não existe**. Dos 49 referrers medidos, **9 são append-only** (Tier 0, intocáveis) e a maioria dos "mutáveis" não era link: `doc-auto-relink.mjs:148` usa `Copiloto-doc.md` como **fixture do próprio selftest**.
- **[#5599] `adr-proposto-parado` gritava há 21 dias sobre pendência inexistente.** A lista de marcadores de formalização veio da auditoria #4034, anterior ao campo `promoted_to`. Corpus medido (105 proposals): `resulting_adr` 3 · `realized_by` 3 · `superseded_by` 1 · **`promoted_to` 2**, ambos apontando ADR existente — FP zero. A:3 → **A:1**.
- **108 testes sem lane** (44 em `Modules/OficinaAuto/Tests` + 64 em `tests/Feature/Cliente`): as 2 lanes foram deletadas em **27/07** *porque estavam vermelhas* e os testes ficaram. **Não consertado — decisão [W].**

## 3. A grade: `memoria-conhecimento` = 7,1

Rodada `full-parcial` ([#5598](https://github.com/wagnerra23/oimpresso.com/pull/5598)) — 23 agentes, 6,27M tokens, base fresca em worktree detached.

Placar: **0 acima-de-categoria** · 3 diferencial-de-integração · 1 empatada · 3 refutadas · 1 `REFUTADO_TB`. Anti-Goodhart: 2 canários plantados, **2 derrubados**, goodhart = 0.

⚠️ **Não é queda dos 7,5 de 18/07** — o conjunto de fraquezas mudou e as 2 reincidentes **subiram**. Ler como Δ é o que a regra 12 do método proíbe.

**O achado estrutural:** 5 das 8 fraquezas já tinham máquina viva que a pesquisa não achou. Causa nomeada: o dossiê dos pesquisadores (`reguas-do-sistema.js:470`) não inclui `MAQUINAS-INVENTARIO.md` — falso-negativo por construção, mesmo padrão do 7/9 da rodada anterior.

## 4. A nota mais baixa virou máquina

F5 (passo de escrita, **5,5**) → [#5601](https://github.com/wagnerra23/oimpresso.com/pull/5601). O `lapide-recheck` passa a medir o que o §5 custa:

```
tamanho  : 336.161 chars = 83,9% do proibicoes.md · teto declarado: NENHUM
por lápide: média 475 palavras · mediana 460 · maior 1.142
ritmo    : 104 lápides em 67 dias = 1,55/dia
estrutura: 96 normais + 8 emenda/meta
           entre as normais: 8 sem "O que foi tentado" · 9 sem "Por que caiu" · 0 sem "O limite"
```

O que torna a métrica honesta é **emenda fora do denominador** — medido, não presumido: as 8 entradas `EMENDA da lápide X` não repetem as 3 partes por construção (7 das 8 não têm "O que foi tentado"), e contá-las seria o falso-positivo da família guard-sintático. Com a separação, "O limite" tem **100%** entre as normais, e o **9** de "Por que caiu" reproduz o número que a grade mediu por outro caminho.

Três "não" assertados no selftest: sem `score`/`nota`/`indice` no retorno (lápide C9), `teto_declarado: null` (declarar orçamento é ato [W]), e `--sample` não encolhe a métrica.

## 5. Armadilhas de ferramenta que custaram tempo

- `/tmp` do node (Windows) **≠** `/tmp` do bash (MSYS).
- `if grep -q <arquivo-inexistente>` devolve "não achou" e vira **falso OK** — cheque existência antes de interpretar ausência.
- `git cat-file -e origin/main:<path>` sofre **MSYS mangling** no `:`; use `git ls-tree ... -- <path>`.
- `node -e` misturando `require` + top-level `await` → `ERR_AMBIGUOUS_MODULE_SYNTAX`.
- Expressão `${{ }}` contendo `: ` precisa de **aspas duplas** no YAML — o validador `js-yaml` pegou uma quebra que eu mesmo introduzi.

## 6. Estado no fechamento

**MCP indisponível a sessão inteira** (`brief-fetch` timeout no `SessionStart`) — o checklist MCP-first do ADR 0130 não pôde ser cumprido; estado apurado por git e declarado no handoff.

`adr-proposto-parado` A:1·B:0·C:2 · `lapide-recheck` 104 lápides, 3 `revisar` · `selftest-registry-check` 🟢 zero órfãos · `reguas-ledger-check --check` rc=0.

## 7. Pendências que são ato [W]

1. **Ratificar a declaração do #5595** — o único ato que fecha o incômodo original. Sem ela, o check que a completaria mediria a opinião de um agente.
2. Número canônico para o `boost-guidelines` `recusado` (único A:1 restante).
3. **Declarar o teto de contexto do §5** — a máquina agora dá o número toda corrida.
4. Decidir os 108 testes sem lane.
