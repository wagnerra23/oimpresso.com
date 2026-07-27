---
date: "2026-07-27"
topic: "Loop IA-OS — 8 PRs consertando mecanismos que afirmavam o que não faziam"
authors: [C]
module: Jana
tags: [loop-ia-os, lc-08, lc-11, permissoes, ragas, lgpd, governanca]
pii: false
---

# Loop IA-OS — os mecanismos que afirmavam o que não faziam

> **Pergunta que abriu a sessão ([W]):** *"o ciclo completo da minha IA foi finalizado?"*
> **Resposta medida:** não — e o instrumento que dizia "sim" estava quebrado.

## O fio condutor

Oito PRs, um só defeito em oito disfarces: **um mecanismo afirmava uma coisa e fazia
outra**. Nenhum estava quebrado de forma visível. Todos passavam despercebidos porque
quem olhava era admin, porque ninguém confrontava a **saída** com a **fonte**, ou
porque o número truncado ficava três linhas abaixo do número inteiro.

| PR | O mecanismo dizia | O que fazia |
|---|---|---|
| [#4833](https://github.com/wagnerra23/oimpresso.com/pull/4833) `36d7e37dae` | "LOOP FECHADO — nada a fazer" | item #6 aberto; o `done:false` do manifesto era ignorado em `detect: file_any` |
| [#4844](https://github.com/wagnerra23/oimpresso.com/pull/4844) `96cb18230d` | README: sentinel mede a qualidade da Jana | mede `faithfulness(q, gt, gt)` = 1.0 por construção |
| [#4849](https://github.com/wagnerra23/oimpresso.com/pull/4849) `88219cd922` | — (medidor novo) | 42 permissões órfãs, 66 declaradas-sem-uso, 5 do rename `copiloto.*` |
| [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853) `632c5182e2` | menu liberava o item | rota negava: menu `jana.*`, controller `copiloto.*` |
| [#4856](https://github.com/wagnerra23/oimpresso.com/pull/4856) `a4e6cd3729` | pendente ou feito (só 2 estados) | item descartado por [W] não tinha como ser representado |
| [#4857](https://github.com/wagnerra23/oimpresso.com/pull/4857) `6232171c49` | eval tem alerta `onFailure` | `onFailure` é do scheduler; quem invoca é cron → o vermelho morria no log |
| [#4859](https://github.com/wagnerra23/oimpresso.com/pull/4859) `4a603ac91e` | 5 permissões declaradas protegiam `/ia` | grupo sem `can:` nenhum; menu escondido ≠ rota protegida |
| [#4860](https://github.com/wagnerra23/oimpresso.com/pull/4860) `4e93790dc1` | `n_failed: 20` | `failures` carregava 10 — corte silencioso |

## Os 4 itens do loop, ao fim

`NADA PENDENTE — 3 entregue(s), 1 descartado(s) por decisão [W]`

O **#6 (LGPD purge) virou won't-do**: [W] decidiu que num ERP não se apaga PII — o
controle migra de retenção pra **acesso**. Medido antes de registrar: as 15 entidades
de `retention.php` são todas `jana_*`/MCP/derivado (zero `contacts`/`transactions`/
`nfe_*`); o que o purge apagaria é o que a Larissa **digitou no chat**, que [W]
classificou como podendo conter dado do cliente dela. Fica vivo o caminho **sob
demanda** (`LgpdEsquecerTitularTool`, Art. 18 §VI) — matar a varredura automática não
mata a obrigação legal. Lápide em `proibicoes.md` §5.

## O que NÃO fechou (e é o ponto)

1. **O manifesto ainda mede presença.** Os 3 `[OK]` restantes saem de `detect: file_any`
   = o arquivo existe. Consertei o caso em que isso mentia, não o critério.
2. **Nada do que entreguei foi exercitado em produção.** O elo do alerta, o fim do
   corte e o gate são código correto e **não-provado**. A primeira prova real é a
   corrida de **domingo 2026-08-02 06:00**.
3. **`context_recall 0.3461 < piso 0.36`** desde 26/07 — diagnóstico melhor, doença igual.
4. **`ContextSnapshotService` scopa só por `business_id`** (6 queries, zero
   `created_by`/`view_own_*`): vendedora com `view_own_sell_only` recebe, pela Jana, o
   faturamento da loja inteira. **Não** é vazamento entre empresas — é entre usuários
   da mesma. Registrado na proposta de permissões, não fechado.

## Erros meus, medidos e corrigidos na própria sessão

Registro porque são a evidência de que o método funciona quando se deixa a medição falar.

- **Inflei contagem dentro de uma lápide sobre não inflar contagem** — escrevi "3ª
  instância de presence-gate em produção" incluindo o lint `toHaveKey`, cuja lápide diz
  *"morreu antes de nascer, por medição"*. São 2. Pego pelo adversário **antes** do commit.
- **"O comando só emite agregados"** — errado: grepei `questions`/`per_question`/
  `results` e não `failures`. O dado por pergunta sempre existiu, truncado.
- **Commitei na branch errada** — o fix do gate foi parar na branch do item 4. O hook
  `block-destructive` barrou meu `--force-with-lease`; usei `revert` + `cherry-pick`.
- **Quase falsifiquei história** — o renome `copiloto.*→jana.*` pegou 146 arquivos,
  incluindo **34 de `memory/`** (5 ADRs aceitas, handoffs, session logs). Revertido e
  refeito com escopo executável.
- **Li a fila do CI errado** — `gh run list --limit 40` devolve os mais recentes;
  concluí "0 rodando" quando havia **10**. Testei 2 hipóteses de conserto e **as duas
  morreram na medição** (92/113 workflows já têm `cancel-in-progress`; 1 par duplicado
  em ~120 runs). Não havia máquina pra consertar.
- **Meu teste ia nascer morto** — `JanaAccessGateTest` não estava na allowlist da lane
  `jana-pest.yml`, e `Modules/Jana` não está na matriz do `modules-pest`. Nunca rodaria.

## Achado colateral que vale sozinho

O gate `can:jana.access` quebrou **6 testes existentes** com `Expected 404 but received
403`. Isso **provou** que ele morde. O conserto foi dar a pré-condição (`jana.access` no
`beforeEach`), não enfraquecer a asserção: o contrato virou *"usuário COM acesso ao
módulo ainda não alcança dado de outra empresa"*.

E o `Gate::before` (`AuthServiceProvider:34-47`) devolve `true` em qualquer ability pra
`Admin#{business_id}` — **permissão de IA só morde funcionário, nunca o dono**. Travado
por teste pra ninguém ler o gate como "agora o admin também é barrado".

## Refs

`proibicoes.md` §5 2026-07-27 (2 lápides) · `LICOES_CODE.md` LC-11 3→4 ·
proposta `2026-07-27-permissoes-da-jana-subtrair-e-ligar.md` ·
pesquisa `2026-07-27-arte-permissoes-ia-erp.md` · US-COPI-140 · US-COPI-143 · ADR 0216 · ADR 0318
