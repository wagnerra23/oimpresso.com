---
sessao: "05"
titulo: "Catraca da sonda de tema escuro nas Pages de produção — saída da thread"
autor: "[CL]"
criado: 2026-10-13
thread: 05-baseline.md
veredito: "Catraca ligada em job próprio. Baseline nasceu com as 19 Pages da _saida-02 e baixou para 9 no mesmo PR, porque a thread 03 zerou 18 entradas. Controle com 1 superfície clara numa Page limpa saiu vermelho."
---

# _saída 05 · Catraca sobre a sonda de produção

PR [#9265](https://github.com/wagnerra23/oimpresso.com/pull/9265).

## 1 · O que ficou

- **Baseline** `scripts/design/tema-escuro-producao-baseline.json`, formato `pages: { <Page>: { <seletor>: <n> } }`, só com Page que tem superfície clara. Gravada pela sonda (`--producao <dir> --write-baseline-producao`), nunca à mão.
- **Catraca** `--producao <dir> --catraca`: reprova (exit 1) Page medida com seletor claro fora da baseline (`PAGE NOVA` / seletor novo) e contagem que sobe (`SUBIU`). Contagem que desce não reprova: imprime `↓ … baixe a baseline`, e o PR baixa. Page não medida não conta; o exit 2 ("NÃO MEDI") continua valendo.
- **`[class*=swatch]` na allowlist** (pedido da `_saida-04`); `cmp-grade :: span.gmi-swatch` saiu da baseline do espelho.
- **Job próprio** `tema-escuro-producao` no `visual-regression.yml` (nome do check: `tema-escuro · Pages de produção vs baseline`). Roda em PR que toca Pages ou CSS do núcleo e de `Modules/*/Resources/`, a sonda, o teste ou a baseline; fora disso, skip-as-pass. Sem `continue-on-error`: vermelho é vermelho (D2).
- **Preparação comum**: Setup Laravel e semente saíram do YAML, literais, para `scripts/tests/visreg-ci-setup.sh`, que os dois jobs chamam.

## 2 · Por que job próprio

Nos 60 runs de PR mais recentes, os jobs pesados verdes do `visual-regression` levaram 1048–1316 s contra o teto de 1500 s, e a sonda custa ~225 s (run 37608542269). Somada ao job, 3 dos 13 verdes passariam do teto. No PR, o job próprio levou 401 s com a preparação inteira.

## 3 · Prova

| run | o que mostra |
|---|---|
| [38455636963](https://github.com/wagnerra23/oimpresso.com/actions/runs/38455636963) | verde · Pest 75 passed (150 assertions) · 75 Pages medidas · `catraca: 19 Page(s) na baseline · 0 acima · 18 abaixo` |
| [38458067977](https://github.com/wagnerra23/oimpresso.com/actions/runs/38458067977) | **controle vermelho** · `div` branco proposital em `superadmin/Pacotes` · `PAGE NOVA superadmin/Pacotes /superadmin/packages · div.tema-escuro-controle-05 0→1` · exit 1 |
| [38459929893](https://github.com/wagnerra23/oimpresso.com/actions/runs/38459929893) | controle revertido · verde · 75 Pages · `catraca: 9 Page(s) na baseline · 0 acima · 0 abaixo` |

Local: `--selftest` 28/28 (8 da catraca) · `tema-escuro-probe.test.mjs` 7/7 pelo CLI de fora (baseline igual → 0; 1 claro numa Page limpa → 1; baseline ausente → 2).

## 4 · A baseline: de 19 para 9

O 1º run mostrou 18 entradas em 0, todas consertadas pela thread 03 (as 7 do Fiscal no `footer.fx-shell-foot`, `Sells/Index`, a placa do `Sells/CreateV3`, `Financeiro/Cobranca` e as `fin-filter-cb`). A regra do 05 manda o PR baixar; a baseline foi regravada a partir do artefato desse run. Ficaram:

| Page | seletor · n |
|---|---|
| Compras | `div` ×2 · `select` ×1 |
| Financeiro/Dre | `div` ×1 |
| Financeiro/Fluxo | `div` ×1 |
| Financeiro/PlanoContas | `div.flex.min-w-0` ×4 |
| Financeiro/Unificado | `span.inline-grid.place-items-center` ×1 |
| Governance/Dashboard | `span.inline-flex.w-fit` ×1 |
| Manufacturing/Settings | `span.pointer-events-none.block` ×2 |
| Oficina/OS | `span.text-xs.px-1.5` ×6 |
| StockTransfer/Create | `span.inline-flex.h-5` ×2 |

## 5 · Não feito, e por quê

- **Required.** O check nasce no `visual-regression.yml`, que é advisory; entrar na lista de required é flip na branch protection, fora desta thread.
- **Componentes compartilhados.** O gatilho segue o 05 (Pages e CSS). Um PR que só mexe em `resources/js/Components/**` não dispara o job, embora possa pôr superfície clara numa Page.
- **Universo.** Continuam as telas de `tests/Browser/visreg-screens.json` (75 hoje); ampliar é ampliar o manifesto.

## 6 · Prefixo tocado

`scripts/design/tema-escuro-*`, `scripts/tests/visreg-ci-setup.sh` e este recibo. Fora do prefixo: `.github/workflows/visual-regression.yml` (o job novo e a extração da preparação). O commit de controle em `Modules/Superadmin/…/Pacotes/Index.tsx` foi revertido no mesmo PR.
