---
date: "2026-08-09"
time: "11:00 UTC"
slug: reguas-delta-4-propostas-refutadas-e-o-preco-que-faltava
tldr: "Rodada delta da grade de réguas. O retrato saiu com notas vazias (schema deixava a âncora opcional) — consertado com guard que aborta antes de gastar. Depois [W] mandou ligar um adversário: ele derrubou as 4 propostas que eu fiz pra fechar o loop de aprendizado, e a ordem corrigida dele rendeu 3 consertos reais — o pr-critic saía verde sem criticar há um mês, o ledger-check saía 0 com 3 violações, e claude-opus-5 não tinha preço, zerando toda medição de dinheiro."
prs: [5480, 5483, 5484, 5485, 5487]
decided_by: [W]
related_adrs: [0353-maquina-evolucao-reguas-looping, 0344-two-strikes-cobre-processo]
next_steps:
  - "Decidir o modelo do pr-critic (var PR_CRITIC_MODEL, sem tocar em secret) — senão ~70% das runs ficam vermelhas num advisory"
  - "Re-rodar a grade só quando houver Δ material: as notas de 08-08 seguem com a ressalva de denominador no errata_notas"
---

# Grade de réguas: o adversário derrubou as 4 propostas, e o que shipou veio da fila

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `my-work` → 8 tasks, **todas em REVIEW**, nenhuma tocada nesta sessão (US-TR-309/310/311, US-PROD-027, US-INFRA-023/048, US-TR-305/306).
- Handoffs irmãos do mesmo par de dias: `2026-08-09-0042`, `-0049`, `-0050`, `-0057` (sessões paralelas; nenhuma tocou `memory/reguas/` nem `scripts/governance/agent-cost-per-pr.mjs`).
- Conflito de contador evitado: outra sessão levou o **LC-08 de 63 → 65** enquanto eu trabalhava. Li o valor de `origin/main` **na hora de escrever**, não o do início da sessão, e incrementei 65 → 69.

## O que aconteceu

**1. A rodada (modo `delta`, run `wf_32c91912-fca`, 43 agentes, 10,9M tokens, 0 erro).** Mediu 24 fraquezas e re-vereditou 15 claims — e gravou o retrato com `notas: {}`. O agente `delta-scan` omitiu `ultimo_retrato`, que era **opcional** no schema; a composição itera as chaves de `ultimo_retrato.notas` e rodou zero vezes. O checkpoint gravou assim mesmo, **depois** dos 39 agentes. Duas travas ([#5480](https://github.com/wagnerra23/oimpresso.com/pull/5480)): `required` no schema + guard que **aborta antes da fase Verificar** — falha em 1 agente, não em 39.

**2. [W] mandou ligar um adversário antes de eu construir. Ele derrubou as 4.** Cada uma por um motivo medido, e duas delas por premissa **factualmente falsa minha**:

| | kill |
|---|---|
| **P1** cadência do delta | custo é `CAP=24 × effort:high`, não a janela: 13d→47 alvos, 7d→45, 3d→42, **os três cortados pro mesmo 24** ⇒ semanal gasta MAIS. E a ADR 0353 **D2 já recusou as duas formas** |
| **P2** custo no retrato | campo auto-declarado (o workflow não conta token), série de 1 ponto, dono existe (`agent-cost-per-pr.mjs`) |
| **P3** `journal` na fraqueza | o `journal` das claims é ponteiro de run de ~15 chars, **não trajetória** — eu afirmei o formato sem ler o conteúdo |
| **P4** PR→fraqueza | lápide nominal de §5 2026-07-26 + presence-gate + pior Goodhart das quatro |

**3. O furo dele também estava stale, e isso importa.** Ele apontou a D3 como *"13 dias parada"*; a reconciliação **foi feita** no [#4820](https://github.com/wagnerra23/oimpresso.com/pull/4820) (6→3), e as 3 restantes são **irrecuperáveis por ausência de fonte**. Conferido: `REFUTADO_TB` **nunca existiu** no `claims.json` — grep nos 4 commits, 0 em todas as versões.

**4. A ordem corrigida rendeu 3 consertos reais** — nenhum na forma que eu propus:

- [#5483](https://github.com/wagnerra23/oimpresso.com/pull/5483) **`pr-critic` saía verde sem produzir crítica há um mês.** 1010 runs, **zero comentários** (2 oráculos + controle positivo). `403 model_not_found` mascarado por `bash -e` sem `pipefail` no `| tee`. Bite-test executa o `run:` **real** do YAML com stub.
- [#5484](https://github.com/wagnerra23/oimpresso.com/pull/5484) **`ledger-check` rodava sem `--check`** e saía 0. Não dava pra só ligar: as 3 violações são permanentes ⇒ vermelho eterno. Viraram **dado** (`residuos_irrecuperaveis`), conhecido não morde, **novo morde**, resíduo que sumiu é cobrado pra remoção.
- [#5485](https://github.com/wagnerra23/oimpresso.com/pull/5485) **`claude-opus-5` não estava em `PRECOS_USD_MTOK`.** Match é por prefixo e `claude-opus-4-8` não cobre ⇒ o modelo que faz todo o trabalho custava `null` e **toda medição de dinheiro do sistema estava zerada**. Estava nomeado na fila de indexação desde **18/07**, invisível 21 dias — porque o aviso agregado só existia no `renderHuman` (terminal) e **nunca chegava ao brief**. Corrigido nos dois eixos.

## Artefatos gerados

| PR | arquivos | prova |
|---|---|---|
| #5480 | workflow + selftest + ledger + session log | bloco `[8]`, 4 CN; mutação derruba 6 asserts |
| #5483 | `pr-critic.yml` + `critica.test.mjs` | 13→17 asserts; mutação derruba com âncora certa |
| #5484 | `config.json` + `reguas-ledger-check.mjs` + CI | 12 asserts, 4 E2E de CLI; 2 mutações |
| #5485 | `agent-cost-per-pr.{mjs,test.mjs}` | 3 asserts novos; mutação nas 2 direções |
| #5487 | `proibicoes.md` §5 + `LICOES_CODE.md` | LC-08 **65 → 69** |

Session log: [`2026-08-08-reguas-delta-e-a-ancora-que-faltou.md`](../sessions/2026-08-08-reguas-delta-e-a-ancora-que-faltou.md).

## Persistência

- **git:** 5 PRs mergeados em `main` (`#5480` `#5483` `#5484` `#5485` `#5487`). Lápides verificadas em `origin/main` após o merge.
- **MCP:** nenhuma task criada/movida — o trabalho foi de instrumento, não de US. Webhook propaga o canon em ~2min.
- **BRIEFING:** não aplicável — nada em `Modules/<X>/` foi tocado.

## Lições catalogadas

**LC-08 65 → 69**, quatro instâncias minhas, cada uma de uma fonte errada diferente: **(e)** atribuí o custo à janela sem medir o cap (~4× de erro, publicado como recomendação); **(f)** afirmei o formato do `journal` sem ler o conteúdo; **(g)** disse que o wiring do Órgão 5 *"nunca foi feito"* quando a forma ratificada está em `ZELADOR.md:58`, arquivo que eu tinha lido **na mesma sessão**; **(h)** disse a [W] *"a mutação não morde"* sobre meu próprio bite-test, tendo ancorado o `sed` na primeira ocorrência de `PR_CRITIC_MODEL` — o comentário do cabeçalho, não o step.

Um near-miss ficou em prosa, fora do contador: a 2ª mutação do brief **deletou** o bloco em vez de mover, e eu quase reportei isso como prova de que o assert de posição mordia. Refeita imprimindo `bloco presente? true · depois da tabela? true` **antes** de ler o resultado.

**A lição de sistema, que vale mais que as quatro:** o achado que mais pagou não veio de medir o sistema — veio de **ler a fila que o sistema já tinha escrito**. O `claude-opus-5` estava nomeado, com o conserto explícito, há 21 dias. O gargalo não era falta de máquina; era o aviso não alcançar quem lê. Variante nova do padrão do §5: não é *"a máquina não mediu"*, é *"a máquina mediu, disse, e não alcançou o leitor"*.

## Próximos passos pra retomar

```bash
gh pr view 5483 --json url && node scripts/governance/reguas-indexar.mjs
```

1. **Decisão [W] pendente:** o modelo do `pr-critic`. Enquanto o provider estiver quebrado, ~70% das runs ficam vermelhas num advisory. Saída sem tocar em segredo: apontar a var `PR_CRITIC_MODEL` pra um modelo acessível — **não medi** quais o projeto OpenAI alcança.
2. **Fila de indexação:** 11 achados `existia-mas-invisível` pendentes, cada um com destino exato. É o chip mais barato — e a fila acabou de provar que rende.
3. **Não re-rodar a grade por reflexo:** 3 dos 4 consertos são de instrumento, não de capacidade; o retrato de 08-08 segue válido com a ressalva de denominador já gravada no `errata_notas`.

## Pointers detalhados

- §5 `memory/proibicoes.md` → entrada **2026-08-08 (4 propostas, 4 recusadas)** — os kills com números, o limite, e a correção do furo da D3.
- `memory/reguas/config.json` → `residuos_irrecuperaveis` (os 3, com motivo e fonte que resolveria).
- [ADR 0353](../decisions/0353-maquina-evolucao-reguas-looping.md) **D2** (wiring é [W]) e **D3** (reconciliação — **já feita**, não re-perseguir).
