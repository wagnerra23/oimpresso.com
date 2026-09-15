---
date: "2026-09-15"
time: "21:37 UTC"
slug: troca-de-alvo-invocador-e-o-0x08
tldr: "7 PRs em main. Os dois que mudam comportamento: o hook da TROCA DE ALVO (braco mecanico da session-start-check, #7347) e o INVOCADOR do maquinas-inventario --write no commit (#7365), que ataca na origem os 72-de-81 commits que omitiam a regeneracao. Mais o indice que o #7347 deixou stale por 24s (#7351), os 3 marcadores de fronteira-de-palavra colapsados em byte 0x08 (#7360) e 2 recibos no ledger (LC-08 + classe nova LC-34). Os 2 fechamentos levaram REJECT do ciclo-adversary."
cycle: CYCLE-08
prs: [7342, 7347, 7351, 7356, 7360, 7365, 7371]
decided_by: [W]
related_adrs:
  - 0119-paralelismo-sessoes-whats-active-tier-1
  - 0399-aposentar-rubrica-module-grade-gate-e-baseline
  - 0344-two-strikes-cobre-processo
next_steps:
  - "Nada pendente: os 7 PRs estao em main e nenhum ficou aberto"
  - "O hook do #7365 NAO foi observado disparando — a wiring nasce nele e o harness le o settings.json no inicio da sessao. A proxima sessao que commitar tocando `.claude/`, `scripts/governance/` ou `.github/workflows/` e quem vai produzir a primeira mordida real"
  - "[W] decidir se o `Gate:` da LC-34 sobe de `advisory` quando/se o hook do #7365 provar mordida em producao (hoje e advisory + fail-open, e por isso a LC-34 alarma na 2a ocorrencia)"
  - "Declarado e NAO consertado: o indice de maquinas carrega ~108 linhas de drift herdado; o #7365 ataca a causa, mas o passivo ja acumulado reaparece no primeiro PR que tocar maquina"
  - "Observacao de schema (nao consertada, intent proprio): o pattern de `related_adrs` no handoff.schema.json e `^[0-9]{4}-[a-z0-9-]+$`, que NAO aceita ponto — logo a ADR 0224 (`...claude-4.8-aware`) nao pode ser citada nesse campo por handoff nenhum"
---

# Handoff — do braço mecânico da troca de alvo até o invocador do `--write`

> **Fio condutor da sessão:** cada PR nasceu do defeito do anterior. O #7347 instalou um hook e
> deixou o índice de máquinas stale; o #7351 pagou isso; ao provar o #7351 eu li um campo derivado
> sem olhar o `state` e gastei 3 sondas (#7356); ao consertar um byte invisível achei mais três
> (#7360); ao fechar o buraco do `--write` (#7365) eu mesmo contaminei o índice com uma sonda
> (#7371). Não é uma sequência planejada — é o loop funcionando.

## Os 7 PRs

| PR | mergeado (UTC) | commit | o que é |
|---|---|---|---|
| [#7342](https://github.com/wagnerra23/oimpresso.com/pull/7342) | 19:16:53 | `1bf56e169` | as 5 strings do `distiller_freshness` que diziam só "doc" depois de o #7328 fazer a métrica medir a união |
| [#7347](https://github.com/wagnerra23/oimpresso.com/pull/7347) | 19:40:05 | `b93f03d1f` | **hook `whats-active-troca-de-alvo`** — braço mecânico da `session-start-check` ([ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)) |
| [#7351](https://github.com/wagnerra23/oimpresso.com/pull/7351) | 19:57:26 | `c03704d3c` | regenera o `MAQUINAS-INVENTARIO.md` que o #7347 deixou stale — perdeu o merge por **24 segundos** |
| [#7356](https://github.com/wagnerra23/oimpresso.com/pull/7356) | 20:25:32 | `eac15ad01` | recibo **LC-08**: li `zero check-runs` como evento pendente, com `state: merged` ao lado |
| [#7360](https://github.com/wagnerra23/oimpresso.com/pull/7360) | 20:37:22 | `c0add161e` | restaura os **3 marcadores de fronteira-de-palavra** que colapsaram em byte `0x08` |
| [#7365](https://github.com/wagnerra23/oimpresso.com/pull/7365) | 21:27:22 | `664d70dab` | **liga o INVOCADOR do `maquinas-inventario --write`** no commit |
| [#7371](https://github.com/wagnerra23/oimpresso.com/pull/7371) | 21:35:06 | `27767657e` | classe nova **LC-34** + lápide §5 |

⚠️ **Sobre atribuição de merge:** a API reporta `merged_by: wagnerra23` em **todos os 7**, inclusive
nos que eu apertei — o `gh` está autenticado com a credencial do [W], e o campo não distingue os
dois. Pelo que **observei**: eu mergeei o #7360 e o #7365; [W] mergeou os outros 5. Registro assim
em vez de citar o campo, que aqui não prova nada.

## O que passou a existir no main

**1. `whats-active-troca-de-alvo`** (#7347) — a `session-start-check` consultava sessões paralelas
só no `session_start`. Numa sessão longa o alvo muda horas depois, e o check já passou. O hook avisa
quando o próximo `Write/Edit` cai num alvo **novo** da sessão. Stateless (os alvos vistos saem do
transcript, idioma do `modulo-preflight-warning`), advisory, `exit 0` sempre.

FP medido **antes** de instalar: 1728 transcripts, 865 com edits classificáveis, **91,1% tocam um
alvo só** (zero nudge), 168 nudges no corpus = **0,19 por sessão**. Origem medida: quatro sessões
atacaram `distiller_freshness` no mesmo dia; duas eram minhas e viraram PR duplicado.

**2. O invocador do `--write`** (#7365) — e aqui a medição **corrigiu o pedido**: o
`maquinas-inventario` **já tinha** invocador (o `--check` advisory no CI, que é quem ficou vermelho
no #7347). Faltava alguém rodar o `--write`. Contado nos últimos 300 commits do main:

| | |
|---|---|
| tocam path coberto (`.claude/` · `scripts/governance/` · `.github/workflows/`) | **81 (27%)** |
| regeneraram o índice junto | **9** |
| **não** regeneraram | **72 (89%)** |

Custo medido: `--write` ~6,5s, `--check` ~6,5s. Por isso **não** virou `PostToolUse` de `Edit` —
seria imposto em toda edição. No commit, o filtro de path custa ~0ms e a medição só é paga nos 27%.

**O desenho central é o que ele NÃO é:** o predicado barato *"o índice está no diff?"* é exatamente
a forma que a [§5 2026-07-01](../proibicoes.md) baniu. O filtro de path só decide se vale **pagar** a
medição; quem decide é o `--check`, que mede a árvore. **Índice fresco ⇒ silêncio absoluto** — e a
mutação prova que esse assert sustenta o desenho (removendo a medição, o selftest fica vermelho).

**3. A classe LC-34** (#7371) — `derivado-de-arvore-contaminada`. Ver abaixo.

## As duas classes que registrei, e por que são diferentes

**LC-08 (#7356)** — li *"zero check-runs no meu SHA"* como *"o `synchronize` ainda não disparou"*
sem pedir o `state`, que dizia `closed`/`merged:true`. O #7347 mergeou às 19:40:05Z e meu commit do
índice foi pushado às **19:40:29Z** — 24s depois, numa branch cujo PR já estava fechado, e PR
fechado não emite `synchronize`. Gastei 3 sondas.

O que tornou a história errada **coerente**: o ref remoto *de fato* havia avançado
(`git ls-remote` = `4532077a`), então "push landou mas o evento não veio" era compatível com toda a
evidência colhida. Evidência verdadeira, conclusão falsa.

O que essa ocorrência **acrescentou** à lápide-mãe: a regra positiva dela (*"peça estado e derivado
na mesma chamada"*) **não é executável** nesse sub-caso — `commits/{sha}/check-runs` devolve
`["check_runs","total_count"]` e zero campo de estado, porque o PR e o commit são objetos em
endpoints diferentes. A regra que alcança é a outra: antes de ler **ausência ou zero** de um endpoint
escopado a um objeto, estabelecer que o objeto está num estado em que aquele endpoint **pode** ser
não-vazio.

**LC-34 (#7371)** — para provar no repo real que o hook do #7365 funciona, criei uma sonda em
`.claude/hooks/_sonda-temp.mjs`, **dentro de um dos diretórios que o gerador varre**. O `--write` a
incorporou (legitimamente: ela existia), apaguei a sonda, e o índice foi pro CI com um fantasma.

Por que **classe nova** e não recibo na LC-32: lá o defeito é *ignorar* que a ferramenta escreve;
aqui eu quis a escrita e ela estava certa — o **insumo** estava sujo. Defesas opostas (*"use leitura
pura"* × *"não suje a árvore"*), e defesa é o que a classe agrupa.

## Os dois adversários, e o que eles derrubaram

Rodei o `ciclo-adversary` antes de cada fechamento de ledger, como o canon dele manda. **Os dois
deram REJECT**, e nas duas vezes o achado central era meu erro de raciocínio, não de digitação.

**No #7356** ele rodou o comando literal da lápide-mãe e mostrou que ele **funciona**
(`gh pr view 7347 --json state,mergeStateStatus` → `{"state":"MERGED"}`). Eu ia escrever que a regra
era *inexequível* — afirmação de impossibilidade em canon é a §5 2026-09-01, que produz **silêncio**,
não vermelho. Derrubou também "número em vez de sentinela é superfície nova" (a limite (2) da mãe já
diz *"indistinguíveis pelo valor"*) e "razão mais forte pra não armar" (idêntica à dela).

**No #7371**, dois achados piores:

1. O campo `Gate:` que eu escrevi (`none pra PREVENÇÃO, …`) seria lido pelo `semGate()` do **próprio
   hook do ledger** como *"tem gate"* — ele só aceita `none` exato ou `none — <justificativa>`. A
   classe nasceria **invisível ao alarme na 2ª ocorrência**, em arquivo append-only. É literalmente o
   bug que o docblock daquele hook documenta. Corrigi para `advisory — <hook>` e verifiquei
   **importando a função real do fonte**: devolve `true` (= sem gate, alarma na 2ª), com 6 controles.
2. Minha claim de que prevenção era impossível era **espantalho**. O predicado indecidível é *"isto é
   uma sonda?"*; o decidível é *"o derivado está consistente com a árvore no instante do commit?"* —
   e **isso é exatamente o hook do #7365**. No cenário do incidente ele teria regenerado sem o
   fantasma.

## Erros meus contidos antes de publicar (não viraram recibo, mas custaram tempo)

- **`grep --hidden`** — é flag do ripgrep; o grep abortou e eu quase li "zero invocadores" como
  ausência. Refeito com `git grep` + controle positivo (§5 2026-07-30, cometida por mim).
- **MSYS mangling** em `git rev-parse <ref>:<path>` → devolveu `origin\ma…`, e eu quase concluí
  "DIFERE" em 3 arquivos. Refeito com `git ls-tree` + controle positivo.
- **run id extraído do `details_url`** com `[0-9]+$` → peguei o **job**, deu 404. E 404 não é "não
  existe", é "perguntei errado".
- **`require()` num `.mjs`** — o hook novo nasceu quebrado; pego por `node --check`.
- **regex ancorado exigindo espaço antes de `git`** — não casava `git commit` no início da string.
  Trocado por **tokenização**, porque o mega-regex também errava `git -C /repo commit` (flag com
  valor) e `-am` (cluster curto).
- **`execFileSync` no bite-test** — o hook sai 0 sempre, então o stderr do caso de sucesso nunca
  chegava ao assert: 2 asserts de mensagem estavam passando **por não-medição**. Trocado por
  `spawnSync`.
- **LC-26 duas vezes** — par de barra invertida colapsando em heredoc, ao escrever o próprio ledger.
  Resolvido escrevendo o script por ferramenta de arquivo e montando a barra por `chr(92)`.
- **Sonda que agrega páginas** — `gh api --paginate --jq '[…]|length'` imprime **um número por
  página**; a espera do CI leu `1` e `0` em linhas separadas e o guard recusou interpretar como zero,
  corretamente. Refeito contando **linhas**.
- **O `memory-schema` barrou este próprio handoff** na 1ª tentativa: `tldr` acima de 500 e um
  `related_adrs` com ponto no nome. Consertado antes de existir arquivo — a defesa que mais me
  ajudou hoje foi a que me impediu de gravar.

## Estado MCP no momento do fechamento

⚠️ **O MCP do oimpresso NÃO estava disponível nesta sessão** — declaro em vez de fingir snapshot.
`ToolSearch` por `brief-fetch`/`cycles-active`/`my-work`/`whats-active` devolveu **zero tools**
(o único servidor com falha reportada foi o `laravel-boost`, que é outro). E o
`brief-fetch-curl.mjs`, re-rodado no fechamento, caiu em **fallback por timeout**:
*"servidor MCP não respondeu no tempo"*.

O que **tenho** como snapshot, com a data honesta:

- **Brief #644**, puxado pelo hook no `SessionStart`: gerado ~2h **antes** do início da sessão
  (`cycle: —`, HITL pendente [W] = 5, ADRs 24h = 5707/5710, commits 24h = 42). É retrato de então,
  não do fechamento.
- **Medições próprias no fechamento**, todas por git/filesystem contra `origin/main` fresco:
  os 7 PRs `merged=true` com SHA conferido por `merge-base --is-ancestor`; `LC-34` e `LC-33` ambos
  presentes no tree do main (1 e 1); a lápide na fonte (1) e no §5 derivado (1);
  `licoes-code-two-strikes --reconcile` = **130/130 recibos, zero pendurados**;
  `sec5-derive --check` rc=0; `gate-selftest` **82/82**.
- **Sessões paralelas:** conferidas por `git log --remotes="origin/claude/*"` + PRs abertos, não por
  `whats-active` (indisponível). No fechamento, o PR aberto que tocava o ledger era o #7364 (rec da
  LC-08); o #7358 foi detectado **mergeado** durante a sessão, o que obrigou a re-basear a LC-34 pra
  cair depois da LC-33.

## O que fica declarado e não consertado

- **~108 linhas de drift herdado** no índice de máquinas: o #7365 ataca a causa (regenera no commit),
  mas o passivo já acumulado reaparece no primeiro PR que tocar máquina. Não há como pagar só a
  própria linha — o `--check` compara fidelidade total.
- **O hook do #7365 nunca foi observado disparando pelo harness.** A evidência de que ele morde é o
  selftest (2 camadas) + a mutação + um run manual no repo real. O disparo automático só a próxima
  sessão produz, porque a wiring nasce nele e o `settings.json` é lido no início da sessão.
- **O step de marcador de recorrência passa por construção** para a lápide da LC-34 (frontier com
  `>` estrito e 10 lápides do mesmo dia), então o verde dele não sustenta conclusão sobre ela.
- **O pattern de `related_adrs` não aceita ponto**, então a ADR 0224 (advisory × bloqueante) não pode
  ser citada nesse campo por handoff nenhum. Citada aqui no corpo. Mexer no schema é intent próprio.
