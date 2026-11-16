---
date: "2026-11-15"
hour: "21:30 BRT"
duration: "3h"
topic: "Smoke dos clientes vivos (ROTA LIVRE + WR2) estendendo o screen-smoke e o sells:smoke-daily"
authors: [W, C]
outcomes:
  - "Job smoke-clientes no screen-smoke-after-merge: 14 telas dos dois clientes em todo deploy, só leitura por máquina, ALERT por cliente (#10925)"
  - "sells:smoke-daily acusa ROTA LIVRE sem venda em 5 dias; WR2 informativo (#10926)"
  - "Label affects/rota-livre e affects/wr2 por PR (#10927)"
  - "SMOKE.md dos dois clientes + correção datada do canon: biz=1 é a WR2 real"
prs: [10925, 10926, 10927]
us: []
related_adrs:
  - "0164-screen-review-pdca-tela-smoke-pos-merge"
  - "0358-doutrina-de-teste-tenant-98-supersede-0101"
  - "0336-gates-design-promocao-por-mordida-provada-emenda-0314"
---

# 2026-11-15 — Smoke dos clientes vivos

## Pedido

Guardião da ROTA LIVRE e da WR2: smoke automático pós-deploy, documentação por cliente, cruzar
PRs com impacto nos dois e caminho para virar bloqueador.

## O que a medição mudou no plano

1. **O smoke pós-deploy já existia e estava ligado** (`screen-smoke-after-merge`, conta fictícia
   biz=99). Criar workflow novo seria máquina paralela (LC-19). [W] aprovou estender.
2. **Ele saía vermelho sempre, e nunca por regressão:** nas últimas 40 runs, as 6 que executaram
   reprovaram por `NÃO MEDIDO` (Page sem rota). O fail-closed veio do #7648 e ficou intacto; o
   sinal de cliente foi para um job próprio.
3. **Premissas do pedido corrigidas:** a ROTA LIVRE é vestuário (sells, cliente, financeiro, pelo
   `perfil.yml`), sem m², ordem de produção ou Copiloto.
4. **[W] declarou biz=1 = WR2 real.** Medido em produção (só contagem): zero marcações do biz=1
   em 365 dias, 2 na produção inteira (outro business), 2 importações. O alarme de "WR2 sem
   marcação" não foi armado porque nasceria vermelho para sempre.
5. **Janela da ROTA LIVRE medida:** 332 dias com venda em 365, maior lacuna 3 dias → 5 dias, zero
   alarme falso no ano.
6. **O login da conta do smoke está quebrado em produção.** O dispatch no branch abriu 0 de 12
   telas e saiu vermelho com `NAO-EXERCIDA` (canário). Estava escondido porque as runs antigas
   morriam no `NÃO MEDIDO` antes do login. Seletores conferidos contra `Site/Login.tsx`; causa
   provável é credencial. Conserto é do [W].

## Erros meus, pegos antes do merge

- Health gate copiado sem o `echo` final: `[ -z "$OK" ] && {…}` como última linha reprovava com
  prod em 200 (run 43777006880). Consertado com `if`.
- Barras invertidas colapsando no transporte (LC-26) duas vezes ao escrever PHP e YAML por
  Python; resolvido com a ferramenta de edição e `chr(92)`.
- `str.count` de linha do YAML casando também a linha mais indentada; ancorado na quebra de linha.

## Defesas novas

- Só leitura por máquina no modo clientes: requisição que não seja GET/HEAD é abortada e a tela
  vira QUEBRADO. Lidas as escritas automáticas do shell (presença, tema, consentimento): todas
  por clique.
- Selftests com mutantes mortos (`select-routes.test.mjs`, `affects-clientes --selftest`).
