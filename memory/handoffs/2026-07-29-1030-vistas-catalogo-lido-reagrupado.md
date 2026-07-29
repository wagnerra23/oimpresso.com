---
date: "2026-07-29"
time: "10:30"
slug: "vistas-catalogo-lido-reagrupado"
tldr: "As 21 vistas agrupadas pelo titulo foram abertas e lidas; 3 estavam no tema errado, 2 eram planos e nao retratos, a duvida manual x historia caiu por prova literal e a ordem das grades de 07-17 estava invertida. 24 URLs preservadas identicas."
decided_by: ["W"]
prs: [5022]
next_steps:
  - "Conferir gh pr checks 5022 verde antes de mergear (86 pending no fechamento)"
  - "Se algum check reprovar, investigar gh run view --log-failed antes de qualquer merge --admin"
related_adrs:
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
---

# Handoff — catálogo de vistas lido por dentro e reagrupado

## O estado em uma frase

O [`VISTAS-PUBLICADAS.md`](../reference/VISTAS-PUBLICADAS.md) declarava que **21 das 24 vistas estavam
agrupadas pelo título**. Todas foram abertas; o agrupamento por título errou em 3 temas, 2 naturezas,
1 ordem e 1 dúvida em aberto. PR [#5022](https://github.com/wagnerra23/oimpresso.com/pull/5022) aberto
com **1 arquivo**, CI ainda rodando ao fechar este handoff.

## O que mudou no catálogo

| Vista | Estava | Ficou |
|---|---|---|
| Máquina de entrada | `viva` em *conhecimento e memória* | tema próprio — ingestão de sinal externo |
| Memória do processo | mesmo tema | tema próprio — auditoria de enforcement |
| Arquitetura oimpresso | *sistema inteiro* | tema próprio — arquitetura **de código** |
| 2 planos (07-20) | espalhados, 1 como histórica | tema *planos de campanha*, ambos `viva` |
| manual × história | dúvida anotada | 2 temas — trilogia provada pelo rodapé |
| 2 grades de 07-17 | ordem invertida | corrigida; escopo marcado por linha |
| 3 vistas dark | itens soltos | sequência #3981→#3982→#3983 |

**A mais cara era a Máquina de entrada:** `viva` no tema errado, fazia as três vistas legítimas de
organização do conhecimento parecerem históricas dela.

## O que sustenta cada correção (não é opinião)

- **Trilogia:** o rodapé do manual diz literal *"o 3º da família (Mapa = o quê · Guia = como chegou ·
  Manual = como operar)"* e as três se linkam entre si.
- **Ordem das grades:** a parcial de 07-17 diz *"▲ era 7,0 · grade da manhã"* e se declara *"rodada
  parcial · 1 de 11 dimensões"*.
- **Onda dark:** cada uma cita o PR da etapa e pergunta *"aprova pra mergear?"* — são pedidos
  encadeados, não versões.

## Preservação verificada

- **24 URLs idênticas** ao original — `diff` de UUIDs extraídos dos dois arquivos.
- **16/16 links relativos resolvem** — conferido antes do CI, porque `deadlink-gate` é required e o
  PR adiciona 8 links novos.
- Nada apagado, nada despublicado, hook `vista-publicada-padrao.mjs` intocado.

## Limite declarado

Ler por dentro corrigiu o **agrupamento**, não re-verificou o **conteúdo** das vistas contra o repo de
hoje. Está escrito na seção de confiabilidade do próprio catálogo, não só aqui.

## Achado que sobreviveu ao agrupamento

No tema IA a linhagem estava certa, mas a **histórica guarda as 6 plantas Mermaid navegáveis que a
viva não tem**. É argumento concreto pró-append-only: descartar a superada teria perdido informação
que a sucessora não carrega.

## Armadilha para a próxima sessão

**A branch `claude/xenodochial-curie-3f14fa` está 38 atrás de `main` e 7 à frente — e `git cherry`
prova que os 7 NÃO estão em `main`.** São trabalho de outra sessão (censo de IA, planta viva, LC-08
ocorrência 24). Não foram tocados e continuam lá. Quem for usar essa branch: ou abre PR pra eles, ou
rebaseia — mas não os misture com outro assunto.

## Estado MCP no momento do fechamento

- `cycles-active` → **Nenhum cycle ATIVO em COPI.**
- `my-work` (@wagner) → **8 tasks, todas em REVIEW**: US-COPI-123 `p0` · US-TR-309/310/305/306 `p1` ·
  US-PG-008 `p1` · US-PROD-027 `p1` · US-INFRA-023 `p1`. Nenhuma tocada nesta sessão.
- `decisions-search` → sem ADR nova no intervalo; o trabalho é catálogo curado, não decisão.
