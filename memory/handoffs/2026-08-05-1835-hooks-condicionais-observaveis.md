---
date: "2026-08-05"
time: "1835 BRT"
slug: "hooks-condicionais-observaveis"
tldr: "Fecha o next_step do handoff das 14:38: dos 23 hooks advisory mudos, os 15 CONDICIONAIS ganharam tag e os não-observáveis caíram 23 → 8. Sobram os 7 banners de SessionStart (por argumento) e o diag-pretooluse-trace, que NÃO EMITE mensagem e saiu do escopo (16 → 15). Medido antes de editar: a sonda alcança SessionStart/Stop/UserPromptSubmit e stderr é canal contável. Achado: --check-aliases era promessa vazia; implementado, achou 1 alias quebrado no 1º uso."
decided_by: [W]
cycle: null
prs: [5323]
us: []
next_steps:
  - "8 não-observáveis seguem: 7 banners de SessionStart + diag-pretooluse-trace. Os banners são decisão em aberto (o corte desta leva foi por CONDICIONAL, não por advisory); o diag NUNCA deve ser tagado — não emite"
  - "⚠️ `diag-pretooluse-trace` segue WIRED (matcher `Skill|DesignSync|design-login`) embora o próprio cabeçalho mande des-registrá-lo ao terminar o diagnóstico da ADR 0315. Des-registrar mexe em settings.json = decisão [W]. NENHUM gate pega isso"
  - "zero-entrega subiu 17 → 31 por efeito da leva: 14 hooks saíram do escuro pra fila de verificação. Cada um pede 1 bite-test com payload real pra distinguir 'condição nunca satisfeita' de 'não morde mais'"
  - "`--check-aliases` agora existe e é verde no main — se algum dia ficar vermelho, é alias que parou de casar, e o hook correspondente voltou a ser indistinguível de morto"
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0344-two-strikes-cobre-processo", "0130-handoff-append-only-mcp-first"]
---

# Handoff 2026-08-05 18:35 BRT — os hooks condicionais passaram a falar

## TL;DR

Continuação direta do [handoff das 14:38](2026-08-05-1438-promocoes-required-e-hooks-observaveis.md),
cujo `next_steps` dizia: *"23 hooks advisory seguem não-observáveis — forward-only, cada um
ganha tag quando for tocado"*. Esta sessão tocou **15 de uma vez**, com critério — e a
medição prévia **tirou 1 do escopo**.

**Não-observáveis 23 → 8** · observáveis 26 → 41 · [PR #5323](https://github.com/wagnerra23/oimpresso.com/pull/5323)
mergeado com **94 pass · 0 fail**.

## O pedido, e por que ele exigiu cuidado

O prompt foi `hooks não-observáveis	34	23` — dois números colados de uma grade, sem frase.
São a linha `MEDIDO` do [#5314](https://github.com/wagnerra23/oimpresso.com/pull/5314), de
poucas horas antes, cujo corpo registra a decisão [W] de deixar **os 23 advisory
forward-only**.

Ou seja: o pedido tocava num corte deliberado do mesmo dia. Agir direto contrariaria decisão
registrada. O caminho foi medir, apresentar o custo (17 dos 23 têm `.test.mjs`) e **perguntar
com recomendação cravada**. [W] escolheu os condicionais.

## O que ficou decidido

| | |
|---|---|
| **Entram (15)** | 7 PreToolUse advisory · 4 Stop · 4 UserPromptSubmit · 1 PostToolUse — *condicional que morre é 100% invisível* |
| **Ficam de fora (7)** | banners de SessionStart — *sempre falam, então o sumiço aparece pro humano* |
| **Nunca deve entrar (1)** | `diag-pretooluse-trace` — **não emite mensagem**, só grava num log de arquivo |

## As 3 medições que precederam a 1ª edição

1. **A sonda alcança os eventos destes hooks** — o attachment `hook_success` de
   SessionStart/Stop/UserPromptSubmit carrega a saída em `"content":"…"`, a 3ª sonda. Nenhum
   dos 11 do #5314 era desses eventos: estava **não-verificado**, e sem isso a leva seria teatro.
2. **stderr é canal contável** — `block-destructive` (stderr puro) tem **279** entregas. Por
   isso o 0 do `charter-da-tela` foi lido como *"dispara pouco"* (`PreToolUse:Read` = 32
   attachments no corpus) e não como canal morto.
3. **Não existe atalho** — 6.413 attachments com `hookName` nos 375 transcripts, sempre
   `<Evento>:<matcher>` (14 valores), **nunca** o nome do script. A tag é o único identificador.

## Formas usadas (as mesmas do #5314)

- **ALIAS (2)** — `tema-owner` e `charter-da-tela` já emitiam tag; registrar dá observabilidade
  **retroativa** (o `tema-owner` entra com 8 emissões históricas em vez de zerar o rastro).
- **PREFIXO (13)** — tag no **início** da mensagem. A sonda casa no começo do valor, então tag
  depois de linha vazia não conta. Nenhuma mensagem perdeu conteúdo.

## O bite-test (o que impede isto de virar presence-gate)

`tagDe()` lê o **fonte** — tag num comentário contaria como observável com o hook mudo.
[`observabilidade-tags.test.mjs`](../../.claude/hooks/observabilidade-tags.test.mjs) dispara
os 15 (8 E2E com payload real + 5 unit + 2 alias), exige a tag **no início**, e tem 4
controles negativos provando que sabe reprovar. Cai sozinho no required `gate selftest (GT-G6)`.

⚠️ Nota pra quem for estender: **3 dos 15 saíram com 0 bytes** na primeira tentativa de E2E, e
em nenhum caso era a tag faltando — era a condição não satisfeita. Um teste que aceitasse
aquilo daria verde por não-execução (LC-13). O `audit-creates-tasks`, por exemplo, lê
`tool_input.content`, **não** o arquivo em disco.

## Achado de tabela — `--check-aliases` era promessa vazia

Prometido no cabeçalho dos ALIASES desde que nasceu (*"se alguém renomear, `--check-aliases`
acusa"*), **não existia**: a única ocorrência da string era o comentário. Como esta leva
dobrava a dependência do mecanismo (8 → 10 aliases), foi implementado.

**No primeiro uso achou um alias real quebrado:** `mcp-first-nudge` aponta pra arquivo
inexistente desde a aposentadoria do [#4587](https://github.com/wagnerra23/oimpresso.com/pull/4587)
(2026-07-20). Removido — mantê-lo faria o modo nascer **vermelho permanente**.

## Efeito colateral honesto

**Zero-entrega 17 → 31.** Tornar observável não cria entrega — move de *"não sei se funciona"*
para *"sei que não entregou na janela"*. Os 14 nessa fila são o ganho; cada um pede um
bite-test com payload real pra distinguir condição-nunca-satisfeita de não-morde-mais.

## Estado MCP no momento do fechamento

Consultado nesta ordem, antes de escrever (ADR 0130):

| Tool | Resultado |
|---|---|
| `cycles-active` | **Nenhum cycle ATIVO em COPI** |
| `my-work` | 30 tasks (1 doing · 13 review · 10 blocked · 6 todo) — **nenhuma relacionada a hooks/observabilidade**; nada a atualizar |
| `sessions-recent` (via git, MCP em fallback) | 4 session logs de 2026-08-05; o irmão `maquinas-que-existiam-e-nao-avisavam` **não cobre** os condicionais (0 menções) → log novo justificado |
| `decisions-search` | sem ADR nova nesta sessão — a leva segue ADR 0224 (advisory) e o precedente do #5314, sem decisão arquitetural nova |

⚠️ O MCP entrou em **FALLBACK** no SessionStart (`settings.local.json` não encontrado, token
indisponível); `cycles-active`/`my-work` responderam depois via ToolSearch. As linhas de
sessions/decisions foram supridas por git contra `origin/main` fresco.

## Sobre o ledger

**`LICOES_CODE.md` não foi incrementado.** A promessa vazia do `--check-aliases` é da família
da lápide §5 2026-07-27, mas foi **consertada**, não **cometida** aqui. Inflar contador por
afinidade temática é o erro que aquela mesma lápide registra na errata do próprio autor.
