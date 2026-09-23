---
date: "2026-09-23"
hour: "15:10 BRT"
topic: "Série de layout do Financeiro (FIN-3..FIN-9), FIN-6b e as duas correções do Balancete"
authors: [W, C]
outcomes:
  - "7 telas do Financeiro na forma do protótipo, cada uma com smoke de valores em produção"
  - "FIN-6b: Lanç. mês e Saldo mês no Plano de contas, provados por dois caminhos"
  - "Aba Balancete do DRE: saiu de 500 (#7831) e de mês zerado (#7833)"
  - "FIN-9 fechada por medição, sem mudança de tela"
prs: [7776, 7779, 7784, 7786, 7795, 7806, 7807, 7811, 7819, 7823, 7826, 7829, 7831, 7833, 7835]
related_adrs: [0409-zero-baseline-de-tolerancia-conformidade-absoluta, 0410-ratificacao-zero-baseline-no-funil-design]
---

# Sessão — série de layout do Financeiro, FIN-6b e Balancete

## TL;DR

A série `migracao-layout-em-ondas` do Financeiro foi executada onda a onda até o fim. Cada tela
teve a lista de valores de produção capturada ANTES e comparada DEPOIS do deploy (mesmos números,
mesmo hash). A FIN-6b trouxe dinheiro para a tela, então seguiu a regra mestre: dois caminhos
independentes e tabela antes→depois aprovada por [W]. Provar a FIN-6b em produção revelou dois
defeitos de valor antigos no balancete, corrigidos com teste que reproduz cada um.
Estado para a próxima sessão: [handoff 14:59](../handoffs/2026-09-23-1459-serie-financeiro-layout-fin6b-balancete.md).

## O que foi feito

- **FIN-3 Impostos, FIN-4 Conciliação, FIN-5 Cobrança, FIN-6 Plano de contas, FIN-8 Unificado**:
  forma do protótipo. A captura de referência de cada tela foi autorizada e aprovada por [W]
  (ADR 0409/0410) e registrada no comparativo da tela.
- **FIN-7 Prova Viva**: removida por [W].
- **FIN-9 (13 telas sem âncora)**: `pt-conformance` com 0 divergências; os 4 alarmes do
  `reconcile-triplet` foram conferidos no código e no charter e são falso-positivos. Registrado no
  RUNBOOK §12.6.
- **FIN-6b**: `DreService::movimentoMesPorConta` na mesma base do DRE; prop deferida; `UC-FPC-05..07`.
- **Balancete**: #7831 (TypeError com código só de dígitos) e #7833 (conta não-folha ignorada;
  totais contam cada título uma vez).
- **Fora da série, achados no caminho**: #7807 (nome acessível do botão de usuário), #7823 (dois
  testes de browser que não podiam passar), #7826 (referência visual do DRE velha desde o #7789).

## Aprendizados

1. **Deploy leve pode esconder mudança de runtime.** A migração do #7789 e o bundle do Plano de
   contas ficaram de fora porque o deploy completo foi cancelado na fila e o seguinte foi
   "sync leve". Sintoma: código no servidor novo, tela velha (ou 500). O `git rev-parse HEAD` do
   servidor não prova o bundle — conferir no DOM. A correção de fundo veio no #7820 (outra sessão).
2. **Provar valor em produção acha defeito que o CI não vê.** O `TypeError` do balancete só aparece
   com código de conta só de dígitos, formato do plano BR real e ausente das fixtures ("9.9.X").
   Meu método novo tinha o mesmo defeito; a fixture passou a ter pai numérico.
3. **Assert que não discrimina passa com o bug.** O primeiro rascunho do teste do total do balancete
   aceitaria dupla contagem ("crédito ≥ 250"); virou delta exato (+250, não +500).
4. **Barra invertida em heredoc colapsou duas vezes** (Python recebendo `App\User` com uma barra só).
   Nas duas o script falhou antes de gravar; a saída foi montar a barra com `chr(92)` ou usar o
   Write. Conferência de bytes de controle rodou em todo arquivo PHP escrito por script.
5. **Catraca que conta texto conta comentário.** A `foundation-ratchet` subiu por um comentário que
   citava `Business::first()` para explicar por que o teste não o usa.

## O que ficou aberto

Ver `next_steps` do handoff: Relatórios × DRE, itens de backend da Cobrança, conta 1.2.1 como folha
(cadastro), regerar fluxo visual de uma tela só, e o `reconcile-triplet` com nome errado.
