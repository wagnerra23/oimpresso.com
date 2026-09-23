---
date: "2026-09-23"
time: "1459"
slug: "serie-financeiro-layout-fin6b-balancete"
tldr: "Série de layout do Financeiro fechada e conferida em produção (DRE, Fluxo, Impostos, Conciliação, Cobrança, Plano de contas, Unificado). FIN-6b pôs Lanç. mês/Saldo mês no Plano de contas com prova por dois caminhos. A aba Balancete do DRE saiu de 500 e de 'mês zerado'. FIN-9 fechou por medição. Achados: pipeline de deploy perdia migração (DRE ficou 500; migração rodada à mão, correção veio no #7820 de outra sessão)."
decided_by: [W]
cycle: null
prs: [7776, 7779, 7784, 7786, 7795, 7806, 7807, 7811, 7819, 7823, 7826, 7829, 7831, 7833]
us: []
next_steps:
  - "Decisão [W]: tela de Relatórios segue o DRE ou não (RUNBOOK-paridade-ondas §4.3)"
  - "Decisão [W]: itens da Cobrança que dependem de backend (ticket médio etc., cobranca-index-gap.md)"
  - "Fluxo visual `selecionar-lote · compact` do Unificado segue acima de τ_alto: só regenera no update global, que não cabe no timeout do job — conserto de fundo é o gate aceitar regerar fluxo de uma tela"
  - "Cadastro: conta 1.2.1 da empresa 1 está como não-folha com os títulos do mês — hoje o código soma certo; marcar como folha é decisão de dado, não de código"
  - "reconcile-triplet.mjs com nome de tela errado compara vazio com vazio e diz CONFORME (registrado no RUNBOOK §12.6, não consertado)"
related_adrs: ["0130-handoff-append-only-mcp-first", "0409-zero-baseline-de-tolerancia-conformidade-absoluta", "0410-ratificacao-zero-baseline-no-funil-design"]
---

# Handoff 2026-09-23 14:59 BRT — Série de layout do Financeiro, FIN-6b e Balancete

## TL;DR

A série `migracao-layout-em-ondas` do Financeiro terminou: as 7 telas com protótipo estão em produção na forma nova, cada uma conferida por smoke contra a lista de valores de antes (números idênticos, hash igual). A FIN-6b acrescentou as colunas **Lanç. mês** e **Saldo mês** ao Plano de contas, pela regra mestre de valor (dois caminhos + antes→depois aprovado por [W]). No caminho apareceram dois defeitos de valor em produção, os dois corrigidos: a **aba Balancete do DRE dava 500** (#7831) e, depois disso, **mostrava o mês zerado** (#7833). A FIN-9 (13 telas sem âncora) fechou por medição, sem mudar tela.

## Estado por onda

| Onda | Tela | PR | Produção |
|---|---|---|---|
| FIN-3 | Impostos | #7776 | smoke OK · 109 números, hash igual |
| FIN-4a/4b | Conciliação | #7784 (o #7779 foi fechado: conteúdo absorvido pelo #7784) | smoke OK · KPIs iguais (empresa 1 sem extrato importado) |
| FIN-5 | Cobrança | #7786 | smoke OK · 104 números e 100 linhas, hash igual |
| FIN-6 | Plano de contas | #7795 | smoke OK · forma nova, 161 contas |
| FIN-6b | Plano de contas: Lanç. mês / Saldo mês | #7829 | smoke OK · bate com a tabela aprovada |
| ~~FIN-7~~ | Prova Viva | — | removida por [W]: "isso não existe" |
| FIN-8 | Unificado | #7806 (+ registro #7819) | smoke OK · forma medida no DOM |
| FIN-9 | 13 sem âncora | #7811 | fechou por medição (§12.6 do RUNBOOK) |

DRE (FIN-1) e Fluxo (FIN-2) são de antes deste trecho; o smoke deles foi refeito hoje depois do deploy: 40 e 6+37 números, hashes iguais.

## Correções de valor (regra mestre)

- **FIN-6b — `DreService::movimentoMesPorConta`**: mesma base do DRE (competência, sem cancelados), saldo com sinal pelo tipo do título, pai soma tudo abaixo dele. Prova: `UC-FPC-05..07` (à mão + balancete) e, em produção, algoritmo × consultas separadas de receber/pagar com rollup fora do PHP — 0 divergências. Valores mostrados ao [W] fora do git.
- **#7831 — Balancete 500**: código de conta só com dígitos vira chave INT no array PHP e o `str_starts_with` estourava `TypeError` sob `strict_types`. Cast pra string; teste reproduz (folha numérica + um pai).
- **#7833 — Balancete zerado**: só folhas (`aceita_lancamento`) alimentavam os pais; título em conta não-folha sumia. Agora cada conta = o lançado nela + tudo abaixo, e os totais contam cada título uma vez. Teste com delta exato do total (pega dupla contagem). Aprovado por [W] com antes→depois.

## Incidentes do dia

- **DRE 500 em produção** — a migração do #7789 (outra sessão) não rodou: o deploy completo foi cancelado na fila e o seguinte foi "sync leve". Rodei por SSH só a migração versionada (coluna aditiva e idempotente). Mesmo mecanismo segurou o bundle do Plano de contas (resolvido com `workflow_dispatch`). A correção do pipeline veio no **#7820** (outra sessão, handoff 14:08).
- **Referência visual do DRE velha** desde o #7789 (só backend → gate não comparou): regerada com aprovação [W] (#7826).
- **Testes quebrados no main**: `UC-RECIPE-01` (placeholder lido com `assertSee`) e `UC-DASH-17` (âncoras da sidebar misturadas + `pendencias` fora da ordem canônica) — #7823.
- **Acessibilidade**: botão de usuário da sidebar sem nome acessível no tenant sem nome — #7807.

## Estado MCP no momento do fechamento

Consultado às 14:59 BRT:
- `cycles-active` → nenhum cycle ativo em COPI.
- `my-work` → sem tasks ativas para @wr23.
- `sessions-recent limit:3` → três estados da arte de 2026-08-22 (IA gerando UI, escala de telas, fidelidade design→produção), indexados hoje.
- `decisions-search` → relevantes: ADR 0409 e 0410 (zero baseline de tolerância; toda captura desta sessão teve autorização [W] explícita, registrada no comparativo de cada tela).

## Para retomar

Nenhum PR desta série aberto. As pendências estão em `next_steps` do frontmatter; todas dependem de decisão [W] ou são mudança de ferramenta com PR próprio.
