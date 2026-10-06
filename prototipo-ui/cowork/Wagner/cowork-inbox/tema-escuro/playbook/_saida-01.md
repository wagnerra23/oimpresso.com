---
sessao: "01"
titulo: "Sonda tema-escuro no espelho servido — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: 1ab4ab51b1
thread: 01-sonda.md
veredito: "entregue como check ADVISORY, não required. A sonda mede 194 de 194 rotas, tem sanidade, baseline de 40 achados em 21 rotas e bite-test. A D2 pede required; a promoção é flip do [W] e não foi feita."
---

# _saída 01 · Sonda tema-escuro

O código entrou no [#8759](https://github.com/wagnerra23/oimpresso.com/pull/8759), mergeado em 2026-10-06 11:23Z. Este recibo foi escrito depois, por outra sessão, a partir do que está no `main` e do run de CI daquele PR. Nada de código mudou aqui.

## 1 · Feito

| arquivo | o que é |
|---|---|
| `scripts/design/tema-escuro-probe.mjs` | sonda Playwright: serve o espelho, liga o escuro, percorre `MOCK.MENU_FLAT` + ghosts + `SUPERADMIN_MENU` e procura fundo claro em `main.main`, também com hover na 1ª dica e com uma linha selecionada |
| `scripts/design/tema-escuro-probe.test.mjs` | bite-test que chama o CLI de fora: ruim sai 1, boa sai 0, sem espelho sai 2 com "NÃO MEDI" |
| `scripts/design/tema-escuro-baseline.json` | 40 achados de 06/10, chave `rota :: seletor`; só encolhe |
| `.github/workflows/design-memory-gate.yml` | job novo `tema-escuro`, dentro de um workflow que já está no `gates-registry.json` |

O que cada item da ficha virou:

- **Claro** = L(OKLab) > 0,78 com alfa ≥ 0,5 e croma < 0,1. Alfa baixo é tinta sobre o escuro, o que resolve o falso positivo do `color-mix(… transparent)` que o índice cita. Croma alto é cor de dado (barra âmbar).
- **Allowlist declarada** no script: papel, placa, tag e o knob de switch (branco 14×14 em 12 rotas, medido em 06/10).
- **Sanidade** antes do veredito: um `<div>` branco injetado tem de ser detectado; se não for, exit 2.
- **Timeout** de 15 s por rota. Rota em timeout não tira nada do baseline; 3 ou mais sem medida dá exit 2.
- **Exit 2 = NÃO MEDI**, com mensagem de falha de ambiente. Nunca sai 0 nesse caso.

## 2 · Prova

- Bite-test nesta sessão (`node scripts/design/tema-escuro-probe.test.mjs`): **4/4 ok**, rc 0.
- Run do job no #8759 ([37451722145](https://github.com/wagnerra23/oimpresso.com/actions/runs/37451722145)): os passos "Playwright" e "tema-escuro probe" **executaram** (não ficaram `skipped`), e o log diz `194 rota(s) · 194 medida(s) · 0 sem medida · 21 com superfície clara`.
- Provas do índice: o arquivo existe; `sanidade` aparece 6 vezes no script; `tema-escuro-probe` aparece no workflow.
- FP medido pelo autor no espelho real, antes de ligar: só com L e alfa, 24 de 63 eram falsos; com croma < 0,1 e a allowlist do knob, **1 de 40** (`cmp-grade .gmi-swatch`, amostra da cor do produto). Esse número não foi refeito nesta sessão.

## 3 · Não feito, e por quê

- **Required.** O D2 diz que a sonda bloqueia o PR. O job entrou **advisory**: não tem `continue-on-error`, então fica vermelho, mas não está na proteção de branch. Promover é flip seu, e a ADR 0336 pede mordida provada em 2 PRs distintos. Até agora a contagem de mordidas reais é zero.
- **Render em todo PR.** O passo de render (alguns minutos) só roda quando o PR toca `prototipo-ui/cowork/Wagner/`, `prototipo-ui/design-system/` ou a própria sonda (diff por `HEAD^1`). O bite-test roda sempre. Se virar required, o filtro precisa existir de outro jeito, porque um required que não nasce em PR que não toca esses caminhos trava o merge (§5 2026-08-08).

## 4 · Pedido literal pro [W]

> Promover o job `tema-escuro — superfície clara no escuro, todas as rotas do espelho (advisory)` do `design-memory-gate.yml` a required, conforme a D2 de 05/10. Números para decidir: FP de 1 em 40 no espelho real, 194 de 194 rotas medidas no CI, 0 timeouts, exit 2 separado do vermelho. Mordidas reais até 06/10: 0. Antes do flip, o render precisa rodar em todo PR (ou o step de detecção precisa sair verde sem render), e o nome do job perde o "(advisory)".

## 5 · Descobertas que mudam outra sessão

- **Thread 04 (triagem no protótipo):** a lista de trabalho é o `tema-escuro-baseline.json`. São 40 achados em 21 rotas: `projects` 7 · `cms-leads` 3 · `modulos` 3 · `cms-blog`, `cms-depoimentos`, `cobranca`, `conn-docs`, `funcoes`, `kb`, `portalos`, `site`, `usuarios` 2 cada · `auditoria`, `cmp-grade`, `cms-modulo`, `comissionados`, `comissoes`, `conn-modulo`, `conn-saude`, `payment-gateways`, `tarefas` 1 cada. O padrão é um só: tokens claros fixos (pílulas pastel `0.95 0.04`, avatares, `btn.primary` e filtros `.active` com fundo `--text`). Cada correção tira a linha do baseline no mesmo PR; a sonda avisa `↓ sumiu`.
- Dos três "não triados" do índice: `dash-legacy` era knob de switch (FP, foi para a allowlist); `cobranca` (Tailwind `sky-50`/`stone-200`) e `payment-gateways` (`w-9`) são achados reais e estão no baseline.
- `cmp-grade :: span.gmi-swatch` está no baseline mas é amostra de cor do produto. A 04 deve pôr na allowlist, não "corrigir".
- **Thread 02:** a sonda serve o espelho com o `servirEspelho` do `design-diff-lote.mjs`. Para as Pages Inertia o servidor é outro; o que se reaproveita são as partes puras exportadas (conversão de cor, allowlist, comparação com baseline).

## 6 · Prefixo tocado

Neste recibo: só `prototipo-ui/cowork/Wagner/cowork-inbox/tema-escuro/playbook/_saida-01.md`. No #8759: `scripts/design/tema-escuro-probe.mjs`, `scripts/design/tema-escuro-probe.test.mjs`, `.github/workflows/design-memory-gate.yml` (prefixo da thread) e `scripts/design/tema-escuro-baseline.json`, que fica fora do `prefixo` do índice mas a ficha manda criar no mesmo PR ("Baseline no mesmo PR"). Nada em `prototipo-ui/` além deste recibo.
